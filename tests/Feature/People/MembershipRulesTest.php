<?php

declare(strict_types=1);

namespace Tests\Feature\People;

use App\Modules\People\Models\Group;
use App\Modules\People\Models\GroupMembership;
use App\Modules\People\Models\GroupStatus;
use App\Modules\People\Services\GroupService;
use App\Modules\People\Support\PeopleViolation;
use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;
use App\Modules\Platform\Models\User;
use App\Modules\Platform\Models\UserStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Support\OrganisationFactory;
use Tests\Support\PeopleFactory;
use Tests\TestCase;

/**
 * Who may be in a group, and what the history is allowed to say afterwards.
 *
 * The rule that needed a design correction is N42: P1-01 keys team membership on
 * (team_id, user_id, joined_at) over DATE-valued timing, which cannot represent
 * join -> leave -> rejoin on one calendar day. The second period carries the
 * same three key values as the first and the database refuses it, with an
 * integrity error about something the administrator did not do wrong. P1-03 does
 * not copy that key shape, and this file is where that is proved rather than
 * asserted in a comment.
 */
final class MembershipRulesTest extends TestCase
{
    use RefreshDatabase;

    private OrganisationFactory $make;

    private PeopleFactory $people;

    protected function setUp(): void
    {
        parent::setUp();

        $this->make = new OrganisationFactory;
        $this->people = new PeopleFactory;
    }

    /**
     * Negative case 30. A CROSS-ORGANISATION MEMBERSHIP IS REFUSED.
     *
     * Every P1-01 rule requires membership to stay inside one organisation.
     *
     * Mutation: drop the same-organisation check in refuseUnlessJoinable().
     */
    public function test_somebody_from_another_organisation_cannot_join(): void
    {
        $ours = $this->make->organisation('Ours');
        $theirs = $this->make->organisation('Theirs');

        $admin = $this->make->user($ours, administrator: true);
        $outsider = $this->make->user($theirs);

        $group = $this->people->group($ours);

        $this->actingAsUser($admin)
            ->post("/console/people/groups/{$group->id}/members", ['user_id' => $outsider->id])
            ->assertSessionHasErrors('people');

        $this->assertSame(
            'That person belongs to a different organisation, so they cannot join this group.',
            session('errors')->get('people')[0]
        );

        $this->assertSame(0, GroupMembership::query()->count());
    }

    /**
     * Negative case 31. Somebody with NO organisation cannot join.
     *
     * D-16 fails closed: NULL is not a wildcard.
     *
     * Mutation: let NULL pass the same-organisation comparison.
     */
    public function test_somebody_with_no_organisation_cannot_join(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);
        $unassigned = $this->make->user();

        $group = $this->people->group($organisation);

        $this->actingAsUser($admin)
            ->post("/console/people/groups/{$group->id}/members", ['user_id' => $unassigned->id])
            ->assertSessionHasErrors('people');

        $this->assertSame(
            'That person is not associated with an organisation yet, so they cannot join a group.',
            session('errors')->get('people')[0]
        );

