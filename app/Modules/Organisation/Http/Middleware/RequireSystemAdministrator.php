<?php

declare(strict_types=1);

namespace App\Modules\Organisation\Http\Middleware;

use App\Modules\Platform\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every Organisation route re-authorises. Menu visibility is never the control.
 *
 * This runs AFTER EnsureSessionIsCurrent, which has already established that a
 * session exists, is within its lifetime, and belongs to an active user. This
 * adds one thing: the platform role.
 *
 * It is a single explicit gate, not a role framework. P1-05 replaces the D-09
 * seam it reads, and until then adding a second role here would be building the
 * authorisation engine nobody has designed yet.
 *
 * Note what this does NOT do: it grants no business-domain access. SYS-004 is
 * explicit that a System Administrator receives none, and there is no business
 * data in this unit to receive. OrganisationBoundaryTest asserts that against
 * the authorisation boundary rather than against an empty result, because an
 * empty result would keep passing after the boundary was removed.
 */
final class RequireSystemAdministrator
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->attributes->get('semantiq_user');

        if (! $user instanceof User || ! $user->isSystemAdministrator()) {
            return $this->refuse($request);
        }

        return $next($request);
    }

    /**
     * The refusal carries no hint about what was requested or whether it exists,
     * so an unauthorised caller cannot map the structure by probing.
     */
    private function refuse(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return redirect()->route('auth.access-denied');
    }
}
