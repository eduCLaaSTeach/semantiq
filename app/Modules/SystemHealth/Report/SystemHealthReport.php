<?php

declare(strict_types=1);

namespace App\Modules\SystemHealth\Report;

use App\Modules\Audit\Models\AuditChainHead;
use App\Modules\Audit\Services\AuditChainVerifier;
use App\Modules\Identity\Health\IdentityHealthCheck;
use App\Modules\Identity\Health\IdentityHealthReport;
use App\Modules\Platform\Health\HealthInspector;
use App\Modules\SystemHealth\Checks\BackgroundWorkCheck;
use App\Modules\SystemHealth\Checks\CacheStoreCheck;
use App\Modules\SystemHealth\Checks\SessionStoreCheck;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * The fourteen rows, and the ONE place each one's status comes from.
 *
 * 9 are PROJECTED from existing authoritative operational sources, 2 are
 * genuinely new round-trip checks, and 3 are derived from what this deployment
 * actually runs.
 *
 * TWO RULES THIS CLASS EXISTS TO HOLD:
 *
 *   ONE AUTHORITATIVE CHECK PER OPERATIONAL FACT. Nine of the fourteen rows
 *   are answered by checks that already existed before this unit. This class
 *   PROJECTS them into an area; it never reimplements one to make it appear
 *   there. There is no second `select 1`, no second is_writable(), no second
 *   chain walk.
 *
 *   A STATUS IS RETURNED BY A CHECK, NEVER ASSIGNED TO A ROW. Nothing here
 *   writes Available except as the answer a check gave. Where no check has an
 *   answer, the row says NotChecked and says so on screen.
 *
 * AND ONE THING IT MUST NOT DO: CONTACT ANYBODY.
 *
 * It calls HealthInspector::inspectLocal(), not inspect(), because inspect()
 * includes identity, and identity reaches
 * IdentityHealthCheck::forInspector() -> report() -> trustAvailability(),
 * which on a cold discovery cache makes two outbound HTTPS calls to Microsoft.
 * The Microsoft row comes from storedReport(), which holds no EntraDiscovery
 * reference at all. Opening this page contacts nobody, and there is no code
 * path by which it could.
 */
final class SystemHealthReport
{
    public function __construct(
        private readonly HealthInspector $inspector,
        private readonly IdentityHealthCheck $identity,
        private readonly SessionStoreCheck $sessions,
        private readonly CacheStoreCheck $cache,
        private readonly BackgroundWorkCheck $jobs,
        private readonly AuditChainVerifier $auditChain,
    ) {}

