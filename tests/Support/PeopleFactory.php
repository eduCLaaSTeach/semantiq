<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Organisation\Models\Organisation;
use App\Modules\People\Models\Group;
use App\Modules\People\Models\GroupMembership;
use App\Modules\People\Models\GroupStatus;
use App\Modules\Platform\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * People state built THROUGH THE MODELS, not through the services.
 *
 * The same choice OrganisationFactory made, and for the same reason: a test must
 * be able to construct a state the services would refuse, and then assert that
 * they refuse to act on it. A factory that used the services could only ever
 * build states the services already allow, which would make every guard look
 * satisfied by construction.
 */
final class PeopleFactory
{
    public function group(
        Organisation $organisation,
        string $name = 'Finance',
        GroupStatus $status = GroupStatus::Active,
        ?string $code = null,
    ): Group {
        return Group::query()->create([
            'organisation_id' => $organisation->id,
            'name' => $name,
            'code' => $code,
            'description' => null,
            'status' => $status,
        ]);
    }

    /**
     * A membership period, placed in time explicitly.
     *
     * joined_at and left_at are accepted as arguments so a test can build the
     * same-day rejoin history N42 is about without waiting for a clock.
     */
    public function membership(
        Group $group,
        User $user,
        ?Carbon $joinedAt = null,
        ?Carbon $leftAt = null,
    ): GroupMembership {
        return GroupMembership::query()->create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'joined_at' => $joinedAt ?? now(),
            'left_at' => $leftAt,
        ]);
    }

    /**
     * The bootstrap grant that P1-00 consumes when the first administrator is
     * established.
     *
     * Written with the query builder because bootstrap_grants has no model, and
     * consumed_by_user_id carries NO FOREIGN KEY - which is the whole point of
     * negative case 26: no schema walk can see this reference, so the purge
     * guard has to ask about it directly.
     */
    public function bootstrapGrant(User $consumedBy): void
    {
        DB::table('bootstrap_grants')->insert([
            'token_hash' => hash('sha256', 'test-grant-'.$consumedBy->id),
            'expected_subject' => $consumedBy->external_subject,
            'expected_tenant' => $consumedBy->tenant_id,
            'expires_at' => now()->addHour(),
            'consumed_at' => now(),
            'consumed_by_user_id' => $consumedBy->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
