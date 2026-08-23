<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Enums\BusinessDomain;
use App\Enums\Role;
use App\Models\DomainEntitlement;
use App\Models\User;
use App\Modules\Audit\Support\AuditLogger;
use App\Modules\Identity\Exceptions\SubjectOutsideOrganisation;
use App\Modules\Identity\Models\AccessRole;
use App\Modules\Identity\Models\UserRole;
use App\Modules\Identity\Policies\SystemAdministratorGuard;
use App\Modules\Identity\Support\Authorization;
use App\Modules\Identity\Support\OrganisationContext;
use App\Modules\Platform\Enums\LifecycleStatus;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use RuntimeException;

/**
 * Every change to an account's identity and access. Feature ADM-005.
 *
 * The ONE write path. Controllers, console commands and the access-review
 * applier all come through here, because the rules below are the kind that get
 * forgotten exactly once and then cannot be un-forgotten:
 *
 *  - VAL-ORG-SUBJECT-001. **Every operation on an EXISTING account first proves
 *    that account belongs to the current organisation.** `users` deliberately
 *    carries no global organisation scope - it is the authentication table, and
 *    a fail-closed global scope there would mean nobody can sign in when the
 *    context fails to resolve (SEC-DEC-022). That choice moves the whole burden
 *    onto the write paths, and this is where it is discharged: in the service,
 *    not the controller, so a console command, a queued job, a future API
 *    endpoint and the access-review applier are all covered by the same line.
 *  - VAL-USER-LASTADMIN-001, delegated to `SystemAdministratorGuard`. Three
 *    separate paths can empty the System Administrator role, so the check lives
 *    in one place all three call.
 *  - VAL-USER-ELEVATE-001. Nobody hands out authority they do not hold, and
 *    nobody acts on somebody who outranks them.
 *  - Every change is AUDITED, with redacted before and after summaries.
 *
 * The tier and the entitlement stay separate throughout. Granting somebody
 * System Administrator through `changeTier()` gives them no business data;
 * granting Finance through `grantEntitlement()` gives them no platform
 * authority. Two methods, two audit events, two decisions - and nothing in this
 * class lets one imply the other.
 */
