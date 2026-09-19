<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Services;

use App\Modules\Access\Models\DomainEntitlement;
use App\Modules\Access\Models\RoleAssignment;
use App\Modules\Access\Support\RoleCatalogue;
use App\Modules\Access\Support\RoleCode;
use App\Modules\Access\Support\ScopeType;
use App\Modules\Access\Support\Sensitivity;
use App\Modules\Platform\Models\User;
use App\Modules\Platform\Security\SecurityEventLogger;
use App\Modules\Reviews\Models\AccessReviewCycle;
use App\Modules\Reviews\Models\AccessReviewItem;
use App\Modules\Reviews\Support\Composition;
use App\Modules\Reviews\Support\ReviewKind;
use App\Modules\Reviews\Support\ReviewState;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Starts a cycle and generates its population. D-85, D-86, D-89.
 *
 * BOTH POPULATIONS ARE DERIVED FROM THE CATALOGUE, never listed. A role added
 * later with an administration class, or a scope type that later covers a whole
 * domain, is included BY CONSTRUCTION - PrivilegedPopulationIsDerivedTest and
 * DomainPopulationIsDerivedTest break if either is hard-coded. A literal list
 * would silently omit the new one, and nobody would notice until an audit asked
 * why.
 *
 * GENERATION IS IDEMPOTENT twice over: two unique indexes make re-running the
 * same cycle a no-op, and an object that already has a PENDING item in any
 * cycle is skipped, so starting a second cycle cannot raise two open questions
 * about one grant.
 */
final class ReviewCycleGenerator
{
    public function __construct(private readonly SecurityEventLogger $events) {}

    public function start(User $actor, CarbonInterface $dueAt, ?int $organisationId): AccessReviewCycle
    {
        return DB::transaction(function () use ($actor, $dueAt, $organisationId): AccessReviewCycle {
            $cycle = AccessReviewCycle::query()->create([
                'organisation_id' => $organisationId,
                'started_at' => now(),
                'due_at' => $dueAt,
                'started_by_user_id' => $actor->getKey(),
            ]);

            $generated = $this->generatePrivileged($cycle, $organisationId) + $this->generateDomain($cycle, $organisationId);

            $this->events->record(SecurityEventLogger::REVIEW_CYCLE_STARTED, [
                'entity_id' => $cycle->getKey(),
                'related_id' => $actor->getKey(),
                'organisation_id' => $organisationId,
                'result' => (string) $generated,
            ]);

            return $cycle;
        });
    }

    /**
     * D-85. Every current assignment whose role permits at least one
     * administration class, held by an active person.
     */
    private function generatePrivileged(AccessReviewCycle $cycle, ?int $organisationId): int
    {
        $roles = $this->privilegedRoleCodes();

        /*
         * SCOPED TO THIS ORGANISATION, AND THE PLATFORM ROLE HANDLED
         * DELIBERATELY.
         *
         * The System Administrator role is platform-scoped, so its assignment
         * carries no organisation_id. Leaving the filter off entirely - the
         * obvious reading of "it has no organisation" - would pull every other
         * customer's privileged access into this cycle. So an assignment
         * qualifies when it belongs to this organisation, OR when it is
         * platform-scoped AND the PERSON holding it belongs to this
         * organisation. The subject is what ties it back.
         */
        $assignments = RoleAssignment::query()
            ->whereNull('ended_at')
            ->whereIn('role_code', $roles)
            ->whereHas('user', fn ($q) => $q->where('status', 'active')->where('organisation_id', $organisationId))
            ->where(fn ($q) => $q
                ->where('organisation_id', $organisationId)
                ->orWhereNull('organisation_id'))
            ->get();

        $count = 0;

        foreach ($assignments as $assignment) {
            if ($this->alreadyPending('role_assignment_id', (int) $assignment->getKey())) {
                continue;
            }

            $roleCode = $assignment->role_code instanceof RoleCode
                ? $assignment->role_code->value
                : (string) $assignment->role_code;

            $composition = Composition::ofPrivilege($roleCode);

            $count += $this->insert([
                'access_review_cycle_id' => $cycle->getKey(),
                'organisation_id' => $organisationId,
                'kind' => ReviewKind::Privileged->value,
                'subject_user_id' => $assignment->user_id,
                'role_assignment_id' => $assignment->getKey(),
                'domain_entitlement_id' => null,
                'business_domain_id' => null,
                'role_code' => $roleCode,
                'state' => ReviewState::Pending->value,
                'due_at' => $cycle->due_at,
                'composition' => json_encode($composition, JSON_THROW_ON_ERROR),
                'composition_fingerprint' => Composition::fingerprint($composition),
            ]);
        }

        return $count;
    }

