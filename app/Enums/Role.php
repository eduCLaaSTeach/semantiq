<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The five-tier role baseline from the reference template, section 7.
 *
 * Tiers are cumulative and ordered highest first: a System Administrator
 * satisfies every check an Administrator satisfies, and so on down. The backing
 * value is the template's tier code rather than the display label, so an
 * authorisation check reads the same here as it does in the specification and a
 * relabelling never becomes a data migration.
 *
 * Labels are the template's own baseline names. The template says confirmed
 * labels live in the App Definition and are renamed only for a documented
 * per-app reason; this project has recorded none, so the baseline stands.
 */
enum Role: string
{
    case SystemAdmin = 'system_admin';
    case Admin = 'admin';
    case Collaborator = 'team';
    case Contributor = 'self';
    case Viewer = 'self_view';

    /**
     * The tier a new account starts on.
     *
     * Viewer, deliberately. Someone who authenticates against the directory but
     * has never been given a role gets the least the system can give them and is
     * promoted on purpose, rather than arriving with access nobody granted.
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
            self::Collaborator => 'Collaborator',
            self::Contributor => 'Contributor',
            self::Viewer => 'Viewer',
        };
    }

    /**
     * Rank within the cumulative order, higher meaning more authority.
     *
     * Comparison only, never persisted, so reordering the enum cannot silently
     * change the meaning of a value already in the database.
     */
    public function rank(): int
    {
        return match ($this) {
            self::SystemAdmin => 5,
            self::Admin => 4,
            self::Collaborator => 3,
            self::Contributor => 2,
            self::Viewer => 1,
        };
    }

    /**
     * Whether this tier carries at least the authority of the given one.
     */
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
