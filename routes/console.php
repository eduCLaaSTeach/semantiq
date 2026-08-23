<?php

declare(strict_types=1);

use App\Modules\Platform\Support\HealthProbe;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

/*
|-------------------------------------------------------------------------------
| Scheduled platform work
|-------------------------------------------------------------------------------
|
| Laravel keeps no record of whether `schedule:run` is being called, so an
| absent or broken cron entry is completely silent: nothing errors, background
| work simply never happens. On shared hosting that is the most common way a
| platform quietly stops working.
|
| The heartbeat below is the cheapest possible fix. It writes a timestamp every
| five minutes, and `HealthProbe` reads it to answer "is the scheduler alive"
| on the Platform Overview and Diagnostics screens - features ADM-001 and
| ADM-024, and the visibility ADM-023 builds on in gate 6.
|
| Server-side cron entry required for this to run at all:
|
|     * * * * * cd <APPLICATION_PATH> && php artisan schedule:run >> /dev/null 2>&1
|
*/

Schedule::call(function (): void {
    /*
     * Written with a lifetime well beyond the check's staleness threshold, so
     * a stale heartbeat reads as "the scheduler stopped" rather than as "the
     * cache entry expired" - two very different problems that would otherwise
     * look identical.
     */
    Cache::put(HealthProbe::SCHEDULER_HEARTBEAT_KEY, now()->toIso8601String(), now()->addDay());
})->everyFiveMinutes()->name('platform-scheduler-heartbeat')->withoutOverlapping();
