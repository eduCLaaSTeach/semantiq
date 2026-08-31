<?php

declare(strict_types=1);

namespace App\Modules\Organisation\Support;

use App\Modules\Platform\Models\User;
use App\Shared\Navigation\Contracts\NavigationAuthorizer;

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
    /**
     * The request is read at CALL time, not injected.
     *
     * NavigationRegistry is a singleton, so an injected Request would be
     * captured once at construction and could be a different instance from the
     * one the session middleware actually set semantiq_user on - which reads as
     * "nobody is signed in" and denies every node.
     */
    public function allows(string $policyKey): bool
    {
        $user = request()->attributes->get('semantiq_user');

        return $user instanceof User && $user->isActive() && $user->isSystemAdministrator();
    }
}
