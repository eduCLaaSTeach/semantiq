<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\MicrosoftSignInController;
use App\Http\Controllers\Auth\SignInController;
use App\Http\Controllers\Pages\HomeController;
use App\Modules\Identity\Http\Controllers\AccessReviewController;
use App\Modules\Identity\Http\Controllers\AccessRoleController;
use App\Modules\Identity\Http\Controllers\BusinessUnitController;
use App\Modules\Identity\Http\Controllers\EntitlementController;
use App\Modules\Identity\Http\Controllers\OrganisationController;
use App\Modules\Identity\Http\Controllers\PermissionController;
use App\Modules\Identity\Http\Controllers\TeamController;
use App\Modules\Identity\Http\Controllers\UserController;
use App\Modules\Platform\Http\Controllers\DiagnosticsController;
use App\Modules\Platform\Http\Controllers\FeatureFlagController;
use App\Modules\Platform\Http\Controllers\PlatformOverviewController;
use App\Modules\Platform\Http\Controllers\SystemConfigurationController;
use Illuminate\Support\Facades\Route;

/*
 * Guest routes. The sign-in screen is the application's front door until the
 * shell exists behind it.
 */
Route::middleware('guest')->group(function (): void {
    Route::get('/sign-in', [SignInController::class, 'show'])->name('sign-in');
    Route::post('/sign-in', [SignInController::class, 'attempt'])->name('sign-in.attempt');

    /*
     * Starting the flow is a POST so it carries CSRF protection: a GET would let
     * any page on the internet bounce a visitor into a sign-in round trip.
     */
    Route::post('/sign-in/microsoft', [MicrosoftSignInController::class, 'redirect'])
        ->name('sign-in.microsoft');

    /*
     * Microsoft returns here as a GET, and it cannot carry our CSRF token, so
     * the single-use state parameter is what protects this leg instead.
     */
    Route::get('/auth/microsoft/callback', [MicrosoftSignInController::class, 'callback'])
        ->name('sign-in.microsoft.callback');

    /*
     * Password reset is deliberately not a form. Identity is federated, so a
     * directory account's password is reset in the directory and not here;
     * offering a reset form would imply this application holds a password it
     * does not own.
     */
    Route::view('/sign-in/password', 'auth.password-help')->name('password.request');
});

Route::post('/sign-out', [SignInController::class, 'signOut'])
    ->middleware('auth')
    ->name('sign-out');

/*
 * Signed-in routes. Every one of these renders inside the shell.
 *
 * The sidebar is filtered per person, but the sidebar is only the first of the
 * gate layers: a route the viewer's tier cannot reach must also be refused
 * here, or a guessed URL walks straight past the navigation.
 */
