<?php

declare(strict_types=1);

namespace App\Modules\Platform\Console;

use App\Modules\Platform\Health\HealthInspector;
use Illuminate\Console\Command;

/**
 * Operator diagnostics, reachable only over SSH.
 *
 * The richer surface is bounded by server access rather than by a web
 * permission, which does not exist yet. Exit code 1 on failure so a deployment
 * step can gate on it.
 */
final class HealthCommand extends Command
{
    protected $signature = 'semantiq:health';

    protected $description = 'Report real application health: database, migrations, configuration, storage, assets.';

    public function handle(HealthInspector $inspector): int
    {
        $report = $inspector->inspect();

        $this->table(
            ['Check', 'Status', 'Detail'],
            array_map(
                fn (string $name, array $c): array => [$name, $c['ok'] ? 'OK' : 'FAIL', $c['detail']],
                array_keys($report->checks),
                array_values($report->checks),
            ),
        );

        if ($report->isHealthy()) {
            $this->info('Healthy.');

            return self::SUCCESS;
        }

        $this->error('Unhealthy: '.implode(', ', $report->failing()));

        return self::FAILURE;
    }
}
