<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Modules\Access\Models\RoleAssignment;
use App\Modules\Access\Services\AdministratorSetGuard;
use App\Modules\Access\Services\RoleAssignmentService;
use App\Modules\Access\Support\AccessViolation;
use App\Modules\Access\Support\RoleCode;
use App\Modules\People\Services\UserDirectoryService;
use App\Modules\People\Support\PeopleViolation;
use App\Modules\Platform\Bootstrap\BootstrapState;
use App\Modules\Platform\Models\User;
use App\Modules\Platform\Models\UserStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\AccessFactory;
use Tests\Support\OrganisationFactory;
use Tests\TestCase;

/**
 * N-M8 to N-M22. THE LOCKOUT INVARIANT.
 *
 *   Normal application operations may never transition the effective active
 *   System Administrator count from >= 1 to 0.
 *
 * A zero state is legitimate on a fresh deployment and during an approved
 * P1-00 recovery condition, and only the existing controlled bootstrap /
 * recovery mechanism may resolve it. Nothing here ever creates an
 * administrator to satisfy the invariant.
 *
 * THE CONCURRENCY EVIDENCE IS MYSQL-ONLY and lives in
 * AdministratorConcurrencyTest. SQLite compiles lockForUpdate() to nothing, so
 * a race test here would report a lock that is not there.
 */
