<?php

declare(strict_types=1);

namespace App\Modules\Organisation\Models;

use App\Modules\Platform\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * D-15: the member is a users row. There is no people table.
 *
 * Leaving a team sets left_at; the row is retained. P1-07 access review will ask
 * "who was in this team in March", and a deleted row cannot answer it.
 *
 * @property int $id
 * @property int $organisation_id
 * @property int $team_id
 * @property int $user_id
 * @property Carbon $joined_at
 * @property Carbon|null $left_at
 */
final class TeamMembership extends Model
{
    protected $fillable = ['organisation_id', 'team_id', 'user_id', 'joined_at', 'left_at'];

    protected function casts(): array
    {
        return ['joined_at' => 'date', 'left_at' => 'date'];
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<TeamMembership>  $query
     * @return Builder<TeamMembership>
     */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereNull('left_at');
    }
}
