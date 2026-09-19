<?php

declare(strict_types=1);

namespace Tests\Feature\Reviews;

use App\Modules\Access\Models\DomainEntitlement;
use App\Modules\Access\Support\RoleCatalogue;
use App\Modules\Access\Support\RoleCode;
use App\Modules\Access\Support\ScopeType;
use App\Modules\Access\Support\Sensitivity;
use App\Modules\Domains\Models\DomainOwnership;
use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;
use App\Modules\Platform\Models\User;
use App\Modules\Reviews\Models\AccessReviewItem;
use App\Modules\Reviews\Services\ReviewCycleGenerator;
use App\Modules\Reviews\Services\ReviewDecisionService;
use App\Modules\Reviews\Services\ReviewerAuthority;
use App\Modules\Reviews\Support\DecisionBasis;
use App\Modules\Reviews\Support\ReviewDecision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AccessFactory;
use Tests\Support\OrganisationFactory;
use Tests\Support\ReviewFactory;
use Tests\TestCase;

/**
 * N-R1, N-R2, N-R3, N-R11, N-R14, N-R22, N-R23. WHO MAY REVIEW WHOM.
 */
final class ReviewAuthorityTest extends TestCase
{
    use RefreshDatabase;

    private OrganisationFactory $make;

    private AccessFactory $access;

    private ReviewFactory $reviews;

    private ReviewerAuthority $authority;

    protected function setUp(): void
    {
        parent::setUp();
        $this->make = new OrganisationFactory;
        $this->access = new AccessFactory;
        $this->reviews = new ReviewFactory;
        $this->authority = app(ReviewerAuthority::class);
    }

    /**
     * N-R3. AN ORGANISATION ADMINISTRATOR CANNOT REVIEW A SYSTEM
     * ADMINISTRATOR.
     *
     * This is the privilege-escalation path the unit exists to close: if they
     * could, the review screen would be a second route to revoking the one
     * person able to stop them - the exact action grantableBy() forbids.
     *
     * Mutation: replace the derivation with a literal role list including
     * system_administrator.
     */
    public function test_an_organisation_administrator_cannot_review_a_system_administrator(): void
    {
        $organisation = $this->make->organisation();
        $orgAdmin = $this->make->user($organisation);
        $this->access->assignment($orgAdmin, RoleCode::OrganisationAdministrator, $organisation);

        $subject = $this->make->user($organisation);
        $assignment = $this->access->assignment($subject, RoleCode::SystemAdministrator);
        $starter = $this->make->user($organisation, administrator: true);
        $item = $this->reviews->privilegedItem($this->reviews->cycle($starter), $assignment);

        $this->assertFalse($this->authority->canReview($orgAdmin, $item));

        // And they may review the roles they CAN grant.
        $auditorAssignment = $this->access->assignment($this->make->user($organisation), RoleCode::Auditor, $organisation);
        $auditorItem = $this->reviews->privilegedItem($this->reviews->cycle($starter), $auditorAssignment);
        $this->assertTrue($this->authority->canReview($orgAdmin, $auditorItem));
    }

    /**
     * The rule is DERIVED, so it cannot drift from the catalogue.
     *
     * Mutation: hard-code the reviewable roles.
     */
    public function test_privileged_authority_matches_grantable_by_for_every_role(): void
    {
        $organisation = $this->make->organisation();
        $starter = $this->make->user($organisation, administrator: true);

        foreach ([RoleCode::SystemAdministrator, RoleCode::OrganisationAdministrator, RoleCode::Auditor] as $actorRole) {
            $actor = $this->make->user($organisation);
            $this->access->assignment($actor, $actorRole, $actorRole->isPlatformScoped() ? null : $organisation);

            foreach (RoleCode::cases() as $targetRole) {
                $subject = $this->make->user($organisation);
                $assignment = $this->access->assignment($subject, $targetRole, $targetRole->isPlatformScoped() ? null : $organisation);
                $item = $this->reviews->privilegedItem($this->reviews->cycle($starter), $assignment);

                $this->assertSame(
                    in_array($targetRole, RoleCatalogue::grantableBy($actorRole), true),
                    $this->authority->canReview($actor, $item),
                    "{$actorRole->value} reviewing {$targetRole->value} disagrees with grantableBy()."
                );
            }
        }
    }

