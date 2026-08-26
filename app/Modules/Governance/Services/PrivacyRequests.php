<?php

declare(strict_types=1);

namespace App\Modules\Governance\Services;

use App\Models\User;
use App\Modules\Audit\Support\AuditLogger;
use App\Modules\Governance\Enums\PrivacyRequestStatus;
use App\Modules\Governance\Exceptions\GovernanceStorageNotInitialised;
use App\Modules\Governance\Models\PrivacyRequest;
use App\Modules\Governance\Privacy\PrivacySubjectAssembler;
use App\Modules\Governance\Support\GovernanceStorage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * The lifecycle of a privacy request. Feature PDPA-01.
 *
 * EVERY TRANSITION GOES THROUGH `moveTo()`, which consults the enum. There is
 * no second path, so a state machine that lives in one place cannot drift from
 * a state machine that lives in a controller.
 *
 * A LIFECYCLE OPERATION IS ALL OR NOTHING. SEC-DEC-088.
 *
 * These methods used to write their business fields and their audit event
 * first and validate the transition last. When the transition turned out to be
 * illegal the exception left the earlier writes behind, so a request could
 * carry `released_at`, `released_by_user_id`, `decision = released`, an
 * `evidence_reference` and a `governance.privacy_request.released` audit event
 * while its status still said `closed`.
 *
 * That is worse than either outcome on its own. The status says the response
 * was never released, the evidence says it was, and both are in the same row.
 * A regulator reading the audit trail and a reviewer reading the screen reach
 * opposite conclusions and neither can tell which is true. For a table whose
 * entire purpose is to be evidence of what was disclosed, an inconsistent row
 * is not a smaller problem than a refused operation - it is a bigger one.
 *
 * Every lifecycle method therefore follows the same shape, in this order:
 *
 *   1. refuse what can be refused without touching anything - the storage
 *      check, the input checks, the separation-of-duties check, and
 *      `assertCanMoveTo()`, which reads the transition table and writes
 *      nothing;
 *   2. open a transaction;
 *   3. write the business fields;
 *   4. write the assembled records and the audit event;
 *   5. change the status through `moveTo()`;
 *   6. commit only if all of that succeeded.
 *
 * VALIDATING EARLIER IS NOT ENOUGH ON ITS OWN, which is why step 2 exists.
 * Moving `moveTo()` to the top would leave a request sitting in `responded`
 * with no `released_at` if the audit write then failed, which is the same
 * contradiction pointing the other way. Only the transaction removes the class
 * of defect rather than one member of it.
 *
 * THE IDENTITY GATE IS CHECKED TWICE, deliberately. `moveTo()` refuses to enter
 * a collecting state for an unverified subject; `assemble()` independently
 * refuses to collect for one. Two checks on the most dangerous operation in the
 * gate is proportionate: the failure mode is handing a stranger somebody else's
 * personal data.
 *
 * NOTHING HERE WRITES A FILE, SENDS MAIL, QUEUES A JOB OR SCHEDULES ANYTHING.
 * Decisions D9 and D10. `evidence_reference` records how a response was
 * delivered by a person, outside the application.
 */
final class PrivacyRequests
{
    public function __construct(
        private readonly GovernanceStorage $storage,
        private readonly PrivacySubjectAssembler $assembler,
        private readonly AuditLogger $audit,
    ) {}

    public function all(): Collection
    {
        if (! $this->storage->privacyRequestsAreReady()) {
            return new Collection;
        }

        return PrivacyRequest::query()
            ->orderByRaw('closed_at is not null')
            ->orderByRaw('due_at is null')
            ->orderBy('due_at')
            ->orderByDesc('received_at')
            ->get();
    }

    public function find(int $id): ?PrivacyRequest
    {
        if (! $this->storage->privacyRequestsAreReady()) {
            return null;
        }

        /* Organisation scope is global on this model, so another
         * organisation's id simply does not resolve. */
        return PrivacyRequest::query()->find($id);
    }

    public function openCount(): int
    {
        return $this->storage->privacyRequestsAreReady()
            ? PrivacyRequest::query()->open()->count()
            : 0;
    }

