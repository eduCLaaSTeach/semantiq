<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * The exemption is implemented, so it is proven by behaviour.
 *
 * Laravel's maintenance mode runs before routing and does NOT exempt /up
 * automatically. If that exemption regressed, the deployment probe would be
 * reporting on maintenance mode rather than on the application, and would fail a
 * perfectly healthy release. Asserting the configuration would not catch a
 * change in how Laravel applies it; driving a real request does.
 */
final class MaintenanceModeTest extends TestCase
{
    // Migrations must have run, or /up correctly reports 503 for pending
    // migrations and the maintenance assertion below would pass for the wrong
    // reason - or fail for one. The first draft of this test did exactly that.
    use RefreshDatabase;

    protected function tearDown(): void
    {
        // Never leave the test application down, whatever the assertions did.
        Artisan::call('up');

        parent::tearDown();
    }

    public function test_liveness_answers_while_ordinary_routes_are_down(): void
    {
        Artisan::call('down');

        $this->get('/up')->assertStatus(200);
        $this->get('/')->assertStatus(503);
    }

    public function test_ordinary_routes_recover_when_maintenance_ends(): void
    {
        Artisan::call('down');
        Artisan::call('up');

        $this->get('/')->assertOk();
    }
}
