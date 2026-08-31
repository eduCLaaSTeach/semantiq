<?php

declare(strict_types=1);

namespace App\Modules\Organisation\Models;

/**
 * Two states, and no third.
 *
 * There is no "deleted" case because P1-01 offers no hard delete on any route.
 * A cascade is convenient exactly once and unexplainable every time afterwards,
 * and the source document warns that restructuring must not silently broaden
 * access - a silent cascade is precisely how structure changes underneath
 * someone's scope.
 */
enum StructureStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    public function isActive(): bool
    {
        return $this === self::Active;
    }
}
