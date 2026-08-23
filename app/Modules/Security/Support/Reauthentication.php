<?php

declare(strict_types=1);

namespace App\Modules\Security\Support;

use App\Models\User;
use App\Modules\Security\Enums\CriticalAction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * Proving who you are again, before a critical action. Feature ADM-010.
 *
 * The problem this solves is an unlocked machine. Everything else in the
 * authorization stack asks "may this ACCOUNT do this"; nothing asks "is the
 * person at the keyboard still the account holder". A confirmation is the only
 * control that distinguishes the two, and it is why ADM-010 lists it separately
 * from every permission check.
 *
 * HOW THE PROOF IS OBTAINED depends on how the account signs in, and this is
 * where a federated deployment differs from a conventional one:
 *
 *  - A LOCAL account is asked for its password. SemantIQ holds the hash, so it
 *    can check it.
 *  - A FEDERATED account has no password here, and inventing one would be
 *    exactly the credential this application is designed not to hold. It is
 *    sent back to Entra with `prompt=login`, which asks the directory to
 *    challenge the person rather than reuse its existing session. The round
 *    trip IS the proof.
 *
 * NOTHING EXTRA IS STORED to make the federated path work. No additional access
 * token, no refresh token, no cached assertion - only a timestamp saying when
 * the proof happened. That is a deliberate constraint of this gate: a
 * re-authentication mechanism that accumulated Microsoft credentials would have
 * made the application a more attractive target than the control was worth.
 *
 * FAILS CLOSED. A session with no recorded confirmation is not confirmed, even
 * immediately after sign-in on a path that forgot to record one. The cost of
 * that choice is one extra prompt; the cost of the other choice is a control
 * that silently does nothing.
 */
class Reauthentication
{
    /**
     * When identity was last proved, as a UTC timestamp in the session.
     *
     * In the SESSION rather than on the user row, deliberately. A confirmation
     * belongs to one browser at one keyboard: writing it to `users` would mean
     * confirming on a laptop also unlocked a critical action on a phone that
     * was already signed in.
     *
     * Public so a test can establish a confirmed session without driving the
     * whole confirmation flow first. Nothing in the application reads it
     * directly - `isFresh()` is the only supported question.
     */
    public const CONFIRMED_AT = 'security.identity_confirmed_at';

    public function __construct(
        private readonly SecurityPolicies $policies,
    ) {}

    /** Whether confirmations are switched on at all. */
    public function isRequired(): bool
    {
        return $this->policies->enabled('activity.confirm_critical_actions');
    }

    /** How long one confirmation covers further critical actions. */
    public function validMinutes(): int
    {
        return $this->policies->number('activity.confirmation_valid_minutes');
    }

    /**
     * Whether this session has proved identity recently enough.
     */
    public function isFresh(): bool
    {
        $confirmedAt = Session::get(self::CONFIRMED_AT);

        if (! is_string($confirmedAt)) {
            return false;
        }

        $moment = Carbon::parse($confirmedAt);

        return $moment->addMinutes($this->validMinutes())->isFuture();
    }

    /**
     * Whether a named action must be confirmed before it may proceed.
     *
     * Both halves have to hold: the switch is on AND the catalogue lists this
     * action. An action absent from `config('security.critical_actions')` is
     * one nothing protects, which is why the enum only declares the ones that
     * exist - see `CriticalAction`.
     */
    public function isDemandedFor(CriticalAction $action): bool
    {
        if (! $this->isRequired()) {
            return false;
        }

        return in_array($action, (array) config('security.critical_actions', []), true);
    }

    /**
     * Record that identity has just been proved.
     *
     * Called by the confirmation screen, by the Microsoft callback when it
     * returns from a `prompt=login` round trip, and by a successful sign-in -
     * signing in a moment ago IS a proof of identity, and asking again
     * immediately would train people to click through the prompt.
     */
    public function confirm(): void
    {
        Session::put(self::CONFIRMED_AT, Carbon::now()->utc()->toIso8601String());
    }

    /**
     * Drop any recorded confirmation.
     *
     * Used when the account changes underneath the session, so a confirmation
     * obtained as one person cannot cover an action taken as another.
     */
    public function forget(): void
    {
        Session::forget(self::CONFIRMED_AT);
    }

    /**
     * How this account can prove itself: with a password, or through Entra.
     *
     * Read from the ACCOUNT rather than from policy, because it is a fact about
     * the account and not a choice. An account with no usable password hash
     * cannot be asked for a password however the policy is set.
     */
    public function usesPassword(?User $user = null): bool
    {
        $user ??= Auth::user();

        if (! $user instanceof User) {
            return false;
        }

        return $user->authentication_source === 'local'
            && is_string($user->getAuthPassword())
            && $user->getAuthPassword() !== '';
    }
}
