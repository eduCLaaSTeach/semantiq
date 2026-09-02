<?php

declare(strict_types=1);

namespace App\Modules\People\Services;

use App\Modules\Organisation\Models\Organisation;
use App\Modules\People\Models\Group;
use App\Modules\People\Models\GroupMembership;
use App\Modules\People\Models\GroupStatus;
use App\Modules\People\Support\PeopleViolation;
use App\Modules\Platform\Models\User;
use App\Modules\Platform\Security\SecurityEventLogger;
use App\Shared\Lifecycle\PurgeDependencies;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Groups, and who is in them.
 *
 * A GROUP GRANTS NOTHING. There is no method here that answers an access
 * question, and there is no column on the table that could be read as one. If a
 * later unit wants groups to participate in access, that is P1-05's decision to
 * make deliberately, not something this service should make easy by accident.
 */
final class GroupService
{
    public function __construct(private readonly SecurityEventLogger $events) {}

    /** @param array<string, string|null> $attributes */
    public function create(Organisation $organisation, array $attributes, User $actor): Group
    {
        $group = new Group;

        $group->forceFill([
            'organisation_id' => $organisation->id,
            'name' => trim((string) $attributes['name']),
            'code' => $this->nullIfBlank($attributes['code'] ?? null),
            'description' => $this->nullIfBlank($attributes['description'] ?? null),
            'status' => GroupStatus::Active->value,
        ])->save();

        $this->record(SecurityEventLogger::GROUP_CREATED, $group, $actor);

        return $group;
    }

    /**
     * Rename and re-describe. NOT its members - that is membership, and
     * membership is a lifecycle rather than a field.
     *
     * organisation_id is deliberately absent from what can be written: a group
     * belongs to the organisation it was created in, and moving one would strand
     * every membership it holds.
     *
     * @param  array<string, string|null>  $attributes
     */
    public function update(Group $group, array $attributes, User $actor): Group
    {
        $group->forceFill([
            'name' => trim((string) $attributes['name']),
            'code' => $this->nullIfBlank($attributes['code'] ?? null),
            'description' => $this->nullIfBlank($attributes['description'] ?? null),
        ])->save();

        $this->record(SecurityEventLogger::GROUP_UPDATED, $group, $actor);

        return $group;
    }

    /** An inactive group keeps its members and its history. */
    public function deactivate(Group $group, User $actor): Group
    {
        $group->forceFill(['status' => GroupStatus::Inactive->value])->save();

        $this->record(SecurityEventLogger::GROUP_DEACTIVATED, $group, $actor);

        return $group;
    }

    public function reactivate(Group $group, User $actor): Group
    {
        $group->forceFill(['status' => GroupStatus::Active->value])->save();

        $this->record(SecurityEventLogger::GROUP_ACTIVATED, $group, $actor);

        return $group;
    }

    /**
     * Add somebody, for a new period.
     *
     * "AT MOST ONE CURRENT MEMBERSHIP" IS ENFORCED HERE, NOT BY THE DATABASE.
     * MySQL 8.4 has no partial index, so the ideal
     * UNIQUE(group_id, user_id) WHERE left_at IS NULL cannot be declared. The
     * locking read below is what makes the invariant hold under concurrency -
     * without it two simultaneous adds both see no current membership and both
     * insert one.
     *
     * There is deliberately NO uniqueness on joined_at. P1-01 keys team
     * membership that way over dates, and it cannot represent join -> leave ->
     * rejoin on one day.
     */
    public function addMember(Group $group, User $user, User $actor): GroupMembership
    {
        return DB::transaction(function () use ($group, $user, $actor): GroupMembership {
            $this->refuseUnlessJoinable($group, $user);

            $current = GroupMembership::query()
                ->where('group_id', $group->id)
                ->where('user_id', $user->id)
                ->whereNull('left_at')
                ->lockForUpdate()
                ->first();

            if ($current !== null) {
                throw PeopleViolation::refuse(
                    'already_a_member',
                    'That person is already in this group.'
                );
            }

            // A new period may not start before the previous one ended, or the
            // history would say somebody was in the group twice at once.
            $lastLeft = GroupMembership::query()
                ->where('group_id', $group->id)
                ->where('user_id', $user->id)
                ->whereNotNull('left_at')
                ->max('left_at');

            $joinedAt = now();

            if ($lastLeft !== null && $joinedAt->lessThan($lastLeft)) {
                $joinedAt = Carbon::parse($lastLeft);
            }

            $membership = new GroupMembership;

            $membership->forceFill([
                'group_id' => $group->id,
                'user_id' => $user->id,
                'joined_at' => $joinedAt,
                'left_at' => null,
            ])->save();

            $this->events->record(SecurityEventLogger::GROUP_MEMBER_ADDED, [
                'user_id' => $actor->id,
                'entity_id' => $group->id,
                'related_id' => $user->id,
            ]);

            return $membership;
        });
    }

