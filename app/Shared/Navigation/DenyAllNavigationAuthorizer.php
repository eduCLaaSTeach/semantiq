<?php

declare(strict_types=1);

namespace App\Shared\Navigation;

use App\Shared\Navigation\Contracts\NavigationAuthorizer;

/**
 * The P1-BASE authorizer: nothing is authorised.
 *
 * There is no identity in P1-BASE, so there is no access to resolve. Denying is
 * the correct answer rather than a placeholder for one, and it means the
 * deny-by-default path is exercised from the first unit - before there is
 * anything worth protecting, which is the right order to prove it in.
 */
final class DenyAllNavigationAuthorizer implements NavigationAuthorizer
{
    public function allows(string $policyKey): bool
    {
        return false;
    }
}
