<?php

declare(strict_types=1);

namespace App\Modules\Access\Services;

use App\Modules\Access\Models\DomainEntitlement;
use App\Modules\Access\Models\EntitlementCeiling;
use App\Modules\Access\Models\EntitlementScope;
use App\Modules\Access\Models\RoleAssignment;
use App\Modules\Access\Support\AccessViolation;
use App\Modules\Access\Support\RoleCatalogue;
use App\Modules\Access\Support\RoleCode;
use App\Modules\Platform\Models\User;
use App\Modules\Platform\Security\SecurityEventLogger;
use Illuminate\Support\Facades\DB;

/**
 * Granting and revoking roles.
 *
 * NOTHING HERE DELETES. Revoking ends a period. The row is the evidence that
 * somebody held authority and when, which is the only reason to keep assignment
 * history rather than a single current-role column.
 *
 * PARENT REVOCATION ENDS ITS CURRENT CHILDREN, IN THE SAME TRANSACTION. A
 * Manager role revoked with its Finance entitlement left orphaned would bring
 * Finance back the day the person is made a Manager again for an unrelated
 * reason - with a scope somebody set in a different context, granted by nobody,
 * appearing in no change record. Ending the children with the parent removes
 * the mechanism.
 *
 * RE-GRANTING CREATES NEW CHILDREN. It never revives old ones.
 */
final class RoleAssignmentService
{
    public function __construct(
        private readonly AdministratorSetGuard $administrators,
        private readonly SecurityEventLogger $events,
    ) {}

    /**
     * Grant a role.
     *
     * The administrator-set boundary is taken even for a grant, so that a grant
     * and a concurrent revocation of the last administrator serialise against
     * each other rather than interleaving.
     */
    public function assign(User $subject, RoleCode $role, ?int $organisationId, User $actor): RoleAssignment
    {
        $this->refuseIfRoleNotGrantableBy($role, $actor);

        // An inactive account cannot be granted authority. Reactivating it
        // would otherwise silently confer a role granted while it was dormant.
        if (! $subject->isActive()) {
            throw AccessViolation::userInactive();
        }

        $organisationId = $this->resolveOrganisation($role, $subject, $organisationId);

        return $this->administrators->serialise(function () use ($subject, $role, $organisationId, $actor): RoleAssignment {
            $held = RoleAssignment::query()
                ->where('user_id', $subject->getKey())
                ->where('role_code', $role->value)
                ->whereNull('ended_at')
                ->lockForUpdate()
                ->exists();

            if ($held) {
                throw AccessViolation::roleAlreadyHeld($role);
            }

            $assignment = RoleAssignment::query()->create([
                'user_id' => $subject->getKey(),
                'organisation_id' => $organisationId,
                'role_code' => $role,
                'assigned_at' => now(),
                'ended_at' => null,
                'assigned_by_user_id' => $actor->getKey(),
            ]);

            // A self-grant is logged as a DISTINGUISHABLE event, not as an
            // ordinary grant. It is the shape a privilege-escalation attempt
            // takes, and burying it among routine grants is how it would be
            // missed.
            $this->events->record(
                $subject->getKey() === $actor->getKey()
                    ? SecurityEventLogger::ROLE_SELF_ASSIGNED
                    : SecurityEventLogger::ROLE_ASSIGNED,
                [
                    'user_id' => $subject->getKey(),
                    'related_id' => $actor->getKey(),
                    'role' => $role->value,
                    'organisation_id' => $organisationId,
                    'entity_id' => $assignment->getKey(),
                    'result' => 'assigned',
                ],
            );

            return $assignment;
        });
    }

    /**
     * Revoke a role, ending every current child beneath it.
     *
     * ONE TRANSACTION. Revoke-then-cascade as two requests would leave a window
     * in which the role is gone but its entitlements are live - and a partial
     * state if the second failed.
     */
    public function revoke(RoleAssignment $assignment, User $actor): RoleAssignment
    {
        return $this->administrators->serialise(function (array $effective) use ($assignment, $actor): RoleAssignment {
            $locked = RoleAssignment::query()
                ->whereKey($assignment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->isCurrent()) {
                throw AccessViolation::roleNotHeld($locked->role_code);
            }

            if ($locked->role_code === RoleCode::SystemAdministrator) {
                $subject = User::query()->findOrFail($locked->user_id);

                // Decided against the set that was just locked and re-read, so
                // the loser of a race sees the winner's committed state.
                $this->administrators->refuseIfLast($effective, $subject);
            }

            $now = now();

            $this->endChildrenOf($locked, $actor, $now);

            $locked->forceFill([
                'ended_at' => $now,
                'ended_by_user_id' => $actor->getKey(),
            ])->save();

            $this->events->record(SecurityEventLogger::ROLE_REVOKED, [
                'user_id' => $locked->user_id,
                'related_id' => $actor->getKey(),
                'role' => $locked->role_code->value,
                'organisation_id' => $locked->organisation_id,
                'entity_id' => $locked->getKey(),
                'result' => 'revoked',
            ]);

            return $locked->refresh();
        });
    }