        $this->assertSame(0, GroupMembership::query()->count());
    }

    /**
     * Negative case 32. A SECOND CURRENT MEMBERSHIP OF ONE GROUP IS REFUSED.
     *
     * MySQL 8.4 has no partial index, so the ideal
     * UNIQUE(group_id, user_id) WHERE left_at IS NULL cannot be declared. The
     * invariant is held by a LOCKING READ inside the write transaction, and this
     * proves the service is doing that work rather than the database.
     *
     * Mutation: remove the current-membership check in addMember().
     */
    public function test_a_second_current_membership_is_refused(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);
        $person = $this->make->user($organisation);

        $group = $this->people->group($organisation);

        $this->actingAsUser($admin)
            ->post("/console/people/groups/{$group->id}/members", ['user_id' => $person->id])
            ->assertSessionHasNoErrors();

        $this->actingAsUser($admin)
            ->post("/console/people/groups/{$group->id}/members", ['user_id' => $person->id])
            ->assertSessionHasErrors('people');

        $this->assertSame(
            'That person is already in this group.',
            session('errors')->get('people')[0]
        );

        $this->assertSame(1, GroupMembership::query()->whereNull('left_at')->count());
    }

    /**
     * And the read that holds it is a LOCKING one, inside the transaction.
     *
     * Same two instruments, same limitation, as the administrator-count guard:
     * SQLite's grammar compiles lockForUpdate() to nothing, so the SQL half runs
     * in CI's MySQL job and the source is asserted here.
     *
     * Mutation: drop lockForUpdate from the current-membership read.
     */
    public function test_the_current_membership_read_is_locking_and_inside_the_transaction(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);
        $person = $this->make->user($organisation);

        $group = $this->people->group($organisation);

        // Against the BASELINE depth: RefreshDatabase wraps the test in its own
        // transaction, so comparing against zero would measure the harness
        // rather than the service. See UserLifecycleTest for the mutation that
        // found this.
        $baseline = DB::transactionLevel();

        $statements = [];

        DB::listen(function ($query) use (&$statements): void {
            $statements[] = ['sql' => strtolower($query->sql), 'depth' => DB::transactionLevel()];
        });

        app(GroupService::class)->addMember($group, $person, $admin);

        $reads = array_values(array_filter(
            $statements,
            fn (array $statement): bool => str_contains($statement['sql'], 'select')
                && str_contains($statement['sql'], 'group_memberships')
                && str_contains($statement['sql'], 'left_at')
        ));

        $this->assertNotEmpty($reads, 'The current-membership check never ran.');

        foreach ($reads as $read) {
            $this->assertGreaterThan(
                $baseline,
                $read['depth'],
                'The current-membership check runs outside the transaction, so two simultaneous '
                .'adds would both see no current membership and both insert one.'
            );
        }

        if (DB::connection()->getDriverName() === 'sqlite') {
            $source = file_get_contents(base_path('app/Modules/People/Services/GroupService.php')) ?: '';

            $this->assertMatchesRegularExpression(
                "/whereNull\('left_at'\)\s*->lockForUpdate\(\)/",
                $source,
                'The current-membership read does not ask for a lock, and no database constraint '
                .'can hold this invariant: MySQL 8.4 has no partial index.'
            );

            return;
        }

        $locking = array_filter($reads, fn (array $read): bool => str_contains($read['sql'], 'for update'));

        $this->assertNotEmpty($locking, 'The current-membership read is not a locking read.');
    }

    /**
     * Negative case 33. An inactive person, or an inactive group, is refused.
     *
     * Both directions, because a change with no effect that looks like a change
     * is worse than a refusal.
     *
     * Mutation: allow either.
     */
    public function test_an_inactive_person_or_an_inactive_group_cannot_gain_a_member(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);

        $inactivePerson = $this->make->user($organisation, status: UserStatus::Inactive);
        $activePerson = $this->make->user($organisation);

        $activeGroup = $this->people->group($organisation, 'Active Group');
        $inactiveGroup = $this->people->group($organisation, 'Inactive Group', GroupStatus::Inactive);

        $this->actingAsUser($admin)
            ->post("/console/people/groups/{$activeGroup->id}/members", ['user_id' => $inactivePerson->id])
            ->assertSessionHasErrors('people');

        $this->assertSame(
            'That person is inactive. Reactivate them before adding them to a group.',
            session('errors')->get('people')[0]
        );

        $this->actingAsUser($admin)
            ->post("/console/people/groups/{$inactiveGroup->id}/members", ['user_id' => $activePerson->id])
            ->assertSessionHasErrors('people');

        $this->assertSame(
            'This group is inactive. Reactivate it before adding members.',
            session('errors')->get('people')[0]
        );

        $this->assertSame(0, GroupMembership::query()->count());
    }

    /**
     * Negative case 42. JOIN, LEAVE, REJOIN, LEAVE, REJOIN - ALL ON ONE
     * CALENDAR DAY.
     *
     * This is the P1-01 collision, reproduced deliberately. Under
     * UNIQUE(group_id, user_id, joined_at) over dates, the second join carries
     * the same three values as the first and the database refuses it with an
     * integrity error about something the administrator did not do wrong.
     *
     * Three periods must exist afterwards, and their boundaries must be
     * distinguishable - which is why joined_at is a DATETIME and not a date.
     *
     * Mutation: key group_memberships on (group_id, user_id, joined_at), or make
     * the columns dates.
     */
    public function test_a_person_may_join_leave_and_rejoin_twice_on_one_day(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);
        $person = $this->make->user($organisation);

        $group = $this->people->group($organisation);

        $day = now()->startOfDay()->addHours(9);

        for ($round = 0; $round < 3; $round++) {
            $this->travelTo($day->copy()->addMinutes($round * 20));

            $this->actingAsUser($admin)
                ->post("/console/people/groups/{$group->id}/members", ['user_id' => $person->id])
                ->assertSessionHasNoErrors();

            if ($round < 2) {
                $this->travelTo($day->copy()->addMinutes($round * 20 + 10));

                $current = GroupMembership::query()
                    ->where('group_id', $group->id)
                    ->where('user_id', $person->id)
                    ->whereNull('left_at')
                    ->sole();

                $this->actingAsUser($admin)
                    ->patch("/console/people/groups/{$group->id}/members/{$current->id}/remove")
                    ->assertSessionHasNoErrors();
            }
        }

        $this->travelBack();

        $periods = GroupMembership::query()
            ->where('group_id', $group->id)
            ->where('user_id', $person->id)
            ->orderBy('joined_at')
            ->get();

        $this->assertCount(3, $periods, 'Three membership periods on one day were not all recorded.');

        $this->assertSame(
            [true, true, false],
            $periods->map(fn (GroupMembership $period): bool => $period->left_at !== null)->all(),
            'The first two periods are not both ended with the third still current.'
        );

        // Every period starts on the same calendar day - so a date-keyed table
        // could not have held them.
        $this->assertSame(
            [$day->toDateString()],
            $periods->map(fn (GroupMembership $period): string => $period->joined_at->toDateString())
                ->unique()->values()->all()
        );

        // And their boundaries are distinguishable, which is what makes the
        // history readable rather than merely storable.
        $this->assertSame(
            3,
            $periods->map(fn (GroupMembership $period): string => $period->joined_at->toDateTimeString())
                ->unique()->count(),
            'Two periods share a start time, so the history cannot say which came first.'
        );
    }

    /**
     * Negative case 43. A new period may not start before the previous one
     * ended.
     *
     * Overlapping periods would say somebody was in the group twice at once,
     * which is not a thing that can happen.
     *
     * The clock is moved BACKWARDS between leaving and rejoining, which is what
     * a real clock correction does.
     *
     * Mutation: remove the lastLeft comparison in addMember().
     */
    public function test_a_rejoin_cannot_start_before_the_previous_period_ended(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);
        $person = $this->make->user($organisation);

        $group = $this->people->group($organisation);

        $left = now()->startOfDay()->addHours(15);

        $this->people->membership($group, $person, $left->copy()->subHour(), $left);

        // The clock now reads EARLIER than the moment the last period ended.
        $this->travelTo($left->copy()->subMinutes(30));

        $this->actingAsUser($admin)
            ->post("/console/people/groups/{$group->id}/members", ['user_id' => $person->id])
            ->assertSessionHasNoErrors();

        $this->travelBack();

        $periods = GroupMembership::query()
            ->where('group_id', $group->id)
            ->orderBy('joined_at')
            ->get();

        $this->assertCount(2, $periods);

        $this->assertTrue(
            $periods[1]->joined_at->greaterThanOrEqualTo($periods[0]->left_at),
            'A membership period starts before the previous one ended, so the history says this '
            .'person was in the group twice at the same time.'
        );
    }

    /**
     * Negative case 44. NO DATABASE INTEGRITY ERROR EVER REACHES THE
     * ADMINISTRATOR.
     *
     * Every membership refusal is a sentence written for a person. The failure
     * this guards against is the service letting a constraint surface, which
     * produces a message about a column name for something the administrator did
     * nothing wrong to cause.
     *
     * Mutation: remove a service guard and let the database refuse instead.
     */
    public function test_no_membership_refusal_exposes_a_database_error(): void
    {
        $organisation = $this->make->organisation();
        $theirs = $this->make->organisation('Theirs');

        $admin = $this->make->user($organisation, administrator: true);

        $group = $this->people->group($organisation);
        $inactiveGroup = $this->people->group($organisation, 'Inactive', GroupStatus::Inactive);

        $member = $this->make->user($organisation);
        $this->people->membership($group, $member);

        $attempts = [
            ['group' => $group->id, 'user' => $this->make->user($theirs)->id],
            ['group' => $group->id, 'user' => $this->make->user()->id],
            ['group' => $group->id, 'user' => $member->id],
            ['group' => $group->id, 'user' => $this->make->user($organisation, status: UserStatus::Inactive)->id],
            ['group' => $inactiveGroup->id, 'user' => $this->make->user($organisation)->id],
            ['group' => $group->id, 'user' => 999999],
        ];

        foreach ($attempts as $attempt) {
            $response = $this->actingAsUser($admin)
                ->post("/console/people/groups/{$attempt['group']}/members", ['user_id' => $attempt['user']]);

            $response->assertSessionHasErrors('people');

            $message = session('errors')->get('people')[0];

            foreach ([
                'SQLSTATE', 'Integrity constraint', 'constraint', 'UNIQUE', 'FOREIGN KEY',
                'group_id', 'user_id', 'left_at', 'group_memberships', 'Exception', 'SQL',
            ] as $leak) {
                $this->assertStringNotContainsString(
                    $leak,
                    $message,
                    "A refusal exposed [{$leak}]: {$message}"
                );
            }

            // And it is a sentence, not a code.
            $this->assertMatchesRegularExpression(
                '/^[A-Z].*\.$/s',
                $message,
                "A refusal is not written as a sentence: {$message}"
            );
        }
    }

    /**
     * Negative case 44, extended to the GROUP screens.
     *
     * The original case covered membership only. Writing the Product Owner test
     * script exposed the gap: step 21 asks the Product Owner to create a
     * duplicate group deliberately, and a duplicate name would have handed them
     * a database integrity error for doing exactly what the script told them to
     * do. Found by reading the script back against the code, not by a test.
     *
     * Mutation: remove refuseIfTaken() and let groups_org_name_uq surface.
     */
    public function test_no_group_refusal_exposes_a_database_error(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);

        $this->actingAsUser($admin)
            ->post('/console/people/groups', ['name' => 'Finance Approvers', 'code' => 'FIN'])
            ->assertSessionHasNoErrors();

        $other = $this->people->group($organisation, 'Fire Wardens', code: 'FIRE');

        $attempts = [
            ['POST', '/console/people/groups', ['name' => 'Finance Approvers', 'code' => null],
                'A group called that already exists. Open it, or choose another name.'],
            ['POST', '/console/people/groups', ['name' => 'Something Else', 'code' => 'FIN'],
                'That code is already used by another group. Choose another one.'],
            ['PUT', "/console/people/groups/{$other->id}", ['name' => 'Finance Approvers', 'code' => 'FIRE'],
                'A group called that already exists. Open it, or choose another name.'],
            ['PUT', "/console/people/groups/{$other->id}", ['name' => 'Fire Wardens', 'code' => 'FIN'],
                'That code is already used by another group. Choose another one.'],
        ];

        foreach ($attempts as [$method, $uri, $payload, $expected]) {
            $this->actingAsUser($admin)->call($method, $uri, $payload)->assertSessionHasErrors('people');

            $message = session('errors')->get('people')[0];

            $this->assertSame($expected, $message);

            foreach (['SQLSTATE', 'Integrity constraint', 'UNIQUE', 'groups_org', 'Exception'] as $leak) {
                $this->assertStringNotContainsString($leak, $message, "A refusal exposed [{$leak}].");
            }
        }

        // Renaming a group to the name it already has is not a duplicate.
        $this->actingAsUser($admin)
            ->put("/console/people/groups/{$other->id}", ['name' => 'Fire Wardens', 'code' => 'FIRE'])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Group::query()->count(), 'A refused create still made a group.');
    }

    /**
     * Negative case 29. MEMBERSHIP HISTORY CANNOT BE DELETED.
     *
     * Ending a membership sets left_at. The row is the evidence that somebody
     * was once a member, which is the only reason to keep a membership table
     * rather than a list of current members.
     *
     * Mutation: add a DELETE route for a membership.
     */
    public function test_no_route_can_delete_a_membership(): void
    {
        foreach (Route::getRoutes() as $route) {
            if (! in_array('DELETE', $route->methods(), true)) {
                continue;
            }

            foreach (['members', 'membership'] as $protected) {
                $this->assertStringNotContainsString(
                    $protected,
                    $route->uri(),
                    "Route [{$route->uri()}] would destroy retained membership history."
                );
            }
        }

        // And ending one keeps the row rather than removing it.
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);
        $person = $this->make->user($organisation);

        $group = $this->people->group($organisation);
        $membership = $this->people->membership($group, $person);

        $this->actingAsUser($admin)
            ->patch("/console/people/groups/{$group->id}/members/{$membership->id}/remove")
            ->assertSessionHasNoErrors();

        $ended = GroupMembership::query()->find($membership->id);

        $this->assertNotNull($ended, 'Ending a membership deleted the row.');
        $this->assertNotNull($ended->left_at);
    }

    /** Ending a membership twice is refused rather than silently accepted. */
    public function test_a_membership_that_has_already_ended_cannot_be_ended_again(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);
        $person = $this->make->user($organisation);

        $group = $this->people->group($organisation);
        $membership = $this->people->membership($group, $person, now()->subYear(), now()->subMonth());

        $endedAt = $membership->left_at;

        $this->actingAsUser($admin)
            ->patch("/console/people/groups/{$group->id}/members/{$membership->id}/remove")
            ->assertSessionHasErrors('people');

        $this->assertSame(
            'That membership has already ended.',
            session('errors')->get('people')[0]
        );

        $this->assertTrue(
            $endedAt->equalTo($membership->fresh()->left_at),
            'A second removal moved the end date of a membership that had already ended.'
        );
    }

    /**
     * Negative case 28. A group with ANY membership history cannot be purged -
     * not only one with current members.
     *
     * Mutation: check only current members.
     */
    public function test_a_group_with_only_ended_memberships_cannot_be_purged(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);
        $person = $this->make->user($organisation);

        $group = $this->people->group($organisation);

        $this->people->membership($group, $person, now()->subYears(2), now()->subYear());

        $this->actingAsUser($admin)
            ->delete("/console/people/groups/{$group->id}")
            ->assertSessionHasErrors('people');

        $this->assertStringContainsString(
            'group membership history exists',
            session('errors')->get('people')[0]
        );

        $this->assertNotNull($group->fresh(), 'A group with membership history was purged.');
    }

    /** A group nobody has ever been in can be purged, or the guard above proves nothing. */
    public function test_a_group_nobody_has_ever_joined_can_be_purged(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);

        $group = $this->people->group($organisation);

        $this->actingAsUser($admin)
            ->delete("/console/people/groups/{$group->id}")
            ->assertSessionHasNoErrors();

        $this->assertNull($group->fresh());
    }

    /** The service refuses directly, not only through the route. */
    public function test_the_service_refuses_a_cross_organisation_membership_directly(): void
    {
        $ours = $this->make->organisation('Ours');
        $theirs = $this->make->organisation('Theirs');

        $admin = $this->make->user($ours, administrator: true);
        $outsider = $this->make->user($theirs);

        $group = $this->people->group($ours);

        $this->expectException(PeopleViolation::class);

        app(GroupService::class)->addMember($group, $outsider, $admin);
    }

    private function actingAsUser(User $user): self
    {
        return $this->withSession([
            EnsureSessionIsCurrent::SESSION_USER_ID => $user->id,
            EnsureSessionIsCurrent::SESSION_AUTHENTICATED_AT => now()->toIso8601String(),
        ]);
    }
}
