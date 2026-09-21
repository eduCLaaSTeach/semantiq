<?php

declare(strict_types=1);

use App\Modules\Access\Http\Controllers\AccessController;
use App\Modules\Access\Http\Controllers\SimulatorController;
use App\Modules\Access\Http\Controllers\StepUpController;
use App\Modules\Access\Http\Middleware\RequireActionClass;
use App\Modules\Access\Support\ActionClass;
use App\Modules\Administration\Http\Controllers\AdministrationHomeController;
use App\Modules\Audit\Http\Controllers\AuditController;
use App\Modules\Domains\Http\Controllers\DomainController;
use App\Modules\Identity\Http\Controllers\EntraController;
use App\Modules\Identity\Http\Controllers\HealthController;
use App\Modules\Identity\Http\Controllers\LoginExperienceController;
use App\Modules\Identity\Http\Controllers\ProvidersController;
use App\Modules\Identity\Http\Controllers\SessionPolicyController;
use App\Modules\Organisation\Http\Controllers\BusinessUnitController;
use App\Modules\Organisation\Http\Controllers\DepartmentController;
use App\Modules\Organisation\Http\Controllers\HierarchyController;
use App\Modules\Organisation\Http\Controllers\LegalEntityController;
use App\Modules\Organisation\Http\Controllers\ProfileController;
use App\Modules\Organisation\Http\Controllers\TeamController;
use App\Modules\Organisation\Http\Middleware\RequireOrganisation;
use App\Modules\People\Http\Controllers\GroupController;
use App\Modules\People\Http\Controllers\UserController;
use App\Modules\Platform\Http\Controllers\Auth\CallbackController;
use App\Modules\Platform\Http\Controllers\Auth\LogoutController;
use App\Modules\Platform\Http\Controllers\Auth\RedirectController;
use App\Modules\Platform\Http\Controllers\Auth\StateController;
use App\Modules\Platform\Http\Controllers\ConsoleController;
use App\Modules\Platform\Http\Controllers\EntryController;
use App\Modules\Platform\Http\Controllers\FirstRun\BeginController;
use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;
use App\Modules\Platform\Setup\Http\Controllers\FirstRunController;
use App\Modules\Platform\Setup\Http\Controllers\IntegrationController;
use App\Modules\Platform\Setup\Http\Middleware\RequireBootstrapSession;
use App\Modules\Reviews\Http\Controllers\AccessReviewDecisionController;
use App\Modules\Reviews\Http\Controllers\AccessReviewsController;
use App\Modules\Security\Http\Controllers\BaselineController;
use App\Modules\Security\Http\Controllers\ExceptionsController;
use App\Modules\Security\Http\Controllers\PrivilegedAccessController;
use App\Modules\Security\Http\Controllers\SecurityEventsController;
use App\Modules\SystemHealth\Http\Controllers\SystemHealthController;
use Illuminate\Support\Facades\Route;

/*
 * Pre-authentication entry: the Login page. Carries no shell, no menu and no
 * business metadata, as the blueprint requires of anything served to an
 * unauthenticated browser.
 */
Route::get('/', EntryController::class)->name('entry');

/*
 * Microsoft Entra ID.
 *
 * /auth/microsoft/callback is the single URI registered in Entra. Bootstrap and
 * normal sign-in share it, distinguished by intent held in the session.
 */
Route::prefix('auth')->name('auth.')->group(function (): void {
    Route::get('microsoft/redirect', RedirectController::class)->name('microsoft.redirect');
    Route::get('microsoft/callback', CallbackController::class)->name('microsoft.callback');

    /*
     * D-73: a DISTINCT callback for step-up returns.
     *
     * Separate from the sign-in callback on purpose. Sharing it would mean an
     * ordinary sign-in and a re-authentication arrive at the same code holding
     * the same session values, and the only thing telling them apart would be
     * intent stored somewhere - which is exactly the kind of distinction that
     * fails silently.
     *
     * THIS URI MUST BE REGISTERED IN ENTRA as a second redirect URI before
     * step-up works in production. See doc/v2/phase-1/P1-05-DEPLOYMENT-NOTE.md.
     */
    Route::get('microsoft/step-up', [StepUpController::class, 'callback'])
        ->middleware(EnsureSessionIsCurrent::class)
        ->name('microsoft.step-up');

    // POST only: a GET logout is triggerable by any third-party page.
    Route::post('logout', LogoutController::class)->name('logout');

    /*
     * Refusal and outcome states. Pre-authentication, standalone cards, no
     * shell. They are routes rather than inline renders so a refusal can be
     * redirected to without carrying any of the failed request with it.
     */
    foreach ([
        'access-not-assigned',
        'account-inactive',
        'access-denied',
        'session-expired',
        'signed-out',
        'sign-in-unavailable',
    ] as $state) {
        Route::get($state, fn () => (new StateController)($state))->name($state);
    }
});

/*
 * First-run bootstrap.
 *
 * Deliberately NOT /bootstrap: that is one of the directories the Apache
 * boundary refuses, so a route there would return 403 in production while
 * passing every local test. RoutePrefixCollisionTest enforces this.
 */
