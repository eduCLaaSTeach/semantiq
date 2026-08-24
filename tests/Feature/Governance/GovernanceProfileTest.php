<?php

declare(strict_types=1);

namespace Tests\Feature\Governance;

use App\Enums\Role;
use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Governance\Enums\ProfileStatus;
use App\Modules\Governance\Models\DataProtectionProfile;
use App\Modules\Governance\Models\DataSovereigntyProfile;
use App\Modules\Governance\Services\DataProtectionProfiles;
use App\Modules\Governance\Services\SovereigntyProfiles;
use App\Modules\Identity\Support\OrganisationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * ADM-014 and ADM-015: the versioning contract, and the seed.
 *
 * WHAT THESE TESTS EXIST TO HOLD IN PLACE, all of it decision D4 and D12:
 *
 *   An approved version is IMMUTABLE. Not "should not be edited" - refused, at
 *   the model, so a console command or a future API meets the same wall a form
 *   does.
 *   Changing an approved profile creates a NEW version and supersedes the old,
 *   so what was in force in March stays answerable.
 *   At most ONE approved version exists at a time.
 *   The seeded sovereignty draft is NEVER in force until a person approves it.
 *   Nothing can be deleted.
 */
class GovernanceProfileTest extends TestCase
{
    use RefreshDatabase;

    private function actor(Role $role = Role::SystemAdmin): User
    {
        $user = User::query()->create(['name' => 'Test Person', 'email' => uniqid().'@example.test']);

        $user->forceFill([
            'role' => $role,
            'organisation_id' => app(OrganisationContext::class)->require()->getKey(),
        ])->save();

        return $user->refresh();
    }

    #[Test]
    public function nothing_is_in_force_on_a_fresh_install(): void
    {
        /* Not Configured, and specifically NOT "the defaults are in force".
         * There is no default privacy position: either somebody decided one or
         * nobody did. */
        $this->assertNull(app(DataProtectionProfiles::class)->inForce());
        $this->assertNull(app(SovereigntyProfiles::class)->inForce());
    }

    #[Test]
    public function a_saved_draft_is_not_in_force(): void
    {
        $actor = $this->actor();

        app(DataProtectionProfiles::class)->saveDraft(
            ['applicable_regime' => 'Singapore PDPA'],
            $actor,
        );

        $this->assertNotNull(app(DataProtectionProfiles::class)->draft());
        $this->assertNull(app(DataProtectionProfiles::class)->inForce());
    }

    #[Test]
    public function approving_puts_a_version_in_force(): void
    {
        $actor = $this->actor();
        $service = app(DataProtectionProfiles::class);

        $service->saveDraft(['applicable_regime' => 'Singapore PDPA'], $actor);
        $approved = $service->approve($actor, 'Confirmed with the compliance owner.');

        $this->assertSame(ProfileStatus::Approved, $approved->status);
        $this->assertSame($approved->getKey(), $service->inForce()?->getKey());
        $this->assertNull($service->draft());
    }

    #[Test]
    public function an_approved_version_cannot_be_edited(): void
    {
        $actor = $this->actor();
        $service = app(DataProtectionProfiles::class);

        $service->saveDraft(['applicable_regime' => 'Singapore PDPA'], $actor);
        $approved = $service->approve($actor, 'Confirmed with the compliance owner.');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/cannot be edited/');

        $approved->applicable_regime = 'EU GDPR';
        $approved->save();
    }

    #[Test]
    public function changing_an_approved_profile_creates_a_new_version_and_supersedes_the_old(): void
    {
        $actor = $this->actor();
        $service = app(DataProtectionProfiles::class);

        $service->saveDraft(['applicable_regime' => 'Singapore PDPA'], $actor);
        $first = $service->approve($actor, 'Initial determination, confirmed with counsel.');

        $service->saveDraft(['applicable_regime' => 'EU GDPR'], $actor);
        $second = $service->approve($actor, 'Customer moved to an EU entity, confirmed with counsel.');

        $first->refresh();

        $this->assertSame(1, $first->version);
        $this->assertSame(2, $second->version);
        $this->assertSame(ProfileStatus::Superseded, $first->status);
        $this->assertSame($second->getKey(), $first->superseded_by_id);
        $this->assertNotNull($first->superseded_at);

        /* The point-in-time answer survives. */
        $this->assertSame('Singapore PDPA', $first->applicable_regime);
        $this->assertSame('EU GDPR', $second->applicable_regime);
    }