    /**
     * End a membership. The row is retained - it is the evidence that somebody
     * was once a member, which is the only reason to keep a membership table
     * rather than a list of current members.
     */
    public function removeMember(GroupMembership $membership, User $actor): GroupMembership
    {
        if (! $membership->isCurrent()) {
            throw PeopleViolation::refuse(
                'not_current',
                'That membership has already ended.'
            );
        }

        $membership->forceFill(['left_at' => now()])->save();

        $this->events->record(SecurityEventLogger::GROUP_MEMBER_REMOVED, [
            'user_id' => $actor->id,
            'entity_id' => $membership->group_id,
            'related_id' => $membership->user_id,
        ]);

        return $membership;
    }

    /**
     * D-39. Only a group with NO membership history at all.
     *
     * One member ever, even ended, and it deactivates instead. The check is the
     * same schema-driven walk D-24 established, so a future foreign key becomes
     * a blocker with no change here.
     */
    public function purge(Group $group, User $actor): void
    {
        $this->refuseIfInUse($group);

        DB::transaction(function () use ($group, $actor): void {
            $this->refuseIfInUse($group, locking: true);

            $id = $group->id;
            $organisationId = $group->organisation_id;

            $group->delete();

            $this->events->record(SecurityEventLogger::GROUP_PURGED, [
                'user_id' => $actor->id,
                'entity_type' => 'group',
                'entity_id' => $id,
                'organisation_id' => $organisationId,
            ]);
        });
    }

    public function isPurgeable(Group $group): bool
    {
        try {
            $this->refuseIfInUse($group);

            return true;
        } catch (PeopleViolation) {
            return false;
        }
    }

    private function refuseIfInUse(Group $group, bool $locking = false): void
    {
        $blockers = PurgeDependencies::blocking($group, $locking);

        if ($blockers !== []) {
            throw PeopleViolation::inUse('group', array_column($blockers, 'phrase'));
        }
    }

    /**
     * The D-16 rules, exactly as P1-01 applies them to teams.
     *
     * A user with no organisation fails closed; a cross-organisation membership
     * is refused; and neither an inactive person nor an inactive group may gain
     * a member, because a change with no effect that looks like a change is
     * worse than a refusal.
     */
    private function refuseUnlessJoinable(Group $group, User $user): void
    {
        if (! $user->belongsToOrganisation()) {
            throw PeopleViolation::refuse(
                'no_organisation',
                'That person is not associated with an organisation yet, so they cannot join a group.'
            );
        }

        if ($user->organisation_id !== $group->organisation_id) {
            throw PeopleViolation::refuse(
                'different_organisation',
                'That person belongs to a different organisation, so they cannot join this group.'
            );
        }

        if (! $user->isActive()) {
            throw PeopleViolation::refuse(
                'inactive_user',
                'That person is inactive. Reactivate them before adding them to a group.'
            );
        }

        if (! $group->isActive()) {
            throw PeopleViolation::refuse(
                'inactive_group',
                'This group is inactive. Reactivate it before adding members.'
            );
        }
    }

    private function record(string $event, Group $group, User $actor): void
    {
        $this->events->record($event, [
            'user_id' => $actor->id,
            'entity_type' => 'group',
            'entity_id' => $group->id,
            'organisation_id' => $group->organisation_id,
        ]);
    }

    private function nullIfBlank(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
