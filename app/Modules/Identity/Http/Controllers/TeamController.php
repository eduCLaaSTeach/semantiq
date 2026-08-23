<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Identity\Models\BusinessUnit;
use App\Modules\Identity\Models\Team;
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
 * Teams. Feature ADM-004.
 *
 * A team is a scope, never a permission - the same rule as a business unit.
 *
 * VAL-TEAM-BU-001 is what shapes this screen: a team belongs to exactly one
 * business unit, so the field is required and the select is never empty-able.
 * Moving a team between units is audited as its own event, because it changes
 * who reports where.
 */
class TeamController extends Controller
{
    public function __construct(
        private readonly StructureRegistry $structure,
    ) {}

    public function index(): View
    {
        $teams = Team::query()
            ->with(['businessUnit:id,name', 'lead:id,name'])
            ->withCount('members')
            ->orderBy('name')
            ->get();

        return view('pages.admin.teams', ['teams' => $teams]);
    }

    public function create(): View
    {
        return view('pages.admin.team-form', [
            'team' => null,
            'units' => $this->assignableUnits(null),
            'leads' => $this->leads(),
        ]);
    }

    public function edit(Team $team): View
    {
        return view('pages.admin.team-form', [
            'team' => $team,
            'units' => $this->assignableUnits($team),
            'leads' => $this->leads(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request, null);

        /** @var User $actor */
        $actor = Auth::user();

        try {
            $this->structure->createTeam($validated, $actor);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return back()->withInput()->withErrors(['form' => $exception->getMessage()]);
        }

        return redirect()->route('admin.teams')->with('status', 'Team created.');
    }

    public function update(Request $request, Team $team): RedirectResponse
    {
        $validated = $this->validated($request, $team);

        /** @var User $actor */
        $actor = Auth::user();

        try {
            $this->structure->updateTeam($team, $validated, $actor);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return back()->withInput()->withErrors(['form' => $exception->getMessage()]);
        }

        return redirect()->route('admin.teams')->with('status', 'Team saved.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Team $team): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:190'],
            'description' => ['nullable', 'string', 'max:500'],
            /* VAL-TEAM-BU-001. Required, always. */
            'business_unit_id' => ['required', 'integer'],
            'lead_user_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string'],
        ];

        if ($team === null) {
            $rules['code'] = ['required', 'string', 'max:32'];
        }

        $validated = $request->validate($rules);

        $validated['status'] = LifecycleStatus::tryFrom((string) ($validated['status'] ?? 'active'))
            ?? LifecycleStatus::Active;

        return $validated;
    }

    /**
     * Units a team may be placed in.
     *
     * Disabled units are excluded, because VAL-BU-INACTIVE-001 refuses the
     * assignment anyway and offering it would be an option that always fails.
     * The one exception is the unit a team is ALREADY in: if it was disabled
     * after the team was placed there, hiding it would make the current value
     * unselectable and any save silently move the team somewhere else.
     *
     * @return Collection<int, BusinessUnit>
     */
    private function assignableUnits(?Team $team): Collection
    {
        return BusinessUnit::query()
            ->where(function ($query) use ($team): void {
                $query->where('status', LifecycleStatus::Active->value);

                if ($team !== null) {
                    $query->orWhere('id', $team->business_unit_id);
                }
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, User>
     */
    private function leads(): Collection
    {
        return User::query()
            ->inCurrentOrganisation()
            ->where('status', LifecycleStatus::Active->value)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }
}
