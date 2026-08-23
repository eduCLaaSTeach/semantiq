<?php

declare(strict_types=1);

namespace Tests\Feature\Shell;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The rendered shell.
 *
 * The load-bearing assertion here is the negative one: a feature a person
 * cannot reach must be ABSENT from the markup, not merely hidden by CSS.
 * Anything present in the HTML has already been sent to their browser.
 */
class ShellTest extends TestCase
{
    use RefreshDatabase;

    private function personOn(Role $role): User
    {
        $user = User::query()->create(['name' => 'Salil Mhatre', 'email' => 'salil@lithan.com']);
        $user->forceFill(['role' => $role])->save();

        return $user->refresh();
    }

    #[Test]
    public function the_dashboard_renders_inside_the_shell(): void
    {
        $this->actingAs($this->personOn(Role::SystemAdmin))
            ->get('/')
            ->assertOk()
            ->assertSee('SemantIQ')
            ->assertSee('Home')
            // The rail owns the corner and the top bar spans only the main column.
            ->assertSee('rail-container', false)
            ->assertSee('top-nav', false)
            ->assertSee('app-main', false);
    }

    #[Test]
    public function the_toast_host_is_present_from_page_load(): void
    {
        // Both live regions, in one host, before any toast exists. A live region
        // inserted at the same moment as its content is not reliably announced.
        $this->actingAs($this->personOn(Role::Viewer))
            ->get('/')
            ->assertSee('aria-live="polite"', false)
            ->assertSee('aria-live="assertive"', false);
    }

    #[Test]
    public function a_system_administrator_sees_every_cluster(): void
    {
        $this->actingAs($this->personOn(Role::SystemAdmin))
            ->get('/')
            ->assertSee('Workspace')
            ->assertSee('Compliance')
            ->assertSee('Application Administration')
            ->assertSee('System Administration');
    }

    #[Test]
    public function a_viewer_never_receives_the_administration_markup_at_all(): void
    {
        $response = $this->actingAs($this->personOn(Role::Viewer))->get('/');

        $response->assertOk()->assertSee('Workspace');

        // Absent, not dimmed. A dimmed link still tells them the feature exists.
        $response->assertDontSee('System Administration')
            ->assertDontSee('Application Administration')
            ->assertDontSee('Compliance')
            ->assertDontSee('Fabric Environment');
    }

    #[Test]
    public function an_unbuilt_destination_renders_disabled_with_a_soon_pill(): void
    {
        $response = $this->actingAs($this->personOn(Role::SystemAdmin))->get('/');

        $response->assertSee('Fabric Environment')
            ->assertSee('aria-disabled="true"', false)
            ->assertSee('Soon');
    }

    #[Test]
    public function the_active_leaf_is_marked_for_assistive_technology_too(): void
    {
        $this->actingAs($this->personOn(Role::Viewer))
            ->get('/')
            ->assertSee('aria-current="page"', false)
            ->assertSee('is-active', false);
    }

    #[Test]
    public function both_theme_variants_of_both_marks_are_present(): void
    {
        // The rail shows the wide mark expanded and the short mark collapsed,
        // each in a per-theme pair. A missing variant is invisible until
        // somebody switches theme or collapses the rail.
        $this->actingAs($this->personOn(Role::Viewer))
            ->get('/')
            ->assertSee('logo-full-light.png')
            ->assertSee('logo-full-dark.png')
            ->assertSee('logo-short-light.png')
            ->assertSee('logo-short-dark.png');
    }

    #[Test]
    public function the_theme_switcher_offers_system_dark_and_light_in_that_order(): void
    {
        $response = $this->actingAs($this->personOn(Role::Viewer))->get('/');

        $html = $response->getContent();
        $system = strpos($html, 'data-theme-choice="system"');
        $dark = strpos($html, 'data-theme-choice="dark"');
        $light = strpos($html, 'data-theme-choice="light"');

        $this->assertNotFalse($system);
        $this->assertTrue($system < $dark && $dark < $light, 'Theme options are out of order');
    }

    #[Test]
    public function the_top_bar_carries_the_app_name_and_no_navigation(): void
    {
        $response = $this->actingAs($this->personOn(Role::Viewer))->get('/');

        $response->assertSee('app-name', false);

        // No global search bar in the chrome: the template is explicit that the
        // top bar has none, and there is no toggle that could add one.
        $this->assertStringNotContainsString('type="search"', $this->topBarOf($response->getContent()));
    }

    private function topBarOf(string $html): string
    {
        $start = strpos($html, '<header class="top-nav"');

        return substr($html, $start, strpos($html, '</header>', $start) - $start);
    }

    #[Test]
    public function the_profile_page_renders_and_reports_the_sign_in_method(): void
    {
        $this->actingAs($this->personOn(Role::Admin))
            ->get('/profile')
            ->assertOk()
            ->assertSee('Email and password')
            ->assertSee('Administrator');
    }

    #[Test]
    public function a_guest_cannot_reach_the_shell(): void
    {
        $this->get('/')->assertRedirect('/sign-in');
        $this->get('/profile')->assertRedirect('/sign-in');
    }

    #[Test]
    public function initials_come_from_the_first_and_last_word(): void
    {
        $this->assertSame('SM', $this->personOn(Role::Viewer)->initials());

        $one = new User(['name' => 'Prince', 'email' => 'p@example.test']);
        $this->assertSame('P', $one->initials());

        // An empty name falls back to the address: a blank avatar reads as a
        // rendering fault rather than as missing data.
        $none = new User(['name' => '', 'email' => 'nobody@example.test']);
        $this->assertSame('N', $none->initials());
    }
}
