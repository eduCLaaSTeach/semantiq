<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Http\Controllers;

use App\Modules\Access\Support\ScopeType;
use App\Modules\Access\Support\Sensitivity;
use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;
use App\Modules\Platform\Models\User;
use App\Modules\Reviews\Models\AccessReviewItem;
use App\Modules\Reviews\Services\ReviewerAuthority;
use App\Modules\Reviews\Support\ReviewKind;
use App\Modules\Reviews\Support\ReviewState;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The three read screens.
 *
 * A REVIEWER SEES ONLY WHAT IS NEEDED TO DECIDE. Access existing is not a
 * reason to show what the access reaches: no business record appears on any of
 * these screens, and the composition is rendered in WORDS. Proving that
 * Restricted access exists by displaying a Restricted row would be a leak
 * dressed as evidence.
 *
 * THE PERMITTED SET IS COMPUTED FIRST. Every filter is a view over it, so no
 * filter can widen it, and the counts on the tabs are the ACTOR'S OWN - a
 * deployment total would disclose access the viewer may not see.
 *
 * OVERDUE IS A PROJECTION over the same items, not a second entity, so the
 * three tabs cannot disagree.
 */
final class AccessReviewsController
{
    private const PER_PAGE = 25;

    public function __construct(private readonly ReviewerAuthority $authority) {}

    public function privileged(Request $request): Response
    {
        return Inertia::render('Reviews/Privileged', $this->payload($request, ReviewKind::Privileged));
    }

    public function domains(Request $request): Response
    {
        return Inertia::render('Reviews/Domains', $this->payload($request, ReviewKind::Domain));
    }

    public function overdue(Request $request): Response
    {
        return Inertia::render('Reviews/Overdue', $this->payload($request, null, true));
    }

    /** @return array<string, mixed> */
    private function payload(Request $request, ?ReviewKind $kind, bool $overdueOnly = false): array
    {
        $actor = $this->actor($request);

        $items = $this->visible($actor)
            ->when($kind !== null, fn (Builder $q) => $q->where('kind', $kind->value))
            ->when($overdueOnly, fn (Builder $q) => $q->overdue())
            ->when(
                is_string($request->query('state')) && ReviewState::tryFrom((string) $request->query('state')) !== null,
                fn (Builder $q) => $q->where('state', (string) $request->query('state')),
            )
            ->when(
                is_string($request->query('q')) && trim((string) $request->query('q')) !== '',
                fn (Builder $q) => $q->whereHas('subject', fn (Builder $u) => $u
                    ->where('display_name', 'like', '%'.trim((string) $request->query('q')).'%')),
            )
            ->with(['subject:id,display_name,email,status', 'domain:id,name'])
            ->orderBy('due_at')
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return [
            'counts' => $this->counts($actor),
            'items' => [
                'data' => array_map(fn (AccessReviewItem $item): array => $this->row($item, $actor), $items->items()),
                'currentPage' => $items->currentPage(),
                'lastPage' => $items->lastPage(),
                'total' => $items->total(),
            ],
            'filters' => [
                'state' => $request->query('state'),
                'q' => $request->query('q'),
            ],
            'canStartCycle' => $this->canStartCycle($actor),
            'openCycle' => $this->openCycleSummary(),
        ];
    }

    /** @return Builder<AccessReviewItem> */
    private function visible(User $actor): Builder
    {
        $query = AccessReviewItem::query();
        $this->authority->scopeVisible($query, $actor);

        return $query;
    }

    /** @return array<string, int> */
    private function counts(User $actor): array
    {
        return [
            'privileged' => (clone $this->visible($actor))->where('kind', ReviewKind::Privileged->value)->pending()->count(),
            'domains' => (clone $this->visible($actor))->where('kind', ReviewKind::Domain->value)->pending()->count(),
            'overdue' => (clone $this->visible($actor))->overdue()->count(),
        ];
    }

    /** @return array<string, mixed> */
    private function row(AccessReviewItem $item, User $actor): array
    {
        $composition = is_array($item->composition) ? $item->composition : [];

        return [
            'id' => $item->getKey(),
            'kind' => $item->kind->value,
            'kindLabel' => $item->kind->label(),
            'person' => $item->subject?->display_name ?? 'Unknown person',
            'personActive' => $item->subject?->status->value === 'active',
            'role' => $item->role_code?->label() ?? '—',
            'domain' => $item->domain?->name,
            'scopes' => $this->describeScopes($composition),
            'ceiling' => $this->describeCeiling($composition),
            'state' => $item->state->value,
            'stateLabel' => $item->state->label(),
            'stateDescription' => $item->state->description(),
            'supersededReason' => $item->superseded_reason?->label(),
            'dueAt' => $item->due_at?->toDateString(),
            'overdue' => $item->isOverdue(),
            'basis' => $this->authority->basisFor($actor, $item)?->prospectiveLabel(),
            'decidable' => $item->state === ReviewState::Pending && $this->authority->canReview($actor, $item),
            'decidedBasis' => $item->decision_basis?->label(),
            'selfReview' => (bool) $item->self_review,
        ];
    }

    /**
     * The composition IN WORDS. No identifier, no row, no count of records.
     *
     * @param  array<string, mixed>  $composition
     * @return list<string>
     */
    private function describeScopes(array $composition): array
    {
        $scopes = is_array($composition['scopes'] ?? null) ? $composition['scopes'] : [];

        return array_values(array_map(
            static function (array $scope): string {
                $type = ScopeType::tryFrom((string) ($scope['type'] ?? ''));

                /*
                 * P1-05'S OWN SENTENCE, not a second wording of the same
                 * thing. "Domain" alone is ambiguous on this screen - it reads
                 * as the business domain's name - and a reviewer attesting to
                 * a grant needs to know what it actually reaches.
                 *
                 * THE FIRST SENTENCE ONLY. P1-05's Domain and Organisation
                 * descriptions end with "Today this is the same as Domain",
                 * which is implementation context about a reserved future
                 * partition - true, and meaningless to somebody deciding
                 * whether a person should still reach Finance. Found by reading
                 * the rendered screen, not by a failing test.
                 */
                return $type === null
                    ? 'An unrecognised scope. This grant is not currently authorising anything.'
                    : self::firstSentence($type->description());
            },
            $scopes,
        ));
    }

    /** Everything up to and including the first full stop. */
    private static function firstSentence(string $text): string
    {
        $end = strpos($text, '. ');

        return $end === false ? $text : substr($text, 0, $end + 1);
    }

    /** @param array<string, mixed> $composition */
    private function describeCeiling(array $composition): ?string
    {
        $value = $composition['ceiling']['sensitivity'] ?? null;

        return is_string($value) ? (Sensitivity::tryFrom($value)?->label() ?? 'Unrecognised sensitivity') : null;
    }

    private function canStartCycle(User $actor): bool
    {
        return $actor->roleAssignments()
            ->whereNull('ended_at')
            ->where('role_code', 'system_administrator')
            ->exists();
    }

    /** @return array<string, mixed>|null */
    private function openCycleSummary(): ?array
    {
        $open = AccessReviewItem::query()->pending()->min('due_at');

        return $open === null ? null : ['nextDue' => (string) $open];
    }

    private function actor(Request $request): User
    {
        return User::query()->findOrFail($request->session()->get(EnsureSessionIsCurrent::SESSION_USER_ID));
    }
}
