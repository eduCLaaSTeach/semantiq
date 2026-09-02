<?php

declare(strict_types=1);

namespace App\Modules\People\Http\Controllers;

use App\Modules\People\Http\Controllers\Concerns\InteractsWithPeople;
use App\Modules\People\Models\Group;
use App\Modules\People\Models\GroupMembership;
use App\Modules\People\Services\GroupService;
use App\Modules\People\Support\PeopleViolation;
use App\Modules\Platform\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Groups, and who is in them.
 *
 * Nothing on these screens answers an access question, and nothing in this
 * controller reads a group to decide what anybody may see. That is asserted by
 * PeopleBoundaryTest rather than left as an intention.
 */
final class GroupController
{
    use InteractsWithPeople;

    private const PER_PAGE = 25;

    public function __construct(private readonly GroupService $groups) {}

    public function index(Request $request): Response
    {
        $organisation = $this->organisation($request);
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', '');

        $groups = Group::query()
            ->where('organisation_id', $organisation->id)
            ->when($search !== '', fn ($query) => $query->where(
                fn ($q) => $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
            ))
            ->when(in_array($status, ['active', 'inactive'], true), fn ($query) => $query->where('status', $status))
            ->withCount(['memberships as members_count' => fn ($query) => $query->whereNull('left_at')])
            ->orderBy('name')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('People/Groups', [
            'groups' => [
                'data' => collect($groups->items())->map(fn (Group $group): array => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'code' => $group->code,
                    'description' => $group->description,
                    'status' => $group->status->value,
                    'members' => $group->members_count,
                ])->all(),
                'total' => $groups->total(),
                'perPage' => $groups->perPage(),
                'currentPage' => $groups->currentPage(),
                'lastPage' => $groups->lastPage(),
            ],
            'filters' => ['search' => $search, 'status' => $status],
        ]);
    }

    public function show(Request $request, Group $group): Response
    {
        $this->refuseIfOutsideOrganisation($request, $group->organisation_id);

        $organisation = $this->organisation($request);

        $search = trim((string) $request->query('search', ''));
        $period = (string) $request->query('period', '');

        $memberships = GroupMembership::query()
            ->where('group_id', $group->id)
            ->when($search !== '', fn ($query) => $query->whereIn(
                'user_id',
                User::query()
                    ->select('id')
                    ->where(fn ($q) => $q->where('display_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"))
            ))
            ->when($period === 'current', fn ($query) => $query->whereNull('left_at'))
            ->when($period === 'past', fn ($query) => $query->whereNotNull('left_at'))
            ->with('user:id,display_name,email,status')
            ->orderByDesc('joined_at')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('People/Group', [
            'group' => [
                'id' => $group->id,
                'name' => $group->name,
                'code' => $group->code,
                'description' => $group->description,
                'status' => $group->status->value,
                'purgeable' => $this->groups->isPurgeable($group),
            ],
            'members' => [
                'data' => collect($memberships->items())->map(fn (GroupMembership $membership): array => [
                    'id' => $membership->id,
                    'userId' => $membership->user_id,
                    'name' => $membership->user?->display_name,
                    'email' => $membership->user?->email,
                    'joinedAt' => $membership->joined_at->toDateString(),
                    'leftAt' => $membership->left_at?->toDateString(),
                    'current' => $membership->isCurrent(),
                ])->all(),
                'total' => $memberships->total(),
                'perPage' => $memberships->perPage(),
                'currentPage' => $memberships->currentPage(),
                'lastPage' => $memberships->lastPage(),
            ],
            'filters' => ['search' => $search, 'period' => $period],
            // Whether anybody has EVER been a member, independent of the filter
            // above. Without it a search that matches nobody would render
            // "Nobody has ever been in this group", which would be untrue.
            'everHadMembers' => GroupMembership::query()->where('group_id', $group->id)->exists(),
            // Candidates: active people of this organisation who are not
            // currently in the group. The same two conditions the service
            // enforces, so the picker and the guard cannot disagree.
            'candidates' => User::query()
                ->where('organisation_id', $organisation->id)
                ->where('status', 'active')
                ->whereNotIn('id', GroupMembership::query()
                    ->select('user_id')
                    ->where('group_id', $group->id)
                    ->whereNull('left_at'))
                ->orderBy('display_name')
                ->get(['id', 'display_name'])
                ->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $attributes = $this->validated($request);

        try {
            $group = $this->groups->create($this->organisation($request), $attributes, $this->actor($request));
        } catch (PeopleViolation $violation) {
            return $this->refuse($violation);
        }

        return $this->confirm('people.group', 'Group added.', $group->id);
    }

    public function update(Request $request, Group $group): RedirectResponse
    {
        $this->refuseIfOutsideOrganisation($request, $group->organisation_id);

        try {
            $this->groups->update($group, $this->validated($request), $this->actor($request));
        } catch (PeopleViolation $violation) {
            return $this->refuse($violation);
        }

        return $this->confirm('people.group', 'Group saved.', $group->id);
    }

    public function deactivate(Request $request, Group $group): RedirectResponse
    {
        $this->refuseIfOutsideOrganisation($request, $group->organisation_id);

        $this->groups->deactivate($group, $this->actor($request));

        return $this->confirm('people.group', 'Group deactivated.', $group->id);
    }

    public function reactivate(Request $request, Group $group): RedirectResponse
    {
        $this->refuseIfOutsideOrganisation($request, $group->organisation_id);

        $this->groups->reactivate($group, $this->actor($request));

        return $this->confirm('people.group', 'Group reactivated.', $group->id);
    }

    public function purge(Request $request, Group $group): RedirectResponse
    {
        $this->refuseIfOutsideOrganisation($request, $group->organisation_id);

        try {
            $this->groups->purge($group, $this->actor($request));
        } catch (PeopleViolation $violation) {
            return $this->refuse($violation);
        }

        return $this->confirm('people.groups', 'Group removed permanently.');
    }

    public function addMember(Request $request, Group $group): RedirectResponse
    {
        $this->refuseIfOutsideOrganisation($request, $group->organisation_id);

        $attributes = $request->validate(['user_id' => ['required', 'integer']]);

        $user = User::query()->find($attributes['user_id']);

        if ($user === null) {
            return $this->refuse(PeopleViolation::refuse('unknown_user', 'That person could not be found.'));
        }

        try {
            $this->groups->addMember($group, $user, $this->actor($request));
        } catch (PeopleViolation $violation) {
            return $this->refuse($violation);
        }

        return $this->confirm('people.group', 'Member added.', $group->id);
    }

    public function removeMember(Request $request, Group $group, GroupMembership $membership): RedirectResponse
    {
        $this->refuseIfOutsideOrganisation($request, $group->organisation_id);

        /*
         * BOTH ids are bound independently, so nothing so far has said they
         * belong together. Without this, /groups/1/members/99/remove would end
         * membership 99 - in some other group - and then confirm "Membership
         * ended" on group 1's page, where the administrator would see no change
         * and somebody elsewhere would silently leave a group.
         */
        if ($membership->group_id !== $group->id) {
            abort(404);
        }

        try {
            $this->groups->removeMember($membership, $this->actor($request));
        } catch (PeopleViolation $violation) {
            return $this->refuse($violation);
        }

        return $this->confirm('people.group', 'Membership ended.', $group->id);
    }

    /** @return array<string, string|null> */
    private function validated(Request $request): array
    {
        /** @var array<string, string|null> $attributes */
        $attributes = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:32'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        return $attributes;
    }
}
