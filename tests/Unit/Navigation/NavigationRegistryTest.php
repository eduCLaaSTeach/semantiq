<?php

declare(strict_types=1);

namespace Tests\Unit\Navigation;

use App\Shared\Navigation\Contracts\NavigationAuthorizer;
use App\Shared\Navigation\NavigationNode;
use App\Shared\Navigation\NavigationRegistry;
use App\Shared\Navigation\ProductArea;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

/**
 * The "no placeholder screens" rule, proven at the boundary that enforces it.
 *
 * D-19 made the complete roadmap visible, which changes what a placeholder is:
 * a roadmap entry is allowed to be visible, and is NOT allowed to have a
 * destination. These guards prove the registry and the node type keep those two
 * things apart rather than trusting the menu author to.
 */
final class NavigationRegistryTest extends TestCase
{
    /**
     * Nothing at all is offered to an actor who is not a System Administrator.
     *
     * The roadmap is registered at boot, so this no longer passes because the
     * registry is empty - it passes because the authorizer denies. Mutation:
     * make SystemAdministratorNavigationAuthorizer::allows() return true.
     */
    public function test_an_unauthenticated_actor_is_offered_no_navigation(): void
    {
        $registry = app(NavigationRegistry::class);

        $this->assertNotSame(
            [],
            $registry->all(),
            'The registry holds no nodes, so this test would pass for the wrong reason.'
        );

        $this->assertSame(
            [],
            $registry->visibleFor(),
            'Navigation was offered to an actor with no session. Menu visibility is not the '
            .'access control, but it must not advertise the product to a stranger either.'
        );
    }

    public function test_a_node_cannot_be_created_without_a_policy_key(): void
    {
        $this->expectException(InvalidArgumentException::class);

        NavigationNode::leaf(ProductArea::SystemAdministration, 'Users', 'i-users', 'console.home', '');
    }

    public function test_a_delivered_node_cannot_be_created_without_a_route(): void
    {
        $this->expectException(InvalidArgumentException::class);

        NavigationNode::leaf(ProductArea::SystemAdministration, 'Users', 'i-users', '', 'users.view');
    }

    public function test_a_node_cannot_be_created_without_an_icon(): void
    {
        $this->expectException(InvalidArgumentException::class);

        NavigationNode::locked(ProductArea::SystemAdministration, 'Users', '', 'users.view');
    }

    /**
     * The rule D-19 rests on: a roadmap entry has NO destination.
     *
     * A locked node holding a route would be a menu entry that looks
     * unavailable and is reachable by URL - the exact placeholder the design
     * forbids. The type makes it unrepresentable.
     *
     * Mutation: drop the locked-and-routed check from the constructor.
     */
    public function test_a_locked_node_cannot_carry_a_route(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // No factory offers this combination, which is the point. Reflection is
        // the only way to ask for it, and the constructor still refuses.
        $constructor = (new \ReflectionClass(NavigationNode::class))->getConstructor();
        $constructor->setAccessible(true);

        $constructor->invoke(
            (new \ReflectionClass(NavigationNode::class))->newInstanceWithoutConstructor(),
            ProductArea::SystemAdministration,
            'Users & Groups',
            'i-users',
            'console.home',
            'administration.view',
            true,
            [],
        );
    }

    /** An accordion that opens onto nothing is refused. */
    public function test_a_group_cannot_be_created_without_children(): void
    {
        $this->expectException(InvalidArgumentException::class);

        NavigationNode::group(
            ProductArea::SemantiqWorkplace,
            'My Intelligence',
            'i-brain',
            'workplace.view',
            [],
        );
    }

    public function test_registering_a_node_for_an_undefined_route_is_refused(): void
    {
        $this->expectException(RuntimeException::class);

        app(NavigationRegistry::class)->add(NavigationNode::leaf(
            ProductArea::SystemAdministration,
            'Users',
            'i-users',
            'route.that.does.not.exist',
            'users.view',
        ));
    }

