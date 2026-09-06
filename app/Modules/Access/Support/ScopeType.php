<?php

declare(strict_types=1);

namespace App\Modules\Access\Support;

/**
 * Which records inside an entitled domain a grant path may reach.
 *
 * FIVE VALUES, and `domain` and `organisation` are BOTH kept - D-74, decided as
 * option (b). Under Release 1's single organisation, with the entitlement
 * already naming a domain, the two resolve to the SAME record set. That is
 * stated on the screen rather than left for the next administrator to work out,
 * because two identical choices presented without explanation is a trap.
 *
 * `organisation` is the honest name for what is granted today. `domain` is
 * reserved for the partition that does not exist yet - when a domain is later
 * split across tenants or legal entities, `domain` becomes narrower, and
 * removing it now would mean migrating live grants to reintroduce it.
 *
 * Both resolve through ONE resolver. Two paths that are equal today drift
 * silently the day a partition arrives, and the drift would be a security
 * change nobody reviewed.
 */
enum ScopeType: string
{
    case Own = 'own';
    case Team = 'team';
    case BusinessUnit = 'business_unit';
    case Domain = 'domain';
    case Organisation = 'organisation';

    public function label(): string
    {
        return match ($this) {
            self::Own => 'Own records',
            self::Team => 'Team',
            self::BusinessUnit => 'Business unit',
            self::Domain => 'Domain',
            self::Organisation => 'Organisation',
        };
    }

    /**
     * Which structural target column this scope requires. NULL means the scope
     * carries no target, and storing one would be a contradiction the screen
     * cannot explain.
     */
    public function targetColumn(): ?string
    {
        return match ($this) {
            self::Team => 'team_id',
            self::BusinessUnit => 'business_unit_id',
            self::Own, self::Domain, self::Organisation => null,
        };
    }

    public function requiresTarget(): bool
    {
        return $this->targetColumn() !== null;
    }

    /**
     * Whether this scope reaches every record in the entitled domain. Domain and
     * Organisation both do, and that is the D-74 equivalence.
     */
    public function coversWholeDomain(): bool
    {
        return $this === self::Domain || $this === self::Organisation;
    }

    public function description(): string
    {
        return match ($this) {
            self::Own => 'Only records where this person is the subject or the assigned owner.',
            self::Team => 'Records belonging to one explicitly assigned team.',
            self::BusinessUnit => 'Records belonging to one business unit, through its departments and teams.',
            self::Domain => 'Every record in this domain. Reserved for a future partition; today this is the same as Organisation.',
            self::Organisation => 'Every record in this domain, across the organisation. Today this is the same as Domain.',
        };
    }
}
