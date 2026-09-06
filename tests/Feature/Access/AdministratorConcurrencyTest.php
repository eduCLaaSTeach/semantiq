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
use App\Modules\Platform\Models\User;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Throwable;

/**
 * THE THREE RACES, against real MySQL, with two connections.
 *
 * THIS CLASS DOES NOT USE RefreshDatabase, AND THAT IS THE POINT.
 *
 * Under RefreshDatabase every row is uncommitted, so a second connection blocks
 * on things that have nothing to do with the lock under test - which is exactly
 * how P1-04's first lock measurement gave the right answer for the wrong
 * reason, and CI caught it. Here the data is COMMITTED, both connections see
 * it, and the only thing that can block the second connection is a lock the
 * first one holds.
 *
 * MySQL ONLY. SQLite has no SELECT ... FOR UPDATE - the locking reads compile
 * away entirely - so this SKIPS WITH A STATED REASON rather than passing
 * vacuously and reporting a lock against a guard holding none.
 *
 * WHAT IS AND IS NOT OBSERVABLE. True simultaneity is not: two people clicking
 * at the same instant would look identical to one person clicking twice. What
 * IS observable, and is what the invariant actually needs, is in three parts:
 *
 *   1. The administrator SET is genuinely held against another connection -
 *      so two removals cannot both read "there is another administrator".
 *   2. Both reducing operations contend on the SAME rows in the SAME order -
 *      so they serialise rather than deadlock.
 *   3. The loser re-evaluates against the winner's COMMITTED state and gets
 *      the ordinary business refusal, never a database error.
 */
