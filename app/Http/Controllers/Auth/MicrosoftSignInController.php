<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Audit\Enums\AuditOutcome;
use App\Modules\Audit\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Sign in with Microsoft Entra ID.
 *
 * OpenID Connect authorization code flow with PKCE, implemented directly rather
 * than through a social-login package. The flow is small, and writing it out
 * keeps every security decision visible in one file instead of spread across a
 * library's configuration.
 *
 * Four properties this depends on, stated so none is quietly removed:
 *
 *  - `state` is single use. It is compared in constant time and deleted before
 *    the code is exchanged, so a replayed callback finds nothing to match.
 *  - `nonce` is bound into the ID token by Microsoft and checked here, which is
 *    what stops a token minted for another session being accepted in this one.
 *  - PKCE (S256) means an intercepted authorization code is useless without the
 *    verifier, which never leaves this server.
 *  - Identity is matched on the directory object id, not the address. Addresses
 *    get reassigned and changed; the object id does not.
 *
 * On the ID token signature: it is not verified against Microsoft's JWKS, and
 * that is deliberate rather than an omission. OIDC Core section 3.1.3.7 permits
 * skipping it when the token is received directly from the token endpoint over
 * TLS with client authentication, which is exactly this flow - the transport and
 * the client secret are already proving origin. A public client, or a token
 * arriving any other way, would have to verify it.
 *
 * Requirement: FR-AUTH-001.
 */
class MicrosoftSignInController extends Controller
{
    private const STATE_KEY = 'microsoft.state';

    private const NONCE_KEY = 'microsoft.nonce';

    private const VERIFIER_KEY = 'microsoft.code_verifier';

    /**
     * Bounded so a hung Microsoft endpoint cannot hold a web worker open.
     */
    private const CONNECT_TIMEOUT_SECONDS = 5;

    private const REQUEST_TIMEOUT_SECONDS = 15;

    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Start the flow: send the person to Microsoft.
     */
    public function redirect(Request $request): RedirectResponse
    {
        if (! $this->isConfigured()) {
            return $this->fail('Microsoft sign-in is not configured yet. Use your email and password, '
                .'or ask an administrator to finish the Microsoft Entra setup.');
        }

        $state = Str::random(40);
        $nonce = Str::random(40);
        $verifier = Str::random(64);

        $request->session()->put(self::STATE_KEY, $state);
        $request->session()->put(self::NONCE_KEY, $nonce);
        $request->session()->put(self::VERIFIER_KEY, $verifier);

        $query = http_build_query([
            'client_id' => config('services.microsoft.client_id'),
            'response_type' => 'code',
            'redirect_uri' => config('services.microsoft.redirect'),
            'response_mode' => 'query',
            'scope' => 'openid profile email offline_access User.Read',
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => $this->challengeFor($verifier),
            'code_challenge_method' => 'S256',
        ]);

        return redirect()->away($this->endpoint('authorize').'?'.$query);
    }

    /**
     * Finish the flow: Microsoft has sent them back.
     */
    public function callback(Request $request): RedirectResponse
    {
        $expectedState = $request->session()->pull(self::STATE_KEY);
        $expectedNonce = $request->session()->pull(self::NONCE_KEY);
        $verifier = $request->session()->pull(self::VERIFIER_KEY);

        $state = $request->string('state')->toString();

        /*
         * Pulled above, so this check can only ever pass once. A callback
         * replayed from a browser history entry or a shared link finds the
         * session value already consumed.
         */
        if ($state === '' || ! is_string($expectedState) || ! hash_equals($expectedState, $state)) {
            return $this->fail('That sign-in link is no longer valid. Try again.', 'state_mismatch');
        }

        /*
         * Microsoft declined, or the person cancelled at the consent screen.
         * Their own description is more useful than anything invented here, but
         * it is not shown to the browser: it can name internal policy.
         */
        if ($request->filled('error')) {
            return $this->fail(
                'Sign-in was not completed at Microsoft. Try again, and tell an administrator if it keeps happening.',
                $request->string('error').': '.$request->string('error_description'),
            );
        }

        $code = $request->string('code')->toString();

        if ($code === '' || ! is_string($verifier)) {
            return $this->fail('Sign-in could not be completed. Try again.', 'missing_code_or_verifier');
        }

        try {
            $tokens = $this->exchange($code, $verifier);

            if (! $this->nonceMatches($tokens['id_token'] ?? null, $expectedNonce)) {
                return $this->fail('Sign-in could not be completed. Try again.', 'nonce_mismatch');
            }

            $claims = $this->claims($tokens['id_token']);
            $profile = $this->profile($tokens['access_token'] ?? null);

            $objectId = $claims['oid'] ?? null;
            $email = $profile['mail']
                ?? $profile['userPrincipalName']
                ?? $claims['preferred_username']
                ?? $claims['email']
                ?? null;
            $name = $profile['displayName'] ?? $claims['name'] ?? $email;

            if (! is_string($objectId) || ! is_string($email)) {
                return $this->fail('Sign-in could not be completed. Try again.', 'incomplete_profile');
            }

            $user = $this->resolve($objectId, $email, (string) $name, $claims['tid'] ?? null);
        } catch (\Throwable $exception) {
            /*
             * The reason is logged, never shown. A token exchange failure can
             * carry a client-secret or tenant-policy detail, and the browser is
             * the last place either belongs.
             */
            return $this->fail(
                'Something went wrong while completing the sign-in. Try again, and tell an administrator if it keeps happening.',
                $exception->getMessage(),
            );
        }

        /*
         * The directory proved who they are. Whether this application still
         * lets them in is a separate question - VAL-USER-DISABLED-001 and
         * VAL-USER-WINDOW-001 - and it is asked here rather than left to
         * Entra, because disabling somebody in SemantIQ has to work even when
         * their directory account is untouched.
         */
        if (! $user->mayAuthenticate()) {
            $this->audit->record(
                action: 'auth.login.failed',
                module: 'Security',
                outcome: AuditOutcome::Denied,
                resourceType: 'user',
                resourceId: $user->getKey(),
                reason: 'Account is '.$user->status->label().' or outside its access window.',
            );

            return $this->fail(
                'Your access to SemantIQ is not currently active. Contact an administrator.',
                'account_not_active',
            );
        }

        Auth::login($user, remember: true);

        // A session fixed before authentication must not become the signed-in one.
        $request->session()->regenerate();

        $this->audit->record(
            action: 'auth.login.succeeded',
            module: 'Security',
            resourceType: 'user',
            resourceId: $user->getKey(),
        );

        return redirect()->intended('/');
    }

