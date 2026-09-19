<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Models;

use App\Modules\Platform\Models\User;
use App\Modules\Reviews\Support\ReviewState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One deliberate act of saying "confirm this population now". D-89.
 *
 * THERE IS DELIBERATELY NO closed_at. A cycle is open exactly while it has a
 * pending item, and that is derived here rather than stored, so it cannot drift
 * from the items. A stored flag would also need something to maintain it, and
 * nothing in this deployment runs on a timer.
 */
final class AccessReviewCycle extends Model
{
    protected $fillable = [
        'organisation_id',
        'started_at',
        'due_at',
        'started_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'due_at' => 'datetime',
        ];
    }

    /** @return HasMany<AccessReviewItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(AccessReviewItem::class);
    }

    /** @return BelongsTo<User, $this> */
    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by_user_id');
    }

    /** Derived, never stored. */
    public function isOpen(): bool
    {
        return $this->items()->where('state', ReviewState::Pending->value)->exists();
    }
}
