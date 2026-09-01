<?php

declare(strict_types=1);

namespace App\Modules\Organisation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
    protected $fillable = ['name', 'legal_name', 'primary_legal_entity_id', 'country', 'timezone', 'status'];

    protected function casts(): array
    {
        return ['status' => StructureStatus::class];
    }

    /**
     * D-25: the organisation's corporate identity, optional.
     *
     * NOT the parent of the business units, and not related to the D-14
     * junction, which stays many-to-many and attribute-free. The primary legal
     * entity need not be associated with any business unit at all.
     *
     * @return BelongsTo<LegalEntity, $this>
     */
    public function primaryLegalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class, 'primary_legal_entity_id');
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
