<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Enums\Role;
use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Identity\Models\Organisation;
use App\Modules\Identity\Support\OrganisationContext;
use App\Modules\Security\Enums\ConcurrentSessionPolicy;
use App\Modules\Security\Support\Reauthentication;
use App\Modules\Security\Support\SecurityCapabilities;
use App\Modules\Security\Support\SessionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ControlsSecurityPolicy;
use Tests\TestCase;

/**
 * ADM-010 Session Policy.
 *
 * Two things are being proved. First, that the timeouts are ENFORCED rather
 * than stored - `config('session.lifetime')` is read from the server
 * environment at boot and cannot express two separate limits, which is why
 * `EnforceSessionPolicy` exists at all. Second, that the driver-dependent
 * controls behave honestly under BOTH drivers: available and working under
 * `database`, and reported unavailable rather than silently doing nothing under
 * `file`. Decision D3.
 */
class SessionPolicyTest extends TestCase
{
    use ControlsSecurityPolicy, RefreshDatabase;

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

    /* ---- Idle and maximum ---------------------------------------------- */

    #[Test]
    public function a_session_idle_beyond_the_policy_is_ended(): void
    {
        $this->withSecurityPolicy('activity.idle_minutes', 30);

        $admin = $this->admin();

        $this->actingAs($admin)->withSession([
            'security.session_started_at' => Carbon::now()->utc()->subMinutes(40)->toIso8601String(),
            'security.session_last_seen_at' => Carbon::now()->utc()->subMinutes(35)->toIso8601String(),
        ])->get(route('admin.security.sessions'))->assertRedirect(route('sign-in'));

        $this->assertGuest();

        $event = AuditEvent::query()->where('action', 'auth.session.expired')->firstOrFail();
        $this->assertSame('idle', $event->after_summary['rule']);
        $this->assertSame(30, $event->after_summary['limit_minutes']);
    }

    #[Test]
    public function a_busy_session_is_still_ended_at_the_maximum_duration(): void
    {
        // The limit idle timeout cannot catch: a session that never goes idle
        // because something keeps it warm is precisely the shape of a session
        // somebody else is using.
        $this->withSecurityPolicy('activity.idle_minutes', 120);
        $this->withSecurityPolicy('activity.maximum_minutes', 60);

        $admin = $this->admin();

        $this->actingAs($admin)->withSession([
            'security.session_started_at' => Carbon::now()->utc()->subMinutes(90)->toIso8601String(),
            'security.session_last_seen_at' => Carbon::now()->utc()->subMinute()->toIso8601String(),
        ])->get(route('admin.security.sessions'))->assertRedirect(route('sign-in'));

        $this->assertGuest();

        $event = AuditEvent::query()->where('action', 'auth.session.expired')->firstOrFail();
        $this->assertSame('maximum', $event->after_summary['rule']);
    }

    #[Test]
    public function a_session_inside_both_limits_carries_on(): void
    {
        // The guard must end a stale session, not every session.
        $admin = $this->admin();

        $this->actingAs($admin)->withSession([
            'security.session_started_at' => Carbon::now()->utc()->subMinutes(5)->toIso8601String(),
            'security.session_last_seen_at' => Carbon::now()->utc()->subMinute()->toIso8601String(),
        ])->get(route('admin.security.sessions'))->assertOk();

        $this->assertAuthenticated();
    }

    #[Test]
    public function the_person_is_told_why_they_were_signed_out(): void
    {
        // "Please sign in" with no reason is indistinguishable from a fault,
        // and a fault is what gets reported as a bug.
        $this->withSecurityPolicy('activity.idle_minutes', 15);

        $admin = $this->admin();

        $this->actingAs($admin)->withSession([
            'security.session_started_at' => Carbon::now()->utc()->subMinutes(40)->toIso8601String(),
            'security.session_last_seen_at' => Carbon::now()->utc()->subMinutes(30)->toIso8601String(),
        ])->get(route('admin.security.sessions'));

        $this->get('/sign-in')->assertOk()->assertSee('15 minutes without activity');
    }

    /* ---- Driver-dependent behaviour ------------------------------------ */

    #[Test]
    public function the_file_driver_reports_session_enumeration_as_unavailable(): void
    {
        config(['session.driver' => 'file']);

        $capabilities = app(SecurityCapabilities::class);

        $this->assertFalse($capabilities->canEnumerateSessions());
        $this->assertStringContainsString('"file"', (string) $capabilities->sessionEnumerationBlocker());
        $this->assertStringContainsString('database', (string) $capabilities->sessionEnumerationBlocker());
    }

    #[Test]
    public function an_unknown_driver_is_reported_unavailable_rather_than_assumed_capable(): void
    {
        // Fails closed: claiming an untested capability is how a control gets
        // believed and then found missing.
        config(['session.driver' => 'array']);

        $this->assertFalse(app(SecurityCapabilities::class)->canEnumerateSessions());
    }

    #[Test]
    public function the_database_driver_reports_session_enumeration_as_available(): void
    {
        config(['session.driver' => 'database']);

        $this->assertTrue(app(SecurityCapabilities::class)->canEnumerateSessions());
        $this->assertNull(app(SecurityCapabilities::class)->sessionEnumerationBlocker());
    }

