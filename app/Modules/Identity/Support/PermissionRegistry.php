<?php

declare(strict_types=1);

namespace App\Modules\Identity\Support;

use App\Enums\Role;

/**
 * The catalogue of every permission this application recognises. Feature ADM-007.
 *
 * IN CODE, ON PURPOSE, and this is the single most important thing to
 * understand before changing this file. A permission an administrator can
 * invent is not a permission: nothing in the codebase checks a key that no line
 * of code names, so a row for `admin.invented.thing` would grant exactly
 * nothing while appearing on screen as though it granted something. That is
 * worse than not offering it at all.
 *
 * The consequence is `VAL-PERM-DENY-001`: an unknown key DENIES. Not "is
 * ignored", not "falls back" - denies. A key removed from this file stops
 * granting the moment the code is deployed, with no data migration and no
 * window where a deleted permission still works.
 *
 * There is deliberately no `permissions` table, which differs from section 29's
 * list. `role_permissions` stores the key as a string, validated against this
 * registry both when written and when checked. The trade-off accepted is that a
 * stale row can outlive its declaration; it is harmless, because a stale row
 * denies, and `semantiq:permissions` reports any that exist.
 *
 * ADDING A PERMISSION IS A CODE REVIEW. That is the point.
 *
 * `minimumTier` is a CEILING, not a default. Granting a permission to somebody
 * below its tier grants nothing - see `Authorization::allows()`.
 */
class PermissionRegistry
{
    /** Memoised. The catalogue is built once and read constantly. */
    private ?array $permissions = null;

    /** Memoised per tier. */
    private array $defaults = [];

    /** Memoised per tier. */
    private array $ceilings = [];

    /**
     * Every declared permission, keyed by its key.
     *
     * @return array<string, Permission>
     */
    public function all(): array
    {
        return $this->permissions ??= $this->build();
    }

    /**
     * One permission, or null when nothing declares it.
     *
     * Null rather than an exception, because this is called on every gated
     * request and an unrecognised key is a denial rather than a crash. The
     * WRITE path throws instead - see `assertDeclared()`.
     */
    public function get(string $key): ?Permission
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * Whether a key is declared at all.
     */
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * The permissions grouped by module, in declaration order.
     *
     * @return array<string, array<string, Permission>>
     */
    public function byModule(): array
    {
        $grouped = [];

        foreach ($this->all() as $key => $permission) {
            $grouped[$permission->module][$key] = $permission;
        }

        return $grouped;
    }

    /**
     * The permissions a tier holds by default, with no role assigned.
     *
     * Every permission this tier AUTO-GRANTS. This is what keeps the six tiers
     * working out of the box: an account with a tier and no assigned role still
     * reaches everything that tier was always able to reach, so nothing that
     * passed before roles existed starts failing.
     *
     * @return list<string>
     */
    public function defaultsFor(Role $tier): array
    {
        return $this->defaults[$tier->value] ??= array_keys(array_filter(
            $this->all(),
            fn (Permission $permission): bool => $tier->atLeast($permission->autoGrantTier()),
        ));
    }

    /**
     * Every permission a tier could EVER hold, however it is granted.
     *
     * The ceiling. `Authorization` intersects the effective set with this, so a
     * role carrying something above a holder's tier is inert rather than
     * effective.
     *
     * Distinct from `defaultsFor()`, and the gap between the two is what makes
     * a role worth assigning: a permission inside the ceiling but outside the
     * defaults is one a role can add. If these two ever collapse back into one
     * another, roles stop being able to grant anything.
     *
     * @return list<string>
     */
    public function ceilingFor(Role $tier): array
    {
        return $this->ceilings[$tier->value] ??= array_keys(array_filter(
            $this->all(),
            fn (Permission $permission): bool => $tier->atLeast($permission->minimumTier),
        ));
    }

    /**
     * Reject a key nothing declares.
     *
     * Used on every WRITE - assigning a permission to a role - where silence
     * would let an administrator save a grant that can never do anything.
     *
     * @throws \InvalidArgumentException
     */
    public function assertDeclared(string $key): Permission
    {
        $permission = $this->get($key);

        if ($permission === null) {
            throw new \InvalidArgumentException(
                'Unknown permission "'.$key.'". Permissions are declared in PermissionRegistry, '
                .'because a key no code checks grants nothing while appearing to grant something.'
            );
        }

        return $permission;
    }

