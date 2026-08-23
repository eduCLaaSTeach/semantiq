<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Models\User;
use App\Modules\Identity\Concerns\BelongsToOrganisation;
use App\Modules\Identity\Enums\ReviewDecision;
use App\Modules\Platform\Enums\LifecycleStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A periodic verification that access is still appropriate. Feature ADM-008.
 *
 * A review is EVIDENCE. Once its items are generated it holds a snapshot of
 * what access looked like at that instant, and the snapshot stays readable
 * after the access has changed - that is why items carry a label as well as an
 * id.
 *
 * @property string $name
 * @property LifecycleStatus $status
 */
class AccessReview extends Model
{
    use BelongsToOrganisation;

    protected $fillable = ['name', 'description', 'due_at', 'updated_by_user_id'];

    protected function casts(): array
    {
        return [
            'status' => LifecycleStatus::class,
            'due_at' => 'date',
            'opened_at' => 'datetime',
            'completed_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(AccessReviewItem::class);
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by_user_id');
    }

    /** Still editable, and no snapshot taken yet. */
    public function isDraft(): bool
    {
        return $this->status === LifecycleStatus::Draft;
    }

    /** Snapshot taken, decisions being made. */
    public function isOpen(): bool
    {
        return $this->status === LifecycleStatus::Open;
    }

    public function isCompleted(): bool
    {
        return $this->status === LifecycleStatus::Completed;
    }

    /**
     * How many items are still undecided.
     *
     * The number that decides whether a review may be completed. An untouched
     * item is never treated as an implicit "keep": a review where half the
     * items were never looked at is a finding, and folding it into the same
     * shape as a finished one would hide that.
     */
    public function undecidedCount(): int
    {
        return $this->items()->where('decision', ReviewDecision::Pending->value)->count();
    }

    /**
     * How many decided revocations have not been carried out.
     *
     * A review that was decided but never applied is its own finding, which is
     * why `applied_at` is separate from `completed_at`.
     */
    public function pendingRevocationCount(): int
    {
        return $this->items()
            ->where('decision', ReviewDecision::Revoke->value)
            ->where('applied', false)
            ->count();
    }
}
