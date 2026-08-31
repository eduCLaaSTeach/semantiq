<?php

declare(strict_types=1);

namespace App\Modules\Organisation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $organisation_id
 * @property int $department_id
 * @property string $name
 * @property StructureStatus $status
 */
final class Team extends Model
{
    protected $fillable = ['organisation_id', 'department_id', 'name', 'code', 'status'];

    protected function casts(): array
    {
        return ['status' => StructureStatus::class];
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** @return HasMany<TeamMembership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(TeamMembership::class);
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }
}
