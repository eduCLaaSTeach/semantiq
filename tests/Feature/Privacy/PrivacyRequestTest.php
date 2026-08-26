<?php

declare(strict_types=1);

namespace Tests\Feature\Privacy;

use App\Enums\Role;
use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Audit\Support\AuditLogger;
use App\Modules\Governance\Enums\CorrectionOutcome;
use App\Modules\Governance\Enums\DisclosureBand;
use App\Modules\Governance\Enums\DisclosureTreatment;
use App\Modules\Governance\Enums\PrivacyRequestStatus;
use App\Modules\Governance\Http\Controllers\PrivacyRequestController;
use App\Modules\Governance\Models\PrivacyCorrectionNote;
use App\Modules\Governance\Models\PrivacyRequest;
use App\Modules\Governance\Models\PrivacyRequestRecord;
use App\Modules\Governance\Services\CorrectionNotes;
use App\Modules\Governance\Services\PrivacyRequests;
use App\Modules\Identity\Support\OrganisationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * PDPA-01 Privacy Requests.
 *
 * The tests that matter most here are the negative ones: that nothing is
 * collected before identity is verified, and that another person's identity
 * never reaches the assembled response.
 */
class PrivacyRequestTest extends TestCase
{
    use RefreshDatabase;

    private function personOn(Role $role, string $name = 'Test Person'): User
    {
        $user = User::query()->create(['name' => $name, 'email' => uniqid().'@example.test']);

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
            'received_channel' => 'email',
        ], $overrides);
    }

    /* ---------------------------------------------------------------- gate */

    #[Test]
    public function nothing_is_collected_before_identity_is_verified(): void
    {
        $actor = $this->personOn(Role::Admin);
        $service = app(PrivacyRequests::class);

        $request = $service->receive($this->aRequest(), $actor);

        $this->assertFalse($request->isIdentityVerified());
        $this->assertSame(PrivacyRequestStatus::IdentityVerification, $request->status);

        $this->expectException(RuntimeException::class);
        $service->assemble($request, $actor);
    }

    #[Test]
    public function no_records_exist_for_an_unverified_request(): void
    {
        $actor = $this->personOn(Role::Admin);
        $request = app(PrivacyRequests::class)->receive($this->aRequest(), $actor);

        try {
            app(PrivacyRequests::class)->assemble($request, $actor);
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame(0, PrivacyRequestRecord::query()->count());
    }

    #[Test]
    public function verification_records_what_was_actually_checked(): void
    {
        $actor = $this->personOn(Role::Admin);
        $service = app(PrivacyRequests::class);

        $request = $service->receive($this->aRequest(), $actor);

        $this->expectException(RuntimeException::class);
        $service->verifyIdentity($request, $actor, 'in_person', '   ');
    }

    #[Test]
    public function an_unknown_verification_method_is_refused(): void
    {
        $actor = $this->personOn(Role::Admin);
        $service = app(PrivacyRequests::class);

        $request = $service->receive($this->aRequest(), $actor);

        $this->expectException(RuntimeException::class);
        $service->verifyIdentity($request, $actor, 'i_recognised_their_voice', 'Sounded like them.');
    }

    #[Test]
    public function the_deadline_is_frozen_at_verification_and_never_recomputed(): void
    {
        $actor = $this->personOn(Role::Admin);
        $service = app(PrivacyRequests::class);

        $request = $service->receive($this->aRequest(), $actor);
        $request = $service->verifyIdentity($request, $actor, 'in_person', 'Passport sighted.');

        $frozen = $request->due_at;
        $this->assertNotNull($frozen);

        /* Change the configured window. An existing deadline must not move. */
        config(['governance.privacy_request.response_due_days' => 5]);

        $this->assertTrue($frozen->equalTo($request->refresh()->due_at));
    }

    /* ------------------------------------------------------- state machine */

    #[Test]
    public function a_request_cannot_skip_from_received_to_released(): void
    {
        $actor = $this->personOn(Role::SystemAdmin);
        $service = app(PrivacyRequests::class);

        $request = $service->receive($this->aRequest(), $actor);

        $this->expectException(RuntimeException::class);
        $service->release($request, $actor, 'Handed over in person.');
    }

    #[Test]
    public function a_closed_request_cannot_be_reopened(): void
    {
        $actor = $this->personOn(Role::SystemAdmin);
        $service = app(PrivacyRequests::class);

        $other = $this->personOn(Role::SystemAdmin);
        $request = $this->throughToRelease($service, $actor, $other);
        $request = $service->close($request, $other);

        $this->assertSame(PrivacyRequestStatus::Closed, $request->status);
        $this->assertSame([], $request->status->allowedNext());
    }

    #[Test]
    public function a_refusal_must_record_why(): void
    {
        $actor = $this->personOn(Role::SystemAdmin);
        $service = app(PrivacyRequests::class);

        $request = $service->receive($this->aRequest(), $actor);
        $request = $service->verifyIdentity($request, $actor, 'in_person', 'Passport sighted.');

        $this->expectException(RuntimeException::class);
        $service->refuse($request, $actor, '  ');
    }

    #[Test]
    public function releasing_requires_evidence_of_how_it_was_delivered(): void
    {
        $actor = $this->personOn(Role::SystemAdmin);
        $service = app(PrivacyRequests::class);

        $request = $service->receive($this->aRequest(), $actor);
        $request = $service->verifyIdentity($request, $actor, 'in_person', 'Passport sighted.');
        $request = $service->assemble($request, $actor);

        $this->expectException(RuntimeException::class);
        $service->release($request, $actor, '');
    }

    /* ---------------------------------------------------- other people */

    #[Test]
    public function another_persons_identity_never_appears_in_an_assembled_response(): void
    {
        $actor = $this->personOn(Role::SystemAdmin, 'Alice Actor');
        $other = $this->personOn(Role::Viewer, 'Bob Bystander');

        /* Alice acts on Bob's record, which is exactly the band C shape. */
        app(AuditLogger::class)->record(
            action: 'admin.user.updated',
            module: 'Admin',
            resourceType: 'user',
            resourceId: $other->getKey(),
        );

        $service = app(PrivacyRequests::class);

        $request = $service->receive($this->aRequest([
            'subject_name' => 'Alice Actor',
            'subject_email' => $actor->email,
            'subject_user_id' => $actor->getKey(),
        ]), $actor);

        $request = $service->verifyIdentity($request, $actor, 'signed_in_session', 'Asked while signed in.');
        $request = $service->assemble($request, $actor);

        $rendered = PrivacyRequestRecord::query()
            ->where('privacy_request_id', $request->getKey())
            ->get()
            ->map(fn (PrivacyRequestRecord $r): string => $r->summary.' '.json_encode($r->detail))
            ->implode(' ');

        $this->assertStringNotContainsString('Bob Bystander', $rendered);
        $this->assertStringNotContainsString($other->email, $rendered);
        $this->assertStringNotContainsString('"'.$other->getKey().'"', $rendered);
    }

    #[Test]
    public function band_c_items_are_described_and_carry_no_payload(): void
    {
        $actor = $this->personOn(Role::SystemAdmin);
        $service = app(PrivacyRequests::class);

        $request = $service->receive($this->aRequest([
            'subject_user_id' => $actor->getKey(),
            'subject_email' => $actor->email,
        ]), $actor);
        $request = $service->verifyIdentity($request, $actor, 'signed_in_session', 'Signed in.');
        $request = $service->assemble($request, $actor);

        $bandC = PrivacyRequestRecord::query()
            ->where('privacy_request_id', $request->getKey())
            ->inBand(DisclosureBand::C)
            ->get();

        $this->assertTrue($bandC->isNotEmpty(), 'expected band C items');

        foreach ($bandC as $record) {
            $this->assertNotSame(
                DisclosureTreatment::Include,
                $record->treatment,
                "band C item from `{$record->source_table}` is disclosed in full",
            );
            $this->assertNull($record->detail, "band C item from `{$record->source_table}` carries a payload");
        }
    }

    #[Test]
    public function a_secret_reference_is_reported_as_a_count_and_never_as_a_pointer(): void
    {
        $actor = $this->personOn(Role::SystemAdmin);
        $service = app(PrivacyRequests::class);

        $request = $service->receive($this->aRequest([
            'subject_user_id' => $actor->getKey(),
            'subject_email' => $actor->email,
        ]), $actor);
        $request = $service->verifyIdentity($request, $actor, 'signed_in_session', 'Signed in.');
        $request = $service->assemble($request, $actor);

        $item = PrivacyRequestRecord::query()
            ->where('privacy_request_id', $request->getKey())
            ->where('source_table', 'secret_references')
            ->first();

        $this->assertNotNull($item);
        $this->assertSame(DisclosureTreatment::Describe, $item->treatment);
        $this->assertNull($item->detail);
    }

    /* --------------------------------------------------- correction notes */

    #[Test]
    public function a_correction_note_cannot_be_updated(): void
    {
        $note = $this->aNote();

        $this->expectException(RuntimeException::class);
        $note->update(['subject_assertion' => 'Something else entirely.']);
    }

    #[Test]
    public function a_correction_note_cannot_be_deleted(): void
    {
        $note = $this->aNote();

        $this->expectException(RuntimeException::class);
        $note->delete();
    }

    #[Test]
    public function a_correction_note_requires_a_reason_on_every_outcome(): void
    {
        $actor = $this->personOn(Role::Admin);
        $service = app(PrivacyRequests::class);
        $request = $service->receive($this->aRequest(), $actor);

        $this->expectException(RuntimeException::class);
        app(CorrectionNotes::class)->record(
            $request,
            $actor,
            'The trail says I approved this and I did not.',
            CorrectionOutcome::Applied,
            '   ',
        );
    }

    #[Test]
    public function noting_a_dispute_never_edits_the_audit_entry(): void
    {
        $actor = $this->personOn(Role::Admin);

        $event = app(AuditLogger::class)->record(
            action: 'admin.user.updated',
            module: 'Admin',
            resourceType: 'user',
            resourceId: $actor->getKey(),
        );

        $this->assertNotNull($event);
        $before = AuditEvent::query()->find($event->getKey())->toArray();

        $service = app(PrivacyRequests::class);
        $request = $service->receive($this->aRequest(['request_type' => 'correction']), $actor);

        app(CorrectionNotes::class)->record(
            $request,
            $actor,
            'This entry is wrong about me.',
            CorrectionOutcome::Noted,
            'The trail cannot be edited, so the dispute is recorded beside it.',
            $event->getKey(),
        );

        $this->assertSame($before, AuditEvent::query()->find($event->getKey())->toArray());
    }

    /* ----------------------------------------------- nothing leaves disk */

    #[Test]
    public function no_deletion_or_export_path_exists(): void
    {
        $methods = array_map(
            static fn (\ReflectionMethod $m): string => strtolower($m->getName()),
            (new \ReflectionClass(PrivacyRequestController::class))
                ->getMethods(\ReflectionMethod::IS_PUBLIC),
        );

        foreach (['destroy', 'delete', 'remove', 'export', 'download', 'pdf'] as $forbidden) {
            $this->assertNotContains($forbidden, $methods, "controller exposes `{$forbidden}`");
        }

        $routes = collect(app('router')->getRoutes())
            ->filter(fn ($route): bool => str_contains((string) $route->uri(), 'privacy-requests'));

        $this->assertTrue($routes->isNotEmpty(), 'expected privacy request routes');

        foreach ($routes as $route) {
            $this->assertNotContains('DELETE', $route->methods(), 'a DELETE route exists: '.$route->uri());
            $this->assertStringNotContainsString('export', (string) $route->uri());
            $this->assertStringNotContainsString('download', (string) $route->uri());
        }
    }

    /**
     * Identical findings are shown once, with the record types beside them.
     *
     * FOUND IN A BROWSER, NOT BY A TEST, and the tests were all green when it
     * was there. A subject with no account produces the same sentence from
     * every band C collector - twenty-odd rows of "no account is linked" - and
     * the handful of rows that say something real were lost among them. A
     * reviewer skimming that list would miss the findings, which defeats the
     * point of having a reviewer look.
     *
     * SEC-DEC-061's lesson in a new shape: this asserts against the RENDERED
     * page, because the service was behaving correctly the whole time.
     */
    #[Test]
    public function identical_findings_are_not_repeated_on_the_screen(): void
    {
        $actor = $this->personOn(Role::SystemAdmin);
        $service = app(PrivacyRequests::class);

        /* No subject_user_id: the case that produces the repetition. */
        $request = $service->receive($this->aRequest(), $actor);
        $request = $service->verifyIdentity($request, $actor, 'in_person', 'Passport sighted.');
        $request = $service->assemble($request, $actor);

        $stored = PrivacyRequestRecord::query()
            ->where('privacy_request_id', $request->getKey())
            ->where('band', DisclosureBand::C->value)
            ->count();

        $this->assertGreaterThan(5, $stored, 'expected the repetitive band C case');

        $html = $this->actingAs($actor)
            ->get('/admin/governance/privacy-requests/'.$request->getKey())
            ->getContent();

        $repeated = substr_count($html, 'no administrative action can be attributed to it');

        $this->assertLessThan(
            $stored,
            $repeated,
            'the same finding is rendered once per record type. It should appear once, with the record '
            .'types listed beside it, or the rows that matter are buried.',
        );

        $this->assertStringContainsString('The same for all', $html);
    }

    private function aNote(): PrivacyCorrectionNote
    {
        $actor = $this->personOn(Role::Admin);
        $request = app(PrivacyRequests::class)->receive($this->aRequest(), $actor);

        return app(CorrectionNotes::class)->record(
            $request,
            $actor,
            'The trail says I approved this and I did not.',
            CorrectionOutcome::Noted,
            'Recorded beside the entry, which cannot be edited.',
        );
    }

    /**
     * Carry a request all the way to released, USING TWO DISTINCT PEOPLE.
     *
     * The earlier version drove the whole lifecycle with one System
     * Administrator and never called `markReviewed()`. That is exactly how the
     * separation-of-duties defect stayed invisible: the helper proved the happy
     * path was reachable by one person, and every test that used it agreed. **A
     * test helper that takes the forbidden shortcut hides the control it is
     * meant to exercise.**
     */
    private function throughToRelease(PrivacyRequests $service, User $handler, User $releaser): PrivacyRequest
    {
        $request = $service->receive($this->aRequest(), $handler);
        $request = $service->verifyIdentity($request, $handler, 'in_person', 'Passport sighted.');
        $request = $service->assemble($request, $handler);
        $request = $service->markReviewed($request, $handler);

        return $service->release($request, $releaser, 'Handed over in person on 26 August.');
    }

    /* ------------------------------------------------ separation of duties */

    #[Test]
    public function a_response_cannot_be_released_before_it_is_reviewed(): void
    {
        $handler = $this->personOn(Role::SystemAdmin);
        $releaser = $this->personOn(Role::SystemAdmin);
        $service = app(PrivacyRequests::class);

        $request = $service->receive($this->aRequest(), $handler);
        $request = $service->verifyIdentity($request, $handler, 'in_person', 'Passport sighted.');
        $request = $service->assemble($request, $handler);

        $this->assertNull($request->reviewed_at);

        $this->expectExceptionMessage('Nobody has reviewed the assembled response yet');
        $service->release($request, $releaser, 'Posted.');
    }

    #[Test]
    public function the_reviewer_cannot_also_release(): void
    {
        $person = $this->personOn(Role::SystemAdmin);
        $service = app(PrivacyRequests::class);

        $request = $service->receive($this->aRequest(), $person);
        $request = $service->verifyIdentity($request, $person, 'in_person', 'Passport sighted.');
        $request = $service->assemble($request, $person);
        $request = $service->markReviewed($request, $person);

        $this->assertSame($person->getKey(), $request->reviewed_by_user_id);

        $this->expectExceptionMessage('You reviewed this response');
        $service->release($request, $person, 'Posted.');
    }

    #[Test]
    public function the_assembler_cannot_release_even_when_somebody_else_reviewed(): void
    {
        $assembler = $this->personOn(Role::SystemAdmin);
        $reviewer = $this->personOn(Role::SystemAdmin);
        $service = app(PrivacyRequests::class);

        $request = $service->receive($this->aRequest(), $assembler);
        $request = $service->verifyIdentity($request, $assembler, 'in_person', 'Passport sighted.');
        $request = $service->assemble($request, $assembler);
        $request = $service->markReviewed($request, $reviewer);

        $this->expectExceptionMessage('You assembled this response');
        $service->release($request, $assembler, 'Posted.');
    }

    #[Test]
    public function a_different_reviewer_and_releaser_succeeds(): void
    {
        $handler = $this->personOn(Role::SystemAdmin);
        $releaser = $this->personOn(Role::SystemAdmin);

        $request = $this->throughToRelease(app(PrivacyRequests::class), $handler, $releaser);

        $this->assertSame(PrivacyRequestStatus::Responded, $request->status);
        $this->assertSame($releaser->getKey(), $request->released_by_user_id);
        $this->assertSame($handler->getKey(), $request->reviewed_by_user_id);
        $this->assertNotSame($request->reviewed_by_user_id, $request->released_by_user_id);
    }

    /**
     * The rule lives in the service, so a typed POST meets it too. The UI
     * hiding a button is convenience; this is the control.
     */
    #[Test]
    public function a_typed_post_cannot_bypass_the_separation_of_duties(): void
    {
        $person = $this->personOn(Role::SystemAdmin);
        $service = app(PrivacyRequests::class);

        $request = $service->receive($this->aRequest(), $person);
        $request = $service->verifyIdentity($request, $person, 'in_person', 'Passport sighted.');
        $request = $service->assemble($request, $person);
        $request = $service->markReviewed($request, $person);

        $this->withoutExceptionHandling__safely(function () use ($person, $request): void {
            $this->actingAs($person)->post(
                '/admin/governance/privacy-requests/'.$request->getKey().'/release',
                ['evidence_reference' => 'Posted by hand.'],
            );
        });

        $request->refresh();

        $this->assertNull(
            $request->released_at,
            'the response was released despite the reviewer being the releaser',
        );
        $this->assertNotSame(PrivacyRequestStatus::Responded, $request->status);
    }

    /**
     * The screen must SAY why, not merely hide the button.
     */
    #[Test]
    public function the_screen_explains_why_release_is_unavailable(): void
    {
        $person = $this->personOn(Role::SystemAdmin);
        $service = app(PrivacyRequests::class);

        $request = $service->receive($this->aRequest(), $person);
        $request = $service->verifyIdentity($request, $person, 'in_person', 'Passport sighted.');
        $request = $service->assemble($request, $person);
        $request = $service->markReviewed($request, $person);

        $html = $this->actingAs($person)
            ->get('/admin/governance/privacy-requests/'.$request->getKey())
            ->getContent();

        $this->assertStringContainsString('You cannot release this response', $html);
        $this->assertStringContainsString('You reviewed this response', $html);
    }

    #[Test]
    public function who_assembled_the_response_is_recorded(): void
    {
        $handler = $this->personOn(Role::SystemAdmin);
        $service = app(PrivacyRequests::class);

        $request = $service->receive($this->aRequest(), $handler);
        $request = $service->verifyIdentity($request, $handler, 'in_person', 'Passport sighted.');
        $request = $service->assemble($request, $handler);

        $this->assertSame(
            $handler->getKey(),
            $request->assembled_by_user_id,
            'without this the separation can only be enforced against a permission tier',
        );
    }

    /* ------------------------------------- what the audit trail may hold */

    #[Test]
    public function the_audit_trail_records_that_an_assembly_happened_and_never_what_was_in_it(): void
    {
        $handler = $this->personOn(Role::SystemAdmin, 'Hilary Handler');
        $releaser = $this->personOn(Role::SystemAdmin, 'Robin Releaser');

        $request = $this->throughToRelease(app(PrivacyRequests::class), $handler, $releaser);

        $records = PrivacyRequestRecord::query()
            ->where('privacy_request_id', $request->getKey())
            ->get();

        $this->assertGreaterThan(0, $records->count(), 'nothing was assembled, so this proves nothing');

        /* Everything the assembled response actually says about this person.
         * None of it may appear anywhere in the trail. */
        $disclosed = $records
            ->flatMap(fn (PrivacyRequestRecord $record): array => array_filter([
                $record->summary,
                $record->detail === null ? null : json_encode($record->detail),
            ]))
            ->filter(fn (string $text): bool => trim($text) !== '')
            ->all();

        $trail = AuditEvent::query()
            ->where('resource_type', 'privacy_request')
            ->where('resource_id', (string) $request->getKey())
            ->get();

        $this->assertGreaterThan(0, $trail->count());

        foreach ($trail as $event) {
            $row = json_encode($event->toArray());

            $this->assertIsString($row);

            /* The subject's own identity is personal data too, and the audit
             * summary deliberately records only whether they have an account. */
            $this->assertStringNotContainsString('dana@example.test', $row, $event->action);
            $this->assertStringNotContainsString('Dana Subject', $row, $event->action);

            foreach ($disclosed as $text) {
                $this->assertStringNotContainsString(
                    $text,
                    $row,
                    'the audit event '.$event->action.' carries assembled personal data',
                );
            }
        }

        /* What it DOES record: that an assembly happened, and how many items. */
        $assembled = $trail->firstWhere('action', 'governance.privacy_request.assembled');

        $this->assertNotNull($assembled);
        $this->assertArrayHasKey('items', (array) $assembled->after_summary);
    }

    /**
     * Run a request that is expected to fail, without caring how the framework
     * surfaces the refusal - what matters is that nothing was released.
     */
    private function withoutExceptionHandling__safely(callable $call): void
    {
        try {
            $call();
        } catch (\Throwable) {
            // The refusal is the point; the transport is not.
        }
    }
}
