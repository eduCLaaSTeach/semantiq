<?php

declare(strict_types=1);

namespace App\Modules\Organisation\Support;

use App\Modules\Platform\Models\User;
use App\Shared\Navigation\Contracts\NavigationAuthorizer;
use Illuminate\Http\Request;

/**
 * Replaces DenyAllNavigationAuthorizer now that there is something to navigate
 * to. It admits System Administrators and nobody else.
 *
 * This is UX, not access control. Every Organisation route re-authorises through
 * RequireSystemAdministrator on its own; if this authorizer were wrong, the
 * request would still be refused. Filtering the menu and authorising the request
 * are deliberately two code paths so they cannot be collapsed into one.
 *
 * It is not a role framework and does not read the policy key beyond requiring
 * one to exist. P1-05 owns the real implementation.
 */
final class SystemAdministratorNavigationAuthorizer implements NavigationAuthorizer
{
    public function __construct(private readonly Request $request) {}

    public function allows(string $policyKey): bool
    {
        $user = $this->request->attributes->get('semantiq_user');

        return $user instanceof User && $user->isActive() && $user->isSystemAdministrator();
    }
}
