<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support;

use App\Modules\Audit\Support\Redaction;
use App\Modules\Platform\Enums\HealthState;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Answers whether the platform is working. Features ADM-001 and ADM-024.
 *
 * Every check follows the same three rules, and a new check that breaks any of
 * them is a defect rather than a variation.
 *
 * IT NEVER THROWS. A probe that fails while reporting failure tells an
 * administrator nothing. Each check catches everything and converts it into a
 * state, so one broken dependency cannot take the health screen down with it.
 *
 * IT NEVER EXPOSES A SECRET OR A HOST. `detail` reaches a browser. It may name
 * a DRIVER - "mysql", "database" - because a driver name is architecture, not
 * access. It may never name a host, a database, a user, a path or a connection
 * string, and every message built from an exception goes through
 * `Redaction::scrub()` first. This is the rule ADM-024's "never expose"
 * section is written against.
 *
 * UNKNOWN IS AN ANSWER. A queue that cannot be inspected from a web request is
 * reported as unknown, not as failed. Reporting an unknown as a failure trains
 * an administrator to ignore the colour, and then the real failure is ignored
 * too.
 *
 * The checks are deliberately cheap: this runs on every load of the
 * Administration landing page, so nothing here may make a network call. Gate 5
 * adds a Connection Test Centre for the checks that genuinely need one, and
 * those are run on request rather than on render.
 */
class HealthProbe
{
    /**
     * The cache key the scheduled heartbeat writes. Reading it is how this
     * class knows the scheduler is running at all: Laravel keeps no record of
     * `schedule:run` by itself, and on shared hosting a missing cron entry is
     * the single most common reason background work silently stops.
     */
    public const SCHEDULER_HEARTBEAT_KEY = 'platform:scheduler-heartbeat';

    /**
     * How stale the heartbeat may be before it is a warning. The heartbeat is
     * written every five minutes, so fifteen tolerates two missed runs before
     * anybody is told - short enough to matter, long enough not to cry wolf.
     */
    private const HEARTBEAT_WARNING_MINUTES = 15;

    public function __construct(
        private readonly FeatureFlags $flags,
    ) {}

    /**
     * Every check, in the order they are shown.
     *
     * @return list<HealthCheck>
     */
    public function run(): array
    {
        return [
            $this->database(),
            $this->cache(),
            $this->queue(),
            $this->scheduler(),
            $this->microsoftEntra(),
            $this->dataProtectionProfile(),
            $this->sovereigntyProfile(),
        ];
    }

    /**
     * The state of the platform as a whole: its unhealthiest dependency.
     *
     * @param  list<HealthCheck>  $checks
     */
    public function overall(array $checks): HealthState
    {
        return HealthState::worst(array_map(fn (HealthCheck $check): HealthState => $check->state, $checks));
    }

    /**
     * Runtime facts that describe the application without describing the host.
     *
     * Shown on Diagnostics. The extended set is behind a flag because, while it
     * still exposes no credential and no customer data, it does describe the
     * server, and a description of the server is worth something to an attacker
     * who has not seen one.
     *
     * @return array<string, string>
     */
    public function runtimeFacts(): array
    {
        $facts = [
            'Environment' => (string) app()->environment(),
            'Application version' => $this->applicationVersion(),
            'Framework' => 'Laravel '.app()->version(),
            'PHP' => PHP_VERSION,
            /* Debug mode on a live instance turns every error into a stack
             * trace with configuration in it, so its state is a health fact. */
            'Debug mode' => config('app.debug') ? 'On' : 'Off',
        ];

        if (! $this->flags->enabled('platform.extended_diagnostics')) {
            return $facts;
        }

        return $facts + [
            /* Driver NAMES only. Never the host, the database, the user or the
             * path - see the class docblock. */
            'Database driver' => (string) config('database.default'),
            'Cache driver' => (string) config('cache.default'),
            'Queue driver' => (string) config('queue.default'),
            'Session driver' => (string) config('session.driver'),
            'Mail transport' => (string) config('mail.default'),
            'Server time zone' => (string) config('app.timezone'),
        ];
    }

