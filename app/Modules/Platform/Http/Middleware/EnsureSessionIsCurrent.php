<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Middleware;

use App\Modules\Platform\Models\User;
use App\Modules\Platform\Security\SecurityEventLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Deny by default, still. P1-00 replaces the resolution step, not the default.
 *
 * Three checks run before any protected functionality is served:
 *
 *  - a session exists and carries a user id;
 *  - it is within the absolute lifetime. Laravel has no absolute lifetime, only
 *    a rolling idle one, so a user active every 59 minutes would otherwise stay
 *    signed in forever. authenticated_at is written once at issuance and never
 *    refreshed;
 *  - the user still exists and is still active.
 *
 * The active-user check is deliberately uncached. Caching it would reintroduce
 * exactly the window D-10 closes: "next protected request" has to mean this
 * request, not the one after the cache expires.
 */
final class EnsureSessionIsCurrent
{
    public const SESSION_USER_ID = 'auth.user_id';

    public const SESSION_AUTHENTICATED_AT = 'auth.authenticated_at';

    public const IDLE_MINUTES = 60;

    public const ABSOLUTE_HOURS = 12;

    public function __construct(private readonly SecurityEventLogger $events) {}

    public function handle(Request $request, Closure $next): Response
    {
        $userId = $request->session()->get(self::SESSION_USER_ID);

        // Never had a session: this is an ordinary unauthenticated visitor, and
        // the design's normal-login journey sends them to the Login page. Telling
        // them their session expired would be both untrue and a hint that they
        // once had one.
        if (! is_int($userId) && ! is_string($userId)) {
            return $this->refuseAnonymous($request);
        }

        if ($this->beyondAbsoluteLifetime($request)) {
            $this->events->record(SecurityEventLogger::SESSION_EXPIRED, [
                'user_id' => (int) $userId,
                'result' => 'refused',
                'reason' => 'absolute_lifetime',
            ]);

            return $this->invalidateAndRefuse($request, 'session-expired');
        }

        $user = User::query()->find($userId);

        if ($user === null) {
            return $this->invalidateAndRefuse($request, 'session-expired');
        }

        if (! $user->isActive()) {
            $this->events->record(SecurityEventLogger::LOGIN_REFUSED_INACTIVE, [
                'user_id' => $user->id,
                'result' => 'refused',
                'reason' => 'inactive_on_request',
            ]);

            return $this->invalidateAndRefuse($request, 'account-inactive');
        }

        $request->attributes->set('semantiq_user', $user);

        return $next($request);
    }

    private function beyondAbsoluteLifetime(Request $request): bool
    {
        $authenticatedAt = $request->session()->get(self::SESSION_AUTHENTICATED_AT);

        if (! is_string($authenticatedAt)) {
            return true;
        }

        return Carbon::parse($authenticatedAt)->addHours(self::ABSOLUTE_HOURS)->isPast();
    }

    private function invalidateAndRefuse(Request $request, string $state): Response
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $this->refuse($request, $state);
    }

    /**
     * The refusal carries no hint about what was requested or whether it exists.
     */
    private function refuse(Request $request, string $state): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return redirect()->route("auth.{$state}");
    }

    private function refuseAnonymous(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return redirect()->route('entry');
    }
}