final class AdministratorLockoutTest extends TestCase
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
     * N-M8. The last active System Administrator cannot be DEACTIVATED away.
     *
     * Mutation: drop the guard.
     */
    public function test_the_last_administrator_cannot_be_deactivated(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);

        // Other people exist and none is an administrator, so a guard counting
        // users rather than administrators would pass wrongly.
        $this->make->user($organisation);
        $this->make->user($organisation);

        $this->expectException(PeopleViolation::class);

        app(UserDirectoryService::class)->deactivate($admin, $admin);
    }

    /**
     * N-M9. ...NOR REVOKED AWAY. A separate route, so a separate guard.
     *
     * Mutation: drop the new guard. A developer fixing deactivation would not
     * necessarily fix this.
     */
    public function test_the_last_administrator_cannot_be_revoked(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);

        $assignment = RoleAssignment::query()
            ->where('user_id', $admin->id)
            ->where('role_code', RoleCode::SystemAdministrator->value)
            ->sole();

        try {
            app(RoleAssignmentService::class)->revoke($assignment, $admin);
            $this->fail('The last System Administrator assignment was revoked.');
        } catch (AccessViolation $violation) {
            $this->assertSame('sole_administrator', $violation->reason);
        }

        $this->assertTrue($assignment->fresh()->isCurrent());
        $this->assertSame(1, app(AdministratorSetGuard::class)->effectiveCount());
    }

    /**
     * The half that makes both of those non-vacuous: WITH TWO, EITHER MAY GO.
     *
     * A service that refused whenever the target was an administrator would
     * pass both cases above and fail here.
     */
    public function test_with_two_administrators_either_may_be_removed(): void
    {
        $organisation = $this->make->organisation();
        $first = $this->make->user($organisation, administrator: true);
        $second = $this->make->user($organisation, administrator: true);

        $assignment = RoleAssignment::query()
            ->where('user_id', $second->id)
            ->where('role_code', RoleCode::SystemAdministrator->value)
            ->sole();

        app(RoleAssignmentService::class)->revoke($assignment, $first);

        $this->assertFalse($assignment->fresh()->isCurrent());
        $this->assertSame(1, app(AdministratorSetGuard::class)->effectiveCount());

        // And now the survivor cannot be removed, which proves the count is of
        // EFFECTIVE administrators rather than of assignment records.
        $survivor = RoleAssignment::query()
            ->where('user_id', $first->id)
            ->where('role_code', RoleCode::SystemAdministrator->value)
            ->sole();

        $this->expectException(AccessViolation::class);

        app(RoleAssignmentService::class)->revoke($survivor, $first);
    }

    /**
     * "EFFECTIVE" MEANS BOTH FILTERS: a current assignment held by an ACTIVE
     * user.
     *
     * An administrator who has been deactivated holds a current assignment and
     * does NOT count, because they cannot sign in - and the deployment would be
     * locked out.
     *
     * Mutation: count assignments without joining users; count active users
     * without checking ended_at.
     */
    public function test_a_deactivated_administrator_does_not_count_towards_the_floor(): void
    {
        $organisation = $this->make->organisation();
        $active = $this->make->user($organisation, administrator: true);
        $deactivated = $this->make->user($organisation, administrator: true);

        $deactivated->forceFill(['status' => UserStatus::Inactive])->save();

        $this->assertSame(1, app(AdministratorSetGuard::class)->effectiveCount());

        // So the ACTIVE one is now the last, and cannot be removed.
        $assignment = RoleAssignment::query()
            ->where('user_id', $active->id)
            ->where('role_code', RoleCode::SystemAdministrator->value)
            ->sole();

        $this->expectException(AccessViolation::class);

        app(RoleAssignmentService::class)->revoke($assignment, $active);
    }

    /**
     * N-M21. A COUNT OF EXACTLY ONE WARNS AND DOES NOT BLOCK - D-49a.
     *
     * The floor stays at 1. Release 1 does not require two, because a floor of
     * 2 cannot be satisfied on a deployment that legitimately has one and would
     * block the very deactivation a departing administrator requires.
     *
     * Mutation: refuse an unrelated administrative operation while the count
     * is 1.
     */
    public function test_a_count_of_one_warns_but_blocks_nothing(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);
        $person = $this->make->user($organisation);

        $guard = app(AdministratorSetGuard::class);

        $this->assertTrue($guard->isSoleAdministratorWarningActive());

        // Every ordinary operation still works while the warning is showing.
        $granted = app(RoleAssignmentService::class)
            ->assign($person, RoleCode::BusinessUser, $organisation->id, $admin);

        $this->assertTrue($granted->isCurrent());

        app(UserDirectoryService::class)->deactivate($person, $admin);

        $this->assertSame(UserStatus::Inactive, $person->fresh()->status);

        // With two, the warning goes away.
        $this->make->user($organisation, administrator: true);

        $this->assertFalse($guard->isSoleAdministratorWarningActive());
    }

    /**
     * N-M22. P1-00's CONTROLLED RECOVERY IS PRESERVED, and P1-05 adds none of
     * its own.
     *
     * BootstrapState is COMPUTED from the assignment predicate. When the
     * effective count reaches zero - which only a legitimate zero state can
     * produce - the system is UNCONFIGURED again and the operator channel
     * reopens, exactly as D-03 approved. That is the same predicate returning
     * true, not a new mechanism.
     *
     * Mutation: store a flag; add a P1-05 "reset administrator" path.
     */
    public function test_bootstrap_state_is_computed_from_assignments_and_reopens_on_zero(): void
    {
        $state = app(BootstrapState::class);

        // A genuinely empty deployment.
        $this->assertTrue($state->isUnconfigured());

        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);

        $this->assertTrue($state->isConfigured());

        /*
         * The exceptional zero state. Reached here directly through the model,
         * because no NORMAL application operation may produce it - the guards
         * above refuse every route that would. That is the point: the state is
         * legitimate, and getting to it is not something the application does.
         */
        RoleAssignment::query()->where('user_id', $admin->id)->update(['ended_at' => now()]);

        $this->assertTrue(
            $state->isUnconfigured(),
            'With no active System Administrator the deployment is not UNCONFIGURED, so P1-00 '
            .'recovery would never reopen and the deployment would be permanently unadministrable.'
        );

        // And it is COMPUTED - no stored flag anywhere on the users table.
        $this->assertNotContains('bootstrap_state', Schema::getColumnListing('users'));
        $this->assertNotContains('is_configured', Schema::getColumnListing('users'));
    }

    /**
     * BOTH REDUCING OPERATIONS TAKE THE SAME BOUNDARY.
     *
     * Observed from the emitted SQL: each must lock the role_assignments SET
     * and then the users rows, not the subject row first. The subject-first
     * order gives two competing removals two different lock roots - a deadlock,
     * resolved by MySQL picking a victim rather than by anything this code
     * does, and a deadlock exception is not a security mechanism.
     *
     * Mutation: lock the subject first. It passes every single-request test.
     */
    public function test_both_reducing_operations_lock_the_same_set_in_the_same_order(): void
    {
        $organisation = $this->make->organisation();
        $first = $this->make->user($organisation, administrator: true);
        $second = $this->make->user($organisation, administrator: true);

        foreach (['deactivate', 'revoke'] as $operation) {
            $statements = [];

            DB::listen(function ($query) use (&$statements): void {
                $statements[] = strtolower($query->sql);
            });

            $subject = $this->make->user($organisation, administrator: true);

            if ($operation === 'deactivate') {
                app(UserDirectoryService::class)->deactivate($subject, $first);
            } else {
                app(RoleAssignmentService::class)->revoke(
                    RoleAssignment::query()
                        ->where('user_id', $subject->id)
                        ->where('role_code', RoleCode::SystemAdministrator->value)
                        ->sole(),
                    $first,
                );
            }

            $reads = array_values(array_filter(
                $statements,
                static fn (string $sql): bool => str_contains($sql, 'select')
                    && (str_contains($sql, 'role_assignments') || str_contains($sql, 'from "users"'))
            ));

            $assignmentIndex = null;
            $usersIndex = null;

            foreach ($reads as $index => $sql) {
                if ($assignmentIndex === null && str_contains($sql, 'from "role_assignments"')) {
                    $assignmentIndex = $index;
                }

                if ($assignmentIndex !== null && $usersIndex === null && str_contains($sql, 'from "users"')) {
                    $usersIndex = $index;
                }
            }

            $this->assertNotNull(
                $assignmentIndex,
                "[{$operation}] never read the administrator assignment set."
            );

            $this->assertNotNull(
                $usersIndex,
                "[{$operation}] never locked the owning users rows. Locking the assignments and not "
                .'their owners leaves the owners free to change under the guard.'
            );

            $this->assertGreaterThan(
                $assignmentIndex,
                $usersIndex,
                "[{$operation}] locked users before role_assignments. One deterministic order - "
                .'assignments, then users - is what makes the boundary COMMON rather than merely '
                .'present; two callers ordering differently deadlock.'
            );
        }
    }
}
