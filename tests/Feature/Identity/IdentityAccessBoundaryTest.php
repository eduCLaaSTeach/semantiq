<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;
use App\Modules\Platform\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\EntraTokenFactory;
use Tests\Support\OrganisationFactory;
use Tests\TestCase;

/**
 * Who may see the Identity screens, and who may not.
 *
 * Every route re-authorises. Navigation visibility is presentation; the route is
 * the control. Both are asserted, and separately, so they cannot be collapsed
 * into one.
 */
final class IdentityAccessBoundaryTest extends TestCase
{
    use RefreshDatabase;

    private const SCREENS = [
        '/console/identity',
        '/console/identity/providers',
        '/console/identity/login-experience',
        '/console/identity/health',
        '/console/identity/session-policy',
    ];

    private const ACTIONS = [
        '/console/identity/health/re-check',
        '/console/identity/entra/reveal',
    ];

    private OrganisationFactory $make;

    protected function setUp(): void
    {
        parent::setUp();

        (new EntraTokenFactory)->configure();
        $this->make = new OrganisationFactory;
    }

    /**
     * B1. Anonymous is refused everywhere, and told nothing.
     *
     * Mutation: remove the session middleware from the group.
     */
    public function test_anonymous_is_refused_on_every_screen_and_action(): void
    {
        foreach (self::SCREENS as $screen) {
            $this->get($screen)->assertRedirect(route('entry'));
        }

        foreach (self::ACTIONS as $action) {
            $response = $this->post($action);

            $this->assertContains(
                $response->getStatusCode(),
                [302, 401, 419],
                "[{$action}] served an anonymous caller."
            );

            $this->assertNotSame(200, $response->getStatusCode());
        }
    }

    /**
     * B2. Authentication is not authorisation.
     *
     * Mutation: drop the administrator gate from any one route.
     */
    public function test_an_authenticated_non_administrator_is_refused(): void
    {
        $user = $this->make->user(administrator: false);

        foreach (self::SCREENS as $screen) {
            $this->actingAsUser($user)->get($screen)->assertRedirect(route('auth.access-denied'));
        }

        foreach (self::ACTIONS as $action) {
            $this->actingAsUser($user)->post($action)->assertRedirect(route('auth.access-denied'));
        }
    }

    /** ...and an administrator is served every one of them. */
    public function test_a_system_administrator_is_served_every_screen(): void
    {
        $admin = $this->make->user(administrator: true);

        foreach (self::SCREENS as $screen) {
            $this->actingAsUser($admin)->get($screen)->assertOk();
        }
    }

    /**
     * B3. Menu visibility and route authorisation are two code paths.
     *
     * The menu is filtered for a non-administrator AND the route refuses them.
     * If only one were true, the other would be the whole control.
     *
     * Mutation: make the route trust the menu.
     */
    public function test_the_menu_and_the_route_are_separate_controls(): void
    {
        $user = $this->make->user(administrator: false);

        $response = $this->actingAsUser($user)->get('/console');

        $this->assertStringNotContainsString('/console/identity', $response->getContent());

        $this->actingAsUser($user)->get('/console/identity')->assertRedirect(route('auth.access-denied'));
    }

    /**
     * B4. Reading identity configuration grants no business access.
     *
     * Asserted against the boundary rather than against an empty result: there
     * is no business data in this unit to request, so a test that merely found
     * nothing would pass for the wrong reason and keep passing after the
     * boundary was removed.
     */
    public function test_reading_identity_grants_no_business_access(): void
    {
        $admin = $this->make->user(administrator: true);

        $this->actingAsUser($admin)->get('/console/identity')->assertOk();

        $this->assertFalse(method_exists($admin, 'domains'));
        $this->assertFalse(method_exists($admin, 'entitlements'));

        $reachable = [];
        $walk = function (array $nodes) use (&$walk, &$reachable): void {
            foreach ($nodes as $node) {
                if ($node['route'] !== null) {
                    $reachable[$node['label']] = $node['route'];
                }

                $walk($node['children']);
            }
        };

        $areas = $this->actingAsUser($admin)->get('/console')->viewData('page')['props']['productAreas'] ?? [];

        foreach ($areas as $area) {
            $walk($area['nodes']);
        }

        $this->assertSame(
            ['Organisation' => '/console/organisation', 'Identity & SSO' => '/console/identity'],
            $reachable,
            'A business domain became reachable. Identity administration confers no business access.'
        );
    }

    /**
     * B5. No Identity screen tells anyone whether a particular account exists.
     *
     * P1-00 deliberately makes "not assigned" and "inactive" read identically to
     * an anonymous caller. Nothing here may undo that by, for instance, listing
     * recent failed sign-ins with names attached.
     *
     * Mutation: add a per-account failure list to any screen.
     */
    public function test_no_identity_screen_discloses_an_account(): void
    {
        $admin = $this->make->user(administrator: true);
        $other = $this->make->user(administrator: false);

        foreach (self::SCREENS as $screen) {
            $body = $this->actingAsUser($admin)->get($screen)->getContent();

            $this->assertStringNotContainsString($other->email, $body, "[{$screen}] disclosed an account.");
            $this->assertStringNotContainsString($admin->email, $body, "[{$screen}] disclosed an account.");
        }
    }

    /** The reveal endpoint accepts exactly two fields, and names nothing else. */
    public function test_reveal_refuses_any_other_field(): void
    {
        $admin = $this->make->user(administrator: true);

        foreach (['client_secret', 'secret', 'app_key', '', 'redirect'] as $field) {
            $response = $this->actingAsUser($admin)->post('/console/identity/entra/reveal', ['field' => $field]);

            $response->assertStatus(422);

            $this->assertSame('That cannot be revealed.', $response->json('message'));
            $this->assertNull($response->json('value'));
        }
    }

    private function actingAsUser(User $user): self
    {
        return $this->withSession([
            EnsureSessionIsCurrent::SESSION_USER_ID => $user->id,
            EnsureSessionIsCurrent::SESSION_AUTHENTICATED_AT => now()->toIso8601String(),
        ]);
    }
}
