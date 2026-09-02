<?php

declare(strict_types=1);

namespace Tests\Feature\People;

use App\Modules\Organisation\Models\ManagementRelationship;
use App\Modules\Organisation\Models\TeamMembership;
use App\Modules\People\Services\UserDirectoryService;
use App\Modules\People\Support\PeopleViolation;
use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;
use App\Modules\Platform\Models\User;
use App\Modules\Platform\Models\UserStatus;
use App\Shared\Lifecycle\PurgeDependencies;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\OrganisationFactory;
use Tests\Support\PeopleFactory;
use Tests\TestCase;

/**
 * Deactivation, reactivation, organisation change and the guarded purge.
 *
 * D-36 settles the shape: deactivation is the SAFE action, so it is never
 * blocked by dependencies - a person leaving is exactly when they have the most
 * history, and a guard that refuses then makes the safe action impossible.
 *
 * There is one exception, and it is not a dependency guard: correction 2. P1-03
 * must not permit an action that leaves SemantIQ with zero active System
 * Administrators, because bootstrap closes on the EXISTENCE of an administrator
 * record rather than on there being an active one - so there would be no route
 * back through the application at all.
 */
final class UserLifecycleTest extends TestCase
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
     * Negative case 19. DEACTIVATION IS NEVER BLOCKED by a dependency.
     *
     * The person under test is loaded with every kind of relationship P1-01 and
     * P1-03 can produce, and deactivation still succeeds. A test on somebody with
     * no relationships would pass against a service that blocked on all of them.
     *
     * Mutation: add a dependency guard to deactivate().
     */
    public function test_a_person_with_every_kind_of_relationship_can_still_be_deactivated(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);

        $leaver = $this->make->user($organisation);
        $report = $this->make->user($organisation);

        $unit = $this->make->businessUnit($organisation);
        $team = $this->make->team($this->make->department($unit));

        TeamMembership::query()->create([
            'organisation_id' => $organisation->id,
            'team_id' => $team->id,
            'user_id' => $leaver->id,
            'joined_at' => now()->subYear(),
        ]);

        ManagementRelationship::query()->create([
            'organisation_id' => $organisation->id,
            'user_id' => $report->id,
            'manager_id' => $leaver->id,
            'effective_from' => now()->subYear(),
        ]);

        $group = $this->people->group($organisation);
        $this->people->membership($group, $leaver);

        $this->actingAsUser($admin)
            ->patch("/console/people/users/{$leaver->id}/deactivate")
            ->assertSessionHasNoErrors();

        $this->assertSame(UserStatus::Inactive, $leaver->fresh()->status);
    }

    /**
     * Negative case 41. THE SOLE ACTIVE SYSTEM ADMINISTRATOR CANNOT BE
     * DEACTIVATED.
     *
     * Without this, production is one click from having nobody who can
     * administer it and no way back in - bootstrap will not reopen, because it
     * closes on the existence of an administrator record.
     *
     * The refusal is the business sentence, and it says what to do instead.
     *
     * Mutation: remove refuseIfLastAdministrator().
     */
    public function test_the_only_active_system_administrator_cannot_be_deactivated(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);

        // Other people exist, and none of them is an administrator - so a guard
        // that counted users rather than administrators would pass wrongly.
        $this->make->user($organisation);
        $this->make->user($organisation);

        $this->actingAsUser($admin)
            ->patch("/console/people/users/{$admin->id}/deactivate")
            ->assertSessionHasErrors('people');

        $this->assertSame(
            'This is the only active System Administrator. Add or retain another active System '
            .'Administrator before deactivating this account.',
            session('errors')->get('people')[0]
        );

        $this->assertSame(UserStatus::Active, $admin->fresh()->status);

        $this->assertSame(
            1,
            User::query()->activeSystemAdministrators()->count(),
            'SemantIQ was left with a different number of active System Administrators.'
        );
    }

    /**
     * Negative case 41b. With TWO active administrators, either may be
     * deactivated.
     *
     * The other half of 41, and the one that makes it non-vacuous: a service
     * that refused whenever the target was an administrator would pass 41 and
     * fail here.
     *
     * Mutation: refuse whenever the target is a System Administrator.
     */
    public function test_with_two_active_administrators_either_may_be_deactivated(): void
    {
        $organisation = $this->make->organisation();

        $first = $this->make->user($organisation, administrator: true);
        $second = $this->make->user($organisation, administrator: true);

        $this->actingAsUser($first)
            ->patch("/console/people/users/{$second->id}/deactivate")
            ->assertSessionHasNoErrors();

        $this->assertSame(UserStatus::Inactive, $second->fresh()->status);

        // And now the survivor cannot be deactivated, which proves the guard
        // counts ACTIVE administrators rather than administrator records.
        $this->actingAsUser($first)
            ->patch("/console/people/users/{$first->id}/deactivate")
            ->assertSessionHasErrors('people');

        $this->assertSame(1, User::query()->activeSystemAdministrators()->count());
    }

    /**
     * Negative case 41c. THE COUNT IS RE-READ INSIDE THE TRANSACTION, WITH A
     * LOCKING READ.
     *
     * A check-then-write would let two administrators deactivate each other
     * concurrently: each reads two, each sees the other as the survivor, and the
     * deployment ends with zero.
     *
     * Two properties, and they need two different instruments:
     *
     *   1. INSIDE THE TRANSACTION - observed behaviourally, from the transaction
     *      depth at the moment the count executes. That works on any engine.
     *   2. A LOCKING READ - observed from the emitted SQL, WHERE THE ENGINE
     *      EMITS IT. SQLite's grammar compiles lockForUpdate() to nothing at
     *      all, so on SQLite there is no "for update" to find and asserting its
     *      presence would fail against correct code, while asserting its absence
     *      would pass against code that never asked for it. On SQLite the source
     *      is asserted instead, and the SQL assertion runs in CI's MySQL job -
     *      which is the engine production uses and the only place the clause
     *      actually exists.
     *
     * That second half is stated rather than glossed: the LOCK ITSELF is not
     * observable in this suite.
     *
     * Mutation: move the check above DB::transaction (caught by 1, on any
     * engine); drop lockForUpdate (caught by 2).
     */
    public function test_the_last_administrator_count_is_a_locking_read_inside_the_transaction(): void
    {
        $organisation = $this->make->organisation();

        $first = $this->make->user($organisation, administrator: true);
        $second = $this->make->user($organisation, administrator: true);

        /*
         * MEASURED AGAINST A BASELINE, not against zero.
         *
         * RefreshDatabase wraps every test in its own transaction, so the depth
         * is already 1 before the service is called. Asserting "depth > 0"
         * therefore passed with the guard moved OUTSIDE the transaction entirely
         * - it was measuring the test harness. Found by the mutation.
         */
        $baseline = DB::transactionLevel();

        $statements = [];

        DB::listen(function ($query) use (&$statements): void {
            $statements[] = ['sql' => strtolower($query->sql), 'depth' => DB::transactionLevel()];
        });

        app(UserDirectoryService::class)->deactivate($second, $first);

        $counting = array_values(array_filter(
            $statements,
            fn (array $statement): bool => str_contains($statement['sql'], 'count(*)')
                && str_contains($statement['sql'], 'platform_role')
        ));

        $this->assertNotEmpty($counting, 'The administrator count was never read.');

        foreach ($counting as $statement) {
            $this->assertGreaterThan(
                $baseline,
                $statement['depth'],
                'The administrator count is read outside the transaction, so a change committed '
                .'between the check and the write would not be seen and two concurrent '
                .'deactivations could leave zero administrators.'
            );
        }

        if (DB::connection()->getDriverName() === 'sqlite') {
            // The clause does not exist on this engine. Assert the code asks for
            // it; CI's MySQL job asserts that it arrives.
            // The guard's own method, not the whole file. Across the file a
            // dotall match could be satisfied by a lockForUpdate() belonging to
            // some other query entirely.
            $source = file_get_contents(base_path('app/Modules/People/Services/UserDirectoryService.php')) ?: '';

            preg_match(
                '/private function refuseIfLastAdministrator\(User \$user\): void\s*\{(.*?)\n    \}/s',
                $source,
                $method
            );

            $this->assertNotEmpty($method, 'refuseIfLastAdministrator() was not found.');

            $this->assertStringContainsString(
                '->lockForUpdate()',
                $method[1],
                'The administrator count does not ask for a locking read. Under MySQL REPEATABLE '
                .'READ a plain SELECT reads the transaction snapshot and cannot see a change '
                .'committed after it opened.'
            );

            return;
        }

        foreach ($counting as $statement) {
            $this->assertStringContainsString(
                'for update',
                $statement['sql'],
                'The administrator count is not a locking read.'
            );
        }
    }

    /**
     * Negative case 20. The dependency summary counts CURRENT relationships
     * only, and omits zero clauses.
     *
     * Nobody should read "manages 0 people" before deciding whether to
     * deactivate somebody, and a summary that counted ended relationships would
     * describe a person's whole career rather than what deactivating them
     * affects now.
     *
     * Mutation: count historical rows too; or emit the zero clauses.
     */
    public function test_the_dependency_summary_counts_only_current_relationships(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);
        $person = $this->make->user($organisation);
        $report = $this->make->user($organisation);

        $unit = $this->make->businessUnit($organisation);
        $team = $this->make->team($this->make->department($unit));

        // One current team membership and one ended one.
        TeamMembership::query()->create([
            'organisation_id' => $organisation->id,
            'team_id' => $team->id,
            'user_id' => $person->id,
            'joined_at' => now()->subYears(2),
            'left_at' => now()->subYear(),
        ]);

        TeamMembership::query()->create([
            'organisation_id' => $organisation->id,
            'team_id' => $team->id,
            'user_id' => $person->id,
            'joined_at' => now()->subMonths(6),
        ]);

        // One ended management relationship, and no current one.
        ManagementRelationship::query()->create([
            'organisation_id' => $organisation->id,
            'user_id' => $report->id,
            'manager_id' => $person->id,
            'effective_from' => now()->subYears(2),
            'effective_to' => now()->subYear(),
        ]);

        $group = $this->people->group($organisation);
        $this->people->membership($group, $person, now()->subYears(2), now()->subYear());

        $phrases = app(UserDirectoryService::class)->currentRelationshipPhrases($person);

        $this->assertSame(
            ['belongs to 1 team'],
            $phrases,
            'The summary does not describe exactly the CURRENT relationships. Ended ones are '
            .'history, and a zero clause is noise nobody should have to read past.'
        );
    }

    /**
     * Negative case 21. DEACTIVATION CHANGES NO MEMBERSHIP OR MANAGEMENT ROW.
     *
     * Reactivation must restore the person as they were, not as a stranger -
     * which is only possible if nothing was ended on the way out.
     *
     * Asserted as a row-by-row comparison of the whole tables, so a change to
     * any column of any row fails, not only to the ones this test thought of.
     *
     * Mutation: have deactivate() end current memberships.
     */
    public function test_deactivation_changes_no_relationship_row(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);
        $person = $this->make->user($organisation);
        $report = $this->make->user($organisation);

        $unit = $this->make->businessUnit($organisation);
        $team = $this->make->team($this->make->department($unit));

        TeamMembership::query()->create([
            'organisation_id' => $organisation->id,
            'team_id' => $team->id,
            'user_id' => $person->id,
            'joined_at' => now()->subYear(),
        ]);

        ManagementRelationship::query()->create([
            'organisation_id' => $organisation->id,
            'user_id' => $report->id,
            'manager_id' => $person->id,
            'effective_from' => now()->subYear(),
        ]);

        $group = $this->people->group($organisation);
        $this->people->membership($group, $person);

        $before = $this->relationshipRows();

        $this->actingAsUser($admin)->patch("/console/people/users/{$person->id}/deactivate");

        $this->assertSame(UserStatus::Inactive, $person->fresh()->status);

        $this->assertEquals(
            $before,
            $this->relationshipRows(),
            'Deactivation altered a membership or management row. Nothing is ended by somebody '
            .'losing access, so that reactivation restores them as they were.'
        );
    }

    /**
     * Negative case 22. Reactivation restores eligibility AND NOTHING ELSE.
     *
     * Nothing was removed, so nothing is rebuilt. A reactivation that recreated
     * a membership would be inventing history.
     *
     * Mutation: have reactivate() recreate a membership.
     */
    public function test_reactivation_restores_eligibility_and_nothing_else(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);
        $person = $this->make->user($organisation, status: UserStatus::Inactive);

        $group = $this->people->group($organisation);

        // An ENDED membership, which reactivation must not resurrect.
        $ended = $this->people->membership($group, $person, now()->subYears(2), now()->subYear());

        $before = $this->relationshipRows();

        $this->actingAsUser($admin)
            ->patch("/console/people/users/{$person->id}/reactivate")
            ->assertSessionHasNoErrors();

        $this->assertSame(UserStatus::Active, $person->fresh()->status);

        $this->assertEquals($before, $this->relationshipRows(), 'Reactivation changed a relationship row.');

        $this->assertNotNull($ended->fresh()->left_at, 'Reactivation resurrected an ended membership.');
    }

    /**
     * Negative case 23. An organisation CHANGE is refused while any current
     * relationship would become cross-organisation, and the refusal NAMES what
     * to resolve.
     *
     * Mutation: allow the change, and watch a P1-01 invariant break.
     */
    public function test_changing_organisation_is_refused_while_current_relationships_exist(): void
    {
        $organisation = $this->make->organisation('Ours');
        $admin = $this->make->user($organisation, administrator: true);
        $person = $this->make->user($organisation);
        $report = $this->make->user($organisation);

        $unit = $this->make->businessUnit($organisation);
        $team = $this->make->team($this->make->department($unit));

        TeamMembership::query()->create([
            'organisation_id' => $organisation->id,
            'team_id' => $team->id,
            'user_id' => $person->id,
            'joined_at' => now()->subYear(),
        ]);

        ManagementRelationship::query()->create([
            'organisation_id' => $organisation->id,
            'user_id' => $report->id,
            'manager_id' => $person->id,
            'effective_from' => now()->subYear(),
        ]);

        $group = $this->people->group($organisation);
        $this->people->membership($group, $person);

        // Clearing to NULL is the same guard - a user with no organisation
        // cannot hold any of those rows either.
        $this->actingAsUser($admin)
            ->put("/console/people/users/{$person->id}", ['organisation_id' => null])
            ->assertSessionHasErrors('people');

        $message = session('errors')->get('people')[0];

        foreach (['manages 1 person', 'belongs to 1 team', 'belongs to 1 group'] as $clause) {
            $this->assertStringContainsString(
                $clause,
                $message,
                "The refusal does not name [{$clause}]. A bare \"not allowed\" leaves the "
                .'administrator with nothing to act on.'
            );
        }

        $this->assertSame($organisation->id, $person->fresh()->organisation_id);
    }

    /** Assigning where there was none is permitted: nothing existing can be invalidated. */
    public function test_assigning_an_organisation_where_there_was_none_is_permitted(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);

        $unassigned = $this->make->user();

        $this->actingAsUser($admin)
            ->put("/console/people/users/{$unassigned->id}", ['organisation_id' => $organisation->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($organisation->id, $unassigned->fresh()->organisation_id);
    }

    /** And a change is permitted once the blocking relationships have ended. */
    public function test_changing_organisation_is_permitted_once_nothing_is_current(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);
        $person = $this->make->user($organisation);

        $group = $this->people->group($organisation);
        $this->people->membership($group, $person, now()->subYears(2), now()->subYear());

        $this->actingAsUser($admin)
            ->put("/console/people/users/{$person->id}", ['organisation_id' => null])
            ->assertSessionHasNoErrors();

        $this->assertNull($person->fresh()->organisation_id);
    }

    /**
     * Negative case 24. PURGING SOMEBODY WHO HAS EVER SIGNED IN IS REFUSED.
     *
     * D-39: purge is for the onboarding mistake - a wrong Object ID typed five
     * minutes ago - not for the departure. Once somebody has signed in they are
     * part of the organisation's history.
     *
     * Mutation: allow it.
     */
    public function test_somebody_who_has_ever_signed_in_cannot_be_purged(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);

        $person = $this->make->user($organisation);
        $person->forceFill(['last_signed_in_at' => now()->subYear()])->save();

        $this->actingAsUser($admin)
            ->delete("/console/people/users/{$person->id}")
            ->assertSessionHasErrors('people');

        $this->assertSame(
            'This person has signed in, so their record is kept as part of the organisation\'s '
            .'history. Deactivate them instead.',
            session('errors')->get('people')[0]
        );

        $this->assertNotNull($person->fresh(), 'A person who had signed in was purged.');
    }

    /**
     * Negative case 25. ANY membership history blocks a purge - not only current
     * membership.
     *
     * The membership below is ENDED. A guard that checked current rows would
     * find nothing and delete the person, taking the evidence that they were
     * ever in the group with them.
     *
     * Mutation: check only current rows.
     */
    public function test_ended_membership_history_blocks_a_purge(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);
        $person = $this->make->user($organisation);

        $group = $this->people->group($organisation);
        $this->people->membership($group, $person, now()->subYears(2), now()->subYear());

        $this->actingAsUser($admin)
            ->delete("/console/people/users/{$person->id}")
            ->assertSessionHasErrors('people');

        $this->assertStringContainsString(
            'group membership history exists',
            session('errors')->get('people')[0]
        );

        $this->assertNotNull($person->fresh());
    }

    /**
     * Negative case 26. THE BOOTSTRAP ADMINISTRATOR CANNOT BE PURGED.
     *
     * This is the case the schema-driven walk CANNOT answer.
     * bootstrap_grants.consumed_by_user_id is an unsignedBigInteger with NO
     * foreign key, so Schema::getForeignKeys never returns it and
     * PurgeDependencies cannot see it. Relying on the walk alone would have
     * produced a guard that looked complete and quietly permitted deleting the
     * founding administrator.
     *
     * The person below has never signed in and holds no relationship at all, so
     * every other condition is satisfied and only this one can refuse.
     *
     * Mutation: delete isBootstrapAdministrator() and rely on the walk.
     */
    public function test_the_bootstrap_administrator_cannot_be_purged(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);

        $founder = $this->make->user($organisation, administrator: true);

        $this->people->bootstrapGrant($founder);

        // Every other purge condition is satisfied.
        $this->assertNull($founder->last_signed_in_at);
        $this->assertSame(0, TeamMembership::query()->where('user_id', $founder->id)->count());

        // And the schema walk genuinely cannot see the reference, which is the
        // whole reason this case exists. If a foreign key is ever added, this
        // assertion fails and the test is telling the truth about why.
        $this->assertNotContains(
            'bootstrap_grants',
            array_column(
                PurgeDependencies::blocking($founder),
                'phrase'
            ),
            'The schema walk now sees bootstrap_grants, so this case is no longer proving that the '
            .'explicit check is what refuses.'
        );

        $this->actingAsUser($admin)
            ->delete("/console/people/users/{$founder->id}")
            ->assertSessionHasErrors('people');

        $this->assertSame(
            'This account established the first System Administrator for this deployment and is '
            .'kept as a permanent record. Deactivate it instead.',
            session('errors')->get('people')[0]
        );

        $this->assertNotNull($founder->fresh(), 'The bootstrap administrator was purged.');
    }

    /**
     * Negative case 27. The purge conditions are RE-CHECKED INSIDE THE
     * TRANSACTION.
     *
     * A dependency created between the first check and the delete must not be
     * missed. The race cannot be reproduced against SQLite, but the property
     * that makes it impossible can be observed: every condition query runs at a
     * transaction depth greater than zero, and before the delete.
     *
     * Mutation: check only before the transaction.
     */
    public function test_the_purge_conditions_are_rechecked_inside_the_transaction(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);
        $person = $this->make->user($organisation);

        // Against the baseline, for the reason recorded above.
        $baseline = DB::transactionLevel();

        $statements = [];

        DB::listen(function ($query) use (&$statements): void {
            $statements[] = ['sql' => strtolower($query->sql), 'depth' => DB::transactionLevel()];
        });

        app(UserDirectoryService::class)->purge($person, $admin);

        $delete = null;

        foreach ($statements as $index => $statement) {
            if (str_starts_with($statement['sql'], 'delete from "users"')
                || str_starts_with($statement['sql'], 'delete from `users`')) {
                $delete = $index;
                break;
            }
        }

        $this->assertNotNull($delete, 'purge() did not delete the user.');

        $this->assertGreaterThan(
            $baseline,
            $statements[$delete]['depth'],
            'The delete itself runs outside a transaction, so a re-check could not protect it.'
        );

        $rechecked = [];

        foreach (array_slice($statements, 0, $delete) as $statement) {
            if ($statement['depth'] <= $baseline) {
                continue;
            }

            foreach (['bootstrap_grants', 'group_memberships', 'team_memberships', 'management_relationships'] as $condition) {
                if (str_contains($statement['sql'], $condition)) {
                    $rechecked[$condition] = true;
                }
            }
        }

        // Both kinds of condition, because they are checked by different
        // mechanisms and only one of them is schema-driven.
        $this->assertArrayHasKey(
            'bootstrap_grants',
            $rechecked,
            'The bootstrap-administrator condition is not re-checked inside the transaction.'
        );

        $this->assertArrayHasKey(
            'group_memberships',
            $rechecked,
            'The dependency walk is not re-run inside the transaction, so a membership created '
            .'after the first check would be missed and its history deleted with the person.'
        );
    }

    /** The purge itself works when every condition is satisfied - or the guards above prove nothing. */
    public function test_a_person_with_no_history_who_has_never_signed_in_can_be_purged(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);
        $person = $this->make->user($organisation);

        $this->actingAsUser($admin)
            ->delete("/console/people/users/{$person->id}")
            ->assertSessionHasNoErrors();

        $this->assertNull(User::query()->find($person->id), 'The purge did not remove the record.');
    }

    /** The service refuses directly too, not only through the route. */
    public function test_the_service_refuses_a_purge_directly(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);

        $person = $this->make->user($organisation);
        $person->forceFill(['last_signed_in_at' => now()])->save();

        $this->expectException(PeopleViolation::class);

        app(UserDirectoryService::class)->purge($person, $admin);
    }

    /**
     * Every row of every table a deactivation could plausibly touch.
     *
     * @return array<string, array<int, object>>
     */
    private function relationshipRows(): array
    {
        return [
            'team_memberships' => DB::table('team_memberships')->orderBy('id')->get()->all(),
            'management_relationships' => DB::table('management_relationships')->orderBy('id')->get()->all(),
            'group_memberships' => DB::table('group_memberships')->orderBy('id')->get()->all(),
        ];
    }

    private function actingAsUser(User $user): self
    {
        return $this->withSession([
            EnsureSessionIsCurrent::SESSION_USER_ID => $user->id,
            EnsureSessionIsCurrent::SESSION_AUTHENTICATED_AT => now()->toIso8601String(),
        ]);
    }
}
