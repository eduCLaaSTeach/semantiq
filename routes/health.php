<?php

declare(strict_types=1);

use App\Modules\Platform\Http\Controllers\LivenessController;
use Illuminate\Support\Facades\Route;

/*
 * Liveness, registered OUTSIDE the web middleware group.
 *
 * This is not tidiness. The web group STARTS A SESSION, so a liveness route
 * inside it cannot answer whenever the session backing store is impaired -
 * which is precisely when a monitor needs an answer. Local verification caught
 * exactly that: /up returned 500 with a stack trace instead of a plain 503.
 *
 * THE REASON IS DELIBERATELY DRIVER-INDEPENDENT, and it used to name a driver.
 * It said "the session driver is `database`", which was both an unnecessary
 * premise and, as P1-09 established by asking the server, untrue of this
 * deployment - it currently runs `file`. Liveness must not depend on
 * application session startup OR on whatever is behind it, and that holds
 * whether the active driver is file today or database later. A rationale that
 * names a driver rots the moment the driver changes; this one cannot.
 *
 * Outside the group it holds no session, sets no cookie and reads no CSRF token,
 * so it degrades to a clean 503 rather than an exception page. It is also
 * exempted from maintenance mode in bootstrap/app.php.
 */
Route::get('/up', LivenessController::class)->name('liveness');
