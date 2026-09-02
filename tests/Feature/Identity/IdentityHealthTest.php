<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Modules\Identity\Health\IdentityHealthCheck;
use App\Modules\Identity\Health\IdentityHealthReport;
use App\Modules\Platform\Health\HealthInspector;
use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;
use App\Modules\Platform\Identity\Microsoft\EntraDiscovery;
use App\Modules\Platform\Models\User;
use App\Modules\Platform\Security\SecurityEventLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Support\EntraTokenFactory;
use Tests\Support\OrganisationFactory;
use Tests\TestCase;

/**
 * SSO Health: the states, the live probe, and the two facts it must not merge.
 */
final class IdentityHealthTest extends TestCase
{
    use RefreshDatabase;

    private EntraTokenFactory $entra;

    private OrganisationFactory $make;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entra = new EntraTokenFactory;
        $this->entra->configure();
        $this->make = new OrganisationFactory;

        // The fixture's redirect URI is a production-shaped URL, so against a
        // test deployment the return-address row is legitimately Degraded. That
        // is correct behaviour and it would mask every other row, so the
        // baseline is a deployment whose configuration MATCHES itself; each case
        // then varies exactly the one thing it is about.
        config(['identity.microsoft.redirect_uri' => route('auth.microsoft.callback')]);

