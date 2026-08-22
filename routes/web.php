<?php

use App\Http\Controllers\Auth\MicrosoftSignInController;
use App\Http\Controllers\Auth\SignInController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

/*
 * Authentication. These are server-rendered and sit outside the single-page
 * application, so they are declared before the catch-all that would otherwise
 * swallow them.
 */
Route::middleware('guest')->group(function () {
    Route::get('/sign-in', [SignInController::class, 'show'])->name('sign-in');

    /*
     * Posted, not linked, so the request carries a CSRF token. A GET entry point
     * would let a third party start a sign-in on someone else's behalf.
     *
     * Rate limited because it is unauthenticated and each hit mints session
     * state and sends someone to an external identity provider.
     */
    Route::post('/auth/microsoft/redirect', [MicrosoftSignInController::class, 'redirect'])
        ->middleware('throttle:10,1')
        ->name('auth.microsoft.redirect');

    /*
     * Microsoft redirects the browser back here, so this one must be a GET. It
     * is safe without a CSRF token because the single-use `state` value, minted
     * above and held in the session, serves the same purpose.
     */
    Route::get('/auth/microsoft/callback', [MicrosoftSignInController::class, 'callback'])
        ->middleware('throttle:20,1')
        ->name('auth.microsoft.callback');
});

Route::post('/sign-out', [SignInController::class, 'destroy'])
    ->middleware('auth')
    ->name('sign-out');

/*
 * The application shell. Every destination inside it requires a session; the
 * auth middleware sends a guest to the sign-in screen rather than throwing.
 *
 * Only built destinations get a route. An unbuilt one renders in the sidebar as
 * a disabled row with a "Soon" indicator and is never a link, so it needs no
 * route and cannot be reached by typing a URL.
 */
Route::middleware('auth')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
});

/*
 * The remaining single-page application entry point. It no longer answers "/",
 * which is now the dashboard, and it stays only so the existing API probes have
 * somewhere to run from while the shell is being filled in.
 */
Route::view('/probe', 'app')->name('probe');