    /**
     * Record a request that has arrived. It discloses nothing yet.
     *
     * @param  array<string, mixed>  $values
     */
    public function receive(array $values, User $actor): PrivacyRequest
    {
        $this->assertWritable();

        /* A new row starts at `received`, and `received` always permits
         * `identity_verification`, so there is nothing to assert here that is
         * not already true by construction. The transaction still matters: the
         * row and its audit event must both exist, or neither. */
        return DB::transaction(function () use ($values, $actor): PrivacyRequest {
            $request = new PrivacyRequest;
            $request->fill($values);
            $request->forceFill([
                'reference' => $this->nextReference(),
                'status' => PrivacyRequestStatus::Received,
                'created_by_user_id' => $actor->getKey(),
                'updated_by_user_id' => $actor->getKey(),
            ]);
            $request->save();

            $this->audit->record(
                action: 'governance.privacy_request.received',
                module: 'Governance',
                resourceType: 'privacy_request',
                resourceId: $request->getKey(),
                after: $this->summarise($request),
            );

            return $this->moveTo($request, PrivacyRequestStatus::IdentityVerification, $actor);
        });
    }

    /**
     * Confirm who the requester is. Until this happens, nothing is collected.
     *
     * `due_at` IS FROZEN HERE. A deadline recomputed later from a policy
     * somebody edited would silently move a date a person is being held to.
     */
    public function verifyIdentity(
        PrivacyRequest $request,
        User $actor,
        string $method,
        string $note,
    ): PrivacyRequest {
        $this->assertWritable();

        if ($request->isIdentityVerified()) {
            throw new RuntimeException('This request has already been verified.');
        }

        if (! array_key_exists($method, $this->verificationMethods())) {
            throw new RuntimeException(
                'Unknown verification method. The list is codified in config/governance.php so that what '
                .'was checked is a comparable fact rather than free text.'
            );
        }

        if (trim($note) === '') {
            throw new RuntimeException(
                'Record what was actually checked. "Verified" without a note is somebody\'s word for it.'
            );
        }

        $this->assertCanMoveTo($request->status, PrivacyRequestStatus::Assembling);

        return $this->atomically($request, function () use ($request, $actor, $method, $note): PrivacyRequest {
            $request->forceFill([
                'identity_verified_at' => now()->utc(),
                'identity_verified_by_user_id' => $actor->getKey(),
                'identity_verification_method' => $method,
                'identity_verification_note' => $note,
                'due_at' => now()->utc()->addDays($this->responseDueDays()),
                'updated_by_user_id' => $actor->getKey(),
            ])->save();

            $this->audit->record(
                action: 'governance.privacy_request.identity_verified',
                module: 'Governance',
                resourceType: 'privacy_request',
                resourceId: $request->getKey(),
                after: ['method' => $method, 'due_at' => $request->due_at?->toIso8601String()],
                reason: $note,
            );

            return $this->moveTo($request, PrivacyRequestStatus::Assembling, $actor);
        });
    }

    /**
     * Collect what is held about the subject.
     *
     * The assembler refuses an unverified subject independently of the state
     * machine. Both checks stay.
     *
     * A RE-RUN PASSES THROUGH `assembling` AND BACK. That intermediate state is
     * legitimate - it is what `permitsCollection()` gates on - but it must
     * never be observable on its own, so both legs are asserted up front and
     * both happen inside the one transaction. Either the request ends in
     * `in_review` with new records and an audit event, or it never left where
     * it started.
     */
    public function assemble(PrivacyRequest $request, User $actor): PrivacyRequest
    {
        $this->assertWritable();

        if (! $request->isIdentityVerified()) {
            throw new RuntimeException(
                'Nothing may be collected for a request whose subject has not been identified.'
            );
        }

        $reRun = $request->status === PrivacyRequestStatus::InReview;

        if ($reRun) {
            $this->assertCanMoveTo($request->status, PrivacyRequestStatus::Assembling);
            $this->assertCanMoveTo(PrivacyRequestStatus::Assembling, PrivacyRequestStatus::InReview);
        } else {
            $this->assertCanMoveTo($request->status, PrivacyRequestStatus::InReview);
        }

        return $this->atomically($request, function () use ($request, $actor, $reRun): PrivacyRequest {
            $working = $reRun
                ? $this->moveTo($request, PrivacyRequestStatus::Assembling, $actor)
                : $request;

            $written = $this->assembler->assemble($working);

            $working->forceFill([
                'assembled_at' => now()->utc(),
                /* Recorded so separation of duties can be enforced against the
                 * person who actually ran the collection, not against a tier. */
                'assembled_by_user_id' => $actor->getKey(),
                'updated_by_user_id' => $actor->getKey(),
            ])->save();

            $this->audit->record(
                action: 'governance.privacy_request.assembled',
                module: 'Governance',
                resourceType: 'privacy_request',
                resourceId: $working->getKey(),
                after: ['items' => $written],
            );

            return $this->moveTo($working, PrivacyRequestStatus::InReview, $actor);
        });
    }

