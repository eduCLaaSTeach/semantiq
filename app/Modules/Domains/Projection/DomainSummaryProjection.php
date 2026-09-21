<?php

declare(strict_types=1);

namespace App\Modules\Domains\Projection;

use App\Modules\Domains\Models\BusinessDomain;
use App\Modules\Domains\Models\DomainStatus;
use App\Modules\Platform\Models\User;

/**
 * THE READ SEAM FOR BUSINESS DOMAINS - D-181, owned by P1-04 and consumed by
 * P1-11.
 *
 * IT REUSES currentOwnership(), AND THAT IS THE POINT OF THE SEAM.
 *
 * BusinessDomain::currentOwnership() is the ONLY definition of "who owns this
 * now" in the system - its own docblock says so: "the open row is the only
 * definition of current owner. Nothing caches it, nothing duplicates it, and no
 * code answers this question from anywhere else." A dashboard writing its own
 * `whereNull('ended_at')` would be a second definition, and the two would agree
 * until the day ownership rules changed in one of them.
 *
 * SO THE UNOWNED COUNT IS whereDoesntHave('currentOwnership') - one correlated
 * subquery over the same relation the Business Domains screen uses. Not a loop
 * over domains asking each one, and not a second idea of what ownership means.
 *
 * IT RETURNS FACTS AND NO VERDICT. D-181: the three-way reading is P1-11's.
 *
 * OWNERSHIP GRANTS ZERO ACCESS, here as everywhere. This reads
 * business_domain_owners to count accountability and touches no entitlement,
 * scope, ceiling or role - OwnershipGrantsNoAccessTest is the behavioural half
 * of that claim.
 *
 * A MISSING SCOPE NARROWS. IT NEVER WIDENS. Same rule, same wording, as
 * PeopleSummaryProjection and ReviewerAuthority::scopeVisible() before it.
 */
final class DomainSummaryProjection
{
    /**
     * Enabled domains, and enabled domains nobody is currently accountable for.
     *
     * DISABLED DOMAINS ARE NOT COUNTED IN EITHER FIGURE. A domain the
     * organisation has switched off having no owner is not a gap - it is a
     * domain nobody needs to be accountable for. Counting them would fill the
     * Action Queue with work that does not exist, which is exactly how an
     * action queue teaches people to ignore it.
     */
    public function for(User $viewer, ?int $organisationId): DomainSummary
    {
        if (! $this->mayBeTold($viewer, $organisationId)) {
            return DomainSummary::withheld();
        }

        $enabled = BusinessDomain::query()
            ->where('organisation_id', $organisationId)
            ->where('status', DomainStatus::Enabled->value);

        $unowned = BusinessDomain::query()
            ->where('organisation_id', $organisationId)
            ->where('status', DomainStatus::Enabled->value)
            ->whereDoesntHave('currentOwnership');

        return DomainSummary::of(
            enabled: $enabled->count(),
            enabledUnowned: $unowned->count(),
        );
    }

    /**
     * The same three narrowings PeopleSummaryProjection applies, and for the
     * same reasons: no scope is not "every organisation", an inactive person is
     * told nothing, and a viewer belonging to another organisation is told
     * nothing about this one.
     */
    private function mayBeTold(User $viewer, ?int $organisationId): bool
    {
        if ($organisationId === null) {
            return false;
        }

        if (! $viewer->isActive()) {
            return false;
        }

        return $viewer->organisation_id === null
            || $viewer->organisation_id === $organisationId;
    }
}
