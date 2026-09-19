<?php

declare(strict_types=1);

namespace Tests\Feature\SystemHealth;

use App\Modules\Platform\Health\HealthInspector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Support\EntraTokenFactory;
use Tests\TestCase;

/**
 * H16. /up AND semantiq:health BEHAVE IDENTICALLY BEFORE AND AFTER.
 *
 * P1-09 added inspectLocal() to HealthInspector and a storedReport() to
 * IdentityHealthCheck. Neither changed inspect(), which is what the deployment
 * probe and the console command are built on - and that is the claim this file
 * exists to hold rather than assert in a commit message.
 *
 * THE RISK IS REAL AND SPECIFIC. The correction removed identity from a NEW
 * projection. Removing it from inspect() as well would look like the same
 * change, would make every P1-09 test pass, and would stop the deployment gate
 * noticing a broken sign-in.
 *
 * UNDER RefreshDatabase, AND THAT WAS A CORRECTION. Written without it first,
 * following LivenessTest, three of these cases failed - and they were right to:
 * the in-memory database has no tables until RefreshDatabase migrates it, so
 * the migration check fails and the whole verdict is legitimately unhealthy.
 * LivenessTest does not notice because it accepts either answer; a case
 * asserting "ok" has to be given a deployment that is actually healthy, or it
 * is measuring the fixture rather than the code.
 *
 * The 503-when-the-database-is-down case is NOT repeated here. It repoints the
 * default connection, which RefreshDatabase cannot roll back through, and
 * LivenessTest already owns it unchanged - which is itself part of the evidence
 * that /up still behaves as it did.
 */
final class DeploymentGateUnchangedTest extends TestCase
{
    use RefreshDatabase;

    /** The console command's six checks, in order. */
    public function test_the_health_command_still_reports_all_six_checks(): void
    {
        $this->artisan('semantiq:health')
            ->expectsOutputToContain('database')
            ->expectsOutputToContain('migrations')
            ->expectsOutputToContain('configuration')
            ->expectsOutputToContain('storage')
            ->expectsOutputToContain('assets')
            ->expectsOutputToContain('identity')
            ->assertSuccessful();
    }

    /**
     * And it still FAILS, with exit code 1, when a check fails. A gate that
     * cannot fail is not a gate.
     */
    public function test_the_health_command_still_fails_on_a_failing_check(): void
    {
        config(['app.key' => null]);

        $this->artisan('semantiq:health')->assertFailed();
    }

    /** /up still answers with one of exactly two words. */
    public function test_up_still_answers_ok(): void
    {
        $response = $this->get('/up');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ok', trim((string) $response->getContent()));
    }

    /** /up still sets no cookie, so it is still outside the web group. */
    public function test_up_still_holds_no_session(): void
    {
        $this->assertEmpty($this->get('/up')->headers->getCookies());
    }

    /**
     * inspect() still returns the six checks, in the order the command prints
     * them, and identity is still one of them.
     */
    public function test_inspect_still_returns_the_six_checks_in_order(): void
    {
        $this->assertSame(
            ['database', 'migrations', 'configuration', 'storage', 'assets', 'identity'],
            array_keys(app(HealthInspector::class)->inspect()->checks)
        );
    }

    /**
     * AN IDENTITY FAILURE, AND NOTHING ELSE.
     *
     * The first version of this emptied the identity keys in production mode.
     * It DID fail identity - and it also failed `configuration`, because
     * ConfigurationValidator requires those same keys in production. So the
     * local projection went red too, and the case below looked like a defect in
     * inspectLocal() when the code was right and the fixture was wrong.
     *
     * The SECOND version set app.env to production instead, and that failed
     * too: production mode requires more configuration than the test
     * environment supplies, and forbids APP_DEBUG, so it trips `configuration`
     * for reasons that have nothing to do with sign-in. Two fixtures, both
     * failing the wrong check - which is precisely the "a test that passes, or
     * fails, for a reason unrelated to what it claims to check" this project
     * keeps producing.
     *
     * So app.env is left alone. Identity is configured here, CORRECTLY - which
     * is what makes the out-of-production exemption not apply, since that
     * exemption needs the provider to be unconfigured. What is removed is the
     * TRUST: nothing is cached and Microsoft cannot be reached, so
     * identityTrustAvailable is FAILED while every local check stays green.
     */
    private function givenSignInIsBrokenAndNothingElseIs(): void
    {
        (new EntraTokenFactory)->configure();

        Cache::flush();
        Http::fake(fn () => Http::response('', 500));
    }

    /**
     * A FAILING IDENTITY STILL FAILS THE DEPLOYMENT.
     *
     * If P1-09 had quietly pointed /up or the command at the local-only
     * projection, this is the case that would have caught it - and the outage
     * it describes, sign-in down on a machine that is otherwise fine, is
     * exactly the one a deployment gate exists for.
     */
    public function test_a_failing_identity_still_fails_the_deployment_verdict(): void
    {
        $this->givenSignInIsBrokenAndNothingElseIs();

        $report = app(HealthInspector::class)->inspect();

        $this->assertFalse($report->checks['identity']['ok'], 'A broken sign-in no longer fails the deployment gate.');
        $this->assertFalse($report->isHealthy());
        $this->assertSame(['identity'], $report->failing(), 'Something other than identity failed, so this proves less than it claims.');
    }

    /**
     * AND THE LOCAL PROJECTION STAYS GREEN THROUGH THE SAME FAILURE.
     *
     * The two readings disagreeing here is not a bug - it is the reason the
     * System Health row is called Local service health rather than presented as
     * the deployment verdict. Sign-in can be down, /up can be returning 503,
     * and every local check can still be green.
     */
    public function test_the_local_projection_is_unaffected_by_a_failing_identity(): void
    {
        $this->givenSignInIsBrokenAndNothingElseIs();

        $inspector = app(HealthInspector::class);

        $this->assertFalse($inspector->inspect()->isHealthy());
        $this->assertTrue(
            $inspector->inspectLocal()->isHealthy(),
            'The local projection followed identity, which means it is not local.'
        );
    }
}
