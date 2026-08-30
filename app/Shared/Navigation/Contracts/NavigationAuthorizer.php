<?php

declare(strict_types=1);

namespace App\Shared\Navigation\Contracts;

/**
 * Decides whether the current actor may SEE a navigation node.
 *
 * This is a UX concern and nothing more. Per the blueprint, menu hiding is never
 * the access control: the route a node points at re-authorises independently,
 * and must continue to do so even if this authorizer were to wrongly allow a
 * node through. Filtering the menu and authorising the request are deliberately
 * two separate code paths so they cannot be collapsed into one by accident.
 *
 * P1-BASE ships DenyAllNavigationAuthorizer. The real implementation arrives
 * with the effective-access engine in P1-05.
 */
interface NavigationAuthorizer
{
    public function allows(string $policyKey): bool;
}
