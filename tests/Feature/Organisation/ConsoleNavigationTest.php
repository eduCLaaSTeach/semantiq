<?php

declare(strict_types=1);

namespace Tests\Feature\Organisation;

use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;
use App\Modules\Platform\Models\User;
use App\Modules\Platform\Models\UserStatus;
use App\Shared\Navigation\ProductArea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Tests\Support\OrganisationFactory;
use Tests\TestCase;

/**
 * The integration the earlier tests missed.
 *
 * NavigationRegistryTest proved the registry filters nodes correctly, and
 * OrganisationBoundaryTest proved /console/organisation authorises correctly.
 * Both passed while the signed-in landing page rendered the P1-00 card with no
 * shell, and while the shared productAreas prop resolved to an empty array on
 * every page - so Organisation was built, routed, authorised and unreachable.
 *
 * Neither test ever asked the question a person asks: after signing in, is the
 * capability actually there. This one does, against the real HTTP response.
 */
final class ConsoleNavigationTest extends TestCase
{
    use RefreshDatabase;

    private OrganisationFactory $make;

    protected function setUp(): void
    {
        parent::setUp();

        $this->make = new OrganisationFactory;
    }

    /** 1 and 2. The landing page carries the shell and the Organisation node. */
    public function test_a_system_administrator_lands_on_console_with_organisation_in_the_navigation(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);

        $response = $this->actingAsUser($admin)->get('/console');
        $response->assertOk();

        $areas = $this->productAreas($response);

        $this->assertNotSame(
            [],
            $areas,
            'The signed-in landing page supplied no navigation at all, so nothing delivered by '
            .'this unit is reachable from the page a System Administrator actually lands on.'
        );

