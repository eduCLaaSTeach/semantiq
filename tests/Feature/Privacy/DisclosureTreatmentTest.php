<?php

declare(strict_types=1);

namespace Tests\Feature\Privacy;

use App\Enums\Role;
use App\Models\User;
use App\Modules\Audit\Exceptions\RequiredAuditEvidenceMissing;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Governance\Enums\DisclosureBand;
use App\Modules\Governance\Enums\DisclosureTreatment;
use App\Modules\Governance\Models\PrivacyRequestRecord;
use App\Modules\Governance\Privacy\CollectedItem;
use App\Modules\Governance\Privacy\PrivacySubjectAssembler;
use App\Modules\Governance\Services\PrivacyRequests;
use App\Modules\Identity\Support\Authorization;
use App\Modules\Identity\Support\OrganisationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;
use Throwable;

/**
 * The disclosure rules themselves: what may be widened, by whom, and what this
 * gate refuses to build at all.
 */
class DisclosureTreatmentTest extends TestCase
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

    private function aRecord(): PrivacyRequestRecord
    {
        $actor = $this->personOn(Role::SystemAdmin);
        $service = app(PrivacyRequests::class);

        $request = $service->receive([
            'request_type' => 'access',
            'subject_name' => 'Dana Subject',
            'subject_email' => 'dana@example.test',
            'subject_user_id' => $actor->getKey(),
            'received_at' => now()->toDateString(),
        ], $actor);

        $request = $service->verifyIdentity($request, $actor, 'signed_in_session', 'Signed in.');
        $request = $service->assemble($request, $actor);

        return PrivacyRequestRecord::query()
            ->where('privacy_request_id', $request->getKey())
            ->inBand(DisclosureBand::C)
            ->firstOrFail();
    }

    /**
     * Make every audit write fail the way a sick database would.
     *
     * A listener that throws on `AuditEvent` creation, so the REAL
     * `AuditLogger` catch block runs and returns the REAL null. A fake logger
     * would prove only that the fake works.
     */
    private function auditWritesFail(): void
    {
        Event::listen('eloquent.creating: '.AuditEvent::class, function (): void {
            throw new RuntimeException('audit storage is unavailable');
        });
    }

    /**
     * A reviewer who is actually signed in.
     *
     * `retreat()` requires the named reviewer to BE the authenticated actor, so
     * a helper that returns a user without signing them in would make every
     * test here exercise a path the application refuses. Creating and
     * authenticating together is what stops that being forgotten.
     */
    private function signedInReviewer(Role $role = Role::SystemAdmin): User
    {
        $reviewer = $this->personOn($role);

        $this->actingAs($reviewer);

        return $reviewer;
    }

    private function treatmentEvents(): int
    {
        return AuditEvent::query()
            ->where('action', 'governance.privacy_request.treatment_changed')
            ->count();
    }

    /**
     * Assert that a refused treatment change left the row exactly as it was.
     *
     * The whole reason this invariant needed writing down is that the two
     * second-approver tests below proved an exception and never looked at the
     * row behind it.
     */
    /**
     * @return array{treatment: DisclosureTreatment, reviewer_action: ?string, reviewer_note: ?string}
     */
    private function snapshot(PrivacyRequestRecord $record): array
    {
        $fresh = PrivacyRequestRecord::query()->findOrFail($record->getKey());

        return [
            'treatment' => $fresh->treatment,
            'reviewer_action' => $fresh->reviewer_action,
            'reviewer_note' => $fresh->reviewer_note,
        ];
    }

    /**
     * @param  array{treatment: DisclosureTreatment, reviewer_action: ?string, reviewer_note: ?string}  $before
     */
    private function assertTreatmentUnchanged(PrivacyRequestRecord $record, array $before): void
    {
        $this->assertSame(
            $before,
            $this->snapshot($record),
            'the treatment, the reviewer action or the note moved despite the refusal',
        );
    }

    /** Run a treatment change expected to be refused. */
    private function refused(callable $call): Throwable
    {
        try {
            $call();
            $this->fail('the treatment change was expected to be refused and was not');
        } catch (Throwable $exception) {
            return $exception;
        }
    }

    /* ------------------------------------------- the construction-time gate */

    #[Test]
    public function a_described_item_cannot_carry_a_payload(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CollectedItem(
            'roles',
            DisclosureBand::C,
            DisclosureTreatment::Describe,
            'You changed a role.',
            ['other_person' => 'Bob Bystander'],
        );
    }

    #[Test]
    public function an_excluded_item_cannot_carry_a_payload(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CollectedItem(
            'secret_references',
            DisclosureBand::C,
            DisclosureTreatment::Exclude,
            'Withheld.',
            ['secret' => 'anything'],
        );
    }

    #[Test]
    public function an_item_must_say_something(): void
    {
        $this->expectException(InvalidArgumentException::class);

        CollectedItem::describe('roles', DisclosureBand::C, '   ');
    }

    /* ---------------------------------------------------- narrow vs widen */

    #[Test]
    public function narrowing_needs_only_the_reviewer(): void
    {
        $record = $this->aRecord();
        $reviewer = $this->signedInReviewer();

        $updated = app(PrivacySubjectAssembler::class)->retreat(
            $record,
            DisclosureTreatment::Exclude,
            $reviewer,
            'The record names a third party.',
        );

        $this->assertSame(DisclosureTreatment::Exclude, $updated->treatment);
        $this->assertSame('narrowed', $updated->reviewer_action);
    }

    #[Test]
    public function widening_without_a_second_approver_is_refused(): void
    {
        $record = $this->aRecord();
        $reviewer = $this->signedInReviewer();

        $before = $this->snapshot($record);
        $events = $this->treatmentEvents();

        $this->refused(fn () => app(PrivacySubjectAssembler::class)->retreat(
            $record,
            DisclosureTreatment::Include,
            $reviewer,
            'They asked for the detail.',
        ));

        /* The refusal happens before any write, so nothing is expected to have
         * moved - but that is a claim about the row, and it is asserted here
         * rather than inferred from the exception. */
        $this->assertTreatmentUnchanged($record, $before);
        $this->assertSame($events, $this->treatmentEvents());
    }

    #[Test]
    public function the_second_approver_cannot_be_the_reviewer(): void
    {
        $record = $this->aRecord();
        $reviewer = $this->signedInReviewer();

        $before = $this->snapshot($record);
        $events = $this->treatmentEvents();

        $this->refused(fn () => app(PrivacySubjectAssembler::class)->retreat(
            $record,
            DisclosureTreatment::Include,
            $reviewer,
            'They asked for the detail.',
            $reviewer,
        ));

        $this->assertTreatmentUnchanged($record, $before);
        $this->assertSame($events, $this->treatmentEvents());
    }

    /* ------------------ the reviewer is the authenticated actor (12a) */

    #[Test]
    public function a_reviewer_who_is_not_the_authenticated_actor_is_refused(): void
    {
        $record = $this->aRecord();

        $alice = $this->personOn(Role::SystemAdmin);
        $charlie = $this->personOn(Role::SystemAdmin);

        /* Charlie is signed in and names Alice as the reviewer. Both hold
         * every permission, so nothing about authority refuses this - only
         * the identity check does. Without it the decision would be weighed
         * against Alice and the audit event attributed to Charlie. */
        $this->actingAs($charlie);

        $before = $this->snapshot($record);

        $exception = $this->refused(fn () => app(PrivacySubjectAssembler::class)->retreat(
            $record,
            DisclosureTreatment::Exclude,
            $alice,
            'Recorded against somebody who is not here.',
        ));

        $this->assertStringContainsString('must be the person making it', $exception->getMessage());
        $this->assertTreatmentUnchanged($record, $before);
        $this->assertSame(0, $this->treatmentEvents());
    }

    #[Test]
    public function an_unauthenticated_treatment_change_is_refused(): void
    {
        $record = $this->aRecord();
        $reviewer = $this->personOn(Role::SystemAdmin);

        /* Deliberately NOT signed in - a console command or a queued job. */
        $before = $this->snapshot($record);

        $this->refused(fn () => app(PrivacySubjectAssembler::class)->retreat(
            $record,
            DisclosureTreatment::Exclude,
            $reviewer,
            'An unattended caller.',
        ));

        $this->assertTreatmentUnchanged($record, $before);
        $this->assertSame(0, $this->treatmentEvents());
    }

    #[Test]
    public function a_reviewer_without_manage_is_refused(): void
    {
        $record = $this->aRecord();

        /* A Viewer is genuinely signed in and genuinely is who they say they
         * are. Identity is not authority. */
        $reviewer = $this->signedInReviewer(Role::Viewer);

        $before = $this->snapshot($record);

        $this->refused(fn () => app(PrivacySubjectAssembler::class)->retreat(
            $record,
            DisclosureTreatment::Exclude,
            $reviewer,
            'Narrowing looks harmless, but it is still a disclosure decision.',
        ));

        $this->assertTreatmentUnchanged($record, $before);
        $this->assertSame(0, $this->treatmentEvents());
    }

    /* ---------------- the second approver must be authorized (12b) */

    #[Test]
    public function a_viewer_cannot_be_the_second_approver(): void
    {
        $record = $this->aRecord();
        $reviewer = $this->signedInReviewer();
        $approver = $this->personOn(Role::Viewer);

        $before = $this->snapshot($record);

        $exception = $this->refused(fn () => app(PrivacySubjectAssembler::class)->retreat(
            $record,
            DisclosureTreatment::Include,
            $reviewer,
            'A second name on the form is not a second decision.',
            $approver,
        ));

        $this->assertStringContainsString('authorised to release', $exception->getMessage());
        $this->assertTreatmentUnchanged($record, $before);
        $this->assertSame(0, $this->treatmentEvents());
    }

    #[Test]
    public function an_administrator_without_release_cannot_be_the_second_approver(): void
    {
        $record = $this->aRecord();
        $reviewer = $this->signedInReviewer();

        /* An Administrator may MANAGE privacy requests and may not RELEASE
         * one - SEC-DEC-083 puts release at the System Administrator ceiling.
         * Approving a widening is a disclosure decision, so it sits at the
         * same ceiling, and this is the case that proves the two permissions
         * are genuinely being told apart rather than one standing in for the
         * other. */
        $approver = $this->personOn(Role::Admin);

        $this->assertTrue(
            app(Authorization::class)
                ->allows($approver, 'admin.privacy_requests.manage'),
            'the premise of this test is an approver who can manage but not release',
        );

        $before = $this->snapshot($record);

        $this->refused(fn () => app(PrivacySubjectAssembler::class)->retreat(
            $record,
            DisclosureTreatment::Include,
            $reviewer,
            'Close, but not the ceiling this decision sits at.',
            $approver,
        ));

        $this->assertTreatmentUnchanged($record, $before);
        $this->assertSame(0, $this->treatmentEvents());
    }

    #[Test]
    public function an_authorized_system_administrator_may_be_the_second_approver(): void
    {
        $record = $this->aRecord();
        $reviewer = $this->signedInReviewer();
        $approver = $this->personOn(Role::SystemAdmin);

        $updated = app(PrivacySubjectAssembler::class)->retreat(
            $record,
            DisclosureTreatment::Include,
            $reviewer,
            'Reviewed the underlying row; it names nobody else.',
            $approver,
        );

        $this->assertSame(DisclosureTreatment::Include, $updated->treatment);
        $this->assertSame('widened', $updated->reviewer_action);
        $this->assertSame(1, $this->treatmentEvents());
    }

    /* ------------------------- a treatment change is evidence-critical (12) */

    #[Test]
    public function a_failed_audit_on_narrowing_leaves_the_treatment_unchanged(): void
    {
        $record = $this->aRecord();
        $reviewer = $this->signedInReviewer();

        $before = $this->snapshot($record);

        $this->auditWritesFail();

        $exception = $this->refused(fn () => app(PrivacySubjectAssembler::class)->retreat(
            $record,
            DisclosureTreatment::Exclude,
            $reviewer,
            'On reflection this says more than it needs to.',
        ));

        $this->assertInstanceOf(RequiredAuditEvidenceMissing::class, $exception);

        /* Narrowing is one person's call and would otherwise have succeeded.
         * This is the rollback, not a pre-flight refusal. */
        $this->assertTreatmentUnchanged($record, $before);
        $this->assertSame(0, $this->treatmentEvents());
    }

    #[Test]
    public function a_failed_audit_on_widening_leaves_the_treatment_unchanged(): void
    {
        $record = $this->aRecord();
        $reviewer = $this->signedInReviewer();
        $approver = $this->personOn(Role::SystemAdmin);

        $before = $this->snapshot($record);

        $this->auditWritesFail();

        $exception = $this->refused(fn () => app(PrivacySubjectAssembler::class)->retreat(
            $record,
            DisclosureTreatment::Include,
            $reviewer,
            'Reviewed the underlying row; it names nobody else.',
            $approver,
        ));

        $this->assertInstanceOf(RequiredAuditEvidenceMissing::class, $exception);

        /* The dangerous direction, with a valid second approver, so every
         * guard passed and only the missing evidence stopped it. */
        $this->assertTreatmentUnchanged($record, $before);
        $this->assertSame(0, $this->treatmentEvents());
    }

    #[Test]
    public function a_treatment_change_is_recorded_without_any_collected_personal_data(): void
    {
        $record = $this->aRecord();
        $reviewer = $this->signedInReviewer();
        $approver = $this->personOn(Role::SystemAdmin);

        $summary = $record->summary;
        $detail = $record->detail === null ? null : json_encode($record->detail);

        app(PrivacySubjectAssembler::class)->retreat(
            $record,
            DisclosureTreatment::Include,
            $reviewer,
            'Reviewed the underlying row; it names nobody else.',
            $approver,
        );

        $event = AuditEvent::query()
            ->where('action', 'governance.privacy_request.treatment_changed')
            ->sole();

        $row = json_encode($event->toArray());

        $this->assertIsString($row);

        /* What the treatment governs must never travel in the record OF the
         * treatment decision. */
        if ($summary !== null && trim($summary) !== '') {
            $this->assertStringNotContainsString($summary, $row);
        }

        if ($detail !== null) {
            $this->assertStringNotContainsString($detail, $row);
        }

        $this->assertStringNotContainsString('Dana Subject', $row);
        $this->assertStringNotContainsString('dana@example.test', $row);

        /* What it DOES carry: the shape of the decision, and both parties. */
        $after = (array) $event->after_summary;

        $this->assertSame('describe', ((array) $event->before_summary)['treatment'], 'previous treatment');
        $this->assertSame('include', $after['treatment'], 'new treatment');
        $this->assertSame('widened', $after['reviewer_action'], 'direction');
        $this->assertSame($record->source_table, $after['source_table'], 'source table');
        $this->assertSame($record->getKey(), $after['record_id'], 'record id');

        /* Both parties, by id. The reviewer is written explicitly AND is the
         * audit actor, because the two are now forced to agree. */
        $this->assertSame($reviewer->getKey(), $after['reviewer_user_id'], 'reviewer identity');
        $this->assertSame($reviewer->getKey(), $event->actor_user_id, 'audit actor matches the reviewer');
        $this->assertTrue($after['second_approval_present']);
        $this->assertSame($approver->getKey(), $after['second_approver_user_id'], 'approver identity');
    }

    #[Test]
    public function widening_with_a_genuine_second_approver_is_recorded_as_widened(): void
    {
        $record = $this->aRecord();
        $reviewer = $this->signedInReviewer();
        $approver = $this->personOn(Role::SystemAdmin);

        $updated = app(PrivacySubjectAssembler::class)->retreat(
            $record,
            DisclosureTreatment::Include,
            $reviewer,
            'Reviewed the underlying row; it names nobody else.',
            $approver,
        );

        $this->assertSame(DisclosureTreatment::Include, $updated->treatment);
        $this->assertSame('widened', $updated->reviewer_action);
        $this->assertNotEmpty($updated->reviewer_note);
    }

    #[Test]
    public function a_change_of_treatment_must_record_why(): void
    {
        $record = $this->aRecord();
        $reviewer = $this->signedInReviewer();

        $this->expectException(RuntimeException::class);

        app(PrivacySubjectAssembler::class)->retreat(
            $record,
            DisclosureTreatment::Exclude,
            $reviewer,
            '   ',
        );
    }

    /**
     * Widening cannot conjure data the collector declined to load.
     *
     * The band C collectors select a count and a date range and never load the
     * other party, so there is nothing to reveal even at the widest treatment.
     */
    #[Test]
    public function widening_a_band_c_item_still_discloses_no_payload(): void
    {
        $record = $this->aRecord();

        $updated = app(PrivacySubjectAssembler::class)->retreat(
            $record,
            DisclosureTreatment::Include,
            $this->signedInReviewer(),
            'Checked the underlying rows.',
            $this->personOn(Role::SystemAdmin),
        );

        $this->assertNull(
            $updated->detail,
            'widening must not create a payload that was never collected',
        );
    }

    /* ------------------------------------------- what this gate refuses */

    #[Test]
    public function assembling_writes_no_file_queues_no_job_and_sends_no_mail(): void
    {
        Storage::fake('local');
        Queue::fake();
        Mail::fake();

        $actor = $this->personOn(Role::SystemAdmin);
        $service = app(PrivacyRequests::class);

        $request = $service->receive([
            'request_type' => 'access',
            'subject_name' => 'Dana Subject',
            'subject_email' => 'dana@example.test',
            'subject_user_id' => $actor->getKey(),
            'received_at' => now()->toDateString(),
        ], $actor);

        $request = $service->verifyIdentity($request, $actor, 'signed_in_session', 'Signed in.');
        $request = $service->assemble($request, $actor);
        $request = $service->markReviewed($request, $actor);

        /* A SECOND PERSON RELEASES. The person who assembled and reviewed a
         * response cannot authorise its own disclosure, so a test that used one
         * actor here would be exercising a path the application refuses. */
        $service->release($request, $this->personOn(Role::SystemAdmin), 'Handed over in person.');

        Queue::assertNothingPushed();
        Mail::assertNothingSent();
        $this->assertSame([], Storage::disk('local')->allFiles());
    }
}
