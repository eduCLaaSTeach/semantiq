<?php

declare(strict_types=1);

namespace App\Modules\Access\StepUp;

/**
 * The five actions that require step-up re-authentication - D-73.
 *
 * FIVE, AND ONLY FIVE. Each is an action that changes who holds privileged
 * authority, or that raises information above the level everybody else sees.
 * N-S1 breaks it by dropping one.
 */
enum StepUpAction: string
{
    case GrantSystemAdministrator = 'grant_system_administrator';
    case RevokeSystemAdministrator = 'revoke_system_administrator';
    case GrantOrganisationAdministrator = 'grant_organisation_administrator';
    case SelfGrant = 'self_grant';
    case GrantRestrictedSensitivity = 'grant_restricted_sensitivity';

    /** What the administrator is told they are about to confirm. */
    public function description(): string
    {
        return match ($this) {
            self::GrantSystemAdministrator => 'grant the System Administrator role',
            self::RevokeSystemAdministrator => 'remove the System Administrator role',
            self::GrantOrganisationAdministrator => 'grant the Organisation Administrator role',
            self::SelfGrant => 'grant access to yourself',
            self::GrantRestrictedSensitivity => 'allow access to restricted information',
        };
    }
}
