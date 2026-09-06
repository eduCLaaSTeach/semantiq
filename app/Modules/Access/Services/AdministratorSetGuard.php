<?php

declare(strict_types=1);

namespace App\Modules\Access\Services;

use App\Modules\Access\Models\RoleAssignment;
use App\Modules\Access\Support\AccessViolation;
use App\Modules\Access\Support\RoleCode;
use App\Modules\Platform\Models\User;
use App\Modules\Platform\Models\UserStatus;
use Illuminate\Support\Facades\DB;

/**
 * THE LOCKOUT INVARIANT, and the COMMON SERIALISATION BOUNDARY it needs.
 *
 *   Normal application operations may never transition the effective active
 *   System Administrator count from >= 1 to 0.
 *
 * A zero state is legitimate on a genuinely fresh deployment, and during an
 * approved P1-00 recovery condition. In either case ONLY the existing
 * controlled P1-00 bootstrap/recovery mechanism may establish a new System
 * Administrator - the same UNCONFIGURED predicate returning true, requiring an
 * authorised operator with SSH, a fresh auditable grant and full Entra SSO.
 * P1-05 adds NO recovery mechanism of its own, and nothing here ever creates an
 * administrator to satisfy the invariant. It only refuses the operation that
 * would breach it.
 *
 * WHY THE WHOLE SET IS LOCKED, AND NOT THE SUBJECT.
 *
 * The first design locked the subject's users row, then its assignments, then
 * counted the others. In the two-administrator race that gives transaction A a
 * lock on user A and transaction B a lock on user B, each then reaching for the
 * other's rows: two lock roots, not one common boundary. It deadlocks, and
 * MySQL picks a victim.
 *
 * A DEADLOCK EXCEPTION IS NOT A SECURITY MECHANISM. The invariant protects the
 * whole SET of active System Administrators, so the whole set is what must be
 * locked - in ONE deterministic order, by assignment id then user id ascending,
 * so two competing removals contend on the same rows in the same sequence. The
 * order is a property of this guard rather than of the caller, which is what
 * makes it impossible for one call site to serialise differently from another.
 *
 * "EFFECTIVE" MEANS a current assignment (ended_at IS NULL) held by a user with
 * status = active. BOTH filters, because this asks "can anybody actually
 * administer this deployment?". That is deliberately DIFFERENT from the D-49
 * rollback reconstruction, which ignores account status because it asks a
 * different question. N-M13 breaks the borrow in both directions.
 */
final class AdministratorSetGuard
{
    /**
     * Lock the whole current System Administrator set, then run the operation.
     *
     * Callers pass the change they intend and this decides whether it would
     * leave zero. The caller's write happens INSIDE this transaction, after the
     * decision, so there is no window between deciding and writing.
     *
     * @template T
     *
     * @param  callable(list<int>): T  $operation  Receives the effective administrator user ids.
     * @return T
     */
    public function serialise(callable $operation): mixed
    {
        return DB::transaction(function () use ($operation): mixed {
            $effective = $this->lockAndReadEffectiveSet();

            return $operation($effective);
        });
    }

    /**
     * Refuse if removing this person's System Administrator authority would
     * leave zero.
     *
     * Called INSIDE serialise(), after the set is locked and re-read. Passing
     * the re-read set rather than querying again is what makes the losing
     * request in a race re-evaluate the winner's committed state rather than
     * its own stale snapshot.
     *
     * @param  list<int>  $effective
     */
    public function refuseIfLast(array $effective, User $subject): void
    {
        if (! in_array($subject->getKey(), $effective, true)) {
            // Not currently an effective administrator, so removing their
            // authority cannot reduce the count.
            return;
        }

        $others = array_values(array_filter(
            $effective,
            static fn (int $id): bool => $id !== $subject->getKey(),
        ));

        if ($others === []) {
            throw AccessViolation::soleAdministrator();
        }
    }

    /**
     * Whether the deployment is one administrator away from lockout - D-49a.
     *
     * A count of exactly one produces a VISIBLE, NON-BLOCKING WARNING. The floor
     * stays at 1: Release 1 does not require a minimum of two, because a floor
     * of 2 cannot be satisfied on a deployment that legitimately has one and
     * would block the very deactivation a departing administrator requires. A
     * second administrator is recommended operationally, not a prerequisite for
     * the product to function.
     *
     * Nothing is blocked by this. The only refusal is an operation that would
     * take the effective count from 1 to 0.
     */
    public function isSoleAdministratorWarningActive(): bool
    {
        return $this->effectiveCount() === 1;
    }

    public const SOLE_ADMINISTRATOR_WARNING =
        'Only one active System Administrator remains. Add another trusted administrator to reduce account-lockout risk.';

    public function effectiveCount(): int
    {
        return RoleAssignment::query()
            ->join('users', 'users.id', '=', 'role_assignments.user_id')
            ->where('role_assignments.role_code', RoleCode::SystemAdministrator->value)
            ->whereNull('role_assignments.ended_at')
            ->where('users.status', UserStatus::Active->value)
            ->count();
    }

    /**
     * Lock every current System Administrator assignment and its owning user
     * row, in ONE deterministic order, then re-evaluate which are effective.
     *
     * Both statements are locking reads inside the caller's transaction. Under
     * MySQL's REPEATABLE READ a plain SELECT would read the transaction's own
     * snapshot and miss a change committed after it opened, so a plain read
     * here would produce exactly the check-then-write race this exists to close.
     *
     * SQLite has no SELECT ... FOR UPDATE. It does not error - it ignores the
     * clause - so this code runs there and the tests pass, but they prove
     * nothing about locking. The concurrency evidence is MySQL only, and saying
     * so is the point: a SQLite concurrency test would report a lock that is
     * not there.
     *
     * @return list<int> The effective administrator user ids.
     */
    private function lockAndReadEffectiveSet(): array
    {
        // 1. The assignment set, ascending by assignment id.
        $assignments = RoleAssignment::query()
            ->where('role_code', RoleCode::SystemAdministrator->value)
            ->whereNull('ended_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $userIds = $assignments
            ->pluck('user_id')
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($userIds === []) {
            return [];
        }

        // 2. Their owning users rows, ascending by user id - the same order for
        //    every caller, which is what removes the deadlock rather than
        //    surviving it.
        $users = User::query()
            ->whereIn('id', $userIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        // 3. Re-evaluate under the locks. Both filters.
        return $users
            ->filter(static fn (User $user): bool => $user->status === UserStatus::Active)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }
}
