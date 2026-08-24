<?php

declare(strict_types=1);

namespace App\Modules\Governance\Models;

use App\Models\User;
use App\Modules\Governance\Enums\ExceptionStatus;
use App\Modules\Identity\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One recorded departure from the approved sovereignty profile. ADM-016.
 *
 * THE EXCEPTION NEVER TOUCHES THE PROFILE. It sits beside it. An exception that
 * edited `data_sovereignty_profiles` would make the approved position a lie,
 * and a year later would be indistinguishable from somebody having approved a
 * weaker position in the first place. This is why `isInForce()` exists and why
 * the profile screen shows exceptions as an overlay rather than folding them in.
 *
 * IN FORCE IS DERIVED, NEVER STORED. Three things must all hold: the status is
 * approved, today is not before the start date, and today is not after the end
 * date. An exception that lapsed at midnight stops applying at midnight, with
 * no job needing to have run - which is what keeps gate 4 free of a queue
 * dependency (SEC-DEC-069).
 *
 * @property int $id
 * @property int $organisation_id
 * @property string $title
 * @property string $justification
 * @property string $aspect
 * @property string|null $requested_geography
 * @property Carbon|null $starts_on
 * @property Carbon $ends_on
 * @property ExceptionStatus $status
 */
class SovereigntyException extends Model
{
    use BelongsToOrganisation;

    /**
     * `status` and every decision column are absent on purpose. Moving an
     * exception through its lifecycle is the service's job, and a lifecycle a
     * form can post is a lifecycle a form can skip.
     */
    protected $fillable = [
        'title',
        'justification',
        'aspect',
        'requested_geography',
        'scope_note',
        'starts_on',
        'ends_on',
        'updated_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ExceptionStatus::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
            'requested_at' => 'datetime',
            'decided_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * Whether this exception is actually permitting anything, right now.
     *
     * The ONE place status and dates are combined. Nothing else compares a
     * status itself, so an approved-but-lapsed exception cannot be read as
     * live by a screen that forgot to check the date.
     */
    public function isInForce(): bool
    {
        if (! $this->status->permitsForce()) {
            return false;
        }

        $today = Carbon::today();

        if ($this->starts_on !== null && $today->lt($this->starts_on)) {
            return false;
        }

        return ! $today->gt($this->ends_on);
    }

    /**
     * Approved, but its end date has passed.
     *
     * Distinct from `isInForce()` returning false for any other reason: a
     * reader needs to tell "this lapsed" from "this was rejected", and the
     * status alone says approved for both.
     */
    public function hasExpired(): bool
    {
        return $this->status->permitsForce() && Carbon::today()->gt($this->ends_on);
    }

    /** Approved, but not started yet. */
    public function isPending(): bool
    {
        return $this->status->permitsForce()
            && $this->starts_on !== null
            && Carbon::today()->lt($this->starts_on);
    }

    /**
     * How many days until it lapses. Negative once it has.
     *
     * Computed on read, like everything else about the window.
     */
    public function daysRemaining(): int
    {
        return (int) Carbon::today()->diffInDays($this->ends_on, false);
    }

    /**
     * A single word for what is happening, for the screen.
     *
     * Derived rather than stored so it cannot disagree with the dates, which is
     * the same reason SEC-DEC-060 gives for calculating a posture sentence from
     * the same result as its badge.
     */
    public function state(): string
    {
        return match (true) {
            $this->status === ExceptionStatus::Requested => 'Awaiting approval',
            $this->status === ExceptionStatus::Rejected => 'Rejected',
            $this->status === ExceptionStatus::Revoked => 'Revoked',
            $this->isPending() => 'Approved, starts '.$this->starts_on->format('j M Y'),
            $this->hasExpired() => 'Expired '.$this->ends_on->format('j M Y'),
            default => 'In force until '.$this->ends_on->format('j M Y'),
        };
    }

    /** The design system's badge class for the derived state. */
    public function stateBadge(): string
    {
        return match (true) {
            $this->isInForce() => 'badge badge-warning',
            $this->hasExpired() => 'badge',
            default => $this->status->badge(),
        };
    }

    public function scopeInForce(Builder $query): Builder
    {
        $today = Carbon::today();

        return $query->where('status', ExceptionStatus::Approved->value)
            ->where('ends_on', '>=', $today)
            ->where(function (Builder $q) use ($today): void {
                $q->whereNull('starts_on')->orWhere('starts_on', '<=', $today);
            });
    }

    public function scopeAwaitingDecision(Builder $query): Builder
    {
        return $query->where('status', ExceptionStatus::Requested->value);
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(DataSovereigntyProfile::class, 'data_sovereignty_profile_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by_user_id');
    }
}
