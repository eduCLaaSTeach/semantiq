<?php

declare(strict_types=1);

namespace App\Modules\Organisation\Support;

use App\Modules\Access\Engine\AccessEngine;
use App\Modules\Access\Support\RoleCode;
use App\Modules\Platform\Models\User;
use App\Shared\Navigation\Contracts\NavigationAuthorizer;

/**
 * Replaces DenyAllNavigationAuthorizer now that there is something to navigate
 * to. It admits System Administrators to the whole System Administration area,
 * and an Organisation Administrator to the EIGHT nodes CL-12 names below.
 *
 * Those eight are not a grant. Every one of them sits behind a route an
 * Organisation Administrator was ALREADY authorised for; seven of them were
 * simply impossible to find. CL-12 stopped hiding them. D-182, which admitted
 * the first of the eight and is the ruling CL-12 supersedes, is preserved in
 * full further down because it is the reasoning CL-12 applies, not a repealed
 * rule.
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
 * CL-12 SUPERSEDES THE NARROW D-182 PRESENTATION RULE - PROSPECTIVELY
 * ---------------------------------------------------------------------------
 *
 * D-182's reasoning is UNCHANGED and is the reason CL-12 exists: a node may be
 * shown when THE ROUTE BEHIND IT ALREADY ADMITS THE VIEWER. D-182 applied that
 * test to one node and said the next node would need its own ruling. CL-12 is
 * that ruling, and it applies the same test to the seven others that pass it.
 *
 * AN ORGANISATION ADMINISTRATOR NOW SEES EIGHT NODES. Administration Home,
 * Organisation, Users & Groups, Roles & Access, Business Domains, Security
 * Status, Access Reviews and Audit. Every one was reachable by URL before this
 * change and returned 200; seven of them simply could not be found, which is
 * "navigation that exists technically but the user cannot actually discover" -
 * the last item on the professional-polish gate.
 *
 * NOTHING IS WIDENED. RoleCatalogue::MATRIX, every route, every
 * RequireActionClass declaration and every action class are untouched. The
 * allow-list MIRRORS an authorisation fact; it does not create one.
 *
 * IDENTITY & SSO, SYSTEM HEALTH AND INTEGRATIONS STAY HIDDEN, because they are
 * PlatformAdmin and an Organisation Administrator is not one. Removing this
 * authorizer entirely would still not let them in.
 *
 * AND IT IS DERIVED FROM NOTHING. The obvious "improvement" is to compute
 * visibility from the ActionClass each route requires. That is WRONG and is
 * deliberately not done: an AUDITOR holds EvidenceRead, so a class-derived rule
 * would hand them Security Status, Access Reviews and Audit as a side effect of
 * a change about Organisation Administrators. An explicit list cannot do that.
 *
 * P1-11's acceptance of the narrow behaviour is NOT rewritten. It was correct
 * for the state it described, and it required that any widening be a decision
 * rather than a side effect - which this is.
 *
 * ---------------------------------------------------------------------------
 * D-182 - THE ORIGINAL RULING, PRESERVED
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
 * *** The sentence above is exactly what CL-12 is: the next ruling. The four
 * screens D-182 named as "staying hidden" are now shown, by decision. ***
 *
 * THE DEFAULT STILL RETURNS FALSE. A viewer holding neither role gains nothing,
 * and an inactive person is refused before either role is consulted.
 *
 * AND THIS IS STILL NOT ACCESS CONTROL. Administration Home re-authorises
 * through its own RequireActionClass; removing this exception would hide the
 * menu entry and change nobody's 200.
 */
final class SystemAdministratorNavigationAuthorizer implements NavigationAuthorizer
{
    /**
     * The one policy key D-182 admitted an Organisation Administrator to.
     *
     * KEPT, and still a member of the CL-12 allow-list below. It is referenced
     * by the P1-11 tests that recorded D-182's original behaviour, and removing
     * it would erase the name of the ruling CL-12 supersedes rather than
     * superseding it.
     *
     * @deprecated by CL-12. Use ORGANISATION_ADMINISTRATOR_POLICY_KEYS.
     */
    public const ORGANISATION_ADMINISTRATOR_EXCEPTION = 'administration.view';

