<?php

declare(strict_types=1);

namespace App\Modules\Security\Http\Controllers;

use App\Modules\Security\Catalogue\ControlCatalogue;
use App\Modules\Security\Http\Controllers\Concerns\RendersPosture;
use App\Modules\Security\Projection\ViewerRow;
use App\Modules\Security\Projection\WithheldRow;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Secure Baseline. A GET, and nothing else.
 *
 * Every control here is enforced somewhere else and was already accepted;
 * P1-06 owns none of them. Each row's only affordance is a LINK to the screen
 * that owns it, and that screen re-authorises on arrival through its own
 * RequireActionClass - visibility here is never permission there.
 */
final class BaselineController
{
    use RendersPosture;

    public function show(Request $request): Response
    {
        $report = $this->report($request);

        $controls = [
            ControlCatalogue::IDENTITY_TRUST,
            ControlCatalogue::APPROVED_PROVIDERS,
            ControlCatalogue::SIGN_IN_REACHABLE,
            ControlCatalogue::SESSION_POLICY,
            ControlCatalogue::INACTIVE_ACCOUNTS,
            ControlCatalogue::ROUTE_COVERAGE,
            ControlCatalogue::GRANT_REQUIRED,
            ControlCatalogue::LAST_ADMINISTRATOR,
            ControlCatalogue::STEP_UP_LOCAL,
            ControlCatalogue::STEP_UP_EXTERNAL,
            ControlCatalogue::SSO_RECHECK,
            ControlCatalogue::ENCRYPTION,
            ControlCatalogue::WEB_EXPOSURE,
            ControlCatalogue::BACKUPS,
        ];

        return Inertia::render('Security/Baseline', [
            'summary' => $this->summary($report),
            'rows' => array_map(
                static fn (ViewerRow|WithheldRow $row): array => $row->toArray(),
                $report->only(...$controls),
            ),
        ]);
    }
}
