<?php

declare(strict_types=1);

namespace Tests\Feature\Shell;

use App\Enums\Role;
use App\Models\User;
use App\Support\Navigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    use RefreshDatabase;

    private function userWith(Role $role): User
    {
        $user = User::query()->create([
            'name' => 'Test Person',
            'email' => Str()->lower($role->value).'@example.test',
            'password' => null,
        ]);

        $user->forceFill(['role' => $role])->save();

        return $user->refresh();
    }

    /**
     * @return list<string>
     */
    private function clusterNamesFor(Role $role): array
    {
        $tree = app(Navigation::class)->for($this->userWith($role));

        return array_column($tree, 'cluster');
    }

    #[Test]
    public function a_platform_administrator_sees_all_four_clusters(): void
    {
        $this->assertSame(
            ['Workspace', 'Compliance', 'Application Administration', 'System Administration'],
            $this->clusterNamesFor(Role::SystemAdmin)
        );
    }

    #[Test]
    public function a_tenant_administrator_does_not_see_system_administration(): void
    {
        $this->assertSame(
            ['Workspace', 'Compliance', 'Application Administration'],
            $this->clusterNamesFor(Role::Admin)
        );
    }

    #[Test]
    public function a_collaborator_sees_workspace_and_compliance_only(): void
    {
        $this->assertSame(['Workspace', 'Compliance'], $this->clusterNamesFor(Role::Team));
    }

    #[Test]
    public function a_contributor_sees_workspace_only(): void
    {
        $this->assertSame(['Workspace'], $this->clusterNamesFor(Role::SelfService));
    }

    #[Test]
    public function a_viewer_sees_workspace_only(): void
    {
        $this->assertSame(['Workspace'], $this->clusterNamesFor(Role::Viewer));
    }

    #[Test]
    public function an_empty_cluster_is_dropped_rather_than_rendered_bare(): void
    {
        // Every node in this cluster is out of reach for a Viewer.
        $tree = app(Navigation::class)->for($this->userWith(Role::Viewer));
        $names = array_column($tree, 'cluster');

        $this->assertNotContains('Application Administration', $names);
        $this->assertNotContains('System Administration', $names);
    }

    #[Test]
    public function an_unknown_policy_denies_rather_than_allows(): void
    {
        $this->assertFalse(
            app(Navigation::class)->allows($this->userWith(Role::SystemAdmin), 'no-such-policy')
        );
    }

    #[Test]
    public function the_active_route_marks_its_leaf_and_opens_nothing_else(): void
    {
        $tree = app(Navigation::class)->for($this->userWith(Role::SystemAdmin), 'dashboard');

        $dashboard = collect($tree[0]['nodes'])->firstWhere('label', 'Dashboard');

        $this->assertTrue($dashboard['is_active']);
        $this->assertTrue($dashboard['is_built']);
    }

    #[Test]
    public function an_unbuilt_leaf_is_marked_so_it_renders_disabled_rather_than_linked(): void
    {
        $tree = app(Navigation::class)->for($this->userWith(Role::SystemAdmin));

        $projects = collect($tree[0]['nodes'])->firstWhere('group', 'Projects');
        $blueprints = collect($projects['children'])->firstWhere('label', 'Blueprints');

        $this->assertFalse($blueprints['is_built']);
        $this->assertArrayNotHasKey('route', $blueprints);
    }

    #[Test]
    public function the_breadcrumb_trail_runs_from_the_cluster_down(): void
    {
        $this->assertSame(
            ['Workspace', 'Dashboard'],
            app(Navigation::class)->trailFor('dashboard')
        );
    }

    #[Test]
    public function every_icon_in_the_tree_is_registered_in_the_sprite(): void
    {
        $sprite = file_get_contents(resource_path('views/components/icon-sprite.blade.php'));

        $collect = function (array $nodes) use (&$collect): array {
            $icons = [];

            foreach ($nodes as $node) {
                $icons[] = $node['icon'];

                if (isset($node['children'])) {
                    $icons = [...$icons, ...$collect($node['children'])];
                }
            }

            return $icons;
        };

        $icons = [];

        foreach (config('navigation.clusters') as $cluster) {
            $icons = [...$icons, ...$collect($cluster['nodes'])];
        }

        foreach (array_unique($icons) as $icon) {
            $this->assertStringContainsString(
                'id="'.$icon.'"',
                $sprite,
                "The navigation references {$icon}, which is not in the icon registry."
            );
        }
    }

    #[Test]
    public function no_group_is_named_after_its_own_cluster(): void
    {
        foreach (config('navigation.clusters') as $cluster) {
            foreach ($cluster['nodes'] as $node) {
                if (isset($node['group'])) {
                    $this->assertNotSame($cluster['cluster'], $node['group']);
                }
            }
        }
    }

    #[Test]
    public function groups_never_nest_deeper_than_three_levels(): void
    {
        $depth = function (array $nodes, int $level) use (&$depth): int {
            $deepest = $level;

            foreach ($nodes as $node) {
                if (isset($node['children'])) {
                    $deepest = max($deepest, $depth($node['children'], $level + 1));
                }
            }

            return $deepest;
        };

        foreach (config('navigation.clusters') as $cluster) {
            $this->assertLessThanOrEqual(
                3,
                $depth($cluster['nodes'], 0),
                "The {$cluster['cluster']} cluster nests groups deeper than three levels."
            );
        }
    }
}
