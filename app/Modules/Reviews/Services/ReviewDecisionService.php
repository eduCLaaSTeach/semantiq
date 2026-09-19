<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Services;

use App\Modules\Access\Models\DomainEntitlement;
use App\Modules\Access\Models\RoleAssignment;
use App\Modules\Access\Services\EntitlementService;
use App\Modules\Access\Services\RoleAssignmentService;
use App\Modules\Platform\Models\User;
use App\Modules\Platform\Security\SecurityEventLogger;
use App\Modules\Reviews\Models\AccessReviewItem;
use App\Modules\Reviews\Support\Composition;
use App\Modules\Reviews\Support\DecisionBasis;
use App\Modules\Reviews\Support\ReviewDecision;
use App\Modules\Reviews\Support\ReviewKind;
use App\Modules\Reviews\Support\ReviewState;
use App\Modules\Reviews\Support\ReviewViolation;
use App\Modules\Reviews\Support\SupersededReason;
use Illuminate\Support\Facades\DB;

/**
 * One decision, one transaction. DESIGN §6.1.
 *
 * RETAIN WRITES NOTHING TO THE ACCESS MODEL. Not a row, not an updated_at. That
 * is why a review can never revive stale access: a thing that writes nothing
 * cannot revive anything. RetainWritesNothingTest asserts the subject's access
 * rows are BYTE-IDENTICAL before and after, because a weaker assertion - "no
 * new entitlement appeared" - would pass even if retain quietly extended a
 * period or reset a ceiling.
 *
 * REVOKE CALLS P1-05'S SERVICES AND CONTAINS NO ACCESS-ENDING LOGIC OF ITS OWN.
 * A second implementation of revocation is a second interpretation of access,
 * and this unit's whole premise is that there is one. Every P1-05 refusal -
 * the administrator floor, entitlement currency, the grantableBy allow-list -
 * passes through unchanged.
 */
final class ReviewDecisionService
{
    public function __construct(
        private readonly ReviewerAuthority $authority,
        private readonly RoleAssignmentService $roles,
        private readonly EntitlementService $entitlements,
        private readonly SecurityEventLogger $events,
    ) {}

