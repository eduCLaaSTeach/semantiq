<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\MicrosoftSignInController;
use App\Http\Controllers\Auth\SignInController;
use App\Http\Controllers\Pages\AdminOverviewController;
use App\Http\Controllers\Pages\HomeController;
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
    Route::get('/admin', AdminOverviewController::class)
        ->middleware('policy:system-admin')
        ->name('admin.overview');
});
