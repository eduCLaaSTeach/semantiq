<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Modules\Platform\Health\HealthInspector;
use Tests\TestCase;

/**
 * A check that cannot fail is not a check.
 *
 * Each test below breaks one dependency and asserts the corresponding check goes
 * red. Without these, a health endpoint that returned success unconditionally
 * would pass every "is it healthy?" test ever written - and would convert an
 * outage into a silent one.
 */
final class HealthInspectorTest extends TestCase
{
    public function test_it_reports_healthy_when_everything_is_in_place(): void
    {
        $report = app(HealthInspector::class)->inspect();

        $this->assertTrue(
            $report->checks['database']['ok'],
            'Database check failed against the test connection.'
        );
        $this->assertTrue($report->checks['configuration']['ok']);
    }

    public function test_the_database_check_fails_when_the_connection_is_broken(): void
    {
        config(['database.connections.broken' => ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 1, 'database' => 'nope', 'username' => 'nope', 'password' => 'nope']]);
        config(['database.default' => 'broken']);

        $report = app(HealthInspector::class)->inspect();

        $this->assertFalse($report->checks['database']['ok']);
        $this->assertContains('database', $report->failing());
        $this->assertFalse($report->isHealthy());
    }

    public function test_a_broken_database_check_leaks_no_connection_detail(): void
    {
        config(['database.connections.broken' => ['driver' => 'mysql', 'host' => 'secret-host.internal', 'port' => 1, 'database' => 'secret_db', 'username' => 'secret_user', 'password' => 'secret_pass']]);
        config(['database.default' => 'broken']);

        $detail = app(HealthInspector::class)->inspect()->checks['database']['detail'];

        foreach (['secret-host.internal', 'secret_db', 'secret_user', 'secret_pass'] as $secret) {
            $this->assertStringNotContainsString($secret, $detail);
        }
    }

    public function test_the_configuration_check_fails_when_a_required_key_is_missing(): void
    {
        config(['app.key' => null]);

        $report = app(HealthInspector::class)->inspect();

        $this->assertFalse($report->checks['configuration']['ok']);
    }
}
