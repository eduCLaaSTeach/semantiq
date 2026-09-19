<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Modules\Access\Services\RoleAssignmentService;
use App\Modules\Access\Support\RoleCode;
use App\Modules\Audit\Models\AuditChainHead;
use App\Modules\Organisation\Models\Organisation;
use App\Modules\Organisation\Models\TeamMembership;
use App\Modules\Organisation\Services\ManagementService;
use App\Modules\Organisation\Services\MembershipService;
use App\Modules\Organisation\Services\OrganisationService;
use App\Modules\Organisation\Services\StructureService;
use App\Modules\People\Models\Group;
use App\Modules\People\Models\GroupStatus;
use App\Modules\People\Services\GroupService;
use App\Modules\People\Services\UserDirectoryService;
use App\Modules\Platform\Models\User;
use App\Modules\Platform\Models\UserStatus;
use App\Modules\Platform\Security\EvidenceNotRecorded;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AccessFactory;
use Tests\Support\OrganisationFactory;
use Tests\TestCase;
use Throwable;

/**
 * D-111 ACROSS THE WHOLE STATE-CHANGE SURFACE, not only the paths that were
 * already transactional.
 *
 * THE GAP THIS CLOSES. The DESIGN said a state change and its evidence share a
 * transaction. Fourteen emitters did not - across six modules - and the claim
 * had been reviewed and approved. In every one of them a failed audit write
 * would have raised AFTER the change had already committed: the operation
 * reported failure and had in fact happened.
 *
 * EVERY CASE ASSERTS THE BUSINESS STATE, never the exception. An assertion that
 * the call threw passes just as happily when the change survived, which is
 * precisely the defect.
 */
final class AuditRollbackTest extends TestCase
{
    use RefreshDatabase;

    private OrganisationFactory $make;

    private AccessFactory $access;