    /**
     * Record that a reviewer has been through the assembled response.
     */
    public function markReviewed(PrivacyRequest $request, User $actor): PrivacyRequest
    {
        $this->assertWritable();

        $next = $request->request_type->needsDecision()
            ? PrivacyRequestStatus::AwaitingDecision
            : PrivacyRequestStatus::InReview;

        $this->assertCanMoveTo($request->status, $next);

        return $this->atomically($request, function () use ($request, $actor, $next): PrivacyRequest {
            $request->forceFill([
                'reviewed_at' => now()->utc(),
                'reviewed_by_user_id' => $actor->getKey(),
                'updated_by_user_id' => $actor->getKey(),
            ])->save();

            $this->audit->record(
                action: 'governance.privacy_request.reviewed',
                module: 'Governance',
                resourceType: 'privacy_request',
                resourceId: $request->getKey(),
            );

            return $this->moveTo($request, $next, $actor);
        });
    }

    /**
     * Authorise disclosure.
     *
     * A DIFFERENT ACT FROM ASSEMBLING OR REVIEWING ONE, AND ENFORCED HERE
     * RATHER THAN BY THE PERMISSION.
     *
     * The permission split was never sufficient on its own. A System
     * Administrator holds `.manage` AND `.release`, so nothing in the
     * permission model stopped one person receiving a request, verifying the
     * identity, assembling the response, and then authorising its own
     * disclosure. Two permissions held by one person are one person, and a
     * separation of duties that only exists in the tier table is a separation
     * of duties that does not exist.
     *
     * Four conditions, all server-side, all refused with a reason:
     *
     *   1. the response has actually been reviewed  (`reviewed_at`)
     *   2. by an identified person                  (`reviewed_by_user_id`)
     *   3. who is not the person releasing it
     *   4. and the releaser did not assemble it either
     *
     * Condition 4 goes beyond the minimum because `assembled_by_user_id` is
     * recorded: if the reviewer and assembler are the same person, requiring
     * only `reviewer != releaser` would still be two people, but requiring both
     * closes the case where a third party reviews work that the releaser
     * assembled and then rubber-stamps it back to them.
     *
     * NONE OF THAT CATCHES A THIRD PERSON RELEASING AN ALREADY-CLOSED REQUEST -
     * they neither assembled nor reviewed it, so all four conditions pass and
     * only the transition table can refuse. That is exactly why the transition
     * is now asserted here, before any field is written.
     *
     * REFUSAL IS NOT GATED THE SAME WAY, deliberately. A refusal discloses
     * nothing - the risk this control exists for is releasing somebody's
     * personal data on one person's say-so, and refusing does the opposite.
     * Refusal still requires `.release` and a written reason.
     */
    public function release(PrivacyRequest $request, User $actor, string $evidenceReference): PrivacyRequest
    {
        $this->assertWritable();

        $blocker = $request->releaseBlockedBecause($actor);

        if ($blocker !== null) {
            throw new RuntimeException($blocker);
        }

        if (trim($evidenceReference) === '') {
            throw new RuntimeException(
                'Record how the response was delivered. SemantIQ sends nothing itself, so without this '
                .'there is no evidence the person ever received an answer.'
            );
        }

        $this->assertCanMoveTo($request->status, PrivacyRequestStatus::Responded);

        return $this->atomically($request, function () use ($request, $actor, $evidenceReference): PrivacyRequest {
            $request->forceFill([
                'decision' => 'released',
                'released_at' => now()->utc(),
                'released_by_user_id' => $actor->getKey(),
                'evidence_reference' => $evidenceReference,
                'updated_by_user_id' => $actor->getKey(),
            ])->save();

            $this->audit->record(
                action: 'governance.privacy_request.released',
                module: 'Governance',
                resourceType: 'privacy_request',
                resourceId: $request->getKey(),
                after: [
                    'evidence_reference' => $evidenceReference,
                    /* Both parties recorded on the release event, so an audit
                     * reader can see the separation held without joining back
                     * to the request row. */
                    'reviewed_by_user_id' => $request->reviewed_by_user_id,
                    'released_by_user_id' => $actor->getKey(),
                ],
            );

            return $this->moveTo($request, PrivacyRequestStatus::Responded, $actor);
        });
    }

