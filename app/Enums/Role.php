<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The platform role, as one cumulative tier ladder.
 *
 * Six tiers rather than the design template's five. The template permits
 * extending the ladder for a documented per-app reason, and doc/ROLE_MODEL.md
 * is that reason: it separates a Domain Owner, who approves a domain's KPIs,
 * glossary and semantic definitions, from an Analyst, who explores and saves
 * analysis inside domains somebody else approved. Collapsing the two onto one
 * rung would make every approval check a special case.
 *
 * Two things this enum deliberately does NOT model:
 *
 *  - **Auditor.** It reads audit trails and governance evidence while holding
 *    no operational rights, so it is not a rung on a ladder at all - it sits
 *    beside one. It is a capability flag on the account (`users.is_auditor`).
 *    Making it a tier would either grant it operational power it must not have
 *    or deny it the Compliance cluster it exists for.
 *  - **Business-domain access.** ROLE_MODEL.md section 1 is explicit that a
 *    role alone never grants business data. Domains are a separate dimension
 *    (`domain_entitlements`), so a System Administrator holds no Sales or HR
 *    data by virtue of being one.
 *
 * The backing value is the tier code and is persisted, so relabelling a role in
 * the interface never becomes a data migration.
 */
enum Role: string
{
    case SystemAdmin = 'system_admin';
    case Admin = 'admin';
    case DomainOwner = 'domain_owner';
    case Analyst = 'analyst';
    case Contributor = 'contributor';
    case Viewer = 'viewer';

    /**
     * The tier a new account starts on.
     *
     * Viewer. Someone who authenticates against the directory but has been
     * granted nothing gets the least the system can give, and is promoted
     * deliberately rather than arriving with access nobody decided on.
     */
    public static function default(): self
    {
        return self::Viewer;
    }

    public function label(): string
    {
        return match ($this) {
            self::SystemAdmin => 'System Administrator',
            self::Admin => 'Administrator',
            self::DomainOwner => 'Domain Owner',
            self::Analyst => 'Analyst',
            self::Contributor => 'Contributor',
            self::Viewer => 'Viewer',
        };
    }

    /**
     * What this tier is for, in one line, for a role picker.
     */
    public function purpose(): string
    {
        return match ($this) {
            self::SystemAdmin => 'Operates the SemantIQ platform itself.',
            self::Admin => 'Administers the organisation and its data environment.',
            self::DomainOwner => 'Owns a business intelligence domain and approves its definitions.',
            self::Analyst => 'Explores and analyses approved business information.',
            self::Contributor => 'Works with assigned insights, alerts and decisions.',
            self::Viewer => 'Reads authorised business intelligence.',
        };
    }

    /**
     * Rank in the cumulative order, higher meaning more authority.
     *
     * Comparison only, never persisted, so reordering the cases cannot change
     * the meaning of a value already stored.
     */
    public function rank(): int
    {
        return match ($this) {
            self::SystemAdmin => 6,
            self::Admin => 5,
            self::DomainOwner => 4,
            self::Analyst => 3,
            self::Contributor => 2,
            self::Viewer => 1,
        };
    }

    public function atLeast(self $minimum): bool
    {
        return $this->rank() >= $minimum->rank();
    }

    /**
     * Whether this tier may view but never mutate.
     */
    public function isReadOnly(): bool
    {
        return $this === self::Viewer;
    }
}
