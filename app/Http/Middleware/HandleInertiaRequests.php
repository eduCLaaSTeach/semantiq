<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Shared\Navigation\NavigationRegistry;
use Illuminate\Http\Request;
use Inertia\Middleware;

/**
 * Shared Inertia props.
 *
 * Navigation is resolved SERVER-SIDE and shared as already-filtered data. React
 * never receives a node the viewer may not see, so there is nothing in the
 * payload to hide in the client.
 *
 * This is presentation, not authorisation. Per D-07, every route inside the
 * authenticated area re-authorises on its own; if this filter were wrong, the
 * request would still be refused. Menu filtering and request authorisation are
 * separate code paths on purpose.
 */
final class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    /** @return array<string, mixed> */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),

            // A CLOSURE, deliberately. Inertia's middleware calls share() BEFORE
            // it calls $next($request), so anything evaluated here runs before
            // the route middleware that establishes who is asking. Resolving
            // visibleFor() eagerly asked the authorizer about a request that had
            // no user yet, so it denied every node and the sidebar was empty for
            // everyone, on every page. Inertia resolves the closure when the
            // response is built, by which time EnsureSessionIsCurrent has run.
            'productAreas' => fn (): array => app(NavigationRegistry::class)->visibleFor(),

            // The confirmation of a successful write, for one render.
            //
            // A closure for the same reason as the line above: share() runs
            // before the route middleware, and the session is read when the
            // response is built. Null on every request that did not just
            // complete a write, which is what makes it a confirmation rather
            // than a banner that lives on the page.
            'confirmation' => fn (): ?string => $request->session()->get('confirmation'),
        ];
    }
}
