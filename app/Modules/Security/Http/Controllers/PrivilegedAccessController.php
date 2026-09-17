<?php

declare(strict_types=1);

namespace App\Modules\Security\Http\Controllers;

use App\Modules\Security\Catalogue\ControlCatalogue;
use App\Modules\Security\Http\Controllers\Concerns\RendersPosture;
use App\Modules\Security\Projection\ViewerDomain;
use App\Modules\Security\Projection\ViewerMetric;
use App\Modules\Security\Projection\ViewerRow;
use App\Modules\Security\Projection\WithheldRow;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Privileged Access Health, and - per D-82 - domain posture as a CLEARLY
 * SEPARATED SECTION within it rather than a fifth tab.
 *
 * The four approved subscreens stand. Six of domain posture's seven facets are
 * access facts, and the seventh - the accountable owner - is accountability FOR
 * access, so this is where it belongs.
 *
 * TWO KINDS OF ROW, AND THEY ARE DIFFERENT TYPES. Posture controls carry a
 * state and contribute to the aggregate; informational metrics carry a count
 * and context and have no state field at all. The screen renders them in
 * separate panels so the distinction is visible, not just structural.
 */
final class PrivilegedAccessController
{
    use RendersPosture;

    public function show(Request $request): Response
    {
        $report = $this->report($request);

        $posture = [
            ControlCatalogue::ADMINISTRATOR_COUNT,
            ControlCatalogue::PRIVILEGED_WITH_DATA,
            ControlCatalogue::INCOMPLETE_PATHS,
            ControlCatalogue::STEP_UP_LOCAL_PRIVILEGED,
            ControlCatalogue::STEP_UP_EXTERNAL_PRIVILEGED,
            ControlCatalogue::INACTIVE_GATE,
        ];

        $metrics = [
            ControlCatalogue::ORGANISATION_ADMINISTRATORS,
            ControlCatalogue::RESTRICTED_GRANTS,
            ControlCatalogue::INACTIVE_WITH_ASSIGNMENTS,
            ControlCatalogue::OWNERS_WITHOUT_ENTITLEMENT,
            ControlCatalogue::BROAD_SCOPES,
        ];

        return Inertia::render('Security/PrivilegedAccess', [
            'summary' => $this->summary($report),
            'rows' => array_map(
                static fn (ViewerRow|WithheldRow $row): array => $row->toArray(),
                $report->only(...$posture),
            ),
            'metrics' => array_map(
                static fn (ViewerMetric $metric): array => $metric->toArray(),
                $report->metricsOnly(...$metrics),
            ),
            'domains' => array_map(
                static fn (ViewerDomain $domain): array => $domain->toArray(),
                $report->domains,
            ),
        ]);
    }
}
