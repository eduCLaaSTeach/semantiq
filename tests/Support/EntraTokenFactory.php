<?php

declare(strict_types=1);

namespace Tests\Support;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;

/**
 * Mints Entra ID tokens with a locally generated key, so every validation
 * failure can be produced exactly and on purpose.
 *
 * This is what lets the negative suite be real without a live tenant: a token
 * with the wrong audience, the wrong issuer, the wrong tenant or a replayed
 * nonce is minted here and fed through the actual validator. No test is
 * weakened because production values are not in Git.
 */
final class EntraTokenFactory
{
    public const TENANT = '11111111-1111-1111-1111-111111111111';

    public const CLIENT_ID = '22222222-2222-2222-2222-222222222222';

    public const KID = 'test-key-1';

    private string $privateKey = '';

    private string $publicKey = '';

    public function __construct()
    {
        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        $private = '';
        openssl_pkey_export($resource, $private);

        $this->privateKey = $private;
        $this->publicKey = openssl_pkey_get_details($resource)['key'];
    }

    public function issuer(): string
    {
        return 'https://login.microsoftonline.com/'.self::TENANT.'/v2.0';
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public function token(array $overrides = []): string
    {
        $claims = array_merge([
            'iss' => $this->issuer(),
            'aud' => self::CLIENT_ID,
            'tid' => self::TENANT,
            'oid' => '33333333-3333-3333-3333-333333333333',
            'email' => 'person@example.test',
            'name' => 'Test Person',
            'nonce' => 'test-nonce',
            'iat' => time() - 10,
            'nbf' => time() - 10,
            'exp' => time() + 3600,
        ], $overrides);

        return JWT::encode($claims, $this->privateKey, 'RS256', self::KID);
    }

    /**
     * Stubs discovery, JWKS and the token endpoint so the real provider and the
     * real validator run unchanged - only the network is replaced.
     */
    public function fakeEndpoints(?string $idToken = null): void
    {
        $details = openssl_pkey_get_details(openssl_pkey_get_public($this->publicKey));

        Http::fake([
            '*/.well-known/openid-configuration' => Http::response([
                'issuer' => $this->issuer(),
                'authorization_endpoint' => 'https://login.microsoftonline.test/authorize',
                'token_endpoint' => 'https://login.microsoftonline.test/token',
                'jwks_uri' => 'https://login.microsoftonline.test/keys',
            ]),
            '*/keys' => Http::response([
                'keys' => [[
                    'kty' => 'RSA',
                    'kid' => self::KID,
                    'use' => 'sig',
                    'alg' => 'RS256',
                    'n' => rtrim(strtr(base64_encode($details['rsa']['n']), '+/', '-_'), '='),
                    'e' => rtrim(strtr(base64_encode($details['rsa']['e']), '+/', '-_'), '='),
                ]],
            ]),
            '*/token' => Http::response(['id_token' => $idToken ?? $this->token()]),
        ]);
    }

    public function configure(): void
    {
        config([
            'identity.microsoft.tenant_id' => self::TENANT,
            'identity.microsoft.client_id' => self::CLIENT_ID,
            'identity.microsoft.client_secret' => 'test-secret-value',
            'identity.microsoft.redirect_uri' => 'https://semantiq.test/auth/microsoft/callback',
        ]);
    }
}
