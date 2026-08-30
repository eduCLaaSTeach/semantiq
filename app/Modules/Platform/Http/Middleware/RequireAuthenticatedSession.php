<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Deny by default, from the first unit.
 *
 * There is no identity provider in P1-BASE, so there is no session to resolve
 * and every authenticated route is refused. P1-00 replaces the resolution step;
 * it does not replace the default, which stays deny.
 *
 * The refusal is a redirect to the entry page and carries no hint about what
 * was requested or whether it exists.
 */
final class RequireAuthenticatedSession
{
    public function __invoke(Request $request, Closure $next): Response
    {
        return $this->handle($request, $next);
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return redirect()->to('/');
    }
}
