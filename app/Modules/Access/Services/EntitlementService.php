<?php

declare(strict_types=1);

namespace App\Modules\Access\Services;

use App\Modules\Access\Models\DomainEntitlement;
use App\Modules\Access\Models\EntitlementCeiling;
use App\Modules\Access\Models\EntitlementScope;
use App\Modules\Access\Models\RoleAssignment;
use App\Modules\Access\Support\AccessViolation;
use App\Modules\Access\Support\ScopeType;
use App\Modules\Access\Support\Sensitivity;
use App\Modules\Domains\Models\BusinessDomain;
use App\Modules\Organisation\Models\BusinessUnit;
use App\Modules\Organisation\Models\Team;
use App\Modules\Platform\Models\User;
use App\Modules\Platform\Security\SecurityEventLogger;
use Illuminate\Support\Facades\DB;

/**
 * Domain entitlements, scopes and sensitivity ceilings.
 *
 * THE LOCK ORDER IS PARENT BEFORE CHILD, EVERYWHERE: role assignment ->
 * entitlement -> scope or ceiling. One order in every method, so two services
 * cannot approach the same tables from opposite ends. The administrator-set
 * boundary is the one deliberate exception, and it lives in
 * AdministratorSetGuard because its invariant is about a SET rather than a
 * subject.
 *
 * NOTHING HERE DELETES, and nothing revives. Re-granting creates a new period.
 */
final class EntitlementService
{
    public function __construct(private readonly SecurityEventLogger $events) {}

    /**
     * Grant an entitlement to a domain.
     *
     * D-57: no entitlement is ever created automatically. This method is the
     * only way one comes into existence, and it takes an actor.
     */
    public function grant(RoleAssignment $assignment, BusinessDomain $domain, User $actor): DomainEntitlement
    {
        return DB::transaction(function () use ($assignment, $domain, $actor): DomainEntitlement {
            $lockedAssignment = RoleAssignment::query()
                ->whereKey($assignment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedAssignment->isCurrent()) {
                throw AccessViolation::roleNotHeld($lockedAssignment->role_code);
            }

            // An entitlement cannot cross a tenancy boundary. The assignment's
            // organisation is the authority here, not the actor's.
            if ($lockedAssignment->organisation_id !== null
                && $domain->organisation_id !== $lockedAssignment->organisation_id) {
                throw AccessViolation::domainOutsideOrganisation();
            }

            $existing = DomainEntitlement::query()
                ->where('role_assignment_id', $lockedAssignment->getKey())
                ->where('business_domain_id', $domain->getKey())
                ->whereNull('ended_at')
                ->lockForUpdate()
                ->exists();

            if ($existing) {
                throw AccessViolation::entitlementAlreadyHeld();
            }

            $entitlement = DomainEntitlement::query()->create([
                'role_assignment_id' => $lockedAssignment->getKey(),
                'business_domain_id' => $domain->getKey(),
                'granted_at' => now(),
                'ended_at' => null,
                'granted_by_user_id' => $actor->getKey(),
            ]);

            $this->events->record(SecurityEventLogger::ENTITLEMENT_GRANTED, [
                'user_id' => $lockedAssignment->user_id,
                'related_id' => $actor->getKey(),
                'role' => $lockedAssignment->role_code->value,
                'domain_id' => $domain->getKey(),
                'entity_id' => $entitlement->getKey(),
                'result' => 'granted',
            ]);

            return $entitlement;
        });
    }