    /**
     * D-86. Sensitive by DEPTH (a Confidential or Restricted ceiling) or by
     * BREADTH (a scope that covers a whole domain or the organisation).
     *
     * Breadth is the rule P1-06's D-78 said would be written here: a
     * whole-domain scope is a legitimate, deliberate grant that carries no
     * posture state, and periodic review is exactly what it is for.
     */
    private function generateDomain(AccessReviewCycle $cycle, ?int $organisationId): int
    {
        $broadScopes = array_values(array_map(
            static fn (ScopeType $t): string => $t->value,
            array_filter(ScopeType::cases(), static fn (ScopeType $t): bool => $t->coversWholeDomain()),
        ));

        $sensitive = array_values(array_map(
            static fn (Sensitivity $s): string => $s->value,
            array_filter(
                Sensitivity::cases(),
                static fn (Sensitivity $s): bool => $s !== Sensitivity::Standard,
            ),
        ));

        $entitlements = DomainEntitlement::query()
            ->whereNull('ended_at')
            ->whereHas('assignment', fn ($q) => $q
                ->whereNull('ended_at')
                ->whereHas('user', fn ($u) => $u
                    ->where('status', 'active')
                    ->where('organisation_id', $organisationId)))
            // And the domain itself belongs to this organisation.
            ->whereHas('domain', fn ($d) => $d->where('organisation_id', $organisationId))
            ->where(function ($q) use ($sensitive, $broadScopes): void {
                $q
                    ->whereHas('ceilings', fn ($c) => $c
                        ->whereNull('ended_at')
                        ->whereIn('sensitivity', $sensitive))
                    ->orWhereHas('scopes', fn ($s) => $s
                        ->whereNull('ended_at')
                        ->whereIn('scope_type', $broadScopes));
            })
            ->with(['assignment', 'scopes', 'ceilings'])
            ->get();

        $count = 0;

        foreach ($entitlements as $entitlement) {
            if ($this->alreadyPending('domain_entitlement_id', (int) $entitlement->getKey())) {
                continue;
            }

            $composition = Composition::ofEntitlement($entitlement);

            $count += $this->insert([
                'access_review_cycle_id' => $cycle->getKey(),
                'organisation_id' => $organisationId,
                'kind' => ReviewKind::Domain->value,
                'subject_user_id' => $entitlement->assignment?->user_id,
                'role_assignment_id' => null,
                'domain_entitlement_id' => $entitlement->getKey(),
                'business_domain_id' => $entitlement->business_domain_id,
                'role_code' => (string) ($composition['role_code'] ?? ''),
                'state' => ReviewState::Pending->value,
                'due_at' => $cycle->due_at,
                'composition' => json_encode($composition, JSON_THROW_ON_ERROR),
                'composition_fingerprint' => Composition::fingerprint($composition),
            ]);
        }

        return $count;
    }

    /** @return list<string> */
    public function privilegedRoleCodes(): array
    {
        $codes = [];

        foreach (RoleCatalogue::administrationClasses() as $class) {
            foreach (RoleCatalogue::rolesPermitting($class) as $role) {
                $codes[$role->value] = true;
            }
        }

        return array_keys($codes);
    }

    private function alreadyPending(string $column, int $id): bool
    {
        return AccessReviewItem::query()
            ->where($column, $id)
            ->where('state', ReviewState::Pending->value)
            ->exists();
    }

    /** @param array<string, mixed> $row */
    private function insert(array $row): int
    {
        $now = now();

        return AccessReviewItem::query()->insertOrIgnore($row + [
            'self_review' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
