<?php

declare(strict_types=1);

namespace App\Modules\Access\Http\Middleware;

use App\Modules\Access\Engine\AccessEngine;
use App\Modules\Access\Engine\AccessQuestion;
use App\Modules\Access\Support\ActionClass;
use App\Modules\Platform\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * EVERY PROTECTED ROUTE DECLARES ITS ACTION CLASS. There is NO DEFAULT.
 *
 * The class is a required middleware parameter, so a route that declares none
 * cannot be constructed - and RouteAuthorizationMatrixTest asserts that every
 * protected route under /console carries one, so an unclassified route fails
 * the build rather than quietly inheriting whatever the last developer thought
 * was reasonable.
 *
 * THE DECISION PRECEDES THE FETCH. This is middleware rather than a check
 * inside a controller precisely so that nothing protected is loaded before the
 * answer is known: a denial must never return a payload the UI then hides.
 *
 * MENU VISIBILITY IS NEVER THE CONTROL. If the navigation filter were wrong,
 * the request would still be refused here. That was true of
 * RequireSystemAdministrator before it and it is true of this.
 *
 * WHAT THIS REPLACED. RequireSystemAdministrator asked users.platform_role
 * directly. It was NOT find-and-replaced into "the new equivalent": every route
 * was enumerated and given the class it actually needs, because a blanket
 * substitution is how Identity & SSO would have quietly become reachable by an
 * Organisation Administrator.
 */
final class RequireActionClass
{
    public function __construct(private readonly AccessEngine $engine) {}

    public function handle(Request $request, Closure $next, string $class): Response
    {
        $actionClass = ActionClass::tryFrom($class);

        if ($actionClass === null) {
            // An unrecognised class is a misconfigured route. It fails CLOSED -
            // the alternative is a typo silently opening a screen.
            return $this->refuse($request);
        }

        $user = $request->attributes->get('semantiq_user');

        $decision = $this->engine->decide(AccessQuestion::administration(
            $user instanceof User ? $user : null,
            $request->route()?->getName() ?? $request->path(),
            $actionClass,
        ));

        if (! $decision->allowed) {
            return $this->refuse($request);
        }

        return $next($request);
    }

    /**
     * The refusal carries no hint about what was requested or whether it
     * exists, so an unauthorised caller cannot map the structure by probing -
     * and it carries NO PAYLOAD, not even an empty object with the field names.
     */
    private function refuse(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return redirect()->route('auth.access-denied');
    }
}
