<?php

declare(strict_types=1);

namespace App\Modules\Security\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Audit\Enums\AuditOutcome;
use App\Modules\Audit\Support\AuditLogger;
use App\Modules\Security\Enums\CriticalAction;
use App\Modules\Security\Support\Reauthentication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Prove who you are again, before a critical action. Feature ADM-010.
 *
 * Reached only by being redirected here from `ConfirmIdentity`. It never
 * decides WHETHER something needs confirming - that is the middleware's job -
 * and it never performs the action being confirmed. It establishes one fact and
 * records one timestamp.
 *
 * TWO PATHS, because a federated account has no password here:
 *
 *  - a LOCAL account is asked for its password, which this application holds
 *    the hash of;
 *  - a FEDERATED account is sent to Entra with `prompt=login`, and the round
 *    trip is the proof. Nothing extra is stored to achieve it.
 *
 * THROTTLED like the sign-in form. A confirmation prompt that accepted
 * unlimited password guesses would be a password oracle sitting behind
 * authentication, which is a better place to guess from than the front door
 * because the address is already known to be valid.
 */
class ReauthenticationController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    public function __construct(
        private readonly Reauthentication $reauthentication,
        private readonly AuditLogger $audit,
    ) {}

    public function show(Request $request): View|RedirectResponse
    {
        /*
         * Nothing to confirm. Reached by typing the URL, or by coming back to a
         * stale tab after confirming elsewhere. Sending them on rather than
         * asking for a password they do not owe is the honest response.
         */
        if (! $this->reauthentication->isRequired() || $this->reauthentication->isFresh()) {
            return redirect()->to($this->intended($request));
        }

        $action = CriticalAction::tryFrom((string) $request->session()->get('reauthenticate.action', ''));

        return view('auth.confirm-identity', [
            'usesPassword' => $this->reauthentication->usesPassword(),
            'action' => $action,
            'validMinutes' => $this->reauthentication->validMinutes(),
        ]);
    }

    public function confirm(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $this->reauthentication->usesPassword($user)) {
            /*
             * A federated account cannot be asked for a password. Refusing here
             * rather than rendering a field that can never be satisfied: a form
             * nobody can complete is worse than an honest redirect.
             */
            return redirect()->route('sign-in.microsoft');
        }

        $key = 'confirm-identity|'.$user->getKey().'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'form' => sprintf(
                    'Too many attempts. Try again in %d seconds.',
                    RateLimiter::availableIn($key),
                ),
            ]);
        }

        $request->validate(
            ['password' => ['required', 'string']],
            ['password.required' => 'Enter your password to confirm this is you.'],
        );

        if (! Hash::check((string) $request->input('password'), (string) $user->getAuthPassword())) {
            RateLimiter::hit($key, self::DECAY_SECONDS);

            $this->audit->record(
                action: 'auth.reauthentication.failed',
                module: 'Security',
                outcome: AuditOutcome::Denied,
                resourceType: 'user',
                resourceId: $user->getKey(),
                reason: 'The password given at the confirmation prompt did not match.',
            );

            throw ValidationException::withMessages([
                'form' => 'That password is not correct.',
            ]);
        }

        RateLimiter::clear($key);

        /*
         * A new session id. A confirmation raises what this session can do, and
         * anything that raises privilege is a moment a fixed session id should
         * not survive.
         */
        $request->session()->regenerate();

        $this->reauthentication->confirm();

        $this->audit->record(
            action: 'auth.reauthentication.succeeded',
            module: 'Security',
            resourceType: 'user',
            resourceId: $user->getKey(),
            reason: 'Identity confirmed with a password.',
        );

        $destination = $this->intended($request);

        /* Used once. Leaving it behind would send a later, unrelated
         * confirmation back to a screen the person is no longer working on. */
        $request->session()->forget(['reauthenticate.intended', 'reauthenticate.action']);

        return redirect()->to($destination)
            ->with('status', 'Identity confirmed. Try that action again.');
    }

    /**
     * Where to send them afterwards.
     *
     * Read from the SESSION, never from the request, and only a URL on this
     * host is honoured. A confirmation screen that redirects anywhere it is
     * told is an open redirect on the one page a person is most primed to
     * trust, and taking the destination from a form field would make it one.
     */
    private function intended(Request $request): string
    {
        $intended = (string) $request->session()->get('reauthenticate.intended', '');

        if ($intended !== '' && str_starts_with($intended, (string) config('app.url'))) {
            return $intended;
        }

        return route('home');
    }
}
