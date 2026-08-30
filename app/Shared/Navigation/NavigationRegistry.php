<?php

declare(strict_types=1);

namespace App\Shared\Navigation;

use App\Shared\Navigation\Contracts\NavigationAuthorizer;
use Illuminate\Contracts\Routing\Registrar;
use RuntimeException;

/**
 * The single source of the sidebar.
 *
 * P1-BASE registers the three product areas and ZERO nodes, because nothing is
 * implemented to navigate to yet. Later units register their own nodes as they
 * deliver real screens.
 *
 * An area with no visible nodes does not render. That is what keeps Fabric
 * Configuration and SemantIQ Workplace out of the Phase 1 sidebar without any
 * special-casing: they have no nodes, so they do not appear.
 */
final class NavigationRegistry
{
    /** @var list<NavigationNode> */
    private array $nodes = [];

    public function __construct(
        private readonly NavigationAuthorizer $authorizer,
        private readonly Registrar $router,
    ) {}

    /**
     * Register a node.
     *
     * The route must already exist. Registering a node for a route that has not
     * been defined is the exact shape of a placeholder menu entry, so it throws
     * rather than rendering a link to a 404.
     */
    public function add(NavigationNode $node): void
    {
        if ($this->router->getRoutes()->getByName($node->routeName) === null) {
            throw new RuntimeException(
                "Navigation node [{$node->label}] points at route [{$node->routeName}], which is "
                .'not defined. Register the route before the node, or do not register the node.'
            );
        }

        $this->nodes[] = $node;
    }

    /**
     * The areas the current actor may see, each with its visible nodes.
     *
     * Empty areas are dropped. In P1-BASE this returns an empty array, because
     * the authorizer denies everything and there are no nodes to deny.
     *
     * @return list<array{key: string, label: string, nodes: list<array{label: string, icon: string, route: string}>}>
     */
    public function visibleFor(): array
    {
        $areas = [];

        foreach (ProductArea::cases() as $area) {
            $nodes = [];

            foreach ($this->nodes as $node) {
                if ($node->area !== $area) {
                    continue;
                }

                if (! $this->authorizer->allows($node->policyKey)) {
                    continue;
                }

                $nodes[] = [
                    'label' => $node->label,
                    'icon' => $node->icon,
                    'route' => route($node->routeName, absolute: false),
                ];
            }

            if ($nodes === []) {
                continue;
            }

            $areas[] = ['key' => $area->value, 'label' => $area->label(), 'nodes' => $nodes];
        }

        return $areas;
    }

    /** @return list<NavigationNode> */
    public function all(): array
    {
        return $this->nodes;
    }
}
