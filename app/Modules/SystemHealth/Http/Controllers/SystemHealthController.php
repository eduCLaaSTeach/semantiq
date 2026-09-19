<?php

declare(strict_types=1);

namespace App\Modules\SystemHealth\Http\Controllers;

use App\Modules\SystemHealth\Report\SystemHealthReport;
use Inertia\Inertia;
use Inertia\Response;

/**
 * ONE GET, AND NOTHING ELSE UNDER THIS PREFIX.
 *
 * There is no write path here at all - no acknowledge, no dismiss, no restart,
 * no clear cache, no re-run migrations. A page that can fix things is a page
 * that can break them, and this one is read by somebody who is already having a
 * bad day. SystemHealthArchitectureTest asserts the route set as an EQUALITY,
 * so a second verb fails the build rather than quietly becoming one.
 *
 * The Check sign-in now control posts to P1-02's OWN endpoint,
 * identity.health.recheck - already behind PlatformAdmin, already rate limited
 * per administrator, already recording its events. P1-09 adds no probe, no rate
 * limiter, no event key and no route of its own for it.
 *
 * RENDERING RECORDS NOTHING. Reading a health page is not a security event: it
 * changes no state, reveals no evidence and names no person, and the catalogue
 * has no key for it. Adding one would mean every refresh wrote a row into the
 * audit chain.
 */
final class SystemHealthController
{
    public function show(SystemHealthReport $report): Response
    {
        return Inertia::render('SystemHealth/Index', [
            'areas' => $report->toArray(),
        ]);
    }
}
