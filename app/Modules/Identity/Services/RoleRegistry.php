<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Enums\Role;
use App\Models\User;
use App\Modules\Audit\Support\AuditLogger;
use App\Modules\Identity\Models\AccessRole;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Models\UserRole;
use App\Modules\Identity\Support\Authorization;
use App\Modules\Identity\Support\OrganisationContext;
use App\Modules\Identity\Support\PermissionRegistry;
use App\Modules\Platform\Enums\LifecycleStatus;
use InvalidArgumentException;
use RuntimeException;

/**
 * Every change to a role and to what it may do. Features ADM-006 and ADM-007.
 *
 * Three rules this class exists to keep:
 *
 *  - VAL-ROLE-SYSTEM-001. A built-in role's CODE and TIER cannot change and it
 *    cannot be deleted. Its display name can, because a customer may call an
 *    Administrator whatever they like; its identity cannot, because that
 *    identity appears in migrations, tests and documentation that the screen
 *    doing the renaming knows nothing about.
 *  - VAL-ROLE-ASSIGNED-001. A role somebody holds cannot be deleted without
 *    remediation. Deleting it would silently remove access from every holder,
 *    with the trail showing one deletion rather than N revocations.
 *  - VAL-PERM-DENY-001 and VAL-USER-ELEVATE-001. Only declared permissions can
 *    be granted, and only by somebody who holds them.
 */