    /**
     * The application version.
     *
     * Read from the deployed `VERSION` file when one exists so the running
     * build can be identified in a support conversation, and reported as
     * unknown rather than guessed when it does not.
     */
    public function applicationVersion(): string
    {
        $path = base_path('VERSION');

        if (! is_file($path)) {
            return 'unknown';
        }

        $version = trim((string) @file_get_contents($path));

        /* Bounded and stripped: the file is on disk, and anything read from
         * disk and shown on screen is untrusted until it is constrained. */
        return $version === '' ? 'unknown' : Str::limit(preg_replace('/[^\w.\-+]/', '', $version) ?? '', 32, '');
    }

    /**
     * Can the application reach its database at all.
     */
    private function database(): HealthCheck
    {
        try {
            DB::connection()->select('select 1');

            return new HealthCheck(
                name: 'Database',
                state: HealthState::Healthy,
                /* The driver, never the host or the database name. */
                detail: 'Connected using the '.config('database.default').' driver.',
            );
        } catch (Throwable $exception) {
            return new HealthCheck(
                name: 'Database',
                state: HealthState::Critical,
                /* Scrubbed: a driver error routinely quotes the DSN it failed
                 * to open, credentials included. */
                detail: Redaction::scrub($exception->getMessage()) ?? 'The database could not be reached.',
            );
        }
    }

    /**
     * A write-then-read round trip, which is the only check that proves a cache
     * is usable rather than merely configured.
     */
    private function cache(): HealthCheck
    {
        try {
            $probe = 'platform:health-probe';
            $token = (string) Str::uuid();

            Cache::put($probe, $token, 10);
            $readBack = Cache::get($probe);
            Cache::forget($probe);

            if ($readBack !== $token) {
                return new HealthCheck(
                    name: 'Cache',
                    state: HealthState::Warning,
                    detail: 'The cache accepted a value but did not return it. Configuration is present but the store is not working.',
                );
            }

            return new HealthCheck(
                name: 'Cache',
                state: HealthState::Healthy,
                detail: 'Read and write confirmed on the '.config('cache.default').' store.',
            );
        } catch (Throwable $exception) {
            return new HealthCheck(
                name: 'Cache',
                state: HealthState::Warning,
                detail: Redaction::scrub($exception->getMessage()) ?? 'The cache could not be reached.',
            );
        }
    }

    /**
     * Whether queued work is piling up.
     *
     * Only inspectable for the database driver, which is what this deployment
     * uses. Any other driver reports unknown rather than a guess: a web request
     * cannot see whether a Redis worker is alive, and pretending otherwise
     * would be worse than admitting it.
     */
    private function queue(): HealthCheck
    {
        try {
            if (config('queue.default') !== 'database') {
                return new HealthCheck(
                    name: 'Background jobs',
                    state: HealthState::Unknown,
                    detail: 'The '.config('queue.default').' driver cannot be inspected from here.',
                );
            }

            $failed = Schema::hasTable('failed_jobs')
                ? DB::table('failed_jobs')->count()
                : 0;

            $pending = Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0;

            if ($failed > 0) {
                return new HealthCheck(
                    name: 'Background jobs',
                    state: HealthState::Warning,
                    detail: $failed.' job'.($failed === 1 ? '' : 's').' failed and need attention.',
                );
            }

            return new HealthCheck(
                name: 'Background jobs',
                state: HealthState::Healthy,
                detail: $pending === 0 ? 'No work waiting.' : $pending.' job'.($pending === 1 ? '' : 's').' waiting.',
            );
        } catch (Throwable $exception) {
            return new HealthCheck(
                name: 'Background jobs',
                state: HealthState::Unknown,
                detail: Redaction::scrub($exception->getMessage()) ?? 'The queue could not be inspected.',
            );
        }
    }

