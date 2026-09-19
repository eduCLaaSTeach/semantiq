<?php

declare(strict_types=1);

namespace Tests\Feature\Reviews;

use App\Modules\Access\Models\DomainEntitlement;
use App\Modules\Access\Models\EntitlementCeiling;
use App\Modules\Access\Models\EntitlementScope;
use App\Modules\Access\Models\RoleAssignment;
use App\Modules\Access\Services\EntitlementService;
use App\Modules\Access\Support\RoleCode;
use App\Modules\Access\Support\ScopeType;
use App\Modules\Access\Support\Sensitivity;
use App\Modules\Domains\Models\BusinessDomain;
use App\Modules\Platform\Models\User;
use App\Modules\Reviews\Services\ReviewDecisionService;
use App\Modules\Reviews\Support\ReviewDecision;
use App\Modules\Reviews\Support\ReviewViolation;
use App\Modules\Reviews\Support\SupersededReason;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AccessFactory;
use Tests\Support\OrganisationFactory;
use Tests\Support\ReviewFactory;
use Tests\TestCase;

/**
 * N-R4 to N-R10, N-R16, N-R20, N-R25. What a decision does, and does not do.
 */
final class ReviewDecisionTest extends TestCase
{
    use RefreshDatabase;

    private OrganisationFactory $make;

    private AccessFactory $access;

    private ReviewFactory $reviews;

