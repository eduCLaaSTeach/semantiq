<?php

declare(strict_types=1);

namespace App\Shared\Navigation;

use App\Shared\Navigation\Contracts\NavigationAuthorizer;
use Illuminate\Contracts\Routing\Registrar;
use RuntimeException;

/**
 * The single source of the sidebar.
 *
 * D-19 changed what this renders. It used to drop any product area holding no
 * node, which is what kept Fabric Configuration and SemantIQ Workplace out of
 * the sidebar entirely. The Product Owner now wants the complete approved
 * structure visible to System Administrators, so the shape of the product is
 * legible rather than looking like a one-feature application.
 *
 * Visibility is not access. A locked node carries NO route, so there is nothing
 * to navigate to and nothing to authorise; NavigationNode refuses to construct
 * a locked node with a route at all. Every delivered route still re-authorises
 * on its own.
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
     * A node that links must link somewhere real: registering a leaf for a
     * route that has not been defined is the exact shape of a placeholder menu
     * entry, so it throws rather than rendering a link to a 404. A locked node
     * has no route by construction and skips the check.
     */
    public function add(NavigationNode $node): void
    {
        foreach ([$node, ...$node->children] as $candidate) {
            if ($candidate->routeName === null) {
                continue;
            }

            $routes = $this->router->getRoutes();

            // A route is named fluently - Route::get(...)->name('x') - so it is
            // added to the collection before its name is set, and the name
            // lookup is stale until refreshed.
            $routes->refreshNameLookups();

            if ($routes->getByName($candidate->routeName) === null) {
                throw new RuntimeException(
                    "Navigation node [{$candidate->label}] points at route "
                    ."[{$candidate->routeName}], which is not defined. Register the route before "
                    .'the node, or do not register the node.'
                );
            }
        }

        $this->nodes[] = $node;
    }

    /**
     * The areas the current actor may see, each with its visible nodes.
     *
     * @return list<array{key: string, label: string, expanded: bool, nodes: list<array<string, mixed>>}>
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

                $nodes[] = $this->present($node);
            }

            if ($nodes === []) {
                continue;
            }

            $areas[] = [
                'key' => $area->value,
                'label' => $area->label(),
                'expanded' => $area->expandedByDefault(),
                'nodes' => $nodes,
            ];
        }

        return $areas;
    }

    /**
     * One node as the shell receives it.
     *
     * route is null for anything locked, so the client has no destination to
     * render even if it tried.
     *
     * @return array<string, mixed>
     */
    private function present(NavigationNode $node): array
    {
        return [
            'label' => $node->label,
            'icon' => $node->icon,
            'route' => $node->routeName === null ? null : route($node->routeName, absolute: false),
            'locked' => $node->locked,
            'children' => array_map(
                fn (NavigationNode $child): array => $this->present($child),
                $node->children
            ),
        ];
    }

    /** @return list<NavigationNode> */
    public function all(): array
    {
        return $this->nodes;
    }
}
