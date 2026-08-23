<?php

declare(strict_types=1);

namespace App\Modules\Identity\Enums;

/**
 * What kind of account this is. Feature ADM-005.
 *
 * It is a CLASSIFICATION, not an authority. A service account is not privileged
 * because it is a service account; it holds whatever tier, roles and
 * entitlements it was given, exactly like a person. Nothing in the
 * authorization path reads this enum, and a reviewer should treat any code that
 * does as a defect.
 *
 * What it is for is review and reporting: "which external contractors can read
 * Finance" is a question an access review has to be able to ask, and it cannot
 * without this.
 */
enum UserType: string
{
    /** An employee of the customer organisation. */
    case Internal = 'internal';

    /** A contractor, partner or auditor. Usually access-window bound. */
    case External = 'external';

    /** A non-human account used by an integration. Never signs in interactively. */
    case Service = 'service';

    public function label(): string
    {
        return match ($this) {
            self::Internal => 'Internal',
            self::External => 'External',
            self::Service => 'Service',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Internal => 'badge',
            /* Both stand out on purpose: an external person and a non-human
             * account are the two rows a reviewer should look at twice. */
            self::External => 'badge badge-warning',
            self::Service => 'badge badge-info',
        };
    }
}