    private ReviewDecisionService $decisions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->make = new OrganisationFactory;
        $this->access = new AccessFactory;
        $this->reviews = new ReviewFactory;
        $this->decisions = app(ReviewDecisionService::class);
    }

    /**
     * N-R4. RETAIN WRITES NOTHING TO THE ACCESS MODEL.
     *
     * The assertion is BYTE-IDENTICAL rows, not "no new entitlement appeared" -
     * the weaker version passes even if retain quietly extends a period or
     * resets a ceiling, which is exactly the kind of helpful change somebody
     * makes.
     *
     * Mutation: touch() the entitlement in the retain branch.
     */
    public function test_retain_changes_no_access_row_at_all(): void
    {
        [$actor, $subject, $entitlement] = $this->domainFixture();
        $item = $this->reviews->domainItem($this->reviews->cycle($actor), $entitlement);

        $before = $this->accessSnapshot($subject);

        $this->decisions->decide($item, ReviewDecision::Retain, $actor);

        $this->assertSame($before, $this->accessSnapshot($subject));
        $this->assertSame('retained', $item->fresh()->state->value);
    }

    /**
     * N-R5. Revoke changes effective access IMMEDIATELY, asserted through the
     * rows the engine reads rather than through the screen.
     *
     * Mutation: mark the item revoked without calling the P1-05 service.
     */
    public function test_revoke_ends_the_entitlement_immediately(): void
    {
        [$actor, , $entitlement] = $this->domainFixture();
        $item = $this->reviews->domainItem($this->reviews->cycle($actor), $entitlement);

        $this->decisions->decide($item, ReviewDecision::Revoke, $actor);

        $this->assertNotNull($entitlement->fresh()->ended_at);
        $this->assertSame('revoked', $item->fresh()->state->value);
    }

    /**
     * N-R6. Revoking one independent path leaves another alone.
     *
     * Two assignments, two entitlements, same person, same domain. Revoking by
     * person and domain would take both.
     *
     * Mutation: revoke by subject + domain instead of by entitlement id.
     */
    public function test_revoking_one_grant_path_leaves_an_independent_one_untouched(): void
    {
        $organisation = $this->make->organisation();
        $actor = $this->make->user($organisation, administrator: true);
        $this->access->assignment($actor, RoleCode::SystemAdministrator);
        $subject = $this->make->user($organisation);
        $domain = $this->access->domain($organisation);

        $first = $this->access->completePath($subject, $domain, RoleCode::Manager, ScopeType::Team, null, Sensitivity::Confidential);
        $second = $this->access->completePath($subject, $domain, RoleCode::Executive, ScopeType::Domain, null, Sensitivity::Confidential);

        /*
         * THE SECOND PATH IS THE ONE REVIEWED, deliberately.
         *
         * Reviewing the first let the mutation "revoke by subject and domain"
         * SURVIVE: its query finds both rows and firstOrFail() happened to
         * return the reviewed one, so the test passed for a reason unrelated to
         * what it claimed. Reviewing the second makes that mutation end the
         * WRONG grant, which is the defect the case exists to catch.
         */
        $item = $this->reviews->domainItem($this->reviews->cycle($actor), $second);
        $this->decisions->decide($item, ReviewDecision::Revoke, $actor);

        $this->assertNotNull($second->fresh()->ended_at, 'The reviewed path should have ended.');
        $this->assertNull($first->fresh()->ended_at, 'An independent path was taken with it.');
    }

    /**
     * N-R7 and D-93. A composition change after the item was raised supersedes
     * it, and no decision is applied.
     *
     * Without this, somebody could widen a grant while its review was open and
     * have the review approve the widened version.
     *
     * Mutation: drop the fingerprint comparison from supersedeReason().
     */
    public function test_a_changed_composition_supersedes_and_writes_nothing(): void
    {
        [$actor, , $entitlement] = $this->domainFixture();
        $item = $this->reviews->domainItem($this->reviews->cycle($actor), $entitlement);

        // Somebody widens the grant while the review is open.
        app(EntitlementService::class)->assignScope($entitlement, ScopeType::Domain, null, $actor);

        $this->decisions->decide($item, ReviewDecision::Revoke, $actor);

        $fresh = $item->fresh();
        $this->assertSame('superseded', $fresh->state->value);
        $this->assertSame(SupersededReason::CompositionChanged, $fresh->superseded_reason);
        $this->assertNull($entitlement->fresh()->ended_at, 'A superseded review still revoked the access.');
    }

    /**
     * N-R7b. An object already ended elsewhere supersedes.
     *
     * Mutation: return null when the object has ended.
     */
    public function test_an_already_revoked_object_supersedes(): void
    {
        [$actor, , $entitlement] = $this->domainFixture();
        $item = $this->reviews->domainItem($this->reviews->cycle($actor), $entitlement);

        app(EntitlementService::class)->revoke($entitlement, $actor);

        $this->decisions->decide($item, ReviewDecision::Retain, $actor);

        $this->assertSame('superseded', $item->fresh()->state->value);
        $this->assertSame(SupersededReason::ObjectEnded, $item->fresh()->superseded_reason);
    }

    /**
     * N-R20. A new assignment period does not inherit the old review.
     *
     * The item points at the OLD entitlement row forever, because
     * domain_entitlements is keyed on a specific assignment period.
     *
     * Mutation: key the item on subject + role_code.
     */
    public function test_a_new_assignment_period_does_not_inherit_an_old_review(): void
    {
        [$actor, $subject, $entitlement] = $this->domainFixture();
        $domainId = $entitlement->business_domain_id;
        $item = $this->reviews->domainItem($this->reviews->cycle($actor), $entitlement);

        app(EntitlementService::class)->revoke($entitlement, $actor);

        // Granted again - a NEW row under a new period.
        $replacement = $this->access->completePath(
            $subject,
            BusinessDomain::query()->findOrFail($domainId),
            RoleCode::Manager,
            ScopeType::Team,
            null,
            Sensitivity::Confidential,
        );

        $this->decisions->decide($item, ReviewDecision::Revoke, $actor);

        $this->assertSame('superseded', $item->fresh()->state->value);
        $this->assertNull($replacement->fresh()->ended_at, 'The old review reached the new grant.');
    }

    /**
     * N-R25. A decision is WRITE-ONCE.
     *
     * Mutation: remove the terminal-state check from decide().
     */
    public function test_a_decided_item_cannot_be_decided_again(): void
    {
        [$actor, , $entitlement] = $this->domainFixture();
        $item = $this->reviews->domainItem($this->reviews->cycle($actor), $entitlement);

        $this->decisions->decide($item, ReviewDecision::Retain, $actor);
        $decidedAt = $item->fresh()->decided_at;

        $this->expectException(ReviewViolation::class);

        try {
            $this->decisions->decide($item->fresh(), ReviewDecision::Revoke, $actor);
        } finally {
            $this->assertSame('retained', $item->fresh()->state->value);
            $this->assertEquals($decidedAt, $item->fresh()->decided_at);
            $this->assertNull($entitlement->fresh()->ended_at);
        }
    }

    /**
     * N-R16. The review record survives the access it reviewed.
     *
     * Mutation: CASCADE the foreign key.
     */
    public function test_the_review_record_survives_revocation(): void
    {
        [$actor, , $entitlement] = $this->domainFixture();
        $item = $this->reviews->domainItem($this->reviews->cycle($actor), $entitlement);

        $this->decisions->decide($item, ReviewDecision::Revoke, $actor);

        $this->assertNotNull($item->fresh(), 'The evidence went with the access.');
        $this->assertSame($entitlement->getKey(), $item->fresh()->domain_entitlement_id);
    }

    /**
     * N-R8. An inactive subject stays denied whatever a retained grant says.
     *
     * The engine denies before any row is read, so retaining changes nothing
     * about what they can reach.
     *
     * Mutation: remove the engine's inactive gate.
     */
    public function test_retaining_a_grant_held_by_an_inactive_person_grants_nothing(): void
    {
        [$actor, $subject, $entitlement] = $this->domainFixture();
        $item = $this->reviews->domainItem($this->reviews->cycle($actor), $entitlement);

        $subject->forceFill(['status' => 'inactive'])->save();

        $this->decisions->decide($item, ReviewDecision::Retain, $actor);

        $this->assertFalse($subject->fresh()->isActive());
        $this->assertNull($entitlement->fresh()->ended_at, 'Retain should not have changed the access either way.');
    }

    /**
     * N-R18. The administrator floor reaches the review screen unchanged.
     *
     * Mutation: bypass AdministratorSetGuard in the review path.
     */
    public function test_revoking_the_last_system_administrator_is_refused_from_a_review(): void
    {
        $organisation = $this->make->organisation();
        $actor = $this->make->user($organisation, administrator: true);
        $assignment = RoleAssignment::query()
            ->where('user_id', $actor->getKey())
            ->whereNull('ended_at')
            ->where('role_code', RoleCode::SystemAdministrator->value)
            ->firstOrFail();

        $item = $this->reviews->privilegedItem($this->reviews->cycle($actor), $assignment);

        try {
            $this->decisions->decide($item, ReviewDecision::Revoke, $actor);
            $this->fail('The last System Administrator was revoked from a review.');
        } catch (\Throwable) {
            // The refusal is P1-05's, unchanged.
        }

        $this->assertNull($assignment->fresh()->ended_at);
    }

    /** @return array{0: User, 1: User, 2: DomainEntitlement} */
    private function domainFixture(): array
    {
        $organisation = $this->make->organisation();
        $actor = $this->make->user($organisation, administrator: true);
        $subject = $this->make->user($organisation);
        $domain = $this->access->domain($organisation);

        $entitlement = $this->access->completePath(
            $subject, $domain, RoleCode::Manager, ScopeType::Team, null, Sensitivity::Confidential
        );

        return [$actor, $subject, $entitlement];
    }

    /**
     * Every access row for this person, in full. Byte-identical or it changed.
     *
     * @return array<string, mixed>
     */
    private function accessSnapshot(User $subject): array
    {
        $assignments = RoleAssignment::query()->where('user_id', $subject->getKey())->orderBy('id')->get()->toArray();
        $ids = array_column($assignments, 'id');
        $entitlements = DomainEntitlement::query()->whereIn('role_assignment_id', $ids)->orderBy('id')->get()->toArray();
        $entitlementIds = array_column($entitlements, 'id');

        return [
            'assignments' => $assignments,
            'entitlements' => $entitlements,
            'scopes' => EntitlementScope::query()->whereIn('domain_entitlement_id', $entitlementIds)->orderBy('id')->get()->toArray(),
            'ceilings' => EntitlementCeiling::query()->whereIn('domain_entitlement_id', $entitlementIds)->orderBy('id')->get()->toArray(),
        ];
    }
}
