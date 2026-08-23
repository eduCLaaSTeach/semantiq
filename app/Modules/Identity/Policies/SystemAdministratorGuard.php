<?php

declare(strict_types=1);

namespace App\Modules\Identity\Policies;

use App\Enums\Role;
use App\Models\User;
use App\Modules\Platform\Enums\LifecycleStatus;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

/**
 * The last active System Administrator cannot be removed, disabled or demoted.
 *
 * VAL-USER-LASTADMIN-001, and the invariant Release 1 gate 2 exists for.
 *
 * WHY IT IS A CLASS AND NOT THREE IF-STATEMENTS. There are three separate ways
 * to empty the role - delete the account, suspend it, lower its tier - reached
 * from three different screens, and a fourth will be added by some later
 * feature nobody has thought of yet. A check written into each controller is a
 * check the fourth path will not have. Every path that could remove the last
 * administrator calls `assertNotLast()`, and a new path that forgets to is a
 * bug this class makes findable rather than a hole nobody notices until an
 * instance is locked out of itself.
 *
 * It THROWS rather than returning false. A caller that ignores a false has
 * quietly locked the customer out of their own platform with no way back in
 * short of database access; an exception cannot be ignored by accident.
 *
 * "ACTIVE" is the operative word. An administrator who is disabled, locked or
 * past their access window cannot sign in, so they cannot be the one keeping
 * the door open. Counting them would let an instance be locked out while the
 * count said everything was fine - the exact failure this guards against.
 */
class SystemAdministratorGuard
{
    /**
     * Refuse a change that would leave no active System Administrator.
     *
     * Called before disabling, deleting, demoting or expiring an account. The
     * subject is EXCLUDED from the count, because the question is not "how many
     * are there" but "how many would remain".
     *
     * @param  User  $subject  The account about to change.
     * @param  string  $action  What is being attempted, for the message and the trail.
     *
     * @throws RuntimeException when the subject is the last one standing.
     */
    public function assertNotLast(User $subject, string $action): void
    {
        if (! $this->isActiveSystemAdministrator($subject)) {
            /* Not an administrator, or already inactive. Removing them changes
             * nothing about how many remain. */
            return;
        }

        if ($this->remainingCountExcluding($subject) > 0) {
            return;
        }

        throw new RuntimeException(
            'This is the last active System Administrator. '.$action.' would leave nobody able to '
            .'administer SemantIQ, including nobody able to undo it. Give another account System '
            .'Administrator first.'
        );
    }

    /**
     * Whether this change is allowed, without throwing.
     *
     * For a SCREEN deciding whether to offer a control. The screen asks here so
     * the button is absent rather than present and fatal; the write path still
     * calls `assertNotLast()`, because a disabled button is not an
     * authorization control and a POST does not care what the page rendered.
     */
    public function permits(User $subject): bool
    {
        if (! $this->isActiveSystemAdministrator($subject)) {
            return true;
        }

        return $this->remainingCountExcluding($subject) > 0;
    }

    /**
     * How many active System Administrators exist at all.
     *
     * Shown on the Users screen so an administrator can see the number before
     * it becomes a refusal, rather than discovering it by being refused.
     */
    public function activeCount(): int
    {
        return $this->baseQuery()->count();
    }

    /**
     * Whether this account currently holds the role in a usable way.
     */
    private function isActiveSystemAdministrator(User $user): bool
    {
        return $user->role === Role::SystemAdmin
            && $user->status === LifecycleStatus::Active;
    }

    /**
     * How many active System Administrators there would be without this one.
     */
    private function remainingCountExcluding(User $subject): int
    {
        return $this->baseQuery()
            ->whereKeyNot($subject->getKey())
            ->count();
    }

    /**
     * Active System Administrators in the current organisation.
     *
     * Explicitly scoped. On a multi-tenant instance another customer's
     * administrator is no help to this customer at all, so counting them would
     * be the same class of failure as counting a disabled one - a count that
     * says the door is held open by somebody who cannot open it.
     */
    private function baseQuery(): Builder
    {
        return User::query()
            ->inCurrentOrganisation()
            ->where('role', Role::SystemAdmin->value)
            ->where('status', LifecycleStatus::Active->value);
    }
}