    /** An Auditor decides nothing, because grantableBy(Auditor) is empty. */
    public function test_an_auditor_can_read_but_decides_nothing(): void
    {
        $organisation = $this->make->organisation();
        $auditor = $this->make->user($organisation);
        $this->access->assignment($auditor, RoleCode::Auditor, $organisation);

        $starter = $this->make->user($organisation, administrator: true);
        $subject = $this->make->user($organisation);
        $assignment = $this->access->assignment($subject, RoleCode::Auditor, $organisation);
        $item = $this->reviews->privilegedItem($this->reviews->cycle($starter), $assignment);

        $this->assertFalse($this->authority->canReview($auditor, $item));

        // But the screen is readable.
        $this->signedInAs($auditor)->get('/console/access-reviews')->assertOk();
    }

    /**
     * B-1a. A System Administrator is eligible for EVERY domain item, and a
     * current owner is recorded as the owner basis.
     *
     * Mutation: restore "fallback only where no owner exists". The first
     * assertion then fails and every item in an owned domain is undecidable.
     */
    public function test_a_system_administrator_may_review_a_domain_that_has_an_owner(): void
    {
        $organisation = $this->make->organisation();
        $actor = $this->make->user($organisation, administrator: true);
        $subject = $this->make->user($organisation);
        $owner = $this->make->user($organisation);
        $domain = $this->access->domain($organisation);

        DomainOwnership::query()->create([
            'business_domain_id' => $domain->id,
            'user_id' => $owner->id,
            'assigned_at' => now(),
        ]);

        $entitlement = $this->access->completePath($subject, $domain, RoleCode::Manager, ScopeType::Team, null, Sensitivity::Confidential);
        $item = $this->reviews->domainItem($this->reviews->cycle($actor), $entitlement);

        $this->assertSame(DecisionBasis::SystemAdministrator, $this->authority->basisFor($actor, $item));
        $this->assertSame(DecisionBasis::DomainOwner, $this->authority->basisFor($owner, $item));
    }

    /**
     * N-R22. OWNING A DOMAIN GRANTS NO BUSINESS DATA.
     *
     * The owner gains standing to attest and nothing else: no assignment, no
     * entitlement, and the engine still never reads business_domain_owners.
     *
     * Mutation: create an entitlement for the owner when they review.
     */
    public function test_reviewing_as_a_domain_owner_grants_the_owner_no_access(): void
    {
        $organisation = $this->make->organisation();
        $actor = $this->make->user($organisation, administrator: true);
        $subject = $this->make->user($organisation);
        $owner = $this->make->user($organisation);
        $domain = $this->access->domain($organisation);

        DomainOwnership::query()->create([
            'business_domain_id' => $domain->id,
            'user_id' => $owner->id,
            'assigned_at' => now(),
        ]);

        $entitlement = $this->access->completePath($subject, $domain, RoleCode::Manager, ScopeType::Team, null, Sensitivity::Confidential);
        $item = $this->reviews->domainItem($this->reviews->cycle($actor), $entitlement);

        app(ReviewDecisionService::class)->decide($item, ReviewDecision::Retain, $owner);

        $this->assertSame(0, $owner->roleAssignments()->count(), 'Reviewing granted the owner a role.');
        $this->assertSame(
            0,
            DomainEntitlement::query()
                ->whereHas('assignment', fn ($q) => $q->where('user_id', $owner->id))
                ->count(),
            'Reviewing granted the owner an entitlement.'
        );
    }

