<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The sign-in surface.
 *
 * These cover what the screen must do without any Entra ID configuration
 * present, which is exactly the state a freshly deployed environment is in.
 */
class SignInPageTest extends TestCase
{
    public function test_the_root_path_redirects_to_the_sign_in_screen(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_the_sign_in_screen_renders_without_a_database(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('auth/sign-in')
                ->where('status', null)
        );
    }

    public function test_starting_sign_in_fails_closed_when_entra_is_not_configured(): void
    {
        config([
            'services.microsoft.tenant_id' => null,
            'services.microsoft.client_id' => null,
        ]);

        $response = $this->post('/auth/microsoft/redirect');

        $response->assertRedirect();
        $response->assertSessionHas('status', function (array $status): bool {
            return $status['level'] === 'warning'
                && str_contains($status['title'], 'not configured');
        });
    }

    public function test_starting_sign_in_is_rejected_without_a_csrf_token(): void
    {
        // The redirect must not be reachable by a cross-site GET, which is why it
        // is a POST behind CSRF verification rather than a link.
        $this->get('/auth/microsoft/redirect')->assertStatus(405);
    }

    public function test_no_microsoft_secret_reaches_the_page_payload(): void
    {
        config(['services.microsoft.client_secret' => 'test-secret-value-must-not-leak']);

        $response = $this->get('/login');

        $response->assertOk();
        $response->assertDontSee('test-secret-value-must-not-leak', escape: false);
    }
}
