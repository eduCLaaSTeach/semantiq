<?php

declare(strict_types=1);

namespace App\Modules\Security\Enums;

/**
 * Where the credential actually lives. Feature ADM-012.
 *
 * Release 1 answers "where is it managed", not "fetch it for me". No provider
 * here is called by SemantIQ: there is no client, no SDK and no network call.
 * That is deliberate - an application that can resolve a reference is an
 * application that holds credentials at runtime, and resolving belongs with the
 * integration work in gate 5, behind an approved architecture decision.
 *
 * Open item U1 in the release plan asks which provider backs production. The
 * abstraction ships either way; the concrete answer does not gate this screen.
 */
enum SecretProvider: string
{
    /** The server's own `.env`, read by the framework and never by a screen. */
    case ServerEnvironment = 'server_environment';

    case AzureKeyVault = 'azure_key_vault';

    /** cPanel's own environment or credential store. */
    case Cpanel = 'cpanel';

    /** Held by the customer outside any system SemantIQ can see. */
    case CustomerManaged = 'customer_managed';

    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::ServerEnvironment => 'Server environment file',
            self::AzureKeyVault => 'Azure Key Vault',
            self::Cpanel => 'cPanel credential store',
            self::CustomerManaged => 'Managed by the customer',
            self::Other => 'Other',
        };
    }

    public function help(): string
    {
        return match ($this) {
            self::ServerEnvironment => 'The application reads it from the server .env at boot. Rotating it needs server access.',
            self::AzureKeyVault => 'Held in a vault. Record the vault name and the secret name, never the value.',
            self::Cpanel => 'Held in the hosting control panel. Rotating it needs cPanel access.',
            self::CustomerManaged => 'The customer holds it and SemantIQ has no visibility of it at all.',
            self::Other => 'Somewhere else. Say where in the purpose field so the next person can find it.',
        };
    }

    /**
     * Whether SemantIQ can, even in principle, check this credential's state.
     *
     * False for all of them in Release 1. The overview reports the expiry dates
     * an administrator TYPED IN, and says so, rather than implying it verified
     * anything with the provider. See SecurityStatus::NotVerified.
     */
    public function supportsAutomaticVerification(): bool
    {
        return false;
    }
}