Route::prefix('first-run')->name('first_run.')->group(function (): void {
    Route::get('closed', fn () => (new StateController)('bootstrap-closed'))->name('closed');

    /*
     * THE GRANT ROUTE IS CONSTRAINED TO THE SHAPE A GRANT ACTUALLY HAS.
     *
     * It used to be Route::get('{grant}', ...) with no constraint, and `closed`
     * above survived only because it is declared FIRST. Declaration order is a
     * habit, not a guarantee: it is invisible at the call site, it is lost by
     * any refactor that sorts or regroups these lines, and it silently stops
     * protecting anything the moment a static route is added below.
     *
     * P1-10 adds eight static routes at this same depth - sign-in, identity,
     * email, ai, fabric, first-administrator, complete, sign-out - so
     * /first-run/sign-in would have been swallowed as a grant token. That is
     * the collision class P1-03 correction 1 and P1-04 were written against,
     * arriving a third time.
     *
     * GrantIssuer generates Str::random(64), so this is the token's real shape.
     * No static route can match it: every one of the eight is far shorter than
     * 64 characters and several contain a hyphen, which the character class
     * excludes. The constraint does not make the ambiguity unlikely - it makes
     * it UNREPRESENTABLE, which is the only version that survives a reorder.
     *
     * FirstRunRoutesDoNotCollide asserts this with the route collection
     * re-registered in REVERSE declaration order, so a test that passes only
     * because of ordering fails.
     */
    /*
     * P1-10. THE LOCAL SETUP SURFACE.
     *
     * Outside the `console` prefix and outside EnsureSessionIsCurrent, because
     * that middleware resolves a User and the bootstrap principal is not one
     * (D-166). RequireBootstrapSession sets `semantiq_bootstrap` instead, and
     * the two attributes are disjoint: every console route reads
     * `semantiq_user`, which a bootstrap request never carries.
     *
     * SIGN-IN AND RECOVERY SIT OUTSIDE THE GUARD, necessarily - they are how a
     * bootstrap session is obtained in the first place.
     */
    Route::get('sign-in', [FirstRunController::class, 'signInForm'])->name('sign_in');
    Route::post('sign-in', [FirstRunController::class, 'signIn'])->name('sign_in.submit');
    Route::get('recover', [FirstRunController::class, 'recoverForm'])->name('recover');
    Route::post('recover', [FirstRunController::class, 'recover'])->name('recover.submit');

    Route::middleware(RequireBootstrapSession::class)->group(function (): void {
        Route::post('sign-out', [FirstRunController::class, 'signOut'])->name('sign_out');

        Route::get('/', [FirstRunController::class, 'overview'])->name('overview');

        Route::get('first-administrator', [FirstRunController::class, 'nominateForm'])
            ->name('first_administrator');
        Route::post('first-administrator', [FirstRunController::class, 'nominate'])
            ->name('first_administrator.submit');

        Route::get('complete', [FirstRunController::class, 'complete'])->name('complete');

        /*
         * The four integration screens, by family.
         *
         * `{family}` is constrained to the four names IntegrationFamily
         * declares. Without the constraint this would match `sign-in`,
         * `recover`, `complete` and `first-administrator` too - the same
         * collision class as the grant route above, one level down, and the
         * reason FirstRunRoutesDoNotCollide reverses the declaration order
         * rather than trusting it.
         */
        Route::get('integration/{family}', [FirstRunController::class, 'family'])
            ->where('family', 'identity|email|ai|fabric')
            ->name('integration');

        Route::put('integration/{family}', [IntegrationController::class, 'update'])
            ->where('family', 'identity|email|ai|fabric')
            ->name('integration.update');

        Route::post('integration/{family}/test', [IntegrationController::class, 'test'])
            ->where('family', 'identity|email|ai|fabric')
            ->name('integration.test');

        /*
         * Explicit credential removal during setup - Gate C correction 4B.
         *
         * IDENTITY IS NOT IN THE CONSTRAINT, unlike the three routes above.
         * First-Run may ESTABLISH and REPLACE Microsoft sign-in, because the
         * Bootstrap principal cannot reach P1-02's console screens and setup
         * would otherwise be unsatisfiable. Removing it is a different matter:
         * it is the one required family, removal mid-setup only makes the
         * deployment less finishable, and P1-02 owns taking it away once there
         * is anybody who can sign in to do so.
         *
         * Like the console route, this is a SEPARATE VERB and never inferred
         * from a blank password field.
         */
        Route::delete('integration/{family}/secret/{name}', [IntegrationController::class, 'removeSecret'])
            ->where('family', 'email|ai|fabric')
            ->where('name', '[a-z_]{1,64}')
            ->name('integration.secret.remove');

        /*
         * D-153 during setup. The recipient is the BOOTSTRAP ADMINISTRATOR'S
         * configured address - the one the operator typed when the local
         * account was created - resolved server-side exactly as on the console.
         *
         * It matters most here: setup is where the mail configuration is first
         * entered, and where "it authenticates but it cannot send" is most
         * likely to be discovered months later by somebody who never gets a
         * password reset.
         */
        Route::post('integration/email/send-test', [IntegrationController::class, 'sendTestEmail'])
            ->name('integration.email.send_test');
    });

    Route::get('{grant}', BeginController::class)
        ->where('grant', '[A-Za-z0-9]{64}')
        ->name('begin');
});

/*
 * The authenticated area. Deny by default: every request re-checks the session,
 * the absolute lifetime and that the user is still active, before anything
 * protected is served.
 *
 * The prefix is deliberately NOT "app" - see RoutePrefixCollisionTest.
 */
