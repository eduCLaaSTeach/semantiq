<?php

declare(strict_types=1);

namespace Tests\Feature\Reviews;

use App\Modules\Access\Models\RoleAssignment;
use App\Modules\Access\StepUp\PendingStepUp;
use App\Modules\Access\StepUp\StepUpAction;
use App\Modules\Access\Support\RoleCode;
use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;
use App\Modules\Platform\Models\User;
use App\Modules\Reviews\Models\AccessReviewItem;
use App\Modules\Reviews\Services\ReviewCycleGenerator;
use App\Modules\Reviews\StepUp\ReviewStepUpCompletion;
use App\Modules\Reviews\Support\ReviewState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AccessFactory;
use Tests\Support\OrganisationFactory;
use Tests\Support\ReviewFactory;
use Tests\TestCase;

/**
 * GATE D DEFECT 3. THE TWO BUTTONS MUST SEND TWO DIFFERENT THINGS.
 *
 * The screen submitted with useForm({ decision: 'retain' }) and then
 * post(url, { data: { decision } }). Inertia types those submit options as
 * Omit<VisitOptions, 'data'> - the key is EXCLUDED - so it was silently
 * dropped and EVERY click sent `retain`. "Remove this access" quietly confirmed
 * the access, which is why it looked like the button did nothing, and why the
 * Product Owner's screen filled with rows marked "Access confirmed".
 *
 * These assert the two requests are DISTINGUISHABLE at the server, which is the
 * only place the difference can be proven without a JavaScript test runner.
 */
final class ReviewDecisionSubmissionTest extends TestCase
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
     * Confirm sends exactly `retain`, and it is not a revoke.
     *
     * Mutation: make the controller treat any input as retain.
     */
    public function test_confirm_sends_retain_and_removes_nothing(): void
    {
        [$actor, $assignment, $item] = $this->auditorFixture();

        $this->signedInAs($actor)
            ->post("/console/access-reviews/items/{$item->id}/decide", ['decision' => 'retain']);

        $this->assertSame(ReviewState::Retained, $item->fresh()->state);
        $this->assertNull($assignment->fresh()->ended_at);
    }

    /**
     * Remove sends exactly `revoke`, and it really removes.
     *
     * THE MUTATION THAT MATTERS: default the decision to 'retain' when the
     * request does not carry one. That is exactly what the browser was doing,
     * and this test is what would have caught it.
     */
    public function test_remove_sends_revoke_and_really_removes(): void
    {
        [$actor, $assignment, $item] = $this->auditorFixture();

        $this->signedInAs($actor)
            ->post("/console/access-reviews/items/{$item->id}/decide", ['decision' => 'revoke']);

        $this->assertSame(ReviewState::Revoked, $item->fresh()->state);
        $this->assertNotNull($assignment->fresh()->ended_at, 'Remove did not remove the access.');
    }

    /**
     * A MISSING OR UNRECOGNISED DECISION IS REFUSED, never defaulted.
     *
     * A default is how "remove" became "retain": the safest-looking value is
     * still a decision nobody made.
     *
     * Mutation: `?? 'retain'` on the input.
     */
    public function test_a_request_with_no_decision_decides_nothing(): void
    {
        [$actor, $assignment, $item] = $this->auditorFixture();

        $this->signedInAs($actor)->post("/console/access-reviews/items/{$item->id}/decide", []);
        $this->assertSame(ReviewState::Pending, $item->fresh()->state);

        $this->signedInAs($actor)->post("/console/access-reviews/items/{$item->id}/decide", ['decision' => 'approve']);
        $this->assertSame(ReviewState::Pending, $item->fresh()->state);

        $this->assertNull($assignment->fresh()->ended_at);
    }

    /**
     * THE PRODUCT OWNER'S EXACT JOURNEY, end to end and with no second
     * administrator manufactured.
     *
     * Removing their own System Administrator access must:
     *   1. start the fresh-sign-in flow rather than appearing to do nothing;
     *   2. not silently retain;
     *   3. after the confirmation, be refused by the administrator floor;
     *   4. leave the assignment active;
     *   5. leave the review showing the refusal rather than a decision.
     *
     * Mutation: skip stepUpActionFor, or bypass AdministratorSetGuard.
     */
    public function test_removing_the_only_system_administrator_confirms_then_refuses(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);
        $assignment = RoleAssignment::query()
            ->where('user_id', $admin->getKey())
            ->whereNull('ended_at')
            ->where('role_code', RoleCode::SystemAdministrator->value)
            ->firstOrFail();

        app(ReviewCycleGenerator::class)->start($admin, now()->addDays(30), $organisation->id);
        $item = AccessReviewItem::query()->where('role_assignment_id', $assignment->getKey())->firstOrFail();

        // 1. The click starts the fresh-sign-in flow.
        $response = $this->signedInAs($admin)
            ->post("/console/access-reviews/items/{$item->id}/decide", ['decision' => 'revoke']);

        $response->assertRedirect();
        $this->assertStringContainsString('step-up', (string) $response->headers->get('Location'));

        $pending = PendingStepUp::query()->latest('id')->firstOrFail();
        $this->assertSame(StepUpAction::SelfReview, $pending->action);
        $this->assertSame(ReviewStepUpCompletion::SUBJECT_TYPE, $pending->subject_type);
        $this->assertSame($item->id, (int) $pending->subject_id);
        $this->assertSame('revoke', $pending->subject_intent);

        // 2. Nothing has been decided yet.
        $this->assertSame(ReviewState::Pending, $item->fresh()->state);

        // 3 and 4. After the confirmation, the administrator floor refuses.
        try {
            app(ReviewStepUpCompletion::class)->complete($pending->fresh(), $admin);
            $this->fail('The only System Administrator was removed through a review.');
        } catch (\Throwable) {
            // P1-05's refusal, unchanged.
        }

        $this->assertNull(
            $assignment->fresh()->ended_at,
            'The only System Administrator assignment was ended.'
        );

        // 5. And the review was NOT silently retained.
        $this->assertNotSame(ReviewState::Retained, $item->fresh()->state);
    }

    /** @return array{0: User, 1: RoleAssignment, 2: AccessReviewItem} */
    private function auditorFixture(): array
    {
        $organisation = $this->make->organisation();
        $actor = $this->make->user($organisation, administrator: true);
        $subject = $this->make->user($organisation);
        $assignment = $this->access->assignment($subject, RoleCode::Auditor, $organisation);
        $item = $this->reviews->privilegedItem($this->reviews->cycle($actor), $assignment);

        return [$actor, $assignment, $item];
    }

    private function signedInAs(User $user): self
    {
        return $this->withSession([
            EnsureSessionIsCurrent::SESSION_USER_ID => $user->id,
            EnsureSessionIsCurrent::SESSION_AUTHENTICATED_AT => now()->toIso8601String(),
        ]);
    }
}
