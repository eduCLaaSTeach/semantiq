<?php

declare(strict_types=1);

namespace App\Modules\Platform\Bootstrap;

use App\Modules\Platform\Identity\AuthenticationFailed;
use App\Modules\Platform\Identity\VerifiedIdentity;
use App\Modules\Platform\Models\BootstrapGrant;
use App\Modules\Platform\Models\PlatformRole;
use App\Modules\Platform\Models\User;
use App\Modules\Platform\Models\UserStatus;
use App\Modules\Platform\Security\SecurityEventLogger;
use Illuminate\Support\Facades\DB;

/**
 * Redeems a bootstrap grant after Entra has verified the identity.
 *
 * Order matters more than anything else in this class. Tenant and subject are
 * checked BEFORE any UPDATE, so a wrong identity refuses without consuming the
 * grant - D-03 rule 7 - and the grant remains usable by the right person.
 *
 * Consumption is a conditional UPDATE with the single-use guard in the WHERE
 * clause. Two concurrent redemptions cannot both succeed regardless of
 * application timing, because the database decides, not us.
 *
 * D-03.1: tid is matched exactly and UPN case-insensitively, then the verified
 * oid is captured. From that point on the user's identity key is oid + tid, and
 * email is never an identity key again.
 */
final class GrantRedeemer
{
    public function __construct(private readonly SecurityEventLogger $events) {}

    public function redeem(string $grant, VerifiedIdentity $identity): User
    {
        $record = BootstrapGrant::query()
            ->where('token_hash', BootstrapGrant::hashFor($grant))
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->first();

        if ($record === null) {
            $this->refuse('grant_invalid');
        }

        if (! hash_equals($record->expected_tenant, $identity->tenant)) {
            $this->refuse('tenant_mismatch');
        }

        if (! hash_equals($record->expected_subject, mb_strtolower($identity->email))) {
            $this->refuse('subject_mismatch');
        }

        return DB::transaction(function () use ($record, $identity): User {
            $user = User::query()->create([
                'provider' => $identity->provider,
                'external_subject' => $identity->subject,
                'tenant_id' => $identity->tenant,
                'email' => $identity->email,
                'display_name' => $identity->displayName,
                'status' => UserStatus::Active,
                'platform_role' => PlatformRole::SystemAdministrator,
                'last_signed_in_at' => now(),
            ]);

            // The guard is in the WHERE clause, not in PHP. Exactly one row must
            // be affected; zero means another request won the race, and the
            // whole transaction - including the user above - rolls back.
            $consumed = BootstrapGrant::query()
                ->whereKey($record->getKey())
                ->whereNull('consumed_at')
                ->where('expires_at', '>', now())
                ->update([
                    'consumed_at' => now(),
                    'consumed_by_user_id' => $user->id,
                ]);

            if ($consumed !== 1) {
                throw AuthenticationFailed::protocol('grant_already_consumed');
            }

            $this->events->record(SecurityEventLogger::BOOTSTRAP_COMPLETED, [
                'provider' => $identity->provider,
                'subject' => $identity->subject,
                'tenant' => $identity->tenant,
                'user_id' => $user->id,
                'result' => 'completed',
            ]);

            return $user;
        });
    }

    private function refuse(string $reason): never
    {
        $this->events->record(SecurityEventLogger::BOOTSTRAP_REFUSED, [
            'result' => 'refused',
            'reason' => $reason,
        ]);

        throw AuthenticationFailed::protocol($reason);
    }
}
