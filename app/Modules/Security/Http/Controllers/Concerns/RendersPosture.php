<?php

declare(strict_types=1);

namespace App\Modules\Security\Http\Controllers\Concerns;

use App\Modules\Access\Engine\AccessEngine;
use App\Modules\Platform\Models\User;
use App\Modules\Security\Posture\PostureEvaluator;
use App\Modules\Security\Projection\PostureProjection;
use App\Modules\Security\Projection\Viewer;
use App\Modules\Security\Projection\ViewerReport;
use Illuminate\Http\Request;

/**
 * The ONE way a Security Status controller obtains something renderable.
 *
 * A controller never sees a PostureReport, a PostureRow, a MetricRow or a
 * PostureState - it receives a ViewerReport, already projected. That is not a
 * convention: N-SS26's architecture guard asserts that no controller and
 * nothing under resources/js references those four names at all, so a
 * controller has NOTHING TO RENDER unless it has already projected.
 *
 * Posture is recomputed here on every request. D-81: no P1-06 cache, for the
 * same reason D-69 refuses a permission cache - a cached posture is wrong at
 * exactly the moment something has just changed.
 */
trait RendersPosture
{
    /**
     * The deployment summary every tab shows. ONE DERIVATION, ONE NUMBER.
     *
     * The badge, the caption and the tab count all come from here, so a tab
     * cannot disagree with the badge above it or with the list beneath it.
     *
     * @return array<string, mixed>
     */
    protected function summary(ViewerReport $report): array
    {
        return [
            'aggregate' => $report->aggregate()->value,
            'aggregateLabel' => $report->aggregateLabel(),
            'caption' => $report->caption(),
            'exceptionCount' => count($report->exceptions()),
            'seesPlatformValues' => $report->seesPlatformValues,
        ];
    }

    protected function report(Request $request): ViewerReport
    {
        $user = $request->attributes->get('semantiq_user');

        return PostureProjection::for(
            app(PostureEvaluator::class)->evaluate(),
            Viewer::for($user instanceof User ? $user : null, app(AccessEngine::class)),
        );
    }
}
