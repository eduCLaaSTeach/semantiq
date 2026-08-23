<?php

declare(strict_types=1);

namespace Tests\Feature\Shell;

use App\Enums\Role;
use App\Models\User;
use App\Support\Navigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The shipped navigation tree, checked against the rest of the application.
 *
 * These are the "nothing left hanging" tests. Every one of them guards a way
 * the tree can quietly stop agreeing with the code around it - a node pointing
 * at a deleted route, a route nothing can reach, a policy name nobody declared.
 * None of those break a page. They all produce a rail that lies, and a rail
 * that lies is worse than a short one.
 *
 * They run against the REAL config/navigation.php, not a fixture, because the
 * whole point is to check the shipped tree.
 */
class NavigationIntegrityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every node in the tree, flattened, with the path that reached it.
     *
     * @return list<array{path: string, node: array<string, mixed>}>
     */
    private function everyNode(): array
    {
        $flat = [];

        $walk = function (array $nodes, string $prefix) use (&$walk, &$flat): void {
            foreach ($nodes as $node) {
                $path = $prefix.' > '.$node['label'];
                $flat[] = ['path' => $path, 'node' => $node];

                if (is_array($node['children'] ?? null)) {
                    $walk($node['children'], $path);
                }
            }
        };

        foreach ((array) config('navigation.clusters', []) as $cluster => $nodes) {
            $walk($nodes, $cluster);
        }

        return $flat;
    }

    #[Test]
    public function every_node_names_a_policy_that_exists(): void
    {
        $declared = array_keys((array) config('navigation.policies', []));

        foreach ($this->everyNode() as $entry) {
            $policy = $entry['node']['policy'] ?? null;

            $this->assertNotNull($policy, $entry['path'].' names no policy');

            // An unknown policy denies, so a typo does not become a grant - it
            // becomes an invisible node nobody can reach and nobody notices.
            $this->assertContains($policy, $declared, $entry['path'].' names undeclared policy "'.$policy.'"');
        }
    }

    #[Test]
    public function every_node_names_an_icon_that_is_in_the_registry(): void
    {
        $registry = file_get_contents(resource_path('views/partials/icons.blade.php'));

        foreach ($this->everyNode() as $entry) {
            $icon = $entry['node']['icon'] ?? null;

            $this->assertNotNull($icon, $entry['path'].' names no icon');

            // A missing symbol renders as an empty box rather than an error.
            $this->assertStringContainsString('id="'.$icon.'"', (string) $registry, $entry['path'].' uses missing icon "'.$icon.'"');
        }
    }

    #[Test]
    public function every_node_route_resolves(): void
    {
        foreach ($this->everyNode() as $entry) {
            $routeName = $entry['node']['route'] ?? null;

            if ($routeName === null) {
                continue;
            }

            $this->assertTrue(
                Route::has($routeName),
                $entry['path'].' points at route "'.$routeName.'" which does not exist',
            );

            // Generating the URL is the real check: a route with a required
            // segment and no `route_parameters` throws here rather than on the
            // first page load in production.
            $url = route($routeName, $entry['node']['route_parameters'] ?? []);

            $this->assertIsString($url);
        }
    }

    #[Test]
    public function every_administration_route_is_reachable_from_the_rail(): void
    {
        $admin = User::query()->create(['name' => 'Ada Admin', 'email' => 'ada@example.test']);
        $admin->forceFill(['role' => Role::SystemAdmin])->save();

        $inRail = [];

        foreach (app(Navigation::class)->for($admin->refresh()) as $nodes) {
            array_walk_recursive($nodes, function ($value, $key) use (&$inRail): void {
                if ($key === 'route') {
                    $inRail[] = $value;
                }
            });
        }

        foreach (Route::getRoutes()->getRoutesByMethod()['GET'] as $route) {
            $name = $route->getName();

            if ($name === null || ! str_starts_with($name, 'admin.')) {
                continue;
            }

            // The other half of "nothing hanging": a screen nobody can navigate
            // to is as much a defect as a link to nothing. Anything genuinely
            // reached only from another page would need an exception listed
            // here, deliberately, rather than by omission.
            $this->assertContains($name, $inRail, 'Route "'.$name.'" exists but nothing in the rail reaches it');
        }
    }

    #[Test]
    public function two_nodes_sharing_a_route_are_told_apart_by_their_parameters(): void
    {
        $navigation = app(Navigation::class);

        $general = ['route' => 'admin.system.settings', 'route_parameters' => ['category' => 'general']];
        $environment = ['route' => 'admin.system.settings', 'route_parameters' => ['category' => 'environment']];

        // Without the parameter comparison one of the pair would look
        // permanently active while the other was open.
        $this->assertTrue($navigation->matches($general, 'admin.system.settings', ['category' => 'general']));
        $this->assertFalse($navigation->matches($environment, 'admin.system.settings', ['category' => 'general']));
    }

    #[Test]
    public function the_breadcrumb_reaches_the_right_one_of_a_shared_route(): void
    {
        $admin = User::query()->create(['name' => 'Ada Admin', 'email' => 'ada@example.test']);
        $admin->forceFill(['role' => Role::SystemAdmin])->save();

        $trail = app(Navigation::class)->trailFor($admin->refresh(), 'admin.system.settings', ['category' => 'environment']);

        $this->assertSame(
            ['System Administration', 'System Configuration', 'Environment Settings'],
            array_column($trail, 'label'),
        );
    }
}
