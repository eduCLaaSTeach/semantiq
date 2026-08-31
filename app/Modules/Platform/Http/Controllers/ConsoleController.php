<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers;

use App\Modules\Platform\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The D-11 confirmation state.
 *
 * Deliberately minimal: it proves an authenticated session reaches a protected
 * route, and nothing more. No sidebar, no Administration Home, no menu, no
 * placeholder for a later unit, no business data.
 *
 * It must not grow into the shell. P1-01 owns what comes next.
 */
final class ConsoleController
{
    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $request->attributes->get('semantiq_user');

        return Inertia::render('Console/Home', [
            'displayName' => $user->display_name,
            'email' => $user->email,
            'isSystemAdministrator' => $user->isSystemAdministrator(),
        ]);
    }
}
