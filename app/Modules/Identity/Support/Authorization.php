<?php

declare(strict_types=1);

namespace App\Modules\Identity\Support;

use App\Models\User;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Models\UserRole;

/**
 * Whether a person may perform a permissioned action. Feature ADM-007.
 *
 * The single answer to that question. Navigation asks it, the route middleware
 * asks it, and service rules ask it - ADM-007 requires enforcement at all three
 * and one implementation is what stops the three drifting apart.
 *
 * THE RULE, in the order it is applied:
 *
 *  1. An UNKNOWN KEY DENIES. VAL-PERM-DENY-001. A typo must never become a
 *     grant, and a permission deleted from the registry must stop working the
 *     moment the code deploys.
 *  2. The account must be ACTIVE. A disabled, locked or expired account is
 *     refused everything, not merely refused sign-in, because a live session
 *     can outlive the moment somebody was disabled.
 *  3. The account's TIER must meet the permission's ceiling. This is the coarse
 *     gate and it cannot be bought around: no role, no grant and no assignment
 *     raises it.
 *  4. The permission must be GRANTED, either as a tier default or through an
 *     assigned role.
 *
 * Steps 3 and 4 are plan decision D2 - the tier and the grant must BOTH agree.
 * Step 3 running before step 4 is what makes `user_roles` unable to become a
 * privilege-escalation path: assigning a System Administrator role to a Viewer
 * changes what is recorded and changes nothing about what they can do.
 *
 * WHAT THIS CLASS DOES NOT DO, and must never be extended to do: decide access
 * to business information. That is the second dimension - the domain
 * entitlement - and `ROLE_MODEL.md` section 1 is explicit that a platform role
 * never implies it. `User::isEntitledTo()` answers that question and this class
 * has no opinion on it. A System Administrator with no entitlement administers
 * the platform and reads no Finance figure.
 */
class Authorization
{
    /** Memoised per user for the life of the request. */
    private array $effective = [];

    public function __construct(
        private readonly PermissionRegistry $registry,
    ) {}

    /**
     * Whether this person holds this permission.
     */
    public function allows(?User $user, string $permission): bool
    {
        if ($user === null) {
            return false;
        }

        /* 1. Unknown denies. */
        $definition = $this->registry->get($permission);

        if ($definition === null) {
            return false;
        }

        /* 2. A suspended account holds nothing, session or no session. */
        if (! $user->status->permitsAuthentication()) {
            return false;
        }

        /* 3. The tier ceiling. Nothing raises it. */
        if (! $user->hasAtLeast($definition->minimumTier)) {
            return false;
        }

        /* 4. And it must actually be granted. */
        return in_array($permission, $this->effectiveFor($user), true);
    }

    /**
     * Every permission this person effectively holds, as keys.
     *
     * The union of their tier's defaults and the permissions their assigned
     * roles carry, with the tier ceiling applied to the whole result. The
     * ceiling is applied AFTER the union, deliberately: a role may carry a
     * permission above its holder's tier - roles are shared, people are not -
     * and the right behaviour is for that permission to be inert rather than
     * for the assignment to be impossible.
     *
     * Answers ADM-007's requirement that effective permissions be determinable
     * for a user, and it is what the Users screen shows.
     *
     * @return list<string>
     */
    public function effectiveFor(User $user): array
    {
        $id = (int) $user->getKey();

        if (array_key_exists($id, $this->effective)) {
            return $this->effective[$id];
        }

        if (! $user->status->permitsAuthentication()) {
            /* A suspended account holds nothing at all. Returning the tier
             * defaults here would make the Users screen show access that the
             * check in allows() would refuse - two answers to one question. */
            return $this->effective[$id] = [];
        }

        $granted = array_merge(
            $this->registry->defaultsFor($user->role),
            $this->fromAssignedRoles($user),
        );

        /*
         * The CEILING, not the defaults. The two are different sets, and using
         * the defaults here was a real bug: the union would be intersected back
         * down to exactly the defaults, so an assigned role could never add
         * anything and roles were decoration. A test caught it.
         */
        $ceiling = $this->registry->ceilingFor($user->role);

        $effective = array_values(array_unique(array_intersect($granted, $ceiling)));

        sort($effective);

        return $this->effective[$id] = $effective;
    }

    /**
     * The permissions this person's assigned roles carry.
     *
     * Only ACTIVE roles count. A disabled role keeps its assignments so that
     * history stays readable and re-enabling is one action, but it grants
     * nothing while disabled - which is the entire point of being able to
     * disable one.
     *
     * Keys are validated against the registry on the way out as well as on the
     * way in, so a row that outlived its declaration grants nothing.
     *
     * @return list<string>
     */
    private function fromAssignedRoles(User $user): array
    {
        $roleIds = UserRole::query()
            ->where('user_id', $user->getKey())
            ->pluck('role_id');

        if ($roleIds->isEmpty()) {
            return [];
        }

        $keys = RolePermission::query()
            ->whereIn('role_id', $roleIds)
            ->whereHas('role', fn ($query) => $query->where('status', 'active'))
            ->pluck('permission_key')
            ->all();

        return array_values(array_filter(
            $keys,
            fn (string $key): bool => $this->registry->has($key),
        ));
    }

    /**
     * Whether an actor may grant a permission to somebody else.
     *
     * VAL-USER-ELEVATE-001: nobody may hand out authority they do not hold. The
     * check is "does the actor hold this permission themselves", which is
     * stricter than comparing tiers and is the right question - an
     * Administrator who was never granted `admin.roles.manage` cannot give it
     * away either.
     */
    public function mayDelegate(User $actor, string $permission): bool
    {
        return $this->allows($actor, $permission);
    }

    /**
     * Whether an actor may act on an account at all.
     *
     * VAL-USER-ELEVATE-001 again, from the other side: an administrator must
     * not be able to disable, demote or re-role somebody who outranks them.
     * Equal tiers may act on each other, because two System Administrators
     * have to be able to correct each other - the last-administrator invariant
     * is what stops that becoming a way to empty the role.
     */
    public function mayActOn(User $actor, User $subject): bool
    {
        return $actor->role->atLeast($subject->role);
    }

    /**
     * Drop the memoised sets.
     *
     * Needed after a role or assignment changes within one request, and by
     * tests. Without it a screen can render the access it just replaced.
     */
    public function flush(): void
    {
        $this->effective = [];
    }
}
