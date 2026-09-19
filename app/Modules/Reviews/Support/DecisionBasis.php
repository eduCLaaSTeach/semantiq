<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Support;

/**
 * On what standing the reviewer acted. D-87, as amended by B-1a.
 *
 * DomainOwner is recorded whenever a current owner of the domain performs the
 * review, which is what makes owner accountability visible afterwards rather
 * than merely intended.
 */
enum DecisionBasis: string
{
    case SystemAdministrator = 'system_administrator';
    case OrganisationAdministrator = 'organisation_administrator';
    case DomainOwner = 'domain_owner';
    case SelfReview = 'self';

    /** What the evidence records afterwards - past tense, on a decided row. */
    public function label(): string
    {
        return match ($this) {
            self::SystemAdministrator => 'System Administrator',
            self::OrganisationAdministrator => 'Organisation Administrator',
            self::DomainOwner => 'Owner of this business domain',
            self::SelfReview => 'Reviewed by the person who holds the access',
        };
    }

    /**
     * What standing the reader has RIGHT NOW, on a row still awaiting a
     * decision.
     *
     * The two are different sentences and the screen needs both. Reusing
     * label() under "You can review this as" produced "Reviewed by the person
     * who holds the access" as an answer to a present-tense question, which
     * reads as a record of something that already happened. Found by looking at
     * the screen, not by a failing test.
     */
    public function prospectiveLabel(): string
    {
        return match ($this) {
            self::SystemAdministrator => 'System Administrator',
            self::OrganisationAdministrator => 'Organisation Administrator',
            self::DomainOwner => 'Owner of this business domain',
            self::SelfReview => 'This is your own access, and nobody else is able to review it',
        };
    }
}
