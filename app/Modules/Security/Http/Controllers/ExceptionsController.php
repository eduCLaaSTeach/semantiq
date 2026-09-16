<?php

declare(strict_types=1);

namespace App\Modules\Security\Http\Controllers;

use App\Modules\Security\Http\Controllers\Concerns\RendersPosture;
use App\Modules\Security\Projection\ViewerRow;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Exceptions. A DERIVED PROJECTION, not a mechanism and not a store.
 *
 * THE LIST IS RECEIVED, NOT DERIVED HERE. It comes from
 * ViewerReport::exceptions(), the same method that produces the count on every
 * other tab. A controller that re-filtered would be the accidental second
 * evaluator, and would drift from the badge within a unit or two.
 *
 * NO WRITE ACTION EXISTS. No acknowledge, no dismiss, no snooze, no "mark as
 * reviewed", and no route that could carry one - the whole prefix is GET-only
 * and the verb set is asserted. The moment an exception can be dismissed, the
 * screen stops reporting the deployment and starts reporting what somebody
 * clicked. Accepted limitations live in ControlCatalogue, a reviewed static
 * register in code, so changing what this deployment accepts costs a code
 * change and a Product Owner decision. D-77.
 *
 * D-83: no artificial not_applicable row is rendered in Release 1, so the "not
 * part of Release 1" area is empty and the screen says what is genuinely absent
 * instead of inventing rows to fill it.
 */
final class ExceptionsController
{
    use RendersPosture;

    public function show(Request $request): Response
    {
        $report = $this->report($request);

        return Inertia::render('Security/Exceptions', [
            'summary' => $this->summary($report),
            'exceptions' => array_map(
                static fn (ViewerRow $row): array => $row->toArray(),
                $report->exceptions(),
            ),
            'notApplicable' => array_map(
                static fn (ViewerRow $row): array => $row->toArray(),
                $report->notApplicable(),
            ),
            'metricCount' => count($report->metrics),
            'withheldCount' => count($report->withheld()),
        ]);
    }
}
