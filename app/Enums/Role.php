<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The five-tier role baseline from the shared UI and UX layout template (§7).
 *
 * The tiers are cumulative and ordered highest first, so a System Administrator
 * satisfies every check an Administrator satisfies. The backing value is the
 * tier code from the template rather than the app-specific label, so the
 * authorisation checks read the same here as they do in the specification.
 */
enum Role: string
{
    case SystemAdmin = 'system_admin';
    case Admin = 'admin';
    case Team = 'team';
    case SelfService = 'self';
    case Viewer = 'self_view';

    /**
     * The role every account starts on.
     *
     * A new account is a Viewer rather than a Contributor, so an unrecognised
     * person who authenticates against the tenant gains the least the system can
     * give them and is promoted deliberately.
     */
    public static function default(): self
    {
        return self::Viewer;
    }

    /**
     * The label shown to a person.
     *
     * These are SemantIQ's own labels, confirmed against doc/06-App-Definition.md
     * section 3, not the template's generic baseline names. The backing tier
     * codes are what authorisation compares, so relabelling is safe and never
     * changes who can reach what.
     */
    public function label(): string
    {
        return match ($this) {
            self::SystemAdmin => 'Platform Administrator',
            self::Admin => 'Tenant Administrator',
            self::Team => 'Lead Data Engineer',
            self::SelfService => 'Data Engineer',
            self::Viewer => 'Business User',
        };
    }

    /**
     * Rank within the cumulative tier order, higher meaning more authority.
     *
     * Used for comparisons only. It is never persisted, so re-ordering the enum
     * cannot silently change what is already stored in the database.
     */
    public function rank(): int
    {
        return match ($this) {
            self::SystemAdmin => 5,
            self::Admin => 4,
            self::Team => 3,
            self::SelfService => 2,
            self::Viewer => 1,
        };
    }

    /**
     * Whether this role carries at least the authority of the given one.
     */
    public function atLeast(self $minimum): bool
    {
        return $this->rank() >= $minimum->rank();
    }
}
