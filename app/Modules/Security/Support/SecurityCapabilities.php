<?php

declare(strict_types=1);

namespace App\Modules\Security\Support;

/**
 * What this deployment is physically able to enforce. Feature ADM-010.
 *
 * A security policy is worth exactly as much as the environment's ability to
 * apply it. Two of ADM-010's controls - concurrent session limits and
 * administrator-initiated revocation - need SemantIQ to list a person's live
 * sessions, and that is a property of the SESSION DRIVER, not of the code.
 *
 * Production runs `SESSION_DRIVER=file` at the time of writing. File sessions
 * are opaque blobs keyed by session id with no index by user, so neither
 * control can work there. Laravel's own `logoutOtherDevices` is not a way round
 * it: it works by rehashing the account's password, and a federated account has
 * no password to rehash.
 *
 * This class exists so that ONE piece of code answers "can we do this here",
 * and every screen, policy check and overview badge reads the same answer. The
 * alternative - each screen deciding for itself - is how one page ends up
 * offering a button another page says is impossible.
 *
 * Decision D3, approved 25 August 2026: build the capability now, detect the
 * driver at runtime, report it as unavailable while production stays on `file`,
 * and change nothing about the production environment in this gate.
 */
class SecurityCapabilities
{
    /**
     * Session drivers that can list a person's sessions.
     *
     * `database` is the one this application has a table for. `redis` and
     * `dynamodb` could in principle, but neither is configured here and neither
     * has been tested, and claiming an untested capability is how a control
     * gets believed and then found missing. Add one only with a test that
     * proves enumeration works on it.
     *
     * @var list<string>
     */
    private const ENUMERABLE_DRIVERS = ['database'];

    /**
     * Whether a person's live sessions can be listed and ended.
     *
     * Gates the concurrent session policy and administrator revocation.
     */
    public function canEnumerateSessions(): bool
    {
        return in_array($this->sessionDriver(), self::ENUMERABLE_DRIVERS, true);
    }

    /** The driver actually in force, for the message on screen. */
    public function sessionDriver(): string
    {
        return (string) config('session.driver', 'file');
    }

    /**
     * Why session enumeration is unavailable, in words an administrator can act
     * on. Null when it IS available.
     *
     * The sentence names the driver in force and the one needed, because
     * "unavailable" without a cause is a dead end for whoever reads it.
     */
    public function sessionEnumerationBlocker(): ?string
    {
        if ($this->canEnumerateSessions()) {
            return null;
        }

        return sprintf(
            'This deployment stores sessions with the "%s" driver, which cannot list a person\'s sessions. '
            .'Ending somebody else\'s session and limiting concurrent sessions both need the "database" driver. '
            .'Changing it signs everybody out at the moment it takes effect, so it is a separately approved change.',
            $this->sessionDriver(),
        );
    }

    /**
     * Whether a named capability is available.
     *
     * The catalogue's `requires` field names one of these. Anything unknown is
     * reported as UNAVAILABLE rather than available, so a typo in the catalogue
     * disables a control instead of silently claiming it works.
     */
    public function has(string $capability): bool
    {
        return match ($capability) {
            'session_enumeration' => $this->canEnumerateSessions(),
            default => false,
        };
    }

    /** Why a named capability is unavailable, or null when it is available. */
    public function blocker(string $capability): ?string
    {
        return match ($capability) {
            'session_enumeration' => $this->sessionEnumerationBlocker(),
            default => 'This control depends on a capability called "'.$capability.'", which this build does not recognise.',
        };
    }
}
