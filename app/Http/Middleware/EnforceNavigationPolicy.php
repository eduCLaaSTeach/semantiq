<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Audit\Support\AuditLogger;
use App\Support\Navigation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates a route by the same policy that gates its sidebar entry.
 *
 * doc/ROLE_MODEL.md section 5 is unambiguous: "Frontend menu visibility is
 * convenience only. Backend authorization is mandatory for every protected
 * API/action." A filtered sidebar hides a link; it does nothing whatsoever
 * about somebody typing the URL, and it is a named Phase 00 acceptance
 * criterion that a business-only user is DENIED an admin route rather than
 * merely not shown it.
 *
 * The same `Navigation::allows()` decides both, on purpose. Two independent
 * implementations of one rule drift, and the drift is invisible until the
 * looser of the two is the one guarding something that matters.
 *
 * 403, not a redirect. A redirect to the dashboard would leave the person
 * wondering whether the page exists; a refusal says what happened.
 *
 * A refusal is AUDITED. Release 1's event catalogue carries
 * `privileged.action.denied` for exactly this reason: a trail containing only
 * successes cannot show an attack that failed, and a 403 that leaves no trace
 * is the most interesting event in the application going unrecorded. Only
 * signed-in people reach this middleware - `auth` runs first - so the trail
 * cannot be flooded by anonymous probing.
 *
 * Usage: ->middleware('policy:app-admin')
 */
class EnforceNavigationPolicy
{
    public function __construct(
        private readonly Navigation $navigation,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(Request $request, Closure $next, string $policy): Response
    {
        if (! $this->navigation->allows($request->user(), $policy)) {
            /*
             * The route name rather than the path: a path can carry an id or a
             * query string that is nobody's business in a trail, and the route
             * name is the stable thing an investigator would search for.
             */
            $this->audit->denied(
                action: 'privileged.action.denied',
                module: 'Security',
                resourceType: 'route',
                resourceId: $request->route()?->getName(),
                reason: 'Policy "'.$policy.'" was not satisfied.',
            );

            abort(403, 'You do not have access to this area.');
        }

        return $next($request);
    }
}
