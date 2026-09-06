<?php

declare(strict_types=1);

namespace App\Modules\People\Services;

use App\Modules\Access\Services\AdministratorSetGuard;
use App\Modules\Organisation\Models\ManagementRelationship;
use App\Modules\Organisation\Models\Organisation;
use App\Modules\Organisation\Models\TeamMembership;
use App\Modules\People\Models\GroupMembership;
use App\Modules\People\Support\PeopleViolation;
use App\Modules\Platform\Models\User;
use App\Modules\Platform\Models\UserStatus;
use App\Modules\Platform\Security\SecurityEventLogger;
use App\Shared\Lifecycle\PurgeDependencies;
use Illuminate\Support\Facades\DB;

/**
 * Bringing a person into SemantIQ, and taking their access away again.
 *
 * D-33 = A: the administrator types the Entra Object ID. There is no directory
 * lookup, no invitation, no pending record and no email binding, because email
 * is mutable and the identity key is not.
 *
 * NOTHING HERE GRANTS ANYTHING. No role is ever assigned here - not by this
 * class, not by any route, not by any request. P1-05 owns the role model, and
 * PeopleBoundaryTest fails the build if this file so much as mentions the
 * column.
 */
final class UserDirectoryService
{
    public function __construct(
        private readonly SecurityEventLogger $events,
        private readonly AdministratorSetGuard $administrators,
    ) {}

