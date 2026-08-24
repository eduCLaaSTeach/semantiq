<?php

declare(strict_types=1);

namespace App\Modules\Governance\Http\Middleware;

use App\Modules\Governance\Support\GovernanceStorage;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses a governance WRITE before its tables exist. Gate 4, batch R1.4a.
 *
 * ONLY THE WRITE ROUTES CARRY THIS. The three read screens are deliberately not
 * gated: each renders a controlled "migration required" state and explains it,
 * because an administrator who opens a screen during a deployment window should
 * be told what is happening rather than shown a wall.
 *
 * The write half fails closed. Accepting a change and discarding it would tell
 * an administrator their privacy or sovereignty position had changed when
 * nothing had - which is worse than refusing, because they would stop looking.
 *
 * BELT AND BRACES. Every service method behind these routes checks the same
 * condition and throws `GovernanceStorageNotInitialised`. This middleware is not
 * the only guard, and it is not the important one: the service check is what a
 * console command, a queued job or a future API would meet. This exists so the
 * refusal reaches the browser as a message on the screen the person was on,
 * rather than as an exception page.
 *
 * Gate 4 adds no route with a model-bound segment in this batch, so the ordering
 * problem SEC-DEC-058 records for the secret-reference routes does not arise
 * here. If a later batch adds one, it must use `whereNumber()` plus a controller
 * lookup for the reason recorded there: `SubstituteBindings` lives in the `web`
 * middleware GROUP and runs before any route-level middleware, so an implicit
 * binding would query the table before this could refuse.
 */
class RequireGovernanceStorage
{
    public function __construct(
        private readonly GovernanceStorage $storage,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->storage->isReady()) {
            return $next($request);
        }

        $message = $this->storage->blocker();

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 503);
        }

        return back()->withErrors(['form' => $message])->withInput();
    }
}
