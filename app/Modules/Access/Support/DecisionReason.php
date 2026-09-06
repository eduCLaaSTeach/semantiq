<?php

declare(strict_types=1);

namespace App\Modules\Access\Support;

/**
 * Why the engine decided what it decided.
 *
 * NOT A DEBUGGING AID. The simulator's "why", the privileged-denial log and
 * P1-07's review all read this, so it is part of the contract.
 *
 * These are machine values and they NEVER reach a screen. §11.4 requires a
 * business-language sentence derived from each one, and ReasonNarrator holds
 * the total mapping - "raw enum values on a user-facing surface" is exactly
 * what the CLAUDE.md §4 polish gate names.
 */
enum DecisionReason: string
{
    case AllowedByPath = 'allowed_by_path';

    // Global gates - checked first, satisfiable by no path and outvotable by none.
    case DeniedUnauthenticated = 'denied_unauthenticated';
    case DeniedInactiveUser = 'denied_inactive_user';
    case DeniedOrganisationMismatch = 'denied_organisation_mismatch';
    case DeniedDomainDisabled = 'denied_domain_disabled';

    // Which link was missing or refused in EVERY candidate path.
    case DeniedNoRole = 'denied_no_role';
    case DeniedNoEntitlement = 'denied_no_entitlement';
    case DeniedScope = 'denied_scope';
    case DeniedCeiling = 'denied_ceiling';

    // A malformed state. Never assumed away.
    case DeniedCeilingMissing = 'denied_ceiling_missing';
    case DeniedUnknownState = 'denied_unknown_state';

    // The engine could not decide. Deny, and raise an operational signal so a
    // broken engine does not look like an ordinary lack of entitlement.
    case DeniedEngineFailure = 'denied_engine_failure';

    public function isAllowed(): bool
    {
        return $this === self::AllowedByPath;
    }

    /**
     * Whether this denial is a security event rather than an ordinary business
     * refusal - D-71. Routine denials are NOT logged: volume buries what
     * matters, and P1-08 would inherit the noise.
     */
    public function isSecurityEvent(): bool
    {
        return $this === self::DeniedUnknownState
            || $this === self::DeniedCeilingMissing;
    }

    public function isEngineFailure(): bool
    {
        return $this === self::DeniedEngineFailure;
    }
}