    /**
     * Revoke an entitlement, ending its current scope and ceiling in the same
     * transaction.
     */
    public function revoke(DomainEntitlement $entitlement, User $actor): DomainEntitlement
    {
        return DB::transaction(function () use ($entitlement, $actor): DomainEntitlement {
            $locked = DomainEntitlement::query()
                ->whereKey($entitlement->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->isCurrent()) {
                throw AccessViolation::entitlementNotCurrent();
            }

            $now = now();

            EntitlementScope::query()
                ->where('domain_entitlement_id', $locked->getKey())
                ->whereNull('ended_at')
                ->update(['ended_at' => $now, 'ended_by_user_id' => $actor->getKey(), 'updated_at' => $now]);

            EntitlementCeiling::query()
                ->where('domain_entitlement_id', $locked->getKey())
                ->whereNull('ended_at')
                ->update(['ended_at' => $now, 'ended_by_user_id' => $actor->getKey(), 'updated_at' => $now]);

            $locked->forceFill(['ended_at' => $now, 'ended_by_user_id' => $actor->getKey()])->save();

            $this->events->record(SecurityEventLogger::ENTITLEMENT_REVOKED, [
                'user_id' => $locked->assignment->user_id,
                'related_id' => $actor->getKey(),
                'domain_id' => $locked->business_domain_id,
                'entity_id' => $locked->getKey(),
                'result' => 'revoked',
            ]);

            return $locked->refresh();
        });
    }

    /**
     * Assign a scope. SEVERAL CURRENT SCOPES ON ONE ENTITLEMENT UNION.
     *
     * A duplicate current scope for the same effective target is REFUSED: it
     * grants nothing the first does not, makes revocation ambiguous and makes
     * the screen wrong. An ENDED row with the same target does not block a new
     * period - that is an ordinary re-grant.
     */
    public function assignScope(
        DomainEntitlement $entitlement,
        ScopeType $scopeType,
        ?int $targetId,
        User $actor,
    ): EntitlementScope {
        return DB::transaction(function () use ($entitlement, $scopeType, $targetId, $actor): EntitlementScope {
            $locked = DomainEntitlement::query()
                ->whereKey($entitlement->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->isCurrent()) {
                throw AccessViolation::entitlementNotCurrent();
            }

            $target = $this->validateTarget($locked, $scopeType, $targetId);

            $duplicate = EntitlementScope::query()
                ->where('domain_entitlement_id', $locked->getKey())
                ->where('scope_type', $scopeType->value)
                ->whereNull('ended_at')
                ->when(
                    $scopeType === ScopeType::Team,
                    static fn ($query) => $query->where('team_id', $target),
                )
                ->when(
                    $scopeType === ScopeType::BusinessUnit,
                    static fn ($query) => $query->where('business_unit_id', $target),
                )
                ->lockForUpdate()
                ->exists();

            if ($duplicate) {
                throw AccessViolation::duplicateScope();
            }

            $scope = EntitlementScope::query()->create([
                'domain_entitlement_id' => $locked->getKey(),
                'scope_type' => $scopeType,
                'team_id' => $scopeType === ScopeType::Team ? $target : null,
                'business_unit_id' => $scopeType === ScopeType::BusinessUnit ? $target : null,
                'assigned_at' => now(),
                'ended_at' => null,
                'assigned_by_user_id' => $actor->getKey(),
            ]);

            $this->events->record(SecurityEventLogger::SCOPE_ASSIGNED, [
                'user_id' => $locked->assignment->user_id,
                'related_id' => $actor->getKey(),
                'domain_id' => $locked->business_domain_id,
                'scope' => $scopeType->value,
                'entity_id' => $scope->getKey(),
                'result' => 'assigned',
            ]);

            return $scope;
        });
    }

    /**
     * Revoke ONE scope. Only that period ends.
     *
     * REVOKING THE LAST SCOPE DOES NOT REVOKE THE ENTITLEMENT. The entitlement
     * stays current and becomes an incomplete, non-authorising grant path -
     * removing a child must never silently mean the parent was revoked.
     * Effective access through it becomes zero immediately, the engine returns
     * denied_scope, and the screen says "No access - scope required".
     */
    public function revokeScope(EntitlementScope $scope, User $actor): EntitlementScope
    {
        return DB::transaction(function () use ($scope, $actor): EntitlementScope {
            $entitlement = DomainEntitlement::query()
                ->whereKey($scope->domain_entitlement_id)
                ->lockForUpdate()
                ->firstOrFail();

            $locked = EntitlementScope::query()
                ->whereKey($scope->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->isCurrent()) {
                throw AccessViolation::scopeNotCurrent();
            }

            $locked->forceFill(['ended_at' => now(), 'ended_by_user_id' => $actor->getKey()])->save();

            $this->events->record(SecurityEventLogger::SCOPE_REVOKED, [
                'user_id' => $entitlement->assignment->user_id,
                'related_id' => $actor->getKey(),
                'domain_id' => $entitlement->business_domain_id,
                'scope' => $locked->scope_type->value,
                'entity_id' => $locked->getKey(),
                'result' => 'revoked',
            ]);

            return $locked->refresh();
        });
    }

