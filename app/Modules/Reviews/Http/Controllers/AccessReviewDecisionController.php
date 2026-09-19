<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Http\Controllers;

use App\Modules\Access\StepUp\StepUpAction;
use App\Modules\Access\StepUp\StepUpService;
use App\Modules\Access\Support\RoleCatalogue;
use App\Modules\Access\Support\RoleCode;
use App\Modules\Access\Support\Sensitivity;
use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;
use App\Modules\Platform\Models\User;
use App\Modules\Reviews\Models\AccessReviewCycle;
use App\Modules\Reviews\Models\AccessReviewItem;
use App\Modules\Reviews\Services\ReviewCycleGenerator;
use App\Modules\Reviews\Services\ReviewDecisionService;
use App\Modules\Reviews\Services\ReviewerAuthority;
use App\Modules\Reviews\StepUp\ReviewStepUpCompletion;
use App\Modules\Reviews\Support\DecisionBasis;
use App\Modules\Reviews\Support\ReviewDecision;
use App\Modules\Reviews\Support\ReviewKind;
use App\Modules\Reviews\Support\ReviewState;
use App\Modules\Reviews\Support\ReviewViolation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Writes: start a cycle, and decide one item.
 *
 * THE STEP-UP REQUIREMENT IS A PROPERTY OF THE DECISION, NOT OF THIS SCREEN.
 * Revoking a System Administrator already required a fresh sign-in in P1-05; a
 * controller that called RoleAssignmentService::revoke() without one would be a
 * step-up bypass for the most privileged action in the product - and it would
 * arrive from correct-looking RE-USE of accepted code rather than from anything
 * new. stepUpActionFor() computes it from the item, so no screen can forget it,
 * and ReviewRevokeRequiresStepUpTest asserts the ENFORCEMENT rather than the
 * wiring: it posts a System Administrator revoke with no resolved step-up and
 * asserts the assignment is still current.
 */
final class AccessReviewDecisionController
{
    public function __construct(
        private readonly ReviewerAuthority $authority,
        private readonly ReviewDecisionService $decisions,
        private readonly ReviewCycleGenerator $generator,
        private readonly StepUpService $stepUp,
    ) {}

    /** Deliberately started, never scheduled - D-89, and nothing here runs on a timer. */
    public function startCycle(Request $request): RedirectResponse
    {
        $actor = $this->actor($request);

        if (! $this->isSystemAdministrator($actor)) {
            return $this->refuse(ReviewViolation::notPermitted());
        }

        $organisationId = $request->attributes->get('semantiq_organisation')?->id;

        /*
         * ONE OPEN CYCLE AT A TIME.
         *
         * Generation already refuses to raise a second item for a grant that
         * has one pending, so an accidental second cycle produced an EMPTY
         * cycle rather than duplicates - which is safe and completely
         * baffling: the button appears to do nothing. Refusing it here says
         * why, in business words, instead.
         */
        if ($this->hasOutstandingReviews($organisationId)) {
            return back()->with(
                'refusal',
                'A review cycle is already in progress. Complete the outstanding reviews before starting another cycle.'
            );
        }

        $days = (int) $request->input('due_in_days', 30);
        $days = max(1, min($days, 365));

        /*
         * THE RESOLVED ORGANISATION, not the actor's own column. A System
         * Administrator's role assignment is platform-scoped, and reading the
         * organisation off the person rather than off the request is how a
         * cycle quietly generates for the wrong one - or for all of them.
         */
        $this->generator->start($actor, now()->addDays($days), $organisationId);

        return redirect()
            ->route('access-reviews.privileged')
            ->with('confirmation', 'A review cycle has been started. Everything due is listed below.');
    }