class UserRegistry
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly Authorization $authorization,
        private readonly SystemAdministratorGuard $guard,
        private readonly OrganisationContext $organisations,
    ) {}

    /**
     * Create an account.
     *
     * The account is created in the actor's organisation and starts `invited`
     * rather than `active`: an account that has never been signed into is not
     * the same as one in use, and starting active would make the two
     * indistinguishable in every later review.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws InvalidArgumentException
     */
    public function create(array $attributes, User $actor): User
    {
        $tier = $attributes['role'] ?? Role::default();

        $this->assertMayGrantTier($actor, $tier);

        $organisationId = $this->organisations->require()->id;

        /* VAL-USER-EMAIL-001. The unique index is the real enforcement; this
         * turns a database exception into a message on the right field. */
        $this->assertEmailIsFree($attributes['email'], $organisationId);

        $user = new User;

        $user->forceFill([
            'organisation_id' => $organisationId,
            'name' => $attributes['name'],
            'email' => $attributes['email'],
            'user_type' => $attributes['user_type'],
            'status' => LifecycleStatus::Invited,
            /* Recorded rather than inferred. An account can move to federated
             * sign-in later without the password column deciding the answer. */
            'authentication_source' => $attributes['authentication_source'] ?? 'local',
            'role' => $tier,
            'is_auditor' => (bool) ($attributes['is_auditor'] ?? false),
            'business_unit_id' => $attributes['business_unit_id'] ?? null,
            'team_id' => $attributes['team_id'] ?? null,
            'external_reference_id' => $attributes['external_reference_id'] ?? null,
            'access_start' => $attributes['access_start'] ?? null,
            'access_end' => $attributes['access_end'] ?? null,
        ])->save();

        $this->audit->record(
            action: 'user.created',
            module: 'Identity',
            resourceType: 'user',
            resourceId: $user->getKey(),
            after: $this->summarise($user),
        );

        return $user;
    }

    /**
     * Change an account's profile, placement and access window.
     *
     * Deliberately cannot change the tier, the roles or the entitlements. Those
     * are three separate methods with three separate audit events, because
     * "the administrator edited a profile" and "the administrator granted
     * themselves Finance" must never be the same line in the trail.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(User $subject, array $attributes, User $actor): User
    {
        $this->assertInOrganisation($subject, 'user.update');
        $this->assertMayActOn($actor, $subject);

        $before = $this->summarise($subject);

        $subject->forceFill([
            'name' => $attributes['name'],
            'user_type' => $attributes['user_type'],
            'business_unit_id' => $attributes['business_unit_id'] ?? null,
            'team_id' => $attributes['team_id'] ?? null,
            'external_reference_id' => $attributes['external_reference_id'] ?? null,
            'access_start' => $attributes['access_start'] ?? null,
            'access_end' => $attributes['access_end'] ?? null,
        ]);

        /*
         * An access window that has just been set in the past would leave the
         * account active but unable to sign in - two sources of truth about the
         * same thing. The status follows the window rather than contradicting
         * it, and the change is visible in the audit summary.
         */
        if ($subject->status === LifecycleStatus::Active && $subject->accessWindowHasClosed()) {
            $subject->status = LifecycleStatus::Expired;
        }

        $subject->save();

        $this->audit->record(
            action: 'user.updated',
            module: 'Identity',
            resourceType: 'user',
            resourceId: $subject->getKey(),
            before: $before,
            after: $this->summarise($subject),
        );

        return $subject;
    }

    /**
     * Change an account's primary tier.
     *
     * The most dangerous single operation in the release, and the one with the
     * most checks in front of it:
     *
     *  - the actor must outrank or equal the subject;
     *  - the actor cannot grant a tier above their own;
     *  - demoting the last active System Administrator is refused outright.
     *
     * @throws RuntimeException when it would empty the System Administrator role.
     */
    public function changeTier(User $subject, Role $tier, User $actor, ?string $reason = null): User
    {
        $this->assertInOrganisation($subject, 'user.tier.change');
        $this->assertMayActOn($actor, $subject);
        $this->assertMayGrantTier($actor, $tier);

        if ($subject->role === $tier) {
            return $subject;
        }

        /* Demotion only. Promoting the last administrator is fine. */
        if (! $tier->atLeast(Role::SystemAdmin)) {
            $this->guard->assertNotLast($subject, 'Demoting them');
        }

        $before = ['role' => $subject->role->value];

        $subject->forceFill(['role' => $tier])->save();
        $this->authorization->flush();

        $this->audit->record(
            action: 'user.role.assigned',
            module: 'Identity',
            resourceType: 'user',
            resourceId: $subject->getKey(),
            before: $before,
            after: ['role' => $tier->value],
            reason: $reason,
        );

        return $subject;
    }

    /**
     * Change an account's status: disable, lock, unlock or re-activate.
     *
     * @throws RuntimeException when it would empty the System Administrator role.
     */
    public function changeStatus(User $subject, LifecycleStatus $status, User $actor, ?string $reason = null): User
    {
        $this->assertInOrganisation($subject, 'user.status.change');
        $this->assertMayActOn($actor, $subject);

        if (! LifecycleStatus::isWithin($status->value, LifecycleStatus::forUser())) {
            throw new InvalidArgumentException('"'.$status->value.'" is not a state an account can hold.');
        }

        if ($subject->status === $status) {
            return $subject;
        }

        /* Anything that stops them signing in could empty the role. */
        if (! $status->permitsAuthentication()) {
            $this->guard->assertNotLast($subject, ucfirst($status->label()).' them');
        }

        $before = ['status' => $subject->status->value];

        $subject->forceFill(['status' => $status])->save();
        $this->authorization->flush();

        $this->audit->record(
            action: match ($status) {
                LifecycleStatus::Disabled => 'user.disabled',
                LifecycleStatus::Active => 'user.unlocked',
                default => 'user.updated',
            },
            module: 'Identity',
            resourceType: 'user',
            resourceId: $subject->getKey(),
            before: $before,
            after: ['status' => $status->value],
            reason: $reason,
        );

        return $subject;
    }

    /**
     * Give an account an additional role.
     *
     * The role's own tier is checked against the actor's, so an Administrator
     * cannot hand out a System Administrator role. The assignment would be
     * inert anyway - the ceiling in `Authorization` sees to that - but a
     * recorded grant that silently does nothing is its own kind of lie, and an
     * access review would show authority the person does not have.
     */
    public function assignRole(User $subject, AccessRole $role, User $actor, ?string $reason = null): void
    {
        $this->assertInOrganisation($subject, 'user.role.assign');
        /* The ROLE as well as the subject. A role belonging to another customer
         * would otherwise be attachable to one of ours by id. */
        $this->assertRoleInOrganisation($role, 'user.role.assign');
        $this->assertMayActOn($actor, $subject);
        $this->assertMayGrantTier($actor, $role->tier);

        $existing = UserRole::query()
            ->where('user_id', $subject->getKey())
            ->where('role_id', $role->getKey())
            ->exists();

        if ($existing) {
            return;
        }

        (new UserRole)->forceFill([
            'user_id' => $subject->getKey(),
            'role_id' => $role->getKey(),
            'assigned_by_user_id' => $actor->getKey(),
        ])->save();

        $this->authorization->flush();

        $this->audit->record(
            action: 'user.role.assigned',
            module: 'Identity',
            resourceType: 'user',
            resourceId: $subject->getKey(),
            after: ['role_code' => $role->code, 'tier' => $role->tier->value],
            reason: $reason,
        );
    }

    /**
     * Take an additional role away.
     *
     * No last-administrator check: this removes an ADDITIONAL role, never the
     * primary tier, so it cannot empty the System Administrator role.
     */
    public function removeRole(User $subject, AccessRole $role, User $actor, ?string $reason = null): void
    {
        $this->assertInOrganisation($subject, 'user.role.remove');
        $this->assertRoleInOrganisation($role, 'user.role.remove');
        $this->assertMayActOn($actor, $subject);

        $removed = UserRole::query()
            ->where('user_id', $subject->getKey())
            ->where('role_id', $role->getKey())
            ->delete();

        if ($removed === 0) {
            return;
        }

        $this->authorization->flush();

        $this->audit->record(
            action: 'user.role.removed',
            module: 'Identity',
            resourceType: 'user',
            resourceId: $subject->getKey(),
            before: ['role_code' => $role->code, 'tier' => $role->tier->value],
            reason: $reason,
        );
    }

    /**
     * Grant access to a business domain's information.
     *
     * THE SECOND DIMENSION, and the reason it has its own method and its own
     * audit event. `ROLE_MODEL.md` section 1: a platform role never implies
     * business data. Nothing about the actor's tier makes this automatic, and
     * nothing about this makes any tier automatic.
     *
     * The actor must themselves hold `admin.entitlements.grant`, which is the
     * elevation rule applied to the second dimension too.
     */
    public function grantEntitlement(User $subject, BusinessDomain $domain, User $actor, ?string $reason = null): void
    {
        $this->assertInOrganisation($subject, 'user.entitlement.grant');
        $this->assertMayActOn($actor, $subject);
        $this->assertMayDelegate($actor, 'admin.entitlements.grant');

        if ($subject->isEntitledTo($domain)) {
            return;
        }

        DomainEntitlement::query()->create([
            'user_id' => $subject->getKey(),
            'domain' => $domain->value,
            'granted_by_user_id' => $actor->getKey(),
        ]);

        $this->audit->record(
            action: 'user.entitlement.granted',
            module: 'Identity',
            resourceType: 'user',
            resourceId: $subject->getKey(),
            after: ['domain' => $domain->value, 'sensitive' => $domain->isSensitive()],
            reason: $reason,
        );
    }

    /**
     * Revoke access to a business domain's information.
     */
    public function revokeEntitlement(User $subject, BusinessDomain $domain, User $actor, ?string $reason = null): void
    {
        $this->assertInOrganisation($subject, 'user.entitlement.revoke');
        $this->assertMayActOn($actor, $subject);
        $this->assertMayDelegate($actor, 'admin.entitlements.grant');

        $removed = DomainEntitlement::query()
            ->where('user_id', $subject->getKey())
            ->where('domain', $domain->value)
            ->delete();

        if ($removed === 0) {
            return;
        }

        $this->audit->record(
            action: 'user.entitlement.revoked',
            module: 'Identity',
            resourceType: 'user',
            resourceId: $subject->getKey(),
            before: ['domain' => $domain->value],
            reason: $reason,
        );
    }

    /**
     * Refuse an operation on an account belonging to another organisation.
     *
     * VAL-ORG-SUBJECT-001, and the reason this class is the authoritative place
     * for it. Route-model binding resolves by primary key and knows nothing
     * about tenancy; the ids are sequential integers; and `users` carries no
     * global scope to catch it. Without this, a System Administrator in one
     * organisation could disable, demote or re-entitle an account in another by
     * supplying its id - which is exactly what happened before this method
     * existed, proved by a test written to demonstrate it.
     *
     * FAILS CLOSED IN BOTH DIRECTIONS. An account with no organisation at all
     * is refused as well as one belonging to somebody else: an unplaced account
     * is not this organisation's to change, and treating "unknown owner" as
     * "mine" is how a boundary check becomes a boundary hole. The one place an
     * unplaced account is legitimate is sign-in, before an organisation has
     * been resolved, and nothing here runs on that path.
     *
     * The DENIAL IS AUDITED before the exception is thrown. Somebody supplying
     * another customer's id is the single most interesting thing that can
     * happen to this application, and it must not be the one refusal that
     * leaves no trace.
     *
     * @throws SubjectOutsideOrganisation
     */
    private function assertInOrganisation(User $subject, string $operation): void
    {
        $current = $this->organisations->currentId();

        if ($current !== null && $subject->organisation_id === $current) {
            return;
        }

        $this->audit->denied(
            action: 'privileged.action.denied',
            module: 'Identity',
            resourceType: 'user',
            resourceId: $subject->getKey(),
            /* Names the operation, never the other organisation. A log line
             * saying which customer owns which id is its own small leak. */
            reason: 'Cross-organisation subject refused for "'.$operation.'".',
        );

        throw SubjectOutsideOrganisation::for($operation);
    }

    /**
     * Refuse a role belonging to another organisation.
     *
     * A null owner is the SHARED built-in set - the six roles seeded one per
     * tier - and is legitimate for every organisation. Anything else must
     * belong to the current one.
     *
     * `AccessRole` does carry the global scope, so a foreign role cannot
     * normally be loaded at all. This covers the case the scope cannot: a model
     * instance loaded under a different context and handed in directly, which
     * is precisely what a background job or a future API could do.
     *
     * @throws SubjectOutsideOrganisation
     */
    private function assertRoleInOrganisation(AccessRole $role, string $operation): void
    {
        $current = $this->organisations->currentId();

        if ($role->organisation_id === null) {
            return;
        }

        if ($current !== null && $role->organisation_id === $current) {
            return;
        }

        $this->audit->denied(
            action: 'privileged.action.denied',
            module: 'Identity',
            resourceType: 'role',
            resourceId: $role->getKey(),
            reason: 'Cross-organisation role refused for "'.$operation.'".',
        );

        throw SubjectOutsideOrganisation::for($operation);
    }

    /**
     * Whether an account is one this organisation may act on.
     *
     * The same question `assertInOrganisation()` asks, without throwing, for a
     * SCREEN deciding whether to offer a control. The write path still calls
     * the asserting form, because a control that was never rendered is not an
     * authorization boundary and a POST does not care what the page showed.
     */
    public function isInOrganisation(User $subject): bool
    {
        $current = $this->organisations->currentId();

        return $current !== null && $subject->organisation_id === $current;
    }

    /**
     * Refuse an actor acting on somebody who outranks them.
     *
     * VAL-USER-ELEVATE-001. The refusal is audited, because an administrator
     * attempting to disable somebody above them is worth knowing about whether
     * or not it succeeded.
     *
     * @throws RuntimeException
     */
    private function assertMayActOn(User $actor, User $subject): void
    {
        if ($this->authorization->mayActOn($actor, $subject)) {
            return;
        }

        $this->audit->denied(
            action: 'privileged.action.denied',
            module: 'Identity',
            resourceType: 'user',
            resourceId: $subject->getKey(),
            reason: 'Actor holds a lower tier than the account they tried to change.',
        );

        throw new RuntimeException(
            'You cannot change an account that holds more authority than you do.'
        );
    }

    /**
     * Refuse an actor granting a tier above their own.
     *
     * VAL-USER-ELEVATE-001 from the granting side. Without it, an
     * Administrator could make themselves a System Administrator by creating an
     * account, which is the classic self-elevation route.
     *
     * @throws RuntimeException
     */
    private function assertMayGrantTier(User $actor, Role $tier): void
    {
        if ($actor->role->atLeast($tier)) {
            return;
        }

        $this->audit->denied(
            action: 'privileged.action.denied',
            module: 'Identity',
            resourceType: 'role',
            resourceId: $tier->value,
            reason: 'Actor tried to grant authority above their own.',
        );

        throw new RuntimeException(
            'You cannot give an account more authority than you hold yourself.'
        );
    }

    /**
     * Refuse an actor delegating a permission they do not hold.
     *
     * @throws RuntimeException
     */
    private function assertMayDelegate(User $actor, string $permission): void
    {
        if ($this->authorization->mayDelegate($actor, $permission)) {
            return;
        }

        $this->audit->denied(
            action: 'privileged.action.denied',
            module: 'Identity',
            resourceType: 'permission',
            resourceId: $permission,
            reason: 'Actor tried to delegate a permission they do not hold.',
        );

        throw new RuntimeException('You cannot grant access you do not hold yourself.');
    }

    /**
     * VAL-USER-EMAIL-001: one address per organisation.
     *
     * @throws InvalidArgumentException
     */
    private function assertEmailIsFree(string $email, int $organisationId): void
    {
        $taken = User::query()
            ->where('email', $email)
            ->where(fn ($query) => $query->where('organisation_id', $organisationId)->orWhereNull('organisation_id'))
            ->exists();

        if ($taken) {
            throw new InvalidArgumentException('An account already exists for that email address.');
        }
    }

    /**
     * A redacted summary for the audit trail.
     *
     * The address is included because it identifies the account and an
     * investigator needs it. No password, no token and no session value is here
     * or reachable from here, and `Redaction` would remove them anyway.
     *
     * @return array<string, mixed>
     */
    private function summarise(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'user_type' => $user->user_type->value,
            'status' => $user->status->value,
            'role' => $user->role->value,
            'business_unit_id' => $user->business_unit_id,
            'team_id' => $user->team_id,
            'access_start' => $user->access_start?->toDateString(),
            'access_end' => $user->access_end?->toDateString(),
        ];
    }

    /**
     * The organisation's accounts, for the registry screen.
     *
     * Explicitly scoped - see `User::scopeInCurrentOrganisation()` for why
     * `users` carries no global scope.
     */
    public function query(): Builder
    {
        return User::query()->inCurrentOrganisation();
    }
}
