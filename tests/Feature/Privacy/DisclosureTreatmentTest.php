<?php

declare(strict_types=1);

namespace Tests\Feature\Privacy;

use App\Enums\Role;
use App\Models\User;
use App\Modules\Governance\Enums\DisclosureBand;
use App\Modules\Governance\Enums\DisclosureTreatment;
use App\Modules\Governance\Models\PrivacyRequestRecord;
use App\Modules\Governance\Privacy\CollectedItem;
use App\Modules\Governance\Privacy\PrivacySubjectAssembler;
use App\Modules\Governance\Services\PrivacyRequests;
use App\Modules\Identity\Support\OrganisationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

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
        $reviewer = $this->personOn(Role::SystemAdmin);

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
        $reviewer = $this->personOn(Role::SystemAdmin);

        $this->expectException(RuntimeException::class);

        app(PrivacySubjectAssembler::class)->retreat(
            $record,
            DisclosureTreatment::Include,
            $reviewer,
            'They asked for the detail.',
        );
    }

    #[Test]
    public function the_second_approver_cannot_be_the_reviewer(): void
    {
        $record = $this->aRecord();
        $reviewer = $this->personOn(Role::SystemAdmin);

        $this->expectException(RuntimeException::class);

        app(PrivacySubjectAssembler::class)->retreat(
            $record,
            DisclosureTreatment::Include,
            $reviewer,
            'They asked for the detail.',
            $reviewer,
        );
    }

    #[Test]
    public function widening_with_a_genuine_second_approver_is_recorded_as_widened(): void
    {
        $record = $this->aRecord();
        $reviewer = $this->personOn(Role::SystemAdmin);
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
        $reviewer = $this->personOn(Role::SystemAdmin);

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
            $this->personOn(Role::SystemAdmin),
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
        $service->release($request, $actor, 'Handed over in person.');

        Queue::assertNothingPushed();
        Mail::assertNothingSent();
        $this->assertSame([], Storage::disk('local')->allFiles());
    }
}
