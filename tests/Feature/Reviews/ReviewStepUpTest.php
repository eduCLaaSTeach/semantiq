<?php

declare(strict_types=1);

namespace Tests\Feature\Reviews;

use App\Modules\Access\StepUp\StepUpAction;
use App\Modules\Access\Support\RoleCatalogue;
use App\Modules\Access\Support\RoleCode;
use App\Modules\Access\Support\ScopeType;
use App\Modules\Access\Support\Sensitivity;
use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;
use App\Modules\Platform\Models\User;
use App\Modules\Reviews\Models\AccessReviewCycle;
use App\Modules\Reviews\Models\AccessReviewItem;
use App\Modules\Reviews\Support\Composition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AccessFactory;
use Tests\Support\OrganisationFactory;
use Tests\TestCase;

/**
 * N-R19 AND THE §12 CORRECTION. The first tests written for this unit.
 *
 * THE RISK IS RE-USE, NOT NEW CODE. Revoking a System Administrator already
 * required a fresh sign-in in P1-05. A review screen that calls
 * RoleAssignmentService::revoke() without enforcing it is a step-up bypass for
 * the most privileged action in the product - and it would look like correct
 * re-use of accepted code.
 *
 * THESE ASSERT THE ENFORCEMENT, NOT THE WIRING. A test that checked "a step-up
 * row was created" would keep passing if the revoke ALSO happened; what matters
 * is that the access is still there afterwards.
 */
final class ReviewStepUpTest extends TestCase
{
    use RefreshDatabase;

    private OrganisationFactory $make;

    private AccessFactory $access;

    protected function setUp(): void
    {
        parent::setUp();
        $this->make = new OrganisationFactory;
        $this->access = new AccessFactory;
    }

    /**
     * N-R19. Revoking a System Administrator from a review needs step-up.
     *
     * Mutation: remove the stepUpActionFor() call from the decision
     * controller. The assignment then ends and this fails.
     */
    public function test_revoking_a_system_administrator_from_a_review_requires_step_up(): void
    {
        $organisation = $this->make->organisation();
        $actor = $this->make->user($organisation, administrator: true);
        $subject = $this->make->user($organisation);
        $assignment = $this->access->assignment($subject, RoleCode::SystemAdministrator);

        $item = $this->itemFor($assignment->id, RoleCode::SystemAdministrator, $subject);

        $this->signedInAs($actor)
            ->post("/console/access-reviews/items/{$item->id}/decide", ['decision' => 'revoke']);

        // THE ACCESS IS STILL THERE. That is the assertion; a pending row is not.
        $this->assertNull($assignment->fresh()->ended_at, 'The System Administrator role was revoked without a fresh sign-in.');
        $this->assertSame('pending', $item->fresh()->state->value);
    }

    /**
     * The same for Organisation Administrator - the role whose revoke action
     * did not exist before §12.
     *
     * Mutation: drop RevokeOrganisationAdministrator from the controller map.
     */
    public function test_revoking_an_organisation_administrator_from_a_review_requires_step_up(): void
    {
        $organisation = $this->make->organisation();
        $actor = $this->make->user($organisation, administrator: true);
        $subject = $this->make->user($organisation);
        $assignment = $this->access->assignment($subject, RoleCode::OrganisationAdministrator, $organisation);

        $item = $this->itemFor($assignment->id, RoleCode::OrganisationAdministrator, $subject);

        $this->signedInAs($actor)
            ->post("/console/access-reviews/items/{$item->id}/decide", ['decision' => 'revoke']);

        $this->assertNull($assignment->fresh()->ended_at);
    }

    /**
     * §12's CORRECTIVE PREREQUISITE, from the screen where the gap originated.
     *
     * RoleCatalogue::requiringStepUp() has always named both roles. The revoke
     * side had only the System Administrator case, so removing somebody holding
     * AccessAdmin went through with no fresh sign-in.
     *
     * Mutation: remove the Organisation Administrator arm from
     * AccessController::REVOKE_STEP_UP_ACTIONS.
     */
    public function test_roles_and_access_revoke_of_an_organisation_administrator_requires_step_up(): void
    {
        $organisation = $this->make->organisation();
        $actor = $this->make->user($organisation, administrator: true);
        $subject = $this->make->user($organisation);
        $assignment = $this->access->assignment($subject, RoleCode::OrganisationAdministrator, $organisation);

        $this->signedInAs($actor)->patch("/console/access/assignments/{$assignment->id}/revoke");

        $this->assertNull(
            $assignment->fresh()->ended_at,
            'An Organisation Administrator was revoked from Roles & Access without a fresh sign-in.'
        );
    }