final class AdministratorConcurrencyTest extends TestCase
{
    private array $userIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped(
                'SQLite has no SELECT ... FOR UPDATE, so the administrator-set locking reads compile '
                .'away entirely and this measurement would report a lock against a guard holding '
                .'none. It runs against MySQL 8.4 in CI, the engine production uses.'
            );
        }

        $this->seedCommitted();
    }

    protected function tearDown(): void
    {
        $this->removeCommitted();

        parent::tearDown();
    }

    /**
     * PART 1. THE SET IS ACTUALLY HELD.
     *
     * While one transaction holds the administrator set, a second connection
     * cannot read the same rows for update. If this were false, two concurrent
     * removals would each read "there is another administrator" and both
     * proceed, leaving zero.
     *
     * Mutation: drop lockForUpdate from either step of
     * lockAndReadEffectiveSet().
     */
    public function test_the_administrator_set_is_genuinely_held_against_another_connection(): void
    {
        $probe = $this->probe();

        DB::beginTransaction();

        // Take the boundary exactly as a reducing operation does.
        RoleAssignment::query()
            ->where('role_code', RoleCode::SystemAdministrator->value)
            ->whereNull('ended_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $assignmentsHeld = $this->blocks(
            fn () => $probe->table('role_assignments')
                ->where('role_code', RoleCode::SystemAdministrator->value)
                ->whereNull('ended_at')
                ->lockForUpdate()
                ->get()
        );

        DB::rollBack();

        $this->assertTrue(
            $assignmentsHeld,
            'The system_administrator assignment set was NOT held against a second connection. Two '
            .'concurrent removals would each see the other as the survivor and the deployment '
            .'would be left with zero administrators.'
        );

        // And the owning users rows, the second half of the boundary. Locking
        // the assignments alone would leave the owners free to be deactivated
        // under the guard's feet.
        DB::beginTransaction();

        User::query()->whereIn('id', $this->userIds)->orderBy('id')->lockForUpdate()->get();

        $usersHeld = $this->blocks(
            fn () => $probe->table('users')->whereIn('id', $this->userIds)->lockForUpdate()->get()
        );

        DB::rollBack();

        $this->assertTrue($usersHeld, 'The owning users rows were not held.');
    }

    /**
     * PART 2 and PART 3. THE THREE RACES.
     *
     *   C-A  deactivation racing revocation
     *   C-B  revocation racing revocation
     *   C-C  deactivation racing deactivation
     *
     * Each is run as: the winner commits, then the loser runs against that
     * committed state. That is precisely the state a blocked transaction sees
     * when the lock is released, and it is the state the guard must refuse
     * from.
     *
     * The assertion in every case: ONE COMPLETES, THE OTHER IS REFUSED WITH A
     * BUSINESS SENTENCE, AND NO RAW DATABASE ERROR REACHES THE ADMINISTRATOR.
     */
    public function test_no_pair_of_reducing_operations_can_reach_zero_administrators(): void
    {
        $races = [
            'C-A deactivate vs revoke' => ['deactivate', 'revoke'],
            'C-B revoke vs revoke' => ['revoke', 'revoke'],
            'C-C deactivate vs deactivate' => ['deactivate', 'deactivate'],
        ];

        foreach ($races as $label => [$winnerOperation, $loserOperation]) {
            $this->resetToTwoAdministrators();

            [$first, $second] = $this->userIds;

            // The winner completes.
            $this->perform($winnerOperation, $first);

            $this->assertSame(
                1,
                app(AdministratorSetGuard::class)->effectiveCount(),
                "[{$label}] the first operation did not reduce the count to one."
            );

            // The loser now runs against the winner's COMMITTED state.
            $refusal = null;

            try {
                $this->perform($loserOperation, $second);
            } catch (PeopleViolation|AccessViolation $violation) {
                $refusal = $violation;
            } catch (QueryException $database) {
                $this->fail(
                    "[{$label}] a raw database error reached the caller: {$database->getMessage()}. "
                    .'A deadlock victim is not a security mechanism, and an administrator must '
                    .'never be shown one.'
                );
            }

            $this->assertNotNull(
                $refusal,
                "[{$label}] the second operation succeeded, leaving zero System Administrators."
            );

            $this->assertSame('sole_administrator', $refusal->reason);

            $this->assertStringContainsString(
                'only active System Administrator',
                $refusal->getMessage(),
                "[{$label}] the refusal is not the business sentence."
            );

            $this->assertSame(
                1,
                app(AdministratorSetGuard::class)->effectiveCount(),
                "[{$label}] the deployment was left with the wrong number of administrators."
            );
        }
    }

    private function perform(string $operation, int $userId): void
    {
        $user = User::query()->findOrFail($userId);
        $actor = User::query()->findOrFail($this->userIds[0]);

        if ($operation === 'deactivate') {
            app(UserDirectoryService::class)->deactivate($user, $actor);

            return;
        }

        app(RoleAssignmentService::class)->revoke(
            RoleAssignment::query()
                ->where('user_id', $userId)
                ->where('role_code', RoleCode::SystemAdministrator->value)
                ->whereNull('ended_at')
                ->firstOrFail(),
            $actor,
        );
    }

    private function probe(): Connection
    {
        config(['database.connections.probe' => config('database.connections.'.config('database.default'))]);

        DB::purge('probe');

        $connection = DB::connection('probe');
        $connection->statement('SET SESSION innodb_lock_wait_timeout = 1');

        return $connection;
    }

    /** Whether a statement waited on a lock rather than completing. */
    private function blocks(callable $statement): bool
    {
        try {
            $statement();

            return false;
        } catch (Throwable $exception) {
            if (str_contains($exception->getMessage(), 'Lock wait timeout')) {
                return true;
            }

            throw $exception;
        }
    }

    /** Committed rows - no RefreshDatabase here, so these are really there. */
    private function seedCommitted(): void
    {
        foreach (['race-one', 'race-two'] as $index => $subject) {
            $this->userIds[] = (int) DB::table('users')->insertGetId([
                'organisation_id' => null,
                'provider' => 'microsoft',
                'external_subject' => 'administrator-race-'.$subject,
                'tenant_id' => '11111111-1111-1111-1111-111111111111',
                'email' => "administrator-race-{$subject}@example.test",
                'display_name' => 'Administrator Race '.($index + 1),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->resetToTwoAdministrators();
    }

    private function resetToTwoAdministrators(): void
    {
        DB::table('role_assignments')->whereIn('user_id', $this->userIds)->delete();
        DB::table('users')->whereIn('id', $this->userIds)->update(['status' => 'active']);

        foreach ($this->userIds as $userId) {
            DB::table('role_assignments')->insert([
                'user_id' => $userId,
                'organisation_id' => null,
                'role_code' => RoleCode::SystemAdministrator->value,
                'assigned_at' => now(),
                'ended_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function removeCommitted(): void
    {
        if ($this->userIds === []) {
            return;
        }

        DB::table('role_assignments')->whereIn('user_id', $this->userIds)->delete();
        DB::table('users')->whereIn('id', $this->userIds)->delete();

        $this->userIds = [];
    }
}
