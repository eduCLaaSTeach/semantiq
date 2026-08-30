<?php

declare(strict_types=1);

namespace App\Modules\Platform\Health;

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
    ) {}

    public function inspect(): HealthReport
    {
        return new HealthReport([
            'database' => $this->database(),
            'migrations' => $this->migrations(),
            'configuration' => $this->configuration(),
            'storage' => $this->storage(),
            'assets' => $this->assets(),
        ]);
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

    /** @return array{ok: bool, detail: string} */
    private function assets(): array
    {
        return is_file(public_path('build/manifest.json'))
            ? ['ok' => true, 'detail' => 'Build manifest present.']
            : ['ok' => false, 'detail' => 'Build manifest missing; assets were not built or not deployed.'];
    }
}
