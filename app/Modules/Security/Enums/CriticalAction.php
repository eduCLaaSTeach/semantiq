<?php

declare(strict_types=1);

namespace App\Modules\Security\Enums;

/**
 * An action that may require the actor to prove who they are again. ADM-010.
 *
 * ADM-010 names six. FOUR ARE DECLARED HERE - the four whose actions exist in
 * the application once this gate ships. The other two guard things nothing can
 * yet do: sovereignty exception approval is gate 4, and integration credential
 * change is gate 5.
 *
 * Declaring all six now would put two switches on the Session Policy screen
 * that an administrator could turn on and that would protect nothing, which is
 * indistinguishable from a security control that has silently failed. Each gate
 * adds its own, exactly as the permission registry does.
 */
enum CriticalAction: string
{
    /** Changing somebody's platform tier. */
    case TierChange = 'tier_change';

    /**
     * Making somebody a System Administrator, or taking it away. Separate from
     * `TierChange` because it is the one tier change that hands over the keys.
     */
    case SystemAdministratorChange = 'system_administrator_change';

    /** Changing any value on the security policy screens. */
    case SecurityPolicyChange = 'security_policy_change';

    /**
     * Creating, changing or retiring a secret reference. ADM-012.
     *
     * The row holds no secret, but it holds the map to every secret this
     * system depends on, and changing where a pointer points is how an
     * integration gets quietly redirected.
     */
    case SecretReferenceChange = 'secret_reference_change';

    public function label(): string
    {
        return match ($this) {
            self::TierChange => 'Changing a platform role',
            self::SystemAdministratorChange => 'Assigning or removing System Administrator',
            self::SecurityPolicyChange => 'Changing a security policy',
            self::SecretReferenceChange => 'Changing a secret reference',
        };
    }

    public function help(): string
    {
        return match ($this) {
            self::TierChange => 'Raising or lowering what somebody may do in SemantIQ.',
            self::SystemAdministratorChange => 'The change that hands over full platform control.',
            self::SecurityPolicyChange => 'Any change on Authentication Policy, Session Policy or API Security.',
            self::SecretReferenceChange => 'Adding, editing or retiring a pointer to a credential held elsewhere.',
        };
    }

    /**
     * The gate 3 features this covers, for the Session Policy screen's note
     * about what is deliberately not listed yet.
     *
     * @return list<string>
     */
    public static function deferred(): array
    {
        return [
            'Approving a sovereignty exception (ADM-016, gate 4)',
            'Changing an integration credential (ADM-018, gate 5)',
        ];
    }
}
