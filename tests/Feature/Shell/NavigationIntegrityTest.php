<?php

declare(strict_types=1);

namespace Tests\Feature\Shell;

use App\Enums\Role;
use App\Models\User;
use App\Modules\Identity\Support\PermissionRegistry;
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
            // to is as much a defect as a link to nothing.
            //
            // A screen is reachable if the rail points at it, OR if it is a
            // DETAIL of a screen the rail points at - `admin.users.show` under
            // `admin.users`. A create form or a record page belongs to its
            // index and is reached from there, not from a rail entry of its
            // own; giving every one its own node would be a rail nobody can
            // read. What this still catches is the case that matters: a whole
            // screen with no path to it from anywhere.
            $reachable = in_array($name, $inRail, true)
                || collect($inRail)->contains(fn (?string $parent): bool => $parent !== null && str_starts_with($name, $parent.'.'));

            $this->assertTrue(
                $reachable,
                'Route "'.$name.'" exists but neither the rail nor any screen the rail reaches leads to it',
            );
        }
    }

    #[Test]
    public function every_permission_a_policy_names_is_declared(): void
    {
        $registry = app(PermissionRegistry::class);

        foreach ((array) config('navigation.policies', []) as $policy => $rule) {
            $permission = $rule['permission'] ?? null;

            if ($permission === null) {
                continue;
            }

            // An undeclared permission denies, so a typo here would silently
            // remove a whole branch of the rail for everybody - and denial is
            // exactly the failure nobody reports as a bug.
            $this->assertTrue(
                $registry->has($permission),
                'Policy "'.$policy.'" names undeclared permission "'.$permission.'"',
            );
        }
    }

    #[Test]
    public function a_rail_node_and_the_route_it_points_at_are_gated_alike(): void
    {
        // The drift this guards against: a node gated by one permission
        // pointing at a route gated by another, so the rail offers a link that
        // 403s - or, far worse, the rail hides a link to a route anyone can
        // reach by typing it.
        $mismatches = [];

        $walk = function (array $nodes) use (&$walk, &$mismatches): void {
            foreach ($nodes as $node) {
                $routeName = $node['route'] ?? null;
                $policy = $node['policy'] ?? null;
                $nodePermission = config('navigation.policies.'.$policy.'.permission');

                if ($routeName !== null && $nodePermission !== null) {
                    $route = Route::getRoutes()->getByName($routeName);
                    $middleware = $route === null ? [] : $route->gatherMiddleware();

                    if (! in_array('permission:'.$nodePermission, $middleware, true)) {
                        $mismatches[] = $routeName.' is offered under "'.$nodePermission.'" but its route does not enforce it';
                    }
                }

                if (is_array($node['children'] ?? null)) {
                    $walk($node['children']);
                }
            }
        };

        foreach ((array) config('navigation.clusters', []) as $nodes) {
            $walk($nodes);
        }

        $this->assertSame([], $mismatches, implode('; ', $mismatches));
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
