<?php

declare(strict_types=1);

use App\Modules\Platform\Http\Controllers\EntryController;
use App\Modules\Platform\Http\Middleware\RequireAuthenticatedSession;
use Illuminate\Support\Facades\Route;

/*
 * Pre-authentication entry. Carries no shell, no menu and no business metadata,
 * as the blueprint requires of anything served to an unauthenticated browser.
 * P1-00 replaces this with the Login page.
 */
Route::get('/', EntryController::class)->name('entry');

/*
 * The authenticated area. Every route inside re-authorises; the group guard is
 * the outer boundary, never the only one. In P1-BASE the guard has no identity
 * to resolve and refuses everything, which is what proves deny-by-default before
 * there is anything behind it.
 */
Route::prefix('app')
    ->middleware(RequireAuthenticatedSession::class)
    ->group(function (): void {
        Route::get('/', fn () => abort(404))->name('app.home');
    });
