<?php

declare(strict_types=1);

namespace App\Modules\Platform\Identity\Microsoft;

use App\Modules\Platform\Identity\AuthenticationFailed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Entra OIDC metadata and signing keys.
 *
 * Cached for 24 hours so Microsoft's key rotation never needs a deployment. An
 * unknown key id triggers at most one refetch, rate limited to once every five
 * minutes: without that limit a hostile token carrying a random kid would be an
 * unauthenticated lever on our outbound requests.
 *
 * TWO RULES GOVERN EVERY WRITE TO THE CACHE HERE, and they are the same rule:
 *
 *   FETCH FIRST. VALIDATE. REPLACE ONLY ON SUCCESS.
 *
 * D-32. The forced refresh used to Cache::forget() the key set and then fetch.
 * The validator calls it for exactly one reason - a token arrived carrying a
 * signing key we do not recognise - which is what a key rotation looks like. So
 * the destructive step ran at the worst possible moment, and a network blip
 * during the fetch destroyed a key set that was still perfectly usable. The
 * person holding the new key failed either way, correctly; everyone else
 * signing in on a previously known key failed too, and the five-minute lock then
 * blocked another attempt. One unlucky request turned a blip into an outage.
 *
 * D-32 does NOT make validation more permissive. A failed refresh returns the
 * cache we already had, and the token that triggered it still fails on its
 * unknown key id, because the old cache is never consulted for a key it does
 * not contain.
 *
 * probe() applies the same rule to P1-02's administrator health check.
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

            /*
             * D-32. Fetch, then replace - never forget, then fetch.
             *
             * A failed fetch here leaves the cached set exactly as it was and
             * returns it. The caller's second decode attempt then fails on the
             * unknown key id, which is the correct outcome for the one token
             * that triggered this; every other sign-in keeps working on the keys
             * we still hold.
             */
            /*
             * Metadata may be unreachable too - the jwks_uri comes from it. A
             * refresh that cannot even resolve where the keys live has failed,
             * and a failed refresh must RETURN the preserved cache rather than
             * throw. Throwing here was the first version of this fix, and it
             * turned "we could not refresh" back into "sign-in is broken for
             * everyone", which is the defect wearing different clothes.
             */
            $metadata = $this->cachedMetadata() ?? $this->fetchMetadata();

            $fresh = $metadata === null ? null : $this->fetchKeysFrom($metadata);

            if ($fresh !== null) {
                Cache::put($key, $fresh, now()->addHours(self::CACHE_HOURS));

                return $fresh;
            }

            return Cache::get($key, []);
        }

        // The ordinary read-through path is unchanged: an unreachable directory
        // still throws, because there is nothing held to fall back to.
        return Cache::remember($key, now()->addHours(self::CACHE_HOURS), function (): array {
            $fresh = $this->fetchKeysFrom($this->metadata());

            if ($fresh === null) {
                throw AuthenticationFailed::protocol('jwks_unavailable');
            }

            return $fresh;
        });
    }

    /**
     * A live, read-only check that Microsoft is reachable RIGHT NOW - P1-02.
     *
     * The health screen's "Re-check now" cannot answer that from the cache: a
     * response cached at 09:00 says nothing about 16:00, and a button with that
     * label reporting a cached success through an outage would be worse than no
     * button. So this deliberately bypasses the read-through cache.
     *
     * It is bounded by a PROVIDER-WIDE lock, not a per-user rate limit: ten
     * administrators with ten browser tabs must not become ten requests to
     * Microsoft. When the lock is held the probe does not run at all, and the
     * caller reports the stored result of the last one.
     *
     * On success the cache is refreshed. On failure NOTHING is touched, so a
     * diagnostic button can never be the thing that breaks sign-in.
     *
     * It requests no token, starts no authorization, sends no client secret and
     * attempts no sign-in. Two GETs to public discovery endpoints, and nothing
     * else.
     *
     * @return array{ran: bool, reachable: bool, reason: string|null}
     */
    public function probe(): array
    {
        if (! Cache::add($this->cacheKey('probe-lock'), 1, self::REFETCH_LOCK_SECONDS)) {
            return ['ran' => false, 'reachable' => false, 'reason' => 'checked_recently'];
        }

        $metadata = $this->fetchMetadata();

        if ($metadata === null) {
            return ['ran' => true, 'reachable' => false, 'reason' => 'directory_unreachable'];
        }

        if (! isset($metadata['issuer'], $metadata['jwks_uri'])) {
            return ['ran' => true, 'reachable' => false, 'reason' => 'directory_incomplete'];
        }

        $keys = $this->fetchKeysFrom($metadata);

        if ($keys === null || $keys === []) {
            return ['ran' => true, 'reachable' => false, 'reason' => 'trust_anchor_unavailable'];
        }

        // Only now, and only because both succeeded.
        Cache::put($this->cacheKey('metadata'), $metadata, now()->addHours(self::CACHE_HOURS));
        Cache::put($this->cacheKey('jwks'), $keys, now()->addHours(self::CACHE_HOURS));

        return ['ran' => true, 'reachable' => true, 'reason' => null];
    }

    /** What is cached right now, without asking Microsoft anything. */
    public function cachedMetadata(): ?array
    {
        $cached = Cache::get($this->cacheKey('metadata'));

        return is_array($cached) ? $cached : null;
    }

    /** @return array<int, array<string, mixed>>|null */
    public function cachedSigningKeys(): ?array
    {
        $cached = Cache::get($this->cacheKey('jwks'));

        return is_array($cached) ? $cached : null;
    }

    /**
     * @return array<string, mixed>|null null on any failure, which the caller
     *                                   turns into "leave the cache alone"
     */
    private function fetchMetadata(): ?array
    {
        try {
            $response = Http::timeout(10)->get($this->metadataUrl());
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $body = $response->json();

        return is_array($body) ? $body : null;
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<int, array<string, mixed>>|null
     */
    private function fetchKeysFrom(array $metadata): ?array
    {
        $uri = $metadata['jwks_uri'] ?? null;

        if (! is_string($uri) || $uri === '') {
            return null;
        }

        try {
            $response = Http::timeout(10)->get($uri);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $keys = $response->json('keys', []);

        return is_array($keys) ? $keys : null;
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
