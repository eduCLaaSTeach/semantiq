<?php

declare(strict_types=1);

namespace App\Modules\SystemHealth\Checks;

use App\Modules\SystemHealth\Report\HealthRow;
use App\Modules\SystemHealth\Report\HealthStatus;
use Illuminate\Console\Scheduling\Schedule;

/**
 * Three rows, DERIVED - never hard-coded, and that is the whole point of this
 * class existing rather than three literals in a controller.
 *
 * `sync` IS A CONFIGURED ARCHITECTURE, NOT AN ABSENCE. Work executes inline, in
 * the request, because that is what this deployment chose. Showing a missing
 * worker as Degraded would report a fault on a deployment that is working
 * exactly as intended - the opposite error to the one a health screen usually
 * makes, and just as wrong.
 *
 * So each row reads the live value:
 *
 *   Background work   reads queue.default and says what that arrangement is;
 *   Queue worker      is Not applicable ONLY WHILE the driver is sync, and
 *                     becomes a real check the day it is not;
 *   Scheduled tasks   COUNTS the registered schedule and is Not configured
 *                     ONLY WHILE that count is zero.
 *
 * A future unit that adds a scheduled task changes these rows without touching
 * this code, which is the only version of this that cannot go stale.
 */
final class BackgroundWorkCheck
{
    private const INLINE_DRIVER = 'sync';

    public function __construct(private readonly Schedule $schedule) {}

    /** @return list<HealthRow> */
    public function rows(): array
    {
        return [$this->backgroundWork(), $this->worker(), $this->scheduledTasks()];
    }

    private function backgroundWork(): HealthRow
    {
        if ($this->runsInline()) {
            return new HealthRow(
                name: 'Background work',
                status: HealthStatus::Available,
                explanation: 'Longer tasks run as part of the request that starts them, which is how this deployment is set up.',
            );
        }

        /*
         * The honest answer the day somebody changes the driver. This unit was
         * never asked to check a queue backend, and claiming Available for an
         * arrangement it has not exercised would be the hard-coded success the
         * whole screen exists to prevent.
         */
        return new HealthRow(
            name: 'Background work',
            status: HealthStatus::NotChecked,
            explanation: 'Longer tasks are handed to a background service on this deployment, and this page does not yet test that service.',
        );
    }

    private function worker(): HealthRow
    {
        if ($this->runsInline()) {
            return new HealthRow(
                name: 'Background service',
                status: HealthStatus::NotApplicable,
                explanation: 'No separate background service is needed, because tasks run as part of the request.',
            );
        }

        return new HealthRow(
            name: 'Background service',
            status: HealthStatus::NotChecked,
            explanation: 'This deployment expects a separate background service, and this page does not yet test whether it is running.',
        );
    }

    private function scheduledTasks(): HealthRow
    {
        $registered = count($this->schedule->events());

        if ($registered === 0) {
            return new HealthRow(
                name: 'Scheduled tasks',
                status: HealthStatus::NotConfigured,
                explanation: 'Nothing is set to run on a timetable on this deployment yet.',
            );
        }

        /*
         * Registered tasks exist, but nothing here has watched one run. Saying
         * Available would claim an observation nobody made; the count is a
         * configuration fact, not a heartbeat.
         */
        return new HealthRow(
            name: 'Scheduled tasks',
            status: HealthStatus::NotChecked,
            explanation: 'Work is set to run on a timetable, and this page does not yet confirm that it is running to time.',
        );
    }

    private function runsInline(): bool
    {
        return (string) config('queue.default') === self::INLINE_DRIVER;
    }
}
