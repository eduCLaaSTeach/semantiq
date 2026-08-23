<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Audit\Support\CorrelationId;
use App\Modules\Platform\Support\HealthProbe;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * The Diagnostics screen. Feature ADM-024.
 *
 * Safe troubleshooting: enough for an administrator to tell whether the problem
 * is the application, the database, the queue, the scheduler or Microsoft, and
 * enough to hand support a reference - without any of the things ADM-024's
 * "Never expose" section lists.
 *
 * WHAT THIS SCREEN MUST NEVER SHOW. `.env` contents, passwords, API
 * credentials, tokens, secret values, production row data, host names,
 * database names, file paths. That is not a review note, it is the acceptance
 * criterion. The controller therefore assembles nothing itself: every fact
 * comes from `HealthProbe`, which redacts and which limits itself to driver
 * names, and from correlation ids, which carry no information at all.
 *
 * The extended fact set is behind the `platform.extended_diagnostics` flag.
 * Even redacted, a description of the runtime is worth something to somebody
 * who has not seen one, so it is off unless an administrator is actually
 * investigating.
 */
class DiagnosticsController extends Controller
{
    /** Enough recent failures to spot a pattern, few enough to read. */
    private const RECENT_FAILURE_LIMIT = 10;

    public function __invoke(HealthProbe $probe): View
    {
        $checks = $probe->run();

        return view('pages.admin.diagnostics', [
            'facts' => $probe->runtimeFacts(),
            'checks' => $checks,
            'overall' => $probe->overall($checks),
            'failures' => $this->recentFailures(),
            /*
             * The id of THIS page view. An administrator raising a support
             * request can quote it, and it appears in the logs beside whatever
             * else this request did. It identifies a request, not a person.
             */
            'correlationId' => CorrelationId::current(),
        ]);
    }

    /**
     * Recent failed and denied actions, for their correlation ids.
     *
     * The ids are the point. They are random, carry no information, and give
     * support something precise to search for - which is exactly what ADM-024
     * asks for when it says "recent error correlation IDs" rather than "recent
     * errors".
     *
     * @return Collection<int, AuditEvent>
     */
    private function recentFailures(): Collection
    {
        return AuditEvent::query()
            ->whereIn('outcome', ['failed', 'denied'])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(self::RECENT_FAILURE_LIMIT)
            ->get();
    }
}
