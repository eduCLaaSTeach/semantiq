<?php

declare(strict_types=1);

namespace App\Modules\Security\Posture\Adapters;

use App\Modules\Access\Models\DomainEntitlement;
use App\Modules\Access\Models\EntitlementCeiling;
use App\Modules\Access\Models\EntitlementScope;
use App\Modules\Access\Support\RoleCatalogue;
use App\Modules\Access\Support\RoleCode;
use App\Modules\Access\Support\ScopeType;
use App\Modules\Access\Support\Sensitivity;
use App\Modules\Domains\Models\BusinessDomain;
use App\Modules\Platform\Models\UserStatus;
use App\Modules\Security\Catalogue\ControlCatalogue;
use App\Modules\Security\Posture\Evidence;
use App\Modules\Security\Posture\PostureState;

/**
 * Per-domain posture, and PR-7.
 *
 * NO CROSS-CONTAMINATION, STRUCTURALLY. Everything below is computed by
 * evidenceFor(int $domainId) - a PURE FUNCTION OF ONE DOMAIN. There is
 * deliberately NO method that takes the whole set and partitions it, because
 * that is exactly where a mis-scoped groupBy, a missing where, or an outer
 * query reused across iterations puts one domain's rows into another's total.
 * Every query below carries business_domain_id at the TOP LEVEL, and no result
 * is computed from a collection shared across domains. N-SS21 breaks it by
 * dropping the predicate, against two domains with deliberately different
 * counts so a contamination bug changes an assertion rather than coincidentally
 * matching.
 *
 * DOMAIN OWNERSHIP IS NEVER ACCESS - D-51. PR-7 counts owners without an
 * entitlement of their own and says so in words: owning a domain grants
 * nothing. It is INFORMATION, NEVER A FAULT, and N-SS18 breaks it by making it
 * a finding.
 *
 * A DISABLED DOMAIN IS A FAIL-CLOSED SUCCESS, not a fault. Nobody reaches its
 * information. Calling that a fault teaches people to ignore the screen.
 *
 * NOTHING HERE IMPLIES DATA CLASSIFICATION OR FABRIC SECURITY EXISTS.
 * Sensitivity is a CEILING ON A GRANT, not a label on data, and there is no
 * data in Phase 1.
 */
final class DomainAdapter implements SourceAdapter
{
    public const OWNER_MISSING = 'domain.owner';

    public const PRIVILEGED_GRANTS = 'domain.privileged';

    public const INCOMPLETE = 'domain.incomplete';

    public const ENTITLEMENTS = 'domain.entitlements';

    public const BROAD_SCOPES = 'domain.broad_scopes';

    public const RESTRICTED = 'domain.restricted';

    public function answers(): array
    {
        return [ControlCatalogue::OWNERS_WITHOUT_ENTITLEMENT];
    }

    /** PR-7, across the organisation. Information, never a fault. */
    public function evidence(): array
    {
        $count = 0;

        foreach ($this->domains() as $domain) {
            $ownerId = $domain->currentOwnership?->user_id;

            if ($ownerId === null) {
                continue;
            }

            $hasOwn = DomainEntitlement::query()
                ->current()
                ->where('business_domain_id', $domain->getKey())
                ->whereHas('assignment', fn ($a) => $a->whereNull('ended_at')->where('user_id', $ownerId))
                ->exists();

            if (! $hasOwn) {
                $count++;
            }
        }

        return [Evidence::count(
            ControlCatalogue::OWNERS_WITHOUT_ENTITLEMENT,
            $count,
            'Owning a domain grants nothing. Being accountable for a domain and being able to see '
            .'its information are separate, and neither implies the other.',
        )];
    }

    /** @return list<BusinessDomain> */
    public function domains(): array
    {
        return BusinessDomain::query()
            ->with('currentOwnership')
            ->orderBy('name')
            ->get()
            ->all();
    }

