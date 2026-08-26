<?php

declare(strict_types=1);

namespace Tests\Feature\Privacy;

use App\Enums\Role;
use App\Models\User;
use App\Modules\Audit\Exceptions\RequiredAuditEvidenceMissing;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Audit\Support\AuditLogger;
use App\Modules\Governance\Enums\CorrectionOutcome;
use App\Modules\Governance\Enums\PrivacyRequestStatus;
use App\Modules\Governance\Models\PrivacyCorrectionNote;
use App\Modules\Governance\Models\PrivacyRequest;
use App\Modules\Governance\Models\PrivacyRequestRecord;
use App\Modules\Governance\Services\CorrectionNotes;
use App\Modules\Governance\Services\PrivacyRequests;
use App\Modules\Identity\Support\OrganisationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;
use Throwable;

/**
 * A PDPA lifecycle action does not survive a failed audit write.
 *
 * THE DEFECT THIS EXISTS FOR. `PrivacyRequests` wrapped its lifecycle
 * operations in a transaction, which made this look settled. It was not.
 * `AuditLogger::record()` catches its own write exception, logs it and returns
 * `null` - deliberately, so that a full disk cannot stop an administrator
 * disabling a compromised account. Nothing propagated, so the transaction
 * committed happily and the shape was:
 *
 *     business fields written
 *     audit INSERT fails, is caught, returns null
 *     status moved
 *     COMMIT
 *
 * leaving a request that says a disclosure happened with no evidence that it
 * did. The row asserts it; the trail denies it; nobody can say which is true.
 * For a table that exists to be evidence, that is the worst available outcome.
 *
 * HOW THE FAILURE IS FORCED HERE. Not with a fake logger. A listener on
 * `AuditEvent`'s `creating` event throws, which is caught by the REAL
 * `AuditLogger` catch block, which returns the REAL null. Every line of the
 * production path runs, including the one the fix depends on. A test double
 * returning null would prove only that the test double works.
 *
 * WHAT EACH TEST ASSERTS. The database, re-read after the refusal - never that
 * an exception was thrown. That distinction is the whole reason this batch
 * needed three review rounds: the previous suite proved exceptions and missed
 * two release blockers sitting in the rows behind them.
 */
class AuditEvidenceRollbackTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Make every audit write fail the way a sick database would.
     *
     * The listener is registered on this test's application instance, so it
     * does not leak into the next test.
     */
    private function auditWritesFail(): void
    {
        Event::listen('eloquent.creating: '.AuditEvent::class, function (): void {
            throw new RuntimeException('audit storage is unavailable');
        });
    }

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
     * @return array<string, mixed>
     */
    private function aRequest(): array
    {
        return [
            'request_type' => 'access',
            'subject_name' => 'Dana Subject',
            'subject_email' => 'dana@example.test',
            'received_at' => now()->toDateString(),
        ];
    }

    /**
     * The operation must be refused, and refused for the right reason.
     *
     * Asserting the exception TYPE matters here: an operation that failed
     * because of a typo in the test would otherwise look like a passing proof
     * of a rollback that never had to happen.
     */
    private function refusedForMissingEvidence(callable $call): void
    {
        try {
            $call();
            $this->fail('the operation was expected to be refused and was not');
        } catch (RequiredAuditEvidenceMissing $exception) {
            $this->assertStringContainsString('has been undone', $exception->getMessage());
        } catch (Throwable $other) {
            $this->fail(
                'expected RequiredAuditEvidenceMissing, got '
                .$other::class.': '.$other->getMessage()
            );
        }
    }

    /** A verified request in `assembling`, built while the audit trail works. */
    private function aVerifiedRequest(User $handler): PrivacyRequest
    {
        $service = app(PrivacyRequests::class);

        $request = $service->receive($this->aRequest(), $handler);

        return $service->verifyIdentity($request, $handler, 'in_person', 'Passport sighted.');
    }

    /* --------------------------------------------------------- receive */

    #[Test]
    public function a_failed_audit_on_receive_leaves_no_privacy_request_row(): void
    {
        $handler = $this->personOn(Role::SystemAdmin);

        $this->auditWritesFail();

        $this->refusedForMissingEvidence(
            fn () => app(PrivacyRequests::class)->receive($this->aRequest(), $handler)
        );

        $this->assertSame(
            0,
            PrivacyRequest::query()->count(),
            'a privacy request row survived a receive whose audit event was never written',
        );
        $this->assertSame(0, AuditEvent::query()->count());
    }

    /* ---------------------------------------------------------- verify */

    #[Test]
    public function a_failed_audit_on_verify_leaves_the_verification_fields_and_status_unchanged(): void
    {
        $handler = $this->personOn(Role::SystemAdmin);

        $service = app(PrivacyRequests::class);
        $request = $service->receive($this->aRequest(), $handler);

        $this->assertSame(PrivacyRequestStatus::IdentityVerification, $request->status);

        $this->auditWritesFail();

        $this->refusedForMissingEvidence(
            fn () => $service->verifyIdentity($request, $handler, 'in_person', 'Passport sighted.')
        );

        $fresh = PrivacyRequest::query()->findOrFail($request->getKey());

        $this->assertNull($fresh->identity_verified_at, 'the request was marked verified with no evidence');
        $this->assertNull($fresh->identity_verified_by_user_id);
        $this->assertNull($fresh->identity_verification_method);
        $this->assertNull($fresh->identity_verification_note);
        $this->assertNull($fresh->due_at, 'a response deadline was frozen against an unrecorded verification');
        $this->assertSame(PrivacyRequestStatus::IdentityVerification, $fresh->status);

        $this->assertSame(
            0,
            AuditEvent::query()->where('action', 'governance.privacy_request.identity_verified')->count(),
        );
    }

    /* -------------------------------------------------------- assemble */

    #[Test]
    public function a_failed_audit_on_assemble_leaves_no_records_no_timestamp_and_no_status_change(): void
    {
        $handler = $this->personOn(Role::SystemAdmin);
        $request = $this->aVerifiedRequest($handler);

        $this->assertSame(PrivacyRequestStatus::Assembling, $request->status);
        $this->assertSame(0, PrivacyRequestRecord::query()->count());

        $this->auditWritesFail();

        $this->refusedForMissingEvidence(fn () => app(PrivacyRequests::class)->assemble($request, $handler));

        $fresh = PrivacyRequest::query()->findOrFail($request->getKey());

        /* The collection genuinely ran and genuinely wrote rows before the
         * audit write failed. This is the assertion that proves the rollback
         * rather than the ordering. */
        $this->assertSame(
            0,
            PrivacyRequestRecord::query()->count(),
            'assembled personal data survived an assembly that was never recorded',
        );
        $this->assertNull($fresh->assembled_at);
        $this->assertNull($fresh->assembled_by_user_id);
        $this->assertSame(PrivacyRequestStatus::Assembling, $fresh->status);
        $this->assertSame(
            0,
            AuditEvent::query()->where('action', 'governance.privacy_request.assembled')->count(),
        );
    }

    /* ---------------------------------------------------------- review */

    #[Test]
    public function a_failed_audit_on_review_leaves_the_review_fields_unchanged(): void
    {
        $handler = $this->personOn(Role::SystemAdmin);

        $service = app(PrivacyRequests::class);
        $request = $service->assemble($this->aVerifiedRequest($handler), $handler);

        $this->auditWritesFail();

        $this->refusedForMissingEvidence(fn () => $service->markReviewed($request, $handler));

        $fresh = PrivacyRequest::query()->findOrFail($request->getKey());

        $this->assertNull($fresh->reviewed_at, 'a review was recorded on the row but not in the trail');
        $this->assertNull($fresh->reviewed_by_user_id);
        $this->assertSame(PrivacyRequestStatus::InReview, $fresh->status);
        $this->assertSame(
            0,
            AuditEvent::query()->where('action', 'governance.privacy_request.reviewed')->count(),
        );
    }

    /* --------------------------------------------------------- release */

    #[Test]
    public function a_failed_audit_on_release_leaves_no_release_fields_and_no_status_change(): void
    {
        $handler = $this->personOn(Role::SystemAdmin);
        $releaser = $this->personOn(Role::SystemAdmin);

        $service = app(PrivacyRequests::class);
        $request = $service->assemble($this->aVerifiedRequest($handler), $handler);
        $request = $service->markReviewed($request, $handler);

        $this->auditWritesFail();

        $this->refusedForMissingEvidence(
            fn () => $service->release($request, $releaser, 'Handed over in person.')
        );

        $fresh = PrivacyRequest::query()->findOrFail($request->getKey());

        $this->assertNull($fresh->decision, 'a disclosure was recorded with no evidence that it happened');
        $this->assertNull($fresh->released_at);
        $this->assertNull($fresh->released_by_user_id);
        $this->assertNull($fresh->evidence_reference);
        $this->assertSame(PrivacyRequestStatus::InReview, $fresh->status);
        $this->assertSame(
            0,
            AuditEvent::query()->where('action', 'governance.privacy_request.released')->count(),
        );
    }

    /* ---------------------------------------------------- refuse, close */

    #[Test]
    public function a_failed_audit_on_refusal_leaves_no_decision(): void
    {
        $handler = $this->personOn(Role::SystemAdmin);
        $releaser = $this->personOn(Role::SystemAdmin);

        $service = app(PrivacyRequests::class);
        $request = $service->assemble($this->aVerifiedRequest($handler), $handler);
        $request = $service->markReviewed($request, $handler);

        $this->auditWritesFail();

        $this->refusedForMissingEvidence(
            fn () => $service->refuse($request, $releaser, 'Held under a legal exemption.')
        );

        $fresh = PrivacyRequest::query()->findOrFail($request->getKey());

        $this->assertNull($fresh->decision);
        $this->assertNull($fresh->decision_reason);
        $this->assertSame(PrivacyRequestStatus::InReview, $fresh->status);
        $this->assertSame(
            0,
            AuditEvent::query()->where('action', 'governance.privacy_request.refused')->count(),
        );
    }

    #[Test]
    public function a_failed_audit_on_close_does_not_close_the_request(): void
    {
        $handler = $this->personOn(Role::SystemAdmin);
        $releaser = $this->personOn(Role::SystemAdmin);

        $service = app(PrivacyRequests::class);
        $request = $service->assemble($this->aVerifiedRequest($handler), $handler);
        $request = $service->markReviewed($request, $handler);
        $request = $service->release($request, $releaser, 'Handed over in person.');

        $this->auditWritesFail();

        $this->refusedForMissingEvidence(fn () => $service->close($request, $releaser));

        $fresh = PrivacyRequest::query()->findOrFail($request->getKey());

        $this->assertNull($fresh->closed_at);
        $this->assertSame(PrivacyRequestStatus::Responded, $fresh->status);
        $this->assertSame(
            0,
            AuditEvent::query()->where('action', 'governance.privacy_request.closed')->count(),
        );
    }

    /* ------------------------------------------------- correction notes */

    #[Test]
    public function a_failed_audit_on_a_correction_note_leaves_no_note(): void
    {
        $handler = $this->personOn(Role::SystemAdmin);
        $request = $this->aVerifiedRequest($handler);

        $this->auditWritesFail();

        $this->refusedForMissingEvidence(fn () => app(CorrectionNotes::class)->record(
            $request,
            $handler,
            'The sign-in on 3 March was not me.',
            CorrectionOutcome::Noted,
            'Annotated beside the event; the trail itself cannot be edited.',
        ));

        /* This table can never be updated or deleted, so a note written
         * without its audit event would be permanently unexplainable. */
        $this->assertSame(
            0,
            PrivacyCorrectionNote::query()->count(),
            'a correction note that can never be edited survived without its audit event',
        );
    }

    /* ------------------------------------------------------ typed route */

    #[Test]
    public function a_typed_release_post_rolls_back_when_the_audit_write_fails(): void
    {
        $handler = $this->personOn(Role::SystemAdmin);
        $releaser = $this->personOn(Role::SystemAdmin);

        $service = app(PrivacyRequests::class);
        $request = $service->assemble($this->aVerifiedRequest($handler), $handler);
        $request = $service->markReviewed($request, $handler);

        $this->auditWritesFail();

        $response = $this->actingAs($releaser)
            ->from('/admin/governance/privacy-requests/'.$request->getKey())
            ->post(
                '/admin/governance/privacy-requests/'.$request->getKey().'/release',
                ['evidence_reference' => 'Sent by registered post.'],
            );

        /* The exception extends RuntimeException, so the controller's existing
         * handling returns the person to the screen with the reason rather
         * than showing them a stack trace. */
        $response->assertRedirect('/admin/governance/privacy-requests/'.$request->getKey());
        $response->assertSessionHasErrors('form');

        $fresh = PrivacyRequest::query()->findOrFail($request->getKey());

        $this->assertNull($fresh->released_at);
        $this->assertNull($fresh->released_by_user_id);
        $this->assertNull($fresh->evidence_reference);
        $this->assertNull($fresh->decision);
        $this->assertSame(PrivacyRequestStatus::InReview, $fresh->status);
        $this->assertSame(
            0,
            AuditEvent::query()->where('action', 'governance.privacy_request.released')->count(),
        );
    }

    /* ------------------------- the ordinary path is deliberately untouched */

    #[Test]
    public function an_ordinary_administrative_action_still_succeeds_when_the_audit_write_fails(): void
    {
        $actor = $this->personOn(Role::SystemAdmin);
        $subject = $this->personOn(Role::Viewer);

        $this->auditWritesFail();

        /* Gate 1 decided that a sick audit trail must not stop an
         * administrator acting. `recordRequired()` is opt-in, and this proves
         * the fix did not quietly turn every `record()` call fail-closed. */
        app(AuditLogger::class)->record(
            action: 'user.disabled',
            module: 'Identity',
            resourceType: 'user',
            resourceId: $subject->getKey(),
        );

        $this->assertSame(0, AuditEvent::query()->count());
        $this->assertTrue(true, 'record() returned without throwing, which is the gate 1 behaviour');
    }
}
