<?php

declare(strict_types=1);

namespace App\Modules\Platform\Setup\Http\Middleware;

use App\Modules\Platform\Security\SecurityEventLogger;
use App\Modules\Platform\Setup\Bootstrap\BootstrapAccess;
use App\Modules\Platform\Setup\Bootstrap\BootstrapAuthenticator;
use App\Modules\Platform\Setup\Bootstrap\BootstrapPrincipal;
use App\Modules\Platform\Setup\Bootstrap\BootstrapSessionPolicy;
use App\Modules\Platform\Setup\Bootstrap\Models\BootstrapAdministrator;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The First-Run guard. NOT EnsureSessionIsCurrent, and not a variant of it.
 *
 * EnsureSessionIsCurrent resolves a User and puts it on the request as
 * `semantiq_user`. The bootstrap principal is NOT a User, so that middleware
 * could not be reused without teaching it to resolve two different kinds of
 * thing - at which point every console route would be one bug away from
 * accepting the wrong one.
 *
 * This sets `semantiq_bootstrap` instead. The two attributes are disjoint:
 * every console route reads `semantiq_user`, which a bootstrap request never
 * carries, and BootstrapCannotReachConsole asserts that as an equality over
 * the route table rather than by trying a handful of URLs.
 *
 * STATE IS RE-READ ON EVERY REQUEST - D-166. A stale bootstrap session becomes
 * unusable because this guard asks again, NOT because a session file was
 * deleted. Production runs file sessions, so a shutdown that depended on
 * removing files would be defeated by one that happens to remain. The session
 * key is necessary and never sufficient.
 */
final class RequireBootstrapSession
{
    public function __construct(
        private readonly BootstrapAccess $access,
        private readonly BootstrapSessionPolicy $policy,
        private readonly SecurityEventLogger $events,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->session()->get(BootstrapAuthenticator::SESSION_KEY);

        // BOTH, IN THIS ORDER. isOpen() is the live state; the session key is
        // only evidence that somebody signed in while it was open.
        if (! is_int($id) || ! $this->access->isOpen($request)) {
            return redirect()->route('first_run.sign_in');
        }

        $principal = BootstrapAdministrator::current();

        if ($principal === null || $principal->id !== $id) {
            return redirect()->route('first_run.sign_in');
        }

        /*
         * D-165. THE TWO CLOCKS, CHECKED THIRD AND FOURTH.
         *
         * After openness and identity, because an expired session on a closed
         * bootstrap should read as closed rather than as timed out, and before
         * the touch below, because touching first would refresh the idle window
         * on the very request that should have failed it - and the idle timeout
         * would then never fire at all.
         */
        if ($this->policy->hasExpired($request)) {
            $this->policy->clear($request);

            // BestEffort by declaration: failing to record this must not keep
            // an expired privileged session alive.
            $this->events->record(SecurityEventLogger::BOOTSTRAP_SESSION_EXPIRED, [
                'result' => 'expired',
                'reason' => 'session_timeout',
            ]);

            return redirect()->route('first_run.sign_in');
        }

        $request->attributes->set(
            'semantiq_bootstrap',
            new BootstrapPrincipal($principal->id, $principal->email),
        );

        // ONLY NOW. Every check above has passed, so this request is a real
        // one and the idle clock may move.
        $this->policy->touch($request);

        return $next($request);
    }
}
