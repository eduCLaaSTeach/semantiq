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
            'productAreas' => app(NavigationRegistry::class)->visibleFor(),
        ];
    }
}
