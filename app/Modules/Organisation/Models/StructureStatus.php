<?php

declare(strict_types=1);

namespace App\Modules\Organisation\Models;

/**
 * Two states, and no third.
 *
 * There is no "deleted" case. D-24 added a guarded permanent delete for four
 * master types, and it destroys the row rather than marking it - a "deleted"
 * status would be a third state that every query then has to remember to
 * exclude, which is how a deleted record comes back.
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
