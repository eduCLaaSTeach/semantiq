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

    /** @var list<string>|null */
    private ?array $auditorReadable = null;

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
     * Every permission the Auditor capability admits.
     *
     * Decision D2, SEC-DEC-062. `Authorization` merges these in AFTER the tier
     * ceiling, because an Auditor is frequently a Viewer and intersecting would
     * discard every one of them.
     *
     * Memoised like `defaultsFor()` and `ceilingFor()`: the catalogue is code
     * and cannot change within a request.
     *
     * @return list<string>
     */
    public function auditorReadableKeys(): array
    {
        return $this->auditorReadable ??= array_keys(array_filter(
            $this->all(),
            static fn (Permission $permission): bool => $permission->orAuditor,
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
            bool $orAuditor = false,
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
                orAuditor: $orAuditor,
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

            /* ---- Audit, gate 1, WIDENED in gate 4 batch R1.4b ----------- */

            /*
             * `admin.audit.view` moves from System Administrator to Domain
             * Owner, and gains the Auditor capability. Decision D2, approved
             * 24 August 2026, SEC-DEC-062.
             *
             * Three sources used to disagree about who may read the trail:
             * this line said System Administrator, the navigation rail admitted
             * the Compliance cluster at Domain Owner or Auditor, and
             * ROLE_MODEL.md says an Auditor reads the audit trail. The rail was
             * therefore the only thing standing between a typed URL and the
             * trail, which CLAUDE.md is explicit is never authorization.
             *
             * `ROLE_MODEL.md` was treated as the authority and the code moved to
             * meet it, rather than the document being narrowed to meet the code.
             */
            $declare('Admin', 'audit', 'view', 'Read the audit trail.', Role::DomainOwner, PermissionRisk::Normal, false, Role::SystemAdmin, true),

            /*
             * The network identifier is a SEPARATE permission at System
             * Administrator. Decision D8, SEC-DEC-063.
             *
             * An IP address is personal data and is rarely what an audit reader
             * actually needs. Bundling it into `admin.audit.view` would mean the
             * Auditor capability just created hands out network identifiers as a
             * side effect of reading the trail. `orAuditor` is deliberately
             * FALSE here.
             */
            $declare('Admin', 'audit', 'view_network', 'See the IP address and user agent recorded against an audit event.', Role::SystemAdmin, PermissionRisk::Elevated),

            /* ---- Governance, gate 4 batch R1.4b ------------------------- */

            /*
             * Retention and sovereignty exceptions follow the D13 split that
             * R1.4a established: read at Domain Owner, manage at Administrator,
             * high-risk approve at System Administrator.
             *
             * `orAuditor` on the two READ permissions and on nothing else. An
             * Auditor reviews governance evidence; they do not write it, request
             * it or bless it. `Permission` refuses to be constructed with the
             * flag on a write action, so this is enforced rather than merely
             * observed.
             */
            $declare('Admin', 'retention', 'view', 'See how long each category of personal data is kept, and on what basis.', Role::DomainOwner, PermissionRisk::Normal, false, null, true),
            $declare('Admin', 'retention', 'manage', 'Set the retention period, basis and disposal action for a category.', Role::Admin, PermissionRisk::High, true),

            $declare('Admin', 'sovereignty_exceptions', 'view', 'See recorded departures from the approved sovereignty profile.', Role::DomainOwner, PermissionRisk::Normal, false, null, true),
            $declare('Admin', 'sovereignty_exceptions', 'request', 'Ask for a time-bounded exception to the approved sovereignty profile.', Role::Admin, PermissionRisk::Elevated, true),
            $declare('Admin', 'sovereignty_exceptions', 'approve', 'Approve, reject or revoke a sovereignty exception. Never available to its requester.', Role::SystemAdmin, PermissionRisk::High, true),

            /* ---- Security, gate 3 --------------------------------------
             *
             * All four sit at System Administrator, ceiling and auto-grant
             * alike. Security policy is not delegated to the Administrator tier
             * in this gate: SEC-DEC-020's open question about Administrator
             * read grants stays open, and a gate that widens access while
             * answering a different question is how access quietly grows.
             *
             * `admin.secrets.view` is deliberately as restricted as the write.
             * The rows hold no secret, but a list of every credential this
             * system depends on, where each lives and when it lapses, is a map
             * for anybody attacking it - reading it is not the harmless half.
             *
             * Neither pair names a business domain. Seeing the security
             * policies grants nothing in Sales, Finance or People; that remains
             * a separate entitlement, checked separately.
             */
            $declare('Admin', 'security', 'view', 'See the security policies and how each control is behaving.', Role::SystemAdmin),
            $declare('Admin', 'security', 'update', 'Change authentication, session and API security policy.', Role::SystemAdmin, PermissionRisk::High, true),

            $declare('Admin', 'secrets', 'view', 'See where credentials are managed and when they lapse.', Role::SystemAdmin),
            $declare('Admin', 'secrets', 'manage', 'Add, change and retire a pointer to a credential held elsewhere.', Role::SystemAdmin, PermissionRisk::High, true),

            /* ---- Governance, gate 4 batch R1.4a ----------------------- */

            /*
             * Decision D13, approved 24 August 2026, recorded as SEC-DEC-067.
             * Governance authority splits three ways rather than sitting
             * entirely at System Administrator the way gate 3 does:
             *
             *   READ     Domain Owner. A Domain Owner who cannot read the
             *            privacy position governing their own domain will work
             *            around it, and the position is a policy document, not
             *            business data.
             *   MANAGE   Administrator. Writing a draft.
             *   APPROVE  System Administrator, and never the same permission as
             *            manage. A person who can weaken a sovereignty profile
             *            must not also be the person who blesses it.
             *
             * The services enforce the further separation the tier split cannot
             * express: a requester never approves their own request.
             *
             * NONE OF THESE NAMES A BUSINESS DOMAIN. The gate 2 rule holds and
             * is asserted by test: a Domain Owner who can read the sovereignty
             * profile holds no Finance, Sales or People data by virtue of it.
             *
             * The Auditor capability approved as D2 was NOT declared here in
             * R1.4a - it changes `Authorization` itself, and a flag the
             * authorization layer could not honour would have been a node that
             * appears and then denies. **R1.4b added it**, on the two READ
             * permissions only.
             *
             * `ROLE_MODEL.md` section 2 lists "review data-protection and
             * sovereignty evidence" among an Auditor's capabilities, alongside
             * reading the audit trail. Leaving these two off would have met the
             * document by half.
             */
            $declare('Admin', 'data_protection', 'view', 'See the data protection profile and the personal data categories this organisation holds.', Role::DomainOwner, PermissionRisk::Normal, false, null, true),
            $declare('Admin', 'data_protection', 'manage', 'Write a draft data protection profile and maintain the personal data categories.', Role::Admin, PermissionRisk::High, true),
            $declare('Admin', 'data_protection', 'approve', 'Approve a data protection profile, making it the version in force.', Role::SystemAdmin, PermissionRisk::High, true),

            $declare('Admin', 'sovereignty', 'view', 'See where this organisation stores and processes its data.', Role::DomainOwner, PermissionRisk::Normal, false, null, true),
            $declare('Admin', 'sovereignty', 'manage', 'Write a draft data sovereignty profile.', Role::Admin, PermissionRisk::High, true),
            $declare('Admin', 'sovereignty', 'approve', 'Approve a data sovereignty profile, including any position that permits data to cross a border.', Role::SystemAdmin, PermissionRisk::High, true),
        );
    }
}
