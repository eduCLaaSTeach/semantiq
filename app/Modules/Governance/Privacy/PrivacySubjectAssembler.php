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
     * NARROWING IS ONE PERSON'S CALL. WIDENING NEEDS TWO.
     *
     * That asymmetry is the control. A reviewer working at speed can always
     * choose to disclose less without anybody checking; choosing to disclose
     * MORE is the direction that can expose somebody who never asked for
     * anything, so it takes a second person who is not the first.
     *
     * THE CHANGE AND ITS AUDIT EVENT ARE ONE UNIT. Invariant 12, SEC-DEC-090.
     *
     * This method decides what a privacy response is allowed to disclose, and
     * for a while it recorded that decision nowhere. The second-approver rule
     * was enforced and then forgotten: `treatment`, `reviewer_action` and
     * `reviewer_note` were persisted, and nothing in the trail said who agreed
     * to disclose more about a third party, or when.
     *
     * That no screen calls this yet was not a defence. It is a public
     * persistent write path, and an unreachable user interface is not an audit
     * control - the first screen to call it would have inherited the gap in
     * silence. So `recordRequired()` is used, inside a transaction: if the
     * event cannot be written, the treatment does not change either.
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

        $this->assertReviewerIsTheAuthenticatedActor($reviewer);

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

        return DB::transaction(function () use ($record, $from, $to, $reviewer, $note, $approver, $widening): PrivacyRequestRecord {
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

            /*
             * WHAT THIS EVENT MAY CARRY. The shape of the decision, never the
             * data it was a decision about. No `summary`, no `detail`, no
             * subject name or address - those are what the treatment governs,
             * and copying them into the trail would disclose by the back door
             * exactly what band C exists to withhold. SEC-DEC-044 was applied
             * to every key below.
             *
             * The APPROVER IS AN ID, not a name. It resolves to a person for
             * anybody entitled to resolve it and says nothing to anybody who is
             * not. The reviewer is the actor of the event through the normal
             * mechanism, so the two parties are both on the row.
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
                    'second_approval_present' => $approver !== null,
                    'second_approver_user_id' => $approver?->getKey(),
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

    /**
     * A second approver must be a different person AND allowed to decide.
     *
     * "A SECOND PERSON" AND "A SECOND PERSON WHO MAY DECIDE THIS" ARE DIFFERENT
     * RULES, and only the second one is a control. This method previously
     * required an approver that was merely non-null and not the reviewer, which
     * **a Viewer satisfied** - so the safeguard on the one direction that can
     * expose somebody who never asked for anything could be met by any account
     * in the organisation.
     *
     * The permission asked for is `admin.privacy_requests.release`, not
     * `.manage`: widening what leaves SemantIQ is a disclosure decision, and it
     * belongs at the same System Administrator ceiling already approved for
     * release itself (SEC-DEC-083). Asked through `Authorization`, never by
     * comparing a role name - one implementation is what stops navigation, the
     * route middleware and service rules drifting apart.
     */
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

        if (! $this->authorization->allows($approver, 'admin.privacy_requests.release')) {
            throw new RuntimeException(
                'The second approver for a widening must be authorised to release a response. A second '
                .'pair of eyes that is not allowed to make the decision is not a second pair of eyes.'
            );
        }
    }
}
