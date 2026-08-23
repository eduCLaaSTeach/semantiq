<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Identity\Models\BusinessUnit;
use App\Modules\Identity\Services\StructureRegistry;
use App\Modules\Platform\Enums\LifecycleStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;

/**
 * Business units. Feature ADM-003.
 *
 * A business unit is a SCOPE, never a permission. Nothing this screen writes
 * grants access to anything.
 *
 * Every rule lives in `StructureRegistry` rather than here - the loop check
 * most of all - because a console command or a later import path must get the
 * same refusals as this form.
 */
class BusinessUnitController extends Controller
{
    public function __construct(
        private readonly StructureRegistry $structure,
    ) {}

    public function index(): View
    {
        /*
         * Ordered by name and assembled into a tree in the view. Loading the
         * whole set is right at this size - an organisation chart is dozens of
         * rows, not thousands - and it lets the hierarchy render without a
         * query per level.
         */
        $units = BusinessUnit::query()
            ->with(['parent:id,name', 'manager:id,name'])
            ->withCount(['teams', 'members'])
            ->orderBy('name')
            ->get();

        return view('pages.admin.business-units', ['units' => $units]);
    }

    public function create(): View
    {
        return view('pages.admin.business-unit-form', [
            'unit' => null,
            'parents' => $this->availableParents(null),
            'managers' => $this->managers(),
        ]);
    }

    public function edit(BusinessUnit $businessUnit): View
    {
        return view('pages.admin.business-unit-form', [
            'unit' => $businessUnit,
            /* Its own subtree is excluded from the list, so the commonest way
             * to create a loop is not even offerable. The service still checks
             * - a select is not an authorization control. */
            'parents' => $this->availableParents($businessUnit),
            'managers' => $this->managers(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request, null);

        /** @var User $actor */
        $actor = Auth::user();

        try {
            $this->structure->createBusinessUnit($validated, $actor);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return back()->withInput()->withErrors(['form' => $exception->getMessage()]);
        }

        return redirect()->route('admin.business-units')->with('status', 'Business unit created.');
    }

    public function update(Request $request, BusinessUnit $businessUnit): RedirectResponse
    {
        $validated = $this->validated($request, $businessUnit);

        /** @var User $actor */
        $actor = Auth::user();

        try {
            $this->structure->updateBusinessUnit($businessUnit, $validated, $actor);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return back()->withInput()->withErrors(['form' => $exception->getMessage()]);
        }

        return redirect()->route('admin.business-units')->with('status', 'Business unit saved.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?BusinessUnit $unit): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:190'],
            'parent_id' => ['nullable', 'integer'],
            'manager_user_id' => ['nullable', 'integer'],
            'cost_centre' => ['nullable', 'string', 'max:64'],
            'country' => ['nullable', 'string', 'size:2'],
            'effective_from' => ['nullable', 'date'],
            /* An end before a start is a window that never opens. */
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'status' => ['nullable', 'string'],
        ];

        /* The code is set once. VAL-BU-CODE-001 makes it the stable identifier,
         * and a stable identifier that can be edited is not one. */
        if ($unit === null) {
            $rules['code'] = ['required', 'string', 'max:32'];
        }

        $validated = $request->validate($rules, [
            'effective_to.after_or_equal' => 'The end date cannot be before the start date.',
        ]);

        $validated['status'] = LifecycleStatus::tryFrom((string) ($validated['status'] ?? 'active'))
            ?? LifecycleStatus::Active;

        return $validated;
    }

    /**
     * Parents a unit may be moved under.
     *
     * Excludes the unit itself and everything beneath it, which is the
     * commonest way to create a loop. The service checks again, because a
     * filtered select stops a mistake and does nothing about a crafted post.
     *
     * @return Collection<int, BusinessUnit>
     */
    private function availableParents(?BusinessUnit $unit): Collection
    {
        $all = BusinessUnit::query()->orderBy('name')->get();

        if ($unit === null) {
            return $all;
        }

        $excluded = $this->subtreeIds($unit, $all);

        return $all->reject(fn (BusinessUnit $candidate): bool => in_array($candidate->getKey(), $excluded, true))->values();
    }

    /**
     * The ids of a unit and everything under it.
     *
     * Walked over the already-loaded set rather than querying per level, and
     * bounded by the collection size so a pre-existing cycle cannot spin here.
     *
     * @param  Collection<int, BusinessUnit>  $all
     * @return list<int>
     */
    private function subtreeIds(BusinessUnit $unit, Collection $all): array
    {
        $ids = [$unit->getKey()];
        $frontier = [$unit->getKey()];
        $guard = $all->count() + 1;

        while ($frontier !== [] && $guard-- > 0) {
            $children = $all->whereIn('parent_id', $frontier)->pluck('id')->all();
            $children = array_values(array_diff($children, $ids));

            $ids = array_merge($ids, $children);
            $frontier = $children;
        }

        return $ids;
    }

    /**
     * Accounts that can be named as a unit manager.
     *
     * Explicitly organisation-scoped - `users` carries no global scope, for the
     * reason recorded on `User::scopeInCurrentOrganisation()`.
     *
     * @return Collection<int, User>
     */
    private function managers(): Collection
    {
        return User::query()
            ->inCurrentOrganisation()
            ->where('status', LifecycleStatus::Active->value)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }
}
