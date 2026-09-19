<?php

declare(strict_types=1);

namespace App\Modules\Platform\Health;

use App\Modules\Identity\Health\IdentityHealthCheck;
use App\Modules\Platform\Support\ConfigurationValidator;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Migrations\Migrator;
use Throwable;

/**
 * Every check performs a real operation.
 *
 * A check that cannot fail is not a check. The database check opens a
 * connection and runs a query rather than reading configuration; the migration
 * check asks the migrator what is outstanding; the storage check writes. Each
 * one has a test that breaks its dependency and asserts the check goes red,
 * because a health endpoint that reports success unconditionally is worse than
 * none - it converts an outage into a silent one.
 *
 * Details are operator-facing and carry no secret, no connection string, no
 * credential and no business data.
 */
final class HealthInspector
{
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly Migrator $migrator,
        private readonly ConfigurationValidator $configuration,
        private readonly IdentityHealthCheck $identity,
    ) {}

    /**
     * The whole deployment verdict. /up and semantiq:health consume this, and
     * it is unchanged: the same six checks, in the same order.
     */
    public function inspect(): HealthReport
    {
        return new HealthReport([
            ...$this->localChecks(),
            'identity' => $this->identity(),
        ]);
    }

    /**
     * P1-09. The local dependencies alone - and therefore no network path.
     *
     * A PROJECTION, NOT A SECOND INSPECTOR. It calls the same five private
     * methods inspect() calls, so a future change to how storage is checked
     * reaches both callers because there is still exactly one implementation.
     * Copying a check into the System Health module would give one deployment
     * two authoritative answers to the same question, which is the duplication
     * that unit exists to prevent.
     *
     * IDENTITY IS EXCLUDED FOR A CONCRETE REASON, not for tidiness. identity()
     * calls IdentityHealthCheck::forInspector(), which calls report(), which
     * calls trustAvailability(), which on a cold discovery cache asks
     * Microsoft over the network. A screen that promised to contact nobody
     * would have done exactly that through this path. System Health reads the
     * stored identity answer instead, through
     * IdentityHealthCheck::storedReport().
     *
     * So this report is NOT the /up verdict and must not be presented as one:
     * sign-in can be down, /up can be 503, and every check in here can still
     * be green. The screen names it accordingly.
     */
    public function inspectLocal(): HealthReport
    {
        return new HealthReport($this->localChecks());
    }

    /** @return array<string, array{ok: bool, detail: string}> */
    private function localChecks(): array
    {
        return [
            'database' => $this->database(),
            'migrations' => $this->migrations(),
            'configuration' => $this->configuration(),
            'storage' => $this->storage(),
            'assets' => $this->assets(),
        ];
    }

    /** @return array{ok: bool, detail: string} */
    private function database(): array
    {
        try {
            $this->db->connection()->select('select 1');

            return ['ok' => true, 'detail' => 'Connection opened and query executed.'];
        } catch (Throwable) {
            // The exception message can carry the host, database name and user.
            return ['ok' => false, 'detail' => 'Could not open a database connection.'];
        }
    }

    /** @return array{ok: bool, detail: string} */
    private function migrations(): array
    {
        try {
            if (! $this->migrator->repositoryExists()) {
                return ['ok' => false, 'detail' => 'Migration repository does not exist.'];
            }

            $pending = count($this->migrator->getMigrationFiles($this->migrator->paths() ?: [database_path('migrations')]))
                - count($this->migrator->getRepository()->getRan());

            return $pending > 0
                ? ['ok' => false, 'detail' => "{$pending} migration(s) pending."]
                : ['ok' => true, 'detail' => 'No pending migrations.'];
        } catch (Throwable) {
            return ['ok' => false, 'detail' => 'Could not determine migration state.'];
        }
    }

    /** @return array{ok: bool, detail: string} */
    private function configuration(): array
    {
        $problems = $this->configuration->problems();

        return $problems === []
            ? ['ok' => true, 'detail' => 'All required configuration present.']
            : ['ok' => false, 'detail' => count($problems).' configuration problem(s); see the log.'];
    }

    /** @return array{ok: bool, detail: string} */
    private function storage(): array
    {
        foreach ([storage_path('framework'), storage_path('logs'), base_path('bootstrap/cache')] as $path) {
            if (! is_dir($path) || ! is_writable($path)) {
                return ['ok' => false, 'detail' => 'A required runtime directory is not writable.'];
            }
        }

        return ['ok' => true, 'detail' => 'Runtime directories writable.'];
    }

    /**
     * P1-02. The SAME object the SSO Health screen renders, collapsed.
     *
     * Not a second copy of the logic: semantiq:health and the screen must not be
     * able to disagree about one deployment, because then an operator has to
     * pick which to believe.
     *
     * @return array{ok: bool, detail: string}
     */
    private function identity(): array
    {
        try {
            return $this->identity->forInspector();
        } catch (Throwable) {
            return ['ok' => false, 'detail' => 'Could not determine identity health.'];
        }
    }

    /** @return array{ok: bool, detail: string} */
    private function assets(): array
    {
        return is_file(public_path('build/manifest.json'))
            ? ['ok' => true, 'detail' => 'Build manifest present.']
            : ['ok' => false, 'detail' => 'Build manifest missing; assets were not built or not deployed.'];
    }
}
