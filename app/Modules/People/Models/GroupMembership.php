<?php

declare(strict_types=1);

namespace App\Modules\People\Models;

use App\Modules\Platform\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One period of membership. Ending it sets left_at; nothing deletes it.
 *
 * left_at NULL means CURRENT. Both boundaries are datetimes rather than dates,
 * so join -> leave -> rejoin on one calendar day is three honest periods instead
 * of a uniqueness collision - see the migration for why P1-01's key shape was
 * not copied.
 *
 * @property int $id
 * @property int $group_id
 * @property int $user_id
 * @property Carbon $joined_at
 * @property Carbon|null $left_at
 */
final class GroupMembership extends Model
{
    protected $fillable = ['group_id', 'user_id', 'joined_at', 'left_at'];

    protected function casts(): array
    {
        return ['joined_at' => 'datetime', 'left_at' => 'datetime'];
    }

    /** @return BelongsTo<Group, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isCurrent(): bool
    {
        return $this->left_at === null;
    }

    /**
     * @param  Builder<GroupMembership>  $query
     * @return Builder<GroupMembership>
     */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereNull('left_at');
    }
}
