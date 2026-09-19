<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Access\Models\DomainEntitlement;
use App\Modules\Access\Models\RoleAssignment;
use App\Modules\Access\Support\RoleCode;
use App\Modules\Platform\Models\User;
use App\Modules\Reviews\Models\AccessReviewCycle;
use App\Modules\Reviews\Models\AccessReviewItem;
use App\Modules\Reviews\Support\Composition;
use Carbon\CarbonInterface;

/**
 * Review fixtures built the way the generator builds them, so a test is never
 * kinder than reality.
 *
 * THE ORGANISATION COMES FROM THE CYCLE, exactly as generation sets it. A
 * fixture that left it null would have let every organisation-scoped guard pass
 * against items belonging to nobody.
 */
final class ReviewFactory
{
    public function cycle(User $starter, ?CarbonInterface $dueAt = null): AccessReviewCycle
    {
        return AccessReviewCycle::query()->create([
            'organisation_id' => $starter->organisation_id,
            'started_at' => now(),
            'due_at' => $dueAt ?? now()->addDays(30),
            'started_by_user_id' => $starter->getKey(),
        ]);
    }

    public function privilegedItem(AccessReviewCycle $cycle, RoleAssignment $assignment): AccessReviewItem
    {
        $role = $assignment->role_code instanceof RoleCode
            ? $assignment->role_code->value
            : (string) $assignment->role_code;

        $composition = Composition::ofPrivilege($role);

        return AccessReviewItem::query()->create([
            'access_review_cycle_id' => $cycle->getKey(),
            'organisation_id' => $cycle->organisation_id,
            'kind' => 'privileged',
            'subject_user_id' => $assignment->user_id,
            'role_assignment_id' => $assignment->getKey(),
            'role_code' => $role,
            'state' => 'pending',
            'due_at' => $cycle->due_at,
            'composition' => $composition,
            'composition_fingerprint' => Composition::fingerprint($composition),
        ]);
    }

    public function domainItem(AccessReviewCycle $cycle, DomainEntitlement $entitlement): AccessReviewItem
    {
        $composition = Composition::ofEntitlement($entitlement);

        return AccessReviewItem::query()->create([
            'access_review_cycle_id' => $cycle->getKey(),
            'organisation_id' => $cycle->organisation_id,
            'kind' => 'domain',
            'subject_user_id' => $entitlement->assignment?->user_id,
            'domain_entitlement_id' => $entitlement->getKey(),
            'business_domain_id' => $entitlement->business_domain_id,
            'role_code' => (string) $composition['role_code'],
            'state' => 'pending',
            'due_at' => $cycle->due_at,
            'composition' => $composition,
            'composition_fingerprint' => Composition::fingerprint($composition),
        ]);
    }
}
