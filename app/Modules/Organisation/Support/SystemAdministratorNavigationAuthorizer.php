<?php

declare(strict_types=1);

namespace App\Modules\Organisation\Support;

use App\Modules\Access\Engine\AccessEngine;
use App\Modules\Access\Support\RoleCode;
use App\Modules\Platform\Models\User;
use App\Shared\Navigation\Contracts\NavigationAuthorizer;

/**
 * Replaces DenyAllNavigationAuthorizer now that there is something to navigate
 * to. It admits System Administrators to the whole approved roadmap, and an
 * Organisation Administrator to the TWO ADMINISTRATION AREAS - Fabric
 * Configuration and System Administration - and not to SemantIQ Workplace.
 *
 * SemantIQ Workplace is the business area. Administration authority is not
 * business-data authority, so an administrator does not receive it for being
 * one; it stays governed by business access, which is a separate unit and not
 * built here.
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
 * CL-12, AND THE TWO RULINGS IT WENT THROUGH. BOTH ARE KEPT.
 * ---------------------------------------------------------------------------
 *
 * CL-12 WAS FIRST DELIVERED AS AN EIGHT-KEY LIST, in #147 and #148, and that
 * was CORRECT FOR THE RULING IT WAS GIVEN. The Product Owner's instruction at
 * the time was that an Organisation Administrator should see the System
 * Administration destinations they were ALREADY AUTHORISED FOR - eight of
 * eleven - while Identity & SSO, System Health and Integrations stayed hidden
 * because they are PlatformAdmin and an Organisation Administrator was not one.
 * It shipped, it was verified, and it is not being rewritten as a mistake.
 *
 * THE PRODUCT OWNER THEN CLARIFIED THE ACTUAL ROLE MODEL, and it was a
 * different model rather than a correction to the same one: Fabric
 * Configuration and System Administration are BOTH administration areas, and
 * an Organisation Administrator is an administrator of the organisation -
 * entitled to the complete current administration surface. RoleCatalogue was
 * amended to grant PlatformAdmin, so the three formerly-hidden nodes are now
 * genuinely theirs to open rather than shown-and-refused.
 *
 * SO AN ORGANISATION ADMINISTRATOR NOW SEES TWO AREAS AND 25 NODES. Fourteen
 * Fabric Configuration nodes, every one LOCKED and carrying no route, and
 * eleven System Administration nodes, every one DELIVERED.
 *
 * WHAT THE AMENDMENT DELIBERATELY DID NOT DO. BusinessData is still absent.
 * grantableBy() still excludes system_administrator, so an Organisation
 * Administrator still cannot make one. requiringStepUp() is unchanged. The two
 * role codes are not collapsed, and System Administrator remains the role that
 * sees the entire roadmap including Workplace.
 *
 * AND IT IS STILL DERIVED FROM NOTHING. The obvious "improvement" is to
 * compute visibility from the ActionClass each route requires. That is WRONG
 * and is deliberately not done: an AUDITOR holds EvidenceRead, so a
 * class-derived rule would hand them Security Status, Access Reviews and Audit
 * as a side effect of a change about administrators. The role is checked
 * FIRST, and an Auditor is neither administrator, so they see nothing.
 *
 * P1-11's acceptance, and the first CL-12 ruling, are both preserved below and
 * in the tests. Each was right for the state it described.
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
     * CL-12 AMENDED. THE TWO ADMINISTRATION AREAS, AS POLICY KEYS.
     *
     * fabric.view is the whole of Fabric Configuration - all FOURTEEN of its
     * nodes share that one key, and every one of them is LOCKED: no route, no
     * controller, no table, no service. Showing that area grants nothing,
     * because there is nothing behind it to reach.
     *
     * The other eleven are every policy key System Administration declares.
     * All eleven are DELIVERED, and after the RoleCatalogue amendment an
     * Organisation Administrator holds the action class each one requires -
     * so nothing here is visible-but-unopenable.
     *
     * workplace.view IS ABSENT, AND THAT IS THE POINT OF THE LIST. SemantIQ
     * Workplace is the business area, governed by business access rather than
     * administration authority. Administration authority is not business-data
     * authority, and an administrator does not get Workplace for being one.
     *
     * WHY A LIST RATHER THAN "is this an admin area". The authorizer is handed
     * a policy key and nothing else; deciding by area would mean either
     * rebuilding ApprovedMenu on every one of the 36 calls per render, or
     * widening the NavigationAuthorizer contract. The Product Owner ruled for
     * the smallest explicit approach, so the list is written out - and
     * test_the_allow_list_is_exactly_the_two_admin_areas DERIVES the same set
     * from ApprovedMenu and fails if the two ever disagree. The list cannot go
     * stale silently; that test is what makes writing it out safe.
     *
     * @var list<string>
     */
    public const ORGANISATION_ADMINISTRATOR_POLICY_KEYS = [
        // Fabric Configuration - all 14 nodes, every one locked.
        'fabric.view',

        // System Administration - all 11 nodes, every one delivered.
        self::ORGANISATION_ADMINISTRATOR_EXCEPTION, // Administration Home
        'organisation.view',                        // Organisation
        'people.view',                              // Users & Groups
        'access.view',                              // Roles & Access
        'domains.view',                             // Business Domains
        'identity.view',                            // Identity & SSO
        'security.view',                            // Security Status
        'access-reviews.view',                      // Access Reviews
        'audit.view',                               // Audit
        'system-health.view',                       // System Health
        'integrations.view',                        // Integrations
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
