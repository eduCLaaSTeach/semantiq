<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Enums\BusinessDomain;
use App\Models\User;
use App\Modules\Audit\Support\AuditLogger;
use App\Modules\Identity\Enums\ReviewDecision;
use App\Modules\Identity\Models\AccessReview;
use App\Modules\Identity\Models\AccessReviewItem;
use App\Modules\Identity\Models\AccessRole;
use App\Modules\Identity\Support\OrganisationContext;
use App\Modules\Platform\Enums\LifecycleStatus;
use RuntimeException;

/**
 * The access review workflow. Feature ADM-008.
 *
 * Create -> generate items -> decide -> apply -> audit.
 *
 * Three properties this class is built around.
 *
 * A REVIEW IS A SNAPSHOT. Items are generated once, when the review opens, and
 * each carries the label the grant had at that moment. Regenerating them later
 * would destroy the evidence, so `open()` refuses to run twice.
 *
 * SILENCE IS RECORDED AS SILENCE. An item nobody looked at stays `pending`. It
 * is never treated as an implicit "keep", because a review where half the items
 * were ignored is a finding and folding it into the same shape as a finished
 * one hides that. `complete()` refuses while anything is undecided.
 *
 * DECIDING AND APPLYING ARE DIFFERENT EVENTS. A review can be completed and its
 * revocations never carried out, and that gap is itself worth seeing - hence a
 * separate `applied_at` and a separate `apply()`.
 *
 * A person's PRIMARY TIER is deliberately not reviewable. Changing a tier has
 * invariants of its own, the last System Administrator among them, and a bulk
 * decision screen would route around them.
 */
