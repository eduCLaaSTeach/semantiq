<?php

declare(strict_types=1);

namespace App\Modules\People\Models;

/**
 * Two states, and no third.
 *
 * No "deleted" case, for the reason P1-01 already recorded: a deleted status is
 * a third state every query then has to remember to exclude, which is how a
 * deleted record comes back. D-39's guarded purge destroys the row instead, and
 * only when there is no history to destroy with it.
 */
enum GroupStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    public function isActive(): bool
    {
        return $this === self::Active;
    }
}
