<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Enums\Role;
use App\Models\User;
use App\Modules\Audit\Support\AuditLogger;
use App\Modules\Platform\Support\FeatureFlags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ADM-024 Diagnostics.
 *
 * The feature's own specification is written mostly as a list of things the
 * screen must NEVER show, so that is what most of these tests assert. A
 * diagnostics page is the most natural place in an application for a
 * credential to end up, because everything about it invites "just show me
 * what is configured".
 */
class DiagnosticsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::query()->create(['name' => 'Ada Admin', 'email' => 'ada@example.test']);
        $user->forceFill(['role' => Role::SystemAdmin])->save();

        return $user->refresh();
    }

    #[Test]
    public function it_reports_what_an_administrator_needs_to_triage(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/system/diagnostics')
            ->assertOk()
            ->assertSee('Runtime')
            ->assertSee('Connectivity')
            ->assertSee('Application version')
            ->assertSee('Debug mode')
            ->assertSee('This page reference');
    }

    #[Test]
    public function it_never_shows_a_credential_or_a_host(): void
    {
        config()->set('services.microsoft.tenant', 'tenant-guid-value');
        config()->set('services.microsoft.client_id', 'client-id-value');
        config()->set('services.microsoft.client_secret', 'client-secret-value');
        config()->set('services.microsoft.redirect', 'https://example.test/callback');
        config()->set('database.connections.sqlite.database', '/var/secret/path/app.sqlite');

        $response = $this->actingAs($this->admin())->get('/admin/system/diagnostics');

        $response->assertOk();

        // ADM-024's "never expose" list, asserted item by item.
        foreach (['client-secret-value', 'client-id-value', 'tenant-guid-value', '/var/secret/path'] as $forbidden) {
            $response->assertDontSee($forbidden);
        }
    }

    #[Test]
    public function the_extended_fact_set_is_off_until_it_is_switched_on(): void
    {
        $admin = $this->admin();

        // Even redacted, a description of the runtime is worth something to
        // somebody who has not seen one.
        $this->actingAs($admin)
            ->get('/admin/system/diagnostics')
            ->assertDontSee('Session driver');

        app(FeatureFlags::class)->set('platform.extended_diagnostics', true, $admin);
        app(FeatureFlags::class)->flush();

        $this->actingAs($admin)
            ->get('/admin/system/diagnostics')
            ->assertOk()
            ->assertSee('Session driver')
            // Driver names are architecture. Host names are access.
            ->assertSee('Database driver');
    }

    #[Test]
    public function recent_refusals_are_listed_with_their_references(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        app(AuditLogger::class)->denied('privileged.action.denied', 'Platform', reason: 'Tier not held');

        $this->get('/admin/system/diagnostics')
            ->assertOk()
            ->assertSee('privileged.action.denied')
            ->assertSee('Tier not held')
            ->assertSee('Denied');
    }

    #[Test]
    public function it_explains_itself_when_nothing_has_gone_wrong(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/system/diagnostics')
            ->assertOk()
            ->assertSee('Nothing to report')
            ->assertSee('a trail of successes cannot show an');
    }

    #[Test]
    public function it_stays_behind_the_system_administration_boundary(): void
    {
        $person = User::query()->create(['name' => 'Ann Admin', 'email' => 'ann@example.test']);
        $person->forceFill(['role' => Role::Admin])->save();

        $this->actingAs($person->refresh())->get('/admin/system/diagnostics')->assertForbidden();
    }

    #[Test]
    public function a_guest_is_sent_to_sign_in_rather_than_forbidden(): void
    {
        $this->get('/admin/system/diagnostics')->assertRedirect('/sign-in');
    }
}