    /**
     * Replace one role with another as ONE transaction - never revoke-then-
     * assign as two requests.
     */
    public function replace(RoleAssignment $assignment, RoleCode $role, User $actor): RoleAssignment
    {
        $subject = User::query()->findOrFail($assignment->user_id);

        $this->refuseIfRoleNotGrantableBy($role, $actor);

        return DB::transaction(function () use ($assignment, $role, $subject, $actor): RoleAssignment {
            $this->revoke($assignment, $actor);

            return $this->assign($subject, $role, $assignment->organisation_id, $actor);
        });
    }

    /**
     * End every current entitlement, scope and ceiling beneath this assignment.
     *
     * Written as three statements rather than a loop of model saves because the
     * whole point is that no child survives the parent - a per-row loop that
     * throws halfway leaves exactly the orphan this prevents, and the
     * transaction would roll it back but the shape invites the mistake.
     */
    private function endChildrenOf(RoleAssignment $assignment, User $actor, mixed $now): void
    {
        $entitlementIds = DomainEntitlement::query()
            ->where('role_assignment_id', $assignment->getKey())
            ->whereNull('ended_at')
            ->pluck('id')
            ->all();

        if ($entitlementIds === []) {
            return;
        }

        EntitlementScope::query()
            ->whereIn('domain_entitlement_id', $entitlementIds)
            ->whereNull('ended_at')
            ->update(['ended_at' => $now, 'ended_by_user_id' => $actor->getKey(), 'updated_at' => $now]);

        EntitlementCeiling::query()
            ->whereIn('domain_entitlement_id', $entitlementIds)
            ->whereNull('ended_at')
            ->update(['ended_at' => $now, 'ended_by_user_id' => $actor->getKey(), 'updated_at' => $now]);

        DomainEntitlement::query()
            ->whereIn('id', $entitlementIds)
            ->whereNull('ended_at')
            ->update(['ended_at' => $now, 'ended_by_user_id' => $actor->getKey(), 'updated_at' => $now]);
    }

    /**
     * D-65 and the escalation boundary.
     *
     * An Organisation Administrator can NEVER grant or revoke
     * system_administrator. The check is an allow-list from the catalogue, so a
     * role added later is not grantable by them until somebody says so - the
     * find-and-replace outcome this unit exists to prevent.
     */
    private function refuseIfRoleNotGrantableBy(RoleCode $role, User $actor): void
    {
        $grantable = [];

        foreach (RoleAssignment::query()
            ->where('user_id', $actor->getKey())
            ->whereNull('ended_at')
            ->pluck('role_code') as $held) {
            $actorRole = $held instanceof RoleCode ? $held : RoleCode::from((string) $held);

            $grantable = array_merge($grantable, RoleCatalogue::grantableBy($actorRole));
        }

        if (! in_array($role, $grantable, true)) {
            throw AccessViolation::roleNotGrantable($role);
        }
    }

    /**
     * system_administrator is platform-scoped and MUST accept a NULL
     * organisation - bootstrap creates one before a Company Profile exists, so
     * requiring one here would make a fresh deployment unbootstrappable. Every
     * other role REQUIRES one, so a role cannot escape its tenancy boundary.
     */
    private function resolveOrganisation(RoleCode $role, User $subject, ?int $organisationId): ?int
    {
        if ($role->isPlatformScoped()) {
            if ($organisationId !== null) {
                throw AccessViolation::platformRoleWithOrganisation();
            }

            return null;
        }

        $organisationId ??= $subject->organisation_id;

        if ($organisationId === null) {
            throw AccessViolation::organisationRequired($role);
        }

        if ($subject->organisation_id !== null && $subject->organisation_id !== $organisationId) {
            throw AccessViolation::userOutsideOrganisation();
        }

        return $organisationId;
    }
}
