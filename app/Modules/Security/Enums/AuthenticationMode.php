<?php

declare(strict_types=1);

namespace App\Modules\Security\Enums;

/**
 * How people are allowed to sign in. Feature ADM-009, "Authentication Mode".
 *
 * ADM-009's rule is that the local bootstrap account "should become break-glass
 * after Entra is operational". These three modes are that progression, in
 * order, and the mode is what the sign-in screen and both controllers read.
 *
 * `LocalOnly` exists because a customer's Entra application may not be
 * registered yet on the day SemantIQ is installed. It is the WEAKEST mode and
 * the screen says so.
 */
enum AuthenticationMode: string
{
    /** Everyone signs in through Microsoft Entra. No credential form at all. */
    case FederatedOnly = 'federated_only';

    /**
     * Entra for everyone, plus a credential form for accounts marked as local
     * administrators. The intended steady state: break-glass access survives an
     * Entra outage without offering a password form to the whole company.
     */
    case FederatedWithLocalAdmin = 'federated_with_local_admin';

    /** Credential form only. Entra is not configured or not yet working. */
    case LocalOnly = 'local_only';

    public function label(): string
    {
        return match ($this) {
            self::FederatedOnly => 'Microsoft Entra only',
            self::FederatedWithLocalAdmin => 'Microsoft Entra, with local administrator sign-in',
            self::LocalOnly => 'Local sign-in only',
        };
    }

    public function help(): string
    {
        return match ($this) {
            self::FederatedOnly => 'The strongest option. Nobody can sign in with a password held here, so an Entra outage locks everybody out including administrators.',
            self::FederatedWithLocalAdmin => 'The intended steady state. Business users go through Entra; accounts marked as local administrators keep a credential form as break-glass access.',
            self::LocalOnly => 'The weakest option, for use before Entra is registered. Every account signs in with a password held by this application.',
        };
    }

    /** Whether the credential form is offered at all. */
    public function allowsCredentialForm(): bool
    {
        return $this !== self::FederatedOnly;
    }

    /** Whether the Microsoft button is offered at all. */
    public function allowsFederatedSignIn(): bool
    {
        return $this !== self::LocalOnly;
    }

    /**
     * Whether the credential form is restricted to local administrators.
     *
     * Distinct from `allowsCredentialForm()`: in `FederatedWithLocalAdmin` the
     * form is rendered but only a local administrator may get through it, and
     * the refusal for anybody else must be indistinguishable from a wrong
     * password. See SEC-DEC-027.
     */
    public function restrictsCredentialFormToLocalAdmins(): bool
    {
        return $this === self::FederatedWithLocalAdmin;
    }
}
