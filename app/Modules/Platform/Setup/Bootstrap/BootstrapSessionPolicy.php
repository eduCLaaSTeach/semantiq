<?php

declare(strict_types=1);

namespace App\Modules\Platform\Setup\Bootstrap;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * D-165. THE BOOTSTRAP SESSION'S OWN, SHORTER, POLICY.
 *
 *   30 MINUTES IDLE
 *   4 HOURS ABSOLUTE
 *
 * DELIBERATELY NOT Laravel's SESSION_LIFETIME, and deliberately not
 * EnsureSessionIsCurrent's twelve hours. This credential exists only until the
 * first permanent administrator is established, it is used at a keyboard by
 * somebody doing one task, and it is the most privileged local thing in the
 * deployment. Inheriting the normal user policy would give a setup session the
 * lifetime of an ordinary working day.
 *
 * THE TIMESTAMPS ARE SERVER-SIDE, AND THAT IS THE WHOLE MECHANISM. They live in
 * the session record, which the server writes and the browser cannot. A cookie
 * expiry is a request the browser may honour; this is a fact the server checks.
 *
 * ORDER MATTERS AND IS FIXED - see RequireBootstrapSession:
 *
 *   1. is bootstrap still open at all
 *   2. is this session's principal still the principal
 *   3. idle
 *   4. absolute
 *   5. only then, touch
 *
 * TOUCHING LAST IS THE POINT. Updating last activity before the checks would
 * mean every request refreshed the idle window - including the request that
 * should have failed it - and the idle timeout would never fire.
 *
 * AN UNREADABLE TIMESTAMP IS AN EXPIRED ONE. A missing, malformed or
 * unparseable value fails closed rather than being treated as "probably fine",
 * because the alternative is a session whose age nobody can establish being
 * allowed to continue.
 */
final class BootstrapSessionPolicy
{
    public const IDLE_MINUTES = 30;

    public const ABSOLUTE_HOURS = 4;

    public const SESSION_AUTHENTICATED_AT = 'bootstrap.authenticated_at';

    public const SESSION_LAST_ACTIVITY_AT = 'bootstrap.last_activity_at';

    /** Called once, at authentication. Both clocks start together. */
    public function begin(Request $request): void
    {
        $now = now()->toIso8601String();

        $request->session()->put(self::SESSION_AUTHENTICATED_AT, $now);
        $request->session()->put(self::SESSION_LAST_ACTIVITY_AT, $now);
    }

    /**
     * Has this session run out of either clock?
     *
     * Exactly 30 minutes idle expires, and exactly 4 hours absolute expires:
     * the boundary belongs to the refusal, because a policy stated as "30
     * minutes" that permits the thirtieth minute is a policy of 30 minutes and
     * a bit, and nobody would be able to say what the bit was.
     */
    public function hasExpired(Request $request): bool
    {
        $authenticatedAt = $this->instant($request, self::SESSION_AUTHENTICATED_AT);
        $lastActivityAt = $this->instant($request, self::SESSION_LAST_ACTIVITY_AT);

        if ($authenticatedAt === null || $lastActivityAt === null) {
            return true;
        }

        if ($lastActivityAt->copy()->addMinutes(self::IDLE_MINUTES)->lte(now())) {
            return true;
        }

        /*
         * THE ABSOLUTE CLOCK IS NOT REFRESHED BY ACTIVITY. That is the
         * difference between the two: a session in continuous use still ends
         * four hours after it began. Without it, "idle timeout" alone would let
         * a session live indefinitely as long as somebody kept a tab open.
         */
        return $authenticatedAt->copy()->addHours(self::ABSOLUTE_HOURS)->lte(now());
    }

    /** Move the idle clock forward. Called ONLY after the checks have passed. */
    public function touch(Request $request): void
    {
        $request->session()->put(self::SESSION_LAST_ACTIVITY_AT, now()->toIso8601String());
    }

    /**
     * Clear everything this session held.
     *
     * THE RECOVERY MARKER GOES TOO. A recovery episode is opened on one
     * session; letting the marker outlive the session that opened it would mean
     * a stale tab could still hold an open recovery context after the
     * credential session behind it had expired.
     */
    public function clear(Request $request): void
    {
        $request->session()->forget(BootstrapAuthenticator::SESSION_KEY);
        $request->session()->forget(BootstrapAccess::RECOVERY_SESSION_KEY);
        $request->session()->forget(self::SESSION_AUTHENTICATED_AT);
        $request->session()->forget(self::SESSION_LAST_ACTIVITY_AT);
        $request->session()->regenerate();
    }

    private function instant(Request $request, string $key): ?Carbon
    {
        $value = $request->session()->get($key);

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
