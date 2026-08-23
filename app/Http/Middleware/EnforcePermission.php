<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Audit\Support\AuditLogger;
use App\Modules\Identity\Support\Authorization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates a route by a permission key. Feature ADM-007.
 *
 * ADM-007 requires authorization at three layers - navigation, the route or
 * controller boundary, and service rules - and this is the second. It asks the
 * same `Authorization::allows()` the rail asks and the services ask, so all
 * three give one answer.
 *
 * IT IS NOT A SUBSTITUTE FOR THE SERVICE CHECK. A route gate stops a request
 * arriving; it does nothing about a console command, a queued job, or a second
 * route added later that forgets the middleware. The services check again for
 * exactly that reason, and the duplication is deliberate.
 *
 * Every refusal is audited. A 403 that leaves no trace is the most interesting
 * event in the application going unrecorded.
 *
 * Usage: ->middleware('permission:admin.users.view')
 */
class EnforcePermission
{
    public function __construct(
        private readonly Authorization $authorization,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if ($this->authorization->allows($request->user(), $permission)) {
            return $next($request);
        }

        $this->audit->denied(
            action: 'privileged.action.denied',
            module: 'Security',
            resourceType: 'route',
            resourceId: $request->route()?->getName(),
            /* The permission, not the path: a path can carry an id that is
             * nobody's business in a trail, and the key is the stable thing an
             * investigator would search for. */
            reason: 'Permission "'.$permission.'" is not held.',
        );

        abort(403, 'You do not have access to this area.');
    }
}
