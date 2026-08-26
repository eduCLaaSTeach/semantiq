<?php

declare(strict_types=1);

namespace App\Modules\Governance\Services;

use App\Models\User;
use App\Modules\Audit\Support\AuditLogger;
use App\Modules\Governance\Enums\CorrectionOutcome;
use App\Modules\Governance\Exceptions\GovernanceStorageNotInitialised;
use App\Modules\Governance\Models\PrivacyCorrectionNote;
use App\Modules\Governance\Models\PrivacyRequest;
use App\Modules\Governance\Support\GovernanceStorage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Disputes about records, and what was decided. Feature PDPA-01, decision D11.
 *
 * A NOTE IS WRITTEN ONCE, COMPLETE. There is no update method here and no
 * delete method, and the model refuses both. The database triggers required by
 * SEC-DEC-066 are the actual control; this class simply never asks.
 *
 * WHY `noted` IS A SUCCESSFUL OUTCOME AND NOT A FAILURE. Where the disputed
 * record is an audit event, recording the dispute IS the complete remedy: the
 * trail cannot be edited, so the permanent annotation beside it is what the
 * subject is entitled to. Presenting that as a lesser result than `applied`
 * would push reviewers toward editing things that must not be edited.
 */
final class CorrectionNotes
{
    public function __construct(
        private readonly GovernanceStorage $storage,
        private readonly AuditLogger $audit,
    ) {}

    public function forRequest(PrivacyRequest $request): Collection
    {
        if (! $this->storage->privacyRequestsAreReady()) {
            return new Collection;
        }

        return PrivacyCorrectionNote::query()
            ->where('privacy_request_id', $request->getKey())
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Record what the subject asserts is wrong, and the decision about it.
     *
     * The assertion and the decision are written together, in one row, once.
     * Recording the assertion first and deciding later would mean updating the
     * row, and this table cannot be updated.
     */
    public function record(
        PrivacyRequest $request,
        User $actor,
        string $assertion,
        CorrectionOutcome $outcome,
        string $reason,
        ?int $auditEventId = null,
    ): PrivacyCorrectionNote {
        if (! $this->storage->privacyRequestsAreReady()) {
            throw GovernanceStorageNotInitialised::forWrite('The correction note');
        }

        if (trim($assertion) === '') {
            throw new RuntimeException(
                'Record what the person actually said is wrong, in their terms. A note without the '
                .'assertion records only that somebody complained.'
            );
        }

        if (trim($reason) === '') {
            throw new RuntimeException(
                'Record why this outcome was reached, including when the record was corrected. '
                .'"Corrected" alone does not say what was corrected or on what basis.'
            );
        }

        /*
         * THE NOTE AND ITS AUDIT EVENT ARE ONE UNIT.
         *
         * This table cannot be updated or deleted - the triggers refuse - so a
         * note written without its audit event could never be corrected or
         * withdrawn afterwards. It would sit there permanently, recording that
         * somebody disputed a record, with nothing saying who decided what or
         * when. `recordRequired()` throws rather than returning null, and this
         * transaction is what turns that throw into "the note was never
         * written" instead of "the note is now unexplainable, for ever".
         * SEC-DEC-089.
         */
        return DB::transaction(function () use (
            $request, $actor, $assertion, $outcome, $reason, $auditEventId
        ): PrivacyCorrectionNote {
            $note = new PrivacyCorrectionNote;
            $note->fill([
                'privacy_request_id' => $request->getKey(),
                'audit_event_id' => $auditEventId,
                'subject_assertion' => $assertion,
            ]);
            $note->forceFill([
                'outcome' => $outcome,
                'outcome_reason' => $reason,
                'decided_by_user_id' => $actor->getKey(),
                'decided_at' => now()->utc(),
                'created_by_user_id' => $actor->getKey(),
            ]);
            $note->save();

            $this->audit->recordRequired(
                action: match ($outcome) {
                    CorrectionOutcome::Noted => 'governance.privacy_correction.noted',
                    CorrectionOutcome::Applied => 'governance.privacy_correction.applied',
                    CorrectionOutcome::Refused => 'governance.privacy_correction.refused',
                },
                module: 'Governance',
                resourceType: 'privacy_correction_note',
                resourceId: $note->getKey(),
                after: [
                    'request_reference' => $request->reference,
                    'outcome' => $outcome->value,
                    'annotates_event' => $auditEventId !== null,
                ],
                reason: $reason,
            );

            return $note;
        });
    }
}
