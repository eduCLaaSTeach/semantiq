<?php

declare(strict_types=1);

namespace App\Modules\Organisation\Models;

use App\Modules\Platform\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One link in the management chain. Both sides are users (D-15).
 *
 * A user has one current manager: one row with effective_to IS NULL.
 *
 * @property int $id
 * @property int $organisation_id
 * @property int $user_id
 * @property int $manager_id
 * @property Carbon $effective_from
 * @property Carbon|null $effective_to
 */
final class ManagementRelationship extends Model
{
    protected $fillable = ['organisation_id', 'user_id', 'manager_id', 'effective_from', 'effective_to'];

    protected function casts(): array
    {
        return ['effective_from' => 'date', 'effective_to' => 'date'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /**
     * @param  Builder<ManagementRelationship>  $query
     * @return Builder<ManagementRelationship>
     */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereNull('effective_to');
    }
}
