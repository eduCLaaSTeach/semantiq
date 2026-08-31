<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\Auth;

use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;
use App\Modules\Platform\Security\SecurityEventLogger;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * POST only. A GET logout is triggerable by any image tag on any page.
 *
 * Destroys the SemantIQ session and nothing else: per D-04 there is no global
 * Entra sign-out, because signing a user out of SemantIQ must not sign them out
 * of Outlook or Teams.
 */
final class LogoutController
{
    public function __construct(private readonly SecurityEventLogger $events) {}

    public function __invoke(Request $request): Response
    {
        $userId = $request->session()->get(EnsureSessionIsCurrent::SESSION_USER_ID);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if (is_int($userId) || is_string($userId)) {
            $this->events->record(SecurityEventLogger::LOGOUT, [
                'user_id' => (int) $userId,
                'result' => 'signed_out',
            ]);
        }

        return redirect()->route('auth.signed-out');
    }
}
