<?php

declare(strict_types=1);

namespace App\Modules\Identity\Support;

use App\Enums\Role;
use InvalidArgumentException;

/**
 * One declared permission. Feature ADM-007.
 *
 * A read-only value produced by `PermissionRegistry`, never constructed from a
 * database row. Everything about what a permission MEANS lives in code, which
 * is what makes the registry reviewable.
 *
 * TWO TIERS, AND THE DIFFERENCE BETWEEN THEM IS WHAT MAKES ROLES WORTH HAVING.
 *
 *   `minimumTier` is the CEILING. Nobody below it can hold this permission by
 *     any route - no role, no assignment, nothing. `Authorization::allows()`
 *     filters every effective permission through it, which is plan decision
 *     D2's "the tier and the grant must both agree" expressed as data rather
 *     than as a rule somebody has to remember at each call site.
 *
 *   `grantedFrom` is the tier that holds it AUTOMATICALLY, with no role
 *     assigned. It is never lower than the ceiling and is usually the same,
 *     which is why most tiers work out of the box.
 *
 * When `grantedFrom` is HIGHER than `minimumTier`, the permission is opt-in:
 * the top tier has it anyway, and a tier below can hold it only if a role
 * explicitly grants it. That gap is the whole point of the role table. Without
 * it the effective set would always equal the tier defaults and an assigned
 * role could add nothing - roles would be decoration, which is exactly the flaw
 * a test caught here.
 *
 * `requiresAudit` marks an action whose use must leave a trail even when it
 * succeeds. `risk` is shown to an administrator assigning it, so a high-risk
 * permission is not one line in a list of forty identical-looking checkboxes.
 */
readonly class Permission
{
    public function __construct(
        public string $key,
        public string $module,
        public string $resource,
        public string $action,
        public string $description,
        public Role $minimumTier,
        public PermissionRisk $risk = PermissionRisk::Normal,
        public bool $requiresAudit = false,
        /*
         * Defaults to the ceiling, so a permission that says nothing about this
         * behaves the way every permission did before the field existed.
         */
        public ?Role $grantedFrom = null,
        /*
         * THE AUDITOR CAPABILITY. Decision D2, approved 24 August 2026,
         * recorded as SEC-DEC-062.
         *
         * When true, an account carrying `users.is_auditor` holds this
         * permission whatever their tier says. `ROLE_MODEL.md` describes an
         * Auditor as somebody who reads the audit trail and reviews governance
         * evidence without operating the platform, and before this field the
         * authorization layer had no way to express that: `is_auditor` was
         * understood by `Navigation` and by nothing else, so the rail was the
         * only thing standing between a typed URL and the trail. CLAUDE.md is
         * explicit that hiding a menu item is never authorization.
         *
         * IT IS ALLOWED ON READ PERMISSIONS ONLY, and `PermissionRegistry`
         * refuses to construct one that is not. An Auditor reviews; they do not
         * manage, request or approve.
         *
         * IT GRANTS ONE SPECIFIC PERMISSION AND NOTHING ELSE. It does not raise
         * the tier ceiling, so it cannot become a route to authority in general,
         * and it says nothing about business-domain entitlement - an Auditor who
         * can read the audit log still holds no Finance figure.
         */
        public bool $orAuditor = false,
    ) {
        if ($this->orAuditor && ! $this->isRead()) {
            /*
             * A construction-time refusal rather than a test, because a write
             * permission carrying the auditor flag would hand an Auditor the
             * ability to change what they are supposed to be reviewing. That
             * must be impossible to declare, not merely caught later.
             */
            throw new InvalidArgumentException(
                "The permission `{$this->key}` declares orAuditor on a `{$this->action}` action. "
                .'The Auditor capability is allowed on read permissions only.'
            );
        }
    }

    /**
     * The tier that holds this automatically.
     */
    public function autoGrantTier(): Role
    {
        return $this->grantedFrom ?? $this->minimumTier;
    }

    /**
     * Whether this permission only reads.
     *
     * Derived from the action rather than declared, so a new read action cannot
     * be mislabelled as a write by a typo in a flag. `view` and `export` are
     * the read actions; everything else changes something.
     */
    public function isRead(): bool
    {
        return in_array($this->action, ['view', 'export'], true);
    }

    /**
     * A human label, for the Permissions screen and the role editor.
     */
    public function label(): string
    {
        return ucfirst($this->action).' '.str_replace('_', ' ', $this->resource);
    }
}
