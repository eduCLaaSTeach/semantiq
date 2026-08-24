<?php

declare(strict_types=1);

namespace App\Modules\Governance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Audit\Enums\AuditOutcome;
use App\Modules\Audit\Support\AuditLogQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Audit Logs. Feature ADM-013.
 *
 * ONE READ-ONLY SCREEN. No write route exists, and none should: an audit screen
 * that could change an audit event would defeat the append-only protection the
 * database triggers exist to enforce.
 *
 * FOUR FUNCTIONAL VIEWS AS FILTER PRESETS, per DEC-004. `?view=security`
 * selects one; the ordinary filters then narrow within it. One table, one
 * query, one route - so the future Governance Overview can link straight to
 * `/audit-logs?view=security` rather than the product maintaining four parallel
 * screens to link into.
 *
 * THE NETWORK IDENTIFIER IS NOT SELECTED for a reader without
 * `admin.audit.view_network`. Not masked, not blanked in the blade - absent
 * from the query. `AuditLogQuery` owns that, and this controller passes the
 * same answer to the view so the column and the header cannot disagree.
 *
 * NO STORAGE GUARD. `audit_events` has existed since gate 1, so unlike every
 * other governance screen there is no deployment window in which this table is
 * missing. The two indexes R1.4b adds make the presets fast; their absence
 * would make the screen slow, never broken.
 */
class AuditLogController extends Controller
{
    public function __construct(
        private readonly AuditLogQuery $query,
    ) {}

    public function __invoke(Request $request): View
    {
        /** @var User|null $reader */
        $reader = Auth::user();

        $views = (array) config('governance.audit_views', []);

        /* An unknown view falls back to everything rather than to nothing. A
         * typo in a URL should show a reader too much and let them notice, not
         * show them an empty trail and let them conclude it is empty. */
        $view = (string) $request->query('view', 'all');
        if (! array_key_exists($view, $views)) {
            $view = 'all';
        }

        $filters = [
            'from' => $request->query('from'),
            'to' => $request->query('to'),
            'actor' => $request->query('actor'),
            'action' => $request->query('action'),
            'module' => $request->query('module'),
            'outcome' => $request->query('outcome'),
            'resource_type' => $request->query('resource_type'),
            'correlation_id' => $request->query('correlation_id'),
            'reason' => $request->query('reason'),
        ];

        $options = $this->query->filterOptions();

        return view('pages.admin.governance.audit-log', [
            'views' => $views,
            'view' => $view,
            'viewDefinition' => $views[$view],
            'filters' => $filters,
            'anyFilterApplied' => collect($filters)->filter(
                static fn ($v): bool => is_string($v) && $v !== ''
            )->isNotEmpty(),
            'events' => $this->query->run($reader, $view, $filters),
            /* Passed so the table renders the column only when the query
             * actually selected it. One answer, used twice. */
            'maySeeNetwork' => $this->query->maySeeNetwork($reader),
            'modules' => $options['modules'],
            'resourceTypes' => $options['resource_types'],
            'outcomes' => AuditOutcome::cases(),
        ]);
    }
}
