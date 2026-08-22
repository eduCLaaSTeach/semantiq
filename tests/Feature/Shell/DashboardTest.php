<?php

declare(strict_types=1);

namespace Tests\Feature\Shell;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function userWith(Role $role, string $name = 'Ada Lovelace'): User
    {
        $user = User::query()->create([
            'name' => $name,
            'email' => 'person@example.test',
            'password' => null,
        ]);

        $user->forceFill(['role' => $role])->save();

        return $user->refresh();
    }

    #[Test]
    public function a_guest_is_sent_to_sign_in_rather_than_erroring(): void
    {
        $this->get('/')
            ->assertRedirect(route('sign-in'));
    }

    #[Test]
    public function the_dashboard_renders_inside_the_shell(): void
    {
        $this->actingAs($this->userWith(Role::SystemAdmin))
            ->get('/')
            ->assertOk()
            ->assertSee('Dashboard')
            ->assertSee('SemantIQ')
            ->assertSee('Platform Administrator');
    }

    #[Test]
    public function the_account_control_shows_initials(): void
    {
        $this->actingAs($this->userWith(Role::SystemAdmin, 'Ada Lovelace'))
            ->get('/')
            ->assertSee('>AL<', escape: false);
    }

    #[Test]
    public function a_one_word_name_still_produces_an_initial(): void
    {
        $this->actingAs($this->userWith(Role::Viewer, 'Prince'))
            ->get('/')
            ->assertSee('>P<', escape: false);
    }

    #[Test]
    public function a_platform_administrator_sees_the_system_administration_cluster(): void
    {
        $this->actingAs($this->userWith(Role::SystemAdmin))
            ->get('/')
            ->assertSee('System Administration')
            ->assertSee('Entra Connections');
    }

    #[Test]
    public function a_viewer_never_receives_the_admin_markup_at_all(): void
    {
        $response = $this->actingAs($this->userWith(Role::Viewer))->get('/');

        $response->assertOk()
            ->assertSee('Workspace')
            // Absent, not merely hidden: it must not reach the browser.
            ->assertDontSee('System Administration')
            ->assertDontSee('Entra Connections')
            ->assertDontSee('Application Administration');
    }

    #[Test]
    public function an_unbuilt_destination_renders_disabled_with_a_soon_indicator(): void
    {
        $this->actingAs($this->userWith(Role::SystemAdmin))
            ->get('/')
            ->assertSee('Blueprints')
            ->assertSee('Soon')
            ->assertSee('aria-disabled="true"', escape: false);
    }

    #[Test]
    public function the_shell_carries_the_skip_link_and_the_toast_host(): void
    {
        $this->actingAs($this->userWith(Role::SystemAdmin))
            ->get('/')
            ->assertSee('Skip to content')
            ->assertSee('aria-live="polite"', escape: false)
            ->assertSee('aria-live="assertive"', escape: false);
    }

    #[Test]
    public function the_probe_page_is_still_reachable(): void
    {
        $this->get('/probe')->assertOk();
    }
}