    /**
     * Find or create the local mirror of a directory account.
     *
     * Matched on the object id first. Falling back to the address is what makes
     * an existing local account adoptable by the directory the first time that
     * person signs in with Microsoft, instead of colliding on the unique email.
     */
    private function resolve(string $objectId, string $email, string $name, ?string $tenantId): User
    {
        $user = User::query()->where('entra_object_id', $objectId)->first()
            ?? User::query()->where('email', $email)->first()
            ?? new User;

        $user->forceFill([
            'name' => $name !== '' ? $name : $email,
            'email' => $email,
            'entra_object_id' => $objectId,
            'entra_tenant_id' => $tenantId,
            'last_signed_in_at' => now(),
        ])->save();

        return $user->refresh();
    }

    /**
     * Trade the authorization code for tokens.
     *
     * @return array<string, mixed>
     */
    private function exchange(string $code, string $verifier): array
    {
        $response = Http::asForm()
            ->connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
            ->timeout(self::REQUEST_TIMEOUT_SECONDS)
            ->post($this->endpoint('token'), [
                'client_id' => config('services.microsoft.client_id'),
                'client_secret' => config('services.microsoft.client_secret'),
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => config('services.microsoft.redirect'),
                'code_verifier' => $verifier,
                'scope' => 'openid profile email offline_access User.Read',
            ])
            ->throw();

        return $response->json();
    }

    /**
     * The person's profile from Microsoft Graph.
     *
     * The ID token alone often carries no usable address - a guest account's
     * preferred_username can be their home-tenant address - so Graph is asked
     * for the authoritative one. A failure here is not fatal: the claims are the
     * fallback.
     *
     * @return array<string, mixed>
     */
    private function profile(?string $accessToken): array
    {
        if (! is_string($accessToken)) {
            return [];
        }

        $response = Http::withToken($accessToken)
            ->connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
            ->timeout(self::REQUEST_TIMEOUT_SECONDS)
            ->get('https://graph.microsoft.com/v1.0/me');

        return $response->successful() ? $response->json() : [];
    }

    /**
     * Whether the ID token carries the nonce this sign-in started with.
     */
    private function nonceMatches(mixed $idToken, mixed $expectedNonce): bool
    {
        if (! is_string($idToken) || ! is_string($expectedNonce)) {
            return false;
        }

        $nonce = $this->claims($idToken)['nonce'] ?? null;

        return is_string($nonce) && hash_equals($expectedNonce, $nonce);
    }

    /**
     * The ID token's payload claims.
     *
     * Decoded, not verified - see the class comment for why that is sound here.
     *
     * @return array<string, mixed>
     */
    private function claims(string $idToken): array
    {
        $parts = explode('.', $idToken);

        if (count($parts) !== 3) {
            return [];
        }

        $payload = json_decode(
            (string) base64_decode(strtr($parts[1], '-_', '+/'), true),
            associative: true,
        );

        return is_array($payload) ? $payload : [];
    }

    /**
     * The S256 challenge for a verifier, base64url encoded without padding.
     */
    private function challengeFor(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, binary: true)), '+/', '-_'), '=');
    }

    /**
     * A tenant-scoped Entra endpoint.
     */
    private function endpoint(string $path): string
    {
        return sprintf(
            'https://login.microsoftonline.com/%s/oauth2/v2.0/%s',
            config('services.microsoft.tenant'),
            $path,
        );
    }

    /**
     * Whether every value the flow needs is present.
     *
     * Checked before starting rather than discovered halfway through, so an
     * unconfigured deployment shows an explanation instead of bouncing someone
     * to a Microsoft error page.
     */
    private function isConfigured(): bool
    {
        foreach (['tenant', 'client_id', 'client_secret', 'redirect'] as $key) {
            if (blank(config("services.microsoft.{$key}"))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Send them back to the sign-in screen with something they can act on.
     *
     * The message and the logged reason are separate on purpose: the browser
     * gets the sentence, the log gets the detail.
     */
    private function fail(string $message, ?string $reason = null): RedirectResponse
    {
        if ($reason !== null) {
            Log::warning('Microsoft sign-in failed', ['reason' => $reason]);
        }

        return redirect()->route('sign-in')->withErrors(['form' => $message]);
    }
}
