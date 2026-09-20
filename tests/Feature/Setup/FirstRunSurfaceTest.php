<?php

declare(strict_types=1);

namespace Tests\Feature\Setup;

use App\Modules\Access\Models\RoleAssignment;
use App\Modules\Access\Support\RoleCode;
use App\Modules\Platform\Models\User;
use App\Modules\Platform\Models\UserStatus;
use App\Modules\Platform\Setup\Bootstrap\BootstrapAuthenticator;
use App\Modules\Platform\Setup\Bootstrap\Models\BootstrapAdministrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * THE FIRST-RUN SURFACE, AS A BROWSER REACHES IT.
 *
 * B3, B4, B7 and the route-level half of B1. The architecture guards prove
 * there is no path; these prove the paths that DO exist behave.
 */
final class FirstRunSurfaceTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'the-operator-typed-this-once';

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('integration-test:identity:');
    }

    private function givenALocalAdministrator(): void
    {
        BootstrapAdministrator::query()->create([
            'singleton' => BootstrapAdministrator::SINGLETON,
            'email' => 'setup@example.test',
            'password_hash' => Hash::make(self::PASSWORD),
        ]);
    }

    private function givenASystemAdministratorExists(): void
    {
        $user = User::query()->create([
            'provider' => 'microsoft',
            'external_subject' => 'oid-admin',
            'tenant_id' => 'tenant-1',
            'email' => 'admin@example.test',
            'display_name' => 'The Administrator',
            'status' => UserStatus::Active,
        ]);

        RoleAssignment::query()->create([
            'user_id' => $user->id,
            'organisation_id' => null,
            'role_code' => RoleCode::SystemAdministrator,
            'assigned_at' => now(),
        ]);
    }

    /** The sign-in screen is reachable while setup is open. */
    public function test_the_sign_in_screen_is_served_while_setup_is_open(): void
    {
        $this->givenALocalAdministrator();

        $this->get('/first-run/sign-in')->assertOk();
    }

    /** Signing in reaches the overview. */
    public function test_signing_in_reaches_the_overview(): void
    {
        $this->givenALocalAdministrator();

        $this->post('/first-run/sign-in', [
            'email' => 'setup@example.test',
            'password' => self::PASSWORD,
        ])->assertRedirect(route('first_run.overview'));

        $this->get('/first-run')->assertOk();
    }

    /**
     * B7. A WRONG PASSWORD AND AN UNKNOWN ADDRESS ARE INDISTINGUISHABLE.
     *
     * Mutation: return a different message for an unknown address. This screen
     * would then answer "does this deployment have a setup administrator, and
     * what is their address?" to anyone who asks.
     */
    public function test_b7_every_refusal_reads_the_same(): void
    {
        $this->givenALocalAdministrator();

        $wrongPassword = $this->post('/first-run/sign-in', [
            'email' => 'setup@example.test',
            'password' => 'not-the-password',
        ]);

        $unknownAddress = $this->post('/first-run/sign-in', [
            'email' => 'nobody@example.test',
            'password' => self::PASSWORD,
        ]);

        $wrongPassword->assertSessionHasErrors('email');
        $unknownAddress->assertSessionHasErrors('email');

        $this->assertSame(
            session('errors')?->get('email'),
            session('errors')?->get('email'),
        );

        // The message names neither half.
        $message = (string) (session('errors')?->get('email')[0] ?? '');

        foreach (['password', 'address', 'email address', 'unknown', 'not found', 'exists'] as $tell) {
            $this->assertStringNotContainsString($tell, strtolower($message),
                "The refusal says [{$tell}], which tells a caller which half failed.");
        }
    }

    /**
     * B3 and B4. THE INSTANT A SYSTEM ADMINISTRATOR EXISTS, AN ESTABLISHED
     * BOOTSTRAP SESSION FAILS ON ITS NEXT REQUEST.
     *
     * THE SESSION IS NOT TOUCHED. Production runs FILE sessions, so a shutdown
     * that depended on deleting session files would be defeated by one that
     * happens to remain. The guard re-reads state instead - D-166.
     *
     * Mutation: check the state at sign-in only. The session then stays valid
     * for its full four hours after setup has closed.
     */
    public function test_b3_and_b4_an_established_session_fails_on_its_next_request(): void
    {
        $this->givenALocalAdministrator();

        $this->post('/first-run/sign-in', [
            'email' => 'setup@example.test',
            'password' => self::PASSWORD,
        ]);

        $this->get('/first-run')->assertOk();

        // Nothing about the session changes. Only the world does.
        $this->givenASystemAdministratorExists();

        $this->get('/first-run')->assertRedirect(route('first_run.sign_in'));
        $this->get('/first-run/first-administrator')->assertRedirect(route('first_run.sign_in'));
        $this->get('/first-run/integration/identity')->assertRedirect(route('first_run.sign_in'));
    }

    /** ...and the session key is still there, which is the point of the case. */
    public function test_b4_the_session_key_survives_and_grants_nothing(): void
    {
        $this->givenALocalAdministrator();

        $this->post('/first-run/sign-in', [
            'email' => 'setup@example.test',
            'password' => self::PASSWORD,
        ]);

        $this->assertNotNull(session(BootstrapAuthenticator::SESSION_KEY),
            'The bootstrap session key is absent, so the case below would pass trivially.');

        $this->givenASystemAdministratorExists();

        $this->get('/first-run')->assertRedirect(route('first_run.sign_in'));

        $this->assertNotNull(session(BootstrapAuthenticator::SESSION_KEY),
            'The session key was deleted, so this deployment is relying on removing session state '
            .'rather than on re-reading the world. Production runs file sessions.');
    }

    /** B1. A bootstrap session reaches no console route. */
    public function test_b1_a_bootstrap_session_reaches_no_console_route(): void
    {
        $this->givenALocalAdministrator();

        $this->post('/first-run/sign-in', [
            'email' => 'setup@example.test',
            'password' => self::PASSWORD,
        ]);

        foreach ([
            '/console',
            '/console/integrations',
            '/console/system-health',
            '/console/identity',
            '/console/people/users',
        ] as $uri) {
            $response = $this->get($uri);

            $this->assertNotSame(200, $response->getStatusCode(),
                "A bootstrap session reached [{$uri}].");
        }
    }

    /** Every First-Run screen requires the bootstrap session. */
    public function test_every_setup_screen_requires_a_bootstrap_session(): void
    {
        $this->givenALocalAdministrator();

        foreach ([
            '/first-run',
            '/first-run/first-administrator',
            '/first-run/complete',
            '/first-run/integration/identity',
            '/first-run/integration/email',
            '/first-run/integration/ai',
            '/first-run/integration/fabric',
        ] as $uri) {
            $this->get($uri)->assertRedirect(route('first_run.sign_in'));
        }
    }

    /** Sign-out ends the session. */
    public function test_signing_out_ends_the_session(): void
    {
        $this->givenALocalAdministrator();

        $this->post('/first-run/sign-in', [
            'email' => 'setup@example.test',
            'password' => self::PASSWORD,
        ]);

        $this->post('/first-run/sign-out')->assertRedirect(route('first_run.sign_in'));

        $this->get('/first-run')->assertRedirect(route('first_run.sign_in'));
    }

    /** P1. /up and semantiq:health are unchanged by any of this. */
    public function test_p1_liveness_and_the_health_command_are_unchanged(): void
    {
        $this->get('/up')->assertOk();
        $this->assertSame('ok', trim((string) $this->get('/up')->getContent()));

        $this->artisan('semantiq:health')->assertExitCode(0);
    }
}
