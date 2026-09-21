<?php

declare(strict_types=1);

namespace App\Modules\People\Projection;

use App\Modules\People\Models\Group;
use App\Modules\People\Models\GroupStatus;
use App\Modules\Platform\Models\User;
use App\Modules\Platform\Models\UserStatus;

/**
 * THE READ SEAM FOR PEOPLE - D-180, owned by P1-03 and consumed by P1-11.
 *
 * WHY THIS EXISTS AT ALL. Administration Home needs three numbers about people.
 * The alternative was three `User::query()->count()` calls written inside a
 * dashboard, and that is the failure this seam was approved to prevent: the
 * People queries and their scoping would then live in two modules, and the
 * screen that summarises Users & Groups could quietly start disagreeing with
 * Users & Groups itself.
 *
 * ADDING IT DURING P1-11'S IMPLEMENTATION DOES NOT TRANSFER OWNERSHIP TO P1-11.
 * It lives here, it is tested here, and PeopleBoundaryTest holds the fence.
 *
 * SCOPE IS APPLIED INSIDE THE SEAM, NEVER BY THE CALLER. A consumer passes the
 * organisation it resolved; it does not pass a query it built. That is what
 * makes "a missing scope narrows" a property of this file rather than a habit
 * every caller has to remember.
 *
 * A MISSING SCOPE NARROWS. IT NEVER WIDENS.
 *
 * ReviewerAuthority::scopeVisible() states the rule in its own comment - "'No
 * organisation on the assignment' must never widen into 'every organisation'" -
 * and this adopts it verbatim. A null organisation is not "count them all": it
 * is "there is nothing here to count within", and the honest answer is
 * withheld.
 *
 * NO ROW IS EVER LOADED. Three aggregates, three integers. Nothing here can
 * return a name or an email because nothing here fetches a model.
 */
final class PeopleSummaryProjection
{
    /**
     * The three counts for one organisation, or withheld.
     *
     * $organisationId is the scope the CALLER resolved - the same
     * OrganisationService::current() that Users & Groups itself is scoped by,
     * so the dashboard and the feature cannot disagree.
     */
    public function for(User $viewer, ?int $organisationId): PeopleSummary
    {
        if (! $this->mayBeTold($viewer, $organisationId)) {
            return PeopleSummary::withheld();
        }

        return PeopleSummary::of(
            activeUsers: User::query()
                ->where('organisation_id', $organisationId)
                ->where('status', UserStatus::Active->value)
                ->count(),
            inactiveUsers: User::query()
                ->where('organisation_id', $organisationId)
                ->where('status', UserStatus::Inactive->value)
                ->count(),
            activeGroups: Group::query()
                ->where('organisation_id', $organisationId)
                ->where('status', GroupStatus::Active->value)
                ->count(),
        );
    }

    /**
     * Two conditions, and both narrow.
     *
     * NO SCOPE, NO ANSWER. A null organisation is the day-one deployment and
     * the platform-scoped viewer with nothing resolved. Neither is "every
     * organisation", and this is the only place that decision is made.
     *
     * AN INACTIVE PERSON IS TOLD NOTHING. The route's middleware checks this
     * too. Both checks are deliberate: a projection that relied on its caller
     * having checked would be a projection that leaks the first time somebody
     * calls it from a console command.
     *
     * A VIEWER BELONGING SOMEWHERE ELSE IS TOLD NOTHING. Release 1 is
     * single-tenant so this cannot be reached through the screens, which is
     * exactly why it is asserted rather than assumed - the same reasoning
     * InteractsWithPeople::refuseIfOutsideOrganisation() already records. A
     * platform-scoped viewer carries no organisation of their own and is not
     * caught by it.
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
