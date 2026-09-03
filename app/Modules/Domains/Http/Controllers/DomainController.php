<?php

declare(strict_types=1);

namespace App\Modules\Domains\Http\Controllers;

use App\Modules\Domains\Http\Controllers\Concerns\InteractsWithDomains;
use App\Modules\Domains\Models\AccessExpectation;
use App\Modules\Domains\Models\BusinessDomain;
use App\Modules\Domains\Models\DomainOwnership;
use App\Modules\Domains\Services\DomainOwnershipService;
use App\Modules\Domains\Services\DomainService;
use App\Modules\Domains\Support\DomainViolation;
use App\Modules\Platform\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Business Domains: what the organisation's intelligence is about, and who is
 * accountable for each part of it.
 *
 * NOTHING ON THESE SCREENS ANSWERS AN ACCESS QUESTION, and nothing in this
 * controller reads a domain to decide what anybody may see. Every route
 * re-authorises through RequireSystemAdministrator and RequireOrganisation, and
 * the only thing a domain's status or owner changes is what this screen shows
 * about that domain. DomainsBoundaryTest asserts that rather than leaving it as
 * an intention.
 *
 * ONE LIST AND ONE RECORD PAGE, no tab strip. P1-03 has tabs because it
 * delivers two kinds of thing; this delivers one. Baseline and custom domains
 * are the same object with a different origin, and a tab would ask the reader
 * to know which tab a domain lives in before they could find it.
 */
final class DomainController
{
    use InteractsWithDomains;

    private const PER_PAGE = 25;

    public function __construct(
        private readonly DomainService $domains,
        private readonly DomainOwnershipService $ownership,
    ) {}

    public function index(Request $request): Response
    {
        $organisation = $this->organisation($request);

        $search = trim((string) $request->query('search', ''));
        $kind = (string) $request->query('kind', '');
        $status = (string) $request->query('status', '');
        $owner = (string) $request->query('owner', '');

        $domains = BusinessDomain::query()
            ->where('organisation_id', $organisation->id)
            ->when($search !== '', fn ($query) => $query->where(
                fn ($q) => $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
            ))
            ->when(in_array($kind, ['baseline', 'custom'], true), fn ($query) => $query->where('kind', $kind))
            ->when(in_array($status, ['enabled', 'disabled'], true), fn ($query) => $query->where('status', $status))
            /*
             * The owner filter is computed from the OPEN ownership row and, for
             * "attention", a join to the owner's status. Nothing is stored: a
             * needs_attention column would be a second source of truth that
             * goes stale the moment somebody is deactivated.
             */
            ->when($owner === 'assigned', fn ($query) => $query->whereHas('currentOwnership'))
            ->when($owner === 'unassigned', fn ($query) => $query->whereDoesntHave('currentOwnership'))
            ->when($owner === 'attention', fn ($query) => $query->whereHas(
                'currentOwnership',
                fn ($q) => $q->whereHas('user', fn ($u) => $u->where('status', '!=', 'active'))
            ))
            ->with(['currentOwnership.user:id,display_name,status'])
            ->orderBy('kind')
            ->orderBy('name')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('Domains/Index', [
            'domains' => [
                'data' => collect($domains->items())
                    ->map(fn (BusinessDomain $domain): array => $this->summary($domain))
                    ->all(),
                'total' => $domains->total(),
                'perPage' => $domains->perPage(),
                'currentPage' => $domains->currentPage(),
                'lastPage' => $domains->lastPage(),
            ],
            'filters' => ['search' => $search, 'kind' => $kind, 'status' => $status, 'owner' => $owner],
            // Whether the table is empty because there are no domains at all,
            // or because a filter matched nothing. Two different facts, and
            // P1-03 shipped a defect by conflating them once already.
            'anyDomains' => BusinessDomain::query()->where('organisation_id', $organisation->id)->exists(),
        ]);
    }

