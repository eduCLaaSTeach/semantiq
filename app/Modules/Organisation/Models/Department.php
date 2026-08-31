<?php

declare(strict_types=1);

namespace App\Modules\Organisation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $organisation_id
 * @property int $business_unit_id
 * @property string $name
 * @property StructureStatus $status
 */
final class Department extends Model
{
    protected $fillable = ['organisation_id', 'business_unit_id', 'name', 'code', 'status'];

    protected function casts(): array
    {
        return ['status' => StructureStatus::class];
    }

    /** @return BelongsTo<BusinessUnit, $this> */
    public function businessUnit(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    /** @return HasMany<Team, $this> */
    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }
}
