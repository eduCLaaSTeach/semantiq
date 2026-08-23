<?php

declare(strict_types=1);

namespace App\Modules\Platform\Enums;

/**
 * The one status vocabulary for the whole administrator release.
 *
 * Release 1 section 31 lists the status values five different record families
 * are allowed to hold. They are collected here rather than left as five
 * separate enums for one reason: every one of them ends up rendered as a badge,
 * and five vocabularies produce five slightly different ideas of which colour
 * "error" is. One enum, one colour map, one place to look.
 *
 * The vocabulary is DELIBERATELY WIDER than any single record uses. A record
 * declares its own subset through one of the `for*()` sets below, and its
 * validation rejects anything outside that subset. That keeps "an integration
 * may be `connected`" and "a user may not" as an enforced rule rather than a
 * convention, while still leaving one place to add a state.
 *
 * The badge role is the design system's closed set of six - neutral, success,
 * warning, danger, info, violet - from resources/css/app.css. No status invents
 * a seventh, and none carries an inline colour.
 */
enum LifecycleStatus: string
{
    /* User lifecycle - section 31. */
    case Invited = 'invited';
    case Active = 'active';
    case Disabled = 'disabled';
    case Locked = 'locked';
    case Expired = 'expired';

    /* Integration lifecycle - section 31. */
    case Draft = 'draft';
    case Configured = 'configured';
    case Connected = 'connected';
    case Warning = 'warning';
    case Error = 'error';

    /* Policy lifecycle - section 31. */
    case Approved = 'approved';
    case Superseded = 'superseded';

    /* Access review lifecycle - section 31. */
    case Open = 'open';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /* Sovereignty exception lifecycle - section 31. */
    case Pending = 'pending';
    case Rejected = 'rejected';
    case Revoked = 'revoked';

    /**
     * The human label. Held here rather than in each view so the same state
     * never reads as "Disabled" on one screen and "Deactivated" on the next.
     */
    public function label(): string
    {
        return match ($this) {
            self::Invited => 'Invited',
            self::Active => 'Active',
            self::Disabled => 'Disabled',
            self::Locked => 'Locked',
            self::Expired => 'Expired',
            self::Draft => 'Draft',
            self::Configured => 'Configured',
            self::Connected => 'Connected',
            self::Warning => 'Warning',
            self::Error => 'Error',
            self::Approved => 'Approved',
            self::Superseded => 'Superseded',
            self::Open => 'Open',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
            self::Pending => 'Pending',
            self::Rejected => 'Rejected',
            self::Revoked => 'Revoked',
        };
    }

    /**
     * The CSS class for the badge, from the design system's six roles.
     *
     * The mapping is by MEANING, not by record family. `locked` and `error` are
     * both danger because both mean something is broken and somebody must act;
     * `expired` and `superseded` are both neutral because both mean this row is
     * simply no longer the current one and nothing is wrong.
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Active, self::Connected, self::Approved, self::Completed => 'badge badge-success',
            self::Warning, self::Pending, self::Invited => 'badge badge-warning',
            self::Error, self::Locked, self::Rejected, self::Revoked => 'badge badge-danger',
            self::Configured, self::Open => 'badge badge-info',
            self::Draft => 'badge badge-violet',
            self::Disabled, self::Expired, self::Superseded, self::Cancelled => 'badge',
        };
    }

    /**
     * The states an organisation may hold. ADM-002.
     *
     * Narrow on purpose: an organisation is the instance's owner, so it is
     * either operating or it is not. It has no draft and no approval.
     *
     * @return list<self>
     */
    public static function forOrganisation(): array
    {
        return [self::Active, self::Disabled];
    }

    /**
     * Whether a value is inside a given vocabulary.
     *
     * Takes the subset explicitly so the caller states which record family it
     * is validating. A shared "is this a valid status" check would accept
     * `connected` for a user, which is exactly the mistake section 31's five
     * separate lists exist to prevent.
     *
     * @param  list<self>  $vocabulary
     */
    public static function isWithin(?string $value, array $vocabulary): bool
    {
        $candidate = $value === null ? null : self::tryFrom($value);

        return $candidate !== null && in_array($candidate, $vocabulary, true);
    }
}