    /** @return list<SystemHealthArea> */
    public function areas(): array
    {
        $local = $this->inspector->inspectLocal()->checks;

        return [
            new SystemHealthArea(
                name: 'Application',
                description: 'Whether this release is complete and correctly installed.',
                rows: [
                    $this->fromLocal($local, 'migrations', 'Database structure',
                        'The database has every change this release expects.',
                        'The database is missing changes this release expects, so parts of SemantIQ may not work.'),
                    $this->fromLocal($local, 'configuration', 'Required settings',
                        'Every setting this release needs is present.',
                        'A setting this release needs is missing, so parts of SemantIQ may not work.'),
                    $this->fromLocal($local, 'assets', 'Screens and styling',
                        'The files the screens are built from were installed with this release.',
                        'The files the screens are built from are missing, so pages may look broken.'),
                ],
            ),

            new SystemHealthArea(
                name: 'Sign-in',
                description: 'Whether people can sign in with their work account.',
                rows: [$this->microsoft()],
            ),

            /*
             * "Tasks and timetables", NOT "Background work", and the browser
             * sweep is why. The area was called Background work and its first
             * row is called Background work, so the screen printed the same
             * words twice, three lines apart, meaning two different things -
             * the heading and one of the things under it. The UI standard
             * forbids naming a group after its cluster for exactly this
             * reason, and it reads the same way one level down.
             */
            new SystemHealthArea(
                name: 'Tasks and timetables',
                description: 'How longer tasks and timetabled work are handled here.',
                rows: $this->jobs->rows(),
            ),

            new SystemHealthArea(
                name: 'Connections',
                description: 'Whether the services SemantIQ stores information in are answering.',
                rows: [
                    $this->fromLocal($local, 'database', 'Database',
                        'A connection was opened and the database answered.',
                        'A connection to the database could not be opened, so SemantIQ cannot store or read information.'),
                    $this->sessionStore(),
                    $this->cacheStore(),
                    $this->fromLocal($local, 'storage', 'File storage',
                        'The folders SemantIQ writes to are present and writable.',
                        'A folder SemantIQ needs to write to is missing or not writable.'),
                ],
            ),

            new SystemHealthArea(
                name: 'Service health',
                description: 'The overall picture, and whether the record of what happened is sound.',
                rows: [
                    $this->localServiceHealth($local),
                    $this->auditIntegrity(),
                    $this->auditStore(),
                ],
            ),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function toArray(): array
    {
        return array_map(static fn (SystemHealthArea $area): array => $area->toArray(), $this->areas());
    }

    /**
     * An existing HealthInspector check, projected.
     *
     * BOTH SENTENCES ARE DECLARED HERE, and the inspector's own detail is
     * deliberately not used: those details are operator-facing ("Build manifest
     * missing", "3 migration(s) pending") and this screen is read by a person
     * who should not have to know what a manifest is. Nothing is caught, so
     * nothing catchable can be rendered.
     *
     * A MISSING KEY IS NotChecked, NOT Available. If a future change renames a
     * check, this row says nobody looked rather than quietly reporting health
     * for a check that no longer runs.
     *
     * @param  array<string, array{ok: bool, detail: string}>  $local
     */
    private function fromLocal(array $local, string $key, string $name, string $whenOk, string $whenNot): HealthRow
    {
        if (! array_key_exists($key, $local)) {
            return new HealthRow($name, HealthStatus::NotChecked,
                'This check did not run, so nothing is known about it.');
        }

        return $local[$key]['ok']
            ? new HealthRow($name, HealthStatus::Available, $whenOk)
            : new HealthRow($name, HealthStatus::Unavailable, $whenNot);
    }

    /**
     * Microsoft Entra ID - THE STORED ANSWER, WITH ITS AGE.
     *
     * storedReport() reads only what P1-02 already wrote. It is not report():
     * report() would ask Microsoft on a cold cache, which is the defect this
     * whole arrangement was corrected to remove.
     *
     * The three real states are preserved. Collapsing Degraded into Available -
     * which forInspector() does, correctly, for a two-state deployment gate -
     * would render a sign-in that needs attention as healthy, and a six-state
     * vocabulary fed by a two-state source is a status that looks measured and
     * is not.
     */
    private function microsoft(): HealthRow
    {
        $stored = $this->identity->storedReport();

        return match ($stored->state) {
            IdentityHealthReport::FAILED => new HealthRow(
                name: 'Microsoft Entra ID',
                status: HealthStatus::Unavailable,
                explanation: 'The last check found a problem that stops people signing in. Identity & SSO shows what it was.',
                checkedAt: $this->age($stored->checkedAt),
            ),
            IdentityHealthReport::DEGRADED => new HealthRow(
                name: 'Microsoft Entra ID',
                status: HealthStatus::Degraded,
                explanation: 'The last check found something that needs attention and may affect signing in. Identity & SSO shows what it was.',
                checkedAt: $this->age($stored->checkedAt),
            ),
            IdentityHealthReport::HEALTHY => new HealthRow(
                name: 'Microsoft Entra ID',
                status: HealthStatus::Available,
                explanation: 'The last check found no problem with signing in.',
                checkedAt: $this->age($stored->checkedAt),
            ),
            // NOT a default that lands on Available. Nothing trustworthy is
            // stored, so nobody has looked, and the row says exactly that.
            default => new HealthRow(
                name: 'Microsoft Entra ID',
                status: HealthStatus::NotChecked,
                explanation: 'Sign-in has not been checked on this release yet. Use Check sign-in now.',
                checkedAt: null,
            ),
        };
    }

    private function sessionStore(): HealthRow
    {
        $result = $this->sessions->run();

        return new HealthRow('Staying signed in', $result['status'], $result['explanation']);
    }

    private function cacheStore(): HealthRow
    {
        $result = $this->cache->run();

        return new HealthRow('Temporary storage', $result['status'], $result['explanation']);
    }

    /**
     * NOT the /up verdict, and named so nobody reads it as one.
     *
     * /up and semantiq:health answer over all SIX inspector checks, identity
     * included. This row answers over the five LOCAL ones, because reaching
     * identity would have contacted Microsoft. So sign-in can be down, /up can
     * be returning 503, and every check behind this row can still be green -
     * which is why it is called Local service health and why its sentence
     * points at the Sign-in area rather than implying it covers it.
     *
     * @param  array<string, array{ok: bool, detail: string}>  $local
     */
    private function localServiceHealth(array $local): HealthRow
    {
        foreach ($local as $check) {
            if (! $check['ok']) {
                return new HealthRow(
                    name: 'Local service health',
                    status: HealthStatus::Unavailable,
                    explanation: 'At least one of this application\'s own checks failed. The rows above show which. Signing in is reported separately, under Sign-in.',
                );
            }
        }

        return new HealthRow(
            name: 'Local service health',
            status: HealthStatus::Available,
            explanation: 'Every one of this application\'s own checks passed. Signing in is reported separately, under Sign-in.',
        );
    }

    /**
     * IT SHOWS THAT THE RECORD IS SOUND. IT SHOWS NO RECORD.
     *
     * No event, no actor, no count of rows, no sequence number - D-124. System
     * Health must not become a second way to read the log; Audit is that way,
     * and it is gated on EvidenceRead for reasons this screen does not get to
     * reopen.
     */
    private function auditIntegrity(): HealthRow
    {
        try {
            $result = $this->auditChain->verify();
        } catch (Throwable) {
            return new HealthRow('Record integrity', HealthStatus::NotChecked,
                'The record could not be checked just now, so nothing is known about it.');
        }

        return $result['intact']
            ? new HealthRow('Record integrity', HealthStatus::Available,
                'The record of what happened is complete and unaltered.')
            : new HealthRow('Record integrity', HealthStatus::Unavailable,
                'The record of what happened does not verify. Audit shows the detail.');
    }

    private function auditStore(): HealthRow
    {
        try {
            $head = AuditChainHead::query()->find(AuditChainHead::ID);
        } catch (Throwable) {
            return new HealthRow('Record keeping', HealthStatus::NotChecked,
                'Whether record keeping has started could not be established just now.');
        }

        return $head !== null
            ? new HealthRow('Record keeping', HealthStatus::Available,
                'SemantIQ is recording what happens, and the date it started from is held.')
            : new HealthRow('Record keeping', HealthStatus::Unavailable,
                'SemantIQ cannot confirm when its record of events begins.');
    }

    /**
     * A human age, never an instant and never a duration in figures.
     *
     * The row carries "3 hours ago", not a timestamp: a timestamp invites the
     * reader to work out a timezone, and this screen is read in a hurry.
     */
    private function age(?string $iso): ?string
    {
        if (! is_string($iso) || $iso === '') {
            return null;
        }

        try {
            return Carbon::parse($iso)->diffForHumans();
        } catch (Throwable) {
            return null;
        }
    }
}
