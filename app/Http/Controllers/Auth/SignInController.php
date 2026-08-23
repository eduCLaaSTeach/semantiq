<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Audit\Enums\AuditOutcome;
use App\Modules\Audit\Support\AuditLogger;
use App\Modules\Security\Support\AuthenticationGuard;
use App\Modules\Security\Support\Reauthentication;
use App\Modules\Security\Support\SecurityPolicies;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The sign-in screen and the credential path behind it.
 *
 * Two ways in are offered. Microsoft single sign-on is the intended one, since
 * identity is federated and the customer's directory decides who gets an
 * account at all. The credential form is the fallback for accounts the
 * directory does not hold.
 *
 * Errors follow the section 8 validation contract. A wrong password is not a
 * field error: neither the address nor the password is individually wrong, the
 * pair is, so it becomes the form-level message shown beside the submit rather
 * than an arrow pointing at one of the two inputs.
 */
class SignInController extends Controller
{
    /*
     * The attempt threshold and the lockout duration used to be two private
     * constants here. They are now ADM-009 policy, read from
     * `AuthenticationGuard`, because "Failed Login Threshold" and "Lock
     * Duration" are two of the settings that feature names and a constant in a
     * controller is not a setting anybody can change.
     *
     * Throttling remains on the credential path only. Without it the form is an
     * offline password list with a network connection attached.
     */

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly AuthenticationGuard $guard,
    ) {}

    /**
     * Show the screen.
     *
     * Which ways in are OFFERED comes from ADM-009's authentication mode. A way
     * in that policy has turned off is absent from the screen, not disabled on
     * it: a greyed-out password field invites somebody to ask for it back.
     */
    public function show(): View
    {
        return view('auth.sign-in', [
            'offersCredentialForm' => $this->guard->offersCredentialForm(),
            'offersFederatedSignIn' => $this->guard->offersFederatedSignIn(),
        ]);
    }

    /**
     * Try a set of credentials.
     */
    public function attempt(Request $request): RedirectResponse
    {
        /*
         * The form may be gone from the screen while this route still exists,
         * so the route refuses independently. A control that is only hidden is
         * not an access control.
         */
        if (! $this->guard->offersCredentialForm()) {
            $this->audit->denied(
                action: 'auth.login.failed',
                module: 'Security',
                resourceType: 'user',
                reason: 'Credential sign-in is not offered under the current authentication policy.',
            );

            throw ValidationException::withMessages([
                'form' => 'Signing in with a password is not available. Use the Microsoft sign-in button.',
            ]);
        }

        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Enter your work email.',
            'email.email' => 'Enter a valid email address.',
            'password.required' => 'Enter your password.',
        ]);

        $key = $this->throttleKey($request);

        if (RateLimiter::tooManyAttempts($key, $this->guard->attemptThreshold())) {
            throw ValidationException::withMessages([
                'form' => sprintf(
                    'Too many sign-in attempts. Try again in %d seconds.',
                    RateLimiter::availableIn($key),
                ),
            ]);
        }

        if (! Auth::attempt($credentials, $this->mayRemember($request))) {
            RateLimiter::hit($key, $this->guard->lockSeconds());

            $this->audit->record(
                action: 'auth.login.failed',
                module: 'Security',
                outcome: AuditOutcome::Failed,
                resourceType: 'user',
                /* The address, never the password. `Redaction` would strip a
                 * password anyway; not passing one is the better guarantee. */
                reason: 'Credentials did not match for '.$credentials['email'].'.',
            );

            /*
             * One message, and it never says which half was wrong. "No account
             * with that address" turns the form into a directory lookup that
             * confirms who works here.
             */
            throw ValidationException::withMessages([
                'form' => 'Those credentials do not match our records.',
            ]);
        }

        /*
         * The password was right. Whether the ACCOUNT may sign in is a separate
         * question - VAL-USER-DISABLED-001 and VAL-USER-WINDOW-001 - and it is
         * asked after authentication rather than before, so a disabled account
         * and a wrong password remain indistinguishable from outside. Checking
         * first would turn the form back into the directory lookup the message
         * above exists to prevent.
         */
        $user = Auth::user();

        /*
         * Two separate questions, both asked after the password is verified and
         * both answered with the SAME sentence.
         *
         * 1. Is the ACCOUNT allowed to authenticate at all - VAL-USER-DISABLED-001
         *    and VAL-USER-WINDOW-001.
         * 2. Is this FORM allowed to admit this account under ADM-009's
         *    authentication policy - VAL-AUTH-MODE-001. A federated account, or
         *    anybody who is not a local System Administrator while Entra is the
         *    primary path, is refused here even with the right password.
         *
         * The reason differs in the trail and never differs in the browser.
         */
        $refusal = $user instanceof User
            ? ($user->mayAuthenticate()
                ? $this->guard->credentialRefusal($user)
                : 'Account is '.$user->status->label().' or outside its access window.')
            : null;

        if ($user instanceof User && $refusal !== null) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            RateLimiter::hit($key, $this->guard->lockSeconds());

            $this->audit->record(
                action: 'auth.login.failed',
                module: 'Security',
                outcome: AuditOutcome::Denied,
                resourceType: 'user',
                resourceId: $user->getKey(),
                /* The reason IS recorded in the trail, where an administrator
                 * can see it. It is only withheld from the browser. */
                reason: $refusal,
            );

            throw ValidationException::withMessages([
                'form' => 'Those credentials do not match our records.',
            ]);
        }

        RateLimiter::clear($key);

        $this->audit->record(
            action: 'auth.login.succeeded',
            module: 'Security',
            resourceType: 'user',
            resourceId: $user?->getKey(),
        );

        /*
         * A new session id on sign-in, so a session fixed before authentication
         * is not the one that ends up signed in.
         */
        $request->session()->regenerate();

        /*
         * Signing in a moment ago IS a proof of identity, so it counts as one
         * for ADM-010's critical actions. Recording it here rather than making
         * an administrator confirm twice in the same minute is the difference
         * between a control people respect and one they click through.
         */
        app(Reauthentication::class)->confirm();

        return redirect()->intended('/');
    }

    /**
     * End the session.
     */
    public function signOut(Request $request): RedirectResponse
    {
        $this->audit->record(
            action: 'auth.logout',
            module: 'Security',
            resourceType: 'user',
            resourceId: Auth::id(),
        );

        app(Reauthentication::class)->forget();

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('sign-in')->with('status', 'You have been signed out.');
    }

    /**
     * Per address and per network, so one account being attacked cannot lock
     * out an unrelated person behind the same address, and one network cannot
     * work through a list of addresses unthrottled.
     */
    private function throttleKey(Request $request): string
    {
        return 'sign-in|'.mb_strtolower((string) $request->input('email')).'|'.$request->ip();
    }

    /**
     * Whether "remember me" is honoured at all.
     *
     * ADM-010's remember-me policy, applied at the one place it can actually
     * take effect. Zero days turns it off, which is the default: a remembered
     * sign-in outlives the browser closing and is a credential sitting on a
     * device. A ticked box that policy does not honour is ignored silently
     * here, and the sign-in screen does not render the box when the policy is
     * zero, so the two agree.
     */
    private function mayRemember(Request $request): bool
    {
        return $request->boolean('remember')
            && app(SecurityPolicies::class)->number('activity.remember_me_days') > 0;
    }
}
