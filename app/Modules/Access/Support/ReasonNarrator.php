<?php

declare(strict_types=1);

namespace App\Modules\Access\Support;

/**
 * The one place a DecisionReason becomes a sentence an administrator reads.
 *
 * PRESENTATION MAPPING ONLY. It is not a second authorization decision: it
 * changes nothing, decides nothing and is never consulted by the engine. The
 * machine reason codes stay exactly as designed and remain available internally
 * for evidence, audit and testing.
 *
 * THE MAPPING IS TOTAL. Every case of DecisionReason has a sentence here, and
 * N-EN4 breaks it by adding a code with no mapping - which must fail rather
 * than fall through to the enum. A raw enum value on a user-facing surface is
 * precisely what the CLAUDE.md §4 gate exists to catch, and this makes it
 * structural instead of a review habit.
 */
final class ReasonNarrator
{
    public static function narrate(DecisionReason $reason): string
    {
        return match ($reason) {
            DecisionReason::AllowedByPath => 'Allowed.',
            DecisionReason::DeniedUnauthenticated => 'Not signed in.',
            DecisionReason::DeniedInactiveUser => 'This user account is inactive.',
            DecisionReason::DeniedOrganisationMismatch => 'This record belongs to a different organisation.',
            DecisionReason::DeniedDomainDisabled => 'This business domain is currently disabled.',
            DecisionReason::DeniedNoRole => 'This person holds no role that permits this action.',
            DecisionReason::DeniedNoEntitlement => 'This person has no entitlement to this business domain.',
            DecisionReason::DeniedScope => 'The assigned scope does not include this record.',
            DecisionReason::DeniedCeiling => 'The assigned sensitivity level does not permit this information.',
            DecisionReason::DeniedCeilingMissing => 'This grant has no sensitivity level assigned and cannot be used.',
            DecisionReason::DeniedUnknownState => 'This access configuration could not be interpreted and has been refused.',
            DecisionReason::DeniedEngineFailure => 'Access could not be determined and was refused.',
        };
    }

    /**
     * Every reason and its sentence, for the guard that asserts the mapping is
     * total and for the simulator's legend.
     *
     * @return array<string, string>
     */
    public static function all(): array
    {
        $narrated = [];

        foreach (DecisionReason::cases() as $reason) {
            $narrated[$reason->value] = self::narrate($reason);
        }

        return $narrated;
    }
}
