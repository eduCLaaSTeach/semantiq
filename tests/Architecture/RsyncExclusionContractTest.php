<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * Correction 3: the exclusion contract is enforced, not documented.
 *
 * The deployment pre-flight makes the same assertion on the runner before any
 * transfer. This one makes it on every pull request, so dropping an exclusion
 * goes red in review rather than being discovered by a deployment that has
 * already deleted the file.
 *
 * .env is the only copy of the database password and, from P1-00, the Microsoft
 * client secret. Losing it is unrecoverable.
 */
final class RsyncExclusionContractTest extends TestCase
{
    private const CONTRACT = __DIR__.'/../../deployment/rsync-protected-paths.txt';

    private const WORKFLOW = __DIR__.'/../../.github/workflows/deploy.yml';

    public function test_the_contract_file_exists_and_is_not_empty(): void
    {
        $this->assertFileExists(self::CONTRACT);
        $this->assertNotEmpty($this->protectedPaths(), 'The exclusion contract lists no paths.');
    }

    public function test_every_protected_path_is_excluded_in_the_deploy_workflow(): void
    {
        $workflow = file_get_contents(self::WORKFLOW);

        foreach ($this->protectedPaths() as $path) {
            // The trailing boundary matters. Without it, --exclude ".env.example"
            // satisfies a search for ".env" and the guard silently passes while
            // the only copy of the database password goes unprotected. This test
            // was written without it and did exactly that.
            $this->assertMatchesRegularExpression(
                '/--exclude[= ]"'.preg_quote($path, '/').'"(\s|$)/m',
                $workflow,
                "Protected path [{$path}] is not excluded in deploy.yml. rsync --delete would "
                .'remove it from the server, and .env exists nowhere else.'
            );
        }
    }

    public function test_the_deploy_workflow_runs_the_exclusion_preflight(): void
    {
        $this->assertStringContainsString(
            'rsync-protected-paths.txt',
            file_get_contents(self::WORKFLOW),
            'deploy.yml does not read the exclusion contract, so its pre-flight cannot enforce it.'
        );
    }

    /** @return list<string> */
    private function protectedPaths(): array
    {
        $lines = file(self::CONTRACT, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        return array_values(array_filter(
            array_map('trim', $lines),
            fn (string $l): bool => $l !== '' && ! str_starts_with($l, '#'),
        ));
    }
}
