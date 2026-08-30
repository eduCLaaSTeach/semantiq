<?php

declare(strict_types=1);

use App\Modules\Platform\Http\Controllers\LivenessController;
use Illuminate\Support\Facades\Route;

/*
 * Liveness, registered OUTSIDE the web middleware group.
 *
 * This is not tidiness. The web group starts a session, and the session driver
 * is `database`; a liveness route inside that group cannot answer when the
 * database is down, which is precisely when a monitor needs an answer. Local
 * verification caught exactly that: /up returned 500 with a stack trace instead
 * of a plain 503.
 *
 * Outside the group it holds no session, sets no cookie and reads no CSRF token,
 * so it degrades to a clean 503 rather than an exception page. It is also
 * exempted from maintenance mode in bootstrap/app.php.
 */
Route::get('/up', LivenessController::class)->name('liveness');
