<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Modules\Identity\Support\SessionPolicy;
use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\DatabaseSessionHandler;
use Illuminate\Support\Facades\DB;
use Tests\Support\OrganisationFactory;
use Tests\TestCase;

/**
 * D-31, proven by BEHAVIOUR rather than by a number.
 *
 * The defect was never that a value was wrong. It was that nothing anywhere
 * asserted what happens to an idle session, so a constant declaring 60 sat
 * beside a configuration enforcing 120 and neither was ever contradicted.
 * Asserting config('session.lifetime') === 60 would repeat that mistake
 * somewhere new: it would pass on any day the two disagreed, because it never
 * asks a session to expire.
 *
 * So the first two cases drive the real handler and watch a session die.
 *
 * WHAT THESE CASES DO AND DO NOT COVER, stated rather than implied. Laravel's
 * feature-test client keeps one session store in memory for the whole test, so
 * two $this->get() calls do not round-trip through the session driver and an
 * HTTP-level "wait an hour and click" cannot be written honestly here. What
 * enforces the idle timeout in production is DatabaseSessionHandler::read(),
 * which compares the stored last_activity against session.lifetime - so that is
 * what is driven, with the lifetime the application is actually configured with.
 * The third case covers the other half: an expired session, once the store is
 * empty, is refused at the HTTP boundary. Together they are the journey; either
 * alone would be a claim about half of it.
 */
final class SessionPolicyEnforcementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * D1. An hour of inactivity ends the session.
     *
     * Mutation: SESSION_LIFETIME=120. The 61-minute read then still returns the
     * session and this fails - which is the production defect, reproduced.
     *
     * The first version of this case travelled idleMinutes() + 1 and so passed
     * under that mutation, because it was asking the handler to honour its own
     * configuration rather than the policy. Found by running the mutation, not
     * by reading the test.
     */
    public function test_a_session_idle_beyond_the_policy_is_gone(): void
    {
        $handler = $this->handler();

        $handler->write('idle-session', 'the payload');

        $this->assertNotSame('', $handler->read('idle-session'), 'The session was not stored at all.');

        // Deliberately measured against the APPROVED value, not the configured
        // one. Travelling past whatever happens to be configured would prove
        // only that the handler honours its own setting - it passed at 120,
        // found by mutation - and 120 is the defect.
        $this->travel(SessionPolicy::APPROVED_IDLE_MINUTES + 1)->minutes();

        $this->assertSame(
            '',
            $handler->read('idle-session'),
            'A session idle beyond the policy is still readable, so the idle timeout is not enforced.'
        );
    }

    /**
     * D2. And "idle expiry" is not "everything expires".
     *
     * Without this, a handler that refused every session would satisfy the case
     * above and look like a working timeout.
     */
    public function test_a_session_idle_within_the_policy_survives(): void
    {
        $handler = $this->handler();

        $handler->write('busy-session', 'the payload');

        $this->travel(SessionPolicy::APPROVED_IDLE_MINUTES - 5)->minutes();

        $this->assertSame(
            'the payload',
            $handler->read('busy-session'),
            'A session well inside the policy was expired, so the timeout is not a timeout.'
        );
    }

    /** D1, second half: once the session is empty, the request is refused. */
    public function test_a_request_with_an_expired_session_is_refused(): void
    {
        $user = (new OrganisationFactory)->user(administrator: true);

        $this->withSession([
            EnsureSessionIsCurrent::SESSION_USER_ID => $user->id,
            EnsureSessionIsCurrent::SESSION_AUTHENTICATED_AT => now()->toIso8601String(),
        ])->get('/console')->assertOk();

        $this->flushSession();

        $this->get('/console')->assertRedirect(route('entry'));
    }

    /**
     * D3. The two controls stay distinct.
     *
     * The absolute lifetime is P1-00's, it is already covered by
     * AuthenticationFlowTest, and it is NOT re-implemented here - a second worse
     * copy of an existing test is not evidence. What is asserted is the property
     * D-31 could have broken: that tightening the idle timeout did not quietly
     * make it the only control, and that idle remains shorter than absolute, or
     * the absolute one could never fire.
     */
    public function test_the_idle_timeout_is_shorter_than_the_absolute_lifetime(): void
    {
        $policy = new SessionPolicy;

        $this->assertTrue($policy->revalidatesEveryRequest());

        $this->assertSame(SessionPolicy::APPROVED_ABSOLUTE_HOURS, $policy->absoluteHours());

        $this->assertTrue(
            $policy->idleIsShorterThanAbsolute(),
            'The idle timeout is at or beyond the absolute lifetime, so the absolute one can never fire.'
        );
    }

    /** The enforced idle timeout is the approved one. */
    public function test_the_enforced_policy_matches_the_approved_policy(): void
    {
        $this->assertTrue(
            (new SessionPolicy)->matchesApprovedPolicy(),
            'The enforced session policy is not the approved one. That disagreement, with nothing '
            .'to report it, is exactly the D-31 defect.'
        );
    }

    /** The dead constant is gone, and cannot come back. */
    public function test_the_middleware_no_longer_declares_an_idle_constant(): void
    {
        $this->assertFalse(
            defined(EnsureSessionIsCurrent::class.'::IDLE_MINUTES'),
            'IDLE_MINUTES is back. A constant nothing reads is how the enforced policy drifted '
            .'from the approved one without anybody noticing.'
        );
    }

    private function handler(): DatabaseSessionHandler
    {
        return new DatabaseSessionHandler(
            DB::connection(),
            'sessions',
            (new SessionPolicy)->idleMinutes(),
            $this->app,
        );
    }
}
