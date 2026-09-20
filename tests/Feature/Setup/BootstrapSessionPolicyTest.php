<?php

declare(strict_types=1);

namespace Tests\Feature\Setup;

use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;
use App\Modules\Platform\Setup\Bootstrap\BootstrapAccess;
use App\Modules\Platform\Setup\Bootstrap\BootstrapAuthenticator;
use App\Modules\Platform\Setup\Bootstrap\BootstrapRecovery;
use App\Modules\Platform\Setup\Bootstrap\BootstrapSessionPolicy;
use App\Modules\Platform\Setup\Bootstrap\Models\BootstrapAdministrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * D-165. THE BOOTSTRAP SESSION HAS ITS OWN, SHORTER, POLICY.
 *
 *   30 MINUTES IDLE, 4 HOURS ABSOLUTE.
 *
 * The boundaries are tested on BOTH SIDES. A test that only checked "expired
 * after a long time" would pass against a policy of any length at all, which is
 * the vacuous guard CLAUDE.md section 2 names - and the number in the rule is
 * the entire content of the rule.
 */
final class BootstrapSessionPolicyTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'the-operator-typed-this-once';

    protected function setUp(): void
    {
        parent::setUp();

        BootstrapAdministrator::query()->create([
            'singleton' => BootstrapAdministrator::SINGLETON,
            'email' => 'setup@example.test',
            'password_hash' => Hash::make(self::PASSWORD),
        ]);
    }

    private function signIn(): void
    {
        $this->post('/first-run/sign-in', [
            'email' => 'setup@example.test',
            'password' => self::PASSWORD,
        ])->assertRedirect(route('first_run.overview'));
    }

    /** Both clocks start at authentication, server-side. */
    public function test_both_clocks_start_at_authentication(): void
    {
        $this->signIn();

        $this->assertNotNull(session(BootstrapSessionPolicy::SESSION_AUTHENTICATED_AT));
        $this->assertNotNull(session(BootstrapSessionPolicy::SESSION_LAST_ACTIVITY_AT));
    }

    /** 29 minutes 59 seconds idle is still a valid session. */
    public function test_twenty_nine_minutes_fifty_nine_seconds_idle_is_valid(): void
    {
        $this->signIn();

        $this->travel(29)->minutes();
        $this->travel(59)->seconds();

        $this->get('/first-run')->assertOk();
    }

    /**
     * 30 minutes idle expires.
     *
     * Mutation: remove the idle check from BootstrapSessionPolicy::hasExpired().
     * This case must fail.
     */
    public function test_thirty_minutes_idle_expires(): void
    {
        $this->signIn();

        $this->travel(30)->minutes();

        $this->get('/first-run')->assertRedirect(route('first_run.sign_in'));

        $this->assertNull(session(BootstrapAuthenticator::SESSION_KEY),
            'The session key survived expiry, so a later request could still present it.');
    }

    /** Activity moves the idle clock, so a working session is not cut off. */
    public function test_activity_refreshes_the_idle_clock(): void
    {
        $this->signIn();

        // Three requests, twenty minutes apart. Sixty minutes of elapsed time,
        // never thirty minutes idle.
        foreach ([20, 20, 20] as $minutes) {
            $this->travel($minutes)->minutes();
            $this->get('/first-run')->assertOk();
        }
    }

    /** 3 hours 59 minutes absolute is still valid. */
    public function test_three_hours_fifty_nine_minutes_absolute_is_valid(): void
    {
        $this->signIn();

        // Kept active throughout, so only the absolute clock is in question.
        foreach (range(1, 15) as $ignored) {
            $this->travel(15)->minutes();
            $this->get('/first-run')->assertOk();
        }

        $this->travel(14)->minutes();

        $this->get('/first-run')->assertOk();
    }

    /**
     * 4 HOURS ABSOLUTE EXPIRES EVEN WITH RECENT ACTIVITY.
     *
     * This is the case that tells the two clocks apart: the session is being
     * used continuously and still ends.
     *
     * Mutation: refresh the absolute clock in touch(), or drop the absolute
     * check. Either makes this fail, and either would let a session live
     * indefinitely as long as somebody kept a tab open.
     */
    public function test_four_hours_absolute_expires_despite_continuous_activity(): void
    {
        $this->signIn();

        foreach (range(1, 15) as $ignored) {
            $this->travel(15)->minutes();
            $this->get('/first-run')->assertOk();
        }

        // 3h45m of continuous use so far. One more quarter-hour crosses four.
        $this->travel(15)->minutes();

        $this->get('/first-run')->assertRedirect(route('first_run.sign_in'));
    }

    /** A stale recovery context expires with the session that opened it. */
    public function test_a_recovery_context_expires_with_its_session(): void
    {
        // Close bootstrap so recovery is the only way in, then recover.
        $principal = BootstrapAdministrator::current();
        $this->assertNotNull($principal);
        $principal->disabled_at = now();
        $principal->password_hash = BootstrapAdministrator::UNUSABLE_HASH;
        $principal->save();

        $token = app(BootstrapRecovery::class)->issue('ssh-operator');

        $this->post('/first-run/recover', [
            'token' => $token,
            'password' => 'a-new-recovery-password',
            'password_confirmation' => 'a-new-recovery-password',
        ])->assertRedirect(route('first_run.sign_in'));

        $this->assertTrue(session(BootstrapAccess::RECOVERY_SESSION_KEY));

        $this->post('/first-run/sign-in', [
            'email' => 'setup@example.test',
            'password' => 'a-new-recovery-password',
        ])->assertRedirect(route('first_run.overview'));

        $this->travel(30)->minutes();

        $this->get('/first-run')->assertRedirect(route('first_run.sign_in'));

        $this->assertNull(session(BootstrapAccess::RECOVERY_SESSION_KEY),
            'THE RECOVERY MARKER OUTLIVED THE SESSION THAT OPENED IT. A stale tab would still '
            .'hold an open recovery context after the credential session behind it had expired.');
    }

    /** An unreadable timestamp is treated as expired, not as fine. */
    public function test_an_unreadable_timestamp_expires(): void
    {
        $this->signIn();

        $this->session([BootstrapSessionPolicy::SESSION_LAST_ACTIVITY_AT => 'not a date']);

        $this->get('/first-run')->assertRedirect(route('first_run.sign_in'));
    }

    /** A missing timestamp expires too. */
    public function test_a_missing_timestamp_expires(): void
    {
        $this->signIn();

        $this->session([BootstrapSessionPolicy::SESSION_AUTHENTICATED_AT => null]);

        $this->get('/first-run')->assertRedirect(route('first_run.sign_in'));
    }

    /**
     * THE NORMAL USER SESSION POLICY IS UNCHANGED.
     *
     * Bootstrap has its own, shorter policy precisely so it does not become the
     * deployment's policy. A change here would silently shorten every ordinary
     * administrator's day.
     */
    public function test_the_normal_user_session_policy_is_untouched(): void
    {
        $this->assertSame(12, EnsureSessionIsCurrent::ABSOLUTE_HOURS,
            'The ordinary user absolute lifetime changed. Bootstrap has its own policy so that '
            .'it does not have to borrow, or alter, this one.');

        $this->assertSame(30, BootstrapSessionPolicy::IDLE_MINUTES);
        $this->assertSame(4, BootstrapSessionPolicy::ABSOLUTE_HOURS);

        $this->assertNotSame(
            EnsureSessionIsCurrent::ABSOLUTE_HOURS,
            BootstrapSessionPolicy::ABSOLUTE_HOURS,
            'The two policies are the same number, so nothing would notice if Bootstrap started '
            .'inheriting the ordinary one.',
        );
    }

    /** The clocks are server-side: the browser cannot extend them. */
    public function test_the_clocks_are_server_side(): void
    {
        $this->signIn();

        $authenticatedAt = session(BootstrapSessionPolicy::SESSION_AUTHENTICATED_AT);

        $this->assertIsString($authenticatedAt);
        $this->assertNotNull(Carbon::parse($authenticatedAt));

        // They live in the SESSION, which the server writes. Nothing about them
        // travels in a cookie the browser could edit.
        $response = $this->get('/first-run');

        foreach ($response->headers->getCookies() as $cookie) {
            $this->assertStringNotContainsString('bootstrap.authenticated_at', (string) $cookie->getValue());
            $this->assertStringNotContainsString('bootstrap.last_activity_at', (string) $cookie->getValue());
        }
    }
}
