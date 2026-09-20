<?php

declare(strict_types=1);

namespace App\Modules\Identity\Support;

use App\Modules\Platform\Identity\IdentityProvider;
use App\Modules\Platform\Setup\Identity\IdentityConfigurationSource;

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

    /**
     * EVERY VALUE HERE COMES FROM THE RESOLVED SOURCE, NOT FROM config().
     *
     * This method used to read config('identity.microsoft.*') four times and
     * missingKeys() read four more through a variable, which no grep for the
     * call shape would have found. On a deployment running from the store that
     * meant the Entra screen described .env while sign-in used the store -
     * confidently wrong at exactly the moment somebody is debugging.
     */
    public static function build(IdentityProvider $provider, IdentityConfigurationSource $source): self
    {
        $identity = $source->resolve();

        $callback = route('auth.microsoft.callback');

        return new self(
            providerKey: $provider->key(),
            providerName: ApprovedProviders::nameFor($provider->key()) ?? 'Unknown provider',
            configured: $provider->isConfigured(),
            directoryMasked: IdentitySafeValue::masked($identity->tenantId),
            applicationMasked: IdentitySafeValue::masked($identity->clientId),
            // The ONE read of the client secret in this module. It becomes an
            // enum here and the string is never assigned to anything.
            secret: SecretPresence::of($identity->clientSecret),
            redirectUri: $identity->redirectUri,
            redirectUriMatchesDeployment: $identity->redirectUri !== ''
                && rtrim($identity->redirectUri, '/') === rtrim($callback, '/'),
            // Key NAMES, for the unconfigured empty state. Never a value - the
            // value is by definition absent, which is the whole finding. The
            // question is asked of the RESOLVED source, so a store-backed
            // deployment is never told that .env is missing something.
            missingKeys: $identity->missingKeys(),
        );
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
