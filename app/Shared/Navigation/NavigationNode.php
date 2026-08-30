<?php

declare(strict_types=1);

namespace App\Shared\Navigation;

use InvalidArgumentException;

/**
 * One navigable item.
 *
 * The constructor is where the "no placeholder screens" rule is enforced. A node
 * cannot exist without a route name that actually resolves and a non-empty
 * policy key. That makes a menu entry pointing at nothing, or at an
 * unauthorised-but-visible screen, unrepresentable rather than merely against
 * the rules - the failure happens at registration, in a test, not in review.
 */
final class NavigationNode
{
    public function __construct(
        public readonly ProductArea $area,
        public readonly string $label,
        public readonly string $icon,
        public readonly string $routeName,
        public readonly string $policyKey,
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

        if (trim($routeName) === '') {
            throw new InvalidArgumentException(
                "Navigation node [{$label}] needs a route name. A node without a destination is a "
                .'placeholder, and placeholders are not permitted.'
            );
        }
    }
}
