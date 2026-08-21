<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

/**
 * Shares the data every Inertia page receives.
 *
 * Anything added here is serialised into the page payload and is therefore
 * visible in the browser. Never share a secret, an access token, or another
 * user's record from this class.
 */
class HandleInertiaRequests extends Middleware
{
    /**
     * The Blade template that wraps every Inertia response.
     */
    protected $rootView = 'app';

    /**
     * Data shared with every page.
     *
     * `flash.status` carries the one form-level message the sign-in screen
     * renders as a persistent inline alert. It is a structured value rather than
     * a bare string so the page can pick the alert role without parsing copy.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'flash' => [
                'status' => $request->session()->get('status'),
            ],
        ];
    }
}
