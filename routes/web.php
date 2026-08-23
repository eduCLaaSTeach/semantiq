<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\MicrosoftSignInController;
use App\Http\Controllers\Auth\SignInController;
use Illuminate\Support\Facades\Route;

/*
 * Guest routes. The sign-in screen is the application's front door until the
 * shell exists behind it.
 */
Route::middleware('guest')->group(function (): void {
    Route::get('/sign-in', [SignInController::class, 'show'])->name('sign-in');
    Route::post('/sign-in', [SignInController::class, 'attempt'])->name('sign-in.attempt');

    Route::post('/sign-in/microsoft', [MicrosoftSignInController::class, 'redirect'])
        ->name('sign-in.microsoft');

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
 * The signed-in landing page. A placeholder until the application shell is
 * built: it exists so the sign-in redirect has somewhere real to land.
 */
Route::get('/', fn () => view('home'))->middleware('auth')->name('home');
