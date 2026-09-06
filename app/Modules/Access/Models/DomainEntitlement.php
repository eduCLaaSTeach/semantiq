<?php

declare(strict_types=1);

namespace App\Modules\Access\Models;

use App\Modules\Domains\Models\BusinessDomain;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One period during which a role assignment was entitled to a business domain.
 *
 * A CURRENT ENTITLEMENT IS NOT THE SAME CLAIM AS AN EFFECTIVE ONE. Revoking its
 * last scope leaves this row current and authorising nothing - removing a child
 * must never silently mean the parent was revoked. isIncomplete() is what the
 * screen asks so it can say "No access - scope required" rather than presenting
 * a grant that does not work.
 *
 * @property int $id
 * @property int $role_assignment_id
 * @property int $business_domain_id
 * @property Carbon $granted_at
 * @property Carbon|null $ended_at
 */
final class DomainEntitlement extends Model
{
    protected $table = 'domain_entitlements';

    protected $fillable = [
        'role_assignment_id',
        'business_domain_id',
        'granted_at',
        'ended_at',
        'granted_by_user_id',
        'ended_by_user_id',
    ];

    protected function casts(): array
    {
        return ['granted_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    /** @return BelongsTo<RoleAssignment, $this> */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(RoleAssignment::class, 'role_assignment_id');
    }

    /** @return BelongsTo<BusinessDomain, $this> */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(BusinessDomain::class, 'business_domain_id');
    }

    /** @return HasMany<EntitlementScope, $this> */
    public function scopes(): HasMany
    {
        return $this->hasMany(EntitlementScope::class, 'domain_entitlement_id');
    }

    /** @return HasMany<EntitlementCeiling, $this> */
    public function ceilings(): HasMany
    {
        return $this->hasMany(EntitlementCeiling::class, 'domain_entitlement_id');
    }

    public function isCurrent(): bool
    {
        return $this->ended_at === null;
    }

    /**
     * @param  Builder<DomainEntitlement>  $query
     * @return Builder<DomainEntitlement>
     */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereNull('ended_at');
    }
}