        $this->assertSame(
            ['Organisation'],
            $this->nodeLabels($areas),
            'Organisation is not the navigation offered on the landing page.'
        );
    }

    /** 3. The node resolves to the real route, not a dead link. */
    public function test_the_organisation_node_resolves_to_the_organisation_route(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);

        $areas = $this->productAreas($this->actingAsUser($admin)->get('/console'));

        $this->assertSame('/console/organisation', $areas[0]['nodes'][0]['route']);

        // And that href actually serves the screen, rather than merely existing.
        $this->actingAsUser($admin)->get($areas[0]['nodes'][0]['route'])->assertOk();
    }

    /** The landing page must render the shell, not the P1-00 standalone card. */
    public function test_the_landing_page_renders_inside_the_authenticated_shell(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);

        $source = file_get_contents(__DIR__.'/../../../resources/js/Pages/Console/Home.jsx');

        $this->assertStringContainsString(
            'AppShell',
            $source,
            'The landing page does not use the authenticated shell, so no navigation can appear on it.'
        );

        // The prop the shell needs must actually arrive.
        $this->assertArrayHasKey(
            'productAreas',
            $this->page($this->actingAsUser($admin)->get('/console'))['props']
        );
    }

    /** 4. An authenticated non-administrator gains no node. */
    public function test_a_non_administrator_is_offered_no_navigation(): void
    {
        $organisation = $this->make->organisation();

        $response = $this->actingAsUser($this->make->user($organisation))->get('/console');
        $response->assertOk();

        $this->assertSame(
            [],
            $this->productAreas($response),
            'A user who is not a System Administrator was offered navigation. Menu visibility is '
            .'not the access control, but it must not advertise a capability either.'
        );
    }

    /** 4, continued. An inactive administrator never reaches the page at all. */
    public function test_an_inactive_administrator_does_not_reach_the_landing_page(): void
    {
        $organisation = $this->make->organisation();

        $admin = $this->make->user(
            $organisation,
            administrator: true,
            status: UserStatus::Inactive,
        );

        $this->actingAsUser($admin)->get('/console')->assertRedirect(route('auth.account-inactive'));
    }

    /**
     * 2. A future cluster is non-navigable.
     *
     * Today that is true because neither area has any node at all, so the
     * cluster does not render. Once the roadmap menu lands its items will
     * render disabled, and this guard becomes the one that proves they still
     * cannot be reached: NO ROUTE EXISTS for any of them.
     *
     * Mutation: register a route under either area's prefix.
     */
    public function test_no_future_phase_area_has_any_reachable_route(): void
    {
        // '' is the root route, whose URI trims to nothing.
        $delivered = ['', 'console', 'auth', 'first-run', 'up'];

        foreach (Route::getRoutes() as $route) {
            $first = explode('/', trim($route->uri(), '/'))[0] ?? '';

            $this->assertContains(
                $first,
                $delivered,
                "Route [{$route->uri()}] is outside every delivered area. Fabric Configuration "
                .'and SemantIQ Workplace are Phase 2 and Phase 3; a route for either would be a '
                .'placeholder for functionality that does not exist.'
            );
        }
    }

    /**
     * 5. No future-phase area becomes visible in the CURRENT sidebar.
     *
     * This asserts today's rendered state. It is deliberately separate from
     * ProductAreaOrderTest, which asserts the declared order of all three areas
     * whether or not they currently render.
     */
    public function test_no_future_phase_navigation_appears(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);

        $areas = $this->productAreas($this->actingAsUser($admin)->get('/console'));

        $this->assertSame(
            [ProductArea::SystemAdministration->value],
            array_column($areas, 'key'),
            'An area beyond System Administration is rendering. Fabric Configuration is Phase 2 '
            .'and SemantIQ Workplace is Phase 3; neither has any delivered screen.'
        );
    }

    /**
     * 5. The reorder moved no access or phase boundary.
     *
     * Organisation is still gated by the platform role and nothing else, and
     * the areas still own the phases they owned before D-23.
     */
    public function test_the_navigation_reorder_moved_no_access_or_phase_boundary(): void
    {
        $organisation = $this->make->organisation();

        // Unchanged: a non-administrator is offered nothing and refused the route.
        $member = $this->make->user($organisation);
        $this->assertSame([], $this->productAreas($this->actingAsUser($member)->get('/console')));
        $this->actingAsUser($member)
            ->get('/console/organisation')
            ->assertRedirect(route('auth.access-denied'));

        // Unchanged: phase ownership.
        $this->assertSame(1, ProductArea::SystemAdministration->deliveryPhase());
        $this->assertSame(2, ProductArea::FabricConfiguration->deliveryPhase());
        $this->assertSame(3, ProductArea::SemantiqWorkplace->deliveryPhase());
    }

    /** The SYS-004 statement must survive the move onto the shell. */
    public function test_the_landing_page_still_states_that_administration_grants_no_business_access(): void
    {
        $source = file_get_contents(__DIR__.'/../../../resources/js/Pages/Console/Home.jsx');

        $this->assertStringContainsString('does not grant it', $source);
        $this->assertStringContainsString('/auth/logout', $source, 'Sign out is no longer offered.');
    }

    /**
     * @return array<string, mixed>
     */
    private function page(TestResponse $response): array
    {
        /** @var array<string, mixed> $page */
        $page = $response->viewData('page');

        return $page;
    }

    /**
     * @return list<array{key: string, label: string, nodes: list<array{label: string, icon: string, route: string}>}>
     */
    private function productAreas(TestResponse $response): array
    {
        /** @var list<array{key: string, label: string, nodes: list<array{label: string, icon: string, route: string}>}> $areas */
        $areas = $this->page($response)['props']['productAreas'] ?? [];

        return $areas;
    }

    /**
     * @param  list<array{key: string, label: string, nodes: list<array{label: string, icon: string, route: string}>}>  $areas
     * @return list<string>
     */
    private function nodeLabels(array $areas): array
    {
        $labels = [];

        foreach ($areas as $area) {
            foreach ($area['nodes'] as $node) {
                $labels[] = $node['label'];
            }
        }

        return $labels;
    }

    private function actingAsUser(User $user): self
    {
        return $this->withSession([
            EnsureSessionIsCurrent::SESSION_USER_ID => $user->id,
            EnsureSessionIsCurrent::SESSION_AUTHENTICATED_AT => now()->toIso8601String(),
        ]);
    }
}
