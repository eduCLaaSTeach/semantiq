<?php

declare(strict_types=1);

namespace App\Modules\Platform\Setup\Bootstrap;

/**
 * The local setup administrator, as a value.
 *
 * A DISTINCT TYPE WITH NO RELATIONSHIP TO User, deliberately. It does not
 * extend Model, it does not implement Authenticatable, it has no roles(), no
 * organisation_id and no status - and AccessEngine has no method that accepts
 * it, so "give the bootstrap principal an entitlement" DOES NOT TYPECHECK.
 *
 * That is the difference between a type boundary and a policy. A policy is a
 * rule somebody has to remember while writing the next feature; this one fails
 * at compile time in the hands of the person who would otherwise have broken
 * it, which is the only place a guard is cheap.
 *
 * IT IS NOT IN RoleCatalogue AND MUST NEVER BE. Adding a bootstrap_administrator
 * role would put it inside the access model, where every entitlement mechanism
 * would then have to be taught to exclude it - seven places to get right
 * instead of one type to keep separate.
 */
final readonly class BootstrapPrincipal
{
    public function __construct(
        public int $id,
        public string $email,
    ) {}
}
