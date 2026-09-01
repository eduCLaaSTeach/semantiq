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

        $this->assertContains(
            'Organisation',
            $this->nodeLabels($areas),
            'Organisation is not in the navigation offered on the landing page.'
        );

        // D-19 shows the whole roadmap. Exactly one entry is a destination.
        $this->assertSame(
            ['Organisation' => '/console/organisation'],
            $this->reachable($areas),
            'Something other than Organisation is reachable from the sidebar. Organisation is '
            .'the only capability delivered; every other entry is a roadmap label.'
        );
    }

    /** 3. The node resolves to the real route, not a dead link. */
    public function test_the_organisation_node_resolves_to_the_organisation_route(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);

        $areas = $this->productAreas($this->actingAsUser($admin)->get('/console'));

        $reachable = $this->reachable($areas);

        $this->assertSame(['Organisation' => '/console/organisation'], $reachable);

        // And that href actually serves the screen, rather than merely existing.
        $this->actingAsUser($admin)->get($reachable['Organisation'])->assertOk();
    }

    /**
     * D-19: the complete approved roadmap renders, in the approved order, and
     * grants nothing.
     *
     * Mutation: drop an entry from ApprovedMenu, or reorder the areas.
     */
    public function test_the_complete_approved_roadmap_renders_in_the_approved_order(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);

        $areas = $this->productAreas($this->actingAsUser($admin)->get('/console'));

        $this->assertSame(
            [
                ProductArea::SemantiqWorkplace->value,
                ProductArea::FabricConfiguration->value,
                ProductArea::SystemAdministration->value,
            ],
            array_column($areas, 'key'),
            'The product areas are not in the approved order (D-23).'
        );

        // Only System Administration opens by default.
        $this->assertSame([false, false, true], array_column($areas, 'expanded'));

        $this->assertSame(
            $this->approvedLabels(),
            $this->nodeLabels($areas),
            'The rendered menu is not the approved menu.'
        );

        // The Product Owner states the roadmap as 43 entries. Asserted as a
        // number as well as a list, because the number is what gets quoted in a
        // status report and is therefore what gets quoted wrongly.
        $this->assertCount(43, $this->flatten($areas), 'The roadmap is not 43 entries.');

        $perArea = array_map(fn (array $area): int => count($this->flatten([$area])), $areas);

        $this->assertSame(
            [19, 14, 10],
            $perArea,
            'The per-area counts are not 19 Workplace, 14 Fabric, 10 System Administration.'
        );
    }

    /**
     * Every roadmap entry is inert: no route, and therefore nothing to reach.
     *
     * Mutation: turn any locked() entry in ApprovedMenu into a leaf().
     */
    public function test_every_roadmap_entry_carries_no_destination(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);

        $areas = $this->productAreas($this->actingAsUser($admin)->get('/console'));

        $inert = 0;

        foreach ($this->flatten($areas) as $node) {
            if ($node['label'] === 'Organisation') {
                continue;
            }

            $this->assertNull($node['route'], "Roadmap entry [{$node['label']}] carries a route.");
            $this->assertTrue($node['locked'], "Roadmap entry [{$node['label']}] is not marked locked.");
            $inert++;
        }

        $this->assertGreaterThan(
            35,
            $inert,
            'Almost nothing was checked, so this guard would pass against an empty menu.'
        );
    }

    /**
     * D-19 is a presentation rule for System Administrators only. Full menu
     * visibility must never leak to anyone else.
     *
     * Mutation: make SystemAdministratorNavigationAuthorizer::allows() return true.
     */
    public function test_the_roadmap_is_visible_to_a_system_administrator_only(): void
    {
        $organisation = $this->make->organisation();

        $member = $this->make->user($organisation);

        $response = $this->actingAsUser($member)->get('/console');

        $this->assertSame([], $this->productAreas($response));

        // And not merely filtered out of the prop - absent from the delivered HTML.
        foreach (['Sales Intelligence', 'Semantic Model', 'Access Reviews'] as $roadmapLabel) {
            $response->assertDontSee($roadmapLabel);
        }
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

        foreach ($areas as $area) {
            if ($area['key'] === ProductArea::SystemAdministration->value) {
                continue;
            }

            foreach ($this->flatten([$area]) as $node) {
                $this->assertNull(
                    $node['route'],
                    "[{$node['label']}] in area [{$area['key']}] has a destination. Fabric "
                    .'Configuration is Phase 2 and SemantIQ Workplace is Phase 3; neither has any '
                    .'delivered screen, so neither may be navigable.'
                );
            }
        }
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
     * Every label the sidebar renders, groups and children alike, in order.
     *
     * @param  list<array<string, mixed>>  $areas
     * @return list<string>
     */
    private function nodeLabels(array $areas): array
    {
        return array_column($this->flatten($areas), 'label');
    }

    /**
     * Every node in the tree, flattened depth-first in render order.
     *
     * @param  list<array<string, mixed>>  $areas
     * @return list<array<string, mixed>>
     */
    private function flatten(array $areas): array
    {
        $flat = [];

        $walk = function (array $nodes) use (&$walk, &$flat): void {
            foreach ($nodes as $node) {
                $flat[] = $node;
                $walk($node['children']);
            }
        };

        foreach ($areas as $area) {
            $walk($area['nodes']);
        }

        return $flat;
    }

    /**
     * Label => URL for everything the sidebar can actually navigate to.
     *
     * @param  list<array<string, mixed>>  $areas
     * @return array<string, string>
     */
    private function reachable(array $areas): array
    {
        $reachable = [];

        foreach ($this->flatten($areas) as $node) {
            if ($node['route'] !== null) {
                $reachable[$node['label']] = $node['route'];
            }
        }

        return $reachable;
    }

    /**
     * The approved menu, written out independently of the code that builds it.
     *
     * This list was FIRST written by reading ApprovedMenu, which made it
     * self-referential: deleting an entry from the menu changed both sides at
     * once and the test still passed. A mutation proved it. It is restated here
     * from the phase authority documents instead, so that changing the menu
     * fails this test and forces the change to be checked against the approved
     * documents rather than merely against itself.
     *
     * Order is depth-first in render order: an area's entries, and a group's
     * children immediately after the group.
     *
     * @return list<string>
     */
    private function approvedLabels(): array
    {
        return [
            // SemantIQ Workplace - Phase 3.
            'Home',
            'My Intelligence',
            'Executive Intelligence',
            'Sales Intelligence',
            'Finance Intelligence',
            'People Intelligence',
            'Operations Intelligence',
            'Customer Intelligence',
            'Learning Intelligence',
            'Custom Intelligence',
            'Explore',
            'Ask SemantIQ',
            'Insights',
            'Risks & Opportunities',
            'Recommendations',
            'Decisions & Alerts',
            'Reports & Dashboards',
            'My Workspace',
            'Help',

            // Fabric Configuration - Phase 2.
            'Overview',
            'Data Sources',
            'Connect Source',
            'Discovery',
            'Data Classification',
            'Ingestion',
            'Data Quality',
            'Business Model',
            'Security Mapping',
            'Semantic Model',
            'AI Readiness',
            'Pipelines & Refresh',
            'Power BI Publication',
            'Monitoring',

            // System Administration - Phase 1.
            'Administration Home',
            'Organisation',
            'Users & Groups',
            'Roles & Access',
            'Business Domains',
            'Identity & SSO',
            'Security Status',
            'Access Reviews',
            'Audit',
            'System Health',
        ];
    }

    private function actingAsUser(User $user): self
    {
        return $this->withSession([
            EnsureSessionIsCurrent::SESSION_USER_ID => $user->id,
            EnsureSessionIsCurrent::SESSION_AUTHENTICATED_AT => now()->toIso8601String(),
        ]);
    }
}