class AccessReviewService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly UserRegistry $users,
        private readonly OrganisationContext $organisations,
    ) {}

    /**
     * Create a review in draft. No snapshot is taken yet.
     */
    public function create(string $name, ?string $description, ?string $dueAt, User $actor): AccessReview
    {
        $review = new AccessReview;
        $review->forceFill([
            'organisation_id' => $this->organisations->require()->id,
            'name' => $name,
            'description' => $description,
            'due_at' => $dueAt,
            'status' => LifecycleStatus::Draft,
            'created_by_user_id' => $actor->getKey(),
            'updated_by_user_id' => $actor->getKey(),
        ])->save();

        $this->audit->record(
            action: 'access_review.created',
            module: 'Identity',
            resourceType: 'access_review',
            resourceId: $review->getKey(),
            after: ['name' => $name, 'due_at' => $dueAt],
        );

        return $review;
    }

    /**
     * Take the snapshot and open the review for decisions.
     *
     * One item per additional role and per domain entitlement held by anyone in
     * the organisation. Each records the grant's label as it is NOW, so the
     * evidence survives a later rename or revocation.
     *
     * @throws RuntimeException when the review is not a draft.
     */
    public function open(AccessReview $review, User $actor): AccessReview
    {
        if (! $review->isDraft()) {
            /* Re-opening would regenerate the snapshot and destroy the
             * evidence of what access looked like when it was taken. */
            throw new RuntimeException('This review has already been opened. A review is a snapshot and is taken once.');
        }

        $generated = 0;

        foreach ($this->users->query()->with('accessRoles', 'domainEntitlements')->get() as $subject) {
            foreach ($subject->accessRoles as $role) {
                $this->addItem($review, $subject, AccessReviewItem::TYPE_ROLE, (string) $role->getKey(), $role->name.' ('.$role->code.')');
                $generated++;
            }

            foreach ($subject->entitledDomains() as $domain) {
                $this->addItem(
                    $review,
                    $subject,
                    AccessReviewItem::TYPE_ENTITLEMENT,
                    $domain->value,
                    $domain->label().($domain->isSensitive() ? ' (restricted fields)' : ''),
                );
                $generated++;
            }
        }

        $review->forceFill([
            'status' => LifecycleStatus::Open,
            'opened_at' => now(),
            'opened_by_user_id' => $actor->getKey(),
            'updated_by_user_id' => $actor->getKey(),
        ])->save();

        $this->audit->record(
            action: 'access_review.opened',
            module: 'Identity',
            resourceType: 'access_review',
            resourceId: $review->getKey(),
            after: ['items' => $generated],
        );

        return $review;
    }

    /**
     * Record one keep-or-revoke decision.
     *
     * Each decision is saved as it is made and audited on its own, rather than
     * batched at the end. A long review session that is interrupted keeps every
     * decision already made, and the trail says who decided what and when
     * rather than who pressed submit.
     *
     * @throws RuntimeException when the review is not open.
     */
    public function decide(AccessReviewItem $item, ReviewDecision $decision, ?string $note, User $actor): AccessReviewItem
    {
        $review = $item->review;

        if (! $review->isOpen()) {
            throw new RuntimeException('Decisions can only be recorded while a review is open.');
        }

        if ($decision === ReviewDecision::Pending) {
            throw new RuntimeException('"Not decided" is what an item starts as. It cannot be chosen.');
        }

        $before = ['decision' => $item->decision->value];

        $item->forceFill([
            'decision' => $decision,
            'note' => $note,
            'decided_by_user_id' => $actor->getKey(),
            'decided_at' => now(),
        ])->save();

        $this->audit->record(
            action: 'access_review.decided',
            module: 'Identity',
            resourceType: 'access_review_item',
            resourceId: $item->getKey(),
            before: $before,
            after: [
                'decision' => $decision->value,
                'subject' => $item->subject_label,
                'user_id' => $item->user_id,
            ],
            reason: $note,
        );

        return $item;
    }

    /**
     * Close the review.
     *
     * @throws RuntimeException while any item is still undecided.
     */
    public function complete(AccessReview $review, User $actor): AccessReview
    {
        if (! $review->isOpen()) {
            throw new RuntimeException('Only an open review can be completed.');
        }

        $undecided = $review->undecidedCount();

        if ($undecided > 0) {
            throw new RuntimeException(
                $undecided.' item'.($undecided === 1 ? ' is' : 's are').' still undecided. '
                .'A review that records no decision for an item is not evidence that the access was approved.'
            );
        }

        $review->forceFill([
            'status' => LifecycleStatus::Completed,
            'completed_at' => now(),
            'updated_by_user_id' => $actor->getKey(),
        ])->save();

        $this->audit->record(
            action: 'access_review.completed',
            module: 'Identity',
            resourceType: 'access_review',
            resourceId: $review->getKey(),
            after: [
                'items' => $review->items()->count(),
                'revocations' => $review->items()->where('decision', ReviewDecision::Revoke->value)->count(),
            ],
        );

        return $review;
    }

    /**
     * Carry out the revocations the review decided on.
     *
     * Each revocation goes through `UserRegistry`, NOT through a direct delete,
     * so every one of them gets the same checks and the same audit event as a
     * revocation made by hand. A bulk operation that bypasses the rules is how
     * a bulk operation becomes the way around the rules.
     *
     * Safe to run more than once: an item already applied is skipped.
     *
     * @return int how many grants were actually revoked.
     *
     * @throws RuntimeException when the review is not completed.
     */
    public function apply(AccessReview $review, User $actor): int
    {
        if (! $review->isCompleted()) {
            throw new RuntimeException('Only a completed review can be applied.');
        }

        $applied = 0;

        $items = $review->items()
            ->where('decision', ReviewDecision::Revoke->value)
            ->where('applied', false)
            ->with('user')
            ->get();

        foreach ($items as $item) {
            $subject = $item->user;

            if ($subject === null) {
                /* The account was deleted between the decision and the apply.
                 * The grant is gone with it, so the item is settled - and the
                 * review's own evidence still records what was decided. */
                $item->forceFill(['applied' => true])->save();

                continue;
            }

            if ($item->isRole()) {
                $role = AccessRole::query()->find($item->subject_key);

                if ($role !== null) {
                    $this->users->removeRole($subject, $role, $actor, 'Revoked by access review "'.$review->name.'".');
                }
            }

            if ($item->isEntitlement()) {
                $domain = $item->domain();

                if ($domain instanceof BusinessDomain) {
                    $this->users->revokeEntitlement($subject, $domain, $actor, 'Revoked by access review "'.$review->name.'".');
                }
            }

            $item->forceFill(['applied' => true])->save();
            $applied++;
        }

        $review->forceFill([
            'applied_at' => now(),
            'updated_by_user_id' => $actor->getKey(),
        ])->save();

        $this->audit->record(
            action: 'access_review.applied',
            module: 'Identity',
            resourceType: 'access_review',
            resourceId: $review->getKey(),
            after: ['revoked' => $applied],
        );

        return $applied;
    }

    /**
     * Cancel a review that is not going to be finished.
     *
     * Kept rather than deleted. "We started a review and abandoned it" is
     * information an auditor is entitled to, and deleting the row would remove
     * it.
     */
    public function cancel(AccessReview $review, User $actor, ?string $reason = null): AccessReview
    {
        if ($review->isCompleted()) {
            throw new RuntimeException('A completed review cannot be cancelled. Its decisions are evidence.');
        }

        $review->forceFill([
            'status' => LifecycleStatus::Cancelled,
            'updated_by_user_id' => $actor->getKey(),
        ])->save();

        $this->audit->record(
            action: 'access_review.cancelled',
            module: 'Identity',
            resourceType: 'access_review',
            resourceId: $review->getKey(),
            reason: $reason,
        );

        return $review;
    }

    /**
     * Add one item, unless the same grant is already in this review.
     */
    private function addItem(AccessReview $review, User $subject, string $type, string $key, string $label): void
    {
        $exists = AccessReviewItem::query()
            ->where('access_review_id', $review->getKey())
            ->where('user_id', $subject->getKey())
            ->where('subject_type', $type)
            ->where('subject_key', $key)
            ->exists();

        if ($exists) {
            return;
        }

        $item = new AccessReviewItem;
        $item->forceFill([
            'access_review_id' => $review->getKey(),
            'user_id' => $subject->getKey(),
            'subject_type' => $type,
            'subject_key' => $key,
            'subject_label' => $label,
            'decision' => ReviewDecision::Pending,
            'applied' => false,
        ])->save();
    }
}