    #[Test]
    public function only_one_version_is_ever_approved_at_a_time(): void
    {
        $actor = $this->actor();
        $service = app(DataProtectionProfiles::class);

        foreach (['Singapore PDPA', 'EU GDPR', 'UK GDPR'] as $regime) {
            $service->saveDraft(['applicable_regime' => $regime], $actor);
            $service->approve($actor, 'Approved for testing the invariant.');
        }

        $this->assertSame(
            1,
            DataProtectionProfile::query()->approved()->count(),
            'More than one version is approved at once, so "the version in force" has no single answer.'
        );
    }

    #[Test]
    public function a_new_draft_copies_the_approved_version(): void
    {
        $actor = $this->actor();
        $service = app(DataProtectionProfiles::class);

        $service->saveDraft([
            'applicable_regime' => 'Singapore PDPA',
            'regime_basis' => 'Determined by counsel on 25 August 2026.',
            'breach_notification_due_days' => 3,
        ], $actor);
        $service->approve($actor, 'Initial determination, confirmed with counsel.');

        /* Somebody changes one field. They should not have to retype the rest. */
        $service->saveDraft(['breach_notification_due_days' => 5], $actor);

        $draft = $service->draft();

        $this->assertSame('Singapore PDPA', $draft?->applicable_regime);
        $this->assertSame('Determined by counsel on 25 August 2026.', $draft?->regime_basis);
        $this->assertSame(5, $draft?->breach_notification_due_days);
    }

    #[Test]
    public function approving_requires_a_reason(): void
    {
        $actor = $this->actor();
        $service = app(DataProtectionProfiles::class);

        $service->saveDraft(['applicable_regime' => 'Singapore PDPA'], $actor);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/requires a stated reason/');

        $service->approve($actor, '   ');
    }

    #[Test]
    public function a_version_cannot_be_deleted(): void
    {
        /* SEC-DEC-038 holds across gate 4: there is no deletion path anywhere. */
        $actor = $this->actor();
        $service = app(DataProtectionProfiles::class);

        $draft = $service->saveDraft(['applicable_regime' => 'Singapore PDPA'], $actor);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/cannot be deleted/');

        $draft->delete();
    }

    #[Test]
    public function the_sovereignty_profile_seeds_a_draft_from_the_confirmed_facts(): void
    {
        $actor = $this->actor();

        $draft = app(SovereigntyProfiles::class)->ensureDraft($actor);

        $this->assertNotNull($draft);
        $this->assertSame('sg', $draft->storage_geography);
        $this->assertSame('sg', $draft->backup_geography);
        $this->assertSame('none', $draft->external_replication);
        $this->assertStringContainsString('SEC-DEC-036', (string) $draft->source_note);
    }

    #[Test]
    public function the_seeded_sovereignty_draft_is_never_in_force(): void
    {
        /*
         * The whole of SEC-DEC-068 in one assertion. A profile nobody approved
         * is a guess with good provenance, and a screen showing it as settled
         * would be a false healthy applied to sovereignty.
         */
        $actor = $this->actor();
        $service = app(SovereigntyProfiles::class);

        $draft = $service->ensureDraft($actor);

        $this->assertSame(ProfileStatus::Draft, $draft?->status);
        $this->assertFalse($draft->isInForce());
        $this->assertNull($service->inForce());
    }

    #[Test]
    public function the_seed_runs_once_and_does_not_reopen_a_draft_under_a_settled_profile(): void
    {
        $actor = $this->actor();
        $service = app(SovereigntyProfiles::class);

        $service->ensureDraft($actor);
        $service->approve($actor, 'Geographies confirmed with the hosting provider.');

        /* Settled. Opening a draft here would make the screen look like
         * somebody had started editing when nobody had. */
        $this->assertNull($service->ensureDraft($actor));
        $this->assertSame(1, DataSovereigntyProfile::query()->count());
    }

    #[Test]
    public function the_seeded_geographies_are_recorded_as_a_draft_in_the_audit_trail(): void
    {
        $actor = $this->actor();

        app(SovereigntyProfiles::class)->ensureDraft($actor);

        $event = AuditEvent::query()
            ->where('action', 'governance.sovereignty_profile.seeded')
            ->first();

        $this->assertNotNull($event, 'Seeding a sovereignty position left no trail.');

        /* And it records the real values, not "[redacted]". SEC-DEC-044. */
        $summary = (array) $event->after_summary;
        $this->assertSame('sg', $summary['storage_geography'] ?? null);
        $this->assertSame('sg', $summary['backup_geography'] ?? null);
        $this->assertSame('draft', $summary['status'] ?? null);
    }

