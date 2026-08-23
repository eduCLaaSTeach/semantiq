<?php

declare(strict_types=1);

namespace App\Modules\Audit\Enums;

/**
 * How an audited action ended.
 *
 * `Denied` is separate from `Failed` on purpose. A failure is the system not
 * managing to do something; a denial is the system refusing to. Only the second
 * is a security signal, and a trail that folds them together cannot answer the
 * question an incident review actually asks - was anybody trying?
 *
 * Release 1's audit catalogue includes `privileged.action.denied` for exactly
 * this reason: a trail containing only successes cannot show an attack that
 * failed.
 */
enum AuditOutcome: string
{
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Denied = 'denied';

    public function label(): string
    {
        return match ($this) {
            self::Succeeded => 'Succeeded',
            self::Failed => 'Failed',
            self::Denied => 'Denied',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Succeeded => 'badge badge-success',
            self::Failed => 'badge badge-warning',
            self::Denied => 'badge badge-danger',
        };
    }
}
