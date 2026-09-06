<?php

declare(strict_types=1);

namespace App\Modules\Access\Models;

use App\Modules\Access\Support\ScopeType;
use App\Modules\Organisation\Models\BusinessUnit;
use App\Modules\Organisation\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One period during which one scope applied to one entitlement.
 *
 * SEVERAL CURRENT ROWS ON ONE ENTITLEMENT UNION. A record is in scope when ANY
 * of them covers it. One row never reduces another, and a narrower row beside a
 * broader one is redundant rather than restrictive.
 *
 * @property int $id
 * @property int $domain_entitlement_id
 * @property ScopeType $scope_type
 * @property int|null $team_id
 * @property int|null $business_unit_id
 * @property Carbon $assigned_at
 * @property Carbon|null $ended_at
 */
final class EntitlementScope extends Model
{
    protected $table = 'entitlement_scopes';

    protected $fillable = [
        'domain_entitlement_id',
        'scope_type',
        'team_id',
        'business_unit_id',
        'assigned_at',
        'ended_at',
        'assigned_by_user_id',
        'ended_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'scope_type' => ScopeType::class,
            'assigned_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<DomainEntitlement, $this> */
    public function entitlement(): BelongsTo
    {
        return $this->belongsTo(DomainEntitlement::class, 'domain_entitlement_id');
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<BusinessUnit, $this> */
    public function businessUnit(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    public function isCurrent(): bool
    {
        return $this->ended_at === null;
    }

    /**
     * The value that makes two scope rows duplicates of each other. Two current
     * rows with the same effective target are refused: they make revocation
     * ambiguous and the screen wrong, and they grant nothing the first does not.
     */
    public function effectiveTarget(): ?int
    {
        return match ($this->scope_type) {
            ScopeType::Team => $this->team_id,
            ScopeType::BusinessUnit => $this->business_unit_id,
            ScopeType::Own, ScopeType::Domain, ScopeType::Organisation => null,
        };
    }

    /**
     * @param  Builder<EntitlementScope>  $query
     * @return Builder<EntitlementScope>
     */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereNull('ended_at');
    }
}
