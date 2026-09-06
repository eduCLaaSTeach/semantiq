<?php

declare(strict_types=1);

namespace App\Modules\Platform\Bootstrap;

use App\Modules\Access\Models\RoleAssignment;
use App\Modules\Access\Support\RoleCode;
use App\Modules\Platform\Models\User;
use App\Modules\Platform\Models\UserStatus;

/**
 * Whether the deployment has a System Administrator yet.
 *
 * Computed, never stored. A stored flag can drift from reality - and the
 * failure mode of a stale "configured" flag is a permanently unbootstrappable
 * system, while a stale "unconfigured" flag is an open bootstrap path. Deriving
 * it from the actual administrator count makes both impossible.
 *
 * This same predicate is what makes recovery work: if every System
 * Administrator is deactivated, the system is UNCONFIGURED again and the
 * operator channel reopens. Recovery is not a special mode or a flag - it is
 * this returning true.
 *
 * P1-05 CHANGED THE SOURCE AND NOTHING ELSE. The question is identical - "is
 * there an active System Administrator?" - and it is now asked of role
 * assignments rather than of users.platform_role, which no longer exists. P1-05
 * added NO recovery mechanism of its own, no flag, no manual database step and
 * no weakening of the SSH + fresh auditable grant + full Entra SSO requirements.
 * Adding one would be building a second way to create a privileged account.
 */
final class BootstrapState
{
    public function isConfigured(): bool
    {
        return RoleAssignment::query()
            ->join('users', 'users.id', '=', 'role_assignments.user_id')
            ->where('role_assignments.role_code', RoleCode::SystemAdministrator->value)
            ->whereNull('role_assignments.ended_at')
            ->where('users.status', UserStatus::Active->value)
            ->exists();
    }

    public function isUnconfigured(): bool
    {
        return ! $this->isConfigured();
    }
}