    /**
     * @throws ReviewViolation
     */
    public function decide(AccessReviewItem $item, ReviewDecision $decision, User $actor): AccessReviewItem
    {
        return DB::transaction(function () use ($item, $decision, $actor): AccessReviewItem {
            /** @var AccessReviewItem $locked */
            $locked = AccessReviewItem::query()
                ->whereKey($item->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            /*
             * ALREADY-DECIDED IS CHECKED BEFORE AUTHORITY, deliberately. A
             * decided item refuses identically whoever asks, so the refusal is
             * not an oracle for who may review what.
             */
            if ($locked->state->isTerminal()) {
                $this->refuse($locked, $actor, 'already_decided');

                throw ReviewViolation::alreadyDecided();
            }

            $basis = $this->authority->basisFor($actor, $locked);

            if ($basis === null) {
                $this->refuse($locked, $actor, 'not_permitted');

                throw ReviewViolation::notPermitted();
            }

            $reason = $this->supersedeReason($locked);

            if ($reason !== null) {
                return $this->supersede($locked, $reason, $actor);
            }

            if ($decision === ReviewDecision::Revoke) {
                $this->revoke($locked, $actor);
            }

            $locked->forceFill([
                'state' => $decision->resultingState(),
                'decided_at' => now(),
                'decided_by_user_id' => $actor->getKey(),
                'decision_basis' => $basis,
                'self_review' => $basis === DecisionBasis::SelfReview,
            ])->save();

            $this->recordDecision($locked, $decision, $actor, $basis);

            return $locked;
        });
    }

    /**
     * Is the item still exactly what was reviewed? D-93, STRICT.
     *
     * A reviewer attested to a composition, so a changed composition is a
     * different fact. Without the fingerprint comparison somebody could widen a
     * grant while its review was open and have the review approve the widened
     * version - the reviewer's attestation would cover access they never saw.
     *
     * Called at render for honesty and again INSIDE the transaction after
     * locking, which is the authoritative one.
     */
    public function supersedeReason(AccessReviewItem $item): ?SupersededReason
    {
        if ($item->kind === ReviewKind::Privileged) {
            $assignment = RoleAssignment::query()->find($item->role_assignment_id);

            return ($assignment === null || $assignment->ended_at !== null)
                ? SupersededReason::ObjectEnded
                : null;
        }

        $entitlement = DomainEntitlement::query()->with(['assignment', 'scopes', 'ceilings'])->find($item->domain_entitlement_id);

        if ($entitlement === null || $entitlement->ended_at !== null) {
            return SupersededReason::ObjectEnded;
        }

        if ($entitlement->assignment === null || $entitlement->assignment->ended_at !== null) {
            return SupersededReason::SubjectRoleEnded;
        }

        $live = Composition::fingerprint(Composition::ofEntitlement($entitlement));

        return hash_equals($item->composition_fingerprint, $live) ? null : SupersededReason::CompositionChanged;
    }

    /**
     * PUBLIC, SO IT TAKES ITS OWN BOUNDARY. D-111.
     *
     * decide() already calls this inside a transaction, where this becomes a
     * savepoint and costs nothing. But a public method is reachable from
     * anywhere, and "it happens to be called from inside one today" is not a
     * guarantee - it is a comment somebody deletes. The static atomicity guard
     * found this one; no test exercised the unwrapped path, so the runtime
     * guard never saw it.
     */
    public function supersede(AccessReviewItem $item, SupersededReason $reason, ?User $actor = null): AccessReviewItem
    {
        return DB::transaction(fn (): AccessReviewItem => $this->applySupersede($item, $reason, $actor));
    }

    private function applySupersede(AccessReviewItem $item, SupersededReason $reason, ?User $actor): AccessReviewItem
    {
        $item->forceFill([
            'state' => ReviewState::Superseded,
            'superseded_reason' => $reason,
            'decided_at' => now(),
        ])->save();

        $this->events->record(SecurityEventLogger::REVIEW_ITEM_SUPERSEDED, [
            'user_id' => $item->subject_user_id,
            'related_id' => $actor?->getKey(),
            'organisation_id' => $item->organisation_id,
            'entity_id' => $item->getKey(),
            'reason' => $reason->value,
            'result' => 'superseded',
        ]);

        return $item;
    }

    /** The reviewed object is locked before anything is ended. */
    private function revoke(AccessReviewItem $item, User $actor): void
    {
        if ($item->kind === ReviewKind::Privileged) {
            $assignment = RoleAssignment::query()
                ->whereKey($item->role_assignment_id)
                ->lockForUpdate()
                ->firstOrFail();

            // D-91: ends the assignment AND every current child, which is what
            // P1-05 already defines revoking a role to mean.
            $this->roles->revoke($assignment, $actor);

            return;
        }

        $entitlement = DomainEntitlement::query()
            ->whereKey($item->domain_entitlement_id)
            ->lockForUpdate()
            ->firstOrFail();

        // D-91: ends THIS entitlement and its children only. A sibling
        // entitlement under another assignment is untouched by construction.
        $this->entitlements->revoke($entitlement, $actor);
    }

    private function recordDecision(
        AccessReviewItem $item,
        ReviewDecision $decision,
        User $actor,
        DecisionBasis $basis,
    ): void {
        $context = [
            'user_id' => $item->subject_user_id,
            'related_id' => $actor->getKey(),
            'organisation_id' => $item->organisation_id,
            'entity_id' => $item->getKey(),
            'role' => $item->role_code?->value,
            'domain_id' => $item->business_domain_id,
            'result' => $decision->value,
        ];

        /*
         * D-88 / D-94. Self-review emits its OWN key instead of the ordinary
         * one. Evidence that needs a join to spot is evidence nobody spots.
         */
        $event = $basis === DecisionBasis::SelfReview
            ? SecurityEventLogger::REVIEW_ITEM_SELF_REVIEWED
            : ($decision === ReviewDecision::Retain
                ? SecurityEventLogger::REVIEW_ITEM_RETAINED
                : SecurityEventLogger::REVIEW_ITEM_REVOKED);

        $this->events->record($event, $context);
    }

    /**
     * THE ONE REVIEW-REFUSAL EVIDENCE CHANNEL. One key, a reason that names
     * which rule refused, and no second key for any new kind of refusal.
     *
     * Public because a refusal can also arrive from P1-05 - the administrator
     * floor being the one the Product Owner meets - after the decision has
     * left this service, and that refusal is still a refused review. Recording
     * it under a key of its own would split the evidence for one question
     * ("what was refused, and why?") across two places.
     *
     * @param  string  $reason  this service's own vocabulary, or the
     *                          AccessViolation reason P1-05 refused with.
     */
    public function refuse(AccessReviewItem $item, User $actor, string $reason): void
    {
        $this->events->record(SecurityEventLogger::REVIEW_REFUSED, [
            'user_id' => $item->subject_user_id,
            'related_id' => $actor->getKey(),
            'organisation_id' => $item->organisation_id,
            'entity_id' => $item->getKey(),
            'reason' => $reason,
            'result' => 'refused',
        ]);
    }
}