class RoleRegistry
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly Authorization $authorization,
        private readonly PermissionRegistry $permissions,
        private readonly OrganisationContext $organisations,
    ) {}

    /**
     * The built-in role codes, one per tier.
     *
     * Seeded by migration rather than by a seeder, because production runs
     * `migrate --force` and never runs seeders - the same reasoning as the
     * bootstrap organisation in gate 1.
     *
     * @return array<string, array{name: string, tier: Role, description: string}>
     */
    public static function builtIn(): array
    {
        return [
            'system_admin' => [
                'name' => 'System Administrator',
                'tier' => Role::SystemAdmin,
                'description' => 'Operates the platform: configuration, security, integrations and audit. Holds no business data by default.',
            ],
            'admin' => [
                'name' => 'Administrator',
                'tier' => Role::Admin,
                'description' => 'Manages people, structure and access within the organisation.',
            ],
            /*
             * ADM-006 calls this role `collaborator`; doc/ROLE_MODEL.md, which
             * CLAUDE.md names as the authorization authority, calls it Domain
             * Owner and distinguishes it from an Analyst. Plan decision D1 kept
             * the shipped six tiers and made `collaborator` a documented alias.
             * The code follows the tier so the two documents stay reconcilable.
             */
            'domain_owner' => [
                'name' => 'Domain Owner',
                'tier' => Role::DomainOwner,
                'description' => 'Owns a business domain\'s definitions and approves changes to them. Called Collaborator in the Release 1 specification.',
            ],
            'analyst' => [
                'name' => 'Analyst',
                'tier' => Role::Analyst,
                'description' => 'Explores and analyses the domains they are entitled to.',
            ],
            'contributor' => [
                'name' => 'Contributor',
                'tier' => Role::Contributor,
                'description' => 'Contributes content within the domains they are entitled to.',
            ],
            'viewer' => [
                'name' => 'Viewer',
                'tier' => Role::Viewer,
                'description' => 'Reads the domains they are entitled to, and changes nothing.',
            ],
        ];
    }

    /**
     * Create a customer-defined role.
     *
     * The tier is checked against the actor's own: nobody creates a role more
     * powerful than themselves, because that would be self-elevation with an
     * extra step.
     *
     * @throws RuntimeException
     */
    public function create(string $code, string $name, Role $tier, ?string $description, User $actor): AccessRole
    {
        $this->assertMayGrantTier($actor, $tier);

        $code = $this->normaliseCode($code);

        $organisationId = $this->organisations->require()->id;

        if ($this->codeIsTaken($code, $organisationId)) {
            throw new InvalidArgumentException('A role with the code "'.$code.'" already exists.');
        }

        $role = new AccessRole;
        $role->forceFill([
            'organisation_id' => $organisationId,
            'code' => $code,
            'name' => $name,
            'description' => $description,
            'tier' => $tier,
            'is_system' => false,
            'status' => LifecycleStatus::Active,
            'version' => 1,
            'created_by_user_id' => $actor->getKey(),
            'updated_by_user_id' => $actor->getKey(),
        ])->save();

        $this->audit->record(
            action: 'role.created',
            module: 'Identity',
            resourceType: 'role',
            resourceId: $role->getKey(),
            after: ['code' => $code, 'name' => $name, 'tier' => $tier->value],
        );

        return $role;
    }

    /**
     * Change a role's name, description or status.
     *
     * The CODE and the TIER are absent from this method's signature on purpose.
     * Neither is editable on any role, built-in or not: the code is the stable
     * identifier, and the tier is the ceiling. A ceiling that can be raised
     * through an edit form is not a ceiling, so raising one means creating a
     * different role and moving people to it deliberately.
     */
    public function update(AccessRole $role, string $name, ?string $description, LifecycleStatus $status, User $actor): AccessRole
    {
        $this->assertMayManage($actor, $role);

        if (! LifecycleStatus::isWithin($status->value, LifecycleStatus::forStructure())) {
            throw new InvalidArgumentException('"'.$status->value.'" is not a state a role can hold.');
        }

        if ($role->isProtected() && $status !== LifecycleStatus::Active) {
            /* Disabling a built-in role would strip a whole tier's default
             * behaviour from every account holding it, with no obvious way
             * back. VAL-ROLE-SYSTEM-001 covers existence, and this covers the
             * form of removal that does not delete anything. */
            throw new RuntimeException('A built-in role cannot be disabled. It is part of the access model.');
        }

        $before = ['name' => $role->name, 'status' => $role->status->value];

        $role->forceFill([
            'name' => $name,
            'description' => $description,
            'status' => $status,
            'updated_by_user_id' => $actor->getKey(),
        ])->save();

        $this->authorization->flush();

        $this->audit->record(
            action: 'role.updated',
            module: 'Identity',
            resourceType: 'role',
            resourceId: $role->getKey(),
            before: $before,
            after: ['name' => $name, 'status' => $status->value],
        );

        return $role;
    }

    /**
     * Replace the set of permissions a role carries.
     *
     * Every key is validated against `PermissionRegistry` and against the
     * actor's own effective permissions before anything is written. The second
     * check is the one that matters: without it an Administrator could grant a
     * role `admin.roles.manage` and then assign that role to themselves.
     *
     * The whole set is replaced rather than diffed by the caller, so the screen
     * posts what it shows and there is no way to end up with a grant nobody
     * ticked. The role's `version` is bumped so an access review can record
     * WHICH version of a role it approved.
     *
     * @param  list<string>  $keys
     *
     * @throws InvalidArgumentException|RuntimeException
     */
    public function setPermissions(AccessRole $role, array $keys, User $actor, ?string $reason = null): void
    {
        $this->assertMayManage($actor, $role);

        $keys = array_values(array_unique($keys));

        foreach ($keys as $key) {
            /* Unknown key: refuse the whole save rather than silently drop one
             * checkbox, so the administrator sees what happened. */
            $permission = $this->permissions->assertDeclared($key);

            if (! $this->authorization->mayDelegate($actor, $key)) {
                $this->audit->denied(
                    action: 'role.permissions_changed',
                    module: 'Identity',
                    resourceType: 'role',
                    resourceId: $role->getKey(),
                    reason: 'Actor tried to grant "'.$key.'", which they do not hold.',
                );

                throw new RuntimeException(
                    'You cannot give a role the "'.$permission->label().'" permission, because you do not hold it yourself.'
                );
            }
        }

        $before = $role->permissionKeys();
        sort($before);

        $after = $keys;
        sort($after);

        if ($before === $after) {
            return;
        }

        $role->permissions()->delete();

        foreach ($keys as $key) {
            (new RolePermission)->forceFill([
                'role_id' => $role->getKey(),
                'permission_key' => $key,
                'granted_by_user_id' => $actor->getKey(),
            ])->save();
        }

        $role->forceFill([
            'version' => $role->version + 1,
            'updated_by_user_id' => $actor->getKey(),
        ])->save();

        $this->authorization->flush();

        $this->audit->record(
            action: 'role.permissions_changed',
            module: 'Identity',
            resourceType: 'role',
            resourceId: $role->getKey(),
            before: ['permissions' => $before],
            after: ['permissions' => $after, 'version' => $role->version],
            reason: $reason,
        );
    }

    /**
     * Delete a customer-defined role.
     *
     * @throws RuntimeException when the role is built in or still assigned.
     */
    public function delete(AccessRole $role, User $actor): void
    {
        $this->assertMayManage($actor, $role);

        /* VAL-ROLE-SYSTEM-001. */
        if ($role->isProtected()) {
            throw new RuntimeException('A built-in role cannot be deleted. It is part of the access model.');
        }

        /* VAL-ROLE-ASSIGNED-001. Deleting it would remove access from every
         * holder while the trail showed one deletion rather than N
         * revocations, so the remediation has to happen first and be visible. */
        $holders = UserRole::query()->where('role_id', $role->getKey())->count();

        if ($holders > 0) {
            throw new RuntimeException(
                'This role is still assigned to '.$holders.' account'.($holders === 1 ? '' : 's')
                .'. Remove it from them first, so each removal is recorded against the person it affected.'
            );
        }

        $before = ['code' => $role->code, 'name' => $role->name, 'tier' => $role->tier->value];

        $role->permissions()->delete();
        $role->delete();

        $this->authorization->flush();

        $this->audit->record(
            action: 'role.deleted',
            module: 'Identity',
            resourceType: 'role',
            resourceId: $role->getKey(),
            before: $before,
        );
    }

    /**
     * A role code: lowercase, underscores, nothing else.
     *
     * Normalised rather than merely validated, so `Sales Lead` and `sales_lead`
     * cannot both exist and confuse a later lookup.
     */
    private function normaliseCode(string $code): string
    {
        $code = strtolower(trim($code));
        $code = (string) preg_replace('/[^a-z0-9]+/', '_', $code);
        $code = trim($code, '_');

        if ($code === '') {
            throw new InvalidArgumentException('A role code must contain at least one letter or number.');
        }

        return $code;
    }

    private function codeIsTaken(string $code, int $organisationId): bool
    {
        return AccessRole::query()
            ->where('code', $code)
            ->where(fn ($query) => $query->where('organisation_id', $organisationId)->orWhereNull('organisation_id'))
            ->exists();
    }

    /**
     * @throws RuntimeException
     */
    private function assertMayManage(User $actor, AccessRole $role): void
    {
        if (! $this->authorization->allows($actor, 'admin.roles.manage')) {
            throw new RuntimeException('You do not have authority to change roles.');
        }

        /* A role can never be managed by somebody the role outranks: editing a
         * System Administrator role is itself a System Administrator action. */
        if (! $actor->role->atLeast($role->tier)) {
            $this->audit->denied(
                action: 'role.updated',
                module: 'Identity',
                resourceType: 'role',
                resourceId: $role->getKey(),
                reason: 'Actor tried to change a role above their own tier.',
            );

            throw new RuntimeException('You cannot change a role that carries more authority than you hold.');
        }
    }

    /**
     * @throws RuntimeException
     */
    private function assertMayGrantTier(User $actor, Role $tier): void
    {
        if (! $actor->role->atLeast($tier)) {
            throw new RuntimeException('You cannot create a role with more authority than you hold yourself.');
        }
    }
}
