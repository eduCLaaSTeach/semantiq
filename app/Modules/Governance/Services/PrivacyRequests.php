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
use RuntimeException;

/**
 * The lifecycle of a privacy request. Feature PDPA-01.
 *
 * EVERY TRANSITION GOES THROUGH `moveTo()`, which consults the enum. There is
 * no second path, so a state machine that lives in one place cannot drift from
 * a state machine that lives in a controller.
 *
 * THE IDENTITY GATE IS CHECKED TWICE, deliberately. `moveTo()` refuses an
 * illegal transition; `assemble()` independently refuses to collect for an
 * unverified subject. Two checks on the most dangerous operation in the gate is
 * proportionate: the failure mode is handing a stranger somebody else's
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
        if (! $this->storage->privacyRequestsAreReady()) {
            throw GovernanceStorageNotInitialised::forWrite('The privacy request');
        }

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
    }

    /**
     * Collect what is held about the subject.
     *
     * The assembler refuses an unverified subject independently of the state
     * machine. Both checks stay.
     */
    public function assemble(PrivacyRequest $request, User $actor): PrivacyRequest
    {
        $this->assertWritable();

        if (! $request->isIdentityVerified()) {
            throw new RuntimeException(
                'Nothing may be collected for a request whose subject has not been identified.'
            );
        }

        if ($request->status === PrivacyRequestStatus::InReview) {
            $request = $this->moveTo($request, PrivacyRequestStatus::Assembling, $actor);
        }

        $written = $this->assembler->assemble($request);

        $request->forceFill([
            'assembled_at' => now()->utc(),
            'updated_by_user_id' => $actor->getKey(),
        ])->save();

        $this->audit->record(
            action: 'governance.privacy_request.assembled',
            module: 'Governance',
            resourceType: 'privacy_request',
            resourceId: $request->getKey(),
            after: ['items' => $written],
        );

        return $this->moveTo($request, PrivacyRequestStatus::InReview, $actor);
    }

    /**
     * Record that a reviewer has been through the assembled response.
     */
    public function markReviewed(PrivacyRequest $request, User $actor): PrivacyRequest
    {
        $this->assertWritable();

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

        $next = $request->request_type->needsDecision()
            ? PrivacyRequestStatus::AwaitingDecision
            : PrivacyRequestStatus::InReview;

        return $next === $request->status ? $request : $this->moveTo($request, $next, $actor);
    }

    /**
     * Authorise disclosure. A different act from assembling one, which is why
     * it sits behind its own permission at System Administrator.
     */
    public function release(PrivacyRequest $request, User $actor, string $evidenceReference): PrivacyRequest
    {
        $this->assertWritable();

        if (trim($evidenceReference) === '') {
            throw new RuntimeException(
                'Record how the response was delivered. SemantIQ sends nothing itself, so without this '
                .'there is no evidence the person ever received an answer.'
            );
        }

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
            after: ['evidence_reference' => $evidenceReference],
        );

        return $this->moveTo($request, PrivacyRequestStatus::Responded, $actor);
    }

    /**
     * Refuse the request. A lawful outcome, and it must be explicable.
     */
    public function refuse(PrivacyRequest $request, User $actor, string $reason): PrivacyRequest
    {
        $this->assertWritable();

        if (trim($reason) === '') {
            throw new RuntimeException(
                'A refusal must record why. Refusing is lawful; refusing without a stated reason is not '
                .'defensible to the person or to a regulator.'
            );
        }

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
    }

    public function close(PrivacyRequest $request, User $actor): PrivacyRequest
    {
        $this->assertWritable();

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

        if (! $from->permits($next)) {
            throw new RuntimeException(
                "A privacy request cannot move from `{$from->value}` to `{$next->value}`. "
                .'The legal moves are: '.implode(', ', array_map(
                    fn (PrivacyRequestStatus $s): string => $s->value,
                    $from->allowedNext(),
                )).'.'
            );
        }

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
