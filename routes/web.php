<?php

declare(strict_types=1);

use App\Modules\Platform\Http\Controllers\Auth\CallbackController;
use App\Modules\Platform\Http\Controllers\Auth\LogoutController;
use App\Modules\Platform\Http\Controllers\Auth\RedirectController;
use App\Modules\Platform\Http\Controllers\Auth\StateController;
use App\Modules\Platform\Http\Controllers\ConsoleController;
use App\Modules\Platform\Http\Controllers\EntryController;
use App\Modules\Platform\Http\Controllers\FirstRun\BeginController;
use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;
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
    });
