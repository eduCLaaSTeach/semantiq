<?php

declare(strict_types=1);

namespace App\Modules\Reviews\StepUp;

use App\Modules\Access\StepUp\PendingStepUp;
use App\Modules\Access\StepUp\StepUpAction;
use App\Modules\Access\StepUp\StepUpCompletion;
use App\Modules\Access\Support\AccessViolation;
use App\Modules\Platform\Models\User;
use App\Modules\Reviews\Models\AccessReviewItem;
use App\Modules\Reviews\Services\ReviewDecisionService;
use App\Modules\Reviews\Support\ReviewDecision;
use App\Modules\Reviews\Support\ReviewKind;
use App\Modules\Reviews\Support\ReviewViolation;
use Illuminate\Http\RedirectResponse;

/**
 * P1-07's half of a confirmed review decision.
 *
 * ONE STEP-UP AUTHORISES ONE EXACT ITEM, ONE EXACT DECISION, ONCE.
 *
 * Both halves come off the STORED row and nothing else:
 *
 *   subject_id      the exact review item, bound when the confirmation began
 *   subject_intent  the exact decision, bound at the same moment
 *
 * The first implementation held the decision in a mutable column on the item
 * and found the item again by the id of the ACCESS OBJECT. Two real failures
 * followed. A second tab could change the decision while the first confirmation
 * was away, so the returning step-up executed an intent nobody confirmed. And
 * if the reviewed item became terminal while a LATER review existed for the
 * same access object, the callback attached itself to the later one -
 * authorising a decision on a review the person never saw.
 *
 * Neither is reachable now: there is nothing mutable to change, and the item is
 * addressed by id rather than searched for.
 */
final class ReviewStepUpCompletion implements StepUpCompletion
{
    public const SUBJECT_TYPE = 'access_review_item';

    public function __construct(private readonly ReviewDecisionService $decisions) {}

    public function handles(StepUpAction $action): bool
    {
        return $action->isReviewDecision();
    }

    public function complete(PendingStepUp $pending, User $actor): RedirectResponse
    {
        if ($pending->subject_type !== self::SUBJECT_TYPE || $pending->subject_id === null) {
            throw AccessViolation::stepUpInvalid();
        }

        $decision = ReviewDecision::tryFrom((string) $pending->subject_intent);

        if ($decision === null) {
            throw AccessViolation::stepUpInvalid();
        }

        // Addressed by id. NOT searched for by the access object it is about.
        $item = AccessReviewItem::query()->find($pending->subject_id);

        if ($item === null) {
            throw AccessViolation::stepUpInvalid();
        }

        try {
            /*
             * EVERYTHING IS RE-CHECKED HERE, not merely at the confirmation.
             * The reviewer has been away at Microsoft: the item may have been
             * decided by somebody else, their authority may have been removed,
             * and the access may have changed underneath it. The decision
             * service re-runs all three under its own locks, and a terminal
             * item refuses rather than being decided twice.
             */
            $this->decisions->decide($item, $decision, $actor);
        } catch (ReviewViolation) {
            // The confirmation was genuine; the review moved underneath it. The
            // reference is consumed either way - this runs inside the
            // transaction that consumed it - so nothing can be retried.
            throw AccessViolation::stepUpInvalid();
        } catch (AccessViolation $violation) {
            return $this->refused($item, $actor, $violation);
        }

        return $this->originatingScreen($item)
            ->with('confirmation', $decision === ReviewDecision::Retain
                ? 'Access confirmed. Nothing about the access was changed.'
                : 'Access removed. It ended at that moment.');
    }

    /**
     * P1-05 REFUSED THE REMOVAL. The review is not decided, and the person is
     * standing in Access Reviews.
     *
     * The Product Owner met this as the last-administrator floor: removing the
     * only System Administrator from a review left them on ROLES & ACCESS,
     * reading a refusal about a screen they had not been on, with no sign of
     * what had become of the review. The destination was never the review's -
     * it was P1-05's own refuseToIndex(), which is correct for an action begun
     * in Roles & Access and wrong for one begun here, so the correction belongs
     * on this side of the boundary and P1-05's own destination is untouched.
     *
     * FOUR FACTS, ALL OF THEM DELIBERATE:
     *
     *  - THE ACCESS IS UNCHANGED. decide() runs in its own transaction, so a
     *    refusal from inside it rolls back to its savepoint. Nothing was ended.
     *  - THE ITEM IS STILL PENDING. It is written only AFTER the revoke, which
     *    is what threw - so there is nothing to undo and nothing to mark. It is
     *    not retained, not revoked and not superseded: none of those happened.
     *  - THE STEP-UP IS SPENT. RETURNING rather than throwing lets the
     *    surrounding transaction commit the consumption. One confirmation
     *    authorises one attempt, and a refused attempt was still the attempt -
     *    rethrowing here would hand back a reusable reference, which is exactly
     *    the "be helpful" change D-73 exists to refuse.
     *  - THE REFUSAL IS P1-05'S OWN SENTENCE, shown on the review screen. This
     *    unit does not paraphrase the administrator floor; a second wording of
     *    a rule is a second rule.
     */
    private function refused(AccessReviewItem $item, User $actor, AccessViolation $violation): RedirectResponse
    {
        // The EXISTING review-refusal evidence, carrying P1-05's reason. No
        // second event key: "which review was refused, and why" is one question
        // and answering it from two keys is answering it from neither.
        $this->decisions->refuse($item, $actor, $violation->reason);

        return $this->originatingScreen($item)->with('refusal', $violation->getMessage());
    }

    /** The tab the confirmation was begun from - never Roles & Access. */
    private function originatingScreen(AccessReviewItem $item): RedirectResponse
    {
        return redirect()->route(
            $item->kind === ReviewKind::Privileged ? 'access-reviews.privileged' : 'access-reviews.domains'
        );
    }
}
