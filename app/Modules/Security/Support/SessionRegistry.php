<?php

declare(strict_types=1);

namespace App\Modules\Security\Support;

use App\Models\User;
use App\Modules\Audit\Support\AuditLogger;
use App\Modules\Security\Enums\ConcurrentSessionPolicy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Lists and ends a person's live sessions. Feature ADM-010.
 *
 * WHAT THIS CLASS CAN DO DEPENDS ON THE SESSION DRIVER, and that is the single
 * most important thing about it. `SESSION_DRIVER=file` stores each session as
 * an opaque blob named after its own id, with no index by user, so there is no
 * query that answers "which sessions belong to this person". The `database`
 * driver writes a `user_id` column and every capability here follows from that
 * one column.
 *
 * Production runs `file` at the time of writing. Decision D3, approved
 * 25 August 2026: build the capability now, detect the driver at runtime, and
 * report it as unavailable rather than half-working. Nothing in this gate
 * changes the production environment.
 *
 * EVERY METHOD IS SAFE TO CALL WHATEVER THE DRIVER IS. `liveFor()` returns an
 * empty list and `revokeAllFor()` refuses, rather than throwing, so a caller
 * cannot accidentally depend on a capability that is not there. Callers that
 * need to TELL somebody why ask `SecurityCapabilities` first, and the screens
 * do exactly that.
 */
class SessionRegistry
{
    public function __construct(
        private readonly SecurityCapabilities $capabilities,
        private readonly SecurityPolicies $policies,
        private readonly AuditLogger $audit,
    ) {}

    /** Whether anything on this class can actually do its job here. */
    public function isAvailable(): bool
    {
        return $this->capabilities->canEnumerateSessions();
    }

    /**
     * A person's live sessions, most recently active first.
     *
     * The PAYLOAD IS NEVER READ. It is a serialised blob that can contain
     * anything the framework put there, and this class only needs four columns
     * that sit beside it. Deserialising a session payload to display it would
     * be reading untrusted data for no reason.
     *
     * @return list<array{id: string, ip_address: string|null, user_agent: string|null, last_active_at: Carbon, is_current: bool}>
     */
    public function liveFor(User $user, ?string $currentSessionId = null): array
    {
        if (! $this->isAvailable()) {
            return [];
        }

        $rows = DB::connection(config('session.connection'))
            ->table((string) config('session.table', 'sessions'))
            ->where('user_id', $user->getKey())
            ->orderByDesc('last_activity')
            ->get(['id', 'ip_address', 'user_agent', 'last_activity']);

        return $rows->map(fn ($row): array => [
            'id' => (string) $row->id,
            'ip_address' => $row->ip_address === null ? null : (string) $row->ip_address,
            /* Truncated: a user agent is long, attacker-controlled and going
             * onto a page. The first 120 characters identify the browser. */
            'user_agent' => $row->user_agent === null ? null : mb_substr((string) $row->user_agent, 0, 120),
            'last_active_at' => Carbon::createFromTimestamp((int) $row->last_activity),
            'is_current' => $currentSessionId !== null && hash_equals((string) $row->id, $currentSessionId),
        ])->all();
    }

    /** How many live sessions a person has. Zero when unavailable. */
    public function countFor(User $user): int
    {
        return count($this->liveFor($user));
    }

    /**
     * End every session belonging to a person.
     *
     * Returns how many were ended, or null when the driver cannot do it. Null
     * rather than zero: "we ended none" and "we cannot end any" are different
     * answers, and a caller that treats the second as the first reports success
     * for something that did not happen.
     */
    public function revokeAllFor(User $user, ?string $exceptSessionId = null): ?int
    {
        if (! $this->isAvailable()) {
            $this->audit->denied(
                action: 'security.sessions.revoked',
                module: 'Security',
                resourceType: 'user',
                resourceId: $user->getKey(),
                reason: 'The session driver in force cannot enumerate sessions, so none could be ended.',
            );

            return null;
        }

        if (! $this->policies->enabled('activity.revocation_enabled')) {
            $this->audit->denied(
                action: 'security.sessions.revoked',
                module: 'Security',
                resourceType: 'user',
                resourceId: $user->getKey(),
                reason: 'Administrator-initiated session revocation is turned off by policy.',
            );

            return null;
        }

        $query = DB::connection(config('session.connection'))
            ->table((string) config('session.table', 'sessions'))
            ->where('user_id', $user->getKey());

        if ($exceptSessionId !== null) {
            $query->where('id', '!=', $exceptSessionId);
        }

        $ended = $query->delete();

        $this->audit->record(
            action: 'security.sessions.revoked',
            module: 'Security',
            resourceType: 'user',
            resourceId: $user->getKey(),
            /* `ended_count`, not `sessions_ended`. Redaction::summarise()
             * replaces the value of any key containing "session", so the
             * obvious name would have recorded "[redacted]" instead of the
             * number - found by a test, not by review. SEC-DEC-044. */
            after: ['ended_count' => $ended],
            reason: 'An administrator ended this account\'s signed-in sessions.',
        );

        return $ended;
    }

    /**
     * Apply the concurrent session policy after a new sign-in.
     *
     * Ends the OLDEST sessions rather than refusing the new one. Refusing would
     * mean somebody whose old session is stuck on a machine they no longer have
     * can never sign in again, which turns a policy into a lockout. Ending the
     * oldest keeps the person working and still holds the limit.
     *
     * Returns how many were ended; zero when the policy is unlimited, when the
     * driver cannot enumerate, or when the limit is not exceeded.
     */
    public function applyConcurrencyLimit(User $user, string $currentSessionId): int
    {
        if (! $this->isAvailable()) {
            return 0;
        }

        $policy = ConcurrentSessionPolicy::tryFrom($this->policies->text('activity.concurrent_policy'))
            ?? ConcurrentSessionPolicy::Unlimited;

        if (! $policy->requiresSessionEnumeration()) {
            return 0;
        }

        $limit = $policy === ConcurrentSessionPolicy::Single
            ? 1
            : max(1, $this->policies->number('activity.concurrent_limit'));

        /* Oldest first, so the ones taken off the end are the stale ones. The
         * session just created is excluded outright: ending it would sign the
         * person out of the sign-in they have just completed. */
        $others = DB::connection(config('session.connection'))
            ->table((string) config('session.table', 'sessions'))
            ->where('user_id', $user->getKey())
            ->where('id', '!=', $currentSessionId)
            ->orderBy('last_activity')
            ->pluck('id')
            ->all();

        $surplus = count($others) + 1 - $limit;

        if ($surplus <= 0) {
            return 0;
        }

        $doomed = array_slice($others, 0, $surplus);

        DB::connection(config('session.connection'))
            ->table((string) config('session.table', 'sessions'))
            ->whereIn('id', $doomed)
            ->delete();

        $this->audit->record(
            action: 'security.sessions.limited',
            module: 'Security',
            resourceType: 'user',
            resourceId: $user->getKey(),
            /* Same reason as above: the key must not contain "session". */
            after: ['ended_count' => count($doomed), 'limit' => $limit],
            reason: 'The concurrent session policy ended the oldest sessions for this account.',
        );

        return count($doomed);
    }
}