    /**
     * Create a person from an Object ID the administrator supplied.
     *
     * PROVIDER AND TENANT ARE NOT PARAMETERS AND NOT REQUEST INPUT. They are
     * read from configuration here. A disabled input is still a value a crafted
     * request can supply, so the safe arrangement is for the value never to
     * reach this method from outside.
     *
     * @param  array{object_id: string, email: string, display_name?: string|null}  $attributes
     */
    public function provision(Organisation $organisation, array $attributes, User $actor): User
    {
        $objectId = strtolower(trim($attributes['object_id']));
        $email = trim($attributes['email']);
        $displayName = trim((string) ($attributes['display_name'] ?? '')) ?: $email;

        $tenant = (string) config('identity.microsoft.tenant_id');

        return DB::transaction(function () use ($organisation, $objectId, $email, $displayName, $tenant, $actor): User {
            /*
             * The database constraint is the guard - users_identity_uq makes a
             * duplicate identity unrepresentable, so two administrators racing
             * produce one user and one refusal whatever the timing. This read
             * exists only so the refusal reads as a sentence rather than as a
             * constraint violation.
             */
            $existing = User::query()
                ->where('provider', 'microsoft')
                ->where('external_subject', $objectId)
                ->where('tenant_id', $tenant)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                $this->events->record(SecurityEventLogger::USER_PROVISION_REFUSED, [
                    'result' => 'refused',
                    'reason' => 'duplicate_identity',
                ]);

                throw PeopleViolation::duplicateIdentity();
            }

            $user = new User;

            $user->forceFill([
                'organisation_id' => $organisation->id,
                'provider' => 'microsoft',
                'external_subject' => $objectId,
                'tenant_id' => $tenant,
                // Provisional until this person first signs in. Never used for
                // authentication, authorisation or duplicate resolution.
                'email' => $email,
                'display_name' => $displayName,
                'status' => UserStatus::Active->value,
                // A provisioned person holds NO role. P1-05 grants authority
                // through role_assignments, deliberately and by somebody, and
                // provisioning is not that act. There is no field here to set.
                'last_signed_in_at' => null,
            ])->save();

            $this->events->record(SecurityEventLogger::USER_PROVISIONED, [
                'user_id' => $actor->id,
                'organisation_id' => $organisation->id,
                'entity_type' => 'user',
                'entity_id' => $user->id,
            ]);

            return $user;
        });
    }

    /**
     * Deactivate a person. Never blocked by dependencies - with ONE exception.
     *
     * D-36: a person leaving is exactly when they have the most relationships,
     * and a guard that refuses then makes the safe action impossible. Teams,
     * groups, management and history are all untouched.
     *
     * The exception is lockout. If this is the last active System
     * Administrator, deactivating leaves a deployment with nobody who can
     * administer it and no route back through the application.
     *
     * P1-05 moved the guard itself to AdministratorSetGuard, because the same
     * invariant is now reachable by two operations and a second copy of it is
     * the worst possible place for two sources of truth.
     */
    public function deactivate(User $user, User $actor): User
    {
        // THE ADMINISTRATOR-SET BOUNDARY, not this user's row.
        //
        // Deactivation is one of exactly two operations that can reduce the
        // effective System Administrator count; revoking the role is the other.
        // Both take the SAME boundary, in the same deterministic order, so two
        // competing removals contend on the same locked set rather than on two
        // different subject rows. Locking the subject first would give each
        // transaction its own lock root, deadlock, and leave MySQL's victim
        // selection as the thing standing between the deployment and lockout.
        return $this->administrators->serialise(function (array $effective) use ($user, $actor): User {
            // The decision comes from the shared guard; the SENTENCE is this
            // module's, because this module's screen renders it and "before
            // deactivating this account" is the right wording here.
            if ($this->administrators->wouldLeaveZero($effective, $user)) {
                throw PeopleViolation::soleAdministrator();
            }

            $user->forceFill(['status' => UserStatus::Inactive->value])->save();

            $this->events->record(SecurityEventLogger::USER_DEACTIVATED, [
                'user_id' => $actor->id,
                'entity_type' => 'user',
                'entity_id' => $user->id,
                'result' => 'deactivated',
            ]);

            return $user;
        });
    }

    /** Restores authentication eligibility. Rebuilds nothing, because nothing was removed. */
    public function reactivate(User $user, User $actor): User
    {
        $user->forceFill(['status' => UserStatus::Active->value])->save();

        $this->events->record(SecurityEventLogger::USER_ACTIVATED, [
            'user_id' => $actor->id,
            'entity_type' => 'user',
            'entity_id' => $user->id,
            'result' => 'activated',
        ]);

        return $user;
    }

    /**
     * Assign or change the organisation - the D-16 seam, written for somebody
     * other than the bootstrap administrator for the first time.
     *
     * Assigning where there was none is free. CHANGING is guarded: every current
     * membership and management relationship belongs to the old organisation and
     * would become a cross-organisation row, which every P1-01 rule forbids.
     *
     * Refusing is deliberate. The alternative is either breaking that invariant
     * or deleting somebody's history to preserve it, and deleting history to
     * make an edit succeed is the thing this product does not do.
     */
    public function assignOrganisation(User $user, ?int $organisationId, User $actor): User
    {
        return DB::transaction(function () use ($user, $organisationId, $actor): User {
            $current = $user->organisation_id;

            if ($current !== null && $current !== $organisationId) {
                $blockers = $this->currentRelationshipPhrases($user);

                if ($blockers !== []) {
                    throw PeopleViolation::organisationChangeBlocked($blockers);
                }
            }

            $user->forceFill(['organisation_id' => $organisationId])->save();

            $this->events->record(SecurityEventLogger::USER_ORGANISATION_ASSIGNED, [
                'user_id' => $actor->id,
                'entity_type' => 'user',
                'entity_id' => $user->id,
                'organisation_id' => $organisationId,
            ]);

            return $user;
        });
    }

    /**
     * D-39. Purge is for the onboarding mistake, not for the departure.
     *
     * Six conditions, and the seventh is that they are re-checked inside the
     * transaction. Five of them are the schema-driven dependency walk that D-24
     * established, so a foreign key P1-05 adds becomes a blocker with no change
     * here. TWO ARE NOT, and they are not because the schema cannot answer them:
     *
     *   - "never signed in" is a column value, not a reference;
     *   - bootstrap_grants.consumed_by_user_id CARRIES NO FOREIGN KEY, so
     *     Schema::getForeignKeys does not return it and the walk cannot see it.
     *
     * That second one is the important one. Relying on the walk alone would have
     * produced a guard that looked complete and quietly permitted deleting the
     * founding administrator. Found by reading the P1-00 migration rather than
     * trusting the mechanism to be total.
     */
    public function purge(User $user, User $actor): void
    {
        $this->refuseIfNotPurgeable($user);

        DB::transaction(function () use ($user, $actor): void {
            // The second check, inside the transaction and locking, for the same
            // reason D-24 does it: a dependency created after the first check
            // must not be missed.
            $this->refuseIfNotPurgeable($user, locking: true);

            $id = $user->id;

            $user->delete();

            $this->events->record(SecurityEventLogger::USER_PURGED, [
                'user_id' => $actor->id,
                'entity_type' => 'user',
                'entity_id' => $id,
            ]);
        });
    }

    public function isPurgeable(User $user): bool
    {
        try {
            $this->refuseIfNotPurgeable($user);

            return true;
        } catch (PeopleViolation) {
            return false;
        }
    }

    private function refuseIfNotPurgeable(User $user, bool $locking = false): void
    {
        if ($user->last_signed_in_at !== null) {
            throw PeopleViolation::hasSignedIn();
        }

        if ($this->isBootstrapAdministrator($user)) {
            throw PeopleViolation::bootstrapAdministrator();
        }

        $blockers = PurgeDependencies::blocking($user, $locking);

        if ($blockers !== []) {
            throw PeopleViolation::inUse('person', array_column($blockers, 'phrase'));
        }
    }

    /**
     * Condition 6, and it needs its own query.
     *
     * bootstrap_grants.consumed_by_user_id is an unsignedBigInteger with no
     * constraint - read from the P1-00 migration, not assumed - so no schema
     * walk will ever find it.
     */
    private function isBootstrapAdministrator(User $user): bool
    {
        return DB::table('bootstrap_grants')
            ->where('consumed_by_user_id', $user->getKey())
            ->exists();
    }

    /**
     * What a person is currently part of, in business language.
     *
     * CURRENT ONLY - effective_to and left_at NULL - and a clause is omitted
     * entirely when its count is zero, so nobody reads "manages 0 people".
     *
     * @return list<string>
     */
    public function currentRelationshipPhrases(User $user): array
    {
        $manages = ManagementRelationship::query()
            ->where('manager_id', $user->getKey())
            ->whereNull('effective_to')
            ->count();

        $teams = TeamMembership::query()
            ->where('user_id', $user->getKey())
            ->whereNull('left_at')
            ->count();

        $groups = GroupMembership::query()
            ->where('user_id', $user->getKey())
            ->whereNull('left_at')
            ->count();

        $phrases = [];

        if ($manages > 0) {
            $phrases[] = $manages === 1 ? 'manages 1 person' : "manages {$manages} people";
        }

        if ($teams > 0) {
            $phrases[] = $teams === 1 ? 'belongs to 1 team' : "belongs to {$teams} teams";
        }

        if ($groups > 0) {
            $phrases[] = $groups === 1 ? 'belongs to 1 group' : "belongs to {$groups} groups";
        }

        return $phrases;
    }
}
