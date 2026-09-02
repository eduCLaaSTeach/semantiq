<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Health\IdentityHealthCheck;
use App\Modules\Platform\Models\User;
use App\Modules\Platform\Security\SecurityEventLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;
use Inertia\Response;

/**
 * SSO Health, and the one action in the unit.
 *
 * Rendering this screen contacts nobody. Only "Re-check now" probes, and it
 * carries TWO guards for different jobs:
 *
 *   the PROVIDER-WIDE lock, inside EntraDiscovery::probe(), is the real
 *   protection - ten administrators with ten tabs must not become ten requests
 *   to Microsoft;
 *
 *   the per-administrator rate limit here is a UI guard - it stops double
 *   submits and impatient clicking before they reach the lock, and gives the
 *   person a sentence instead of a silent no-op.
 */
final class HealthController
{
    private const PER_ADMINISTRATOR_SECONDS = 60;

    public function __construct(
        private readonly IdentityHealthCheck $health,
        private readonly SecurityEventLogger $events,
    ) {}

    public function show(): Response
    {
        return Inertia::render('Identity/Health', [
            'health' => $this->health->report()->toArray(),
        ]);
    }

    public function recheck(Request $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->attributes->get('semantiq_user');

        $limiter = 'identity-health-recheck:'.$actor->id;

        if (RateLimiter::tooManyAttempts($limiter, 1)) {
            // Names no internal timer and counts no seconds down.
            return back()->withErrors([
                'identity' => 'Health was checked moments ago. Try again shortly.',
            ]);
        }

        RateLimiter::hit($limiter, self::PER_ADMINISTRATOR_SECONDS);

        $outcome = $this->health->recheck();
        $report = $outcome['report'];

        $this->events->record(SecurityEventLogger::IDENTITY_HEALTH_CHECKED, [
            'provider' => 'microsoft',
            'user_id' => $actor->id,
            'result' => $report->state(),
        ]);

        /*
         * On CHANGE, not on evaluation. Without the remembered state a screen
         * refresh would produce an event every time, and the events that matter
         * would be buried by the ones that do not.
         *
         * previous_result is deliberately absent: the D-12 context-key list has
         * no key for it, and that fixed list is the reason a token cannot be
         * logged by accident. It is not widened to make a log line read better.
         */
        if ($outcome['changed']) {
            $this->events->record(SecurityEventLogger::IDENTITY_HEALTH_STATE_CHANGED, [
                'provider' => 'microsoft',
                'result' => $report->state(),
                'reason' => $report->firstConcern() ?? 'none',
            ]);
        }

        if (! $outcome['ran']) {
            return back()->withErrors([
                'identity' => 'A live check was run moments ago. This is its result.',
            ]);
        }

        return redirect()->route('identity.health')->with('confirmation', 'Health re-checked.');
    }
}
