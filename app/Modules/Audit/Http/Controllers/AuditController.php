<?php

declare(strict_types=1);

namespace App\Modules\Audit\Http\Controllers;

use App\Modules\Audit\Models\AuditChainHead;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Audit\Projection\AuditProjection;
use App\Modules\Audit\Services\AuditChainVerifier;
use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;
use App\Modules\Platform\Models\User;
use App\Modules\Security\Catalogue\AuditCategory;
use App\Modules\Security\Catalogue\EventCatalogue;
use App\Shared\Navigation\NavigationRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Audit. FOUR GETS AND NOTHING ELSE.
 *
 * There is no POST, PATCH, PUT or DELETE anywhere under /console/audit, and
 * AuditImmutabilityTest asserts the route set as an EQUALITY - a fifth verb
 * fails the build rather than quietly becoming an edit path. That is how D-97's
 * "no application update or delete path" is enforced, rather than by intention.
 *
 * EvidenceRead ADMITS THE VIEWER TO THE ENDPOINT; the projection decides the
 * ROWS and the FIELDS. The class is not the projection, and conflating them is
 * what showed an Auditor an empty Security Status screen in P1-06.
 */
final class AuditController
{
    private const PER_PAGE = 25;

    public function __construct(
        private readonly AuditProjection $projection,
        private readonly AuditChainVerifier $verifier,
    ) {}

    public function userAccess(Request $request): Response
    {
        return $this->tab($request, AuditCategory::UserAccess);
    }

    public function adminChanges(Request $request): Response
    {
        return $this->tab($request, AuditCategory::AdminChanges);
    }

    public function securityEvents(Request $request): Response
    {
        return $this->tab($request, AuditCategory::SecurityEvents);
    }

    public function configuration(Request $request): Response
    {
        return $this->tab($request, AuditCategory::ConfigurationChanges);
    }

    private function tab(Request $request, AuditCategory $category): Response
    {
        $viewer = $this->actor($request);
        $organisationId = $request->attributes->get('semantiq_organisation')?->id;

        $filters = $this->filters($request);

        $events = $this->query($viewer, $organisationId, $category, $filters)
            ->orderByDesc('occurred_at')
            ->orderByDesc('sequence')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $names = $this->names($events->getCollection()->all());
        $personName = static fn (int $id): string => $names[$id] ?? 'Account removed';

        return Inertia::render('Audit/Log', [
            'productAreas' => app(NavigationRegistry::class)->visibleFor(),
            'category' => $category->value,
            'title' => $category->label(),
            'description' => $category->description(),
            'counts' => $this->counts($viewer, $organisationId),
            'filters' => $filters,
            'actions' => $this->actionOptions($category),
            'evidenceStart' => $this->evidenceStart(),
            'chain' => $this->verifier->verify(),
            'events' => [
                'data' => array_map(
                    fn (AuditEvent $e): array => $this->projection->row($e, $viewer, $personName),
                    $events->items(),
                ),
                'currentPage' => $events->currentPage(),
                'lastPage' => $events->lastPage(),
                'total' => $events->total(),
            ],
        ]);
    }

    /**
     * @param  array<string, string|null>  $filters
     * @return Builder<AuditEvent>
     */
    private function query(User $viewer, ?int $organisationId, AuditCategory $category, array $filters): Builder
    {
        $query = AuditEvent::query()->inCategory($category);

        $this->projection->scopeVisible($query, $viewer, $organisationId);

        if ($filters['event'] !== null) {
            $query->where('event', $filters['event']);
        }

        if ($filters['outcome'] !== null) {
            $query->where('outcome', $filters['outcome']);
        }

        if ($filters['from'] !== null) {
            $query->where('occurred_at', '>=', $filters['from'].' 00:00:00');
        }

        if ($filters['to'] !== null) {
            $query->where('occurred_at', '<=', $filters['to'].' 23:59:59');
        }

        /*
         * PERSON FILTERS MATCH A NAME, NEVER A FREE-TEXT SEARCH OVER CONTEXT.
         * There is no free text in an audit row to search - D-102 - and adding
         * a searchable note field is exactly the leak channel P1-06 refused.
         */
        if ($filters['person'] !== null) {
            $ids = User::query()
                ->where('display_name', 'like', '%'.$filters['person'].'%')
                ->pluck('id');

            $query->where(function (Builder $who) use ($ids): void {
                $who->whereIn('actor_user_id', $ids)->orWhereIn('subject_user_id', $ids);
            });
        }

        return $query;
    }

    /** @return array<string, string|null> */
    private function filters(Request $request): array
    {
        $event = (string) $request->query('event', '');

        return [
            // An unrecognised key filters nothing rather than erroring - and it
            // cannot reach the query, so it cannot be an injection surface.
            'event' => in_array($event, EventCatalogue::semanticsKeys(), true) ? $event : null,
            'outcome' => $this->trimmed($request, 'outcome', 24),
            'person' => $this->trimmed($request, 'person', 80),
            'from' => $this->date($request, 'from'),
            'to' => $this->date($request, 'to'),
        ];
    }

    private function trimmed(Request $request, string $key, int $max): ?string
    {
        $value = mb_substr(trim((string) $request->query($key, '')), 0, $max);

        return $value === '' ? null : $value;
    }

    private function date(Request $request, string $key): ?string
    {
        $value = trim((string) $request->query($key, ''));

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;
    }

    /** The actions a reader may filter by, in business words, for THIS tab. */
    private function actionOptions(AuditCategory $category): array
    {
        $options = [];

        foreach (EventCatalogue::semanticsKeys() as $key) {
            if (EventCatalogue::semanticsFor($key)->category !== $category) {
                continue;
            }

            $options[] = ['value' => $key, 'label' => EventCatalogue::mapping()[$key][1] ?? $key];
        }

        usort($options, static fn (array $a, array $b): int => strcmp($a['label'], $b['label']));

        return $options;
    }

    /** @return array<string, int> */
    private function counts(User $viewer, ?int $organisationId): array
    {
        $counts = [];

        foreach (AuditCategory::cases() as $category) {
            $query = AuditEvent::query()->inCategory($category);
            $this->projection->scopeVisible($query, $viewer, $organisationId);

            $counts[$category->value] = $query->count();
        }

        return $counts;
    }

    /**
     * D-109. A STORED INSTANT, never MIN(occurred_at) - a computed start date
     * moves when the earliest row is removed, and would then look entirely
     * consistent while concealing what the chain exists to reveal.
     */
    private function evidenceStart(): ?string
    {
        return AuditChainHead::query()->find(AuditChainHead::ID)?->started_at?->format('j F Y, H:i');
    }

    /**
     * @param  list<AuditEvent>  $events
     * @return array<int, string>
     */
    private function names(array $events): array
    {
        $ids = [];

        foreach ($events as $event) {
            $ids[] = $event->actor_user_id;
            $ids[] = $event->subject_user_id;
        }

        $ids = array_values(array_unique(array_filter($ids)));

        return $ids === []
            ? []
            : User::query()->whereIn('id', $ids)->pluck('display_name', 'id')->all();
    }

    private function actor(Request $request): User
    {
        return User::query()->findOrFail($request->session()->get(EnsureSessionIsCurrent::SESSION_USER_ID));
    }
}