    public function show(Request $request, BusinessDomain $domain): Response
    {
        $this->refuseIfOutsideOrganisation($request, $domain->organisation_id);

        $history = DomainOwnership::query()
            ->where('business_domain_id', $domain->id)
            ->with('user:id,display_name,email,status')
            ->orderByDesc('assigned_at')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('Domains/Record', [
            'domain' => $this->summary($domain) + [
                'description' => $domain->description,
                'expectation' => $domain->access_expectation->value,
                'purgeable' => $this->domains->isPurgeable($domain),
            ],
            'history' => $history->map(fn (DomainOwnership $period): array => [
                'id' => $period->id,
                'name' => $period->user?->display_name,
                'email' => $period->user?->email,
                'userStatus' => $period->user?->status->value,
                'assignedAt' => $period->assigned_at->toDateString(),
                'endedAt' => $period->ended_at?->toDateString(),
                'current' => $period->isCurrent(),
            ])->all(),
            'expectations' => AccessExpectation::options(),
            // Only ACTIVE people of this organisation are offered. The refusal
            // still exists and is still tested - a picker is a convenience,
            // never the guard.
            'candidates' => User::query()
                ->where('organisation_id', $domain->organisation_id)
                ->where('status', 'active')
                ->orderBy('display_name')
                ->get(['id', 'display_name', 'email'])
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->display_name,
                    'email' => $user->email,
                ])->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:32', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/i'],
            'description' => ['nullable', 'string', 'max:500'],
        ], [
            'code.regex' => 'A code uses letters, numbers and hyphens, with no spaces.',
        ]);

        try {
            $domain = $this->domains->create($this->organisation($request), $data, $this->actor($request));
        } catch (DomainViolation $violation) {
            return $this->refuse($violation);
        }

        return $this->confirm('domains.show', 'Domain created.', $domain->id);
    }

    public function update(Request $request, BusinessDomain $domain): RedirectResponse
    {
        $this->refuseIfOutsideOrganisation($request, $domain->organisation_id);

        // `code` and `kind` are absent from this list and from the service's
        // parameters. An extra field in the request has nowhere to arrive.
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'access_expectation' => ['required', 'string', 'in:undecided,broad,limited,exceptional'],
        ]);

        try {
            $this->domains->update($domain, $data, $this->actor($request));
        } catch (DomainViolation $violation) {
            return $this->refuse($violation);
        }

        return $this->confirm('domains.show', 'Domain updated.', $domain->id);
    }

    public function enable(Request $request, BusinessDomain $domain): RedirectResponse
    {
        $this->refuseIfOutsideOrganisation($request, $domain->organisation_id);

        try {
            $this->domains->enable($domain, $this->actor($request));
        } catch (DomainViolation $violation) {
            return $this->refuse($violation);
        }

        return $this->confirm('domains.show', 'Domain enabled.', $domain->id);
    }

    public function disable(Request $request, BusinessDomain $domain): RedirectResponse
    {
        $this->refuseIfOutsideOrganisation($request, $domain->organisation_id);

        $this->domains->disable($domain, $this->actor($request));

        return $this->confirm('domains.show', 'Domain disabled.', $domain->id);
    }

    public function setOwner(Request $request, BusinessDomain $domain): RedirectResponse
    {
        $this->refuseIfOutsideOrganisation($request, $domain->organisation_id);

        $data = $request->validate(['user_id' => ['required', 'integer']]);

        $owner = User::query()->whereKey($data['user_id'])->first();

        if ($owner === null) {
            return $this->refuse(DomainViolation::ownerOutsideOrganisation());
        }

        try {
            $this->ownership->set($domain, $owner, $this->actor($request));
        } catch (DomainViolation $violation) {
            return $this->refuse($violation);
        }

        return $this->confirm('domains.show', 'Owner assigned.', $domain->id);
    }

    public function clearOwner(Request $request, BusinessDomain $domain): RedirectResponse
    {
        $this->refuseIfOutsideOrganisation($request, $domain->organisation_id);

        try {
            $this->ownership->clear($domain, $this->actor($request));
        } catch (DomainViolation $violation) {
            return $this->refuse($violation);
        }

        return $this->confirm('domains.show', 'Owner cleared.', $domain->id);
    }

    public function purge(Request $request, BusinessDomain $domain): RedirectResponse
    {
        $this->refuseIfOutsideOrganisation($request, $domain->organisation_id);

        try {
            $this->domains->purge($domain, $this->actor($request));
        } catch (DomainViolation $violation) {
            return $this->refuse($violation);
        }

        return $this->confirm('domains.index', 'Domain removed.');
    }

    /**
     * What every screen says about a domain.
     *
     * `needsAttention` is DERIVED here and stored nowhere: a domain has a
     * current owner and that owner is not active. It is a prompt, not a
     * refusal, and the domain stays enabled - P1-04 does not get to refuse a
     * P1-03 deactivation, so the drift is surfaced rather than prevented.
     *
     * @return array<string, mixed>
     */
    private function summary(BusinessDomain $domain): array
    {
        $current = $domain->currentOwnership;
        $owner = $current?->user;

        return [
            'id' => $domain->id,
            'name' => $domain->name,
            'code' => $domain->code,
            'kind' => $domain->kind->value,
            'kindLabel' => $domain->kind->label(),
            'status' => $domain->status->value,
            'statusLabel' => $domain->status->label(),
            'expectationLabel' => $domain->access_expectation->label(),
            'owner' => $owner === null ? null : [
                'id' => $owner->id,
                'name' => $owner->display_name,
                'active' => $owner->isActive(),
            ],
            'needsAttention' => $owner !== null && ! $owner->isActive(),
        ];
    }
}
