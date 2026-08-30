<?php

declare(strict_types=1);

namespace Tests\Unit\Navigation;

use App\Shared\Navigation\NavigationNode;
use App\Shared\Navigation\NavigationRegistry;
use App\Shared\Navigation\ProductArea;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

/**
 * The "no placeholder screens" rule, proven at the boundary that enforces it.
 */
final class NavigationRegistryTest extends TestCase
{
    public function test_p1_base_exposes_no_navigation_at_all(): void
    {
        $this->assertSame(
            [],
            app(NavigationRegistry::class)->visibleFor(),
            'P1-BASE registered navigation. Nothing is implemented to navigate to yet.'
        );
    }

    public function test_a_node_cannot_be_created_without_a_policy_key(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new NavigationNode(ProductArea::SystemAdministration, 'Users', 'users', 'app.home', '');
    }

    public function test_a_node_cannot_be_created_without_a_route(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new NavigationNode(ProductArea::SystemAdministration, 'Users', 'users', '', 'users.view');
    }

    public function test_a_node_cannot_be_created_without_an_icon(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new NavigationNode(ProductArea::SystemAdministration, 'Users', '', 'app.home', 'users.view');
    }

    public function test_registering_a_node_for_an_undefined_route_is_refused(): void
    {
        $this->expectException(RuntimeException::class);

        app(NavigationRegistry::class)->add(new NavigationNode(
            ProductArea::SystemAdministration,
            'Users',
            'users',
            'route.that.does.not.exist',
            'users.view',
        ));
    }

    /**
     * Menu filtering is UX. It denies here because the P1-BASE authorizer denies
     * everything - and even if it wrongly allowed a node, the route behind it
     * re-authorises on its own (see DenyByDefaultTest).
     */
    public function test_an_authorised_area_with_no_visible_nodes_does_not_render(): void
    {
        $registry = app(NavigationRegistry::class);

        $registry->add(new NavigationNode(
            ProductArea::SystemAdministration,
            'App home',
            'home',
            'app.home',
            'platform.view',
        ));

        $this->assertSame([], $registry->visibleFor());
    }
}
