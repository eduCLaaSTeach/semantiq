<?php

declare(strict_types=1);

namespace App\Modules\Security\Posture\Adapters;

use App\Modules\Access\Services\AdministratorSetGuard;
use App\Modules\Security\Catalogue\ControlCatalogue;
use App\Modules\Security\Posture\Evidence;
use App\Modules\Security\Posture\PostureState;

/**
 * B-8 and PR-1 - the administrator set, asked of the guard that owns it.
 *
 * THE WORKED EXAMPLE OF "NO DUPLICATED RULE". AdministratorSetGuard's
 * effectiveCount() filters on BOTH a current assignment and users.status =
 * active, and its own docblock explains that this is deliberately DIFFERENT
 * from the D-49 rollback reconstruction, which ignores account status because
 * it asks a different question. An adapter that wrote its own count would pick
 * one of those two definitions by accident and then disagree with Roles &
 * Access about how many administrators this deployment has.
 *
 * PR-1 HAS A GREEN BRANCH AND IT IS REAL. Two or more genuine administrators is
 * Healthy. That half cannot be observed in production without manufacturing a
 * second administrator, which is forbidden - so it is proven by fixture, and
 * the Product Owner Test Script says so rather than inferring it from a passing
 * test.
 */
final class AdministratorAdapter implements SourceAdapter
{
    public function __construct(private readonly AdministratorSetGuard $guard) {}

    public function answers(): array
    {
        return [ControlCatalogue::LAST_ADMINISTRATOR, ControlCatalogue::ADMINISTRATOR_COUNT];
    }

    public function evidence(): array
    {
        $count = $this->guard->effectiveCount();

        return [
            $this->lastAdministratorGuard($count),
            $this->administratorCount($count),
        ];
    }

    /**
     * B-8 - the lockout invariant is in place.
     *
     * The guard cannot be asked "are you installed?" without performing a
     * removal, which this screen must never do. What it CAN state honestly is
     * that the invariant is currently satisfied, and at what margin - which is
     * what PR-1 below quantifies.
     */
    private function lastAdministratorGuard(int $count): Evidence
    {
        if ($count === 0) {
            return Evidence::state(
                ControlCatalogue::LAST_ADMINISTRATOR,
                PostureState::Critical,
                'There is no active System Administrator. Nobody can administer this deployment, '
                .'and only the controlled first-run recovery can establish one.',
            );
        }

        return Evidence::state(
            ControlCatalogue::LAST_ADMINISTRATOR,
            PostureState::Healthy,
            'Removing the last System Administrator is refused, so this deployment cannot be '
            .'left with nobody able to administer it.',
        );
    }

    /**
     * PR-1 - 0 is Critical, 1 is Attention, 2 or more is Healthy.
     *
     * One is not a fault and is not blocked: the floor stays at 1, because a
     * floor of 2 could not be satisfied on a deployment that legitimately has
     * one and would block the very deactivation a departing administrator
     * needs. It is a lockout RISK, and it is worth seeing.
     */
    private function administratorCount(int $count): Evidence
    {
        if ($count === 0) {
            return Evidence::state(
                ControlCatalogue::ADMINISTRATOR_COUNT,
                PostureState::Critical,
                'No active System Administrator. Nobody can administer this deployment.',
            );
        }

        if ($count === 1) {
            return Evidence::state(
                ControlCatalogue::ADMINISTRATOR_COUNT,
                PostureState::Attention,
                AdministratorSetGuard::SOLE_ADMINISTRATOR_WARNING,
            );
        }

        return Evidence::state(
            ControlCatalogue::ADMINISTRATOR_COUNT,
            PostureState::Healthy,
            "{$count} active System Administrators. If one account becomes unavailable, another "
            .'person can still administer this deployment.',
        );
    }
}
