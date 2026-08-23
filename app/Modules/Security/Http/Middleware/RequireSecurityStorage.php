<?php

declare(strict_types=1);

namespace App\Modules\Security\Http\Middleware;

use App\Modules\Security\Support\SecurityStorage;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses a secret-reference action before its table exists. Feature ADM-012.
 *
 * WHY A MIDDLEWARE AND NOT A CHECK IN THE CONTROLLER. Four of the six secret
 * routes carry a `{secretReference}` segment, and Laravel resolves an implicit
 * model binding BEFORE the controller method runs. A check inside the method
 * would arrive after the query that fails, so a typed URL during the deployment
 * window would still return a raw database error. This runs first.
 *
 * The index screen is deliberately NOT gated by this. It renders a controlled
 * "migration required" state instead, because an administrator who opens the
 * screen during a deployment window should be told what is happening rather
 * than shown a wall.
 *
 * FAILS CLOSED. A write during this window is refused outright: accepting one
 * and discarding it would tell somebody their credential inventory had been
 * recorded when nothing had.
 */
class RequireSecurityStorage
{
    public function __construct(
        private readonly SecurityStorage $storage,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->storage->secretReferencesAreReady()) {
            return $next($request);
        }

        $message = $this->storage->blocker();

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 503);
        }

        /*
         * Back to the index with the message rather than an error page. The
         * index handles this state and explains it, so the person lands
         * somewhere that makes sense instead of on a 503 they have to
         * interpret.
         */
        return redirect()
            ->route('admin.security.secrets')
            ->withErrors(['form' => $message]);
    }
}
