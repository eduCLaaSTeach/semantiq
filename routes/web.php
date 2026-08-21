<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\MicrosoftSignInController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web routes
|--------------------------------------------------------------------------
|
| Only the sign-in surface exists so far. The authenticated application is
| added behind an auth middleware group as its screens are built, so nothing
| unfinished sits in the live path.
|
*/

Route::redirect('/', '/login');

Route::get('/login', [MicrosoftSignInController::class, 'create'])
    ->name('login');

Route::post('/auth/microsoft/redirect', [MicrosoftSignInController::class, 'redirect'])
    ->name('auth.microsoft.redirect');
