<?php

declare(strict_types=1);

namespace App\Modules\Identity\Enums;

/**
 * What a reviewer decided about one access grant. Feature ADM-008.
 *
 * `Pending` is a real state and the default. A review where every item was
 * decided and a review where half were never looked at must never be
 * indistinguishable, because the second is a finding and the first is not.
 * That is why there is no implicit "keep" for an untouched item: silence is
 * recorded as silence.
 */
enum ReviewDecision: string
{
    case Pending = 'pending';
    case Keep = 'keep';
    case Revoke = 'revoke';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Not decided',
            self::Keep => 'Keep',
            self::Revoke => 'Revoke',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'badge badge-warning',
            self::Keep => 'badge badge-success',
            self::Revoke => 'badge badge-danger',
        };
    }
}