    /**
     * THE ITEM IS RESOLVED HERE, NOT BY ROUTE-MODEL BINDING.
     *
     * Binding answers 404 for an id that does not exist and this controller
     * answered 302 for one the viewer may not touch - a directory-enumeration
     * oracle that let somebody map other people's reviews by probing
     * identifiers. It is the same defect the P1-01 anonymous sweep found on
     * records, arriving by a different route, and it was caught here by the
     * test asserting the two responses are IDENTICAL rather than asserting a
     * particular status.
     */
    public function decide(Request $request, int $item): RedirectResponse
    {
        $actor = $this->actor($request);

        $decision = ReviewDecision::tryFrom((string) $request->input('decision'));
        $found = AccessReviewItem::query()->find($item);

        if ($decision === null || $found === null) {
            return $this->refuse(ReviewViolation::notPermitted());
        }

        $item = $found;

        /*
         * AUTHORITY IS RE-EVALUATED HERE and again inside the service's
         * transaction. Checking only at render would leave a page open in a
         * browser able to act after the authority behind it was removed.
         */
        $basis = $this->authority->basisFor($actor, $item);

        if ($basis === null || $item->state !== ReviewState::Pending) {
            return $this->refuse(
                $item->state === ReviewState::Pending
                    ? ReviewViolation::notPermitted()
                    : ReviewViolation::alreadyDecided()
            );
        }

        $action = $this->stepUpActionFor($item, $decision, $basis);

        if ($action !== null) {
            /*
             * THE EXACT ITEM AND THE EXACT DECISION ARE BOUND HERE, into the
             * confirmation itself.
             *
             * The first implementation held the decision in a mutable column on
             * the item and let the callback find the item again by the access
             * object it was about. A second tab could then change the decision
             * while the confirmation was away, and a terminal item with a later
             * pending review for the same access could hand the confirmation to
             * the wrong review entirely. Nothing about either is reachable once
             * both halves live on the pending row, which nobody can edit.
             */
            $reference = $this->stepUp->begin(
                $actor,
                $request->session()->getId(),
                $action,
                [
                    'subject_user_id' => $item->subject_user_id,
                    'role_assignment_id' => $item->role_assignment_id,
                    'domain_entitlement_id' => $item->domain_entitlement_id,
                    'business_domain_id' => $item->business_domain_id,
                    'role_code' => $item->role_code?->value,
                    'organisation_id' => $item->organisation_id,
                    'subject_type' => ReviewStepUpCompletion::SUBJECT_TYPE,
                    'subject_id' => $item->getKey(),
                    'subject_intent' => $decision->value,
                ],
            );

            return redirect()->route('access.step-up.begin', ['reference' => $reference]);
        }

        try {
            $this->decisions->decide($item, $decision, $actor);
        } catch (ReviewViolation $violation) {
            return $this->refuse($violation);
        }

        return redirect()
            ->route($item->kind === ReviewKind::Privileged ? 'access-reviews.privileged' : 'access-reviews.domains')
            ->with('confirmation', $decision === ReviewDecision::Retain
                ? 'Access confirmed. Nothing about the access was changed.'
                : 'Access removed. It ended at that moment.');
    }

    /**
     * D-92, exactly as approved.
     *
     * Ordinary retain requires nothing, because it writes nothing to the access
     * model - there is no privileged change to protect. Everything that removes
     * privileged or restricted access does, and so does any self-review.
     */
    public function stepUpActionFor(
        AccessReviewItem $item,
        ReviewDecision $decision,
        DecisionBasis $basis,
    ): ?StepUpAction {
        if ($basis === DecisionBasis::SelfReview) {
            return StepUpAction::SelfReview;
        }

        if ($decision === ReviewDecision::Retain) {
            return null;
        }

        if ($item->kind === ReviewKind::Privileged) {
            // DERIVED from the catalogue, never listed. A role added to
            // requiringStepUp() later is covered here by construction.
            return in_array($item->role_code, RoleCatalogue::requiringStepUp(), true)
                ? StepUpAction::ReviewRevokePrivileged
                : null;
        }

        $ceiling = $item->composition['ceiling']['sensitivity'] ?? null;

        return $ceiling === Sensitivity::Restricted->value
            ? StepUpAction::RevokeRestrictedEntitlement
            : null;
    }

    /** Is any item in the latest cycle still awaiting a decision? */
    private function hasOutstandingReviews(?int $organisationId): bool
    {
        $cycleId = AccessReviewCycle::query()
            ->where('organisation_id', $organisationId)
            ->orderByDesc('id')
            ->value('id');

        return $cycleId !== null && AccessReviewItem::query()
            ->where('access_review_cycle_id', $cycleId)
            ->pending()
            ->exists();
    }

    private function isSystemAdministrator(User $actor): bool
    {
        return $actor->roleAssignments()
            ->whereNull('ended_at')
            ->where('role_code', RoleCode::SystemAdministrator->value)
            ->exists();
    }

    private function refuse(ReviewViolation $violation): RedirectResponse
    {
        return back()->with('refusal', $violation->getMessage());
    }

    private function actor(Request $request): User
    {
        return User::query()->findOrFail($request->session()->get(EnsureSessionIsCurrent::SESSION_USER_ID));
    }
}
