<?php

declare(strict_types=1);

namespace App\Modules\Platform\Identity\Microsoft;

use App\Modules\Platform\Identity\AuthenticationFailed;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Throwable;

/**
 * Validates an Entra ID token, one check at a time.
 *
 * Every check in the design's validation sequence is a separate, named step
 * here, because the point of this unit is to prove each of them fires. A
 * library that validated "the token" as a single opaque operation would give us
 * a green test suite and no evidence.
 *
 * Issuer, audience and tenant are three checks, not one. Issuer proves who
 * signed it, audience proves who it was for, and tid proves which directory the
 * user belongs to. A token can pass two of those and fail the third.
 */
final class IdTokenValidator
{
    /**
     * RS256 only. Never trust the token header's own algorithm claim: that is
     * the algorithm-confusion attack, where "none" or an HMAC over the public
     * key turns verification into a formality.
     */
    private const ALLOWED_ALGORITHM = 'RS256';

    private const LEEWAY_SECONDS = 120;

    public function __construct(
        private readonly EntraDiscovery $discovery,
        private readonly string $clientId,
        private readonly string $expectedTenant,
    ) {}

    /**
     * @return array<string, mixed> the validated claim set
     */
    public function validate(string $idToken, string $expectedNonce): array
    {
        $claims = $this->decodeAndVerifySignature($idToken);

        $this->assertIssuer($claims);
        $this->assertAudience($claims);
        $this->assertNonce($claims, $expectedNonce);
        $this->assertTenant($claims);
        $this->assertRequiredClaims($claims);

        return $claims;
    }

    /**
     * Steps 3, 4 and 7: well-formed, correctly signed, and inside its validity
     * window. php-jwt enforces exp/nbf/iat with the leeway set below.
     *
     * @return array<string, mixed>
     */
    private function decodeAndVerifySignature(string $idToken): array
    {
        JWT::$leeway = self::LEEWAY_SECONDS;

        foreach ([false, true] as $forceRefresh) {
            $keys = $this->signingKeys($forceRefresh);

            try {
                return (array) JWT::decode($idToken, $keys);
            } catch (Throwable $e) {
                // An unknown key id is the one failure worth retrying: Microsoft
                // rotates signing keys without notice. Everything else is a real
                // validation failure and must not trigger outbound traffic.
                if ($forceRefresh || ! $this->looksLikeUnknownKey($e)) {
                    throw AuthenticationFailed::protocol('token_signature_invalid');
                }
            }
        }

        throw AuthenticationFailed::protocol('token_signature_invalid');
    }

    /**
     * The tenant's signing keys: RSA signature keys only, parsed with RS256 as
     * the default algorithm.
     *
     * THE DEFAULT ALGORITHM IS NOT OPTIONAL, and omitting it is what broke
     * production. Microsoft's real JWKS omits the per-key "alg" field, and
     * php-jwt refuses to parse such a key without a default - so the entire key
     * set failed to parse and every sign-in was refused as
     * "token_signature_invalid" before a signature was ever checked. The test
     * JWKS included "alg", so CI never saw it: the fixture was more helpful
     * than reality, which is the only reason this reached production.
     *
     * Supplying the default does NOT weaken verification. Keys are filtered
     * first, so only RSA signature keys reach the parser, and the token
     * header's own algorithm is still never trusted to choose the verification
     * method - php-jwt requires the header algorithm to match the key's, which
     * is fixed at RS256 here.
     *
     * @return array<string, Key>
     */
    private function signingKeys(bool $forceRefresh): array
    {
        $usable = array_values(array_filter(
            $this->discovery->signingKeys($forceRefresh),
            fn (array $key): bool => $this->isRsaSignatureKey($key),
        ));

        if ($usable === []) {
            throw AuthenticationFailed::protocol('no_rs256_signing_key');
        }

        try {
            return JWK::parseKeySet(['keys' => $usable], self::ALLOWED_ALGORITHM);
        } catch (Throwable) {
            // A key set we cannot parse is not a bad signature, and reporting it
            // as one is what sent the first investigation of this defect after
            // the wrong cause.
            //
            // Honest note on coverage: php-jwt's parser is lenient. It accepts
            // an RSA key with a malformed modulus and only fails later, at
            // verification - so with the filter above already guaranteeing
            // kty/kid/n/e, this branch is not reachable by any input we could
            // write a test for. It is kept as a genuine guard against a future
            // parser that is stricter, not presented as a tested path. The
            // distinct reason that IS reachable, and is tested, is
            // no_rs256_signing_key.
            throw AuthenticationFailed::protocol('signing_key_format_invalid');
        }
    }

    /**
     * Only RSA keys intended for signatures are admitted.
     *
     * An encryption key, an EC key, or a key advertising a different algorithm
     * must never reach the parser carrying an RS256 default, because that
     * default would be describing it wrongly.
     *
     * @param  array<string, mixed>  $key
     */
    private function isRsaSignatureKey(array $key): bool
    {
        if (($key['kty'] ?? null) !== 'RSA') {
            return false;
        }

        // "use" is optional; when present it must say signature.
        if (isset($key['use']) && $key['use'] !== 'sig') {
            return false;
        }

        // "alg" is optional - Microsoft omits it - but when present it must be
        // the one algorithm we accept.
        if (isset($key['alg']) && $key['alg'] !== self::ALLOWED_ALGORITHM) {
            return false;
        }

        return isset($key['kid'], $key['n'], $key['e']);
    }

    private function looksLikeUnknownKey(Throwable $e): bool
    {
        return str_contains(strtolower($e->getMessage()), 'kid');
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function assertIssuer(array $claims): void
    {
        if (! isset($claims['iss']) || ! hash_equals($this->discovery->issuer(), (string) $claims['iss'])) {
            throw AuthenticationFailed::protocol('issuer_mismatch');
        }
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function assertAudience(array $claims): void
    {
        $audience = $claims['aud'] ?? null;
        $audiences = is_array($audience) ? $audience : [$audience];

        foreach ($audiences as $candidate) {
            if (is_string($candidate) && hash_equals($this->clientId, $candidate)) {
                return;
            }
        }

        throw AuthenticationFailed::protocol('audience_mismatch');
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function assertNonce(array $claims, string $expectedNonce): void
    {
        if (! isset($claims['nonce']) || ! hash_equals($expectedNonce, (string) $claims['nonce'])) {
            throw AuthenticationFailed::protocol('nonce_mismatch');
        }
    }

    /**
     * Refused as access-denied rather than as a protocol error: the token is
     * genuine, the person is simply not from a directory we trust.
     *
     * @param  array<string, mixed>  $claims
     */
    private function assertTenant(array $claims): void
    {
        if (! isset($claims['tid']) || ! hash_equals($this->expectedTenant, (string) $claims['tid'])) {
            throw AuthenticationFailed::tenant();
        }
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function assertRequiredClaims(array $claims): void
    {
        if (! isset($claims['oid']) || (string) $claims['oid'] === '') {
            throw AuthenticationFailed::protocol('missing_oid');
        }

        if ($this->emailFrom($claims) === null) {
            throw AuthenticationFailed::protocol('missing_email');
        }
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    public function emailFrom(array $claims): ?string
    {
        foreach (['email', 'preferred_username', 'upn'] as $claim) {
            $value = $claims[$claim] ?? null;

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }
}