    /**
     * Refuse the request. A lawful outcome, and it must be explicable.
     */
    public function refuse(PrivacyRequest $request, User $actor, string $reason): PrivacyRequest
    {
        $this->assertWritable();

        /* Same reasoning as the release guard: `moveTo()` returns early on a
         * same-state move, so without this a second refusal would replace the
         * recorded reason for the first one. */
        if ($request->decision !== null) {
            throw new RuntimeException(
                'This request has already been answered. The decision and its reason are part of the '
                .'record and are not overwritten. Raise a new request if something further is needed.'
            );
        }

        if (trim($reason) === '') {
            throw new RuntimeException(
                'A refusal must record why. Refusing is lawful; refusing without a stated reason is not '
                .'defensible to the person or to a regulator.'
            );
        }

        $this->assertCanMoveTo($request->status, PrivacyRequestStatus::Refused);

        return $this->atomically($request, function () use ($request, $actor, $reason): PrivacyRequest {
            $request->forceFill([
                'decision' => 'refused',
                'decision_reason' => $reason,
                'updated_by_user_id' => $actor->getKey(),
            ])->save();

            $this->audit->record(
                action: 'governance.privacy_request.refused',
                module: 'Governance',
                resourceType: 'privacy_request',
                resourceId: $request->getKey(),
                reason: $reason,
            );

            return $this->moveTo($request, PrivacyRequestStatus::Refused, $actor);
        });
    }

    public function close(PrivacyRequest $request, User $actor): PrivacyRequest
    {
        $this->assertWritable();

        /* Closing twice would move `closed_at` to the second click. The date a
         * request was finished is quoted to the person and to a regulator. */
        if ($request->closed_at !== null || $request->status === PrivacyRequestStatus::Closed) {
            throw new RuntimeException(
                'This request is already closed. The date it was closed is part of the record and is not '
                .'moved. Reopen by raising a new request.'
            );
        }

        $this->assertCanMoveTo($request->status, PrivacyRequestStatus::Closed);

        return $this->atomically($request, function () use ($request, $actor): PrivacyRequest {
            $request->forceFill([
                'closed_at' => now()->utc(),
                'updated_by_user_id' => $actor->getKey(),
            ])->save();

            $this->audit->record(
                action: 'governance.privacy_request.closed',
                module: 'Governance',
                resourceType: 'privacy_request',
                resourceId: $request->getKey(),
            );

            return $this->moveTo($request, PrivacyRequestStatus::Closed, $actor);
        });
    }

