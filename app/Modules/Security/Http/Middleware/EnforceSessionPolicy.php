<?php

declare(strict_types=1);

namespace App\Modules\Security\Http\Middleware;

use App\Models\User;
use App\Modules\Audit\Enums\AuditOutcome;
use App\Modules\Audit\Support\AuditLogger;
use App\Modules\Security\Support\Reauthentication;
use App\Modules\Security\Support\SecurityPolicies;
use App\Modules\Security\Support\SessionRegistry;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ends a session that has outlived the policy. Feature ADM-010.
 *
 * WHY THIS EXISTS AT ALL, given that Laravel has `config('session.lifetime')`.
 * That value is read from the server environment at boot. It is not something
 * an administrator can change from a screen, it applies one number to idleness
 * and says nothing about total age, and changing it needs server access and a
 * deploy. ADM-010 asks for two separate limits, set by an administrator, taking
 * effect immediately. So the policy is enforced here, on the request, against
 * the values in `security_policies`.
 *
 * TWO DIFFERENT LIMITS, and they catch different things:
 *
 *   IDLE - time since the last request. Catches the abandoned session on an
 *   unlocked machine.
 *
 *   MAXIMUM - time since sign-in, however busy the session has been. Catches
 *   the session that never goes idle because something keeps it warm, which is
 *   precisely the shape of a session somebody else is using.
 *
 * A session ended here is ended properly - signed out, invalidated, token
 * regenerated - and recorded, because "your session ended" with no trail is
 * indistinguishable from a fault.
 *
 * DELIBERATELY DOES NOT run on the guest routes. A visitor with no session has
 * nothing to expire, and stamping timestamps into an anonymous session would
 * create one for every crawler that touches the sign-in page.
 */
class EnforceSessionPolicy
{
    /** When this session's identity was established, as an ISO timestamp. */
    private const STARTED_AT = 'security.session_started_at';

    /** When this session last made a request. */
    private const LAST_SEEN_AT = 'security.session_last_seen_at';

    public function __construct(
        private readonly SecurityPolicies $policies,
        private readonly SessionRegistry $sessions,
        private readonly Reauthentication $reauthentication,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return $next($request);
        }

        $now = Carbon::now()->utc();
        $session = $request->session();

        $startedAt = $session->get(self::STARTED_AT);
        $lastSeenAt = $session->get(self::LAST_SEEN_AT);

        /*
         * First authenticated request of a new session. Stamp it, and apply the
         * concurrent session policy here rather than in the two sign-in
         * controllers, so a third way in - a future API, a console impersonation
         * - cannot bypass the limit by forgetting to call it.
         */
        if (! is_string($startedAt)) {
            $session->put(self::STARTED_AT, $now->toIso8601String());
            $session->put(self::LAST_SEEN_AT, $now->toIso8601String());

            $this->sessions->applyConcurrencyLimit($user, (string) $session->getId());

            return $next($request);
        }

        $idleLimit = $this->policies->number('activity.idle_minutes');
        $maximumLimit = $this->policies->number('activity.maximum_minutes');

        $idleSince = is_string($lastSeenAt) ? Carbon::parse($lastSeenAt) : Carbon::parse($startedAt);

        if ($idleSince->copy()->addMinutes($idleLimit)->isPast()) {
            return $this->end($request, $user, 'idle', $idleLimit);
        }

        if (Carbon::parse($startedAt)->copy()->addMinutes($maximumLimit)->isPast()) {
            return $this->end($request, $user, 'maximum', $maximumLimit);
        }

        $session->put(self::LAST_SEEN_AT, $now->toIso8601String());

        return $next($request);
    }

    /**
     * End the session and say why, in a sentence the person can act on.
     *
     * "You were signed out because the session had been idle for two hours" is
     * a different message from "please sign in", and the difference is whether
     * somebody reports a bug.
     */
    private function end(Request $request, User $user, string $rule, int $limitMinutes): Response
    {
        $this->audit->record(
            action: 'auth.session.expired',
            module: 'Security',
            outcome: AuditOutcome::Succeeded,
            resourceType: 'user',
            resourceId: $user->getKey(),
            after: ['rule' => $rule, 'limit_minutes' => $limitMinutes],
            reason: $rule === 'idle'
                ? 'Session ended after reaching the idle timeout.'
                : 'Session ended after reaching the maximum duration.',
        );

        /* The confirmation goes with the session it belonged to. */
        $this->reauthentication->forget();

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $message = $rule === 'idle'
            ? 'You were signed out after '.$limitMinutes.' minutes without activity. Sign in again to continue.'
            : 'You were signed out because a session may last at most '.$limitMinutes.' minutes. Sign in again to continue.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 401);
        }

        return redirect()->route('sign-in')->with('status', $message);
    }
}
