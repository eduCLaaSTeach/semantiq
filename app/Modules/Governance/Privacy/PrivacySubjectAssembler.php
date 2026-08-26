<?php

declare(strict_types=1);

namespace App\Modules\Governance\Privacy;

use App\Models\User;
use App\Modules\Governance\Enums\DisclosureTreatment;
use App\Modules\Governance\Models\PrivacyRequest;
use App\Modules\Governance\Models\PrivacyRequestRecord;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Runs every collector for one request and stores the result.
 *
 * THE IDENTITY GATE IS ENFORCED HERE, not only at the state machine. A status
 * says where a row got to; a verification timestamp says that a person checked
 * something. Assembly asks for the second, so a bug that moved a request into
 * `assembling` without verification still collects nothing.
 *
 * ASSEMBLY IS REPLACE, NOT APPEND. Re-running deletes the previous rows and
 * writes fresh ones, inside a transaction. A response that mixed two runs
 * would show a reviewer a mixture of what was true then and what is true now,
 * and neither they nor anybody afterwards could tell which was which.
 *
 * Deleting `privacy_request_records` is NOT in tension with the append-only
 * rule: these are a working draft of a response, not evidence. The evidence is
 * the audit trail, which records every assembly, and
 * `privacy_correction_notes`, which genuinely cannot be touched.
 */
final class PrivacySubjectAssembler
{
    public function __construct(
        private readonly CollectorCatalogue $catalogue,
    ) {}

    /**
     * Collect everything about the subject of this request.
     *
     * @return int the number of items written
     */
    public function assemble(PrivacyRequest $request): int
    {
        if (! $request->isIdentityVerified()) {
            throw new RuntimeException(
                'Nothing may be collected for a request whose subject has not been identified. '
                .'Verification is what carries the weight an authenticated session would otherwise carry, '
                .'and assembling first would let anybody obtain a stranger\'s data by asserting they are them.'
            );
        }

        $items = [];

        foreach ($this->catalogue->all() as $collector) {
            foreach ($collector->collect($request) as $item) {
                $items[] = [$collector::class, $item];
            }
        }

        return DB::transaction(function () use ($request, $items): int {
            PrivacyRequestRecord::query()
                ->where('privacy_request_id', $request->getKey())
                ->delete();

            foreach ($items as [$collectorClass, $item]) {
                $record = new PrivacyRequestRecord;
                $record->organisation_id = $request->organisation_id;
                $record->privacy_request_id = $request->getKey();
                $record->band = $item->band;
                $record->source_table = $item->sourceTable;
                $record->collector = $collectorClass;
                $record->treatment = $item->treatment;
                $record->summary = $item->summary;
                $record->detail = $item->detail;
                $record->occurred_at = $item->occurredAt;
                $record->reviewer_action = 'kept';
                $record->save();
            }

            return count($items);
        });
    }

    /**
     * Change one item's treatment.
     *
     * NARROWING IS ONE PERSON'S CALL. WIDENING NEEDS TWO.
     *
     * That asymmetry is the control. A reviewer working at speed can always
     * choose to disclose less without anybody checking; choosing to disclose
     * MORE is the direction that can expose somebody who never asked for
     * anything, so it takes a second person who is not the first.
     *
     * @param  User  $approver  the second approver, required only when widening
     */
    public function retreat(
        PrivacyRequestRecord $record,
        DisclosureTreatment $to,
        User $reviewer,
        string $note,
        ?User $approver = null,
    ): PrivacyRequestRecord {
        $from = $record->treatment;

        if ($from === $to) {
            return $record;
        }

        $widening = $to->isWiderThan($from);

        if ($widening) {
            $this->assertSecondApproverIsValid($reviewer, $approver);
        }

        if (trim($note) === '') {
            throw new RuntimeException(
                'A change of treatment must record why. Without it nobody can later tell whether a '
                .'disclosure was considered or accidental.'
            );
        }

        /*
         * A widened item still carries no detail payload unless it was
         * collected with one. Widening cannot conjure data that was never
         * gathered - the collector decided what to load, and it declined to
         * load another person's identity in the first place.
         */
        $record->treatment = $to;
        $record->reviewer_action = $widening ? 'widened' : 'narrowed';
        $record->reviewer_note = $note;
        $record->save();

        return $record;
    }

    private function assertSecondApproverIsValid(User $reviewer, ?User $approver): void
    {
        if ($approver === null) {
            throw new RuntimeException(
                'Widening what is disclosed requires a second approver. Narrowing does not: being more '
                .'careful is always one person\'s call, and being less careful should not be.'
            );
        }

        if ($approver->getKey() === $reviewer->getKey()) {
            throw new RuntimeException(
                'The second approver for a widening must not be the reviewer who proposed it. One person '
                .'approving their own widening is one person deciding, whatever the record says.'
            );
        }
    }
}
