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
    /**
     * How many attempts one address and address-plus-network pair may make.
     *
     * Throttling is on the credential path only. Without it the form is an
     * offline password list with a network connection attached.
     */
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Show the screen.
     */
    public function show(): View
    {
        return view('auth.sign-in');
    }

    /**
     * Try a set of credentials.
     */
    public function attempt(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Enter your work email.',
            'email.email' => 'Enter a valid email address.',
            'password.required' => 'Enter your password.',
        ]);

        $key = $this->throttleKey($request);

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'form' => sprintf(
                    'Too many sign-in attempts. Try again in %d seconds.',
                    RateLimiter::availableIn($key),
                ),
            ]);
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($key, self::DECAY_SECONDS);

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

        if ($user instanceof User && ! $user->mayAuthenticate()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            RateLimiter::hit($key, self::DECAY_SECONDS);

            $this->audit->record(
                action: 'auth.login.failed',
                module: 'Security',
                outcome: AuditOutcome::Denied,
                resourceType: 'user',
                resourceId: $user->getKey(),
                /* The reason IS recorded in the trail, where an administrator
                 * can see it. It is only withheld from the browser. */
                reason: 'Account is '.$user->status->label().' or outside its access window.',
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
}