    /**
     * CL-12. THE COMPLETE SET AN ORGANISATION ADMINISTRATOR MAY SEE.
     *
     * Eight keys, every one declared by ApprovedMenu, and every one behind a
     * route an Organisation Administrator is ALREADY authorised for through
     * OrgAdmin, AccessAdmin or EvidenceRead. Nothing here widens an
     * authorisation; it stops hiding destinations the viewer can already open.
     *
     * THE THREE THAT ARE ABSENT ARE ABSENT ON PURPOSE. identity.view,
     * system-health.view and integrations.view sit behind PlatformAdmin, which
     * an Organisation Administrator does not hold. So do fabric.view and
     * workplace.view, which belong to phases that are not delivered.
     *
     * @var list<string>
     */
    public const ORGANISATION_ADMINISTRATOR_POLICY_KEYS = [
        self::ORGANISATION_ADMINISTRATOR_EXCEPTION, // Administration Home
        'organisation.view',                        // Organisation
        'people.view',                              // Users & Groups
        'access.view',                              // Roles & Access
        'domains.view',                             // Business Domains
        'security.view',                            // Security Status
        'access-reviews.view',                      // Access Reviews
        'audit.view',                               // Audit
    ];

    /**
     * Where the per-request role answers live.
     *
     * A Request attribute, so it is born and dies with the request. Named
     * distinctly from semantiq_user so neither can be mistaken for the other.
     */
    public const REQUEST_ROLE_MEMO = 'semantiq_navigation_role_memo';

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

        if ($this->roleHeldOnceThisRequest($user, RoleCode::SystemAdministrator)) {
            return true;
        }

        /*
         * CL-12. An explicit list, compared strictly, and STILL GATED ON THE
         * ROLE - the two conditions are both required and neither is enough.
         *
         * in_array with strict: true rather than a loose comparison, because a
         * loose one would admit anything PHP considers equal to a key.
         */
        return in_array($policyKey, self::ORGANISATION_ADMINISTRATOR_POLICY_KEYS, true)
            && $this->roleHeldOnceThisRequest($user, RoleCode::OrganisationAdministrator);
    }

    /**
     * AccessEngine::holdsRole, asked AT MOST ONCE PER ROLE PER USER PER REQUEST.
     *
     * NOT A SECOND DEFINITION OF THE QUESTION, and deliberately not named as
     * though it were. It decides nothing: it forwards to the engine and
     * remembers what the engine said. N-B8 (AccessBoundaryTest) fails any
     * `function holdsRole` outside the engine, and it was right to fail this
     * one while it carried that name - a method called holdsRole here reads as
     * a rival answer to the same question, which is how two definitions drift
     * apart.
     *
     * WHY THIS EXISTS. NavigationRegistry::visibleFor() calls allows() once per
     * menu node - 36 of them - so every page render asked the same two
     * questions 36 and 8 times. Measured on /console before this method:
     *
     *   System Administrator        37 role_assignments queries
     *   Organisation Administrator  45
     *   Auditor                     45  <- eight OrgAdmin lookups for a role
     *   Business User               45     they do not hold
     *
     * (36 of each count come from this class; the 37th SystemAdministrator
     * lookup is ConsoleController's own, which CL-12 does not touch.)
     *
     * THE ANSWER IS AN INVARIANT WITHIN ONE REQUEST. Role assignments do not
     * change halfway through rendering a menu, so asking again cannot return
     * anything new - it can only cost another round trip.
     *
     * WHERE IT IS KEPT, AND WHY NOWHERE ELSE. The current Request's attribute
     * bag, which is created per request and discarded with it.
     *
     *   - NOT a static property and NOT an instance property: NavigationRegistry
     *     holds this authorizer as a SINGLETON, so either would outlive the
     *     request and answer the next viewer with the previous viewer's roles.
     *     That is a privilege-escalation bug, not a performance detail.
     *   - NOT the application cache, the session, Redis or the database: a role
     *     revoked between two requests must be gone on the second one. D-69
     *     forbids caching permission answers across requests and is unchanged.
     *
     * KEYED BY USER ID AS WELL AS ROLE, so that even within a single request a
     * second user's question can never be answered from the first user's entry.
     * One request normally has one viewer; the key does not rely on that.
     *
     * AccessEngine IS UNTOUCHED. This memoises the CALLER's repeated question;
     * it does not put a cache inside the engine, and every route still
     * re-authorises through its own middleware on a fresh request.
     */
    private function roleHeldOnceThisRequest(User $user, RoleCode $role): bool
    {
        $request = request();

        /** @var array<string, bool> $answered */
        $answered = $request->attributes->get(self::REQUEST_ROLE_MEMO, []);

        $key = $user->getKey().'|'.$role->value;

        if (array_key_exists($key, $answered)) {
            return $answered[$key];
        }

        $answered[$key] = $this->engine->holdsRole($user, $role);

        $request->attributes->set(self::REQUEST_ROLE_MEMO, $answered);

        return $answered[$key];
    }
}
