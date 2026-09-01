<?php

declare(strict_types=1);

namespace App\Shared\Navigation;

use InvalidArgumentException;

/**
 * One navigable item, or one item on the roadmap that is not navigable yet.
 *
 * THREE SHAPES, and the difference is enforced rather than trusted:
 *
 *  - a LEAF has a route that resolves, and links;
 *  - a LOCKED leaf has NO route at all, and renders as a non-link row carrying
 *    the "Soon" pill;
 *  - a GROUP has children and no route of its own.
 *
 * A locked node holding a route would be the placeholder the design forbids -
 * a menu entry that looks unavailable but is reachable by URL. So the
 * constructor refuses it outright: locked and routed is unrepresentable, not
 * merely discouraged.
 *
 * D-19: the complete roadmap is shown to System Administrators for this release
 * stage so the product's shape is legible. Showing an item grants nothing. The
 * route does not exist, no controller exists, and every delivered route
 * re-authorises independently.
 */
final class NavigationNode
{
    /**
     * @param  list<NavigationNode>  $children
     */
    private function __construct(
        public readonly ProductArea $area,
        public readonly string $label,
        public readonly string $icon,
        public readonly ?string $routeName,
        public readonly string $policyKey,
        public readonly bool $locked,
        public readonly array $children,
    ) {
        if (trim($label) === '') {
            throw new InvalidArgumentException('A navigation node needs a label.');
        }

        if (trim($icon) === '') {
            throw new InvalidArgumentException(
                "Navigation node [{$label}] needs an icon; the design system makes icons mandatory."
            );
        }

        if (trim($policyKey) === '') {
            throw new InvalidArgumentException(
                "Navigation node [{$label}] needs a policy key. A node nothing gates is a node "
                .'that leaks its existence to every viewer.'
            );
        }

        if ($locked && $routeName !== null) {
            throw new InvalidArgumentException(
                "Navigation node [{$label}] is locked but carries a route. A locked node must "
                .'have no destination at all, or it is a placeholder that looks unavailable and '
                .'is reachable anyway.'
            );
        }

        if (! $locked && $children === [] && ($routeName === null || trim($routeName) === '')) {
            throw new InvalidArgumentException(
                "Navigation node [{$label}] needs a route name. A node without a destination is a "
                .'placeholder, and placeholders are not permitted.'
            );
        }
    }

    /** A delivered destination. Its route must resolve. */
    public static function leaf(
        ProductArea $area,
        string $label,
        string $icon,
        string $routeName,
        string $policyKey,
    ): self {
        return new self($area, $label, $icon, $routeName, $policyKey, false, []);
    }

    /**
     * A roadmap entry: visible, never navigable, carrying no route.
     */
    public static function locked(
        ProductArea $area,
        string $label,
        string $icon,
        string $policyKey,
    ): self {
        return new self($area, $label, $icon, null, $policyKey, true, []);
    }

    /**
     * A group of children. The group itself is never a destination.
     *
     * @param  list<NavigationNode>  $children
     */
    public static function group(
        ProductArea $area,
        string $label,
        string $icon,
        string $policyKey,
        array $children,
        bool $locked = true,
    ): self {
        if ($children === []) {
            throw new InvalidArgumentException(
                "Navigation group [{$label}] has no children. An empty accordion is a control "
                .'that opens onto nothing.'
            );
        }

        return new self($area, $label, $icon, null, $policyKey, $locked, $children);
    }

    public function isGroup(): bool
    {
        return $this->children !== [];
    }
}
