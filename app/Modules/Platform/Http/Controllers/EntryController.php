<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/**
 * The pre-authentication entry page.
 *
 * The blueprint requires that an unauthenticated browser receives no protected
 * shell, menu or business metadata. This page therefore carries none: brand,
 * product name, a short statement, and nothing that describes what exists
 * behind authentication.
 *
 * P1-00 replaces this with the real Login page and its Sign in with Microsoft
 * action. It is not a placeholder for a screen that should exist now - in
 * P1-BASE there is genuinely nothing to sign in to.
 */
final class EntryController
{
    public function __invoke(): Response
    {
        return Inertia::render('Entry');
    }
}
