<?php

declare(strict_types=1);

namespace App\Modules\Security\Enums;

use Illuminate\Support\Carbon;

/**
 * The lifecycle state of a secret reference. Feature ADM-012.
 *
 * DERIVED, not stored as a free choice. An administrator sets the dates and
 * whether the reference is retired; the state follows from those. A status
 * somebody can set independently of the dates is a status that goes stale the
 * moment nobody remembers to update it, and a stale "Active" beside a lapsed
 * certificate is worse than no status at all.
 *
 * `Retired` is the one an administrator sets, because "we stopped using this"
 * is a fact no date can express.
 */
enum SecretStatus: string
{
    case Active = 'active';
    case RotationDue = 'rotation_due';
    case ExpiringSoon = 'expiring_soon';
    case Expired = 'expired';
    case Retired = 'retired';
    case Unknown = 'unknown';

    /** How many days ahead counts as expiring soon. */
    public const EXPIRY_HORIZON_DAYS = 30;

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::RotationDue => 'Rotation due',
            self::ExpiringSoon => 'Expiring soon',
            self::Expired => 'Expired',
            self::Retired => 'Retired',
            self::Unknown => 'No expiry recorded',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Active => 'badge badge-success',
            self::RotationDue, self::ExpiringSoon => 'badge badge-warning',
            self::Expired => 'badge badge-danger',
            self::Retired, self::Unknown => 'badge',
        };
    }

    /**
     * Work out the state from the dates.
     *
     * Order matters and is worst-first: a retired reference is retired whatever
     * its dates say, and an expired one is expired even if rotation is also
     * overdue. Reporting the milder of two true things is how a screen
     * understates a problem.
     */
    public static function derive(
        ?Carbon $expiresOn,
        ?Carbon $rotationDueOn,
        ?Carbon $retiredAt,
        ?Carbon $today = null,
    ): self {
        if ($retiredAt !== null) {
            return self::Retired;
        }

        $today ??= Carbon::today();

        if ($expiresOn !== null && $expiresOn->lt($today)) {
            return self::Expired;
        }

        if ($expiresOn !== null && $expiresOn->lte($today->copy()->addDays(self::EXPIRY_HORIZON_DAYS))) {
            return self::ExpiringSoon;
        }

        if ($rotationDueOn !== null && $rotationDueOn->lte($today)) {
            return self::RotationDue;
        }

        /*
         * No expiry date at all. NOT reported as Active: a credential nobody
         * gave an expiry to is a credential nobody is tracking, and calling
         * that healthy is the false green this whole screen exists to avoid.
         */
        if ($expiresOn === null && $rotationDueOn === null) {
            return self::Unknown;
        }

        return self::Active;
    }

    /** How this state reads on the Security Overview roll-up. */
    public function overviewStatus(): SecurityStatus
    {
        return match ($this) {
            self::Active => SecurityStatus::Healthy,
            self::RotationDue, self::ExpiringSoon => SecurityStatus::Warning,
            self::Expired => SecurityStatus::Critical,
            self::Retired => SecurityStatus::NotAvailable,
            /* We were never told when this expires, so we cannot say it is
             * fine. NotVerified rather than Healthy, per rule 9. */
            self::Unknown => SecurityStatus::NotVerified,
        };
    }
}
