<?php

declare(strict_types=1);

namespace Tests\Feature\Privacy;

use App\Enums\Role;
use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Governance\Enums\PrivacyRequestStatus;
use App\Modules\Governance\Models\PrivacyRequest;
use App\Modules\Governance\Models\PrivacyRequestRecord;
use App\Modules\Governance\Services\PrivacyRequests;
use App\Modules\Identity\Support\OrganisationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Throwable;

/**
 * A lifecycle operation is all-or-nothing.
 *
 * THE DEFECT THIS EXISTS FOR. Every lifecycle method wrote its business fields
 * and its audit event FIRST and validated the state transition LAST. When the
 * transition turned out to be illegal, the exception left the earlier writes
 * behind - so a request could carry `released_at`, `released_by_user_id`,
 * `decision = released`, an `evidence_reference` and a
 * `governance.privacy_request.released` audit event while its status still said
 * `closed`.
 *
 * That is worse than either outcome on its own. The status says the response
 * was never released; the evidence says it was; and both are in the same row.
 * A regulator reading the audit trail and a reviewer reading the screen would
 * reach opposite conclusions, and neither could tell which was true.
 *
 * Every test here drives a REAL illegal operation and then asserts that
 * nothing at all survived it - fields, records and audit events alike.
 */
class LifecycleAtomicityTest extends TestCase
{
    use RefreshDatabase;

