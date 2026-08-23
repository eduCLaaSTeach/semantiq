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
use App\Modules\Security\Http\Controllers\ApiSecurityController;
use App\Modules\Security\Http\Controllers\AuthenticationPolicyController;
use App\Modules\Security\Http\Controllers\ReauthenticationController;
use App\Modules\Security\Http\Controllers\SecretReferenceController;
use App\Modules\Security\Http\Controllers\SecurityOverviewController;
use App\Modules\Security\Http\Controllers\SessionPolicyController;
use Illuminate\Support\Facades\Route;

/*
 * Guest routes. The sign-in screen is the application's front door until the
 * shell exists behind it.
 */
Route::middleware('guest')->group(function (): void {
    Route::get('/sign-in', [SignInController::class, 'show'])->name('sign-in');
    Route::post('/sign-in', [SignInController::class, 'attempt'])->name('sign-in.attempt');

    /*
     * Password reset is deliberately not a form. Identity is federated, so a
     * directory account's password is reset in the directory and not here;
     * offering a reset form would imply this application holds a password it
     * does not own.
     */
    Route::view('/sign-in/password', 'auth.password-help')->name('password.request');
});

/*
 * The Microsoft round trip. NOT inside the `guest` group, and that is a change
 * made in Release 1 gate 3 rather than an oversight.
 *
 * ADM-010's re-authentication sends an ALREADY SIGNED-IN person back to Entra
 * with `prompt=login`, and they return through this same callback. Under
 * `guest` the framework would bounce them away before the controller ran, so
 * the confirmation could never complete. Both legs therefore accept a guest and
 * a signed-in person, and the controller decides which flow it is.
 *
 * What protected these routes before is unchanged and is not the `guest`
 * middleware: starting the flow is a POST so it carries CSRF protection - a GET
 * would let any page on the internet bounce a visitor into a sign-in round trip
 * - and the callback is protected by the single-use `state` parameter, since
 * Microsoft cannot carry our CSRF token back.
 */
Route::post('/sign-in/microsoft', [MicrosoftSignInController::class, 'redirect'])
    ->name('sign-in.microsoft');

