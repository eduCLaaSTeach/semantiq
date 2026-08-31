<?php

declare(strict_types=1);

namespace App\Modules\Platform\Identity\Microsoft;

use App\Modules\Platform\Identity\AuthenticationFailed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Entra OIDC metadata and signing keys.
 *
 * Cached for 24 hours so Microsoft's key rotation never needs a deployment. An
 * unknown key id triggers at most one refetch, rate limited to once every five
 * minutes: without that limit a hostile token carrying a random kid would be an
 * unauthenticated lever on our outbound requests.
 */
final class EntraDiscovery
{
    private const CACHE_HOURS = 24;

    private const REFETCH_LOCK_SECONDS = 300;

    public function __construct(private readonly string $tenant) {}

    /**
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        return Cache::remember(
            $this->cacheKey('metadata'),
            now()->addHours(self::CACHE_HOURS),
            function (): array {
                $response = Http::timeout(10)->get($this->metadataUrl());

                if (! $response->successful()) {
                    throw AuthenticationFailed::protocol('discovery_unavailable');
                }

                return $response->json();
            }
        );
    }

    public function authorizationEndpoint(): string
    {
        return $this->metadata()['authorization_endpoint']
            ?? throw AuthenticationFailed::protocol('discovery_incomplete');
    }

    public function tokenEndpoint(): string
    {
        return $this->metadata()['token_endpoint']
            ?? throw AuthenticationFailed::protocol('discovery_incomplete');
    }

    /**
     * The issuer this tenant's tokens must carry. Read from discovery rather
     * than assembled by string concatenation, so a tenant whose issuer format
     * differs is still validated against what Microsoft actually publishes.
     */
    public function issuer(): string
    {
        return $this->metadata()['issuer']
            ?? throw AuthenticationFailed::protocol('discovery_incomplete');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function signingKeys(bool $forceRefresh = false): array
    {
        $key = $this->cacheKey('jwks');

        if ($forceRefresh) {
            if (! Cache::add($this->cacheKey('jwks-refetch'), 1, self::REFETCH_LOCK_SECONDS)) {
                // A refetch happened moments ago. Serve what we have rather than
                // letting an attacker drive our outbound traffic.
                return Cache::get($key, []);
            }

            Cache::forget($key);
        }

        return Cache::remember($key, now()->addHours(self::CACHE_HOURS), function (): array {
            $uri = $this->metadata()['jwks_uri'] ?? throw AuthenticationFailed::protocol('discovery_incomplete');

            $response = Http::timeout(10)->get($uri);

            if (! $response->successful()) {
                throw AuthenticationFailed::protocol('jwks_unavailable');
            }

            return $response->json('keys', []);
        });
    }

    private function metadataUrl(): string
    {
        return "https://login.microsoftonline.com/{$this->tenant}/v2.0/.well-known/openid-configuration";
    }

    private function cacheKey(string $suffix): string
    {
        return "semantiq:entra:{$this->tenant}:{$suffix}";
    }
}