    /**
     * N-R1 and N-R23. READING IS NOT DECIDING.
     *
     * An evidence reader sees the review evidence of their own organisation;
     * whether they may act on a given row is a separate question, answered per
     * row as `decidable`. An Organisation Administrator therefore SEES a System
     * Administrator's review and cannot decide it.
     *
     * The first implementation filtered the listing by decision authority, so
     * an Auditor - whose entire role is reading evidence - got an empty screen.
     *
     * Mutation: make `decidable` true for every visible row, or drop
     * scopeVisible() from the controller.
     */
    public function test_a_listing_shows_evidence_but_marks_what_cannot_be_decided(): void
    {
        $organisation = $this->make->organisation();
        $starter = $this->make->user($organisation, administrator: true);

        $orgAdmin = $this->make->user($organisation);
        $this->access->assignment($orgAdmin, RoleCode::OrganisationAdministrator, $organisation);

        // One item the Organisation Administrator may never review.
        $sa = $this->make->user($organisation);
        $this->reviews->privilegedItem(
            $this->reviews->cycle($starter),
            $this->access->assignment($sa, RoleCode::SystemAdministrator),
        );

        $response = $this->signedInAs($orgAdmin)->get('/console/access-reviews');
        $response->assertOk();

        $props = $response->viewData('page')['props'];

        // Visible as evidence...
        $this->assertCount(1, $props['items']['data']);
        $this->assertSame(1, $props['counts']['privileged']);

        // ...and NOT actionable.
        $this->assertFalse($props['items']['data'][0]['decidable']);
        $this->assertNull($props['items']['data'][0]['basis']);
    }

    /**
     * BLOCKER 4. An Auditor reads the evidence and decides nothing.
     *
     * grantableBy(Auditor) is empty, which is exactly right for deciding and
     * exactly wrong for reading. Both halves are asserted here because the
     * defect was that one was used for the other.
     *
     * Mutation: filter the listing by decision authority again.
     */
    public function test_an_auditor_sees_review_evidence_and_can_decide_nothing(): void
    {
        $organisation = $this->make->organisation();
        $starter = $this->make->user($organisation, administrator: true);
        $auditor = $this->make->user($organisation);
        $this->access->assignment($auditor, RoleCode::Auditor, $organisation);

        $subject = $this->make->user($organisation);
        $this->reviews->privilegedItem(
            $this->reviews->cycle($starter),
            $this->access->assignment($subject, RoleCode::Auditor, $organisation),
        );

        $props = $this->signedInAs($auditor)->get('/console/access-reviews')->viewData('page')['props'];

        $this->assertCount(1, $props['items']['data'], 'An Auditor saw no review evidence at all.');
        $this->assertFalse($props['items']['data'][0]['decidable']);
        $this->assertSame(1, $props['counts']['privileged']);
    }

    /**
     * BLOCKER 3. Reviews never cross an organisation boundary - not in the
     * listing, not in the counts, and not in the decision.
     *
     * The System Administrator role is PLATFORM-SCOPED, so its assignment
     * carries no organisation_id. Reading that as "every organisation" is how
     * one customer's access reaches another's screen.
     *
     * Mutation: drop the organisation filter from scopeVisible(), or from
     * basisFor().
     */
    public function test_reviews_never_cross_an_organisation_boundary(): void
    {
        $ours = $this->make->organisation('Ours');
        $theirs = $this->make->organisation('Theirs');

        $ourAdmin = $this->make->user($ours, administrator: true);
        $theirAdmin = $this->make->user($theirs, administrator: true);

        $theirSubject = $this->make->user($theirs);
        $theirItem = $this->reviews->privilegedItem(
            $this->reviews->cycle($theirAdmin),
            $this->access->assignment($theirSubject, RoleCode::Auditor, $theirs),
        );

        // Not visible.
        $props = $this->signedInAs($ourAdmin)->get('/console/access-reviews')->viewData('page')['props'];
        $this->assertSame([], $props['items']['data'], "Another organisation's reviews were listed.");
        $this->assertSame(0, $props['counts']['privileged']);

        // Not decidable, even though this actor is a System Administrator.
        $this->assertNull($this->authority->basisFor($ourAdmin, $theirItem));

        $this->signedInAs($ourAdmin)
            ->post("/console/access-reviews/items/{$theirItem->id}/decide", ['decision' => 'revoke']);

        $this->assertSame('pending', $theirItem->fresh()->state->value);
    }

