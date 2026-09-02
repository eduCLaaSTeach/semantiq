<?php

declare(strict_types=1);

namespace App\Modules\Identity\Support;

use App\Modules\Platform\Identity\IdentityProvider;

/**
 * The safe read model: everything the Identity screens are allowed to know.
 *
 * Screens receive THIS, serialised - never configuration. The client secret has
 * no representation here beyond an enum, so there is no property a view could
 * accidentally print, and the full directory and application identifiers never
 * enter the page payload at all: only their masked forms do. The unmasked
 * values are obtainable only through the explicit reveal endpoint, which
 * re-authorises and is CSRF-protected.
 *
 * That last part is what makes the mask real rather than cosmetic. If the full
 * identifier shipped in the props and the mask were CSS, it would already be in
 * the page source of every screenshot-adjacent artefact.
 */
final readonly class IdentityConfigurationReport
{
    /**
     * @param  list<string>  $missingKeys
     */
    public function __construct(
        public string $providerKey,
        public string $providerName,
        public bool $configured,
        public string $directoryMasked,
        public string $applicationMasked,
        public SecretPresence $secret,
        public string $redirectUri,
        public bool $redirectUriMatchesDeployment,
        public array $missingKeys,
    ) {}

    public static function build(IdentityProvider $provider): self
    {
        $tenant = (string) config('identity.microsoft.tenant_id');
        $client = (string) config('identity.microsoft.client_id');
        $redirect = (string) config('identity.microsoft.redirect_uri');

        $callback = route('auth.microsoft.callback');

        return new self(
            providerKey: $provider->key(),
            providerName: ApprovedProviders::nameFor($provider->key()) ?? 'Unknown provider',
            configured: $provider->isConfigured(),
            directoryMasked: IdentitySafeValue::masked($tenant),
            applicationMasked: IdentitySafeValue::masked($client),
            // The ONE read of the client secret in this module. It becomes an
            // enum here and the string is never assigned to anything.
            secret: SecretPresence::of(config('identity.microsoft.client_secret')),
            redirectUri: $redirect,
            redirectUriMatchesDeployment: $redirect !== '' && rtrim($redirect, '/') === rtrim($callback, '/'),
            missingKeys: self::missingKeys(),
        );
    }

    /**
     * Key NAMES, for the unconfigured empty state. Never a value - the value is
     * by definition absent, which is the whole finding.
     *
     * @return list<string>
     */
    private static function missingKeys(): array
    {
        $missing = [];

        foreach ([
            'MICROSOFT_TENANT_ID' => 'identity.microsoft.tenant_id',
            'MICROSOFT_CLIENT_ID' => 'identity.microsoft.client_id',
            'MICROSOFT_CLIENT_SECRET' => 'identity.microsoft.client_secret',
            'MICROSOFT_REDIRECT_URI' => 'identity.microsoft.redirect_uri',
        ] as $name => $key) {
            if ((string) config($key) === '') {
                $missing[] = $name;
            }
        }

        return $missing;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'providerKey' => $this->providerKey,
            'providerName' => $this->providerName,
            'configured' => $this->configured,
            'directoryMasked' => $this->directoryMasked,
            'applicationMasked' => $this->applicationMasked,
            'secret' => $this->secret->inWords(),
            'redirectUri' => $this->redirectUri,
            'redirectUriMatchesDeployment' => $this->redirectUriMatchesDeployment,
            'missingKeys' => $this->missingKeys,
        ];
    }
}