Route::prefix('console')
    ->middleware(EnsureSessionIsCurrent::class)
    ->group(function (): void {
        Route::get('/', ConsoleController::class)->name('console.home');

        /*
         * P1-11 - ADMINISTRATION HOME. ONE GET, ONE VERB, NO PARAMETERS.
         *
         * Nothing on this screen changes anything, so there is nothing for a
         * second verb to do. AdministrationHomeIsAProjectionTest asserts this
         * as an EQUALITY over every route whose URI begins `console/
         * administration`, so a second verb fails the build rather than being
         * noticed in review.
         *
         * OrgAdmin - D-132. The blueprint names a "platform/organisation
         * administrator" as this screen's audience, and narrowing the route to
         * PlatformAdmin to match an implementation detail of the sidebar was
         * the option D-182 explicitly refused.
         *
         * RequireOrganisation IS DELIBERATELY ABSENT - D-133. A deployment
         * whose company profile has not been created is exactly the deployment
         * that needs this screen, and bouncing it to Company Profile would make
         * the page that exists to say "set up the Organisation first"
         * unreachable until somebody had.
         *
         * /console IS UNCHANGED - D-131. It keeps its D-11 confirmation state
         * and stays reachable by anybody with a session, including a person
         * holding no role at all. This is not that page and must not become it.
         */
        Route::middleware(RequireActionClass::class.':'.ActionClass::OrgAdmin->value)
            ->get('administration', [AdministrationHomeController::class, 'show'])
            ->name('administration.home');

        /*
         * P1-01 - Organisation.
         *
         * Every route re-authorises through RequireActionClass, declaring the
         * class it actually needs. Menu visibility is never the control: if the
         * navigation filter were wrong, the request would still be refused
         * here.
         *
         * The prefix is 'organisation', which is deliberately NOT one of the
         * directories the Apache boundary refuses - RoutePrefixCollisionTest
         * guards that in both directions.
         *
         * DELETE IS REGISTERED FOR EXACTLY FOUR URIS - the D-24 guarded purge
         * of a legal entity, business unit, department or team, and nothing
         * else. There is deliberately no DELETE for the organisation, for team
         * memberships or for management relationships: those carry the history
         * the rest of the unit is built to keep. LifecycleCompletenessTest
         * asserts that exact set, so a fifth DELETE fails the build.
         */
        Route::middleware(RequireActionClass::class.':'.ActionClass::OrgAdmin->value)
            ->prefix('organisation')
            ->name('organisation.')
            ->group(function (): void {
                // The Company Profile is outside RequireOrganisation: it is the
                // screen that creates the organisation, so requiring one would
                // make it unreachable.
                Route::get('/', [ProfileController::class, 'show'])->name('profile');
                Route::post('/', [ProfileController::class, 'store'])->name('profile.store');
                Route::put('/', [ProfileController::class, 'update'])->name('profile.update');

                Route::middleware(RequireOrganisation::class)->group(function (): void {
                    Route::get('legal-entities', [LegalEntityController::class, 'index'])->name('legal-entities');
                    Route::post('legal-entities', [LegalEntityController::class, 'store'])->name('legal-entities.store');
                    Route::put('legal-entities/{legalEntity}', [LegalEntityController::class, 'update'])->name('legal-entities.update');
                    Route::delete('legal-entities/{legalEntity}', [LegalEntityController::class, 'purge'])->name('legal-entities.purge');
                    Route::patch('legal-entities/{legalEntity}/deactivate', [LegalEntityController::class, 'deactivate'])->name('legal-entities.deactivate');
                    Route::patch('legal-entities/{legalEntity}/reactivate', [LegalEntityController::class, 'reactivate'])->name('legal-entities.reactivate');

                    Route::get('business-units', [BusinessUnitController::class, 'index'])->name('business-units');
                    Route::post('business-units', [BusinessUnitController::class, 'store'])->name('business-units.store');
                    Route::get('business-units/{businessUnit}', [BusinessUnitController::class, 'show'])->name('business-unit');
                    Route::put('business-units/{businessUnit}', [BusinessUnitController::class, 'update'])->name('business-units.update');
                    Route::delete('business-units/{businessUnit}', [BusinessUnitController::class, 'purge'])->name('business-units.purge');
                    Route::patch('business-units/{businessUnit}/deactivate', [BusinessUnitController::class, 'deactivate'])->name('business-units.deactivate');
                    Route::patch('business-units/{businessUnit}/reactivate', [BusinessUnitController::class, 'reactivate'])->name('business-units.reactivate');

                    // D-14: associate and dissociate, both directions many.
                    Route::post('business-units/{businessUnit}/legal-entities', [BusinessUnitController::class, 'associate'])->name('business-units.associate');
                    Route::patch('business-units/{businessUnit}/legal-entities/{legalEntity}/dissociate', [BusinessUnitController::class, 'dissociate'])->name('business-units.dissociate');

                    Route::get('departments', [DepartmentController::class, 'index'])->name('departments');
                    Route::post('departments', [DepartmentController::class, 'store'])->name('departments.store');
                    Route::put('departments/{department}', [DepartmentController::class, 'update'])->name('departments.update');
                    Route::delete('departments/{department}', [DepartmentController::class, 'purge'])->name('departments.purge');
                    Route::patch('departments/{department}/move', [DepartmentController::class, 'move'])->name('departments.move');
                    Route::patch('departments/{department}/deactivate', [DepartmentController::class, 'deactivate'])->name('departments.deactivate');
                    Route::patch('departments/{department}/reactivate', [DepartmentController::class, 'reactivate'])->name('departments.reactivate');

                    Route::get('teams', [TeamController::class, 'index'])->name('teams');
                    Route::post('teams', [TeamController::class, 'store'])->name('teams.store');
                    Route::get('teams/{team}', [TeamController::class, 'show'])->name('team');
                    Route::put('teams/{team}', [TeamController::class, 'update'])->name('teams.update');
                    Route::delete('teams/{team}', [TeamController::class, 'purge'])->name('teams.purge');
                    Route::patch('teams/{team}/move', [TeamController::class, 'move'])->name('teams.move');
                    Route::patch('teams/{team}/deactivate', [TeamController::class, 'deactivate'])->name('teams.deactivate');
                    Route::patch('teams/{team}/reactivate', [TeamController::class, 'reactivate'])->name('teams.reactivate');

                    // Removing a member sets left_at and retains the row, so this
                    // is a PATCH and not a DELETE. The verb is the honest one.
                    Route::post('teams/{team}/members', [TeamController::class, 'addMember'])->name('teams.members.add');
                    Route::patch('teams/{team}/members/{membership}/remove', [TeamController::class, 'removeMember'])->name('teams.members.remove');

                    Route::get('hierarchy', [HierarchyController::class, 'index'])->name('hierarchy');
                    Route::post('hierarchy', [HierarchyController::class, 'setManager'])->name('hierarchy.set');
                    Route::patch('hierarchy/{user}/clear', [HierarchyController::class, 'clearManager'])->name('hierarchy.clear');
                });
            });

        /*
         * P1-02 - Identity & SSO.
         *
         * A WINDOW ONTO THE FRONT DOOR, NEVER A HANDLE ON IT. Five GET screens
         * and exactly two POSTs, and neither POST writes business data: one
         * probes Microsoft and updates a cache, one returns a value it read.
         *
         * There is deliberately no PUT, PATCH or DELETE anywhere under this
         * prefix, and no route that could write .env. IdentityArchitectureTest
         * asserts that exact set, so a write route added later fails the build
         * rather than quietly becoming the .env editor this unit is defined as
         * not having.
         *
         * Every route re-authorises through RequireActionClass. P1-05 replaced
         * the old RequireSystemAdministrator with it - route by route, never as
         * a blanket substitution.
         */
        /*
         * P1-03 - Users & Groups.
         *
         * EVERY DYNAMIC SEGMENT SITS ONE LEVEL BELOW A STATIC ONE. `users` and
         * `groups` are both static and at the same depth, so no route can
         * capture the other's name.
         *
         * The first design put user records at /console/people/{user} beside
         * /console/people/groups and claimed the collision was gone because the
         * prefix had been renamed from `users` to `people`. It had not: a
         * dynamic segment and a static one at the same depth clash whatever
         * their parent is called, and the proof was that correctness needed
         * both declaration order and a whereNumber to hold. It now needs
         * neither. The numeric constraints below are defence in depth, and
         * PeopleRoutingTest asserts the set still resolves when the file is
         * read in reverse.
         *
         * RequireOrganisation is used from the Organisation module rather than
         * promoted to Platform: it depends on OrganisationService and redirects
         * to the Company Profile, so moving it would make Platform depend
         * backwards on Organisation.
         */
        Route::middleware([RequireActionClass::class.':'.ActionClass::OrgAdmin->value, RequireOrganisation::class])
            ->prefix('people')
            ->name('people.')
            ->group(function (): void {
                Route::get('/', fn () => redirect()->route('people.users'));

                Route::get('users', [UserController::class, 'index'])->name('users');
                Route::post('users', [UserController::class, 'store'])->name('users.store');
                Route::get('users/{user}', [UserController::class, 'show'])->name('user')->whereNumber('user');
                Route::put('users/{user}', [UserController::class, 'update'])->name('users.update')->whereNumber('user');
                Route::patch('users/{user}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate')->whereNumber('user');
                Route::patch('users/{user}/reactivate', [UserController::class, 'reactivate'])->name('users.reactivate')->whereNumber('user');
                Route::post('users/{user}/reveal', [UserController::class, 'reveal'])->name('users.reveal')->whereNumber('user');
                Route::delete('users/{user}', [UserController::class, 'purge'])->name('users.purge')->whereNumber('user');

                Route::get('groups', [GroupController::class, 'index'])->name('groups');
                Route::post('groups', [GroupController::class, 'store'])->name('groups.store');
                Route::get('groups/{group}', [GroupController::class, 'show'])->name('group')->whereNumber('group');
                Route::put('groups/{group}', [GroupController::class, 'update'])->name('groups.update')->whereNumber('group');
                Route::patch('groups/{group}/deactivate', [GroupController::class, 'deactivate'])->name('groups.deactivate')->whereNumber('group');
                Route::patch('groups/{group}/reactivate', [GroupController::class, 'reactivate'])->name('groups.reactivate')->whereNumber('group');
                Route::delete('groups/{group}', [GroupController::class, 'purge'])->name('groups.purge')->whereNumber('group');

                Route::post('groups/{group}/members', [GroupController::class, 'addMember'])->name('groups.members.add')->whereNumber('group');
                Route::patch('groups/{group}/members/{membership}/remove', [GroupController::class, 'removeMember'])->name('groups.members.remove')->whereNumber('group')->whereNumber('membership');
            });

        /*
         * P1-04 - Business Domains.
         *
         * ONE LIST AND ONE RECORD PAGE, no tabs: this unit delivers ONE kind of
         * thing, and baseline and custom domains are the same object with a
         * different origin.
         *
         * {domain} is the only thing at its depth. There is no static segment
         * beside it, which is the collision correction 1 of P1-03 was written
         * for; whereNumber is defence in depth rather than the mechanism, and
         * DomainRoutingTest asserts the set still resolves when this file is
         * read in reverse.
         *
         * owner/clear is a PATCH, not a DELETE. In this codebase DELETE means a
         * record is permanently destroyed and the complete DELETE route set is
         * asserted; clearing an owner ends a period and destroys nothing, so a
         * DELETE would both misdescribe it and weaken that assertion.
         *
         * A DOMAIN GRANTS NOTHING. Every route below is behind the same two
         * gates as People, and no route anywhere reads a domain to decide what
         * a person may see.
         */
        Route::middleware([RequireActionClass::class.':'.ActionClass::OrgAdmin->value, RequireOrganisation::class])
            ->prefix('domains')
            ->name('domains.')
            ->group(function (): void {
                Route::get('/', [DomainController::class, 'index'])->name('index');
                Route::post('/', [DomainController::class, 'store'])->name('store');

                Route::get('{domain}', [DomainController::class, 'show'])->name('show')->whereNumber('domain');
                Route::put('{domain}', [DomainController::class, 'update'])->name('update')->whereNumber('domain');
                Route::delete('{domain}', [DomainController::class, 'purge'])->name('purge')->whereNumber('domain');

                Route::patch('{domain}/enable', [DomainController::class, 'enable'])->name('enable')->whereNumber('domain');
                Route::patch('{domain}/disable', [DomainController::class, 'disable'])->name('disable')->whereNumber('domain');
                Route::patch('{domain}/owner', [DomainController::class, 'setOwner'])->name('owner.set')->whereNumber('domain');
                Route::patch('{domain}/owner/clear', [DomainController::class, 'clearOwner'])->name('owner.clear')->whereNumber('domain');
            });

        /*
         * P1-02 - Identity & SSO. PLATFORM_ADMIN, all seven, and that is the
         * one line of this file most worth reading twice.
         *
         * Every other console prefix moved to ORG_ADMIN so an Organisation
         * Administrator can reach it. These seven did NOT. Identity & SSO is
         * the front door of the whole product, and opening one of them to
         * ORG_ADMIN is precisely the outcome a find-and-replace of the old
         * middleware would have produced - which is why the routes were
         * enumerated rather than swapped. N-E9 breaks it by opening one.
         */
        /*
         * P1-05 - Roles & Access. ACCESS_ADMIN.
         *
         * FIVE SCREENS AND ONE RECORD PAGE. Role Assignments is the list;
         * everything below an assignment - entitlements, scopes, ceilings -
         * lives on the record page for that assignment, because a scope means
         * nothing without the entitlement it hangs from.
         *
         * NOTHING HERE IS A DELETE. Every removal ends a period, so every one
         * is a PATCH. In this codebase DELETE means a record is permanently
         * destroyed and the complete DELETE route set is asserted; a DELETE
         * here would both misdescribe the operation and weaken that assertion.
         * N-L1 breaks it by adding one.
         *
         * EVERY DYNAMIC SEGMENT SITS ONE LEVEL BELOW A STATIC ONE -
         * assignments/{assignment}, entitlements/{entitlement},
         * scopes/{scope}, step-up/{reference}. The first draft of this file put
         * {assignment} directly under /access beside the static `entitlements`,
         * `scopes` and `simulator` segments, which is EXACTLY the collision
         * P1-03 correction 1 was written for: a dynamic segment and a static
         * one at the same depth clash whatever their parent is called.
         *
         * whereNumber is defence in depth rather than the mechanism, and
         * AccessRoutingTest asserts the set still resolves when this file is
         * read in reverse.
         */
        Route::middleware([RequireActionClass::class.':'.ActionClass::AccessAdmin->value, RequireOrganisation::class])
            ->prefix('access')
            ->name('access.')
            ->group(function (): void {
                Route::get('/', [AccessController::class, 'index'])->name('index');
                Route::post('assignments', [AccessController::class, 'assignRole'])->name('roles.assign');

                Route::get('assignments/{assignment}', [AccessController::class, 'show'])->name('show')->whereNumber('assignment');
                Route::patch('assignments/{assignment}/revoke', [AccessController::class, 'revokeRole'])->name('roles.revoke')->whereNumber('assignment');
                Route::post('assignments/{assignment}/entitlements', [AccessController::class, 'grantEntitlement'])->name('entitlements.grant')->whereNumber('assignment');

                Route::patch('entitlements/{entitlement}/revoke', [AccessController::class, 'revokeEntitlement'])->name('entitlements.revoke')->whereNumber('entitlement');
                Route::post('entitlements/{entitlement}/scopes', [AccessController::class, 'assignScope'])->name('scopes.assign')->whereNumber('entitlement');
                Route::patch('entitlements/{entitlement}/ceiling', [AccessController::class, 'setCeiling'])->name('ceiling.set')->whereNumber('entitlement');

                Route::patch('scopes/{scope}/revoke', [AccessController::class, 'revokeScope'])->name('scopes.revoke')->whereNumber('scope');

                /*
                 * The Access Simulator. GET renders it; POST asks a question.
                 *
                 * The POST WRITES NOTHING. It runs the real engine against
                 * proposed state inside a transaction that always rolls back,
                 * and it is a POST rather than a GET because it carries a body,
                 * not because it changes anything.
                 */
                Route::get('simulator/run', [SimulatorController::class, 'show'])->name('simulator');
                Route::post('simulator/run', [SimulatorController::class, 'simulate'])->name('simulator.run');

                /*
                 * D-73 step-up. The confirmation card and the departure to
                 * Microsoft. The RETURN is at auth/microsoft/step-up, beside
                 * the sign-in callback, because that URI is what Entra
                 * redirects to.
                 *
                 * The reference is opaque and single-use. It appears in the
                 * address bar only between these two routes, and is moved into
                 * the session before anybody leaves for Microsoft.
                 */
                Route::get('step-up/{reference}', [StepUpController::class, 'begin'])->name('step-up.begin');
                Route::post('step-up/{reference}', [StepUpController::class, 'redirect'])->name('step-up.redirect');
            });

        /*
         * P1-06 - Security Status. EVIDENCE_READ, and EVERY ROUTE IS A GET.
         *
         * FOUR ROUTES, FOUR GETS, AND NO OTHER VERB UNDER THIS PREFIX AT ALL.
         * SecurityStatusArchitectureTest asserts that exact set, so a POST
         * added later fails the build. That is how "a mandatory control cannot
         * be switched off from these screens" is enforced STRUCTURALLY rather
         * than asserted in prose: there is no route that could carry the
         * switch, no acknowledgement, no dismissal and no override.
         *
         * REMEDIATION IS NAVIGATION. Every row's affordance is a link to the
         * screen that OWNS the control - Identity & SSO, Roles & Access,
         * Business Domains, Users & Groups - and that screen re-authorises on
         * arrival through its own RequireActionClass. Visibility here is never
         * permission there, which is the P1-05 lesson applied.
         *
         * EVIDENCE_READ, NOT A NEW CLASS. System Administrator, Organisation
         * Administrator and Auditor already hold it, and it is exactly the
         * right class: read the evidence, change nothing. No role is broadened.
         * What an Organisation Administrator or Auditor may VALUE is narrower
         * than what they may reach, and that is decided in the projection -
         * platform rows are named but not valued (D-76).
         *
         * RequireOrganisation IS DELIBERATELY ABSENT. Every other console
         * prefix carries it; this one must not. Posture on a deployment that
         * has not been configured yet is exactly the day-one screen this unit
         * exists to provide, and RequireOrganisation would redirect it to the
         * Company Profile.
         *
         * D-82: FOUR SUBSCREENS. Domain posture is a separated section within
         * Privileged Access Health, not a fifth tab.
         *
         * There is no dynamic segment anywhere below, so the collision P1-03
         * correction 1 and P1-04 were written against cannot occur here.
         */
        Route::middleware(RequireActionClass::class.':'.ActionClass::EvidenceRead->value)
            ->prefix('security')
            ->name('security.')
            ->group(function (): void {
                Route::get('/', [BaselineController::class, 'show'])->name('baseline');
                Route::get('privileged-access', [PrivilegedAccessController::class, 'show'])->name('privileged');
                Route::get('exceptions', [ExceptionsController::class, 'show'])->name('exceptions');
                Route::get('events', [SecurityEventsController::class, 'show'])->name('events');
            });

        /*
         * P1-07 Access Reviews.
         *
         * READS AT EvidenceRead - System Administrator, Organisation
         * Administrator and Auditor. Auditor decides nothing, and that falls
         * out of the authority algorithm rather than being a special case:
         * RoleCatalogue::grantableBy(Auditor) is empty.
         *
         * DECISIONS AT AccessAdmin, and the per-item authority is re-checked in
         * the controller AND again inside the service's transaction. Neither is
         * sufficient alone: the class admits you to the endpoint, the algorithm
         * decides the row.
         *
         * READING IS NOT DECIDING. An Auditor holds EvidenceRead and so reads
         * the evidence; grantableBy(Auditor) is empty, so they decide nothing.
         * The listing shows them rows with no action, which is what read-only
         * means - the first implementation filtered the listing by decision
         * authority and showed them an empty screen.
         *
         * RequireOrganisation IS PRESENT on both groups. Reviews are about one
         * organisation's access, and the System Administrator role is
         * platform-scoped - so without it, "the actor's assignment has no
         * organisation" would quietly mean "every organisation".
         *
         * B-1: no BUSINESS role holds an administration class, so a domain
         * owner cannot pass either gate today. The owner basis is implemented
         * and tested; reaching it through the UI waits on D-19.
         */
        Route::middleware([RequireActionClass::class.':'.ActionClass::EvidenceRead->value, RequireOrganisation::class])
            ->prefix('access-reviews')
            ->name('access-reviews.')
            ->group(function (): void {
                Route::get('/', [AccessReviewsController::class, 'privileged'])->name('privileged');
                Route::get('domains', [AccessReviewsController::class, 'domains'])->name('domains');
                Route::get('overdue', [AccessReviewsController::class, 'overdue'])->name('overdue');
            });

        Route::middleware([RequireActionClass::class.':'.ActionClass::AccessAdmin->value, RequireOrganisation::class])
            ->prefix('access-reviews')
            ->name('access-reviews.')
            ->group(function (): void {
                Route::post('cycles', [AccessReviewDecisionController::class, 'startCycle'])->name('cycles.start');
                Route::post('items/{item}/decide', [AccessReviewDecisionController::class, 'decide'])->name('items.decide')->whereNumber('item');
            });

        /*
         * P1-08 Audit. FOUR GETs AND NOTHING ELSE.
         *
         * There is deliberately no POST, PATCH, PUT or DELETE under this
         * prefix, and AuditImmutabilityTest asserts the set as an EQUALITY - so
         * a fifth verb fails the build rather than quietly becoming an edit
         * path. That is D-97's "no application update or delete path", enforced
         * rather than intended.
         *
         * EvidenceRead admits System Administrator, Organisation Administrator
         * and Auditor to the ENDPOINT. Which ROWS and which FIELDS each may
         * read is decided by AuditProjection - D-99 and D-100 - because the
         * action class is not the projection, and conflating the two is what
         * showed an Auditor an empty Security Status screen in P1-06.
         *
         * RequireOrganisation is present for the same reason as on Access
         * Reviews: the System Administrator role is platform-scoped, so without
         * it "the actor's assignment has no organisation" would quietly mean
         * "every organisation".
         */
        Route::middleware([RequireActionClass::class.':'.ActionClass::EvidenceRead->value, RequireOrganisation::class])
            ->prefix('audit')
            ->name('audit.')
            ->group(function (): void {
                Route::get('/', [AuditController::class, 'userAccess'])->name('user-access');
                Route::get('admin-changes', [AuditController::class, 'adminChanges'])->name('admin-changes');
                Route::get('security-events', [AuditController::class, 'securityEvents'])->name('security-events');
                Route::get('configuration', [AuditController::class, 'configuration'])->name('configuration');
            });

        /*
         * P1-09 System Health. ONE GET, AND NO OTHER VERB AT ALL.
         *
         * SystemHealthArchitectureTest asserts this set as an EQUALITY, so a
         * POST added later fails the build. That is how "this page cannot
         * change anything" is enforced structurally rather than intended:
         * there is no route that could carry a restart, a cache clear, an
         * acknowledgement or a dismissal.
         *
         * PlatformAdmin, NOT EvidenceRead, and the difference is the point.
         * Evidence access and infrastructure access are different authorities:
         * an Auditor reads what happened; an operator reads whether the
         * machine is working. Placing this behind EvidenceRead would hand an
         * Auditor and an Organisation Administrator a view of the deployment's
         * infrastructure that nobody decided to give them. D-117.
         *
         * RequireOrganisation IS DELIBERATELY ABSENT, for the same reason it is
         * absent from Security Status: infrastructure health is not one
         * organisation's fact, and a deployment whose organisation is not
         * configured yet is exactly when somebody needs this screen.
         *
         * The Check sign-in now control posts to identity.health.recheck in the
         * group below. P1-09 adds no write path of its own.
         */
        Route::middleware(RequireActionClass::class.':'.ActionClass::PlatformAdmin->value)
            ->prefix('system-health')
            ->name('system-health.')
            ->group(function (): void {
                Route::get('/', [SystemHealthController::class, 'show'])->name('show');
            });

        /*
         * P1-10. PLATFORM INTEGRATIONS - the same settings First-Run writes,
         * after setup is over.
         *
         * PlatformAdmin, like System Health and Identity, and for the same
         * reason: these are deployment-wide facts and credentials, not one
         * organisation's. RequireOrganisation is deliberately absent - a
         * deployment whose organisation is not configured yet is exactly when
         * somebody needs this screen.
         *
         * It shares IntegrationController with First-Run on purpose. Two
         * controllers would be two validation rules, two invalidation paths
         * and two chances to forget one - and "saving" would come to mean
         * something slightly different depending on which screen you were on.
         *
         * THERE IS NO REVEAL VERB HERE FOR ANY SECRET, and none anywhere in
         * the route table. NoSecretRevealRouteExists asserts that as an
         * equality rather than by naming the routes that do exist.
         */
        Route::middleware(RequireActionClass::class.':'.ActionClass::PlatformAdmin->value)
            ->prefix('integrations')
            ->name('integrations.')
            ->group(function (): void {
                Route::get('/', [IntegrationController::class, 'index'])->name('show');

                /*
                 * GATE D UI CORRECTION. ONE ROUTE PER TAB, Pattern B.
                 *
                 * The four integrations were four cards stacked on one URL.
                 * They are now four tabs, and a tab in this product is a real
                 * link to a real URL - so browser Back works, a refresh keeps
                 * the section, and a section can be linked to directly. A
                 * client-only switch hiding four screens behind one URL breaks
                 * every one of those.
                 *
                 * MICROSOFT ENTRA ID IS THE BARE PATH, exactly as Company
                 * Profile is /console/organisation. `integrations.show` is the
                 * name ApprovedMenu already points the menu leaf at, so the
                 * first tab keeps working without the navigation knowing
                 * anything changed.
                 *
                 * THERE IS DELIBERATELY NO /console/integrations/identity.
                 *
                 * A redirect there was written first, because it is the obvious
                 * address to guess. It was removed when its cost showed up in a
                 * guard rather than in review: PostInstallSsoChange asserts that
                 * PUT /console/integrations/identity is NOT FOUND, and once any
                 * verb answered on that URI the same request became a 405. The
                 * claim survives either way - neither status writes anything -
                 * but "that route does not exist" is a stronger sentence than
                 * "that method is not allowed there", and it is the sentence
                 * D-148 is written in.
                 *
                 * A convenience URL is not worth trading it for. Company
                 * Profile has no /console/organisation/profile either.
                 *
                 * NO NEW WRITE VERB. This is a GET. The PUT, POST and DELETE
                 * constraints below are untouched, and identity still has none
                 * of them: IdentityIsNotWritableOnTheConsole asserts the whole
                 * console set as an equality, so it had to be justified there
                 * before it could be added here.
                 */
                Route::get('{family}', [IntegrationController::class, 'show'])
                    ->where('family', 'email|ai|fabric')
                    ->name('family');

                /*
                 * D-148. THE WRITABLE SET EXCLUDES IDENTITY, IN THE ROUTE
                 * CONSTRAINT.
                 *
                 * Not in the controller, and not by a check inside update() -
                 * in the constraint, so PUT /console/integrations/identity
                 * does not resolve to a route at all. A controller-level
                 * refusal is a refusal somebody can weaken; a route that does
                 * not exist has nothing to weaken.
                 *
                 * P1-02 owns Microsoft Entra configuration after installation.
                 * The card on this screen is a SUMMARY AND A LINK to it.
                 *
                 * First-Run keeps its identity form, because the Bootstrap
                 * principal cannot reach P1-02's console screens - see
                 * IntegrationFamily::writableOnTheConsole().
                 */
                Route::put('{family}', [IntegrationController::class, 'update'])
                    ->where('family', 'email|ai|fabric')
                    ->name('update');

                Route::post('{family}/test', [IntegrationController::class, 'test'])
                    ->where('family', 'email|ai|fabric')
                    ->name('test');

                /*
                 * Explicit credential removal - Gate C correction 4B.
                 *
                 * A SEPARATE ROUTE, never inferred from a blank password
                 * field. A blank field means "keep what is saved", which is
                 * what the form says it means; making it also mean "delete the
                 * credential" would destroy a working integration for anybody
                 * who opened the page to change a port.
                 */
                Route::delete('{family}/secret/{name}', [IntegrationController::class, 'removeSecret'])
                    ->where('family', 'email|ai|fabric')
                    ->where('name', '[a-z_]{1,64}')
                    ->name('secret.remove');

                /*
                 * D-153. SEND ONE TEST MESSAGE - Gate C round 3.
                 *
                 * NO {family} AND NO RECIPIENT, and neither is an omission.
                 * Email is the only family that can send anything, so a family
                 * parameter would be a parameter with one legal value; and the
                 * recipient is the signed-in administrator's own address,
                 * resolved server-side, because a test that can be pointed at
                 * an address is an open relay with a diagnostic's name on it.
                 *
                 * Test connection proves the server accepts the credentials.
                 * This proves it accepts a MESSAGE from the configured From
                 * address, which is a different permission and the one that
                 * actually fails in production.
                 */
                Route::post('email/send-test', [IntegrationController::class, 'sendTestEmail'])
                    ->name('email.send_test');
            });

        Route::middleware(RequireActionClass::class.':'.ActionClass::PlatformAdmin->value)
            ->prefix('identity')
            ->name('identity.')
            ->group(function (): void {
                Route::get('/', [EntraController::class, 'show'])->name('entra');
                Route::post('entra/reveal', [EntraController::class, 'reveal'])->name('entra.reveal');

                /*
                 * GATE C ROUND 3. MICROSOFT SIGN-IN IS CONFIGURABLE AFTER
                 * INSTALLATION, AND ONLY FROM HERE.
                 *
                 * P1-02 owns identity. Platform Integrations shows a summary
                 * and links to this screen, and has no identity write route at
                 * all - IdentityIsNotWritableOnTheConsole asserts that as an
                 * equality, so this pair cannot be quietly duplicated there.
                 *
                 * The PUT stages a candidate and redirects to Microsoft. It
                 * writes nothing: the live configuration is the only way
                 * anybody signs in, and it is not touched until a confirmation
                 * comes back AND the candidate answers a discovery round trip.
                 */
                Route::get('entra/change', [EntraController::class, 'edit'])->name('entra.edit');
                Route::put('entra', [EntraController::class, 'update'])->name('entra.update');

                Route::get('providers', [ProvidersController::class, 'show'])->name('providers');
                Route::get('login-experience', [LoginExperienceController::class, 'show'])->name('login-experience');

                Route::get('health', [HealthController::class, 'show'])->name('health');
                Route::post('health/re-check', [HealthController::class, 'recheck'])->name('health.recheck');

                Route::get('session-policy', [SessionPolicyController::class, 'show'])->name('session-policy');
            });
    });