    private function personOn(Role $role): User
    {
        $user = User::query()->create(['name' => 'Test Person', 'email' => uniqid().'@example.test']);

        $user->forceFill([
            'role' => $role,
            'organisation_id' => app(OrganisationContext::class)->require()->getKey(),
        ])->save();

        return $user->refresh();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function aRequest(array $overrides = []): array
    {
        return array_merge([
            'request_type' => 'access',
            'subject_name' => 'Dana Subject',
            'subject_email' => 'dana@example.test',
            'received_at' => now()->toDateString(),
        ], $overrides);
    }

    /**
     * A closed request that carries complete, valid review and assembly
     * evidence - exactly the shape that slips past `releaseBlockedBecause()`.
     */
    private function aClosedRequestWithEvidence(User $handler, User $releaser): PrivacyRequest
    {
        $service = app(PrivacyRequests::class);

        $request = $service->receive($this->aRequest(), $handler);
        $request = $service->verifyIdentity($request, $handler, 'in_person', 'Passport sighted.');
        $request = $service->assemble($request, $handler);
        $request = $service->markReviewed($request, $handler);
        $request = $service->release($request, $releaser, 'Handed over in person.');

        return $service->close($request, $releaser);
    }

    private function countEvents(string $action): int
    {
        return AuditEvent::query()->where('action', $action)->count();
    }

    /** Run an operation expected to be refused. */
    private function refused(callable $call): void
    {
        try {
            $call();
            $this->fail('the operation was expected to be refused and was not');
        } catch (Throwable) {
            // The refusal is the point.
        }
    }

    /* --------------------------------------------------------- release */

    #[Test]
    public function an_illegal_release_leaves_no_release_evidence_behind(): void
    {
        $handler = $this->personOn(Role::SystemAdmin);
        $releaser = $this->personOn(Role::SystemAdmin);
        $third = $this->personOn(Role::SystemAdmin);

        $request = $this->aClosedRequestWithEvidence($handler, $releaser);
        $this->assertSame(PrivacyRequestStatus::Closed, $request->status);

        $before = [
            'decision' => $request->decision,
            'released_at' => $request->released_at?->toIso8601String(),
            'released_by_user_id' => $request->released_by_user_id,
            'evidence_reference' => $request->evidence_reference,
        ];
        $eventsBefore = $this->countEvents('governance.privacy_request.released');

        /* A THIRD person releases an already-closed request. They did not
         * assemble it and did not review it, so the separation-of-duties check
         * passes and only the state machine can refuse. */
        $this->refused(fn () => app(PrivacyRequests::class)->release($request, $third, 'Posted again.'));

        $fresh = PrivacyRequest::query()->findOrFail($request->getKey());

        $this->assertSame(PrivacyRequestStatus::Closed, $fresh->status);
        $this->assertSame($before['decision'], $fresh->decision);
        $this->assertSame($before['released_at'], $fresh->released_at?->toIso8601String());
        $this->assertSame($before['released_by_user_id'], $fresh->released_by_user_id);
        $this->assertSame($before['evidence_reference'], $fresh->evidence_reference);

        $this->assertSame(
            $eventsBefore,
            $this->countEvents('governance.privacy_request.released'),
            'an audit event was written for a release that the state machine refused',
        );
    }

    /* ---------------------------------------------------------- refuse */

    #[Test]
    public function an_illegal_refusal_leaves_no_refusal_evidence_behind(): void
    {
        $handler = $this->personOn(Role::SystemAdmin);
        $releaser = $this->personOn(Role::SystemAdmin);

        $request = $this->aClosedRequestWithEvidence($handler, $releaser);
        $eventsBefore = $this->countEvents('governance.privacy_request.refused');
        $decisionBefore = $request->decision;

        $this->refused(fn () => app(PrivacyRequests::class)
            ->refuse($request, $releaser, 'Changed my mind after closing.'));

        $fresh = PrivacyRequest::query()->findOrFail($request->getKey());

        $this->assertSame(PrivacyRequestStatus::Closed, $fresh->status);
        $this->assertSame($decisionBefore, $fresh->decision);
        $this->assertNull($fresh->decision_reason);
        $this->assertSame($eventsBefore, $this->countEvents('governance.privacy_request.refused'));
    }

    /* ----------------------------------------------------------- close */

    #[Test]
    public function an_illegal_close_leaves_no_closure_evidence_behind(): void
    {
        $actor = $this->personOn(Role::SystemAdmin);
        $service = app(PrivacyRequests::class);

        /* Received, not yet anywhere near closable. */
        $request = $service->receive($this->aRequest(), $actor);
        $eventsBefore = $this->countEvents('governance.privacy_request.closed');

        $this->refused(fn () => $service->close($request, $actor));

        $fresh = PrivacyRequest::query()->findOrFail($request->getKey());

        $this->assertNull($fresh->closed_at, 'closed_at was written for a close the state machine refused');
        $this->assertNotSame(PrivacyRequestStatus::Closed, $fresh->status);
        $this->assertSame($eventsBefore, $this->countEvents('governance.privacy_request.closed'));
    }

    /* ---------------------------------------------------------- review */

    #[Test]
    public function an_illegal_review_leaves_no_review_evidence_behind(): void
    {
        $handler = $this->personOn(Role::SystemAdmin);
        $releaser = $this->personOn(Role::SystemAdmin);

        $request = $this->aClosedRequestWithEvidence($handler, $releaser);

        $reviewedAtBefore = $request->reviewed_at?->toIso8601String();
        $reviewerBefore = $request->reviewed_by_user_id;
        $eventsBefore = $this->countEvents('governance.privacy_request.reviewed');

        $this->refused(fn () => app(PrivacyRequests::class)->markReviewed($request, $releaser));

        $fresh = PrivacyRequest::query()->findOrFail($request->getKey());

        $this->assertSame($reviewedAtBefore, $fresh->reviewed_at?->toIso8601String());
        $this->assertSame($reviewerBefore, $fresh->reviewed_by_user_id);
        $this->assertSame($eventsBefore, $this->countEvents('governance.privacy_request.reviewed'));
    }

    /* -------------------------------------------------------- assemble */

    #[Test]
    public function an_illegal_assembly_leaves_no_records_and_no_timestamp(): void
    {
        $handler = $this->personOn(Role::SystemAdmin);
        $releaser = $this->personOn(Role::SystemAdmin);

        $request = $this->aClosedRequestWithEvidence($handler, $releaser);

        $recordsBefore = PrivacyRequestRecord::query()
            ->where('privacy_request_id', $request->getKey())->count();
        $assembledAtBefore = $request->assembled_at?->toIso8601String();
        $eventsBefore = $this->countEvents('governance.privacy_request.assembled');

        $this->refused(fn () => app(PrivacyRequests::class)->assemble($request, $handler));

        $fresh = PrivacyRequest::query()->findOrFail($request->getKey());

        $this->assertSame(
            $recordsBefore,
            PrivacyRequestRecord::query()->where('privacy_request_id', $request->getKey())->count(),
            'assembled records survived an assembly the state machine refused',
        );
        $this->assertSame($assembledAtBefore, $fresh->assembled_at?->toIso8601String());
        $this->assertSame(PrivacyRequestStatus::Closed, $fresh->status);
        $this->assertSame($eventsBefore, $this->countEvents('governance.privacy_request.assembled'));
    }

    /* -------------------------------------------------- identity verify */

    #[Test]
    public function an_illegal_verification_leaves_no_verification_evidence(): void
    {
        $handler = $this->personOn(Role::SystemAdmin);
        $releaser = $this->personOn(Role::SystemAdmin);

        $request = $this->aClosedRequestWithEvidence($handler, $releaser);

        $verifiedAtBefore = $request->identity_verified_at?->toIso8601String();
        $dueBefore = $request->due_at?->toIso8601String();
        $eventsBefore = $this->countEvents('governance.privacy_request.identity_verified');

        $this->refused(fn () => app(PrivacyRequests::class)
            ->verifyIdentity($request, $releaser, 'in_person', 'Second look at the passport.'));

        $fresh = PrivacyRequest::query()->findOrFail($request->getKey());

        $this->assertSame($verifiedAtBefore, $fresh->identity_verified_at?->toIso8601String());
        $this->assertSame($dueBefore, $fresh->due_at?->toIso8601String());
        $this->assertSame(
            $eventsBefore,
            $this->countEvents('governance.privacy_request.identity_verified'),
        );
    }

    /* ------------------------------------------------------ typed route */

    #[Test]
    public function a_typed_post_against_a_closed_request_leaves_nothing_behind(): void
    {
        $handler = $this->personOn(Role::SystemAdmin);
        $releaser = $this->personOn(Role::SystemAdmin);
        $third = $this->personOn(Role::SystemAdmin);

        $request = $this->aClosedRequestWithEvidence($handler, $releaser);

        $evidenceBefore = $request->evidence_reference;
        $releasedByBefore = $request->released_by_user_id;
        $eventsBefore = $this->countEvents('governance.privacy_request.released');

        try {
            $this->actingAs($third)->post(
                '/admin/governance/privacy-requests/'.$request->getKey().'/release',
                ['evidence_reference' => 'Posted a second time by hand.'],
            );
        } catch (Throwable) {
            // However the framework surfaces it, nothing may have been written.
        }

        $fresh = PrivacyRequest::query()->findOrFail($request->getKey());

        $this->assertSame(PrivacyRequestStatus::Closed, $fresh->status);
        $this->assertSame($evidenceBefore, $fresh->evidence_reference);
        $this->assertSame($releasedByBefore, $fresh->released_by_user_id);
        $this->assertSame($eventsBefore, $this->countEvents('governance.privacy_request.released'));
    }
}
