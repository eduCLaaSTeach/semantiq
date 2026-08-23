<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Enums\Role;
use App\Models\User;
use App\Modules\Audit\Support\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ADM-001 Platform Overview.
 *
 * The acceptance criteria are explicit: real status values, failed dependencies
 * identified, warnings linked to the screen that fixes them, and no credential
 * exposed. The last of those is tested hardest, because it is the one that
 * cannot be noticed by looking at the page.
 */
class PlatformOverviewTest extends TestCase
{
    use RefreshDatabase;

    private function personOn(Role $role): User
    {
        $user = User::query()->create(['name' => 'Test Person', 'email' => uniqid().'@example.test']);
        $user->forceFill(['role' => $role])->save();

        return $user->refresh();
    }

    #[Test]
    public function it_shows_real_health_states_rather_than_placeholders(): void
    {
        $this->actingAs($this->personOn(Role::SystemAdmin))
            ->get('/admin')
            ->assertOk()
            ->assertSee('Platform status')
            ->assertSee('Health checks')
            ->assertSee('Database')
            ->assertSee('Scheduler')
            // The database is genuinely reachable in a test run, so this is a
            // real result and not a hard-coded string.
            ->assertSee('Connected using the');
    }

    #[Test]
    public function an_outstanding_dependency_appears_in_the_things_needing_attention(): void
    {
        // Microsoft sign-in is unconfigured in a test environment, and the
        // governance profiles arrive in gate 4, so all three are outstanding.
        config()->set('services.microsoft.client_id', null);

        $this->actingAs($this->personOn(Role::SystemAdmin))
            ->get('/admin')
            ->assertOk()
            ->assertSee('Needs your attention')
            ->assertSee('Microsoft Entra sign-in')
            ->assertSee('Data sovereignty profile');
    }

    #[Test]
    public function no_configured_credential_reaches_the_page(): void
    {
        config()->set('services.microsoft.tenant', 'tenant-guid-value');
        config()->set('services.microsoft.client_id', 'client-id-value');
        config()->set('services.microsoft.client_secret', 'client-secret-value');
        config()->set('services.microsoft.redirect', 'https://example.test/callback');

        $response = $this->actingAs($this->personOn(Role::SystemAdmin))->get('/admin');

        $response->assertOk();

        // The probe reports PRESENCE only. It never reads a value into a
        // variable, so none of these can appear even by accident.
        foreach (['tenant-guid-value', 'client-id-value', 'client-secret-value'] as $secret) {
            $response->assertDontSee($secret);
        }
    }

    #[Test]
    public function recent_changes_are_shown_once_there_are_any(): void
    {
        $admin = $this->personOn(Role::SystemAdmin);
        $this->actingAs($admin);

        app(AuditLogger::class)->record('system.setting.updated', 'Platform', resourceId: 'app.display_name');

        $this->get('/admin')
            ->assertOk()
            ->assertSee('system.setting.updated')
            ->assertSee($admin->email);
    }

    #[Test]
    public function it_explains_itself_before_anything_has_happened(): void
    {
        // The template forbids a bare blank box. An empty state says what will
        // be here and what has to happen first.
        $this->actingAs($this->personOn(Role::SystemAdmin))
            ->get('/admin')
            ->assertOk()
            ->assertSee('Nothing recorded yet');
    }

    #[Test]
    public function it_stays_behind_the_system_administration_boundary(): void
    {
        // Unchanged by this release: an Administrator who can invite a colleague
        // does not thereby hold every provider credential.
        $this->actingAs($this->personOn(Role::Admin))->get('/admin')->assertForbidden();
        $this->actingAs($this->personOn(Role::Analyst))->get('/admin')->assertForbidden();
    }

    #[Test]
    public function a_guest_is_sent_to_sign_in_rather_than_forbidden(): void
    {
        // Its own test on purpose: `actingAs` persists for the rest of a test
        // method, so a guest assertion after an authenticated one is not a
        // guest assertion at all. That mistake reads as a passing 403.
        $this->get('/admin')->assertRedirect('/sign-in');
    }
}