    #[Test]
    public function a_profile_naming_two_geographies_crosses_a_border_even_with_every_switch_off(): void
    {
        /*
         * The case a switch-only check would miss. Singapore storage with
         * United States backups has crossed a border whatever the switches say,
         * and backups are exactly the leg people forget - which is why
         * SEC-DEC-036 asked about them separately.
         */
        $actor = $this->actor();
        $service = app(SovereigntyProfiles::class);

        $service->ensureDraft($actor);
        $draft = $service->saveDraft(['backup_geography' => 'us'], $actor);

        $this->assertFalse($draft->cross_geo_storage);
        $this->assertFalse($draft->cross_geo_processing);
        $this->assertTrue(
            $draft->crossesABorder(),
            'Singapore storage and United States backups is a cross-border position and was not flagged.'
        );
    }

    #[Test]
    public function the_form_is_prefilled_from_the_version_in_force_when_no_draft_is_open(): void
    {
        /*
         * FOUND IN A BROWSER, not by a test - and the service test above passed
         * throughout, because it called `saveDraft()` with one key rather than
         * through a form.
         *
         * With no draft open the form rendered BLANK. Somebody changing one
         * field therefore posted six empty ones on top of an approved profile,
         * and the new version silently lost every value the old one had.
         * `saveDraft()` does copy the approved version first, but the copy was
         * overwritten a moment later by the blanks the form had just sent.
         *
         * The fix is that the form renders from the version in force when there
         * is no draft, so the post carries those values forward. This asserts
         * the rendered HTML, which is where the defect actually lived.
         */
        $actor = $this->actor();
        $service = app(DataProtectionProfiles::class);

        $service->saveDraft([
            'applicable_regime' => 'Singapore PDPA',
            'regime_basis' => 'Determined by counsel on 25 August 2026.',
            'privacy_officer_designated' => true,
            'breach_notification_due_days' => 3,
            'breach_notification_basis' => 'PDPA Part VIB.',
        ], $actor);
        $service->approve($actor, 'Initial determination, confirmed with counsel.');

        $this->assertNull($service->draft());

        $response = $this->actingAs($actor)->get(route('admin.governance.data-protection'));

        $response->assertOk();
        $response->assertSee('Determined by counsel on 25 August 2026.', false);
        $response->assertSee('PDPA Part VIB.', false);
        /* And the form says what saving it will do, rather than looking like an
         * edit of the approved version. */
        $response->assertSee('starts version 2 as a draft');
    }

    #[Test]
    public function a_sovereignty_profile_can_still_be_revised_after_it_is_approved(): void
    {
        /*
         * ALSO FOUND IN A BROWSER. The sovereignty screen rendered its form
         * only when a draft existed. The seeded draft is consumed by the first
         * approval, and nothing opened another - so the screen became
         * permanently read-only the moment somebody approved version 1, with a
         * `beginRevision()` method nothing ever called.
         *
         * A sovereignty position that cannot be changed after approval is worse
         * than useless: geographies move.
         */
        $actor = $this->actor();
        $service = app(SovereigntyProfiles::class);

        $service->ensureDraft($actor);
        $service->saveDraft(['evidence_reference' => 'Hosting agreement 2026-SG-01'], $actor);
        $service->approve($actor, 'Geographies confirmed with the hosting provider.');

        $this->assertNull($service->draft());

        $response = $this->actingAs($actor)->get(route('admin.governance.sovereignty'));

        $response->assertOk();
        /* The form is there... */
        $response->assertSee('name="storage_geography"', false);
        /* ...pre-filled from the version in force... */
        $response->assertSee('Hosting agreement 2026-SG-01', false);
        /* ...and it says what saving it will do. A short fragment, because the
         * sentence wraps across source lines and a longer one would assert the
         * blade's line breaks rather than its wording. */
        $response->assertSee('starts version 2');

        /* And saving really does open version 2 without disturbing version 1. */
        $revision = $service->saveDraft(['backup_geography' => 'my'], $actor);

        $this->assertSame(2, $revision->version);
        $this->assertSame(ProfileStatus::Draft, $revision->status);
        $this->assertSame(1, $service->inForce()?->version);
        /* The carried-forward value survived the revision. */
        $this->assertSame('Hosting agreement 2026-SG-01', $revision->evidence_reference);
    }

    #[Test]
    public function an_incomplete_profile_names_what_is_missing_rather_than_reading_as_complete(): void
    {
        $actor = $this->actor();
        $service = app(DataProtectionProfiles::class);

        $draft = $service->saveDraft(['applicable_regime' => 'Singapore PDPA'], $actor);

        $gaps = $draft->gaps();

        $this->assertNotEmpty($gaps);

        /* The two compliance-owned fields count as gaps, deliberately: an
         * approved profile with no stated legal basis is incomplete, and
         * calling it complete would claim compliance nobody signed off. */
        $joined = implode(' ', $gaps);
        $this->assertStringContainsString('basis', $joined);
        $this->assertStringContainsString('compliance-owned', $joined);
    }
}
