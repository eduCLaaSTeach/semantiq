<?php

declare(strict_types=1);

namespace App\Modules\Organisation\Services;

use App\Modules\Organisation\Models\Team;
use App\Modules\Organisation\Models\TeamMembership;
use App\Modules\Organisation\Support\StructureViolation;
use App\Modules\Platform\Models\User;
use App\Modules\Platform\Security\SecurityEventLogger;
use Illuminate\Support\Carbon;

/**
 * Team membership. D-15: the member is a users row.
 *
 * The organisation comparison here is D-16's whole reason for existing. It reads
 * user.organisation_id and team.organisation_id, and NEVER Entra tenant_id -
 * tenant_id is a directory boundary, not a SemantIQ tenancy boundary, and in
 * single-tenant Release 1 it would make this guard pass for a reason unrelated
 * to what it claims to check.
 */
final class MembershipService
{
    public function __construct(private readonly SecurityEventLogger $events) {}

    public function add(Team $team, User $member, User $actor, ?Carbon $joinedAt = null): TeamMembership
    {
        // D-16, fail closed. A user with no organisation cannot join anything.
        if (! $member->belongsToOrganisation()) {
            throw StructureViolation::because(
                'user_without_organisation',
                'This user is not associated with an organisation.'
            );
        }

        if ($member->organisation_id !== $team->organisation_id) {
            throw StructureViolation::because(
                'organisation_mismatch',
                'The user and the team belong to different organisations.'
            );
        }

        if (! $team->isActive()) {
            throw StructureViolation::because('inactive_parent', 'The team is inactive.');
        }

        if (! $member->isActive()) {
            throw StructureViolation::because('inactive_user', 'This user is not active.');
        }

        $alreadyCurrent = TeamMembership::query()
            ->where('team_id', $team->id)
            ->where('user_id', $member->id)
            ->whereNull('left_at')
            ->exists();

        if ($alreadyCurrent) {
            throw StructureViolation::because(
                'duplicate_membership',
                'This user is already a current member of the team.'
            );
        }

        $joined = ($joinedAt ?? now())->toDateString();

        $sameDay = TeamMembership::query()
            ->where('team_id', $team->id)
            ->where('user_id', $member->id)
            ->whereDate('joined_at', $joined)
            ->first();

        if ($sameDay !== null) {
            // Rejoining on the day they left means the removal was a mistake.
            // Reopening the row avoids writing a zero-length membership, which
            // P1-07 would have to read past, and avoids colliding with the
            // (team_id, user_id, joined_at) key that exists to prevent it. The
            // removal itself is not lost: both events are already recorded.
            $sameDay->forceFill(['left_at' => null])->save();

            $membership = $sameDay;
        } else {
            $membership = TeamMembership::query()->create([
                'organisation_id' => $team->organisation_id,
                'team_id' => $team->id,
                'user_id' => $member->id,
                'joined_at' => $joined,
            ]);
        }

        $this->events->record(SecurityEventLogger::TEAM_MEMBER_ADDED, [
            'user_id' => $actor->id,
            'organisation_id' => $team->organisation_id,
            'entity_type' => 'teams',
            'entity_id' => $team->id,
            'related_id' => $member->id,
            'result' => 'added',
        ]);

        return $membership;
    }

    /**
     * Removal sets left_at. The row is retained so the history stays answerable.
     */
    public function remove(TeamMembership $membership, User $actor): TeamMembership
    {
        if ($membership->left_at !== null) {
            throw StructureViolation::because('not_current', 'This membership has already ended.');
        }

        $membership->left_at = now()->toDateString();
        $membership->save();

        $this->events->record(SecurityEventLogger::TEAM_MEMBER_REMOVED, [
            'user_id' => $actor->id,
            'organisation_id' => $membership->organisation_id,
            'entity_type' => 'teams',
            'entity_id' => $membership->team_id,
            'related_id' => $membership->user_id,
            'result' => 'removed',
        ]);

        return $membership;
    }
}
