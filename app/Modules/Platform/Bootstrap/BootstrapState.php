<?php

declare(strict_types=1);

namespace App\Modules\Platform\Bootstrap;

use App\Modules\Platform\Models\User;

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
 */
final class BootstrapState
{
    public function isConfigured(): bool
    {
        return User::query()->activeSystemAdministrators()->exists();
    }

    public function isUnconfigured(): bool
    {
        return ! $this->isConfigured();
    }
}
