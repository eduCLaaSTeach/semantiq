<?php

declare(strict_types=1);

namespace App\Modules\Governance\Models;

use App\Models\User;
use App\Modules\Governance\Enums\RetentionStatus;
use App\Modules\Identity\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * How long one category of personal data is kept. Feature PDPA-03.
 *
 * **THIS RECORD DELETES NOTHING.** There is no sweep, no job and no deletion
 * path anywhere in gate 4 (SEC-DEC-038). A filled-in policy means somebody
 * wrote down an intention and, once approved, that a human agreed it. It does
 * not mean anything enforces it, and every screen showing this data says so
 * plainly - because a table full of periods reads as protection, and reading it
 * that way is how an organisation believes it is compliant while nothing
 * happens.
 *
 * THREE FIELDS ARE COMPLIANCE-OWNED AND SHIP EMPTY. `retention_months`, `basis`
 * and `lawful_basis` are judgements about law and about the customer's own
 * obligations. A plausible default written by software would be a compliance
 * claim nobody made. Null means Not Configured - never "forever", and never the
 * seven years the repository used to apply to everything.
 *
 * @property int $id
 * @property int $organisation_id
 * @property int $personal_data_category_id
 * @property int|null $retention_months
 * @property string|null $basis
 * @property string|null $lawful_basis
 * @property string|null $start_event
 * @property string|null $disposal_action
 * @property string|null $owner
 * @property string|null $exception_rule
 * @property Carbon|null $next_review_on
 * @property RetentionStatus $status
 */
class RetentionPolicy extends Model
{
    use BelongsToOrganisation;

    protected $fillable = [
        'retention_months',
        'basis',
        'lawful_basis',
        'start_event',
        'disposal_action',
        'owner',
        'exception_rule',
        'next_review_on',
        'updated_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RetentionStatus::class,
            'retention_months' => 'integer',
            'next_review_on' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(PersonalDataCategory::class, 'personal_data_category_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    /**
     * Whether a period has been decided at all.
     *
     * The question every screen asks first, because a policy with no period is
     * not a shorter policy - it is no policy.
     */
    public function hasPeriod(): bool
    {
        return $this->retention_months !== null;
    }

    /**
     * The period in words, or the honest absence of one.
     */
    public function periodLabel(): string
    {
        if (! $this->hasPeriod()) {
            return 'Not Configured';
        }

        $months = (int) $this->retention_months;

        if ($months % 12 === 0) {
            $years = intdiv($months, 12);

            return $years === 1 ? '1 year' : $years.' years';
        }

        return $months === 1 ? '1 month' : $months.' months';
    }

    /**
     * What is still unanswered on this policy.
     *
     * Returns the gaps rather than a boolean, so a screen can name them. A
     * warning badge with no explanation tells a reader something is wrong and
     * not what to do about it.
     *
     * The two compliance-owned fields count as gaps deliberately. A period with
     * no stated basis is a number somebody chose, and calling that complete
     * would be claiming compliance nobody signed off.
     *
     * @return list<string>
     */
    public function gaps(): array
    {
        $gaps = [];

        if (! $this->hasPeriod()) {
            $gaps[] = 'No retention period has been set.';
        }

        if (($this->basis ?? '') === '') {
            $gaps[] = 'No basis has been recorded for the period. This is compliance-owned text and '
                .'engineering cannot supply it.';
        }

        if (($this->lawful_basis ?? '') === '') {
            $gaps[] = 'No lawful basis has been recorded for holding this data.';
        }

        if (($this->start_event ?? '') === '') {
            $gaps[] = 'No start event has been set, so the period cannot be counted from anything.';
        }

        if (($this->disposal_action ?? '') === '') {
            $gaps[] = 'No disposal action has been chosen.';
        }

        if (($this->owner ?? '') === '') {
            $gaps[] = 'Nobody is recorded as accountable for this category.';
        }

        if ($this->next_review_on === null) {
            $gaps[] = 'No review date has been set.';
        }

        return $gaps;
    }

    /** Whether the review date has passed. Derived, never stored. */
    public function reviewIsOverdue(): bool
    {
        return $this->next_review_on !== null && Carbon::today()->gt($this->next_review_on);
    }

    /**
     * Whether this category is COMPLETE, which is not the same as protected.
     *
     * Named carefully. `isComplete()` would invite a screen to render a green
     * tick, and a complete retention policy still deletes nothing.
     */
    public function isFullyDescribed(): bool
    {
        return $this->gaps() === [];
    }
}
