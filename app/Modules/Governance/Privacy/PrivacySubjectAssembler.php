<?php

declare(strict_types=1);

namespace App\Modules\Governance\Privacy;

use App\Models\User;
use App\Modules\Audit\Support\AuditLogger;
use App\Modules\Governance\Enums\DisclosureTreatment;
use App\Modules\Governance\Models\PrivacyRequest;
use App\Modules\Governance\Models\PrivacyRequestRecord;
use App\Modules\Identity\Support\Authorization;
use Illuminate\Support\Facades\Auth;
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
        private readonly AuditLogger $audit,
        private readonly Authorization $authorization,
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
     * NARROWING IS ALLOWED. WIDENING IS REFUSED. D5, fail closed. SEC-DEC-093.
     *
     * A reviewer working at speed can always choose to disclose LESS without
     * anybody checking. Choosing to disclose MORE can expose somebody who never
     * asked for anything, so it needs a second person - and this release has no
     * way to establish that a second person actually agreed.
     *
     * WHY THE SECOND-APPROVER ARGUMENT WAS REMOVED RATHER THAN KEPT.
     *
     * The previous version accepted a `User $approver`, checked they were a
     * different person, checked they held `admin.privacy_requests.release`, and
     * recorded them in the audit trail as having approved the widening.
     *
     * THEY HAD NOT APPROVED ANYTHING. They were an object handed over by the
     * reviewer. They never authenticated, never performed an approval action
     * and never saw the decision. Being ALLOWED to approve is not evidence of
     * having approved, and a reviewer could have named any System Administrator
     * in the organisation.
     *
     * That is worse than having no rule at all, because the audit trail would
     * have carried a false statement - "this person approved" - in the one
     * record whose entire purpose is to show that two people were involved.
     *
     * So widening is refused outright, and the parameter is gone. An argument
     * that cannot mean what it appears to mean should not be accepted; the API
     * is unmerged, so nothing depends on it. The refusal message says the
     * workflow is not enabled rather than implying the caller did something
     * wrong, because they did not.
     *
     * The approval workflow itself - an independently authenticated second
     * person performing their own action - is NOT assigned to any batch. Its
     * scope needs separate approval.
     */
    public function retreat(
        PrivacyRequestRecord $record,
        DisclosureTreatment $to,
        User $reviewer,
        string $note,
    ): PrivacyRequestRecord {
        $from = $record->treatment;

        if ($from === $to) {
            return $record;
        }

        $this->assertReviewerIsTheAuthenticatedActor($reviewer);

        if ($to->isWiderThan($from)) {
            throw new RuntimeException(
                'Widening a privacy-response disclosure requires an independently authenticated second '
                .'approval. That approval workflow is not enabled in this release, so a disclosure can '
                .'be narrowed but not widened. Raise it with whoever owns this request if more needs to '
                .'be disclosed.'
            );
        }

        if (trim($note) === '') {
            throw new RuntimeException(
                'A change of treatment must record why. Without it nobody can later tell whether a '
                .'disclosure was considered or accidental.'
            );
        }

        return DB::transaction(function () use ($record, $from, $to, $reviewer, $note): PrivacyRequestRecord {
            /*
             * A widened item still carries no detail payload unless it was
             * collected with one. Widening cannot conjure data that was never
             * gathered - the collector decided what to load, and it declined to
             * load another person's identity in the first place.
             */
            $record->treatment = $to;
            /* Only ever `narrowed` while widening is closed. The column keeps
             * its wider vocabulary because the stored history from before this
             * rule, and any future approved widening, both use it. */
            $record->reviewer_action = 'narrowed';
            $record->reviewer_note = $note;
            $record->save();

            /*
             * WHAT THIS EVENT MAY CARRY. The shape of the decision, never the
             * data it was a decision about. No `summary`, no `detail`, no
             * subject name or address - those are what the treatment governs,
             * and copying them into the trail would disclose by the back door
             * exactly what band C exists to withhold. SEC-DEC-044 was applied
             * to every key below.
             *
             * THIS EVENT RECORDS THE AUTHENTICATED REVIEWER AND THE NARROWING
             * DECISION ONLY. `reviewer_user_id` is an id rather than a name: it
             * resolves to a person for anybody entitled to resolve it and says
             * nothing to anybody who is not.
             *
             * There is deliberately NO SECOND-APPROVER FIELD. Widening is not
             * supported until an independently authenticated second-approval
             * workflow exists, so there is no second party to name - and an
             * event that named one would be asserting an approval nobody had
             * given. SEC-DEC-093.
             */
            $this->audit->recordRequired(
                action: 'governance.privacy_request.treatment_changed',
                module: 'Governance',
                resourceType: 'privacy_request',
                resourceId: $record->privacy_request_id,
                before: ['treatment' => $from->value],
                after: [
                    'request_reference' => $record->request?->reference,
                    /* Written explicitly rather than left to the actor column.
                     * The two are now forced to agree, and the row says so
                     * without a reader having to know that they are. */
                    'reviewer_user_id' => $reviewer->getKey(),
                    'record_id' => $record->getKey(),
                    'source_table' => $record->source_table,
                    'band' => $record->band->value,
                    'treatment' => $to->value,
                    'reviewer_action' => $record->reviewer_action,
                ],
                reason: $note,
            );

            return $record;
        });
    }

    /**
     * The person named as reviewer must be the person who is signed in.
     *
     * WHY THIS IS A SERVICE CONCERN AND NOT A CONTROLLER ONE. This method took
     * a `User $reviewer` while `AuditLogger` derives its actor from
     * `Auth::user()`, and nothing made them the same person. A caller could
     * pass Alice as the reviewer while Charlie was signed in: the decision
     * evaluated against Alice's authority, the event attributed to Charlie,
     * each half correct on its own and the pair worthless as evidence.
     *
     * A public method is callable, and the next caller may be a console
     * command, a queued job or a controller written by somebody who never read
     * this file. Every one of those bypasses a controller check. So the
     * agreement is established here. Invariant 12a, SEC-DEC-091.
     *
     * There is deliberately no "acting on behalf of" path. Changing what a
     * privacy response discloses is an interactive decision by a named person,
     * and an unauthenticated context has no such person to name.
     */
    private function assertReviewerIsTheAuthenticatedActor(User $reviewer): void
    {
        $actor = Auth::user();

        if ($actor === null) {
            throw new RuntimeException(
                'Changing what a response discloses is a decision by a named person, so it cannot be '
                .'done without one signed in. There is no unattended path for this.'
            );
        }

        if ($actor->getAuthIdentifier() !== $reviewer->getKey()) {
            throw new RuntimeException(
                'The reviewer of a disclosure change must be the person making it. Recording somebody '
                .'else as the reviewer would put one name on the decision and another on the audit '
                .'event, and neither could then be shown to be the true one.'
            );
        }

        if (! $this->authorization->allows($reviewer, 'admin.privacy_requests.manage')) {
            throw new RuntimeException(
                'You are not authorised to change what a privacy response discloses.'
            );
        }
    }
}
