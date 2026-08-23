<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Audit\Enums\AuditOutcome;
use App\Modules\Audit\Support\AuditLogger;
use App\Modules\Audit\Support\Redaction;
use App\Modules\Identity\Support\OrganisationContext;
use App\Modules\Platform\Enums\LifecycleStatus;
use App\Modules\Security\Exceptions\SelfProvisioningRefused;
use App\Modules\Security\Support\AuthenticationGuard;
use App\Modules\Security\Support\Reauthentication;
use App\Modules\Security\Support\SecurityPolicies;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

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

    /**
     * Where to come back to after a re-authentication round trip, and the flag
     * that says this is one. ADM-010.
     */
    private const REAUTH_KEY = 'microsoft.reauthenticating';

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly AuthenticationGuard $guard,
    ) {}

    /**
     * Start the flow: send the person to Microsoft.
     *
     * `$prompt` is `login` when this is a re-authentication for a critical
     * action (ADM-010). It asks Entra to challenge the person again rather than
     * silently reissuing from an existing directory session, which is the whole
     * point of asking. Nothing extra is stored to achieve it: no additional
     * token, no cached credential - the round trip IS the proof.
     */
    public function redirect(Request $request): RedirectResponse
    {
        if (! $this->guard->offersFederatedSignIn()) {
            return $this->fail(
                'Microsoft sign-in is turned off by the current authentication policy. Use your email and password.',
                'federated_sign_in_disabled',
            );
        }

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

        /*
         * A re-authentication is a normal sign-in with one extra parameter.
         * `prompt=login` is standard OpenID Connect: it tells the provider to
         * challenge the person rather than reuse an existing directory session.
         * Without it, an attacker sitting at an unlocked machine confirms a
         * critical action by being bounced straight back.
         */
        $reauthenticating = $request->boolean('reauthenticate') && Auth::check();

        $request->session()->put(self::REAUTH_KEY, $reauthenticating);

        $parameters = [
            'client_id' => config('services.microsoft.client_id'),
            'response_type' => 'code',
            'redirect_uri' => config('services.microsoft.redirect'),
            'response_mode' => 'query',
            'scope' => 'openid profile email offline_access User.Read',
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => $this->challengeFor($verifier),
            'code_challenge_method' => 'S256',
        ];

        if ($reauthenticating) {
            $parameters['prompt'] = 'login';
            /* Ask for this person specifically, so the challenge cannot be
             * satisfied by signing in as somebody else entirely. */
            $parameters['login_hint'] = (string) Auth::user()?->email;
        }

        return redirect()->away($this->endpoint('authorize').'?'.http_build_query($parameters));
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

            /*
             * ADM-009's allow-lists, checked BEFORE the account is touched.
             * Doing it after would create or update a local row for somebody
             * the policy is about to refuse, which is how a refused person ends
             * up with an account.
             */
            $tenantId = is_string($claims['tid'] ?? null) ? $claims['tid'] : null;
            $refusal = $this->guard->federatedRefusal($tenantId, $email);

            if ($refusal !== null) {
                $this->audit->record(
                    action: 'auth.login.failed',
                    module: 'Security',
                    outcome: AuditOutcome::Denied,
                    resourceType: 'user',
                    reason: $refusal,
                );

                /*
                 * Deliberately NOT the detailed disclosure SEC-DEC-032 allows.
                 * That decision covers telling somebody the state of THEIR OWN
                 * SemantIQ account. This refusal is about the directory and the
                 * address they came from, and naming the allowed tenant or the
                 * allowed domains would hand an outsider the shape of the
                 * customer's own policy. SEC-DEC-045.
                 */
                return $this->fail(
                    'This directory account cannot sign in to SemantIQ. Ask an administrator if you believe it should.',
                    'outside_authentication_policy',
                );
            }

            $user = $this->resolve($objectId, $email, (string) $name, $tenantId);
        } catch (SelfProvisioningRefused) {
            /*
             * Caught before the generic handler below, so this gets a sentence
             * somebody can act on rather than "something went wrong". It names
             * no policy detail: what is disclosed is that they have no account
             * here, which they can already tell.
             */
            $this->audit->record(
                action: 'auth.login.failed',
                module: 'Security',
                outcome: AuditOutcome::Denied,
                resourceType: 'user',
                reason: 'No SemantIQ account exists for this directory account, and automatic account creation is off.',
            );

            return $this->fail(
                'You do not have a SemantIQ account yet. Ask an administrator to create one for you.',
                'self_provisioning_refused',
            );
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
         *
         * THIS PATH DELIBERATELY SAYS WHY, AND THE CREDENTIAL FORM DOES NOT.
         * The two are not inconsistent; they are answering different questions
         * (SEC-DEC-032).
         *
         * On the credential form, nobody has proved anything yet. Saying "that
         * account is disabled" would confirm to an anonymous visitor that the
         * address belongs to a real person here and tell them what happened to
         * them - account enumeration, which is why that path returns one
         * sentence for a wrong password, an unknown address and a suspended
         * account alike.
         *
         * Here, Microsoft has ALREADY authenticated this person and this is
         * their own account. Telling somebody the state of their own access
         * enumerates nothing: they cannot learn anything about anyone but
         * themselves, and the alternative sends a contractor whose access
         * expired yesterday to a help desk to be told what the screen could
         * have said. So the state is named, and the person is told who to ask.
         *
         * What is still withheld is anything about ANOTHER account, and any
         * detail of why an administrator made the change. The reason recorded
         * in the trail may be fuller than the sentence shown.
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

            return $this->fail($this->accessDeniedMessage($user), 'account_not_active');
        }

        $reauthenticating = (bool) $request->session()->pull(self::REAUTH_KEY, false);

        /*
         * A RE-AUTHENTICATION, not a sign-in. ADM-010.
         *
         * The person was already signed in; they were sent back to Entra with
         * `prompt=login` to prove they are still at the keyboard. Two things
         * follow from that.
         *
         * The account that came back must be the SAME account. Otherwise
         * somebody could satisfy an administrator's confirmation prompt by
         * signing in as themselves, and the confirmation would attach to the
         * administrator's session.
         *
         * The session is NOT regenerated and `Auth::login` is not called again.
         * The existing session continues; only the confirmation timestamp
         * changes. Regenerating would end the very session being confirmed.
         */
        if ($reauthenticating) {
            $current = Auth::user();

            if (! $current instanceof User || $current->getKey() !== $user->getKey()) {
                $this->audit->record(
                    action: 'auth.reauthentication.failed',
                    module: 'Security',
                    outcome: AuditOutcome::Denied,
                    resourceType: 'user',
                    resourceId: $current instanceof User ? $current->getKey() : null,
                    reason: 'The directory account that returned was not the account being confirmed.',
                );

                return $this->fail(
                    'That confirmation was completed with a different account. Sign in again as yourself and retry.',
                    'reauthentication_identity_mismatch',
                );
            }

            app(Reauthentication::class)->confirm();

            $this->audit->record(
                action: 'auth.reauthentication.succeeded',
                module: 'Security',
                resourceType: 'user',
                resourceId: $user->getKey(),
                reason: 'Identity confirmed through a fresh Microsoft Entra sign-in.',
            );

            return redirect()->intended(route('home'));
        }

        /*
         * `remember` follows ADM-010's remember-me policy rather than being
         * hard-coded true. Zero days is the default, and a remembered federated
         * sign-in that policy says should not persist is exactly the kind of
         * quiet disagreement between a screen and the code that makes a policy
         * worthless.
         */
        Auth::login($user, remember: app(SecurityPolicies::class)->number('activity.remember_me_days') > 0);

        // A session fixed before authentication must not become the signed-in one.
        $request->session()->regenerate();

        app(Reauthentication::class)->confirm();

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
    /**
     * What to tell somebody Microsoft authenticated but SemantIQ will not admit.
     *
     * Names the actual state, per SEC-DEC-032. Their identity is proven and
     * this is their own account, so this enumerates nothing - and "your access
     * ended on the 3rd" is the difference between a person who knows what to
     * ask for and a person who opens a support ticket saying "it does not work".
     *
     * Every branch ends with who to approach, because a message that states a
     * problem and no next step is a message that generates a support call.
     */
    private function accessDeniedMessage(User $user): string
    {
        if ($user->accessWindowHasClosed()) {
            return 'Your access to SemantIQ ended on '
                .$user->access_end->toFormattedDateString()
                .'. Ask an administrator to extend it.';
        }

        return match ($user->status) {
            LifecycleStatus::Invited => 'Your SemantIQ account has not been activated yet. Ask an administrator to activate it.',
            LifecycleStatus::Disabled => 'Your SemantIQ access has been disabled. Ask an administrator to restore it.',
            LifecycleStatus::Locked => 'Your SemantIQ account is locked. Ask an administrator to unlock it.',
            LifecycleStatus::Expired => 'Your SemantIQ access has expired. Ask an administrator to extend it.',
            /* A state that permits authentication should never reach here. If
             * it does, something is wrong with the check rather than with the
             * account, and the generic sentence is the honest answer. */
            default => 'Your access to SemantIQ is not currently active. Contact an administrator.',
        };
    }

    private function resolve(string $objectId, string $email, string $name, ?string $tenantId): User
    {
        $user = User::query()->where('entra_object_id', $objectId)->first()
            ?? User::query()->where('email', $email)->first()
            ?? new User;

        $attributes = [
            'name' => $name !== '' ? $name : $email,
            'email' => $email,
            'entra_object_id' => $objectId,
            'entra_tenant_id' => $tenantId,
            'authentication_source' => 'entra',
            'last_signed_in_at' => now(),
        ];

        /*
         * A NEW account must be PLACED in an organisation, and this is the only
         * place in the application that creates one without an administrator
         * present to say which.
         *
         * An unplaced account is unmanageable: every mutation in `UserRegistry`
         * refuses a subject that does not belong to the current organisation
         * (VAL-ORG-SUBJECT-001), so an administrator could never disable, place
         * or entitle somebody who had signed in through Microsoft. That is not
         * a theoretical gap - it was the state of this method until the guard
         * exposed it.
         *
         * On the single-organisation deployment baseline the context resolves
         * to the one active organisation, which is the right answer. On an
         * instance holding more than one it deliberately resolves to nothing,
         * because WHICH customer a new federated person belongs to is a real
         * question and guessing it would put somebody in the wrong tenant. The
         * caller refuses the sign-in rather than creating an account nobody
         * owns.
         */
        if (! $user->exists) {
            /*
             * ADM-009's "Auto-create Users", and it is OFF by default.
             *
             * With it off, somebody who authenticates against the directory but
             * has no SemantIQ account is refused rather than given one. That is
             * the safer default because it keeps the decision about who gets an
             * account with an administrator: a directory can contain every
             * employee, every contractor and every guest of every partner
             * organisation, and "authenticated" is not the same question as
             * "should be able to use this".
             */
            if (! $this->guard->maySelfProvision()) {
                throw new SelfProvisioningRefused;
            }

            $organisationId = app(OrganisationContext::class)->currentId();

            if ($organisationId === null) {
                throw new RuntimeException('No organisation context: a new federated account cannot be placed.');
            }

            $attributes['organisation_id'] = $organisationId;
        }

        $user->forceFill($attributes)->save();

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
            /* Scrubbed like any other string from outside: a token exchange
             * error can quote the request it failed on, and that request
             * carried the client secret. */
            Log::warning('Microsoft sign-in failed', ['reason' => Redaction::scrub($reason)]);
        }

        /*
         * A signed-in person whose RE-AUTHENTICATION failed is still signed in,
         * and sending them to the sign-in screen would look like they had been
         * signed out. They go back to the confirmation screen with the message
         * instead, where trying again makes sense.
         */
        if (Auth::check()) {
            return redirect()->route('reauthenticate')->withErrors(['form' => $message]);
        }

        return redirect()->route('sign-in')->withErrors(['form' => $message]);
    }
}
