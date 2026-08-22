<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Tenancy\OrganisationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Federated sign-in against Microsoft Entra ID.
 *
 * SemantIQ holds no local credential. Identity is delegated entirely to Entra
 * ID, and this controller runs the OpenID Connect authorization-code flow with
 * PKCE against it.
 *
 * Three properties this flow depends on, stated so they are not quietly removed
 * later:
 *
 *  - The authorization request is started by a POST, so it carries a CSRF token.
 *    A GET entry point would let a third party initiate sign-in on someone's
 *    behalf and land them in an account they did not choose.
 *  - `state` is single-use. It is compared with a timing-safe comparison and
 *    pulled out of the session before the code is exchanged, so a replayed
 *    callback finds nothing to match against.
 *  - The identity is read from a direct, server-to-server TLS call to Microsoft
 *    rather than from anything the browser supplied. The browser only ever
 *    carries an authorization code, which is worthless without the client
 *    secret and the PKCE verifier.
 */
class MicrosoftSignInController extends Controller
{
    /**
     * Session keys holding the single-use values that bind one sign-in attempt
     * together. All three are removed the moment the callback consumes them.
     */
    private const STATE_KEY = 'microsoft.state';

    private const NONCE_KEY = 'microsoft.nonce';

    private const VERIFIER_KEY = 'microsoft.code_verifier';

    /**
     * Every outbound call is bounded. An unreachable identity provider must fail
     * the sign-in in seconds rather than holding a request worker open.
     */
    private const CONNECT_TIMEOUT_SECONDS = 5;

    private const REQUEST_TIMEOUT_SECONDS = 15;

    /**
     * Begin the federated sign-in.
     *
     * Fails closed: with the tenant, client identifier, secret, or redirect
     * absent, the person is returned to the sign-in screen with an explanation
     * rather than being sent to a malformed Microsoft URL that would fail
     * confusingly at the other end.
     */
    public function redirect(Request $request): RedirectResponse
    {
        if (! $this->isConfigured()) {
            return back()->with('status', [
                'level' => 'warning',
                'title' => 'Microsoft sign-in is not configured yet',
                'body' => 'This environment has no Entra ID application registered against it, so '
                    .'sign-in cannot start. An administrator needs to add the tenant, client, and '
                    .'redirect settings to the server configuration.',
            ]);
        }

        $state = Str::random(40);
        $nonce = Str::random(40);
        $verifier = Str::random(96);

        $request->session()->put(self::STATE_KEY, $state);
        $request->session()->put(self::NONCE_KEY, $nonce);
        $request->session()->put(self::VERIFIER_KEY, $verifier);

        $query = http_build_query([
            'client_id' => config('services.microsoft.client_id'),
            'response_type' => 'code',
            'redirect_uri' => config('services.microsoft.redirect'),
            'response_mode' => 'query',
            /*
             * User.Read is what Microsoft Graph needs to answer /me. openid,
             * profile, and email are the OIDC basics. Nothing broader is asked
             * for: this application reads an identity and nothing else.
             */
            'scope' => 'openid profile email offline_access User.Read',
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => $this->codeChallengeFor($verifier),
            'code_challenge_method' => 'S256',
        ]);

        return redirect()->away($this->endpoint('authorize').'?'.$query);
    }

