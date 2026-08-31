<?php

declare(strict_types=1);

namespace App\Modules\Organisation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The Company Profile. Release 1 expects exactly one row.
 *
 * @property int $id
 * @property string $name
 * @property StructureStatus $status
 */
final class Organisation extends Model
{
    protected $fillable = ['name', 'legal_name', 'country', 'timezone', 'status'];

    protected function casts(): array
    {
        return ['status' => StructureStatus::class];
    }

    /** @return HasMany<LegalEntity, $this> */
    public function legalEntities(): HasMany
    {
        return $this->hasMany(LegalEntity::class);
    }

    /** @return HasMany<BusinessUnit, $this> */
    public function businessUnits(): HasMany
    {
        return $this->hasMany(BusinessUnit::class);
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }
}