        Cache::flush();
        RateLimiter::clear('identity-health-recheck:1');
    }

    private function check(): IdentityHealthCheck
    {
        return app(IdentityHealthCheck::class);
    }

    private function cacheKey(string $suffix): string
    {
        return 'semantiq:entra:'.EntraTokenFactory::TENANT.':'.$suffix;
    }

    private function givenTrustIsCached(): void
    {
        Cache::put($this->cacheKey('metadata'), [
            'issuer' => $this->entra->issuer(),
            'jwks_uri' => 'https://login.microsoftonline.test/keys',
        ], now()->addHours(24));

        Cache::put($this->cacheKey('jwks'), $this->entra->jwks(), now()->addHours(24));
    }

    private function state(): string
    {
        return $this->check()->report()->state();
    }

    /** @return array<string, string> key => state */
    private function rows(): array
    {
        $rows = [];

        foreach ($this->check()->report()->checks as $row) {
            $rows[$row['key']] = $row['state'];
        }

        return $rows;
    }

    /**
     * H2. A CACHED response keeps identity trust Healthy.
     *
     * Caching for 24 hours is the designed behaviour, not a deficiency, and a
     * warning an administrator cannot act on teaches people to ignore the
     * screen.
     *
     * Mutation: report cached metadata as Degraded.
     */
    public function test_cached_trust_is_healthy_and_never_degraded(): void
    {
        $this->givenTrustIsCached();

        Http::fake(fn () => throw new \RuntimeException('the network must not be touched here'));

        $this->assertSame(IdentityHealthReport::HEALTHY, $this->rows()['identity_trust']);
    }

    /**
     * H3. Rendering the screen probes nothing.
     *
     * The whole point of a live check being an explicit action is that looking
     * at the screen is not one.
     *
     * Mutation: make report() probe.
     */
    public function test_rendering_health_makes_no_outbound_request(): void
    {
        $this->givenTrustIsCached();

        Http::fake();

        $this->check()->report();

        Http::assertNothingSent();
    }

    /**
     * H18. Before any probe, the live-check row is Not checked and turns nothing
     * amber.
     *
     * Mutation: make "never probed" Degraded. Every deployment then sits
     * permanently in warning for a condition that is not a fault.
     */
    public function test_a_never_run_live_check_is_information_and_not_a_warning(): void
    {
        $this->givenTrustIsCached();

        $rows = $this->rows();

        $this->assertSame(IdentityHealthReport::NOT_CHECKED, $rows['microsoft_reachable']);
        $this->assertSame(IdentityHealthReport::HEALTHY, $this->state());
    }

    /**
     * H11. A cached success cannot make a requested probe skip the network.
     *
     * This is the case Revision 1 of the design got wrong. It reasoned that a
     * broken directory is reached on every evaluation anyway - true of one
     * already broken, false of one that breaks AFTER a success is cached. A
     * 24-hour entry would have held that success through an outage, under a
     * button labelled Re-check now.
     *
     * Mutation: route the probe through Cache::remember. The request disappears.
     */
    public function test_a_live_probe_reaches_the_network_even_when_the_cache_is_fresh(): void
    {
        $this->givenTrustIsCached();

        $this->entra->fakeEndpoints();

        $result = app(EntraDiscovery::class)->probe();

        $this->assertTrue($result['ran']);
        $this->assertTrue($result['reachable']);

        /*
         * The DISCOVERY endpoint specifically, not merely "some request".
         *
         * The first version of this asserted that Http::recorded() was
         * non-empty, and a mutation that read metadata from the cache and only
         * fetched the keys sailed through it - the probe still touched the
         * network, so the assertion was satisfied while the thing it exists to
         * prove was not. A probe that trusts a cached discovery response cannot
         * see a directory that went down after it was cached, which is the exact
         * outage this button is for.
         */
        $discoveryCalls = 0;

        foreach (Http::recorded() as [$request]) {
            if (str_contains($request->url(), '.well-known/openid-configuration')) {
                $discoveryCalls++;
            }
        }

        $this->assertSame(
            1,
            $discoveryCalls,
            'The probe did not ask Microsoft for its sign-in settings. A cached success cannot '
            .'stand in for a live check.'
        );
    }

    /**
     * H12. The probe requests no token and starts no authorization.
     *
     * Mutation: add a token request to the probe.
     */
    public function test_a_live_probe_requests_no_token(): void
    {
        $this->entra->fakeEndpoints();

        app(EntraDiscovery::class)->probe();

        foreach (Http::recorded() as [$request]) {
            $this->assertSame('GET', $request->method(), 'The probe made a non-GET request.');
            $this->assertStringNotContainsString('/token', $request->url());
            $this->assertStringNotContainsString('/authorize', $request->url());
        }
    }

    /**
     * H13. A failed probe destroys nothing.
     *
     * Mutation: Cache::forget before the fetch. The cached trust is gone and a
     * diagnostic button has become the thing that breaks sign-in.
     */
    public function test_a_failed_probe_preserves_a_valid_cache(): void
    {
        $this->givenTrustIsCached();

        $metadata = Cache::get($this->cacheKey('metadata'));
        $keys = Cache::get($this->cacheKey('jwks'));

        Http::fake(['*' => Http::response('', 503)]);

        $result = app(EntraDiscovery::class)->probe();

        $this->assertTrue($result['ran']);
        $this->assertFalse($result['reachable']);
        $this->assertSame($metadata, Cache::get($this->cacheKey('metadata')));
        $this->assertSame($keys, Cache::get($this->cacheKey('jwks')));
    }

    /**
     * H14. A successful probe refreshes the cache.
     *
     * Mutation: leave the cache untouched on success.
     */
    public function test_a_successful_probe_refreshes_the_cache(): void
    {
        Cache::put($this->cacheKey('jwks'), $this->entra->jwks(['kid' => 'yesterday']), now()->addHours(24));

        $this->entra->fakeEndpoints();

        app(EntraDiscovery::class)->probe();

        $cached = Cache::get($this->cacheKey('jwks'));

        $this->assertSame(EntraTokenFactory::KID, $cached[0]['kid']);
    }

    /**
     * H15. The lock is PROVIDER-WIDE, not per user.
     *
     * Ten administrators with ten tabs must not become ten requests to
     * Microsoft.
     *
     * Mutation: key the lock by user. The second probe reaches the network.
     */
    public function test_the_probe_lock_holds_across_administrators(): void
    {
        $this->entra->fakeEndpoints();

        $discovery = app(EntraDiscovery::class);

        $first = $discovery->probe();
        $requests = count(Http::recorded());

        $second = $discovery->probe();

        $this->assertTrue($first['ran']);
        $this->assertFalse($second['ran'], 'A second probe ran inside the lock window.');
        $this->assertSame('checked_recently', $second['reason']);
        $this->assertSame($requests, count(Http::recorded()));
    }

    /**
     * H16. A failed probe over a VALID cache is Needs attention, not an outage.
     *
     * And the wording never says sign-in works.
     *
     * Mutation: aggregate a probe failure as Failed.
     */
    public function test_a_failed_probe_with_valid_cache_needs_attention(): void
    {
        $this->givenTrustIsCached();

        Cache::put(IdentityHealthCheck::LAST_PROBE_KEY, [
            'reachable' => false,
            'reason' => 'directory_unreachable',
            'at' => now()->toIso8601String(),
        ], now()->addDay());

        $report = $this->check()->report();

        $this->assertSame(IdentityHealthReport::DEGRADED, $this->rows()['microsoft_reachable']);
        $this->assertSame(IdentityHealthReport::DEGRADED, $report->state());
        $this->assertSame('Needs attention', $report->stateInWords());
        $this->assertStringNotContainsString('Sign-in works', $report->summary());
        $this->assertStringContainsString('may affect sign-in', $report->summary());
    }

    /**
     * H17. A failed probe with NOTHING usable is an outage.
     *
     * Mutation: aggregate it as Needs attention.
     */
    public function test_a_failed_probe_with_no_usable_trust_is_an_outage(): void
    {
        Http::fake(['*' => Http::response('', 503)]);

        Cache::put(IdentityHealthCheck::LAST_PROBE_KEY, [
            'reachable' => false,
            'reason' => 'directory_unreachable',
            'at' => now()->toIso8601String(),
        ], now()->addDay());

        $report = $this->check()->report();

        /*
         * The ROW, not just the aggregate.
         *
         * The first version asserted only the overall state, and a mutation
         * making this row Degraded survived it - because with no usable trust
         * the identity-trust row is Failed anyway and drove the aggregate on its
         * own. The test was passing for a reason unrelated to what it claimed to
         * check, which is the exact failure this project keeps producing.
         */
        $this->assertSame(IdentityHealthReport::FAILED, $this->rows()['microsoft_reachable']);
        $this->assertSame(IdentityHealthReport::FAILED, $report->state());
        $this->assertSame('Sign-in unavailable', $report->stateInWords());
    }

    /**
     * H1. Each check reports its own real cause.
     *
     * Mutation: break each dependency in turn.
     */
    public function test_each_check_fails_for_its_own_cause(): void
    {
        $this->givenTrustIsCached();

        config(['identity.microsoft.client_secret' => '']);
        $this->assertSame(IdentityHealthReport::FAILED, $this->rows()['client_secret']);

        $this->entra->configure();
        config(['identity.microsoft.redirect_uri' => 'https://somewhere-else.test/auth/microsoft/callback']);
        $this->assertSame(IdentityHealthReport::DEGRADED, $this->rows()['return_address']);

        $this->entra->configure();
        config(['session.lifetime' => 120]);
        $this->assertSame(IdentityHealthReport::DEGRADED, $this->rows()['session_policy']);

        config(['session.lifetime' => 60]);
        config(['identity.microsoft.tenant_id' => '']);
        $this->assertSame(IdentityHealthReport::FAILED, $this->rows()['provider_configured']);
    }

    /**
     * H9. Every non-Healthy row carries an action.
     *
     * Mutation: emit a finding with no next step. A warning nobody can act on is
     * noise wearing a warning's clothes.
     */
    public function test_every_non_healthy_row_says_what_to_do(): void
    {
        config(['identity.microsoft.client_secret' => '']);
        Http::fake(['*' => Http::response('', 503)]);

        $checked = 0;

        foreach ($this->check()->report()->checks as $row) {
            if ($row['state'] === IdentityHealthReport::HEALTHY) {
                continue;
            }

            $this->assertNotNull($row['action'], "[{$row['key']}] reports a concern with no action.");
            $this->assertNotSame('', trim((string) $row['action']));
            $checked++;
        }

        $this->assertGreaterThan(2, $checked, 'Too few non-healthy rows were seen to prove anything.');
    }

    /** No finding may reach a person as an exception or an internal reason. */
    public function test_findings_carry_no_internal_reason(): void
    {
        Http::fake(['*' => Http::response('', 503)]);

        foreach ($this->check()->report()->checks as $row) {
            foreach (['discovery_unavailable', 'jwks_unavailable', 'discovery_incomplete', 'Exception', 'Illuminate\\'] as $leak) {
                $this->assertStringNotContainsString($leak, $row['finding'], "[{$row['key']}] leaked [{$leak}].");
                $this->assertStringNotContainsString($leak, (string) $row['action']);
            }
        }
    }

    /**
     * H6. The non-production exemption is an exemption, not the whole
     * behaviour.
     *
     * A check that is trivially green everywhere except the one environment
     * nobody runs tests in is not a check.
     *
     * Mutation: let the exemption apply in production.
     */
    public function test_production_with_no_identity_configuration_fails(): void
    {
        config([
            'app.env' => 'production',
            'identity.microsoft.tenant_id' => '',
            'identity.microsoft.client_id' => '',
            'identity.microsoft.client_secret' => '',
            'identity.microsoft.redirect_uri' => '',
        ]);

        Http::fake(['*' => Http::response('', 503)]);

        $this->assertFalse($this->check()->forInspector()['ok']);
    }

    /**
     * H7. semantiq:health and the screen read ONE source.
     *
     * Mutation: give HealthInspector its own copy of the logic.
     */
    public function test_the_console_health_check_agrees_with_the_screen(): void
    {
        config(['app.env' => 'production']);
        $this->givenTrustIsCached();
        config(['identity.microsoft.client_secret' => '']);

        $screen = $this->check()->report()->state();
        $console = app(HealthInspector::class)->inspect()->checks['identity'];

        $this->assertSame(IdentityHealthReport::FAILED, $screen);
        $this->assertFalse($console['ok'], 'The console reports healthy while the screen reports an outage.');
    }

    /** Degraded does not fail a deployment; an outage does. */
    public function test_degraded_does_not_fail_the_console_health_check(): void
    {
        config(['app.env' => 'production']);
        $this->givenTrustIsCached();
        config(['identity.microsoft.redirect_uri' => 'https://somewhere-else.test/auth/microsoft/callback']);

        $this->assertSame(IdentityHealthReport::DEGRADED, $this->check()->report()->state());
        $this->assertTrue(app(HealthInspector::class)->inspect()->checks['identity']['ok']);
    }

    /**
     * H10. The state-changed event fires on TRANSITION, and its result names the
     * state actually reached.
     *
     * Mutation: fire on every evaluation. A screen refresh would then produce an
     * event every time, and the events that matter would be buried.
     */
    public function test_the_state_changed_event_fires_only_on_a_transition(): void
    {
        $this->givenTrustIsCached();
        $this->entra->fakeEndpoints();

        $admin = $this->make->user(administrator: true);

        $events = [];
        Log::listen(function ($message) use (&$events): void {
            $events[] = $message->message;
        });

        $this->actingAsUser($admin)->post('/console/identity/health/re-check');
        $first = array_count_values($events)[SecurityEventLogger::IDENTITY_HEALTH_STATE_CHANGED] ?? 0;

        RateLimiter::clear('identity-health-recheck:'.$admin->id);
        $events = [];

        $this->actingAsUser($admin)->post('/console/identity/health/re-check');
        $second = array_count_values($events)[SecurityEventLogger::IDENTITY_HEALTH_STATE_CHANGED] ?? 0;

        $this->assertSame(1, $first, 'The first check did not record the state it established.');
        $this->assertSame(0, $second, 'An unchanged state recorded an event anyway.');
    }

    /**
     * H5. Re-check is rate limited per administrator.
     *
     * Mutation: remove the limiter.
     */
    public function test_re_check_is_rate_limited(): void
    {
        $this->entra->fakeEndpoints();

        $admin = $this->make->user(administrator: true);

        $this->actingAsUser($admin)->post('/console/identity/health/re-check')
            ->assertSessionHas('confirmation', 'Health re-checked.');

        $this->actingAsUser($admin)->post('/console/identity/health/re-check')
            ->assertSessionHasErrors('identity');
    }

    /** The refusal names no internal timer and counts no seconds down. */
    public function test_the_rate_limit_refusal_names_no_timer(): void
    {
        $this->entra->fakeEndpoints();

        $admin = $this->make->user(administrator: true);

        $this->actingAsUser($admin)->post('/console/identity/health/re-check');

        $response = $this->actingAsUser($admin)->post('/console/identity/health/re-check');

        $message = 'Health was checked moments ago. Try again shortly.';

        $response->assertSessionHasErrors(['identity' => $message]);
        $this->assertDoesNotMatchRegularExpression('/\d/', $message);
    }

    private function actingAsUser(User $user): self
    {
        return $this->withSession([
            EnsureSessionIsCurrent::SESSION_USER_ID => $user->id,
            EnsureSessionIsCurrent::SESSION_AUTHENTICATED_AT => now()->toIso8601String(),
        ]);
    }
}
