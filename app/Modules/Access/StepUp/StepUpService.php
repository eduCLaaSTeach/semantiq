<?php

declare(strict_types=1);

namespace App\Modules\Access\StepUp;

use App\Modules\Access\Support\AccessViolation;
use App\Modules\Platform\Models\User;
use App\Modules\Platform\Security\SecurityEventLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * D-73 step-up re-authentication.
 *
 * ONE STEP-UP AUTHORISES ONE ACTION, ONCE. There is deliberately no time window
 * in which everything is privileged: somebody who has just signed in, or who
 * has just completed a step-up FOR A DIFFERENT ACTION, must step up again.
 *
 * THE ACTION LIVES SERVER-SIDE. Nothing about it travels in the URL - a URL
 * somebody can edit is an action somebody can substitute. The redirect carries
 * an opaque reference; the target, the role, the domain and the level are read
 * back from the row.
 *
 * FAILING FORWARD IS THE DEFECT. A provider error, a cancellation or an expiry
 * performs no action AND consumes the reference. Leaving it alive "so the user
 * can try again" converts a cancelled step-up into a reusable one - and it is
 * exactly the change somebody makes to improve the experience.
 */
final class StepUpService
{
    /**
     * The provider's clock and this server's clock are not the same clock. This
     * allows for that difference on the "not before the request" test only, and
     * is deliberately small - a generous skew allowance is a generous replay
     * window.
     */
    private const CLOCK_SKEW_SECONDS = 5;

    public function __construct(private readonly SecurityEventLogger $events) {}

    /**
     * Store the intended action and return the plaintext reference for the
     * redirect. The plaintext is never written anywhere.
     *
     * @param  array<string, int|string|null>  $target
     */
    public function begin(User $actor, string $sessionId, StepUpAction $action, array $target): string
    {
        $reference = PendingStepUp::newReference();
        $now = now();

        PendingStepUp::query()->create([
            'reference_hash' => PendingStepUp::hashFor($reference),
            'user_id' => $actor->getKey(),
            'session_id' => $sessionId,
            'action' => $action,
            'subject_user_id' => $target['subject_user_id'] ?? null,
            'role_assignment_id' => $target['role_assignment_id'] ?? null,
            'business_domain_id' => $target['business_domain_id'] ?? null,
            'domain_entitlement_id' => $target['domain_entitlement_id'] ?? null,
            'role_code' => $target['role_code'] ?? null,
            'sensitivity' => $target['sensitivity'] ?? null,
            'organisation_id' => $target['organisation_id'] ?? null,
            'requested_at' => $now,
            'expires_at' => $now->copy()->addMinutes(PendingStepUp::LIFETIME_MINUTES),
        ]);

        $this->events->record(SecurityEventLogger::STEP_UP_REQUESTED, [
            'user_id' => $actor->getKey(),
            'reason' => $action->value,
            'result' => 'requested',
        ]);

        return $reference;
    }

    /**
     * Find the pending action for a returning reference, WITHOUT consuming it.
     *
     * Every binding is checked here: the reference must match a row, the row
     * must belong to this user, to this session, and must not have expired or
     * been consumed. A failure at any of them refuses AND consumes, so a
     * mismatch cannot be retried against a different browser or a different
     * person.
     */
    public function resolve(string $reference, User $actor, string $sessionId): PendingStepUp
    {
        $pending = PendingStepUp::query()
            ->where('reference_hash', PendingStepUp::hashFor($reference))
            ->first();

        if ($pending === null) {
            $this->refuse('unknown_reference', $actor);
        }

        if ($pending->consumed_at !== null) {
            // A replay. Refused AND logged - refusing silently would leave no
            // trace of the attempt.
            $this->refuse('replayed', $actor, $pending);
        }

        if ($pending->user_id !== $actor->getKey()) {
            $this->finish($pending, 'user_mismatch');
            $this->refuse('user_mismatch', $actor, $pending);
        }

        if (! hash_equals($pending->session_id, $sessionId)) {
            $this->finish($pending, 'session_mismatch');
            $this->refuse('session_mismatch', $actor, $pending);
        }

        if ($pending->expires_at->isPast()) {
            $this->finish($pending, 'expired');
            $this->refuse('expired', $actor, $pending);
        }

        return $pending;
    }