Route::middleware('auth')->group(function (): void {
    /*
     * The business experience. Home is the landing page: a business user lands
     * in business intelligence, never in Fabric setup.
     */
    Route::get('/', [HomeController::class, 'home'])
        ->middleware('policy:workspace')
        ->name('home');

    Route::get('/intelligence', [HomeController::class, 'intelligence'])
        ->middleware('policy:workspace')
        ->name('intelligence');

    Route::view('/profile', 'pages.profile')->name('profile');

    /*
     * The privileged control plane. The policy is enforced HERE and not only in
     * the sidebar: a filtered rail hides a link and does nothing at all about a
     * typed URL. ROLE_MODEL.md section 5, and a named Phase 00 acceptance
     * criterion.
     */
    /*
     * Organisation and Users - Release 1 gate 2, features ADM-002 to ADM-008.
     *
     * Gated by `permission:` rather than by `policy:`, so the route boundary
     * checks the SAME declared permission the rail node checks. ADM-007 wants
     * authorization at three layers - navigation, route, service - and these
     * are the second; the services check again for callers that never pass a
     * route at all.
     *
     * `policy:app-admin` stays in front as the cluster gate. Two gates rather
     * than one because they answer different questions: the cluster gate says
     * whether this person belongs in Application Administration, the permission
     * says whether they may do this specific thing.
     */
    Route::middleware('policy:app-admin')->prefix('admin')->name('admin.')->group(function (): void {
        Route::get('/organisation', [OrganisationController::class, 'edit'])
            ->middleware('permission:admin.organisation.view')->name('organisation');
        Route::put('/organisation', [OrganisationController::class, 'update'])
            ->middleware('permission:admin.organisation.update')->name('organisation.update');

        Route::get('/business-units', [BusinessUnitController::class, 'index'])
            ->middleware('permission:admin.business_units.view')->name('business-units');
        Route::get('/business-units/new', [BusinessUnitController::class, 'create'])
            ->middleware('permission:admin.business_units.manage')->name('business-units.create');
        Route::post('/business-units', [BusinessUnitController::class, 'store'])
            ->middleware('permission:admin.business_units.manage')->name('business-units.store');
        Route::get('/business-units/{businessUnit}', [BusinessUnitController::class, 'edit'])
            ->middleware('permission:admin.business_units.manage')->name('business-units.edit');
        Route::put('/business-units/{businessUnit}', [BusinessUnitController::class, 'update'])
            ->middleware('permission:admin.business_units.manage')->name('business-units.update');

        Route::get('/teams', [TeamController::class, 'index'])
            ->middleware('permission:admin.teams.view')->name('teams');
        Route::get('/teams/new', [TeamController::class, 'create'])
            ->middleware('permission:admin.teams.manage')->name('teams.create');
        Route::post('/teams', [TeamController::class, 'store'])
            ->middleware('permission:admin.teams.manage')->name('teams.store');
        Route::get('/teams/{team}', [TeamController::class, 'edit'])
            ->middleware('permission:admin.teams.manage')->name('teams.edit');
        Route::put('/teams/{team}', [TeamController::class, 'update'])
            ->middleware('permission:admin.teams.manage')->name('teams.update');

        Route::get('/users', [UserController::class, 'index'])
            ->middleware('permission:admin.users.view')->name('users');
        Route::get('/users/new', [UserController::class, 'create'])
            ->middleware('permission:admin.users.create')->name('users.create');
        Route::post('/users', [UserController::class, 'store'])
            ->middleware('permission:admin.users.create')->name('users.store');
        Route::get('/users/{user}', [UserController::class, 'show'])
            ->middleware('permission:admin.users.view')->name('users.show');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])
            ->middleware('permission:admin.users.update')->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])
            ->middleware('permission:admin.users.update')->name('users.update');

        /*
         * The three access actions are three routes with three permissions,
         * not one "update access" endpoint. Changing a tier, adding a role and
         * granting a business domain are different decisions with different
         * consequences, and the trail has to be able to tell them apart.
         */
        Route::post('/users/{user}/tier', [UserController::class, 'changeTier'])
            ->middleware('permission:admin.roles.assign')->name('users.tier');
        Route::post('/users/{user}/status', [UserController::class, 'changeStatus'])
            ->middleware('permission:admin.users.disable')->name('users.status');
        Route::post('/users/{user}/roles', [UserController::class, 'changeRole'])
            ->middleware('permission:admin.roles.assign')->name('users.roles');
        Route::post('/users/{user}/entitlements', [UserController::class, 'changeEntitlement'])
            ->middleware('permission:admin.entitlements.grant')->name('users.entitlements');

        Route::get('/roles', [AccessRoleController::class, 'index'])
            ->middleware('permission:admin.roles.view')->name('roles');
        Route::get('/roles/new', [AccessRoleController::class, 'create'])
            ->middleware('permission:admin.roles.manage')->name('roles.create');
        Route::post('/roles', [AccessRoleController::class, 'store'])
            ->middleware('permission:admin.roles.manage')->name('roles.store');
        Route::get('/roles/{role}', [AccessRoleController::class, 'edit'])
            ->middleware('permission:admin.roles.manage')->name('roles.edit');
        Route::put('/roles/{role}', [AccessRoleController::class, 'update'])
            ->middleware('permission:admin.roles.manage')->name('roles.update');
        Route::get('/roles/{role}/permissions', [AccessRoleController::class, 'permissions'])
            ->middleware('permission:admin.roles.manage')->name('roles.permissions');
        Route::put('/roles/{role}/permissions', [AccessRoleController::class, 'updatePermissions'])
            ->middleware('permission:admin.roles.manage')->name('roles.permissions.update');
        Route::delete('/roles/{role}', [AccessRoleController::class, 'destroy'])
            ->middleware('permission:admin.roles.manage')->name('roles.destroy');

        Route::get('/permissions', PermissionController::class)
            ->middleware('permission:admin.permissions.view')->name('permissions');

        Route::get('/entitlements', EntitlementController::class)
            ->middleware('permission:admin.entitlements.view')->name('entitlements');

        Route::get('/access-reviews', [AccessReviewController::class, 'index'])
            ->middleware('permission:admin.access_reviews.view')->name('access-reviews');
        Route::post('/access-reviews', [AccessReviewController::class, 'store'])
            ->middleware('permission:admin.access_reviews.manage')->name('access-reviews.store');
        Route::get('/access-reviews/{accessReview}', [AccessReviewController::class, 'show'])
            ->middleware('permission:admin.access_reviews.view')->name('access-reviews.show');
        Route::post('/access-reviews/{accessReview}/open', [AccessReviewController::class, 'open'])
            ->middleware('permission:admin.access_reviews.manage')->name('access-reviews.open');
        Route::post('/access-reviews/{accessReview}/items/{item}', [AccessReviewController::class, 'decide'])
            ->middleware('permission:admin.access_reviews.manage')->name('access-reviews.decide');
        Route::post('/access-reviews/{accessReview}/complete', [AccessReviewController::class, 'complete'])
            ->middleware('permission:admin.access_reviews.manage')->name('access-reviews.complete');
        Route::post('/access-reviews/{accessReview}/apply', [AccessReviewController::class, 'apply'])
            ->middleware('permission:admin.access_reviews.manage')->name('access-reviews.apply');
        Route::post('/access-reviews/{accessReview}/cancel', [AccessReviewController::class, 'cancel'])
            ->middleware('permission:admin.access_reviews.manage')->name('access-reviews.cancel');
    });

    Route::middleware('policy:system-admin')->prefix('admin')->name('admin.')->group(function (): void {
        /* ADM-001. The landing page: is the platform working, and what next. */
        Route::get('/', PlatformOverviewController::class)->name('overview');

        Route::prefix('system')->name('system.')->group(function (): void {
            /*
             * ADM-021. One pair of routes serves General Settings and
             * Environment Settings; the category is a route segment checked
             * against a closed list in the controller, never taken from the
             * request body.
             */
            Route::get('/settings/{category}', [SystemConfigurationController::class, 'edit'])
                ->name('settings');
            Route::put('/settings/{category}', [SystemConfigurationController::class, 'update'])
                ->name('settings.update');

            /* ADM-021, feature flags. */
            Route::get('/feature-flags', [FeatureFlagController::class, 'index'])
                ->name('feature-flags');
            Route::put('/feature-flags/{key}', [FeatureFlagController::class, 'update'])
                ->name('feature-flags.update');

            /* ADM-024. */
            Route::get('/diagnostics', DiagnosticsController::class)->name('diagnostics');
        });
    });
});
