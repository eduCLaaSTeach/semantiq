<?php

declare(strict_types=1);

namespace App\Modules\Organisation\Support;

use App\Modules\Access\Engine\AccessEngine;
use App\Modules\Access\Support\RoleCode;
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
 * D-19, and this is a TEMPORARY PHASE 1 PRESENTATION RULE. A System
 * Administrator sees the complete approved roadmap so the shape of the product
 * is legible. Seeing it grants nothing: every roadmap entry is locked and
 * carries no route, so there is no destination to reach and nothing to
 * authorise.
 *
 * It is not a role framework and does not read the policy key beyond requiring
 * one to exist. Do not grow the future effective-access navigation model here.
 *
 * P1-05 REPOINTED IT AT THE ENGINE and changed nothing else. It asks
 * AccessEngine::holdsRole rather than a column, so there is one definition of
 * the question. It remains UX: every route still re-authorises on its own, and
 * filtering the menu and authorising the request stay two code paths so they
 * cannot be collapsed into one.
 */
final class SystemAdministratorNavigationAuthorizer implements NavigationAuthorizer
{
    public function __construct(private readonly AccessEngine $engine) {}

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

        return $user instanceof User
            && $user->isActive()
            && $this->engine->holdsRole($user, RoleCode::SystemAdministrator);
    }
}
