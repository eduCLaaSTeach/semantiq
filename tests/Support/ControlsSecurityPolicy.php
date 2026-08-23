<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Security\Support\Reauthentication;
use App\Modules\Security\Support\SecurityPolicies;
use Illuminate\Support\Carbon;

/**
 * Helpers for tests that need a particular security policy in force.
 *
 * WHY THE CATALOGUE DEFAULT AND NOT A DATABASE ROW. Writing a row through
 * `SecurityPolicies::set()` needs an authenticated actor with the right tier
 * and, for a high-risk key, a written reason - which is a lot of setup for a
 * test whose subject is something else entirely, and which couples every test
 * to the write path. Overriding the catalogue default gives the same resolved
 * value with none of it.
 *
 * Tests that are ABOUT the write path do it properly, through the service.
 */
trait ControlsSecurityPolicy
{
    /**
     * Put one policy value in force for this test.
     *
     * The catalogue is keyed by dotted strings - `sign_in.mode` is one array
     * key, not three levels of nesting - so it has to be read, changed and put
     * back whole. `config(['security.policies.sign_in.mode' => ...])` would
     * silently create a nested structure nothing reads.
     */
    protected function withSecurityPolicy(string $key, string|int|bool|null $value): void
    {
        $policies = (array) config('security.policies', []);

        $this->assertArrayHasKey($key, $policies, 'Unknown security policy "'.$key.'" in a test.');

        $policies[$key]['default'] = $value;

        config(['security.policies' => $policies]);

        /* The service memoises per request, and the container instance outlives
         * a single request inside a test. */
        app()->forgetInstance(SecurityPolicies::class);
    }

    /**
     * A session that has recently proved its identity.
     *
     * Spread into `withSession()`. Without it, any route carrying
     * `confirm:` redirects to the confirmation screen - which is the correct
     * behaviour and not what most tests are trying to prove.
     *
     * @return array<string, string>
     */
    protected function confirmedIdentity(): array
    {
        return [Reauthentication::CONFIRMED_AT => Carbon::now()->utc()->toIso8601String()];
    }
}
