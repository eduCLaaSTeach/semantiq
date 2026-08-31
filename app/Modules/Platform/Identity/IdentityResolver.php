<?php

declare(strict_types=1);

namespace App\Modules\Platform\Identity;

use App\Modules\Platform\Models\User;
use App\Modules\Platform\Security\SecurityEventLogger;

/**
 * Maps a verified external identity to an existing SemantIQ user.
 *
 * This class is where self-registration would creep in if anyone made it
 * "helpful". It must never create a user: SYS-014 and SYS-015 require that
 * successful external authentication creates no SemantIQ user and no access,
 * and an unknown identity fails closed.
 *
 * It updates the display projections of a user that already exists, and that is
 * the only write it performs.
 */
final class IdentityResolver
{
    public function __construct(private readonly SecurityEventLogger $events) {}

    public function resolve(VerifiedIdentity $identity): User
    {
        $user = User::query()
            ->where('provider', $identity->provider)
            ->where('external_subject', $identity->subject)
            ->where('tenant_id', $identity->tenant)
            ->first();

        if ($user === null) {
            $this->events->record(SecurityEventLogger::LOGIN_REFUSED_UNKNOWN, [
                'provider' => $identity->provider,
                'subject' => $identity->subject,
                'tenant' => $identity->tenant,
                'result' => 'refused',
                'reason' => 'unknown_identity',
            ]);

            throw AuthenticationFailed::notAssigned();
        }

        if (! $user->isActive()) {
            $this->events->record(SecurityEventLogger::LOGIN_REFUSED_INACTIVE, [
                'provider' => $identity->provider,
                'user_id' => $user->id,
                'result' => 'refused',
                'reason' => 'inactive',
            ]);

            throw AuthenticationFailed::inactive();
        }

        // Directory projections, refreshed on each sign-in. Never authorisation.
        $user->forceFill([
            'email' => $identity->email,
            'display_name' => $identity->displayName,
            'last_signed_in_at' => now(),
        ])->save();

        return $user;
    }
}
