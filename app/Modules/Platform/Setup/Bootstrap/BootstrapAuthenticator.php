<?php

declare(strict_types=1);

namespace App\Modules\Platform\Setup\Bootstrap;

use App\Modules\Platform\Security\SecurityEventLogger;
use App\Modules\Platform\Setup\Bootstrap\Models\BootstrapAdministrator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Local password sign-in for the setup administrator.
 *
 * CORRECTION 6: EVIDENCE FIRST, PRIVILEGE SECOND, AND NO PATH THAT REVERSES
 * THEM.
 *
 * The first draft classed bootstrap.signin.succeeded as BestEffort, which in
 * this codebase means exactly one thing: if the write fails, carry on. Carrying
 * on here means issuing a session for the most privileged local credential in
 * the deployment with no record that it was ever used - so anyone able to make
 * audit writes fail would get a silent sign-in, and afterwards the deployment
 * could not answer "was this used?".
 *
 * A successful login is a STATE CHANGE, not a courtesy note. Ordinary
 * successful login is already StateChangeRecordedFirst; giving the local
 * bootstrap login weaker semantics than the ordinary one is backwards. So
 * record() runs BEFORE the session is touched, and it throws if the evidence
 * cannot be written - at which point no session has been issued and none will
 * be. See EventCatalogue for the declaration that makes the throw happen.
 *
 * THE REFUSAL KEEPS REFUSAL SEMANTICS. A refusal that cannot be recorded must
 * still refuse: hardening it into a failure would let a broken audit store lock
 * somebody out of recovery while granting nothing. Sign-out stays BestEffort,
 * because failing a sign-out keeps a privileged session alive.
 *
 * EVERY REFUSAL IS THE SAME REFUSAL. No local principal, a closed bootstrap, an
 * unknown email and a wrong password are indistinguishable from outside.
 * Distinguishing them would turn this endpoint into a way to ask whether a
 * deployment has an administrator yet, which is the first thing worth knowing
 * about a target.
 *
 * THE SESSION IDENTIFIER IS REGENERATED at authentication, so a fixated
 * identifier from before sign-in cannot become a privileged one.
 */
final class BootstrapAuthenticator
{
    public const SESSION_KEY = 'bootstrap.principal';

    public function __construct(
        private readonly BootstrapAccess $access,
        private readonly SecurityEventLogger $events,
    ) {}

    /**
     * @return BootstrapPrincipal|null Null is the ONLY refusal shape. See above.
     */
    public function attempt(Request $request, string $email, string $password): ?BootstrapPrincipal
    {
        $principal = $this->access->principal($request);

        if ($principal === null || ! $this->credentialMatches($principal, $email, $password)) {
            $this->refuse();

            return null;
        }

        /*
         * THE ORDER IS THE GUARANTEE.
         *
         * This throws when the evidence cannot be persisted, and it throws
         * HERE - before regenerate(), before the session key, before anything
         * a caller could mistake for a signed-in state. There is no catch
         * below it, deliberately: a caught failure would be a silent sign-in
         * wearing a log line.
         */
        $this->events->record(SecurityEventLogger::BOOTSTRAP_SIGNIN_SUCCEEDED, [
            'result' => 'succeeded',
        ]);

        $request->session()->regenerate();
        $request->session()->put(self::SESSION_KEY, $principal->id);

        $principal->last_signed_in_at = now();
        $principal->save();

        return new BootstrapPrincipal($principal->id, $principal->email);
    }

    public function signOut(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
        $request->session()->forget(BootstrapAccess::RECOVERY_SESSION_KEY);
        $request->session()->regenerate();

        // BestEffort by declaration. Failing here would keep a privileged
        // session alive because its farewell note could not be filed.
        $this->events->record(SecurityEventLogger::BOOTSTRAP_SIGNOUT, [
            'result' => 'signed_out',
        ]);
    }

    private function credentialMatches(BootstrapAdministrator $principal, string $email, string $password): bool
    {
        /*
         * BOTH COMPARISONS ALWAYS RUN. Returning early on a wrong email would
         * make an unknown identity measurably faster than a wrong password,
         * which is the same disclosure the generic message is there to prevent,
         * made through the clock instead of the screen.
         */
        $emailMatches = hash_equals(
            mb_strtolower($principal->email),
            mb_strtolower(trim($email)),
        );

        $passwordMatches = $this->passwordMatches($password, $principal->password_hash);

        return $emailMatches && $passwordMatches;
    }

    /**
     * AN UNUSABLE STORED HASH REFUSES. IT DOES NOT THROW.
     *
     * BootstrapCloser replaces the hash with a value no password can match.
     * Handing that value to Hash::check raises "This password does not use the
     * Bcrypt algorithm", which fails closed in the sense that nobody gets in -
     * and fails open in the sense that the sign-in screen returns a 500 with a
     * stack trace instead of the generic refusal every other case gets. An
     * error page that appears for exactly one reason is a disclosure.
     *
     * THE TEST IS "IS THIS A HASH AT ALL", NOT "IS THIS THE SENTINEL". A guard
     * written as a comparison against UNUSABLE_HASH would be satisfied by any
     * other unusable value - a blank column, a truncated string, a row half
     * written by a failed migration - and each of those would throw exactly
     * the same way. Asking the hasher whether it recognises the value covers
     * all of them, and does not have to be updated when the sentinel changes.
     */
    private function passwordMatches(string $password, string $storedHash): bool
    {
        if (Hash::info($storedHash)['algoName'] === 'unknown') {
            return false;
        }

        return Hash::check($password, $storedHash);
    }

    private function refuse(): void
    {
        $this->events->record(SecurityEventLogger::BOOTSTRAP_SIGNIN_REFUSED, [
            'result' => 'refused',
            // A FIXED VOCABULARY, never which half failed. The reason a
            // person sees and the reason recorded are both deliberately
            // uninformative about whether the identity exists.
            'reason' => 'invalid_credential',
        ]);
    }
}