    #[Test]
    public function revocation_returns_null_rather_than_zero_when_the_driver_cannot_do_it(): void
    {
        // Null rather than zero, because "we ended none" and "we cannot end
        // any" are different answers, and a caller that treats the second as
        // the first reports success for something that did not happen.
        config(['session.driver' => 'file']);

        $admin = $this->admin();
        $this->actingAs($admin);

        $this->assertNull(app(SessionRegistry::class)->revokeAllFor($admin));

        $denial = AuditEvent::query()->where('action', 'security.sessions.revoked')->firstOrFail();
        $this->assertSame('denied', $denial->outcome->value);
    }

    #[Test]
    public function revocation_ends_every_session_under_the_database_driver(): void
    {
        config(['session.driver' => 'database']);

        $admin = $this->admin();
        $victim = $this->admin('victim@example.test');
        $this->actingAs($admin);

        foreach (['session-one', 'session-two'] as $id) {
            DB::table('sessions')->insert([
                'id' => $id,
                'user_id' => $victim->getKey(),
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Test',
                'payload' => '',
                'last_activity' => time(),
            ]);
        }

        $this->assertSame(2, app(SessionRegistry::class)->revokeAllFor($victim));
        $this->assertSame(0, DB::table('sessions')->where('user_id', $victim->getKey())->count());

        $event = AuditEvent::query()
            ->where('action', 'security.sessions.revoked')
            ->where('outcome', 'succeeded')
            ->firstOrFail();

        // `ended_count` rather than `sessions_ended`: a key containing
        // "session" is replaced by the audit redactor, which is exactly how
        // this was found. SEC-DEC-044.
        $this->assertSame(2, $event->after_summary['ended_count']);
    }

    #[Test]
    public function revocation_is_refused_when_policy_turns_it_off_even_on_a_capable_driver(): void
    {
        config(['session.driver' => 'database']);
        $this->withSecurityPolicy('activity.revocation_enabled', false);

        $admin = $this->admin();
        $victim = $this->admin('victim@example.test');
        $this->actingAs($admin);

        DB::table('sessions')->insert([
            'id' => 'session-one',
            'user_id' => $victim->getKey(),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test',
            'payload' => '',
            'last_activity' => time(),
        ]);

        $this->assertNull(app(SessionRegistry::class)->revokeAllFor($victim));
        $this->assertSame(1, DB::table('sessions')->where('user_id', $victim->getKey())->count());
    }

