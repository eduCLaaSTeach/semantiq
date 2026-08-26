<?php

declare(strict_types=1);

namespace App\Modules\Governance\Models;

use App\Models\User;
use App\Modules\Governance\Enums\PrivacyRequestStatus;
use App\Modules\Governance\Enums\PrivacyRequestType;
use App\Modules\Identity\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A request from a person about their own personal data. Feature PDPA-01.
 *
 * THE SUBJECT MAY HAVE NO ACCOUNT. `subject_user_id` is nullable by decision
 * D6, and `subject_name` / `subject_email` are held on this row independently
 * so a request survives the deletion of the account it was about.
 *
 * NOTHING IS COLLECTED BEFORE IDENTITY IS VERIFIED. `isIdentityVerified()` is
 * the fact; `PrivacyRequests` enforces it at every transition into collection.
 * The state alone is not treated as evidence, because a state can be reached by
 * a bug and a verification timestamp cannot.
 */
class PrivacyRequest extends Model
{
    use BelongsToOrganisation;

    /**
     * `status`, every verification column and every decision column are absent
     * on purpose. Moving a request through its lifecycle is the service's job,
     * and a lifecycle a form can post is a lifecycle a form can skip.
     */
    protected $fillable = [
        'request_type',
        'subject_user_id',
        'subject_name',
        'subject_email',
        'subject_reference',
        'received_at',
        'received_channel',
        'updated_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PrivacyRequestStatus::class,
            'request_type' => PrivacyRequestType::class,
            'received_at' => 'datetime',
            'identity_verified_at' => 'datetime',
            'assembled_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'released_at' => 'datetime',
            'closed_at' => 'datetime',
            'due_at' => 'datetime',
        ];
    }

    /**
     * The hard gate. Everything downstream of collection asks this, not the
     * status, because a timestamp records that a person did something and a
     * status only records where the row got to.
     */
    public function isIdentityVerified(): bool
    {
        return $this->identity_verified_at !== null;
    }

    /**
     * Why this response cannot be released yet, or null when it can.
     *
     * SEPARATION OF DUTIES IS ENFORCED IN THE SERVICE, NOT BY THE PERMISSION.
     * A System Administrator holds both `.manage` and `.release`, so the tier
     * split alone stops nobody from assembling a response, reviewing their own
     * work and then authorising its disclosure. Two permissions held by one
     * person are one person.
     *
     * This method exists so the SCREEN CAN SAY WHY rather than silently hiding
     * the button. A control that vanishes without explanation reads as a bug,
     * and the person who needs to act cannot tell what to do next.
     *
     * `null` means releasable BY THIS ACTOR. The service re-checks; this is for
     * rendering.
     */
    public function releaseBlockedBecause(?User $actor): ?string
    {
        /*
         * ALREADY ANSWERED IS A BLOCKER, not a repeat.
         *
         * `moveTo()` returns early when a request is already in the state
         * being asked for, so a second release of an already-released request
         * reached the writes and REPLACED `released_at`, `released_by_user_id`
         * and `evidence_reference` without the state machine ever objecting.
         * Who authorised a disclosure and when is the whole evidential value
         * of this row, and it must not be quietly overwritten by anybody who
         * clicks twice. SEC-DEC-088.
         */
        if ($this->decision !== null || $this->released_at !== null) {
            return 'This request has already been answered. The decision, who made it and when are part '
                .'of the record and are not overwritten. Raise a new request if something further is needed.';
        }

        if (! $this->isIdentityVerified()) {
            return 'The requester has not been identified yet. Nothing may be collected, let alone released.';
        }

        if ($this->assembled_at === null) {
            return 'Nothing has been collected yet. Run the collection first.';
        }

        if ($this->reviewed_at === null || $this->reviewed_by_user_id === null) {
            return 'Nobody has reviewed the assembled response yet. A response must be read by a person '
                .'before it can be authorised to leave SemantIQ.';
        }

        if ($actor === null) {
            return 'Sign in to release a response.';
        }

        if ($actor->getKey() === $this->reviewed_by_user_id) {
            return 'You reviewed this response, so you cannot also authorise its disclosure. Somebody else '
                .'must release it - one person agreeing with themselves is not a second pair of eyes.';
        }

        if ($this->assembled_by_user_id !== null && $actor->getKey() === $this->assembled_by_user_id) {
            return 'You assembled this response, so you cannot also authorise its disclosure. Somebody else '
                .'must release it.';
        }

        return null;
    }

    public function canBeReleasedBy(?User $actor): bool
    {
        return $this->releaseBlockedBecause($actor) === null;
    }

    public function isOpen(): bool
    {
        return ! $this->status->isTerminal();
    }

    /**
     * Whether the deadline has passed, derived on read rather than stored.
     *
     * `due_at` itself IS stored, frozen at verification - the derived thing is
     * only whether now is past it. SEC-DEC-069: no queue, no scheduler, and
     * nothing that can go stale between reads.
     */
    public function isOverdue(): bool
    {
        return $this->due_at !== null
            && $this->isOpen()
            && $this->due_at->isPast();
    }

    /**
     * Whole days remaining, negative when overdue. Zero means today.
     */
    public function daysRemaining(): int
    {
        if ($this->due_at === null) {
            return 0;
        }

        return (int) Carbon::now()->startOfDay()->diffInDays($this->due_at->startOfDay(), false);
    }

    /**
     * How the register should describe where this request stands, in one line.
     */
    public function urgency(): string
    {
        if (! $this->isOpen()) {
            return $this->status->label();
        }

        if ($this->due_at === null) {
            return 'No deadline yet';
        }

        $days = $this->daysRemaining();

        return match (true) {
            $days < 0 => abs($days).' day'.(abs($days) === 1 ? '' : 's').' overdue',
            $days === 0 => 'Due today',
            default => $days.' day'.($days === 1 ? '' : 's').' left',
        };
    }

    public function urgencyBadge(): string
    {
        if (! $this->isOpen()) {
            return 'badge-neutral';
        }

        return match (true) {
            $this->isOverdue() => 'badge-danger',
            $this->due_at !== null && $this->daysRemaining() <= 3 => 'badge-warning',
            default => 'badge-info',
        };
    }

    /**
     * Whether this request is about somebody with a SemantIQ account.
     *
     * Used by the screens to explain WHY a collection found little: a subject
     * with no account has no band A record at all, and saying so is different
     * from silently returning nothing.
     */
    public function subjectHasAccount(): bool
    {
        return $this->subject_user_id !== null;
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', [PrivacyRequestStatus::Closed->value]);
    }

    public function scopeAwaitingVerification(Builder $query): Builder
    {
        return $query->whereNull('identity_verified_at')->open();
    }

    public function records(): HasMany
    {
        return $this->hasMany(PrivacyRequestRecord::class);
    }

    public function correctionNotes(): HasMany
    {
        return $this->hasMany(PrivacyCorrectionNote::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_user_id');
    }

    public function identityVerifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'identity_verified_by_user_id');
    }

    public function assembledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assembled_by_user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by_user_id');
    }
}
