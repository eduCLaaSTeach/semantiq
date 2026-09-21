<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Projection;

use App\Modules\Platform\Models\User;
use App\Modules\Reviews\Models\AccessReviewItem;
use App\Modules\Reviews\Services\ReviewerAuthority;
use Illuminate\Database\Eloquent\Builder;

/**
 * THE READ SEAM FOR ACCESS REVIEWS, owned by P1-07 and consumed by P1-11.
 *
 * WHY THIS EXISTS, WHEN THE DESIGN SAID P1-11 COULD COUNT THE BUILDER ITSELF.
 *
 * The DESIGN says two things that cannot both hold: §4.6 has Administration
 * Home calling ->count() on a scoped AccessReviewItem builder, and guard G3
 * says no P1-11 file may reference AccessReviewItem or import
 * Illuminate\Database at all. The second is the rule the unit exists to
 * defend - Administration Home composes and does not query - so the count
 * moved here, into the module that owns the fact. It is the same shape D-180
 * and D-181 already approved twice.
 *
 * VISIBILITY IS DECIDED ENTIRELY BY P1-07. ReviewerAuthority::scopeVisible()
 * is asked; nothing here writes a `where` of its own against a review table
 * beyond the two scopes that class and model already expose. So a consumer
 * cannot see more through this seam than through the Access Reviews screen,
 * and cannot see less either - the numbers agree because they come from the
 * same scope.
 *
 * scopeVisible() IS ALREADY ORGANISATION-SCOPED FOR A PLATFORM-SCOPED SYSTEM
 * ADMINISTRATOR, and says so in its own comment: "'No organisation on the
 * assignment' must never widen into 'every organisation'." This inherits that
 * rather than re-deciding it.
 *
 * A NULL ORGANISATION IS WITHHELD, NOT ZERO. Passing null would produce a
 * perfectly true "no reviews are overdue" about a scope nobody has, and "no
 * reviews are overdue" is the single most reassuring sentence this projection
 * can produce.
 *
 * NO ROW IS LOADED. Two aggregates, two integers.
 */
final class ReviewSummaryProjection
{
    public function __construct(private readonly ReviewerAuthority $authority) {}

    public function for(User $viewer, ?int $organisationId): ReviewSummary
    {
        if ($organisationId === null || ! $viewer->isActive()) {
            return ReviewSummary::withheld();
        }

        return ReviewSummary::of(
            outstanding: $this->scoped($viewer, $organisationId)->pending()->count(),
            overdue: $this->scoped($viewer, $organisationId)->overdue()->count(),
        );
    }

    /** @return Builder<AccessReviewItem> */
    private function scoped(User $viewer, int $organisationId): Builder
    {
        $query = AccessReviewItem::query();

        $this->authority->scopeVisible($query, $viewer, $organisationId);

        return $query;
    }
}
