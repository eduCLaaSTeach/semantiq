<?php

declare(strict_types=1);

namespace App\Modules\Organisation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The top of the structural tree.
 *
 * Note what is absent: no legal_entity_id. D-14 rejected a single parent, and
 * the association is a junction so both directions can be true at once.
 *
 * @property int $id
 * @property int $organisation_id
 * @property string $name
 * @property string|null $code
 * @property StructureStatus $status
 */
final class BusinessUnit extends Model
{
    protected $fillable = ['organisation_id', 'name', 'code', 'status'];

    protected function casts(): array
    {
        return ['status' => StructureStatus::class];
    }

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /** @return HasMany<Department, $this> */
    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    /** @return BelongsToMany<LegalEntity, $this> */
    public function legalEntities(): BelongsToMany
    {
        return $this->belongsToMany(LegalEntity::class, 'business_unit_legal_entity');
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }
}