    /**
     * The same check reaches a group's children, not just its head.
     *
     * A group carries no route of its own, so a broken route hidden one level
     * down is exactly where this guard would be missed.
     *
     * Mutation: check only $node in NavigationRegistry::add().
     */
    public function test_a_child_pointing_at_an_undefined_route_is_refused(): void
    {
        $this->expectException(RuntimeException::class);

        app(NavigationRegistry::class)->add(NavigationNode::group(
            ProductArea::SystemAdministration,
            'Users & Groups',
            'i-users',
            'administration.view',
            [
                NavigationNode::leaf(
                    ProductArea::SystemAdministration,
                    'Users',
                    'i-users',
                    'route.that.does.not.exist',
                    'users.view',
                ),
            ],
            locked: false,
        ));
    }

    /**
     * A roadmap entry is presented with NO route, and a delivered one with a
     * real URL. This is the shape the shell relies on to refuse to link.
     */
    public function test_a_locked_node_is_presented_without_any_destination(): void
    {
        $registry = $this->permissiveRegistry();

        $registry->add(NavigationNode::locked(
            ProductArea::SystemAdministration,
            'Audit',
            'i-scroll',
            'administration.view',
        ));

        $registry->add(NavigationNode::leaf(
            ProductArea::SystemAdministration,
            'Organisation',
            'i-sitemap',
            'organisation.profile',
            'organisation.view',
        ));

        $nodes = $registry->visibleFor()[0]['nodes'];

        $this->assertSame(['Audit', 'Organisation'], array_column($nodes, 'label'));
        $this->assertNull($nodes[0]['route'], 'A locked node was given a destination.');
        $this->assertTrue($nodes[0]['locked']);
        $this->assertSame('/console/organisation', $nodes[1]['route']);
        $this->assertFalse($nodes[1]['locked']);
    }

    /** A group's children are presented too, each carrying the same contract. */
    public function test_group_children_are_presented_and_carry_no_destination(): void
    {
        $registry = $this->permissiveRegistry();

        $registry->add(NavigationNode::group(
            ProductArea::SemantiqWorkplace,
            'My Intelligence',
            'i-brain',
            'workplace.view',
            [
                NavigationNode::locked(
                    ProductArea::SemantiqWorkplace,
                    'Sales Intelligence',
                    'i-trending-up',
                    'workplace.view',
                ),
            ],
        ));

        $group = $registry->visibleFor()[0]['nodes'][0];

        $this->assertNull($group['route']);
        $this->assertSame(['Sales Intelligence'], array_column($group['children'], 'label'));
        $this->assertNull($group['children'][0]['route'], 'A roadmap child was given a destination.');
        $this->assertTrue($group['children'][0]['locked']);
    }

    /**
     * A node whose policy key is denied does not render, and an area left with
     * no visible node does not render either.
     *
     * Menu filtering is UX. It denies here because the authorizer denies - and
     * even if it wrongly allowed a node, the route behind it re-authorises on
     * its own (see DenyByDefaultTest).
     */
    public function test_an_authorised_area_with_no_visible_nodes_does_not_render(): void
    {
        $registry = new NavigationRegistry(
            new class implements NavigationAuthorizer
            {
                public function allows(string $policyKey): bool
                {
                    return false;
                }
            },
            app('router'),
        );

        $registry->add(NavigationNode::locked(
            ProductArea::SystemAdministration,
            'Audit',
            'i-scroll',
            'administration.view',
        ));

        $this->assertSame([], $registry->visibleFor());
    }

    /** D-23: the declared area order is what the shell receives. */
    public function test_areas_are_presented_in_the_approved_order(): void
    {
        $registry = $this->permissiveRegistry();

        // Registered in the reverse of the approved order on purpose: the
        // ORDER OF REGISTRATION must not be what decides the sidebar.
        foreach ([
            ProductArea::SystemAdministration,
            ProductArea::FabricConfiguration,
            ProductArea::SemantiqWorkplace,
        ] as $area) {
            $registry->add(NavigationNode::locked($area, 'Overview', 'i-gauge', 'any.view'));
        }

        $this->assertSame(
            [
                ProductArea::SemantiqWorkplace->value,
                ProductArea::FabricConfiguration->value,
                ProductArea::SystemAdministration->value,
            ],
            array_column($registry->visibleFor(), 'key'),
            'The sidebar order does not follow the approved product-area order (D-23).'
        );
    }

    private function permissiveRegistry(): NavigationRegistry
    {
        return new NavigationRegistry(
            new class implements NavigationAuthorizer
            {
                public function allows(string $policyKey): bool
                {
                    return true;
                }
            },
            app('router'),
        );
    }
}
