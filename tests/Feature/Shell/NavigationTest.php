<?php

declare(strict_types=1);

namespace Tests\Feature\Shell;

use App\Enums\Role;
use App\Models\User;
use App\Support\Navigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The gate engine and the filter-not-fork rule.
 *
 * Most of these run against a FIXTURE tree rather than the shipped one. The
 * shipped navigation is deliberately tiny, because the template is explicit
 * that the tree is asked and never invented and nothing has been confirmed for
 * this application yet. The machinery still has to be right the day a real tree
 * arrives, so the fixture exercises nesting, empty-group collapse and the
 * breadcrumb - none of which the shipped tree can currently reach.
 */
class NavigationTest extends TestCase
{
    use RefreshDatabase;

    private function navigation(): Navigation
    {
        return app(Navigation::class);
    }

    private function personOn(Role $role): User
    {
        $user = User::query()->create(['name' => 'Test Person', 'email' => uniqid().'@example.test']);
        $user->forceFill(['role' => $role])->save();

        return $user->refresh();
    }

    /**
     * A tree with the shapes the shipped one does not yet have: a group, a
     * nested group, and a group whose only child is out of reach.
     */
    private function useFixtureTree(): void
    {
        config()->set('navigation.clusters', [
            'Workspace' => [
                ['label' => 'Dashboard', 'icon' => 'i-grid', 'route' => 'dashboard', 'policy' => 'workspace'],
                [
                    'label' => 'Sources', 'icon' => 'i-grid', 'policy' => 'workspace',
                    'children' => [
                        ['label' => 'All sources', 'icon' => 'i-grid', 'route' => 'profile', 'policy' => 'workspace'],
                        [
                            'label' => 'Connections', 'icon' => 'i-key', 'policy' => 'workspace',
                            'children' => [
                                ['label' => 'Gateways', 'icon' => 'i-key', 'policy' => 'system-admin'],
                            ],
                        ],
                    ],
                ],
            ],
            'System Administration' => [
                [
                    'label' => 'Integrations', 'icon' => 'i-key', 'policy' => 'system-admin',
                    'children' => [
                        ['label' => 'Providers', 'icon' => 'i-key', 'policy' => 'system-admin'],
                    ],
                ],
            ],
        ]);
    }

    #[Test]
    public function a_guest_sees_no_navigation_at_all(): void
    {
        $this->assertSame([], $this->navigation()->for(null));
    }

    #[Test]
    public function the_clusters_keep_their_fixed_order(): void
    {
        $this->useFixtureTree();

        $clusters = array_keys($this->navigation()->for($this->personOn(Role::SystemAdmin)));

        // The four are a closed, ordered set. Workspace always precedes System
        // Administration, whatever order the config happens to be edited into.
        $this->assertSame(['Workspace', 'System Administration'], $clusters);
    }

    #[Test]
    public function a_cluster_with_nothing_visible_is_not_rendered(): void
    {
        $this->useFixtureTree();

        $clusters = $this->navigation()->for($this->personOn(Role::Viewer));

        // A Viewer reaches Workspace and nothing in System Administration, so
        // that cluster disappears rather than rendering an empty heading.
        $this->assertArrayHasKey('Workspace', $clusters);
        $this->assertArrayNotHasKey('System Administration', $clusters);
    }

    #[Test]
    public function a_group_whose_children_are_all_filtered_away_goes_with_them(): void
    {
        $this->useFixtureTree();

        $workspace = $this->navigation()->for($this->personOn(Role::Viewer))['Workspace'];
        $sources = collect($workspace)->firstWhere('label', 'Sources');

        $labels = collect($sources['children'])->pluck('label')->all();

        // "Connections" holds only a system-admin leaf. A header opening onto
        // nothing is worse than no header, so it is gone.
        $this->assertSame(['All sources'], $labels);
    }

    #[Test]
    public function a_higher_tier_sees_the_nested_group_the_lower_tier_cannot(): void
    {
        $this->useFixtureTree();

        $workspace = $this->navigation()->for($this->personOn(Role::SystemAdmin))['Workspace'];
        $sources = collect($workspace)->firstWhere('label', 'Sources');

        $this->assertSame(['All sources', 'Connections'], collect($sources['children'])->pluck('label')->all());
    }

    #[Test]
    public function tiers_are_cumulative(): void
    {
        $navigation = $this->navigation();

        $this->assertTrue($navigation->allows($this->personOn(Role::SystemAdmin), 'workspace'));
        $this->assertTrue($navigation->allows($this->personOn(Role::SystemAdmin), 'app-admin'));
        $this->assertTrue($navigation->allows($this->personOn(Role::Admin), 'compliance'));
        $this->assertFalse($navigation->allows($this->personOn(Role::Admin), 'system-admin'));
        $this->assertFalse($navigation->allows($this->personOn(Role::Contributor), 'compliance'));
    }

    #[Test]
    public function an_unknown_policy_denies(): void
    {
        // A typo in a policy name must never become an accidental grant.
        $this->assertFalse($this->navigation()->allows($this->personOn(Role::SystemAdmin), 'does-not-exist'));
        $this->assertFalse($this->navigation()->allows($this->personOn(Role::SystemAdmin), null));
    }

    #[Test]
    public function the_breadcrumb_carries_the_full_path_from_the_cluster_down(): void
    {
        $this->useFixtureTree();

        $trail = $this->navigation()->trailFor($this->personOn(Role::SystemAdmin), 'profile');

        $this->assertSame(
            ['Workspace', 'Sources', 'All sources'],
            collect($trail)->pluck('label')->all(),
        );

        // The cluster is a heading, and a group has no page of its own, so
        // neither is a link. Only the leaf is a destination.
        $this->assertTrue($trail[0]['cluster']);
        $this->assertNull($trail[0]['route']);
        $this->assertNull($trail[1]['route']);
        $this->assertSame('profile', $trail[2]['route']);
    }

    #[Test]
    public function a_route_outside_the_visible_tree_has_no_trail(): void
    {
        $this->useFixtureTree();

        $this->assertSame([], $this->navigation()->trailFor($this->personOn(Role::Viewer), 'nowhere'));
    }
}