    /**
     * The freshness proof, from the PROVIDER.
     *
     * auth_time must exist, must be at or after the moment the step-up was
     * requested, and must be within the approved tolerance. A missing claim
     * fails closed rather than being treated as acceptable, and a stale one is
     * a SECURITY EVENT because it is the replay-shaped case.
     */
    public function verifyFreshness(PendingStepUp $pending, ?Carbon $authenticatedAt, User $actor): void
    {
        if ($this->isFresh($pending, $authenticatedAt)) {
            return;
        }

        $this->finish($pending, 'stale_freshness');
        $this->refuse('stale_freshness', $actor, $pending);
    }

    /**
     * Three conditions, each of which fails closed on its own.
     *
     * The skew allowance applies ONLY to the "not before the request" test, and
     * is seconds rather than minutes. It exists because the provider's clock and
     * this server's clock are not the same clock, not to widen the window.
     */
    private function isFresh(PendingStepUp $pending, ?Carbon $authenticatedAt): bool
    {
        // 1. Absent where required. Never treated as "probably fine".
        if ($authenticatedAt === null) {
            return false;
        }

        // 2. Predates the step-up request. An auth_time from the original
        //    sign-in would otherwise satisfy a step-up requested an hour later,
        //    which is the whole thing prompt=login exists to prevent.
        if ($authenticatedAt->lt($pending->requested_at->copy()->subSeconds(self::CLOCK_SKEW_SECONDS))) {
            return false;
        }

        // 3. Outside the approved tolerance. Allows for the round trip, and
        //    nothing more.
        return $authenticatedAt->diffInSeconds(now(), absolute: true)
            <= PendingStepUp::FRESHNESS_TOLERANCE_SECONDS;
    }

    /**
     * Consume the reference INSIDE the transaction that performs the privileged
     * write, and run that write.
     *
     * The guard is in the WHERE clause, not in PHP. Exactly one row must be
     * affected; zero means another request won the race, and the whole
     * transaction - including the privileged write - rolls back. This is the
     * single-row conditional UPDATE GrantRedeemer already proves in production.
     *
     * @template T
     *
     * @param  callable(PendingStepUp): T  $write
     * @return T
     */
    public function consumeAndPerform(PendingStepUp $pending, User $actor, callable $write): mixed
    {
        return DB::transaction(function () use ($pending, $actor, $write): mixed {
            $consumed = PendingStepUp::query()
                ->whereKey($pending->getKey())
                ->whereNull('consumed_at')
                ->where('expires_at', '>', now())
                ->update(['consumed_at' => now(), 'outcome' => 'completed', 'updated_at' => now()]);

            if ($consumed !== 1) {
                throw AccessViolation::stepUpInvalid();
            }

            $result = $write($pending);

            $this->events->record(SecurityEventLogger::STEP_UP_COMPLETED, [
                'user_id' => $actor->getKey(),
                'reason' => $pending->action->value,
                'entity_id' => $pending->getKey(),
                'result' => 'completed',
            ]);

            return $result;
        });
    }

    /**
     * The user cancelled at Microsoft, or the provider returned an error.
     *
     * No action, and the reference is CONSUMED.
     */
    public function abandon(PendingStepUp $pending, string $outcome, User $actor): void
    {
        $this->finish($pending, $outcome);

        $this->events->record(SecurityEventLogger::STEP_UP_REFUSED, [
            'user_id' => $actor->getKey(),
            'reason' => $outcome,
            'entity_id' => $pending->getKey(),
            'result' => 'refused',
        ]);
    }

    private function finish(PendingStepUp $pending, string $outcome): void
    {
        PendingStepUp::query()
            ->whereKey($pending->getKey())
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now(), 'outcome' => $outcome, 'updated_at' => now()]);
    }

    private function refuse(string $reason, User $actor, ?PendingStepUp $pending = null): never
    {
        $context = [
            'user_id' => $actor->getKey(),
            'reason' => $reason,
            'result' => 'refused',
        ];

        if ($pending !== null) {
            $context['entity_id'] = $pending->getKey();
        }

        $this->events->record(SecurityEventLogger::STEP_UP_REFUSED, $context);

        throw AccessViolation::stepUpInvalid();
    }
}
