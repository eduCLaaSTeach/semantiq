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

        $request = $this->throughToRelease($service, $actor);
        $request = $service->close($request, $actor);

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

    private function throughToRelease(PrivacyRequests $service, User $actor): PrivacyRequest
    {
        $request = $service->receive($this->aRequest(), $actor);
        $request = $service->verifyIdentity($request, $actor, 'in_person', 'Passport sighted.');
        $request = $service->assemble($request, $actor);

        return $service->release($request, $actor, 'Handed over in person on 26 August.');
    }
}