    /**
     * Complete the federated sign-in.
     *
     * Every failure path returns to the sign-in screen with a message a person
     * can act on. None of them reports what Microsoft said verbatim, because
     * those responses can carry directory detail that does not belong on an
     * unauthenticated page.
     */
    public function callback(Request $request): RedirectResponse
    {
        $expectedState = $request->session()->pull(self::STATE_KEY);
        $expectedNonce = $request->session()->pull(self::NONCE_KEY);
        $verifier = $request->session()->pull(self::VERIFIER_KEY);

        if (! $this->isConfigured()) {
            return $this->failed('Microsoft sign-in is not configured yet',
                'This environment has no Entra ID application registered against it.');
        }

        /*
         * Entra reports a refused or cancelled sign-in by redirecting here with
         * an error rather than a code. Treated as a plain outcome, not a fault.
         */
        if ($request->filled('error')) {
            Log::info('Microsoft sign-in was not completed at the identity provider.', [
                'error' => $request->string('error')->toString(),
            ]);

            return $this->failed('Sign-in was not completed',
                'Microsoft did not complete the sign-in. This usually means it was cancelled or '
                .'your organisation declined the request. You can try again.');
        }

        $state = $request->string('state')->toString();
        $code = $request->string('code')->toString();

        if ($state === '' || ! is_string($expectedState) || ! hash_equals($expectedState, $state)) {
            return $this->failed('Sign-in could not be verified',
                'The sign-in did not match the request that started it, so it was stopped. This '
                .'happens when a sign-in is left open too long or opened in a second tab. Start again.');
        }

        if ($code === '' || ! is_string($verifier)) {
            return $this->failed('Sign-in could not be completed',
                'Microsoft did not return an authorization code. Start the sign-in again.');
        }

        try {
            $tokens = $this->exchangeCode($code, $verifier);

            if ($tokens === null) {
                return $this->failed('Sign-in could not be completed',
                    'The authorization code could not be exchanged with Microsoft. Start the '
                    .'sign-in again, and tell an administrator if it keeps happening.');
            }

            if (! $this->nonceMatches($tokens['id_token'] ?? null, $expectedNonce)) {
                return $this->failed('Sign-in could not be verified',
                    'The identity Microsoft returned did not match the request that started it, so '
                    .'it was rejected.');
            }

            $profile = $this->fetchProfile($tokens['access_token'] ?? '');

            if ($profile === null) {
                return $this->failed('Your directory profile could not be read',
                    'Microsoft signed you in but did not return a profile, so there is no account '
                    .'to open. Tell an administrator if it keeps happening.');
            }

            $user = $this->upsertUser($profile);
        } catch (Throwable $e) {
            /*
             * The message is logged, the exception is not re-thrown to the page.
             * A stack trace on an unauthenticated screen is an information leak,
             * and the request carried an authorization code that must not reach
             * an error page.
             */
            Log::error('Microsoft sign-in failed.', ['reason' => $e->getMessage()]);

            return $this->failed('Sign-in could not be completed',
                'Something went wrong while completing the sign-in. Try again, and tell an '
                .'administrator if it keeps happening.');
        }

        Auth::login($user, remember: true);

        // A new session identifier on privilege change, against session fixation.
        $request->session()->regenerate();

        return redirect()->intended('/');
    }