Route::get('/auth/microsoft/callback', [MicrosoftSignInController::class, 'callback'])
    ->name('sign-in.microsoft.callback');

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
        /*
         * ADM-010 critical action. `confirm:tier_change` escalates itself to
         * the System Administrator variant when the posted tier is that one -
         * see ConfirmIdentity::resolve(). Named after `permission:` so
         * authorization is settled first.
         */
        Route::post('/users/{user}/tier', [UserController::class, 'changeTier'])
            ->middleware(['permission:admin.roles.assign', 'confirm:tier_change'])->name('users.tier');
        Route::post('/users/{user}/status', [UserController::class, 'changeStatus'])
            ->middleware('permission:admin.users.disable')->name('users.status');
        /* ADM-010 critical action: assigning a role is role elevation by
         * another name, since a role carries permissions. */
        Route::post('/users/{user}/roles', [UserController::class, 'changeRole'])
            ->middleware(['permission:admin.roles.assign', 'confirm:tier_change'])->name('users.roles');
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
        /* ADM-010 critical action: changing what a role may do widens
         * everybody who holds it, which is the quietest elevation there is. */
        Route::put('/roles/{role}/permissions', [AccessRoleController::class, 'updatePermissions'])
            ->middleware(['permission:admin.roles.manage', 'confirm:tier_change'])->name('roles.permissions.update');
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

        /*
         * Security - Release 1 gate 3, features ADM-009 to ADM-012.
         *
         * The route family DEC-001 settled in advance, unchanged. Gated by
         * `permission:` as well as by the cluster's `policy:system-admin`, so
         * the route boundary checks the SAME declared permission the rail node
         * checks and a typed URL meets the same gate a hidden link would have.
         *
         * READ and WRITE are separate permissions on every screen. Seeing what
         * the authentication policy says and being able to weaken it are
         * different decisions, and a single `admin.security.manage` would have
         * made them the same one.
         *
         * The secret screens name `admin.secrets.*` rather than
         * `admin.security.*`: the credential map is protected separately from
         * the policy switches, so a later decision to delegate policy reading
         * cannot hand the map over with it.
         */
        Route::prefix('security')->name('security.')->group(function (): void {
            Route::get('/', SecurityOverviewController::class)
                ->middleware('permission:admin.security.view')->name('overview');

            /* ADM-009. */
            Route::get('/authentication', [AuthenticationPolicyController::class, 'edit'])
                ->middleware('permission:admin.security.view')->name('authentication');
            Route::put('/authentication', [AuthenticationPolicyController::class, 'update'])
                ->middleware(['permission:admin.security.update', 'confirm:security_policy_change'])
                ->name('authentication.update');

            /* ADM-010. */
            Route::get('/sessions', [SessionPolicyController::class, 'edit'])
                ->middleware('permission:admin.security.view')->name('sessions');
            Route::put('/sessions', [SessionPolicyController::class, 'update'])
                ->middleware(['permission:admin.security.update', 'confirm:security_policy_change'])
                ->name('sessions.update');

            /*
             * Ending somebody else's sessions. A POST rather than a DELETE
             * because it is an action on a person rather than the removal of a
             * resource, and it is refused outright when the session driver
             * cannot enumerate - see SessionRegistry. The screen does not
             * render the control at all in that case, and this route still
             * refuses it, because a hidden control is not an access control.
             */
            Route::post('/sessions/revoke/{user}', [SessionPolicyController::class, 'revoke'])
                ->middleware(['permission:admin.security.update', 'confirm:security_policy_change'])
                ->name('sessions.revoke');

            /* ADM-011. */
            Route::get('/api', [ApiSecurityController::class, 'edit'])
                ->middleware('permission:admin.security.view')->name('api');
            Route::put('/api', [ApiSecurityController::class, 'update'])
                ->middleware(['permission:admin.security.update', 'confirm:security_policy_change'])
                ->name('api.update');

            /* ADM-012. */
            Route::get('/secrets', [SecretReferenceController::class, 'index'])
                ->middleware('permission:admin.secrets.view')->name('secrets');
            /*
              * Everything below the index carries `security-storage`. The index
              * itself does not: it renders a controlled "migration required"
              * state, so an administrator who opens it during a deployment
              * window is told what is happening rather than shown a wall.
              */
            Route::get('/secrets/new', [SecretReferenceController::class, 'create'])
                ->middleware(['permission:admin.secrets.manage', 'security-storage'])->name('secrets.create');
            Route::post('/secrets', [SecretReferenceController::class, 'store'])
                ->middleware(['permission:admin.secrets.manage', 'security-storage', 'confirm:secret_reference_change'])
                ->name('secrets.store');
            /*
             * `{secretReference}` is a plain integer, resolved in the
             * controller, and NOT an implicit model binding.
             *
             * Laravel's `SubstituteBindings` lives in the `web` middleware
             * GROUP, which runs before any route-level middleware - so an
             * implicit binding queries `secret_references` before
             * `security-storage` gets a chance to refuse, and a typed URL
             * during the deployment window returns a raw database error.
             * Measured, not assumed: the test that walks these five routes with
             * the table dropped caught it.
             *
             * Resolving in the controller also matches how `sessions.revoke`
             * already handles a subject, and lets the organisation boundary be
             * asked for explicitly rather than inherited from a binding.
             */
            Route::get('/secrets/{secretReference}', [SecretReferenceController::class, 'edit'])
                ->whereNumber('secretReference')
                ->middleware(['permission:admin.secrets.manage', 'security-storage'])->name('secrets.edit');
            Route::put('/secrets/{secretReference}', [SecretReferenceController::class, 'update'])
                ->whereNumber('secretReference')
                ->middleware(['permission:admin.secrets.manage', 'security-storage', 'confirm:secret_reference_change'])
                ->name('secrets.update');
            Route::post('/secrets/{secretReference}/retire', [SecretReferenceController::class, 'retire'])
                ->whereNumber('secretReference')
                ->middleware(['permission:admin.secrets.manage', 'security-storage', 'confirm:secret_reference_change'])
                ->name('secrets.retire');
        });
    });

    /*
     * Proving who you are again, before a critical action. ADM-010.
     *
     * Outside the `policy:system-admin` group deliberately. A tier change is a
     * critical action and an Administrator can make one, so gating the
     * confirmation screen at System Administrator would let an Administrator
     * reach the action and never reach the screen that unblocks it.
     *
     * It carries no permission of its own for the same reason: the permission
     * belongs to the action being confirmed, and this screen only proves
     * identity. It is behind `auth`, like everything in this group.
     */
    Route::get('/confirm-identity', [ReauthenticationController::class, 'show'])
        ->name('reauthenticate');
    Route::post('/confirm-identity', [ReauthenticationController::class, 'confirm'])
        ->name('reauthenticate.confirm');
});