    /**
     * Run one complete lifecycle operation as a single unit.
     *
     * Everything inside the closure lands together or not at all: business
     * fields, assembled records, the audit event and the status change. The
     * audit trail is written through Eloquent on the default connection, so a
     * rollback takes the event with it - which is the behaviour wanted here.
     * `AuditLogger` swallowing its own write failure is a separate guarantee
     * about not blocking an administrator, and it is unaffected.
     *
     * ON FAILURE THE IN-MEMORY MODEL IS RELOADED. The database has already
     * rolled back, but `$request` still carries the attributes the closure set
     * on it, and a caller that renders the object it passed in would otherwise
     * display fields that no longer exist in the row. Reloading makes the
     * object agree with the database before the exception reaches the caller.
     */
    private function atomically(PrivacyRequest $request, callable $operation): PrivacyRequest
    {
        try {
            return DB::transaction($operation);
        } catch (Throwable $exception) {
            if ($request->exists) {
                $request->refresh();
            }

            throw $exception;
        }
    }

    /**
     * Whether a move is legal. Reads the transition table and writes nothing.
     *
     * Called before any side effect so that an illegal operation is refused
     * while there is still nothing to undo. `moveTo()` calls it again at the
     * moment of the write, so the single definition of a legal move stays in
     * one place and a caller that forgets the pre-flight check is still
     * refused - later, but inside the transaction.
     *
     * The identity gate is NOT checked here. It depends on `identity_verified_at`,
     * which `verifyIdentity()` legitimately sets during the same operation, so
     * a check on the state before the write would refuse the one transition
     * that is supposed to open collection. It stays in `moveTo()`, where it is
     * evaluated against the row as it will actually be committed.
     */
    private function assertCanMoveTo(PrivacyRequestStatus $from, PrivacyRequestStatus $next): void
    {
        if ($from === $next) {
            return;
        }

        if (! $from->permits($next)) {
            throw new RuntimeException(
                "A privacy request cannot move from `{$from->value}` to `{$next->value}`. "
                .'The legal moves are: '.($from->allowedNext() === []
                    ? 'none, because this request is finished'
                    : implode(', ', array_map(
                        fn (PrivacyRequestStatus $s): string => $s->value,
                        $from->allowedNext(),
                    ))).'.'
            );
        }
    }

    /**
     * The one place a status changes.
     */
    private function moveTo(PrivacyRequest $request, PrivacyRequestStatus $next, User $actor): PrivacyRequest
    {
        $from = $request->status;

        if ($from === $next) {
            return $request;
        }

        $this->assertCanMoveTo($from, $next);

        if ($next->permitsCollection() && ! $request->isIdentityVerified()) {
            throw new RuntimeException(
                'A request cannot enter collection before its subject has been identified.'
            );
        }

        $request->forceFill([
            'status' => $next,
            'updated_by_user_id' => $actor->getKey(),
        ])->save();

        return $request;
    }

    private function assertWritable(): void
    {
        if (! $this->storage->privacyRequestsAreReady()) {
            throw GovernanceStorageNotInitialised::forWrite('The privacy request');
        }
    }

    /**
     * @return array<string, string>
     */
    public function verificationMethods(): array
    {
        return Config::get('governance.identity_verification_methods', []);
    }

    private function responseDueDays(): int
    {
        return (int) Config::get('governance.privacy_request.response_due_days', 30);
    }

    /**
     * The next reference for this organisation.
     *
     * Derived from the highest existing reference rather than from a count, so
     * that a deleted row cannot cause a reference to be reused. References are
     * quoted in correspondence and must never point at two different requests.
     */
    private function nextReference(): string
    {
        $last = PrivacyRequest::query()
            ->orderByDesc('id')
            ->value('reference');

        $next = $last === null ? 1 : ((int) substr((string) $last, 3)) + 1;

        return 'PR-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    /**
     * What the audit trail records about a request.
     *
     * NEVER the assembled data, never the detail payload, and never another
     * person's identity. Reference, state and reason only. SEC-DEC-044 was
     * applied to every key here.
     *
     * @return array<string, mixed>
     */
    private function summarise(PrivacyRequest $request): array
    {
        return [
            'reference' => $request->reference,
            'request_type' => $request->request_type->value,
            'status' => $request->status->value,
            'subject_has_account' => $request->subjectHasAccount(),
            'received_at' => $request->received_at?->toIso8601String(),
        ];
    }
}
