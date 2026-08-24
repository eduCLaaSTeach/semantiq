<?php

declare(strict_types=1);

namespace Tests\Feature\Governance;

use App\Enums\Role;
use App\Models\User;
use App\Modules\Governance\Exceptions\GovernanceStorageNotInitialised;
use App\Modules\Governance\Models\RetentionPolicy;
use App\Modules\Governance\Models\SovereigntyException;
use App\Modules\Governance\Services\DataProtectionProfiles;
use App\Modules\Governance\Services\PersonalDataCatalogue;
use App\Modules\Governance\Services\SovereigntyProfiles;
use App\Modules\Governance\Support\GovernanceStorage;
use App\Modules\Identity\Support\OrganisationContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Gate 4 must survive its own release.
 *
 * The deploy workflow ships code and does NOT run migrations, so every release
 * that adds a table opens a window where the code is live and the tables are
 * not there. Gate 3 measured what that costs: `GET /sign-in` returned 500 and
 * the whole site went down, sign-in included, so nobody could get in to notice.
 *
 * GATE 4 ADDS NO MIDDLEWARE, so the blast radius is smaller - a failure would
 * be confined to the Compliance screens. That is a smaller defect, not an
 * acceptable one, and these tests hold the same three properties gate 3's do:
 *
 *   PROFILE READS fall back and report Not Configured, because with no table
 *   there can be no approved profile and "nothing is approved" is TRUE.
 *
 *   WRITES FAIL CLOSED. Accepting a change and discarding it would tell an
 *   administrator their privacy position had changed when nothing had.
 *
 *   THE REGISTER SCREEN SAYS "MIGRATION REQUIRED", never an empty list. "No
 *   categories recorded" and "we cannot see what is recorded" are opposite
 *   facts, and gate 3 shipped the second as the first. SEC-DEC-057,
 *   SEC-DEC-072.
 *
 * The detection is a SCHEMA QUESTION, never a caught database exception - a
 * broken connection must still fail loudly instead of being reported as
 * "everything is fine, using defaults". SEC-DEC-056.
 */
class GovernanceDeploymentOrderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Drop the gate 4 tables, as the deployment window leaves them.
     *
     * The `migrations` rows go too. On the server those rows have never been
     * written, and dropping only the tables would leave the ledger claiming the
     * work was done - `migrate` would then find nothing pending, which is how
     * gate 3's round-trip test failed before its equivalent line existed.
     */
    private function undoTheGateFourMigrations(): void
    {
        /* R1.4b first: these carry foreign keys into the R1.4a tables. */
        Schema::dropIfExists('sovereignty_exceptions');
        Schema::dropIfExists('retention_policies');

        Schema::dropIfExists('data_protection_profiles');
        Schema::dropIfExists('data_sovereignty_profiles');
        Schema::dropIfExists('personal_data_categories');

        /*
         * The privacy-contact columns go too. This batch's fourth migration
         * ALTERS an existing table rather than creating one, and forgetting it
         * here would leave the columns in place while the ledger says they are
         * not - so `migrate` would try to add them again and fail on a
         * duplicate column. Caught by this test failing, which is the point of
         * running the round trip rather than only the down leg.
         */
        Schema::table('organisations', function (Blueprint $table) {
            $table->dropColumn([
                'privacy_contact_name',
                'privacy_contact_email',
                'privacy_contact_phone',
                'privacy_contact_role',
            ]);
        });

        /*
         * The two audit indexes go too. R1.4b's third migration ALTERS an
         * existing table rather than creating one, and forgetting it here would
         * leave the indexes in place while the ledger says they are not - so
         * `migrate` would try to create them again and fail on a duplicate.
         *
         * Exactly the shape of bug the privacy-contact columns caused in R1.4a,
         * and caught the same way: by running the round trip rather than only
         * the down leg.
         */
        Schema::table('audit_events', function (Blueprint $table) {
            $table->dropIndex('audit_events_org_module_occurred_index');
            $table->dropIndex('audit_events_org_outcome_occurred_index');
        });

        DB::table('migrations')
            ->where(function ($q) {
                $q->where('migration', 'like', '2026_08_27_%')
                    ->orWhere('migration', 'like', '2026_08_28_%');
            })
            ->delete();

        app(GovernanceStorage::class)->forget();
    }

    private function runTheOutstandingMigrations(): void
    {
        Artisan::call('migrate', ['--force' => true]);

        app(GovernanceStorage::class)->forget();
    }

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
    public function readiness_is_answered_by_a_schema_question_and_not_by_swallowing_an_error(): void
    {
        $storage = app(GovernanceStorage::class);

        $this->assertTrue($storage->isReady());

        $this->undoTheGateFourMigrations();

        $this->assertFalse(app(GovernanceStorage::class)->dataProtectionIsReady());
        $this->assertFalse(app(GovernanceStorage::class)->sovereigntyIsReady());
        $this->assertFalse(app(GovernanceStorage::class)->categoriesAreReady());
        $this->assertFalse(app(GovernanceStorage::class)->isReady());
    }

    #[Test]
    public function sign_in_still_works_with_the_gate_four_tables_absent(): void
    {
        /*
         * The gate 3 regression, asserted for gate 4. Gate 4 adds no middleware
         * so this should never have been at risk - and asserting it is how that
         * stays true if a later batch reaches for one.
         */
        $this->undoTheGateFourMigrations();

        $this->get('/sign-in')->assertOk();
    }

    #[Test]
    public function a_profile_read_falls_back_rather_than_failing(): void
    {
        $this->undoTheGateFourMigrations();

        $this->assertNull(app(DataProtectionProfiles::class)->inForce());
        $this->assertNull(app(DataProtectionProfiles::class)->draft());
        $this->assertNull(app(SovereigntyProfiles::class)->inForce());
        $this->assertTrue(app(DataProtectionProfiles::class)->history()->isEmpty());
    }

    #[Test]
    public function the_sovereignty_seed_does_not_run_during_the_deployment_window(): void
    {
        /* Seeding into a table that does not exist would be an exception on a
         * READ path - the exact shape of the gate 3 outage. */
        $this->undoTheGateFourMigrations();

        $this->assertNull(app(SovereigntyProfiles::class)->ensureDraft($this->actor()));
    }

    #[Test]
    public function every_governance_write_fails_closed(): void
    {
        $this->undoTheGateFourMigrations();

        $actor = $this->actor();

        foreach ([
            fn () => app(DataProtectionProfiles::class)->saveDraft(['applicable_regime' => 'Singapore PDPA'], $actor),
            fn () => app(DataProtectionProfiles::class)->approve($actor, 'A reason long enough to pass.'),
            fn () => app(SovereigntyProfiles::class)->saveDraft(['storage_geography' => 'sg'], $actor),
            fn () => app(SovereigntyProfiles::class)->approve($actor, 'A reason long enough to pass.'),
            fn () => app(SovereigntyProfiles::class)->beginRevision($actor),
        ] as $index => $write) {
            try {
                $write();
                $this->fail("Governance write #{$index} succeeded with its table absent. Writes must fail closed.");
            } catch (GovernanceStorageNotInitialised $e) {
                $this->assertStringContainsString('Nothing was written', $e->getMessage());
            }
        }
    }

    #[Test]
    public function the_category_register_reports_migration_required_and_not_an_empty_list(): void
    {
        /*
         * The gate 3 defect, applied to gate 4. An empty list here would say
         * "no personal data is categorised", which during a deployment window
         * is not merely unhelpful - it is the opposite of the truth. Personal
         * data was found in 19 of this application's 23 tables.
         */
        $this->undoTheGateFourMigrations();

        $this->assertTrue(app(PersonalDataCatalogue::class)->all($this->actor())->isEmpty());

        $response = $this->actingAs($this->actor())->get(route('admin.governance.personal-data'));

        $response->assertOk();
        $response->assertSee('Migration required');
        $response->assertDontSee('No categories recorded');
    }

    #[Test]
    public function every_governance_read_screen_renders_rather_than_returning_500(): void
    {
        $this->undoTheGateFourMigrations();

        $actor = $this->actor();

        foreach ([
            'admin.governance.data-protection',
            'admin.governance.personal-data',
            'admin.governance.sovereignty',
        ] as $route) {
            $this->actingAs($actor)->get(route($route))->assertOk();
        }
    }

    #[Test]
    public function a_profile_screen_says_not_configured_rather_than_showing_a_default_as_settled(): void
    {
        $this->undoTheGateFourMigrations();

        $response = $this->actingAs($this->actor())->get(route('admin.governance.data-protection'));

        $response->assertOk();
        $response->assertSee('Not Configured');
        $response->assertSee('has not been initialised');
    }

    #[Test]
    public function every_governance_write_route_is_refused_during_the_window(): void
    {
        $this->undoTheGateFourMigrations();

        $actor = $this->actor();

        $this->actingAs($actor)
            ->put(route('admin.governance.data-protection.update'), ['applicable_regime' => 'Singapore PDPA'])
            ->assertRedirect();

        $this->actingAs($actor)
            ->post(route('admin.governance.data-protection.approve'), ['reason' => 'A reason long enough.'])
            ->assertRedirect();

        $this->actingAs($actor)
            ->put(route('admin.governance.sovereignty.update'), ['storage_geography' => 'sg'])
            ->assertRedirect();

        /* And nothing was written. */
        $this->runTheOutstandingMigrations();
        $this->assertNull(app(DataProtectionProfiles::class)->draft());
        $this->assertNull(app(DataProtectionProfiles::class)->inForce());
    }

    #[Test]
    public function the_organisation_profile_screen_survives_the_window(): void
    {
        /*
         * ADM-002 is an EXISTING screen that this batch changes, which makes it
         * the one place gate 4 could break something already live. It reads
         * four columns that do not exist during the window.
         *
         * It renders, and it renders the honest thing: the privacy contact
         * reads as incomplete, because with no column there is certainly no
         * contact recorded.
         */
        $this->undoTheGateFourMigrations();

        $response = $this->actingAs($this->actor())->get(route('admin.organisation'));

        $response->assertOk();
        $response->assertSee('The privacy contact is incomplete');
    }

    #[Test]
    public function the_r1_4b_screens_survive_the_window_too(): void
    {
        /*
         * Sovereignty exceptions and retention are register screens, so they
         * report Migration required rather than an empty list. An empty
         * exceptions list during a deployment window would say "the approved
         * position applies without exception", which is a reassuring claim the
         * screen cannot actually make.
         */
        $this->undoTheGateFourMigrations();

        $actor = $this->actor();

        foreach ([
            'admin.governance.exceptions',
            'admin.governance.retention',
        ] as $route) {
            $response = $this->actingAs($actor)->get(route($route));

            $response->assertOk();
            $response->assertSee('Migration required');
        }
    }

    #[Test]
    public function the_exceptions_screen_does_not_claim_there_are_no_exceptions(): void
    {
        /* The specific false-reassurance this screen could give. */
        $this->undoTheGateFourMigrations();

        $this->actingAs($this->actor())
            ->get(route('admin.governance.exceptions'))
            ->assertOk()
            ->assertDontSee('No exceptions have been requested')
            ->assertDontSee('applies without exception');
    }

    #[Test]
    public function the_retention_screen_never_stops_saying_it_deletes_nothing(): void
    {
        /*
         * The sentence that stops a retention table reading as protection has
         * to be there in EVERY state, including the one where the table does
         * not exist - otherwise the only time it is missing is the only time
         * somebody might assume the sweep already ran.
         */
        $this->undoTheGateFourMigrations();

        $this->actingAs($this->actor())
            ->get(route('admin.governance.retention'))
            ->assertOk()
            ->assertSee('does not delete anything');
    }

    #[Test]
    public function the_audit_log_works_with_every_gate_four_table_absent(): void
    {
        /*
         * `audit_events` has existed since gate 1, so ADM-013 has no deployment
         * window of its own - and it must not acquire one by depending on a
         * gate 4 table. The two indexes R1.4b adds make it fast; their absence
         * would make it slow, never broken.
         */
        $this->undoTheGateFourMigrations();

        $this->actingAs($this->actor())
            ->get(route('admin.governance.audit'))
            ->assertOk();
    }

    #[Test]
    public function every_r1_4b_write_is_refused_during_the_window(): void
    {
        $this->undoTheGateFourMigrations();

        $actor = $this->actor();

        $this->actingAs($actor)
            ->post(route('admin.governance.exceptions.store'), [
                'title' => 'Should not be written',
                'justification' => 'A justification long enough to pass validation on its own.',
                'aspect' => 'backup',
                'ends_on' => now()->addDays(30)->toDateString(),
            ])
            ->assertRedirect();

        $this->runTheOutstandingMigrations();

        $this->assertSame(0, SovereigntyException::query()->count());
        $this->assertSame(0, RetentionPolicy::query()->count());
    }

    #[Test]
    public function the_full_round_trip_leaves_the_application_working(): void
    {
        /* down -> safe -> up -> works. The sequence a real deployment plus a
         * later migration actually follows. */
        $this->undoTheGateFourMigrations();
        $this->get('/sign-in')->assertOk();

        $this->runTheOutstandingMigrations();

        $this->assertTrue(app(GovernanceStorage::class)->isReady());

        $actor = $this->actor();
        $draft = app(DataProtectionProfiles::class)->saveDraft(['applicable_regime' => 'Singapore PDPA'], $actor);

        $this->assertNotNull($draft->getKey());
        $this->actingAs($actor)->get(route('admin.governance.data-protection'))->assertOk();
    }
}
