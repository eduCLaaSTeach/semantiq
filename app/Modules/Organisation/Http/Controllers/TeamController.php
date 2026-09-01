<?php

declare(strict_types=1);

namespace App\Modules\Organisation\Http\Controllers;

use App\Modules\Organisation\Http\Controllers\Concerns\InteractsWithStructure;
use App\Modules\Organisation\Models\Department;
use App\Modules\Organisation\Models\Team;
use App\Modules\Organisation\Models\TeamMembership;
use App\Modules\Organisation\Services\MembershipService;
use App\Modules\Organisation\Services\StructureService;
use App\Modules\Organisation\Support\StructureViolation;
use App\Modules\Platform\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class TeamController
{
    use InteractsWithStructure;

    public function __construct(
        private readonly StructureService $structure,
        private readonly MembershipService $memberships,
    ) {}

    public function index(Request $request): Response
    {
        $organisation = $this->organisation($request);

        return Inertia::render('Organisation/Teams', [
            'teams' => Team::query()
                ->where('organisation_id', $organisation->id)
                ->with('department:id,name')
                ->withCount(['memberships' => fn ($query) => $query->whereNull('left_at')])
                ->orderBy('name')
                ->get()
                ->map(fn (Team $team): array => [
                    'id' => $team->id,
                    'name' => $team->name,
                    'code' => $team->code,
                    'status' => $team->status->value,
                    'department' => $team->department?->name,
                    'departmentId' => $team->department_id,
                    'members' => $team->memberships_count,
                ])
                ->all(),
            'departments' => Department::query()
                ->where('organisation_id', $organisation->id)
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->all(),
        ]);
    }

    /**
     * Membership, current and past.
     *
     * Past members are shown rather than hidden: a removal sets left_at and
     * retains the row precisely so "who was in this team in March" stays
     * answerable, and a screen that never displays that makes the retention
     * pointless.
     */
    public function show(Request $request, Team $team): Response
    {
        $organisation = $this->organisation($request);

        $members = TeamMembership::query()
            ->where('team_id', $team->id)
            ->with('user:id,display_name,email')
            ->orderByRaw('left_at is null desc')
            ->orderBy('joined_at')
            ->get()
            ->map(fn (TeamMembership $membership): array => [
                'id' => $membership->id,
                'name' => $membership->user?->display_name,
                'email' => $membership->user?->email,
                'joined_at' => $membership->joined_at?->toDateString(),
                'left_at' => $membership->left_at?->toDateString(),
                'current' => $membership->left_at === null,
            ])
            ->all();

        $currentIds = TeamMembership::query()
            ->where('team_id', $team->id)
            ->whereNull('left_at')
            ->pluck('user_id');

        return Inertia::render('Organisation/Team', [
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'status' => $team->status->value,
                'department' => $team->department?->name,
            ],
            'members' => $members,
            // D-16: only users associated with THIS organisation are offered.
            // Entra tenant_id is not consulted here or anywhere in this unit.
            'candidates' => User::query()
                ->where('organisation_id', $organisation->id)
                ->where('status', 'active')
                ->whereNotIn('id', $currentIds)
                ->orderBy('display_name')
                ->get(['id', 'display_name', 'email'])
                ->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'department_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:32'],
        ]);

        $parent = Department::query()->find($validated['department_id']);

        if ($parent === null || $parent->organisation_id !== $this->organisation($request)->id) {
            return $this->refuse(StructureViolation::because(
                'organisation_mismatch',
                'That department is not part of this organisation.'
            ));
        }

        try {
            $this->structure->createTeam(
                $parent,
                ['name' => $validated['name'], 'code' => $validated['code'] ?? null],
                $this->actor($request)
            );
        } catch (StructureViolation $violation) {
            return $this->refuse($violation);
        }

        return redirect()->route('organisation.teams');
    }

    /**
     * Name and code. NOT the department - that is move(), for the same reason
     * a department's business unit is not editable here.
     */
    public function update(Request $request, Team $team): RedirectResponse
    {
        /** @var array<string, string|null> $attributes */
        $attributes = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:32'],
        ]);

        try {
            $this->structure->updateTeam($team, $attributes, $this->actor($request));
        } catch (StructureViolation $violation) {
            return $this->refuse($violation);
        }

        return redirect()->route('organisation.teams');
    }

    public function move(Request $request, Team $team): RedirectResponse
    {
        $validated = $request->validate(['department_id' => ['required', 'integer']]);

        $target = Department::query()->find($validated['department_id']);

        if ($target === null) {
            return $this->refuse(StructureViolation::because('not_found', 'That department does not exist.'));
        }

        try {
            $this->structure->moveTeam($team, $target, $this->actor($request));
        } catch (StructureViolation $violation) {
            return $this->refuse($violation);
        }

        return redirect()->route('organisation.teams');
    }

    public function deactivate(Request $request, Team $team): RedirectResponse
    {
        try {
            $this->structure->deactivateTeam($team, $this->actor($request));
        } catch (StructureViolation $violation) {
            return $this->refuse($violation);
        }

        return redirect()->route('organisation.teams');
    }

    public function reactivate(Request $request, Team $team): RedirectResponse
    {
        try {
            $this->structure->reactivate($team, $this->actor($request));
        } catch (StructureViolation $violation) {
            return $this->refuse($violation);
        }

        return redirect()->route('organisation.teams');
    }

    public function addMember(Request $request, Team $team): RedirectResponse
    {
        $validated = $request->validate(['user_id' => ['required', 'integer']]);

        $member = User::query()->find($validated['user_id']);

        if ($member === null) {
            return $this->refuse(StructureViolation::because('not_found', 'That user does not exist.'));
        }

        try {
            $this->memberships->add($team, $member, $this->actor($request));
        } catch (StructureViolation $violation) {
            return $this->refuse($violation);
        }

        return redirect()->route('organisation.team', $team);
    }

    public function removeMember(Request $request, Team $team, TeamMembership $membership): RedirectResponse
    {
        try {
            $this->memberships->remove($membership, $this->actor($request));
        } catch (StructureViolation $violation) {
            return $this->refuse($violation);
        }

        return redirect()->route('organisation.team', $team);
    }
}
