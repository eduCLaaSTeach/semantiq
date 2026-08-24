<?php

declare(strict_types=1);

namespace App\Modules\Audit\Support;

use App\Models\User;
use App\Modules\Audit\Enums\AuditOutcome;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Identity\Support\Authorization;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Reads the audit trail for ADM-013. One table, one query, filters applied.
 *
 * DEC-004: the four functional views are FILTER PRESETS, not four screens and
 * not four queries. This class turns a preset name plus a set of filters into
 * one `audit_events` query. There is no second table, no materialised view and
 * no per-view cache - a copy of the audit trail would be a second thing that
 * could disagree with the first.
 *
 * THE NETWORK COLUMNS ARE THE SECURITY-CRITICAL PART OF THIS CLASS.
 *
 * `ip_address` is personal data and sits behind its own permission at System
 * Administrator (decision D8, SEC-DEC-063). The column is NOT SELECTED for a
 * reader without it - not masked, not blanked in the view, not hidden by CSS.
 * Absent.
 *
 * That distinction matters more than it looks: a masked value has still been
 * read out of the database, has still crossed into the response object, and is
 * one careless `dd()`, one debug toolbar or one JSON serialisation away from
 * being visible. A column that was never selected cannot leak by accident.
 *
 * It also means the Auditor capability created by D2 hands out no network
 * identifiers as a side effect of admitting somebody to the trail.
 *
 * WHAT THIS CLASS DOES NOT DO. It does not redact. `before_summary`,
 * `after_summary` and `reason` were redacted by `Redaction` at WRITE time and
 * the stored rows already contain `[redacted]` where a sensitive key was
 * matched. Re-redacting here would imply the stored data needed cleaning, which
 * would be a much more alarming fact than it is.
 */
class AuditLogQuery
{
    /** Columns every reader may see. */
    private const PUBLIC_COLUMNS = [
        'id', 'organisation_id', 'occurred_at', 'actor_user_id', 'actor_type', 'actor_label',
        'action', 'module', 'resource_type', 'resource_id', 'outcome',
        'before_summary', 'after_summary', 'reason', 'correlation_id', 'environment', 'created_at',
    ];

    /** Columns only `admin.audit.view_network` may see. */
    private const NETWORK_COLUMNS = ['ip_address'];

    public function __construct(
        private readonly Authorization $authorization,
    ) {}

    /**
     * Whether this reader may see the network identifier.
     *
     * Asked here rather than in the controller so the query and the screen
     * cannot disagree about it: the column is selected and rendered on the same
     * answer.
     */
    public function maySeeNetwork(?User $reader): bool
    {
        return $this->authorization->allows($reader, 'admin.audit.view_network');
    }

    /**
     * The events, filtered, newest first.
     *
     * @param  array<string, mixed>  $filters
     */
    public function run(?User $reader, string $view, array $filters, int $perPage = 50): LengthAwarePaginator
    {
        $query = AuditEvent::query()
            /* The tenancy scope is global on this model; this is the column
             * list, and it is where the network split is enforced. */
            ->select($this->columnsFor($reader))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');

        $this->applyView($query, $view);
        $this->applyFilters($query, $filters);

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * Which columns this reader gets. The one place the split is decided.
     *
     * @return list<string>
     */
    public function columnsFor(?User $reader): array
    {
        return $this->maySeeNetwork($reader)
            ? array_merge(self::PUBLIC_COLUMNS, self::NETWORK_COLUMNS)
            : self::PUBLIC_COLUMNS;
    }

    /**
     * Narrow to one of the declared views.
     *
     * An unknown view name falls back to no narrowing rather than to an empty
     * result. A typo in a query string should show the reader everything and
     * let them notice, not show them nothing and let them conclude the trail is
     * empty - which is the false-empty failure SEC-DEC-057 records.
     */
    private function applyView(Builder $query, string $view): void
    {
        $definition = config('governance.audit_views.'.$view);

        if (! is_array($definition)) {
            return;
        }

        $modules = (array) ($definition['modules'] ?? []);
        $prefixes = (array) ($definition['action_prefixes'] ?? []);

        if ($modules === [] && $prefixes === []) {
            return;
        }

        $query->where(function (Builder $outer) use ($modules, $prefixes): void {
            if ($modules !== []) {
                $outer->orWhereIn('module', $modules);
            }

            foreach ($prefixes as $prefix) {
                $outer->orWhere('action', 'like', $prefix.'%');
            }
        });
    }

    /**
     * The ordinary filters, on top of whichever view is selected.
     *
     * Every one is optional and every one narrows. None of them can widen past
     * the view, and none can cross the organisation boundary - that is the
     * global scope's job and this class never removes it.
     *
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        $from = $filters['from'] ?? null;
        if (is_string($from) && $from !== '') {
            $query->whereDate('occurred_at', '>=', $from);
        }

        $to = $filters['to'] ?? null;
        if (is_string($to) && $to !== '') {
            $query->whereDate('occurred_at', '<=', $to);
        }

        $actor = $filters['actor'] ?? null;
        if (is_string($actor) && $actor !== '') {
            /* Matches `actor_label`, which holds the actor's EMAIL ADDRESS -
             * captured as text at the time, so it still reads correctly after
             * the account is deleted. Not the name: `AuditLogger::actorLabel()`
             * prefers the email because it is the identifier a reader will have
             * to hand when they come looking. */
            $query->where('actor_label', 'like', '%'.$actor.'%');
        }

        $action = $filters['action'] ?? null;
        if (is_string($action) && $action !== '') {
            $query->where('action', 'like', '%'.$action.'%');
        }

        $module = $filters['module'] ?? null;
        if (is_string($module) && $module !== '') {
            $query->where('module', $module);
        }

        $outcome = $filters['outcome'] ?? null;
        if (is_string($outcome) && $outcome !== '' && AuditOutcome::tryFrom($outcome) !== null) {
            $query->where('outcome', $outcome);
        }

        $resourceType = $filters['resource_type'] ?? null;
        if (is_string($resourceType) && $resourceType !== '') {
            $query->where('resource_type', $resourceType);
        }

        $correlation = $filters['correlation_id'] ?? null;
        if (is_string($correlation) && $correlation !== '') {
            /* Exact, not a LIKE. A correlation id is quoted from somewhere else
             * and pasted in whole; a partial match would return one request's
             * events mixed with another's. */
            $query->where('correlation_id', $correlation);
        }

        $reason = $filters['reason'] ?? null;
        if (is_string($reason) && $reason !== '') {
            $query->where('reason', 'like', '%'.$reason.'%');
        }
    }

    /**
     * The distinct values a filter can usefully offer, from what is recorded.
     *
     * Read from the data rather than from a hard-coded list, so a module or
     * resource type introduced by a later gate appears without this class being
     * edited.
     *
     * @return array{modules: list<string>, resource_types: list<string>}
     */
    public function filterOptions(): array
    {
        return [
            'modules' => AuditEvent::query()
                ->select('module')->distinct()->orderBy('module')
                ->pluck('module')->filter()->values()->all(),
            'resource_types' => AuditEvent::query()
                ->select('resource_type')->distinct()->orderBy('resource_type')
                ->pluck('resource_type')->filter()->values()->all(),
        ];
    }
}
