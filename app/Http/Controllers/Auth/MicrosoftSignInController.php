<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

/**
 * The Microsoft single sign-on path.
 *
 * The screen offers this route today; the OIDC exchange behind it is not built
 * yet. It fails closed with a plain explanation rather than a 500, so the button
 * is honest about the state of the feature instead of appearing broken.
 *
 * Replacing this with the real authorization-code and PKCE flow is the next
 * piece of work. Nothing else on the screen changes when it lands.
 */
class MicrosoftSignInController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return redirect()
            ->route('sign-in')
            ->withErrors([
                'form' => 'Microsoft sign-in is not configured yet. Use your email and password, '
                    .'or ask an administrator to finish the Microsoft Entra setup.',
            ]);
    }
}
