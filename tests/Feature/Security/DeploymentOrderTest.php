<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Enums\Role;
use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Identity\Support\OrganisationContext;
use App\Modules\Security\Enums\SecurityStatus;
use App\Modules\Security\Exceptions\SecurityStorageNotInitialised;
use App\Modules\Security\Http\Middleware\SecurityHeaders;
use App\Modules\Security\Support\SecurityPolicies;
use App\Modules\Security\Support\SecurityPosture;
use App\Modules\Security\Support\SecurityStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Deployment order: the application must survive its own release.
 *
 * THE PROBLEM, and it was measured rather than imagined. The deploy workflow
 * ships code and does NOT run migrations - deliberately, because a deployment
 * that migrates a production database unattended is a deployment that can lose
 * data. That leaves a window on every release that adds a table:
 *
 *     code deployed -> migration not yet run -> the new tables do not exist
 *
 * Gate 3's middleware runs on the WEB stack, not only on `/admin/security`.
 * `SecurityHeaders` reads a policy on every single response. Before the fix,
 * `GET /sign-in` returned **500** with these tables absent - the whole site
 * down, sign-in included, so nobody could get in to notice.
 *
 * WHAT THESE TESTS HOLD IN PLACE:
 *
 *   READS fall back to the catalogue default, which is not a compromise: with
 *   no table there can be no override, so the default IS the value in force.
 *
 *   WRITES FAIL CLOSED. Accepting one and discarding it would tell somebody
 *   their security policy had changed when nothing had.
 *
 *   NOTHING INVENTS AN EMPTY STATE. "No references recorded" and "we cannot
 *   tell you what is recorded" are different facts and never share a screen.
 *
 * The detection is a SCHEMA QUESTION, never a caught database exception. A
 * broken connection, a permissions problem or a corrupt table must still fail
 * loudly rather than be reported as "everything is fine, using defaults".
 */
class DeploymentOrderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Drop the gate 3 tables, as the deployment window leaves them.
     *
     * Order matters: `secret_references` carries foreign keys, so it goes
     * first. The readiness cache is cleared because it is a per-request
     * memo and a test spans several.
     */
    private function undoTheGateThreeMigrations(): void
    {
        Schema::dropIfExists('secret_references');
        Schema::dropIfExists('security_policies');

        /*
         * The `migrations` table must forget them too, or this is not the state
         * a real deployment is in. On the server these two rows have never been
         * written; dropping only the tables would leave the ledger claiming the
         * work was done, and `migrate` would find nothing pending - which is
         * how the round-trip test failed before this line existed.
         */
        DB::table('migrations')->where('migration', 'like', '2026_08_26_%')->delete();

        /* Both memos, because both are request-scoped singletons and a test
         * spans several requests. In production a request is a fresh container
         * and the schema cannot change halfway through one. */
        app(SecurityStorage::class)->forget();
        app(SecurityPolicies::class)->forget();
    }

    /** Run the outstanding migrations, as an administrator would on the server. */
    private function runTheOutstandingMigrations(): void
    {
        Artisan::call('migrate', ['--force' => true]);

        app(SecurityStorage::class)->forget();
        app(SecurityPolicies::class)->forget();
    }

    /**
     * Every field one policy screen renders, as the form posts them.
     *
     * Built from the catalogue rather than typed out, so adding a policy does
     * not silently turn this into a partial post that fails validation for the
     * wrong reason.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function wholeScreen(string $screen, array $overrides = []): array
    {
        $payload = [];

        foreach (app(SecurityPolicies::class)->forScreen($screen) as $key => $definition) {
            $value = $overrides[$key] ?? app(SecurityPolicies::class)->get($key);

            /* An unchecked box posts nothing at all, which is what the request
             * normalises back to false. */
            if (is_bool($value)) {
                if ($value) {
                    $payload[str_replace('.', '__', $key)] = '1';
                }

                continue;
            }

            $payload[str_replace('.', '__', $key)] = $value;
        }

        return $payload;
    }

    private function admin(string $email = 'ada@example.test'): User
    {
        $user = User::query()->create([
            'name' => 'Ada Admin',
            'email' => $email,
            'password' => Hash::make('correct-horse-battery'),
        ]);

        $user->forceFill([
            'role' => Role::SystemAdmin,
            'authentication_source' => 'local',
            'organisation_id' => app(OrganisationContext::class)->require()->getKey(),
        ])->save();

        return $user->refresh();
    }

    /* ---- 1. Pre-migration application behaviour ------------------------- */

    #[Test]
    public function the_sign_in_screen_still_works_with_the_gate_three_tables_absent(): void
    {
        // The one that matters most. Before the fix this returned 500, which
        // means nobody could sign in to discover the problem.
        $this->undoTheGateThreeMigrations();

        $this->get('/sign-in')->assertOk()->assertSee('Sign in');
    }

    #[Test]
    public function a_guest_hitting_a_protected_page_still_gets_the_normal_redirect(): void
    {
        $this->undoTheGateThreeMigrations();

        $this->get('/')->assertRedirect(route('sign-in'));
        $this->get(route('admin.users'))->assertRedirect(route('sign-in'));
    }

    #[Test]
    public function somebody_can_still_actually_sign_in(): void
    {
        // Rendering the screen is not the same as the credential path working:
        // `AuthenticationGuard` reads four policies to decide who it admits.
        $this->undoTheGateThreeMigrations();
        $this->admin();

        $this->post('/sign-in', [
            'email' => 'ada@example.test',
            'password' => 'correct-horse-battery',
        ])->assertRedirect('/');

        $this->assertAuthenticated();
    }

    #[Test]
    public function ordinary_existing_pages_do_not_fail(): void
    {
        // Every gate 1 and gate 2 screen goes through the same middleware
        // stack, so a broken policy read takes all of them down too.
        $this->undoTheGateThreeMigrations();
        $admin = $this->admin();

        foreach ([
            'home',
            'admin.overview',
            'admin.users',
            'admin.organisation',
            'admin.roles',
            'admin.permissions',
            'admin.access-reviews',
            'admin.system.diagnostics',
            'admin.system.feature-flags',
        ] as $name) {
            $this->actingAs($admin)->get(route($name))->assertOk($name.' failed');
        }
    }

    #[Test]
    public function the_security_headers_fall_back_to_the_catalogue_defaults(): void
    {
        // Not "no headers". The secure defaults in code remain authoritative,
        // so a release window is not a window with the headers switched off.
        $this->undoTheGateThreeMigrations();

        $response = $this->get('/sign-in')->assertOk();

        foreach (SecurityHeaders::BASE_HEADERS as $header => $value) {
            $this->assertSame($value, $response->headers->get($header), $header.' was not sent.');
        }

        // And the defaults are the SAFE ones: report-only CSP, no HSTS.
        $this->assertNotNull($response->headers->get('Content-Security-Policy-Report-Only'));
        $this->assertNull($response->headers->get('Strict-Transport-Security'));
    }

    #[Test]
    public function the_session_policy_falls_back_to_the_catalogue_defaults(): void
    {
        $this->undoTheGateThreeMigrations();

        $policies = app(SecurityPolicies::class);

        $this->assertSame(120, $policies->number('activity.idle_minutes'));
        $this->assertSame(720, $policies->number('activity.maximum_minutes'));
        $this->assertTrue($policies->enabled('activity.confirm_critical_actions'));
    }

    #[Test]
    public function the_session_middleware_still_ends_a_stale_session(): void
    {
        // The fallback must keep the control WORKING, not merely keep it from
        // crashing. A release window that silently suspends the idle timeout
        // would be a security regression dressed as resilience.
        $this->undoTheGateThreeMigrations();
        $admin = $this->admin();

        $this->actingAs($admin)->withSession([
            'security.session_started_at' => Carbon::now()->utc()->subHours(9)->toIso8601String(),
            'security.session_last_seen_at' => Carbon::now()->utc()->subHours(8)->toIso8601String(),
        ])->get(route('admin.users'))->assertRedirect(route('sign-in'));

        $this->assertGuest();
    }

    #[Test]
    public function a_critical_action_is_still_confirmed(): void
    {
        $this->undoTheGateThreeMigrations();
        $admin = $this->admin();
        $subject = $this->admin('subject@example.test');

        $this->actingAs($admin)
            ->post(route('admin.users.tier', $subject), ['role' => Role::Viewer->value])
            ->assertRedirect(route('reauthenticate'));
    }

    /* ---- 2. The security screens, pre-migration ------------------------- */

    #[Test]
    public function the_security_overview_handles_schema_not_ready_without_a_500(): void
    {
        $this->undoTheGateThreeMigrations();

        $this->actingAs($this->admin())
            ->get(route('admin.security.overview'))
            ->assertOk()
            ->assertSee('Security storage has not been initialised');
    }

    #[Test]
    public function the_overview_reports_secrets_as_not_verified_rather_than_as_an_empty_store(): void
    {
        // The distinction the whole fix turns on. "No references recorded" says
        // the store exists and is empty - a different and far more comforting
        // fact than "we cannot tell you".
        $this->undoTheGateThreeMigrations();

        $secrets = app(SecurityPosture::class)->secrets();

        $this->assertSame(SecurityStatus::NotVerified, $secrets['status']);
        $this->assertStringContainsString('has not been created yet', $secrets['detail']);
        $this->assertStringNotContainsStringIgnoringCase('no secret references recorded', $secrets['detail']);
    }

    #[Test]
    public function the_overview_lists_no_expiring_or_rotation_due_references(): void
    {
        $this->undoTheGateThreeMigrations();

        $posture = app(SecurityPosture::class);

        $this->assertTrue($posture->expiringReferences()->isEmpty());
        $this->assertTrue($posture->rotationDueReferences()->isEmpty());
    }

    #[Test]
    public function the_expiring_credentials_panel_does_not_show_a_green_nothing_expiring(): void
    {
        // The empty list above must NOT be rendered as reassurance. "Nothing
        // expiring in the next 30 days" beside a tick would be a false healthy
        // about the one thing on that page that can take an integration down,
        // at exactly the moment the screen cannot see the data. Caught by
        // looking at the rendered page, not by a test.
        $this->undoTheGateThreeMigrations();

        $this->actingAs($this->admin())
            ->get(route('admin.security.overview'))
            ->assertOk()
            ->assertSee('Cannot be checked yet')
            ->assertDontSee('Nothing expiring in the next');
    }

    #[Test]
    public function each_policy_screen_renders_and_says_it_cannot_be_changed(): void
    {
        $this->undoTheGateThreeMigrations();
        $admin = $this->admin();

        foreach ([
            'admin.security.authentication',
            'admin.security.sessions',
            'admin.security.api',
        ] as $name) {
            $this->actingAs($admin)->get(route($name))
                ->assertOk($name.' failed')
                ->assertSee('These values cannot be changed yet')
                ->assertDontSee('Save changes');
        }
    }

    #[Test]
    public function the_secret_reference_index_shows_migration_required_and_not_an_empty_list(): void
    {
        $this->undoTheGateThreeMigrations();

        $this->actingAs($this->admin())
            ->get(route('admin.security.secrets'))
            ->assertOk()
            ->assertSee('Migration required')
            ->assertSee('It is not empty - it does not exist.')
            ->assertDontSee('No secret references yet')
            ->assertDontSee('New reference');
    }

    /* ---- 3. Writes fail closed ------------------------------------------ */

    #[Test]
    public function a_policy_write_is_refused_with_a_controlled_message_and_changes_nothing(): void
    {
        $this->undoTheGateThreeMigrations();
        $admin = $this->admin();
        $this->actingAs($admin);

        try {
            app(SecurityPolicies::class)->set('activity.idle_minutes', 30, $admin, 'Trying during a deploy.');
            $this->fail('A policy write was accepted with no table to write it to.');
        } catch (SecurityStorageNotInitialised $exception) {
            $this->assertStringContainsString('Security storage has not been initialised', $exception->getMessage());
            $this->assertStringContainsString('Nothing has been changed', $exception->getMessage());

            // No SQL, no driver text, no table name in the sentence a person reads.
            $this->assertStringNotContainsString('SQLSTATE', $exception->getMessage());
            $this->assertStringNotContainsString('security_policies', $exception->getMessage());
        }

        // The value did not move, and the refusal was not audited as a change.
        $this->assertSame(120, app(SecurityPolicies::class)->number('activity.idle_minutes'));
        $this->assertSame(0, AuditEvent::query()->where('action', 'security.policy.updated')->count());
    }

    #[Test]
    public function the_policy_form_shows_the_controlled_message_rather_than_a_database_error(): void
    {
        $this->undoTheGateThreeMigrations();

        $response = $this->actingAs($this->admin())
            ->withSession(['security.identity_confirmed_at' => Carbon::now()->utc()->toIso8601String()])
            ->from(route('admin.security.sessions'))
            ->put(route('admin.security.sessions.update'), [
                /* The whole screen. The form request requires every field it
                 * renders, so a partial post fails validation before it ever
                 * reaches the storage guard - which would prove nothing. */
                'policies' => $this->wholeScreen('sessions', ['activity.idle_minutes' => 30]),
                'reason' => 'Trying during a deploy.',
            ]);

        $response->assertRedirect(route('admin.security.sessions'));
        $response->assertInvalid(['policies' => 'Security storage has not been initialised']);
    }

    #[Test]
    public function every_secret_reference_write_route_is_refused(): void
    {
        $this->undoTheGateThreeMigrations();
        $admin = $this->admin();
        $confirmed = ['security.identity_confirmed_at' => Carbon::now()->utc()->toIso8601String()];

        // A route carrying `{secretReference}` binds its model BEFORE the
        // controller runs, which is why the guard is a middleware. `1` is an id
        // that would be looked up in a table that does not exist.
        foreach ([
            ['get', route('admin.security.secrets.create'), []],
            ['post', route('admin.security.secrets.store'), ['name' => 'Anything']],
            ['get', url('/admin/security/secrets/1'), []],
            ['put', url('/admin/security/secrets/1'), ['name' => 'Anything']],
            ['post', url('/admin/security/secrets/1/retire'), []],
        ] as [$verb, $url, $payload]) {
            $this->actingAs($admin)
                ->withSession($confirmed)
                ->$verb($url, $payload)
                ->assertRedirect(route('admin.security.secrets'));
        }
    }

    /* ---- 4. Migrate up, and the real behaviour returns ------------------ */

    #[Test]
    public function the_full_deployment_sequence_holds_in_both_directions(): void
    {
        // The sequence a release actually goes through, asserted end to end:
        // pre-migration -> up -> gate 3 behaviour -> down -> safe again -> up.
        $admin = $this->admin();

        /* Pre-migration. */
        $this->undoTheGateThreeMigrations();
        $this->get('/sign-in')->assertOk();
        $this->assertFalse(app(SecurityPolicies::class)->storageIsReady());
        $this->assertSame(120, app(SecurityPolicies::class)->number('activity.idle_minutes'));

        /* Migrations up. */
        $this->runTheOutstandingMigrations();
        $this->assertTrue(Schema::hasTable('security_policies'));
        $this->assertTrue(Schema::hasTable('secret_references'));
        $this->assertTrue(app(SecurityPolicies::class)->storageIsReady());

        /* Gate 3 behaviour: a write is accepted and read back. */
        $this->actingAs($admin);
        $this->assertTrue(
            app(SecurityPolicies::class)->set('activity.idle_minutes', 45, $admin, 'Post-migration change.'),
        );
        $this->assertSame(45, app(SecurityPolicies::class)->number('activity.idle_minutes'));

        $this->actingAs($admin)->get(route('admin.security.overview'))->assertOk();
        $this->actingAs($admin)->get(route('admin.security.secrets'))
            ->assertOk()
            ->assertSee('No secret references yet');

        /* `actingAs` persists for the rest of the method, and `/sign-in` is
         * behind the guest middleware - so without this the checks below would
         * follow a redirect to Home and prove nothing. */
        Auth::logout();

        /* Migrations down: the stored override goes with the table, and the
         * catalogue default takes over rather than anything breaking. */
        $this->undoTheGateThreeMigrations();
        $this->get('/sign-in')->assertOk();
        $this->assertSame(120, app(SecurityPolicies::class)->number('activity.idle_minutes'));
        $this->actingAs($admin)->get(route('admin.security.overview'))->assertOk();

        /* And up again. */
        $this->runTheOutstandingMigrations();
        $this->assertTrue(app(SecurityPolicies::class)->storageIsReady());

        /* The overview check above re-authenticated for the rest of the method,
         * and `/sign-in` is behind the guest middleware. */
        Auth::logout();
        $this->get('/sign-in')->assertOk();
    }

    /* ---- 5. The detection itself ---------------------------------------- */

    #[Test]
    public function readiness_is_answered_by_a_schema_question_and_not_by_swallowing_an_error(): void
    {
        // The distinction the requirement turns on. A caught database exception
        // would also swallow a broken connection, a permissions problem or a
        // corrupt table, and report all of them as "everything is fine, using
        // defaults". This asks one specific question and answers only that.
        $storage = app(SecurityStorage::class);

        $this->assertTrue($storage->policiesAreReady());
        $this->assertTrue($storage->secretReferencesAreReady());
        $this->assertTrue($storage->isReady());

        $this->undoTheGateThreeMigrations();

        $this->assertFalse($storage->policiesAreReady());
        $this->assertFalse($storage->secretReferencesAreReady());
        $this->assertFalse($storage->isReady());
    }

    #[Test]
    public function one_table_present_and_the_other_missing_is_handled_independently(): void
    {
        // The two migrations run in order, so there is a moment - and a failed
        // migration leaves a state - where one exists and the other does not.
        Schema::dropIfExists('secret_references');
        app(SecurityStorage::class)->forget();

        $storage = app(SecurityStorage::class);

        $this->assertTrue($storage->policiesAreReady(), 'Policy storage should still be usable.');
        $this->assertFalse($storage->secretReferencesAreReady());
        $this->assertFalse($storage->isReady());

        $admin = $this->admin();
        $this->actingAs($admin);

        // Policy writes still work; secret references are refused.
        $this->assertTrue(
            app(SecurityPolicies::class)->set('activity.idle_minutes', 45, $admin, 'Half-migrated.'),
        );

        $this->actingAs($admin)->get(route('admin.security.secrets'))->assertOk()->assertSee('Migration required');
        $this->actingAs($admin)->get(route('admin.security.overview'))->assertOk();
    }

    #[Test]
    public function the_readiness_check_costs_one_query_per_table_per_request(): void
    {
        // `SecurityHeaders` reads a policy on EVERY response and three other
        // consumers ask the same question. Without the singleton registration
        // and the memo, that is a schema query per consumer per request.
        $storage = app(SecurityStorage::class);

        $this->assertSame($storage, app(SecurityStorage::class), 'SecurityStorage must be a singleton.');
        $this->assertSame(
            app(SecurityPolicies::class),
            app(SecurityPolicies::class),
            'SecurityPolicies must be a singleton.',
        );

        /* Primed first: the FIRST call is the one schema query the design
         * allows. What is being asserted is that the next ones cost nothing. */
        $storage->policiesAreReady();

        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $storage->policiesAreReady();
        $storage->policiesAreReady();
        $storage->policiesAreReady();

        $this->assertSame(0, $queries, 'The readiness answer is memoised, so repeat calls cost nothing.');
    }
}
