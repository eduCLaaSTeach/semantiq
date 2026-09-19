<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Services;

use App\Modules\Access\Models\RoleAssignment;
use App\Modules\Access\Support\RoleCatalogue;
use App\Modules\Access\Support\RoleCode;
use App\Modules\Domains\Models\DomainOwnership;
use App\Modules\Platform\Models\User;
use App\Modules\Reviews\Models\AccessReviewItem;
use App\Modules\Reviews\Support\DecisionBasis;
use App\Modules\Reviews\Support\ReviewKind;
use Illuminate\Database\Eloquent\Builder;

/**
 * THE ONE AUTHORITY ALGORITHM. D-87, as amended by B-1a.
 *
 * Used in three places - filtering a list, authorising one item, and deciding
 * whether self-review is permitted - and NEVER STORED. Stored authority goes
 * stale: somebody who lost domain ownership yesterday would still hold a
 * reviewer row today, which is exactly the privilege drift this unit exists to
 * catch.
 *
 * PRIVILEGED AUTHORITY IS DERIVED FROM RoleCatalogue::grantableBy(), not
 * defined beside it. Because an Organisation Administrator's list excludes
 * system_administrator, "an Organisation Administrator cannot review a System
 * Administrator" holds BY DERIVATION - there is no second list that could agree
 * today and drift tomorrow. It is also why Auditor decides nothing:
 * grantableBy(Auditor) is empty, so read-only falls out of the algorithm rather
 * than being a special case somebody has to remember.
 *
 * DOMAIN OWNERSHIP GRANTS ZERO BUSINESS DATA. This class reads
 * business_domain_owners to establish standing to attest. Nothing here writes
 * it, and nothing here makes the engine read it - OwnershipGrantsNoAccessTest.
 */
final class ReviewerAuthority
{
    public function canReview(User $actor, AccessReviewItem $item): bool
    {
        return $this->basisFor($actor, $item) !== null;
    }

    /**
     * On what standing this actor may act, or null if they may not.
     *
     * The basis is what gets recorded, so owner accountability is visible in
     * the evidence afterwards rather than merely intended.
     */
    public function basisFor(User $actor, AccessReviewItem $item): ?DecisionBasis
    {
        if (! $actor->isActive()) {
            return null;
        }

        if ($actor->getKey() === $item->subject_user_id && ! $this->selfReviewPermitted($item)) {
            return null;
        }

        $basis = $item->kind === ReviewKind::Privileged
            ? $this->privilegedBasis($actor, $item)
            : $this->domainBasis($actor, $item);

        if ($basis === null) {
            return null;
        }

        // D-88: a permitted self-review is recorded as one, whatever standing
        // the actor also happens to hold. What matters in the evidence is that
        // the subject decided their own access.
        return $actor->getKey() === $item->subject_user_id ? DecisionBasis::SelfReview : $basis;
    }

    private function privilegedBasis(User $actor, AccessReviewItem $item): ?DecisionBasis
    {
        $target = $item->role_code;

        if (! $target instanceof RoleCode) {
            return null;
        }

        foreach ($this->currentRolesOf($actor) as $role) {
            if (in_array($target, RoleCatalogue::grantableBy($role), true)) {
                return $role === RoleCode::SystemAdministrator
                    ? DecisionBasis::SystemAdministrator
                    : DecisionBasis::OrganisationAdministrator;
            }
        }

        return null;
    }

    /**
     * B-1a. THE SYSTEM ADMINISTRATOR IS ELIGIBLE FOR EVERY DOMAIN ITEM, and the
     * current owner is the PREFERRED reviewer rather than the only one.
     *
     * The strict "fallback only where no owner exists" wording would have left
     * every item in an owned domain undecidable, because no business role holds
     * an administration action class and D-19 shows the sidebar to System
     * Administrators only - so the accountable owner cannot reach the screen.
     * A permanently stuck item is the failure D-88 was approved to avoid.
     */
    private function domainBasis(User $actor, AccessReviewItem $item): ?DecisionBasis
    {
        if ($item->business_domain_id !== null && $this->ownsDomain($actor, (int) $item->business_domain_id)) {
            return DecisionBasis::DomainOwner;
        }

        return in_array(RoleCode::SystemAdministrator, $this->currentRolesOf($actor), true)
            ? DecisionBasis::SystemAdministrator
            : null;
    }

