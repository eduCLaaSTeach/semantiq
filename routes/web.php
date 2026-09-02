<?php

declare(strict_types=1);

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
use App\Modules\Platform\Http\Controllers\Auth\CallbackController;
use App\Modules\Platform\Http\Controllers\Auth\LogoutController;
use App\Modules\Platform\Http\Controllers\Auth\RedirectController;
use App\Modules\Platform\Http\Controllers\Auth\StateController;
use App\Modules\Platform\Http\Controllers\ConsoleController;
use App\Modules\Platform\Http\Controllers\EntryController;
use App\Modules\Platform\Http\Controllers\FirstRun\BeginController;
use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;
use App\Modules\Platform\Http\Middleware\RequireSystemAdministrator;
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
    Route::get('{grant}', BeginController::class)->name('begin');
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
         * P1-01 - Organisation.
         *
         * Every route re-authorises through RequireSystemAdministrator. Menu
         * visibility is never the control: if the navigation filter were wrong,
         * the request would still be refused here.
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
        Route::middleware(RequireSystemAdministrator::class)
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
         * Every route re-authorises through RequireSystemAdministrator, which
         * P1-02 promoted to Platform because it is now needed by two modules and
         * a second copy of an authorisation gate is the worst possible place for
         * two sources of truth.
         */
        Route::middleware(RequireSystemAdministrator::class)
            ->prefix('identity')
            ->name('identity.')
            ->group(function (): void {
                Route::get('/', [EntraController::class, 'show'])->name('entra');
                Route::post('entra/reveal', [EntraController::class, 'reveal'])->name('entra.reveal');

                Route::get('providers', [ProvidersController::class, 'show'])->name('providers');
                Route::get('login-experience', [LoginExperienceController::class, 'show'])->name('login-experience');

                Route::get('health', [HealthController::class, 'show'])->name('health');
                Route::post('health/re-check', [HealthController::class, 'recheck'])->name('health.recheck');

                Route::get('session-policy', [SessionPolicyController::class, 'show'])->name('session-policy');
            });
    });