    /**
     * Set the sensitivity ceiling.
     *
     * "CLEAR" IS setCeiling(Standard) - a deliberate write that leaves a
     * CURRENT standard row. There is deliberately no clearCeiling() that
     * deletes: absence is a malformed state that fails closed, and a method
     * producing it would be a method producing an invisible permissive default.
     *
     * Granting `restricted` requires step-up, which the controller enforces
     * before calling this.
     */
    public function setCeiling(
        DomainEntitlement $entitlement,
        Sensitivity $sensitivity,
        User $actor,
    ): EntitlementCeiling {
        return DB::transaction(function () use ($entitlement, $sensitivity, $actor): EntitlementCeiling {
            $locked = DomainEntitlement::query()
                ->whereKey($entitlement->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->isCurrent()) {
                throw AccessViolation::entitlementNotCurrent();
            }

            $now = now();

            // End the open period and insert the next, so the history shows
            // what the ceiling was and when it changed.
            EntitlementCeiling::query()
                ->where('domain_entitlement_id', $locked->getKey())
                ->whereNull('ended_at')
                ->lockForUpdate()
                ->update(['ended_at' => $now, 'ended_by_user_id' => $actor->getKey(), 'updated_at' => $now]);

            $ceiling = EntitlementCeiling::query()->create([
                'domain_entitlement_id' => $locked->getKey(),
                'sensitivity' => $sensitivity,
                'assigned_at' => $now,
                'ended_at' => null,
                'assigned_by_user_id' => $actor->getKey(),
            ]);

            $this->events->record(SecurityEventLogger::CEILING_SET, [
                'user_id' => $locked->assignment->user_id,
                'related_id' => $actor->getKey(),
                'domain_id' => $locked->business_domain_id,
                'sensitivity' => $sensitivity->value,
                'entity_id' => $ceiling->getKey(),
                'result' => 'set',
            ]);

            return $ceiling;
        });
    }

    /**
     * A target is REQUIRED exactly where the scope type says so, and REFUSED
     * where it does not apply.
     *
     * A team_id on an `own` scope is a stored contradiction: it would match
     * everything or nothing, neither of which anybody granted, and no screen
     * could explain it.
     */
    private function validateTarget(DomainEntitlement $entitlement, ScopeType $scopeType, ?int $targetId): ?int
    {
        if (! $scopeType->requiresTarget()) {
            if ($targetId !== null) {
                throw AccessViolation::scopeTargetNotApplicable($scopeType);
            }

            return null;
        }

        if ($targetId === null) {
            throw AccessViolation::scopeTargetRequired($scopeType);
        }

        $organisationId = $entitlement->assignment->organisation_id
            ?? $entitlement->domain->organisation_id;

        $belongs = match ($scopeType) {
            ScopeType::Team => Team::query()
                ->whereKey($targetId)
                ->whereHas('department.businessUnit', static fn ($query) => $query->where('organisation_id', $organisationId))
                ->exists(),
            ScopeType::BusinessUnit => BusinessUnit::query()
                ->whereKey($targetId)
                ->where('organisation_id', $organisationId)
                ->exists(),
            default => false,
        };

        if (! $belongs) {
            throw AccessViolation::scopeTargetOutsideOrganisation();
        }

        return $targetId;
    }
}
