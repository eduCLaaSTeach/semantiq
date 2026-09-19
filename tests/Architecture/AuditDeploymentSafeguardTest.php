<?php

declare(strict_types=1);

namespace Tests\Architecture;

use Tests\TestCase;

/**
 * THE PRODUCTION SAFEGUARD, ASSERTED RATHER THAN INTENDED.
 *
 * Audit is the only durable record of what happened on a deployment, and there
 * is no export to fall back on (D-110). A deploy step that could roll the
 * schema back would be a deploy step that can erase the evidence of its own
 * predecessor - and it would be added by somebody being helpful about a failed
 * migration, not by somebody being careless.
 */
final class AuditDeploymentSafeguardTest extends TestCase
{
    /** Mutation: add a `migrate:rollback` step to deploy.yml. */
    public function test_the_deploy_workflow_never_rolls_migrations_back(): void
    {
        $workflow = (string) file_get_contents(base_path('.github/workflows/deploy.yml'));

        $this->assertStringNotContainsString(
            'migrate:rollback',
            $workflow,
            'The deployment can roll migrations back. On production that drops the Audit tables '
            .'and destroys every record gathered since they were created.'
        );

        $this->assertStringNotContainsString('migrate:fresh', $workflow);
        $this->assertStringNotContainsString('migrate:reset', $workflow);
    }

    /** And the safeguard is written down where an operator will find it. */
    public function test_the_rollback_safeguard_is_documented(): void
    {
        $this->assertFileExists(base_path('deployment/AUDIT-ROLLBACK.md'));

        $document = (string) file_get_contents(base_path('deployment/AUDIT-ROLLBACK.md'));

        foreach (['explicit operator approval', 'backup or snapshot taken first', 'not a product feature'] as $clause) {
            $this->assertStringContainsString(
                $clause,
                mb_strtolower($document),
                "The rollback safeguard does not state: {$clause}"
            );
        }
    }
}
