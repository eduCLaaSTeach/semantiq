<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Enums\BusinessDomain;
use App\Enums\Role;
use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Identity\Enums\ReviewDecision;
use App\Modules\Identity\Models\AccessReviewItem;
use App\Modules\Identity\Models\BusinessUnit;
use App\Modules\Identity\Services\AccessReviewService;
use App\Modules\Identity\Services\RoleRegistry;
use App\Modules\Identity\Services\StructureRegistry;
use App\Modules\Identity\Services\UserRegistry;
use App\Modules\Identity\Support\OrganisationContext;
use App\Modules\Platform\Enums\LifecycleStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * Business units, teams and access reviews. Features ADM-003, ADM-004, ADM-008.
 *
 * The two rules that need code rather than a constraint are here: the hierarchy
 * loop check, which no relational constraint can express, and the review
 * workflow's refusal to treat silence as approval.
 */
class StructureAndReviewTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return $this->placed('Ada Admin', Role::SystemAdmin);
    }

    /**
     * An account placed in the organisation currently in force.
     *
     * Placement is not incidental: `UserRegistry` refuses any mutation on a
     * subject outside the current organisation, and both the registry and
     * Microsoft sign-in place an account at creation, so an unplaced one is a
     * state the application cannot produce.
     */
    private function placed(string $name, Role $role, ?string $email = null): User
    {
        $user = User::query()->create(['name' => $name, 'email' => $email ?? uniqid().'@example.test']);
        $user->forceFill([
            'role' => $role,
            'organisation_id' => app(OrganisationContext::class)->currentId(),
        ])->save();

        return $user->refresh();
    }

    private function structure(): StructureRegistry
    {
        return app(StructureRegistry::class);
    }

    /* ---- Business units --------------------------------------------- */

    #[Test]
    public function a_unit_cannot_be_its_own_parent(): void
    {
        $admin = $this->admin();
        $unit = $this->structure()->createBusinessUnit(['code' => 'ops', 'name' => 'Operations'], $admin);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('its own parent');

        $this->structure()->updateBusinessUnit($unit, ['name' => 'Operations', 'parent_id' => $unit->getKey()], $admin);
    }

    #[Test]
    public function a_unit_cannot_be_moved_under_its_own_descendant(): void
    {
        // VAL-BU-LOOP-001, the case a naive check misses. No relational
        // constraint can express "not an ancestor of itself".
        $admin = $this->admin();

        $parent = $this->structure()->createBusinessUnit(['code' => 'grp', 'name' => 'Group'], $admin);
        $child = $this->structure()->createBusinessUnit(['code' => 'div', 'name' => 'Division', 'parent_id' => $parent->getKey()], $admin);
        $grandchild = $this->structure()->createBusinessUnit(['code' => 'sub', 'name' => 'Sub', 'parent_id' => $child->getKey()], $admin);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('loop');

        $this->structure()->updateBusinessUnit($parent, ['name' => 'Group', 'parent_id' => $grandchild->getKey()], $admin);
    }

    #[Test]
    public function a_unit_code_is_unique_within_the_organisation(): void
    {
        $admin = $this->admin();
        $this->structure()->createBusinessUnit(['code' => 'fin', 'name' => 'Finance'], $admin);

        $this->expectException(InvalidArgumentException::class);

        // Normalised as well as checked, so FIN and fin cannot both exist and
        // make "the Finance unit" ambiguous.
        $this->structure()->createBusinessUnit(['code' => 'FIN', 'name' => 'Finance Again'], $admin);
    }

    #[Test]
    public function a_disabled_unit_takes_no_new_team(): void
    {
        // VAL-BU-INACTIVE-001. It keeps everything already in it - history stays
        // auditable - and takes nothing new.
        $admin = $this->admin();
        $unit = $this->structure()->createBusinessUnit(['code' => 'old', 'name' => 'Old Division'], $admin);
        $this->structure()->updateBusinessUnit($unit, ['name' => 'Old Division', 'status' => LifecycleStatus::Disabled], $admin);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('disabled');

        $this->structure()->createTeam(['code' => 'tm', 'name' => 'Team', 'business_unit_id' => $unit->getKey()], $admin);
    }

    #[Test]
    public function moving_a_team_between_units_is_its_own_audit_event(): void
    {
        // ADM-004 asks for it specifically: moving a team changes who reports
        // where, which is a different fact from renaming one.
        $admin = $this->admin();
        $this->actingAs($admin);

        $first = $this->structure()->createBusinessUnit(['code' => 'a', 'name' => 'A'], $admin);
        $second = $this->structure()->createBusinessUnit(['code' => 'b', 'name' => 'B'], $admin);
        $team = $this->structure()->createTeam(['code' => 't', 'name' => 'Team', 'business_unit_id' => $first->getKey()], $admin);

        $this->structure()->updateTeam($team, ['name' => 'Team', 'business_unit_id' => $second->getKey()], $admin);

        $this->assertDatabaseHas('audit_events', ['action' => 'team.reassigned']);
    }

    #[Test]
    public function a_hierarchy_path_reads_from_the_top_down(): void
    {
        $admin = $this->admin();
        $parent = $this->structure()->createBusinessUnit(['code' => 'grp', 'name' => 'Group'], $admin);
        $child = $this->structure()->createBusinessUnit(['code' => 'div', 'name' => 'Division', 'parent_id' => $parent->getKey()], $admin);

        $this->assertSame('Group / Division', BusinessUnit::query()->find($child->getKey())->path());
    }

    /* ---- Access reviews ---------------------------------------------- */

    private function reviews(): AccessReviewService
    {
        return app(AccessReviewService::class);
    }

    #[Test]
    public function opening_a_review_snapshots_the_access_that_exists_now(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $subject = $this->placed('Sam', Role::Analyst, 'sam@example.test');

        $role = app(RoleRegistry::class)->create('reviewer', 'Reviewer', Role::Analyst, null, $admin);
        app(UserRegistry::class)->assignRole($subject, $role, $admin);
        app(UserRegistry::class)->grantEntitlement($subject, BusinessDomain::Sales, $admin);

        $review = $this->reviews()->create('Q3 review', null, null, $admin);
        $this->reviews()->open($review, $admin);

        $items = $review->refresh()->items;

        // One item per grant: the role and the domain, both reviewed, in one
        // list rather than two.
        $this->assertCount(2, $items);
        $this->assertEqualsCanonicalizing(
            ['role', 'entitlement'],
            $items->pluck('subject_type')->all(),
        );
    }

    #[Test]
    public function a_snapshot_is_taken_once(): void
    {
        $admin = $this->admin();
        $review = $this->reviews()->create('Q3 review', null, null, $admin);
        $this->reviews()->open($review, $admin);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('taken once');

        // Re-opening would regenerate the snapshot and destroy the evidence of
        // what access looked like when it was taken.
        $this->reviews()->open($review->refresh(), $admin);
    }

    #[Test]
    public function a_review_cannot_be_completed_while_anything_is_undecided(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $subject = $this->placed('Sam', Role::Analyst, 'sam@example.test');
        app(UserRegistry::class)->grantEntitlement($subject, BusinessDomain::Sales, $admin);

        $review = $this->reviews()->create('Q3 review', null, null, $admin);
        $this->reviews()->open($review, $admin);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not evidence');

        // Silence is recorded as silence. An item nobody looked at is never an
        // implicit "keep", because a review where half the items were ignored
        // is a finding.
        $this->reviews()->complete($review->refresh(), $admin);
    }

    #[Test]
    public function applying_a_review_revokes_through_the_same_path_as_a_manual_change(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $subject = $this->placed('Sam', Role::Analyst, 'sam@example.test');
        app(UserRegistry::class)->grantEntitlement($subject, BusinessDomain::Sales, $admin);

        $review = $this->reviews()->create('Q3 review', null, null, $admin);
        $this->reviews()->open($review, $admin);

        $item = $review->refresh()->items()->firstOrFail();
        $this->reviews()->decide($item, ReviewDecision::Revoke, 'No longer in Sales', $admin);
        $this->reviews()->complete($review->refresh(), $admin);

        $revoked = $this->reviews()->apply($review->refresh(), $admin);

        $this->assertSame(1, $revoked);
        $this->assertFalse($subject->refresh()->isEntitledTo(BusinessDomain::Sales));

        // Through UserRegistry, not a direct delete, so the revocation gets the
        // same audit event as one made by hand. A bulk operation that bypasses
        // the rules is how a bulk operation becomes the way around the rules.
        $this->assertDatabaseHas('audit_events', ['action' => 'user.entitlement.revoked']);
    }

    #[Test]
    public function applying_twice_revokes_nothing_the_second_time(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $subject = $this->placed('Sam', Role::Analyst, 'sam@example.test');
        app(UserRegistry::class)->grantEntitlement($subject, BusinessDomain::Sales, $admin);

        $review = $this->reviews()->create('Q3 review', null, null, $admin);
        $this->reviews()->open($review, $admin);
        $this->reviews()->decide($review->refresh()->items()->firstOrFail(), ReviewDecision::Revoke, null, $admin);
        $this->reviews()->complete($review->refresh(), $admin);

        $this->assertSame(1, $this->reviews()->apply($review->refresh(), $admin));
        $this->assertSame(0, $this->reviews()->apply($review->refresh(), $admin));
    }

    #[Test]
    public function an_item_keeps_the_label_it_had_when_the_snapshot_was_taken(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $subject = $this->placed('Sam', Role::Analyst, 'sam@example.test');

        $role = app(RoleRegistry::class)->create('old_name', 'Old Name', Role::Analyst, null, $admin);
        app(UserRegistry::class)->assignRole($subject, $role, $admin);

        $review = $this->reviews()->create('Q3 review', null, null, $admin);
        $this->reviews()->open($review, $admin);

        app(RoleRegistry::class)->update($role, 'Completely Different Name', null, LifecycleStatus::Active, $admin);

        // The evidence has to say what the reviewer saw, not what is true now.
        $item = $review->refresh()->items()->firstOrFail();
        $this->assertStringContainsString('Old Name', $item->subject_label);
        $this->assertStringNotContainsString('Completely Different', $item->subject_label);
    }

    #[Test]
    public function a_decision_is_audited_as_it_is_made(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $subject = $this->placed('Sam', Role::Analyst, 'sam@example.test');
        app(UserRegistry::class)->grantEntitlement($subject, BusinessDomain::People, $admin);

        $review = $this->reviews()->create('Q3 review', null, null, $admin);
        $this->reviews()->open($review, $admin);
        $this->reviews()->decide($review->refresh()->items()->firstOrFail(), ReviewDecision::Keep, 'Still needed', $admin);

        // Each decision on its own, not batched at submit, so the trail says who
        // decided what rather than who pressed the button.
        $event = AuditEvent::withoutOrganisationScope()->where('action', 'access_review.decided')->firstOrFail();

        $this->assertSame('keep', $event->after_summary['decision']);
        $this->assertSame('Still needed', $event->reason);
    }

    #[Test]
    public function a_completed_review_cannot_be_cancelled(): void
    {
        $admin = $this->admin();
        $review = $this->reviews()->create('Empty review', null, null, $admin);
        $this->reviews()->open($review, $admin);
        $this->reviews()->complete($review->refresh(), $admin);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('evidence');

        $this->reviews()->cancel($review->refresh(), $admin);
    }

    #[Test]
    public function a_primary_tier_is_never_put_into_a_review(): void
    {
        // Changing a tier has invariants of its own - the last System
        // Administrator among them - and a bulk decision screen would route
        // around them.
        $admin = $this->admin();
        $review = $this->reviews()->create('Q3 review', null, null, $admin);
        $this->reviews()->open($review, $admin);

        $types = $review->refresh()->items()->pluck('subject_type')->unique()->all();

        $this->assertNotContains('tier', $types);
        $this->assertSame(0, AccessReviewItem::query()->where('subject_type', 'tier')->count());
    }
}