    /**
     * ONE DOMAIN'S POSTURE ROWS. A pure function of one domain id.
     *
     * @return array{rows: list<array{0: string, 1: PostureState, 2: string}>, metrics: list<array{0: string, 1: int, 2: string}>}
     */
    public function evidenceFor(BusinessDomain $domain): array
    {
        $id = (int) $domain->getKey();

        $rows = [];
        $metrics = [];

        // --- Availability. A disabled domain is fail-closed, not a fault. ---
        if (! $domain->isEnabled()) {
            $rows[] = [
                self::OWNER_MISSING,
                PostureState::Healthy,
                'Disabled — nobody reaches its information.',
            ];

            $metrics[] = [self::ENTITLEMENTS, $this->entitlementCount($id), 'Entitlements are kept while the domain is switched off, and grant nothing until it is switched back on.'];

            return ['rows' => $rows, 'metrics' => $metrics];
        }

        // --- Accountability. -------------------------------------------------
        $rows[] = $domain->currentOwnership === null
            ? [
                self::OWNER_MISSING,
                PostureState::Attention,
                'Enabled, with nobody accountable for it. Assign an owner.',
            ]
            : [
                self::OWNER_MISSING,
                PostureState::Healthy,
                'Somebody is accountable for this domain. Being the owner grants no access to it.',
            ];

        // --- Privileged grants INTO this domain. -----------------------------
        $privilegedCount = $this->privilegedEntitlementCount($id);

        $rows[] = $privilegedCount === 0
            ? [
                self::PRIVILEGED_GRANTS,
                PostureState::Healthy,
                'No administrator holds an entitlement to this domain.',
            ]
            : [
                self::PRIVILEGED_GRANTS,
                PostureState::Attention,
                $privilegedCount === 1
                    ? 'One administrator also holds an entitlement to this domain. Permitted, and worth reviewing.'
                    : "{$privilegedCount} administrators also hold entitlements to this domain. Permitted, and worth reviewing.",
            ];

        // --- Incomplete grants INTO this domain. -----------------------------
        $incomplete = $this->incompleteCount($id);

        $rows[] = $incomplete === 0
            ? [
                self::INCOMPLETE,
                PostureState::Healthy,
                'Every entitlement to this domain has both a scope and a sensitivity limit.',
            ]
            : [
                self::INCOMPLETE,
                PostureState::Attention,
                $incomplete === 1
                    ? 'One entitlement to this domain is missing a scope or a sensitivity limit, so it grants nothing today.'
                    : "{$incomplete} entitlements to this domain are missing a scope or a sensitivity limit, so they grant nothing today.",
            ];

        // --- Counts. Information only. ---------------------------------------
        $metrics[] = [self::ENTITLEMENTS, $this->entitlementCount($id), 'People who have been given access to this domain through an explicit grant.'];
        $metrics[] = [self::BROAD_SCOPES, $this->broadScopeCount($id), 'Grants covering every record in this domain. A deliberate choice, not a threshold.'];
        $metrics[] = [self::RESTRICTED, $this->restrictedCount($id), 'Grants allowing the most sensitive information in this domain. Each was confirmed with a fresh Microsoft sign-in.'];

        return ['rows' => $rows, 'metrics' => $metrics];
    }

    private function entitlementCount(int $domainId): int
    {
        return DomainEntitlement::query()
            ->current()
            ->where('business_domain_id', $domainId)
            ->count();
    }

    private function privilegedEntitlementCount(int $domainId): int
    {
        $privileged = array_map(
            static fn (RoleCode $role): string => $role->value,
            RoleCatalogue::requiringStepUp(),
        );

        /*
         * SAME RULE AS PR-3, and for the same reason - see
         * GrantPathAdapter::privilegedHoldingBusinessData().
         *
         * A preserved assignment on a deactivated account must not turn THIS
         * DOMAIN amber. AccessEngine denies an inactive user at the global
         * gate, so their entitlement to this domain authorises nothing, and
         * reporting it as an administrator holding access to the domain would
         * be inventing risk from a legitimate state.
         *
         * The two call sites are kept in step deliberately: a domain reading
         * amber while the deployment-wide row reads healthy - or the reverse -
         * is the kind of disagreement that makes a reader distrust both.
         */
        return DomainEntitlement::query()
            ->current()
            ->where('business_domain_id', $domainId)
            ->whereHas('assignment', fn ($a) => $a
                ->whereNull('ended_at')
                ->whereIn('role_code', $privileged)
                ->whereHas('user', fn ($user) => $user->where('status', UserStatus::Active->value)))
            ->count();
    }

    private function incompleteCount(int $domainId): int
    {
        return DomainEntitlement::query()
            ->current()
            ->where('business_domain_id', $domainId)
            ->where(function ($query): void {
                $query
                    ->whereDoesntHave('scopes', fn ($s) => $s->whereNull('ended_at'))
                    ->orWhereDoesntHave('ceilings', fn ($c) => $c->whereNull('ended_at'));
            })
            ->count();
    }

    private function broadScopeCount(int $domainId): int
    {
        $broad = array_map(
            static fn (ScopeType $type): string => $type->value,
            array_values(array_filter(
                ScopeType::cases(),
                static fn (ScopeType $type): bool => $type->coversWholeDomain(),
            )),
        );

        return EntitlementScope::query()
            ->current()
            ->whereIn('scope_type', $broad)
            ->whereHas(
                'entitlement',
                fn ($e) => $e->whereNull('ended_at')->where('business_domain_id', $domainId),
            )
            ->count();
    }

    private function restrictedCount(int $domainId): int
    {
        return EntitlementCeiling::query()
            ->current()
            ->where('sensitivity', Sensitivity::Restricted->value)
            ->whereHas(
                'entitlement',
                fn ($e) => $e->whereNull('ended_at')->where('business_domain_id', $domainId),
            )
            ->count();
    }
}