    private Organisation $organisation;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->make = new OrganisationFactory;
        $this->access = new AccessFactory;
        $this->organisation = $this->make->organisation();
        $this->admin = $this->make->user($this->organisation, administrator: true);
    }

    /**
     * The Product Owner's first example.
     *
     * Mutation: remove DB::transaction from OrganisationService::updateProfile.
     * The name is changed and the failure is reported afterwards.
     */
    public function test_an_organisation_update_rolls_back(): void
    {
        $before = $this->organisation->name;

        $this->refuses(fn () => app(OrganisationService::class)
            ->updateProfile($this->organisation, ['name' => 'Renamed Without Evidence'], $this->admin));

        $this->assertSame($before, $this->organisation->fresh()->name, 'The organisation was renamed unevidenced.');
    }

    /**
     * Mutation: remove DB::transaction from UserDirectoryService::reactivate.
     * Somebody regains the ability to sign in with no record of it.
     */
    public function test_a_user_reactivation_rolls_back(): void
    {
        $person = $this->make->user($this->organisation, status: UserStatus::Inactive);

        $this->refuses(fn () => app(UserDirectoryService::class)->reactivate($person, $this->admin));

        $this->assertSame(
            UserStatus::Inactive,
            $person->fresh()->status,
            'Somebody regained the ability to sign in with nothing recording it.'
        );
    }

    /** Mutation: remove DB::transaction from GroupService::deactivate. */
    public function test_a_group_deactivation_rolls_back(): void
    {
        $group = $this->group();

        $this->refuses(fn () => app(GroupService::class)->deactivate($group, $this->admin));

        $this->assertSame(GroupStatus::Active, $group->fresh()->status);
    }

    /** Mutation: remove DB::transaction from GroupService::reactivate. */
    public function test_a_group_reactivation_rolls_back(): void
    {
        $group = $this->group();
        $group->forceFill(['status' => GroupStatus::Inactive->value])->save();

        $this->refuses(fn () => app(GroupService::class)->reactivate($group->fresh(), $this->admin));

        $this->assertSame(GroupStatus::Inactive, $group->fresh()->status);
    }

    /** Mutation: remove DB::transaction from GroupService::removeMember. */
    public function test_a_group_membership_removal_rolls_back(): void
    {
        $group = $this->group();
        $person = $this->make->user($this->organisation);

        $membership = app(GroupService::class)->addMember($group, $person, $this->admin);

        $this->refuses(fn () => app(GroupService::class)->removeMember($membership->fresh(), $this->admin));

        $this->assertNull(
            $membership->fresh()->left_at,
            'Somebody was removed from a group with nothing recording it.'
        );
    }

    /** Mutation: remove DB::transaction from MembershipService::remove. */
    public function test_a_team_membership_removal_rolls_back(): void
    {
        $team = $this->make->team($this->make->department($this->make->businessUnit($this->organisation)));
        $person = $this->make->user($this->organisation);

        $membership = app(MembershipService::class)->add($team, $person, $this->admin);

        $this->refuses(fn () => app(MembershipService::class)->remove($membership->fresh(), $this->admin));

        $this->assertNull(TeamMembership::query()->whereKey($membership->id)->value('left_at'));
    }

    /** Mutation: remove DB::transaction from StructureService::setStatus. */
    public function test_a_structure_deactivation_rolls_back(): void
    {
        $unit = $this->make->businessUnit($this->organisation);

        $this->refuses(fn () => app(StructureService::class)->deactivateBusinessUnit($unit, $this->admin));

        $this->assertSame('active', $unit->fresh()->status->value);
    }

    /** Mutation: remove DB::transaction from ManagementService::clearManager. */
    public function test_clearing_a_manager_rolls_back(): void
    {
        $person = $this->make->user($this->organisation);
        $manager = $this->make->user($this->organisation);

        app(ManagementService::class)->setManager($person, $manager, $this->admin);

        $this->refuses(fn () => app(ManagementService::class)->clearManager($person, $this->admin));

        $this->assertDatabaseHas('management_relationships', [
            'user_id' => $person->id,
            'effective_to' => null,
        ]);
    }

    /**
     * THE NON-VACUOUS HALF, and the one that would catch a "fix" that simply
     * stopped recording.
     *
     * Mutation: make AuditWriter swallow the failure. Every case above passes
     * and this one fails, because the evidence would never have been written.
     */
    public function test_a_successful_change_commits_with_its_evidence(): void
    {
        $service = app(OrganisationService::class);

        $service->updateProfile($this->organisation, ['name' => 'Acme Holdings'], $this->admin);

        $this->assertSame('Acme Holdings', $this->organisation->fresh()->name);
        $this->assertDatabaseHas('audit_events', [
            'event' => 'organisation.updated',
            'organisation_id' => $this->organisation->id,
            'actor_user_id' => $this->admin->id,
        ]);
    }

    /**
     * The paths that were ALREADY transactional stay atomic. Without this, a
     * change that broke P1-05's boundary while fixing P1-01's would pass.
     *
     * Mutation: remove the administrator-set boundary from
     * RoleAssignmentService::assign.
     */
    public function test_an_already_atomic_role_grant_still_rolls_back(): void
    {
        $subject = $this->make->user($this->organisation);

        $this->refuses(fn () => app(RoleAssignmentService::class)
            ->assign($subject, RoleCode::BusinessUser, $this->organisation->id, $this->admin));

        $this->assertDatabaseMissing('role_assignments', [
            'user_id' => $subject->id,
            'role_code' => RoleCode::BusinessUser->value,
        ]);
    }

    private function group(): Group
    {
        return app(GroupService::class)->create($this->organisation, [
            'name' => 'Finance Team',
            'description' => null,
        ], $this->admin);
    }

    /**
     * Run the operation with the evidence store broken, and require it to
     * refuse.
     *
     * The chain head is removed rather than the table dropped: MySQL commits
     * the open transaction implicitly on DDL, so a drop would end the test's
     * own transaction and the failure would be about savepoints rather than
     * about audit. That cost four green-locally, red-on-MySQL cases once
     * already.
     */
    private function refuses(callable $operation): void
    {
        AuditChainHead::query()->delete();

        try {
            $operation();
            $this->fail('The operation completed although its evidence could not be written.');
        } catch (EvidenceNotRecorded) {
            // Expected.
        } catch (Throwable $other) {
            $this->fail('Expected a refusal about evidence, got: '.$other::class.' - '.$other->getMessage());
        }
    }
}