    /**
     * Exchange the authorization code for tokens.
     *
     * @return array<string, mixed>|null the token payload, or null if Microsoft refused
     */
    private function exchangeCode(string $code, string $verifier): ?array
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
            ]);

        if ($response->failed()) {
            /*
             * The error code is logged; the body is not. A token endpoint error
             * body can echo request parameters, and this request carried the
             * client secret.
             */
            Log::warning('Microsoft token exchange was refused.', [
                'status' => $response->status(),
                'error' => (string) $response->json('error', 'unknown'),
            ]);

            return null;
        }

        return $response->json();
    }

    /**
     * Read the signed-in person's directory profile from Microsoft Graph.
     *
     * @return array<string, mixed>|null
     */
    private function fetchProfile(string $accessToken): ?array
    {
        if ($accessToken === '') {
            return null;
        }

        $response = Http::withToken($accessToken)
            ->connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
            ->timeout(self::REQUEST_TIMEOUT_SECONDS)
            ->get('https://graph.microsoft.com/v1.0/me');

        if ($response->failed()) {
            Log::warning('Microsoft Graph refused the profile request.', [
                'status' => $response->status(),
            ]);

            return null;
        }

        $profile = $response->json();

        return is_array($profile) && filled($profile['id'] ?? null) ? $profile : null;
    }

    /**
     * Create or refresh the local mirror of the directory account, and return it.
     *
     * Matched on the Entra object identifier first, because that is the only
     * stable handle: an address is re-assignable and a user principal name can be
     * renamed. The address is a fallback so an account seeded before its first
     * federated sign-in is adopted rather than duplicated.
     *
     * @param  array<string, mixed>  $profile
     */
    private function upsertUser(array $profile): User
    {
        $objectId = (string) $profile['id'];
        $email = $this->emailFrom($profile);
        $name = (string) ($profile['displayName'] ?? $email);

        /*
         * Identity resolution happens BEFORE tenancy is known, so it must run
         * outside the organisation scope. At this point in the flow nobody is
         * authenticated and no organisation context exists, so a scoped query
         * would match nothing and every returning person would look like a new
         * one, silently duplicating accounts.
         *
         * This is the sanctioned use of withoutScoping: the lookup is by Entra
         * object identifier, which is globally unique, not by anything a
         * caller in one organisation could use to fish for another's records.
         */
        return app(OrganisationContext::class)->withoutScoping(
            fn (): User => $this->resolveAndSave($objectId, $email, $name)
        );
    }

    /**
     * Find or create the local mirror, and apply the role rules.
     *
     * Split from upsertUser so the unscoped region is exactly as small as it
     * needs to be, and so what runs inside it is obvious to a reader.
     */
    private function resolveAndSave(string $objectId, string $email, string $name): User
    {
        $user = User::query()->where('entra_object_id', $objectId)->first()
            ?? User::query()->whereRaw('LOWER(email) = ?', [$email])->first()
            ?? new User;

        $user->entra_object_id = $objectId;
        $user->entra_tenant_id = (string) config('services.microsoft.tenant');
        $user->name = $name;
        $user->email = $email;
        $user->last_signed_in_at = now();

        /*
         * A brand new account is a Viewer, so an unrecognised person who
         * authenticates against the tenant gains the least the system can give
         * them. An existing account keeps whatever role it already holds.
         */
        if (! $user->exists) {
            $user->role = Role::default();
            $user->email_verified_at = now();
        }

        /*
         * The bootstrap list is a floor, never a ceiling: it promotes, and never
         * demotes. That way removing an address does not silently revoke an
         * administrator, and an account already promoted above the floor keeps
         * its higher role.
         */
        if ($this->isBootstrapAdmin($email) && ! $user->role?->atLeast(Role::SystemAdmin)) {
            $user->role = Role::SystemAdmin;
        }

        $user->save();

        return $user;
    }

    /**
     * The address to identify the account by.
     *
     * Entra returns `mail` only for accounts with a mailbox, so the user
     * principal name is the fallback. Both come from the directory, never from
     * the browser.
     *
     * @param  array<string, mixed>  $profile
     */
    private function emailFrom(array $profile): string
    {
        $email = $profile['mail'] ?? $profile['userPrincipalName'] ?? null;

        if (! is_string($email) || $email === '') {
            throw new \RuntimeException('The directory profile carried no address.');
        }

        return Str::lower($email);
    }

    /**
     * Whether this address is listed as a bootstrap system administrator.
     */
    private function isBootstrapAdmin(string $email): bool
    {
        $listed = array_map(
            static fn (string $address): string => Str::lower(trim($address)),
            (array) config('semantiq.bootstrap_admins', [])
        );

        return in_array(Str::lower($email), $listed, strict: true);
    }

    /**
     * Whether the ID token carries the nonce this sign-in was started with.
     *
     * The token is read without verifying its signature, which is sound only
     * because of where it came from: it was received in the body of a direct TLS
     * call to Microsoft's token endpoint, authenticated with the client secret.
     * OpenID Connect Core 3.1.3.7 permits skipping signature validation on that
     * exact path. A token that arrived any other way must never be trusted this
     * way.
     */
    private function nonceMatches(mixed $idToken, mixed $expectedNonce): bool
    {
        if (! is_string($idToken) || ! is_string($expectedNonce)) {
            return false;
        }

        $parts = explode('.', $idToken);

        if (count($parts) !== 3) {
            return false;
        }

        $payload = json_decode(
            (string) base64_decode(strtr($parts[1], '-_', '+/'), strict: false),
            associative: true
        );

        if (! is_array($payload) || ! is_string($payload['nonce'] ?? null)) {
            return false;
        }

        return hash_equals($expectedNonce, $payload['nonce']);
    }

    /**
     * The PKCE S256 challenge for a verifier, base64url encoded without padding.
     */
    private function codeChallengeFor(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, binary: true)), '+/', '-_'), '=');
    }

    /**
     * A tenant-scoped Entra ID endpoint.
     *
     * Scoping the URL to the configured tenant is what stops an account from
     * another directory signing in at all: Microsoft will not issue a token for
     * a different tenant against this endpoint.
     */
    private function endpoint(string $name): string
    {
        $tenant = rawurlencode((string) config('services.microsoft.tenant'));

        return "https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/{$name}";
    }

    /**
     * Whether this environment has enough configuration to start a sign-in.
     *
     * Presence only. No secret value is read, logged, compared, or reported.
     */
    private function isConfigured(): bool
    {
        return filled(config('services.microsoft.tenant'))
            && filled(config('services.microsoft.client_id'))
            && filled(config('services.microsoft.client_secret'))
            && filled(config('services.microsoft.redirect'));
    }

    /**
     * Return to the sign-in screen carrying an explanation.
     */
    private function failed(string $title, string $body): RedirectResponse
    {
        return redirect()->route('sign-in')->with('status', [
            'level' => 'danger',
            'title' => $title,
            'body' => $body,
        ]);
    }
}
