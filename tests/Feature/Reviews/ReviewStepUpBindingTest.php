<?php

declare(strict_types=1);

namespace Tests\Feature\Reviews;

use App\Modules\Access\Models\RoleAssignment;
use App\Modules\Access\StepUp\PendingStepUp;
use App\Modules\Access\StepUp\StepUpAction;
use App\Modules\Access\StepUp\StepUpCompletionRegistry;
use App\Modules\Access\StepUp\StepUpService;
use App\Modules\Access\Support\AccessViolation;
use App\Modules\Access\Support\RoleCode;
use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;
use App\Modules\Platform\Models\User;
use App\Modules\Reviews\Models\AccessReviewItem;
use App\Modules\Reviews\StepUp\ReviewStepUpCompletion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AccessFactory;
use Tests\Support\OrganisationFactory;
use Tests\Support\ReviewFactory;
use Tests\TestCase;

/**
 * GATE C BLOCKER 1. ONE STEP-UP AUTHORISES ONE EXACT ITEM, ONE EXACT DECISION,
 * ONCE.
 *
 * The first implementation held the chosen decision in a mutable column on the
 * review item and, on return from Microsoft, found the item again by the id of
 * the ACCESS OBJECT it was about. Two things could go wrong and both are tested
 * here directly rather than reasoned about.
 */
final class ReviewStepUpBindingTest extends TestCase
{
    use RefreshDatabase;

    private OrganisationFactory $make;

    private AccessFactory $access;

    private ReviewFactory $reviews;

    protected function setUp(): void
    {
        parent::setUp();
        $this->make = new OrganisationFactory;
        $this->access = new AccessFactory;
        $this->reviews = new ReviewFactory;
    }

    /**
     * The binding is written at BEGIN and is not on anything a screen can edit.
     *
     * Mutation: stop passing subject_id / subject_intent. The completion then
     * has nothing to address and refuses, which is the safe half - but the
     * absence is what this asserts.
     */
    public function test_the_confirmation_records_the_exact_item_and_the_exact_decision(): void
    {
        [$actor, $item] = $this->privilegedFixture();

        $this->signedInAs($actor)
            ->post("/console/access-reviews/items/{$item->id}/decide", ['decision' => 'revoke']);

        $pending = PendingStepUp::query()->latest('id')->firstOrFail();

        $this->assertSame(ReviewStepUpCompletion::SUBJECT_TYPE, $pending->subject_type);
        $this->assertSame($item->id, (int) $pending->subject_id);
        $this->assertSame('revoke', $pending->subject_intent);
        $this->assertSame($actor->id, (int) $pending->user_id);
    }

    /**
     * RETAIN CHANGED TO REVOKE WHILE THE FIRST CONFIRMATION IS AWAY.
     *
     * The old design would have executed whatever the item said when the
     * reviewer came back. Each confirmation now carries its own intent, so the
     * one that returns performs what IT confirmed - and the other is a separate
     * row that can no longer find a pending item.
     *
     * Mutation: read the decision from the item instead of from the row.
     */
    public function test_a_second_decision_started_elsewhere_cannot_change_what_the_first_confirms(): void
    {
        /*
         * A SELF-REVIEW, because both of its decisions require a confirmation.
         * An ordinary retain correctly needs none - it writes nothing to the
         * access model - so it produces no pending row to diverge from, which
         * is what the first draft of this test got wrong.
         */
        [$actor, $item, $assignment] = $this->selfReviewFixture();

        // Confirmation one: retain.
        $this->signedInAs($actor)
            ->post("/console/access-reviews/items/{$item->id}/decide", ['decision' => 'retain']);
        $first = PendingStepUp::query()->latest('id')->firstOrFail();

        // Confirmation two, from another tab: revoke.
        $this->signedInAs($actor)
            ->post("/console/access-reviews/items/{$item->id}/decide", ['decision' => 'revoke']);
        $second = PendingStepUp::query()->latest('id')->firstOrFail();

        $this->assertSame('retain', $first->subject_intent);
        $this->assertSame('revoke', $second->subject_intent);
        $this->assertNotSame($first->id, $second->id);

        // The FIRST one returns. It retains, because that is what it confirmed.
        $this->complete($first, $actor);

        $this->assertSame('retained', $item->fresh()->state->value);
        $this->assertNull($assignment->fresh()->ended_at, 'The first confirmation executed the second intent.');
    }

    /**
     * TWO STEP-UPS ON THE SAME ITEM. The second cannot decide an item the first
     * already decided.
     *
     * Mutation: remove the terminal-state check from the decision service.
     */
    public function test_the_second_step_up_on_one_item_cannot_decide_it_again(): void
    {
        [$actor, $item, $assignment] = $this->selfReviewFixture();

        $this->signedInAs($actor)->post("/console/access-reviews/items/{$item->id}/decide", ['decision' => 'retain']);
        $first = PendingStepUp::query()->latest('id')->firstOrFail();

        $this->signedInAs($actor)->post("/console/access-reviews/items/{$item->id}/decide", ['decision' => 'revoke']);
        $second = PendingStepUp::query()->latest('id')->firstOrFail();

        $this->complete($first, $actor);
        $this->assertSame('retained', $item->fresh()->state->value);

        $this->expectException(AccessViolation::class);

        try {
            $this->complete($second, $actor);
        } finally {
            $this->assertSame('retained', $item->fresh()->state->value);
            $this->assertNull($assignment->fresh()->ended_at, 'A stale confirmation revoked the access.');
        }
    }

