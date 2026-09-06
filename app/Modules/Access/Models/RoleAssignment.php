<?php

declare(strict_types=1);

namespace App\Modules\Access\Models;

use App\Modules\Access\Support\RoleCode;
use App\Modules\Organisation\Models\Organisation;
use App\Modules\Platform\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One period during which somebody held one role.
 *
 * THIS TABLE IS THE SOLE AUTHORITY for what role a person holds. There is no
 * users.platform_role to disagree with it - D-49 removed it rather than leaving
 * it as a compatibility read.
 *
 * A row is NEVER deleted and NEVER updated in place. Replacing a role ends the
 * open period and inserts the next, in ONE transaction, so the record of who
 * held what and when survives every later decision.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $organisation_id
 * @property RoleCode $role_code
 * @property Carbon $assigned_at
 * @property Carbon|null $ended_at
 * @property int|null $assigned_by_user_id
 * @property int|null $ended_by_user_id
 */
final class RoleAssignment extends Model
{
    protected $table = 'role_assignments';

    protected $fillable = [
        'user_id',
        'organisation_id',
        'role_code',
        'assigned_at',
        'ended_at',
        'assigned_by_user_id',
        'ended_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'role_code' => RoleCode::class,
            'assigned_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /** @return HasMany<DomainEntitlement, $this> */
    public function entitlements(): HasMany
    {
        return $this->hasMany(DomainEntitlement::class, 'role_assignment_id');
    }

    public function isCurrent(): bool
    {
        return $this->ended_at === null;
    }

    /**
     * Current means ended_at IS NULL. It says nothing about whether the account
     * is active - that is a separate question, asked separately, and the two
     * are never collapsed.
     *
     * @param  Builder<RoleAssignment>  $query
     * @return Builder<RoleAssignment>
     */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereNull('ended_at');
    }
}
