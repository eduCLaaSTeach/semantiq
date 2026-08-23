<?php

declare(strict_types=1);

namespace App\Modules\Security\Enums;

/**
 * What kind of credential a reference points at. Feature ADM-012.
 *
 * The TYPE is recorded; the VALUE never is. Knowing that an integration depends
 * on a client secret expiring in eleven days is the whole point of ADM-012, and
 * it needs none of the secret itself.
 *
 * Each case carries the shape its reference identifier should take, which is
 * what `StoreSecretReferenceRequest` validates against. A client secret in Key
 * Vault is named by a vault URI and a secret name; a certificate is named by a
 * thumbprint. Validating the POINTER's shape is a second line of defence
 * against somebody pasting the credential itself into the field.
 */
enum SecretType: string
{
    case ClientSecret = 'client_secret';
    case Certificate = 'certificate';
    case ApiKey = 'api_key';
    case ConnectionPassword = 'connection_password';
    case SigningKey = 'signing_key';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::ClientSecret => 'Client secret',
            self::Certificate => 'Certificate',
            self::ApiKey => 'API key',
            self::ConnectionPassword => 'Connection password',
            self::SigningKey => 'Signing key',
            self::Other => 'Other',
        };
    }

    /**
     * What the administrator should type into the reference identifier.
     *
     * Shown as the field's help text, and it changes with the type, because
     * "reference identifier" on its own invites somebody to paste the secret.
     */
    public function identifierHint(): string
    {
        return match ($this) {
            self::ClientSecret => 'The name of the secret in its provider, for example the Key Vault secret name. Never the secret value.',
            self::Certificate => 'The certificate thumbprint or its name in the store. Never the private key or the PFX contents.',
            self::ApiKey => 'The name or identifier the provider gives the key. Never the key itself.',
            self::ConnectionPassword => 'The name of the credential entry the connection string reads from. Never the password or the full connection string.',
            self::SigningKey => 'The key identifier or the name in the key store. Never the key material.',
            self::Other => 'A name or path that lets somebody find this credential in its provider. Never the credential itself.',
        };
    }

    /**
     * How urgently an expired one of these matters, for the overview roll-up.
     *
     * A lapsed certificate or client secret stops an integration dead, so it is
     * Critical. An expired "other" is a Warning because nothing here knows what
     * depends on it.
     */
    public function severityWhenExpired(): SecurityStatus
    {
        return match ($this) {
            self::Other => SecurityStatus::Warning,
            default => SecurityStatus::Critical,
        };
    }
}
