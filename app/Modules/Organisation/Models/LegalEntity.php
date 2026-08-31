<?php

declare(strict_types=1);

namespace App\Modules\Organisation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A separate organisational axis - never a level in the structural tree (D-14).
 *
 * @property int $id
 * @property int $organisation_id
 * @property string $name
 * @property StructureStatus $status
 */
final class LegalEntity extends Model
{
    protected $fillable = [
        'organisation_id', 'name', 'registration_number',
        'jurisdiction', 'registered_address', 'status',
    ];

    protected function casts(): array
    {
        return ['status' => StructureStatus::class];
    }

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /** @return BelongsToMany<BusinessUnit, $this> */
    public function businessUnits(): BelongsToMany
    {
        return $this->belongsToMany(BusinessUnit::class, 'business_unit_legal_entity');
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }
}