    /**
     * BLOCKER 3. A cycle generates for the request's organisation only.
     *
     * Mutation: generate from the actor's own organisation_id column, or drop
     * the organisation filter from generation.
     */
    public function test_a_cycle_generates_only_for_its_own_organisation(): void
    {
        $ours = $this->make->organisation('Ours');
        $theirs = $this->make->organisation('Theirs');

        $ourAdmin = $this->make->user($ours, administrator: true);
        $theirSubject = $this->make->user($theirs);
        $this->access->assignment($theirSubject, RoleCode::Auditor, $theirs);

        app(ReviewCycleGenerator::class)
            ->start($ourAdmin, now()->addDays(30), $ours->id);

        $this->assertFalse(
            AccessReviewItem::query()->where('subject_user_id', $theirSubject->id)->exists(),
            "A cycle pulled another organisation's access into its population."
        );
    }

    /**
     * N-R2 and N-R14. A refusal discloses nothing - not even existence.
     *
     * Mutation: return 404 for a missing item and 403 for a forbidden one.
     */
    public function test_a_forbidden_item_and_a_missing_item_refuse_identically(): void
    {
        $organisation = $this->make->organisation();
        $starter = $this->make->user($organisation, administrator: true);
        $orgAdmin = $this->make->user($organisation);
        $this->access->assignment($orgAdmin, RoleCode::OrganisationAdministrator, $organisation);

        $sa = $this->make->user($organisation);
        $forbidden = $this->reviews->privilegedItem(
            $this->reviews->cycle($starter),
            $this->access->assignment($sa, RoleCode::SystemAdministrator),
        );

        $missingId = AccessReviewItem::query()->max('id') + 1000;

        $forbiddenResponse = $this->signedInAs($orgAdmin)
            ->post("/console/access-reviews/items/{$forbidden->id}/decide", ['decision' => 'revoke']);
        $missingResponse = $this->signedInAs($orgAdmin)
            ->post("/console/access-reviews/items/{$missingId}/decide", ['decision' => 'revoke']);

        $this->assertSame(
            $forbiddenResponse->getStatusCode(),
            $missingResponse->getStatusCode(),
            'The two refusals differ, so the response says whether the item exists.'
        );

        $this->assertStringNotContainsString($sa->display_name, (string) $forbiddenResponse->getContent());
        $this->assertNull($forbidden->fresh()->decided_at);
    }

    /**
     * N-R11. Authority is re-checked ON SUBMIT, not only at render.
     *
     * Mutation: authorise in the controller only when rendering.
     */
    public function test_a_reviewer_whose_authority_was_removed_cannot_submit(): void
    {
        $organisation = $this->make->organisation();
        $starter = $this->make->user($organisation, administrator: true);
        $orgAdmin = $this->make->user($organisation);
        $assignment = $this->access->assignment($orgAdmin, RoleCode::OrganisationAdministrator, $organisation);

        $subject = $this->make->user($organisation);
        $target = $this->access->assignment($subject, RoleCode::Auditor, $organisation);
        $item = $this->reviews->privilegedItem($this->reviews->cycle($starter), $target);

        // Their authority goes away after the page was rendered.
        $assignment->forceFill(['ended_at' => now()])->save();

        $this->signedInAs($orgAdmin)
            ->post("/console/access-reviews/items/{$item->id}/decide", ['decision' => 'revoke']);

        $this->assertSame('pending', $item->fresh()->state->value);
        $this->assertNull($target->fresh()->ended_at);
    }

    private function signedInAs(User $user): self
    {
        return $this->withSession([
            EnsureSessionIsCurrent::SESSION_USER_ID => $user->id,
            EnsureSessionIsCurrent::SESSION_AUTHENTICATED_AT => now()->toIso8601String(),
        ]);
    }
}
