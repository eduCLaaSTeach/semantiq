<?php

declare(strict_types=1);

namespace App\Modules\Organisation\Services;

use App\Modules\Organisation\Models\ManagementRelationship;
use App\Modules\Organisation\Support\StructureViolation;
use App\Modules\Platform\Models\User;
use App\Modules\Platform\Security\SecurityEventLogger;
use Illuminate\Support\Facades\DB;

/**
 * The management chain.
 *
 * Unlike the structural tree, this one CAN cycle: user_id and manager_id are
 * both users, so nothing in the schema prevents A reporting to B reporting to A.
 * The tree cannot cycle - each node has one typed parent of a different type, so
 * a cycle there is unrepresentable and a check would be theatre.
 *
 * This matters beyond tidiness. P1-05 will walk this chain to resolve manager
 * scope, and a cycle would be an infinite loop in the access engine. It must be
 * unrepresentable before that engine exists.
 */
final class ManagementService
{
    /**
     * Bounds the walk so a cycle already present from bad data cannot hang a
     * request. A chain deeper than this is refused rather than followed.
     */
    private const MAX_CHAIN_DEPTH = 64;

    public function __construct(private readonly SecurityEventLogger $events) {}

    public function setManager(User $subject, User $manager, User $actor): ManagementRelationship
    {
        if ($subject->id === $manager->id) {
            throw StructureViolation::because('self_manager', 'A user may not manage themselves.');
        }

        // D-16, fail closed, both sides.
        if (! $subject->belongsToOrganisation() || ! $manager->belongsToOrganisation()) {
            throw StructureViolation::because(
                'user_without_organisation',
                'Both users must be associated with an organisation.'
            );
        }

        if ($subject->organisation_id !== $manager->organisation_id) {
            throw StructureViolation::because(
                'organisation_mismatch',
                'The user and the manager belong to different organisations.'
            );
        }

        if (! $subject->isActive() || ! $manager->isActive()) {
            throw StructureViolation::because('inactive_user', 'Both users must be active.');
        }

        $this->refuseCycle($subject->id, $manager->id);

        return DB::transaction(function () use ($subject, $manager, $actor): ManagementRelationship {
            $today = now()->toDateString();

            $current = ManagementRelationship::query()
                ->where('user_id', $subject->id)
                ->whereNull('effective_to')
                ->first();

            if ($current !== null && $current->effective_from->toDateString() === $today) {
                // A change on the same day the link began is a correction, not a
                // history event. Ending it and inserting would write a
                // zero-length relationship - noise that P1-07 would have to read
                // past - and would collide with the (user_id, effective_from)
                // key, which exists to prevent exactly that.
                $current->manager_id = $manager->id;
                $current->save();

                $relationship = $current;
            } else {
                // One current manager per user: the previous link is ended, not
                // deleted, so the history stays answerable for P1-07.
                $current?->forceFill(['effective_to' => $today])->save();

                $relationship = ManagementRelationship::query()->create([
                    'organisation_id' => $subject->organisation_id,
                    'user_id' => $subject->id,
                    'manager_id' => $manager->id,
                    'effective_from' => $today,
                ]);
            }

            $this->events->record(SecurityEventLogger::MANAGEMENT_RELATIONSHIP_SET, [
                'user_id' => $actor->id,
                'organisation_id' => $subject->organisation_id,
                'entity_type' => 'users',
                'entity_id' => $subject->id,
                'related_id' => $manager->id,
                'result' => 'set',
            ]);

            return $relationship;
        });
    }

    public function clearManager(User $subject, User $actor): void
    {
        $ended = ManagementRelationship::query()
            ->where('user_id', $subject->id)
            ->whereNull('effective_to')
            ->update(['effective_to' => now()->toDateString()]);

        if ($ended === 0) {
            throw StructureViolation::because('no_current_manager', 'This user has no current manager.');
        }

        $this->events->record(SecurityEventLogger::MANAGEMENT_RELATIONSHIP_CLEARED, [
            'user_id' => $actor->id,
            'organisation_id' => $subject->organisation_id,
            'entity_type' => 'users',
            'entity_id' => $subject->id,
            'result' => 'cleared',
        ]);
    }

    /**
     * Walk up from the proposed manager. If the subject appears anywhere in that
     * chain, the new link would close a loop, so refuse it.
     */
    private function refuseCycle(int $subjectId, int $managerId): void
    {
        $cursor = $managerId;
        $seen = [];

        for ($depth = 0; $depth < self::MAX_CHAIN_DEPTH; $depth++) {
            if ($cursor === $subjectId) {
                throw StructureViolation::because(
                    'management_cycle',
                    'That would create a cycle in the management hierarchy.'
                );
            }

            // A cycle that already exists in the data would otherwise walk
            // forever within the depth bound; stopping on a repeat is cheaper
            // and just as safe.
            if (isset($seen[$cursor])) {
                return;
            }

            $seen[$cursor] = true;

            $next = ManagementRelationship::query()
                ->where('user_id', $cursor)
                ->whereNull('effective_to')
                ->value('manager_id');

            if ($next === null) {
                return;
            }

            $cursor = (int) $next;
        }

        throw StructureViolation::because(
            'management_chain_too_deep',
            'The management chain is too deep to verify safely.'
        );
    }
}
