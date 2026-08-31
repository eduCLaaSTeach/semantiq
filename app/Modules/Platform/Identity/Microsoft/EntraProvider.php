<?php

declare(strict_types=1);

namespace App\Modules\Platform\Identity\Microsoft;

use App\Modules\Platform\Identity\AuthenticationFailed;
use App\Modules\Platform\Identity\IdentityProvider;
use App\Modules\Platform\Identity\VerifiedIdentity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Microsoft Entra ID, authorization code flow with PKCE.
 *
 * The client is confidential - it holds a secret - and still uses PKCE. That is
 * deliberate defence in depth: PKCE defeats code interception even if the
 * redirect is somehow observed, and it costs nothing.
 *
 * state, nonce and the PKCE verifier are read out of the session and deleted
 * before any validation runs. A replayed callback therefore finds nothing to
 * compare against and fails, rather than being compared a second time against
 * values that are still sitting there.
 */
final class EntraProvider implements IdentityProvider
{
    public const SESSION_STATE = 'auth.microsoft.state';

    public const SESSION_NONCE = 'auth.microsoft.nonce';

    public const SESSION_VERIFIER = 'auth.microsoft.verifier';

    private const SCOPES = 'openid profile email';

    public function __construct(
        private readonly EntraDiscovery $discovery,
        private readonly IdTokenValidator $validator,
        private readonly string $tenant,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $redirectUri,
    ) {}

    public function key(): string
    {
        return 'microsoft';
    }

    public function isConfigured(): bool
    {
        return $this->tenant !== ''
            && $this->clientId !== ''
            && $this->clientSecret !== ''
            && $this->redirectUri !== '';
    }

    public function beginAuthorization(): RedirectResponse
    {
        if (! $this->isConfigured()) {
            throw AuthenticationFailed::protocol('provider_not_configured');
        }

        $state = $this->randomValue();
        $nonce = $this->randomValue();
        $verifier = $this->randomValue();

        session([
            self::SESSION_STATE => $state,
            self::SESSION_NONCE => $nonce,
            self::SESSION_VERIFIER => $verifier,
        ]);

        $query = http_build_query([
            'client_id' => $this->clientId,
            'response_type' => 'code',
            'redirect_uri' => $this->redirectUri,
            'response_mode' => 'query',
            'scope' => self::SCOPES,
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => $this->challengeFor($verifier),
            'code_challenge_method' => 'S256',
        ]);

        return new RedirectResponse($this->discovery->authorizationEndpoint().'?'.$query);
    }

    public function completeAuthorization(Request $request): VerifiedIdentity
    {
        // Consume first. Everything below compares against these local copies,
        // so a second callback carrying the same code has nothing to match.
        $expectedState = $this->pull(self::SESSION_STATE);
        $expectedNonce = $this->pull(self::SESSION_NONCE);
        $verifier = $this->pull(self::SESSION_VERIFIER);

        $state = (string) $request->query('state', '');

        if ($expectedState === '' || ! hash_equals($expectedState, $state)) {
            throw AuthenticationFailed::protocol('state_mismatch');
        }

        if ($request->query('error') !== null) {
            throw AuthenticationFailed::protocol('provider_returned_error');
        }

        $code = (string) $request->query('code', '');

        if ($code === '') {
            throw AuthenticationFailed::protocol('missing_code');
        }

        $claims = $this->validator->validate(
            $this->exchangeCodeForIdToken($code, $verifier),
            $expectedNonce,
        );

        return new VerifiedIdentity(
            provider: $this->key(),
            subject: (string) $claims['oid'],
            tenant: (string) $claims['tid'],
            email: (string) $this->validator->emailFrom($claims),
            displayName: isset($claims['name']) && is_string($claims['name']) && $claims['name'] !== ''
                ? $claims['name']
                : (string) $this->validator->emailFrom($claims),
        );
    }

    /**
     * Back channel, server to server. Only the ID token is used; no access token
     * is requested for any API, stored, or returned to the caller.
     */
    private function exchangeCodeForIdToken(string $code, string $verifier): string
    {
        $response = Http::asForm()->timeout(15)->post($this->discovery->tokenEndpoint(), [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->redirectUri,
            'code_verifier' => $verifier,
            'scope' => self::SCOPES,
        ]);

        if (! $response->successful()) {
            throw AuthenticationFailed::protocol('token_exchange_failed');
        }

        $idToken = $response->json('id_token');

        if (! is_string($idToken) || $idToken === '') {
            throw AuthenticationFailed::protocol('missing_id_token');
        }

        return $idToken;
    }

    private function pull(string $key): string
    {
        $value = session()->pull($key, '');

        return is_string($value) ? $value : '';
    }

    private function randomValue(): string
    {
        return Str::random(64);
    }

    private function challengeFor(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }
}
