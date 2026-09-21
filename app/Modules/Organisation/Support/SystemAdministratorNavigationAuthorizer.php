<?php

declare(strict_types=1);

namespace App\Modules\Organisation\Support;

use App\Modules\Access\Engine\AccessEngine;
use App\Modules\Access\Support\RoleCode;
use App\Modules\Platform\Models\User;
use App\Shared\Navigation\Contracts\NavigationAuthorizer;

/**
 * Replaces DenyAllNavigationAuthorizer now that there is something to navigate
 * to. It admits System Administrators, plus ONE node named by D-182.
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
 *
 * ---------------------------------------------------------------------------
 * D-182 - ONE EXPLICIT EXCEPTION, AND ONE ONLY
 * ---------------------------------------------------------------------------
 *
 * "D-19 remains in force for System Administration navigation generally, except
 * that D-182 explicitly makes Administration Home visible to Organisation
 * Administrator because the route itself is OrgAdmin and the screen is their
 * authorised administration landing point."
 *
 * THE FINDING THAT PRODUCED IT. An Organisation Administrator can open four
 * System Administration screens - Organisation, Users & Groups, Business
 * Domains and Roles & Access, all OrgAdmin routes - and could not see a single
 * one of them in the sidebar. That is tolerable on a sub-screen and not on the
 * landing point: a home page nobody can navigate to is not a home page, and
 * "navigation that exists technically but the user cannot actually discover" is
 * the last item on the professional-polish gate.
 *
 * WHAT WAS REFUSED, AND WHY EACH. Making the route PlatformAdmin would have
 * reversed D-132 to match an implementation detail. Carrying the defect would
 * have shipped an undiscoverable home screen. Redesigning the navigation
 * permission model is a change of its own size and does not get smuggled into a
 * dashboard. Exposing every System Administration node would have been a
 * widening nobody asked for - so the other four stay hidden, and that remains a
 * carried navigation item with its own evidence to gather.
 *
 * SO THE EXCEPTION IS ONE POLICY KEY, FOR ONE REASON: the route behind it
 * already admits the viewer. It is not a principle that generalises itself to
 * the next node; the next node needs its own ruling.
 *
 * THE DEFAULT STILL RETURNS FALSE. An Organisation Administrator gains exactly
 * one node, a viewer holding neither role gains nothing, and an inactive person
 * is refused before either role is consulted.
 *
 * AND THIS IS STILL NOT ACCESS CONTROL. Administration Home re-authorises
 * through its own RequireActionClass; removing this exception would hide the
 * menu entry and change nobody's 200.
 */
final class SystemAdministratorNavigationAuthorizer implements NavigationAuthorizer
{
    /**
     * The one policy key D-182 admits an Organisation Administrator to.
     *
     * ApprovedMenu already declares this key for the Administration Home node,
     * so the exception names the key the menu uses rather than a second string
     * that could drift from it.
     */
    public const ORGANISATION_ADMINISTRATOR_EXCEPTION = 'administration.view';

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

        if (! $user instanceof User || ! $user->isActive()) {
            return false;
        }

        if ($this->engine->holdsRole($user, RoleCode::SystemAdministrator)) {
            return true;
        }

        // D-182, and ONLY D-182. One key, because the route behind it is
        // OrgAdmin and this is where an Organisation Administrator starts.
        // Every other System Administration node keeps its D-19 behaviour.
        return $policyKey === self::ORGANISATION_ADMINISTRATOR_EXCEPTION
            && $this->engine->holdsRole($user, RoleCode::OrganisationAdministrator);
    }
}
