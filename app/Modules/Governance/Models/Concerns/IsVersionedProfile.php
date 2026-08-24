<?php

declare(strict_types=1);

namespace App\Modules\Governance\Models\Concerns;

use App\Models\User;
use App\Modules\Governance\Enums\ProfileStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * The versioned-profile behaviour shared by ADM-014 and ADM-015.
 *
 * Decision D4, recorded as SEC-DEC-065. A profile row is a VERSION. An approved
 * version is immutable: changing it writes a new version and supersedes the old
 * one, so "what was in force in March" stays answerable.
 *
 * THE IMMUTABILITY IS ENFORCED ON THE MODEL, not only in the service. A service
 * check protects the paths that go through the service; a model hook also
 * protects a console command, a future API and any code that reaches for
 * `save()` directly. Both exist, and the model hook is the one that cannot be
 * routed around by writing new code.
 *
 * IT IS NOT ENFORCED BY A DATABASE TRIGGER, and that is a deliberate difference
 * from `audit_events` and from `privacy_correction_notes` (SEC-DEC-066). Those
 * two are append-only EVIDENCE, where a mass delete that skips model hooks is
 * the realistic threat. A profile version is a configuration record: it is meant
 * to be superseded, its `superseded_at` and `superseded_by_id` are written by
 * the application after approval, and a trigger refusing every update would
 * forbid the supersession the design depends on.
 *
 * WHAT THE HOOK ALLOWS, and why the list is short. Once approved, only the
 * supersession columns may change. Everything else is frozen. Allowing anything
 * broader would let an approved position be edited under cover of "just fixing
 * a typo", which is the whole failure this decision exists to prevent.
 *
 * @phpstan-require-extends Model
 */
trait IsVersionedProfile
{
    /**
     * The only columns an approved version may still have written to it.
     *
     * @var list<string>
     */
    private const SUPERSESSION_COLUMNS = ['superseded_at', 'superseded_by_id', 'updated_at'];

    public static function bootIsVersionedProfile(): void
    {
        static::updating(function (Model $model): void {
            $original = $model->getOriginal('status');

            $wasApproved = $original === ProfileStatus::Approved->value
                || $original === ProfileStatus::Approved;

            if (! $wasApproved) {
                return;
            }

            $changed = array_keys($model->getDirty());
            $beyond = array_diff($changed, self::SUPERSESSION_COLUMNS);

            /*
             * Superseding is the one permitted change, and it also moves
             * `status` to superseded. That column is allowed only when it is
             * moving to exactly that value - never back to draft, which would
             * be an approved version being reopened.
             */
            if (in_array('status', $beyond, true)) {
                $target = $model->getAttribute('status');
                $isSuperseding = $target === ProfileStatus::Superseded
                    || $target === ProfileStatus::Superseded->value;

                if ($isSuperseding) {
                    $beyond = array_values(array_diff($beyond, ['status']));
                }
            }

            if ($beyond !== []) {
                throw new RuntimeException(
                    'An approved profile version cannot be edited. Create a new version instead. '
                    .'Refused change to: '.implode(', ', $beyond).'.'
                );
            }
        });

        /*
         * Deleting a version would take the point-in-time answer with it. There
         * is no delete path in gate 4 at all (SEC-DEC-038 holds), and this makes
         * that true of a stray call as well as of the screens.
         */
        static::deleting(function (): void {
            throw new RuntimeException(
                'A governance profile version cannot be deleted. Versions are kept so that what was '
                .'in force at an earlier date can still be read.'
            );
        });
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', ProfileStatus::Approved->value);
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', ProfileStatus::Draft->value);
    }

    /**
     * Whether this version is the organisation's actual position.
     *
     * Callers ask here rather than comparing statuses, so a seeded draft
     * (SEC-DEC-068) cannot be mistaken for a decision somebody made.
     */
    public function isInForce(): bool
    {
        return $this->status instanceof ProfileStatus && $this->status->isInForce();
    }

    public function isEditable(): bool
    {
        return $this->status instanceof ProfileStatus && $this->status->isEditable();
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
