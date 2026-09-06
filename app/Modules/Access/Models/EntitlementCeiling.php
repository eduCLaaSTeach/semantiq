<?php

declare(strict_types=1);

namespace App\Modules\Access\Models;

use App\Modules\Access\Support\Sensitivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One period during which one sensitivity ceiling applied to one entitlement.
 *
 * At most ONE current row per entitlement. Clearing writes a current `standard`
 * row rather than deleting: absence is a fault, and a fault denies.
 *
 * @property int $id
 * @property int $domain_entitlement_id
 * @property Sensitivity $sensitivity
 * @property Carbon $assigned_at
 * @property Carbon|null $ended_at
 */
final class EntitlementCeiling extends Model
{
    protected $table = 'entitlement_ceilings';

    protected $fillable = [
        'domain_entitlement_id',
        'sensitivity',
        'assigned_at',
        'ended_at',
        'assigned_by_user_id',
        'ended_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'sensitivity' => Sensitivity::class,
            'assigned_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<DomainEntitlement, $this> */
    public function entitlement(): BelongsTo
    {
        return $this->belongsTo(DomainEntitlement::class, 'domain_entitlement_id');
    }

    public function isCurrent(): bool
    {
        return $this->ended_at === null;
    }

    /**
     * @param  Builder<EntitlementCeiling>  $query
     * @return Builder<EntitlementCeiling>
     */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereNull('ended_at');
    }
}