    /**
     * Self-review is permitted ONLY when nobody else could do it. D-88.
     *
     * Computed at decision time from current assignments and current ownership,
     * never cached: the eligible set is exactly the thing that changes.
     */
    public function selfReviewPermitted(AccessReviewItem $item): bool
    {
        return $this->otherEligibleReviewerExists($item) === false;
    }

    public function otherEligibleReviewerExists(AccessReviewItem $item): bool
    {
        return $this->otherEligibleReviewers($item)->exists();
    }

    /** @return Builder<User> */
    public function otherEligibleReviewers(AccessReviewItem $item): Builder
    {
        $query = User::query()
            ->where('status', 'active')
            ->where('id', '!=', $item->subject_user_id);

        if ($item->kind === ReviewKind::Privileged) {
            $target = $item->role_code;
            $roles = $target instanceof RoleCode
                ? array_values(array_filter(
                    RoleCode::cases(),
                    static fn (RoleCode $r): bool => in_array($target, RoleCatalogue::grantableBy($r), true),
                ))
                : [];

            return $query->whereHas('roleAssignments', fn (Builder $q) => $q
                ->whereNull('ended_at')
                ->whereIn('role_code', array_map(static fn (RoleCode $r): string => $r->value, $roles)));
        }

        // Domain items: any current owner, or any System Administrator (B-1a).
        return $query->where(function (Builder $outer) use ($item): void {
            $outer
                ->whereIn('id', DomainOwnership::query()
                    ->select('user_id')
                    ->where('business_domain_id', $item->business_domain_id)
                    ->whereNull('ended_at'))
                ->orWhereHas('roleAssignments', fn (Builder $q) => $q
                    ->whereNull('ended_at')
                    ->where('role_code', RoleCode::SystemAdministrator->value));
        });
    }

    /**
     * Narrow a listing to the items this actor may act on.
     *
     * The permitted set is computed FIRST; every screen filter is a view over
     * it and can never widen it.
     *
     * @param  Builder<AccessReviewItem>  $query
     */
    public function scopeVisible(Builder $query, User $actor): void
    {
        if (! $actor->isActive()) {
            $query->whereRaw('1 = 0');

            return;
        }

        $roles = $this->currentRolesOf($actor);

        $reviewableRoles = [];
        foreach ($roles as $role) {
            foreach (RoleCatalogue::grantableBy($role) as $grantable) {
                $reviewableRoles[$grantable->value] = true;
            }
        }

        $isSystemAdministrator = in_array(RoleCode::SystemAdministrator, $roles, true);

        $query->where(function (Builder $outer) use ($actor, $reviewableRoles, $isSystemAdministrator): void {
            $outer->where(function (Builder $privileged) use ($reviewableRoles): void {
                $privileged->where('kind', ReviewKind::Privileged->value);

                if ($reviewableRoles === []) {
                    $privileged->whereRaw('1 = 0');

                    return;
                }

                $privileged->whereIn('role_code', array_keys($reviewableRoles));
            });

            $outer->orWhere(function (Builder $domain) use ($actor, $isSystemAdministrator): void {
                $domain->where('kind', ReviewKind::Domain->value);

                if ($isSystemAdministrator) {
                    return;
                }

                $domain->whereIn('business_domain_id', DomainOwnership::query()
                    ->select('business_domain_id')
                    ->where('user_id', $actor->getKey())
                    ->whereNull('ended_at'));
            });
        });
    }

    /** @return list<RoleCode> */
    private function currentRolesOf(User $actor): array
    {
        return RoleAssignment::query()
            ->where('user_id', $actor->getKey())
            ->whereNull('ended_at')
            ->pluck('role_code')
            ->map(static fn ($code): ?RoleCode => $code instanceof RoleCode ? $code : RoleCode::tryFrom((string) $code))
            ->filter()
            ->values()
            ->all();
    }

    private function ownsDomain(User $actor, int $domainId): bool
    {
        return DomainOwnership::query()
            ->where('business_domain_id', $domainId)
            ->where('user_id', $actor->getKey())
            ->whereNull('ended_at')
            ->exists();
    }
}
