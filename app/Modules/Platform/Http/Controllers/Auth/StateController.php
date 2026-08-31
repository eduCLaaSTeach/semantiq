<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\Auth;

use Inertia\Inertia;
use Inertia\Response;

/**
 * The pre-authentication refusal and outcome states.
 *
 * Every one of them is a standalone card with no shell, and none of them says
 * anything the requester did not already know. "Access not assigned" and
 * "account inactive" are deliberately the same shape: telling them apart is
 * exactly how an anonymous caller would enumerate the directory.
 */
final class StateController
{
    public function __invoke(string $state): Response
    {
        return Inertia::render('Auth/State', ['state' => $state]);
    }
}