    /**
     * The catalogue.
     *
     * Keys follow ADM-007's `<module>.<resource>.<action>` format. Ordering is
     * by module then by the order an administrator meets them.
     *
     * Only permissions with something behind them are declared. A key for a
     * screen that gate 3 or gate 5 will build is NOT listed here: it would show
     * up on the Permissions screen and in the role editor as a grant that does
     * nothing, which is precisely the "unwanted parts hanging" problem. Each
     * gate adds its own.
     *
     * @return array<string, Permission>
     */
    private function build(): array
    {
        $declare = function (
            string $module,
            string $resource,
            string $action,
            string $description,
            Role $minimumTier,
            PermissionRisk $risk = PermissionRisk::Normal,
            bool $requiresAudit = false,
            ?Role $grantedFrom = null,
        ): array {
            $key = strtolower($module).'.'.$resource.'.'.$action;

            return [$key => new Permission(
                key: $key,
                module: $module,
                resource: $resource,
                action: $action,
                description: $description,
                minimumTier: $minimumTier,
                risk: $risk,
                requiresAudit: $requiresAudit,
                grantedFrom: $grantedFrom,
            )];
        };

        return array_merge(
            /* ---- Platform, gate 1 ------------------------------------- */
            $declare('Admin', 'platform', 'view', 'See the Platform Overview and whether the platform is healthy.', Role::SystemAdmin),
            $declare('Admin', 'system', 'view', 'See system configuration, feature flags and diagnostics.', Role::SystemAdmin),
            $declare('Admin', 'system', 'update', 'Change system configuration and feature flags.', Role::SystemAdmin, PermissionRisk::Elevated, true),

            /* ---- Organisation, gate 2 --------------------------------- */
            $declare('Admin', 'organisation', 'view', 'See the organisation profile.', Role::Admin),
            $declare('Admin', 'organisation', 'update', 'Change the organisation profile.', Role::Admin, PermissionRisk::Elevated, true),

            $declare('Admin', 'business_units', 'view', 'See business units and their hierarchy.', Role::Admin),
            $declare('Admin', 'business_units', 'manage', 'Create, change and disable business units.', Role::Admin, PermissionRisk::Normal, true),

            $declare('Admin', 'teams', 'view', 'See teams and who leads them.', Role::Admin),
            $declare('Admin', 'teams', 'manage', 'Create, change and disable teams.', Role::Admin, PermissionRisk::Normal, true),

            /* ---- Users and access, gate 2 ------------------------------ */
            $declare('Admin', 'users', 'view', 'See the user registry and each account\'s access.', Role::Admin),
            $declare('Admin', 'users', 'create', 'Invite or create an account.', Role::Admin, PermissionRisk::Elevated, true),
            $declare('Admin', 'users', 'update', 'Change an account\'s profile, placement and access window.', Role::Admin, PermissionRisk::Elevated, true),
            $declare('Admin', 'users', 'disable', 'Disable, lock or unlock an account.', Role::Admin, PermissionRisk::High, true),

            $declare('Admin', 'roles', 'view', 'See roles and the permissions they carry.', Role::Admin),
            /*
             * THE ONE OPT-IN PERMISSION in the shipped catalogue, and it is
             * what proves the role machinery does something.
             *
             * The ceiling is Administrator, so an Administrator CAN hold it -
             * but only if a System Administrator grants it through a role.
             * Editing what a role may do is how somebody quietly widens their
             * own authority, so it is not something a tier should carry
             * automatically. A System Administrator has it either way.
             */
            $declare('Admin', 'roles', 'manage', 'Create and change roles, and what they may do.', Role::Admin, PermissionRisk::High, true, Role::SystemAdmin),
            $declare('Admin', 'roles', 'assign', 'Give an account a role, or take one away.', Role::Admin, PermissionRisk::High, true),

            $declare('Admin', 'permissions', 'view', 'See the permission catalogue and who holds what.', Role::Admin),

            $declare('Admin', 'entitlements', 'view', 'See which business domains an account may read.', Role::Admin),
            $declare('Admin', 'entitlements', 'grant', 'Grant or revoke access to a business domain\'s information.', Role::Admin, PermissionRisk::High, true),

            $declare('Admin', 'access_reviews', 'view', 'See access reviews and their decisions.', Role::Admin),
            $declare('Admin', 'access_reviews', 'manage', 'Open a review, decide its items and apply the result.', Role::Admin, PermissionRisk::Elevated, true),

            /* ---- Audit, gate 1 ----------------------------------------- */
            $declare('Admin', 'audit', 'view', 'Read the audit trail.', Role::SystemAdmin),
        );
    }
}