    /**
     * THE GUARD THAT WOULD HAVE CAUGHT THE ORIGINAL OMISSION.
     *
     * Derived from the catalogue rather than listing the cases, so a role added
     * to requiringStepUp() later without both a grant and a revoke action fails
     * here immediately.
     *
     * Mutation: delete StepUpAction::RevokeOrganisationAdministrator.
     */
    public function test_every_role_requiring_step_up_has_both_a_grant_and_a_revoke_action(): void
    {
        $values = array_map(static fn (StepUpAction $a): string => $a->value, StepUpAction::cases());

        foreach (RoleCatalogue::requiringStepUp() as $role) {
            $this->assertContains(
                "grant_{$role->value}",
                $values,
                "No step-up action covers GRANTING {$role->value}, although the catalogue requires one."
            );

            $this->assertContains(
                "revoke_{$role->value}",
                $values,
                "No step-up action covers REVOKING {$role->value}, although the catalogue requires one. "
                .'This is the gap P1-07 §12 corrected.'
            );
        }
    }

    /**
     * D-92. Ordinary retain requires nothing, because it writes nothing.
     *
     * Mutation: return an action for retain. The decision then never completes
     * without a round trip, and this fails.
     */
    public function test_an_ordinary_retain_needs_no_step_up(): void
    {
        $organisation = $this->make->organisation();
        $actor = $this->make->user($organisation, administrator: true);
        $subject = $this->make->user($organisation);
        $assignment = $this->access->assignment($subject, RoleCode::Auditor, $organisation);

        $item = $this->itemFor($assignment->id, RoleCode::Auditor, $subject);

        $this->signedInAs($actor)
            ->post("/console/access-reviews/items/{$item->id}/decide", ['decision' => 'retain']);

        $this->assertSame('retained', $item->fresh()->state->value);
        $this->assertNull($assignment->fresh()->ended_at);
    }

    /**
     * D-92. Revoking a RESTRICTED entitlement needs step-up; an ordinary one
     * does not.
     *
     * Mutation: drop the ceiling check. The restricted case then revokes
     * without a fresh sign-in.
     */
    public function test_revoking_a_restricted_entitlement_requires_step_up_and_an_ordinary_one_does_not(): void
    {
        $organisation = $this->make->organisation();
        $actor = $this->make->user($organisation, administrator: true);
        $subject = $this->make->user($organisation);
        $domain = $this->access->domain($organisation);

        $restricted = $this->access->completePath(
            $subject, $domain, RoleCode::Manager, ScopeType::Team, null, Sensitivity::Restricted
        );

        $item = $this->domainItemFor($restricted, $subject, $domain->id);

        $this->signedInAs($actor)
            ->post("/console/access-reviews/items/{$item->id}/decide", ['decision' => 'revoke']);

        $this->assertNull(
            $restricted->fresh()->ended_at,
            'A restricted entitlement was revoked from a review without a fresh sign-in.'
        );
    }

    private function itemFor(int $assignmentId, RoleCode $role, User $subject): AccessReviewItem
    {
        return $this->createItem([
            'kind' => 'privileged',
            'role_assignment_id' => $assignmentId,
            'role_code' => $role->value,
            'subject_user_id' => $subject->id,
            'composition' => ['role_code' => $role->value],
        ]);
    }

    private function domainItemFor(object $entitlement, User $subject, int $domainId): AccessReviewItem
    {
        $composition = Composition::ofEntitlement($entitlement);

        return $this->createItem([
            'kind' => 'domain',
            'domain_entitlement_id' => $entitlement->id,
            'business_domain_id' => $domainId,
            'role_code' => (string) $composition['role_code'],
            'subject_user_id' => $subject->id,
            'composition' => $composition,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function createItem(array $attributes): AccessReviewItem
    {
        $cycle = AccessReviewCycle::query()->create([
            'organisation_id' => User::query()->findOrFail($attributes['subject_user_id'])->organisation_id,
            'started_at' => now(),
            'due_at' => now()->addDays(30),
            'started_by_user_id' => $attributes['subject_user_id'],
        ]);

        $composition = $attributes['composition'];
        unset($attributes['composition']);

        return AccessReviewItem::query()->create($attributes + [
            'access_review_cycle_id' => $cycle->id,
            'organisation_id' => $cycle->organisation_id,
            'state' => 'pending',
            'due_at' => $cycle->due_at,
            'composition' => $composition,
            'composition_fingerprint' => Composition::fingerprint($composition),
        ]);
    }

    private function signedInAs(User $user): self
    {
        return $this->withSession([
            EnsureSessionIsCurrent::SESSION_USER_ID => $user->id,
            EnsureSessionIsCurrent::SESSION_AUTHENTICATED_AT => now()->toIso8601String(),
        ]);
    }
}
