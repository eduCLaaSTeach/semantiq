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
 *
 * The prefix is deliberately NOT "app". Under D-08B the whole Laravel tree sits
 * inside the document root, so the web server must refuse /app/ to protect
 * app/ on disk - and a URL prefix of the same name cannot then be served. The
 * first live exposure test caught exactly that: /app/ answered 302 from this
 * group where the security gate required 403 or 404. A route prefix may not
 * share a name with a directory in the deployment root, and
 * RoutePrefixCollisionTest enforces it.
 */
Route::prefix('console')
    ->middleware(RequireAuthenticatedSession::class)
    ->group(function (): void {
        Route::get('/', fn () => abort(404))->name('console.home');
    });