    /**
     * Whether the scheduler has run recently.
     *
     * The heartbeat in routes/console.php writes the cache key this reads. A
     * missing cron entry is the most common reason background work stops on
     * shared hosting, and it is invisible without a check like this one -
     * nothing errors, work simply never happens.
     */
    private function scheduler(): HealthCheck
    {
        try {
            $last = Cache::get(self::SCHEDULER_HEARTBEAT_KEY);

            if (! is_string($last)) {
                return new HealthCheck(
                    name: 'Scheduler',
                    state: HealthState::Unknown,
                    detail: 'No run recorded yet. If this instance was deployed in the last few minutes, wait; otherwise the scheduled task is not installed.',
                );
            }

            $ranAt = Carbon::parse($last);
            $minutes = (int) $ranAt->diffInMinutes(now());

            if ($minutes > self::HEARTBEAT_WARNING_MINUTES) {
                return new HealthCheck(
                    name: 'Scheduler',
                    state: HealthState::Warning,
                    detail: 'Last run '.$ranAt->diffForHumans().'. Scheduled work is not running.',
                );
            }

            return new HealthCheck(
                name: 'Scheduler',
                state: HealthState::Healthy,
                detail: 'Last run '.$ranAt->diffForHumans().'.',
            );
        } catch (Throwable $exception) {
            return new HealthCheck(
                name: 'Scheduler',
                state: HealthState::Unknown,
                detail: Redaction::scrub($exception->getMessage()) ?? 'The scheduler state could not be read.',
            );
        }
    }

    /**
     * Whether Microsoft sign-in is configured.
     *
     * PRESENCE ONLY. No value is read into a variable, compared, logged or
     * rendered. This method's answer goes straight to a browser, so the only
     * safe thing it can know about a client secret is whether one is set.
     */
    private function microsoftEntra(): HealthCheck
    {
        $missing = [];

        foreach (['tenant' => 'tenant', 'client_id' => 'application', 'client_secret' => 'client secret', 'redirect' => 'redirect address'] as $key => $label) {
            if (blank(config('services.microsoft.'.$key))) {
                $missing[] = $label;
            }
        }

        if ($missing === []) {
            return new HealthCheck(
                name: 'Microsoft Entra sign-in',
                state: HealthState::Healthy,
                detail: 'Configured. Sign-in is delegated to your directory.',
            );
        }

        return new HealthCheck(
            name: 'Microsoft Entra sign-in',
            state: HealthState::Warning,
            /* Which values are MISSING is safe to say; which are present is not
             * interesting and their contents are never touched. */
            detail: 'Not configured yet. Missing: '.implode(', ', $missing).'. These are set on the server, never in the application.',
        );
    }

    /**
     * Whether a data protection profile has been recorded.
     *
     * The profile itself is ADM-014 in gate 4. Until then this reports honestly
     * that it is outstanding rather than quietly reporting nothing, because
     * "not built yet" and "built and complete" must never look the same on a
     * screen an administrator uses to decide whether they are ready to go live.
     */
    private function dataProtectionProfile(): HealthCheck
    {
        return $this->outstandingGovernanceProfile(
            name: 'Data protection profile',
            table: 'data_protection_profiles',
            outstanding: 'No profile recorded. Classification, retention and access policy are decided in gate 4 of this release.',
        );
    }

    /**
     * Whether a data sovereignty profile has been recorded.
     *
     * CLAUDE.md requires cross-geo processing and storage to default to OFF and
     * approved geographies to be explicit. Neither is recorded yet, so this
     * says so.
     */
    private function sovereigntyProfile(): HealthCheck
    {
        return $this->outstandingGovernanceProfile(
            name: 'Data sovereignty profile',
            table: 'data_sovereignty_profiles',
            outstanding: 'No profile recorded. Storage and processing geography are approved in gate 4 of this release. Cross-geo processing stays off until then.',
        );
    }

    /**
     * A governance profile that gate 4 will fill in.
     *
     * Written once for both because they differ only in wording, and reading
     * the table with `Schema::hasTable` means this check starts reporting real
     * state the moment gate 4's migration lands - with no edit here to forget.
     */
    private function outstandingGovernanceProfile(string $name, string $table, string $outstanding): HealthCheck
    {
        try {
            $recorded = Schema::hasTable($table) && DB::table($table)->exists();
        } catch (Throwable) {
            $recorded = false;
        }

        return $recorded
            ? new HealthCheck($name, HealthState::Healthy, 'Recorded.')
            : new HealthCheck($name, HealthState::Warning, $outstanding);
    }
}