    #[Test]
    public function the_concurrency_limit_ends_the_oldest_sessions_rather_than_refusing_the_new_one(): void
    {
        // Refusing would mean somebody whose old session is stuck on a machine
        // they no longer have can never sign in again, which turns a policy
        // into a lockout.
        config(['session.driver' => 'database']);
        $this->withSecurityPolicy('activity.concurrent_policy', ConcurrentSessionPolicy::Single->value);

        $admin = $this->admin();
        $this->actingAs($admin);

        foreach ([['old', 100], ['older', 50]] as [$id, $activity]) {
            DB::table('sessions')->insert([
                'id' => $id,
                'user_id' => $admin->getKey(),
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Test',
                'payload' => '',
                'last_activity' => $activity,
            ]);
        }

        $ended = app(SessionRegistry::class)->applyConcurrencyLimit($admin, 'the-new-one');

        $this->assertSame(2, $ended);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $admin->getKey())->count());
    }

    #[Test]
    public function the_concurrency_limit_does_nothing_under_a_driver_that_cannot_enumerate(): void
    {
        config(['session.driver' => 'file']);
        $this->withSecurityPolicy('activity.concurrent_policy', ConcurrentSessionPolicy::Single->value);

        $admin = $this->admin();
        $this->actingAs($admin);

        $this->assertSame(0, app(SessionRegistry::class)->applyConcurrencyLimit($admin, 'the-new-one'));
    }

    #[Test]
    public function the_screen_says_plainly_what_this_driver_cannot_do_and_offers_no_revocation_action(): void
    {
        // Gate 3 rule 10. A greyed-out button is still a button, and somebody
        // will eventually believe they pressed it.
        config(['session.driver' => 'file']);

        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('admin.security.sessions'))
            ->assertOk()
            ->assertSee('Some controls on this screen cannot be applied here')
            ->assertSee('Sessions cannot be listed on this deployment')
            ->assertDontSee('End all sessions');
    }

    #[Test]
    public function the_revocation_route_refuses_independently_of_the_screen(): void
    {
        config(['session.driver' => 'file']);

        $admin = $this->admin();
        $victim = $this->admin('victim@example.test');

        $this->actingAs($admin)
            ->withSession($this->confirmedIdentity())
            ->from(route('admin.security.sessions'))
            ->post(route('admin.security.sessions.revoke', $victim))
            ->assertSessionHasErrors('sessions');
    }

    #[Test]
    public function the_revocation_route_refuses_a_cross_organisation_subject_with_a_404(): void
    {
        // SEC-DEC-033 and SEC-DEC-034: `users` carries no global organisation
        // scope, so every path acting on an account has to ask the boundary
        // explicitly, and the answer is 404 rather than 403.
        config(['session.driver' => 'database']);

        $ours = app(OrganisationContext::class)->require();
        $admin = $this->admin();

        $other = Organisation::query()->forceCreate([
            'code' => 'OTHER', 'name' => 'Other Customer', 'status' => 'active', 'version' => 1,
        ]);

        $outsider = User::query()->create(['name' => 'Outsider', 'email' => 'outsider@example.test']);
        $outsider->forceFill(['role' => Role::Viewer, 'organisation_id' => $other->getKey()])->save();

        app(OrganisationContext::class)->forget();
        app(OrganisationContext::class)->bind($ours);

        $this->actingAs($admin)
            ->withSession($this->confirmedIdentity())
            ->post(route('admin.security.sessions.revoke', $outsider))
            ->assertNotFound();
    }

    /* ---- Re-authentication --------------------------------------------- */

    #[Test]
    public function a_critical_action_demands_a_recent_confirmation(): void
    {
        $admin = $this->admin();
        $subject = $this->admin('subject@example.test');

        // No confirmation in the session at all.
        $this->actingAs($admin)
            ->post(route('admin.users.tier', $subject), ['role' => Role::Viewer->value])
            ->assertRedirect(route('reauthenticate'));
    }

    #[Test]
    public function an_ordinary_action_does_not(): void
    {
        // The control must apply to critical actions and not to everything, or
        // it becomes a prompt people click through without reading.
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.security.sessions'))->assertOk();
    }

    #[Test]
    public function a_confirmation_expires_after_the_configured_window(): void
    {
        $this->withSecurityPolicy('activity.confirmation_valid_minutes', 5);

        $admin = $this->admin();
        $subject = $this->admin('subject@example.test');

        $this->actingAs($admin)
            ->withSession([Reauthentication::CONFIRMED_AT => Carbon::now()->utc()->subMinutes(10)->toIso8601String()])
            ->post(route('admin.users.tier', $subject), ['role' => Role::Viewer->value])
            ->assertRedirect(route('reauthenticate'));
    }

    #[Test]
    public function turning_confirmations_off_lets_a_critical_action_through(): void
    {
        $this->withSecurityPolicy('activity.confirm_critical_actions', false);

        $admin = $this->admin();
        $subject = $this->admin('subject@example.test');

        $this->actingAs($admin)
            ->post(route('admin.users.tier', $subject), ['role' => Role::Viewer->value])
            ->assertRedirect(route('admin.users.show', $subject));
    }

    #[Test]
    public function the_confirmation_screen_accepts_the_right_password_and_refuses_the_wrong_one(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('reauthenticate.confirm'), ['password' => 'wrong'])
            ->assertSessionHasErrors('form');

        $this->assertFalse(app(Reauthentication::class)->isFresh());

        $this->actingAs($admin)
            ->post(route('reauthenticate.confirm'), ['password' => 'correct-horse-battery'])
            ->assertRedirect(route('home'));

        $event = AuditEvent::query()->where('action', 'auth.reauthentication.succeeded')->firstOrFail();
        $this->assertSame('Security', $event->module);
    }

    #[Test]
    public function a_failed_confirmation_is_audited(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('reauthenticate.confirm'), ['password' => 'wrong']);

        $event = AuditEvent::query()->where('action', 'auth.reauthentication.failed')->firstOrFail();
        $this->assertSame('denied', $event->outcome->value);
    }

    #[Test]
    public function the_confirmation_screen_sends_a_federated_account_to_entra_instead_of_asking_for_a_password(): void
    {
        // A federated account has no password here, and inventing one would be
        // exactly the credential this application is designed not to hold.
        $admin = $this->admin('federated@example.test');
        $admin->forceFill(['authentication_source' => 'entra', 'password' => null])->save();

        $this->actingAs($admin->refresh())
            ->withSession(['reauthenticate.action' => 'tier_change'])
            ->get(route('reauthenticate'))
            ->assertOk()
            ->assertSee('Confirm with Microsoft')
            ->assertDontSee('name="password"', false);
    }

    #[Test]
    public function the_confirmation_screen_names_the_action_being_confirmed(): void
    {
        // "Confirm your identity" with no reason trains people to type their
        // password whenever they are asked.
        $admin = $this->admin();

        $this->actingAs($admin)
            ->withSession(['reauthenticate.action' => 'security_policy_change'])
            ->get(route('reauthenticate'))
            ->assertOk()
            ->assertSee('Changing a security policy');
    }

    #[Test]
    public function the_confirmation_screen_only_returns_somebody_to_a_url_on_this_host(): void
    {
        // An open redirect on the one page a person is most primed to trust.
        $admin = $this->admin();

        $this->actingAs($admin)
            ->withSession(['reauthenticate.intended' => 'https://evil.test/phish'])
            ->post(route('reauthenticate.confirm'), ['password' => 'correct-horse-battery'])
            ->assertRedirect(route('home'));
    }
}