    /**
     * THE ONE THAT MATTERED MOST. The confirmed item becomes terminal, and a
     * LATER review exists for the same access object.
     *
     * The old callback searched for "a pending item for this access object" and
     * would have attached itself to the later review - authorising a decision
     * on a review the person never saw. Addressing the item by id makes that
     * unreachable.
     *
     * Mutation: find the item by role_assignment_id + pending state again.
     */
    public function test_a_confirmation_never_attaches_to_a_later_review_of_the_same_access(): void
    {
        [$actor, $item, $assignment] = $this->privilegedFixture();

        $this->signedInAs($actor)->post("/console/access-reviews/items/{$item->id}/decide", ['decision' => 'revoke']);
        $pending = PendingStepUp::query()->latest('id')->firstOrFail();

        // The reviewed item is decided by another route while the reviewer is
        // away, and a NEW cycle raises a fresh review of the same access.
        $item->forceFill(['state' => 'retained', 'decided_at' => now(), 'decided_by_user_id' => $actor->id])->save();
        $later = $this->reviews->privilegedItem($this->reviews->cycle($actor), $assignment);

        $this->expectException(AccessViolation::class);

        try {
            $this->complete($pending, $actor);
        } finally {
            $this->assertSame(
                'pending',
                $later->fresh()->state->value,
                'The confirmation attached itself to a review the person never saw.'
            );
            $this->assertNull($assignment->fresh()->ended_at);
        }
    }

    /**
     * A CANCELLED OR EXPIRED CONFIRMATION EXECUTES NOTHING.
     *
     * Mutation: let the completion run without the reference being live.
     */
    public function test_a_cancelled_or_expired_confirmation_decides_nothing(): void
    {
        [$actor, $item, $assignment] = $this->privilegedFixture();

        $this->signedInAs($actor)->post("/console/access-reviews/items/{$item->id}/decide", ['decision' => 'revoke']);
        $pending = PendingStepUp::query()->latest('id')->firstOrFail();

        // Cancelled at Microsoft: the service ends the row.
        app(StepUpService::class)->abandon($pending, 'provider_error', $actor);

        $this->assertNotNull($pending->fresh()->consumed_at);
        $this->assertSame('pending', $item->fresh()->state->value);
        $this->assertNull($assignment->fresh()->ended_at);

        // And an expired one is equally inert - resolve() refuses it, so the
        // completion is never reached.
        $this->signedInAs($actor)->post("/console/access-reviews/items/{$item->id}/decide", ['decision' => 'revoke']);
        $expired = PendingStepUp::query()->latest('id')->firstOrFail();
        $expired->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->assertFalse($expired->fresh()->isPending());
        $this->assertSame('pending', $item->fresh()->state->value);
    }

    /** An action nobody claims is refused, never performed as "the closest thing". */
    public function test_an_unclaimed_step_up_action_has_no_completion(): void
    {
        $registry = app(StepUpCompletionRegistry::class);

        $this->assertNull($registry->for(StepUpAction::GrantSystemAdministrator));
        $this->assertNotNull($registry->for(StepUpAction::ReviewRevokePrivileged));
        $this->assertNotNull($registry->for(StepUpAction::SelfReview));
        $this->assertNotNull($registry->for(StepUpAction::RevokeRestrictedEntitlement));
    }

    private function complete(PendingStepUp $pending, User $actor): mixed
    {
        return app(ReviewStepUpCompletion::class)->complete($pending->fresh(), $actor);
    }

    /**
     * The sole reviewer reviewing their own access.
     *
     * Every decision here requires a confirmation: D-88 permits self-review
     * only when nobody else could do it, and D-92 requires step-up for any
     * self-review whichever way it goes. An ordinary retain correctly needs
     * none - it writes nothing to the access model - so it produces no pending
     * row to diverge from, which is what the first draft of these two cases got
     * wrong.
     *
     * @return array{0: User, 1: AccessReviewItem, 2: RoleAssignment}
     */
    private function selfReviewFixture(): array
    {
        $organisation = $this->make->organisation();
        $starter = $this->make->user($organisation, administrator: true);
        $subject = $this->make->user($organisation);
        $assignment = $this->access->assignment($subject, RoleCode::BusinessUser, $organisation);
        $item = $this->reviews->privilegedItem($this->reviews->cycle($starter), $assignment);

        // The subject becomes the only person with standing.
        $this->access->assignment($subject, RoleCode::SystemAdministrator);
        $starter->roleAssignments()->whereNull('ended_at')->update(['ended_at' => now()]);

        return [$subject->fresh(), $item, $assignment];
    }

    /** @return array{0: User, 1: AccessReviewItem, 2: RoleAssignment} */
    private function privilegedFixture(): array
    {
        $organisation = $this->make->organisation();
        $actor = $this->make->user($organisation, administrator: true);
        $subject = $this->make->user($organisation);
        $assignment = $this->access->assignment($subject, RoleCode::OrganisationAdministrator, $organisation);
        $item = $this->reviews->privilegedItem($this->reviews->cycle($actor), $assignment);

        return [$actor, $item, $assignment];
    }

    private function signedInAs(User $user): self
    {
        return $this->withSession([
            EnsureSessionIsCurrent::SESSION_USER_ID => $user->id,
            EnsureSessionIsCurrent::SESSION_AUTHENTICATED_AT => now()->toIso8601String(),
        ]);
    }
}
