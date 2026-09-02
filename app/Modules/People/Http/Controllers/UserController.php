<?php

declare(strict_types=1);

namespace App\Modules\People\Http\Controllers;

use App\Modules\Identity\Support\IdentitySafeValue;
use App\Modules\Organisation\Models\ManagementRelationship;
use App\Modules\Organisation\Models\Organisation;
use App\Modules\Organisation\Models\Team;
use App\Modules\Organisation\Models\TeamMembership;
use App\Modules\People\Http\Controllers\Concerns\InteractsWithPeople;
use App\Modules\People\Models\Group;
use App\Modules\People\Models\GroupMembership;
use App\Modules\People\Services\UserDirectoryService;
use App\Modules\People\Support\PeopleViolation;
use App\Modules\Platform\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * People: the list, one person, and their lifecycle.
 *
 * There is no route here that writes platform_role, and no request field that
 * could. Roles are P1-05's, and the record page says so rather than hiding the
 * field - an administrator who cannot find the control should be told it does
 * not exist yet, not left hunting for it.
 */
final class UserController
{
    use InteractsWithPeople;

    private const PER_PAGE = 25;

    public function __construct(private readonly UserDirectoryService $directory) {}

    public function index(Request $request): Response
    {
        $organisation = $this->organisation($request);

        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', '');
        $groupId = (string) $request->query('group', '');
        $assignment = (string) $request->query('organisation', '');

        /*
         * People with NO organisation are IN this list.
         *
         * D-16 makes users.organisation_id nullable, so "not assigned" is a real
         * state a person can be left in - and a list scoped to the current
         * organisation alone would make them invisible at exactly the moment an
         * administrator needs to find them and assign one. The Organisation
         * filter below exists for the same reason.
         */
        $users = User::query()
            ->where(fn ($query) => $query
                ->where('organisation_id', $organisation->id)
                ->orWhereNull('organisation_id'))
            ->when($search !== '', fn ($query) => $query->where(
                fn ($q) => $q->where('display_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
            ))
            ->when(in_array($status, ['active', 'inactive'], true), fn ($query) => $query->where('status', $status))
            ->when($assignment === 'assigned', fn ($query) => $query->whereNotNull('organisation_id'))
            ->when($assignment === 'unassigned', fn ($query) => $query->whereNull('organisation_id'))
            ->when($groupId !== '', fn ($query) => $query->whereIn(
                'id',
                GroupMembership::query()
                    ->select('user_id')
                    ->where('group_id', (int) $groupId)
                    ->whereNull('left_at')
            ))
            ->orderBy('display_name')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('People/Users', [
            'users' => [
                'data' => collect($users->items())->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->display_name,
                    'email' => $user->email,
                    'status' => $user->status->value,
                    // The words, not an empty cell. A blank there reads as a
                    // missing value rather than as a person who has not arrived.
                    'lastSignedIn' => $user->last_signed_in_at?->toDateString(),
                    'organisation' => $user->organisation_id === null ? null : $organisation->name,
                ])->all(),
                'total' => $users->total(),
                'perPage' => $users->perPage(),
                'currentPage' => $users->currentPage(),
                'lastPage' => $users->lastPage(),
            ],
            'filters' => [
                'search' => $search,
                'status' => $status,
                'group' => $groupId,
                'organisation' => $assignment,
            ],
            'groups' => Group::query()
                ->where('organisation_id', $organisation->id)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->all(),
        ]);
    }

    public function show(Request $request, User $user): Response
    {
        $this->refuseIfOutsideOrganisation($request, $user->organisation_id);

        $groups = GroupMembership::query()
            ->where('user_id', $user->id)
            ->with('group:id,name')
            ->orderByDesc('joined_at')
            ->get();

        return Inertia::render('People/User', [
            'person' => [
                'id' => $user->id,
                'name' => $user->display_name,
                'email' => $user->email,
                'status' => $user->status->value,
                'provider' => 'Microsoft Entra ID',
                // Masked. The full values never reach the payload - the P1-02
                // D-27 pattern, reused rather than reinvented.
                'objectIdMasked' => IdentitySafeValue::masked($user->external_subject),
                'tenantMasked' => IdentitySafeValue::masked($user->tenant_id),
                'lastSignedIn' => $user->last_signed_in_at?->toDayDateTimeString(),
                'organisationId' => $user->organisation_id,
                'organisationName' => $user->organisation?->name,
                'purgeable' => $this->directory->isPurgeable($user),
            ],
            /*
             * The organisation control needs its choices, or PLAN §5's "Assign
             * organisation - Yes" would be a route with no way to reach it: the
             * P1-01 failure in reverse, a capability delivered and undiscoverable.
             *
             * Release 1 has one organisation, so the list is one entry plus
             * "Not assigned". The guard lives in the service, not here.
             */
            'organisations' => Organisation::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Organisation $organisation): array => [
                    'id' => $organisation->id,
                    'name' => $organisation->name,
                ])->all(),
            'dependencies' => $this->directory->currentRelationshipPhrases($user),
            'teams' => TeamMembership::query()
                ->where('user_id', $user->id)
                ->get()
                ->map(fn (TeamMembership $membership): array => [
                    'id' => $membership->id,
                    'name' => Team::query()->whereKey($membership->team_id)->value('name'),
                    'current' => $membership->left_at === null,
                ])->all(),
            'manages' => ManagementRelationship::query()
                ->where('manager_id', $user->id)
                ->whereNull('effective_to')
                ->count(),
            'groups' => $groups->map(fn (GroupMembership $membership): array => [
                'id' => $membership->id,
                'groupId' => $membership->group_id,
                'name' => $membership->group?->name,
                'joinedAt' => $membership->joined_at->toDateString(),
                'leftAt' => $membership->left_at?->toDateString(),
                'current' => $membership->isCurrent(),
            ])->all(),
        ]);
    }

    /**
     * D-33 = A. The administrator supplies the Object ID.
     *
     * PROVIDER AND TENANT ARE NOT VALIDATED HERE BECAUSE THEY ARE NOT ACCEPTED.
     * They never leave configuration, so a crafted request carrying either is
     * ignored rather than sanitised - the difference matters, because a
     * sanitiser is one refactor away from being bypassed.
     */
    public function store(Request $request): RedirectResponse
    {
        $attributes = $request->validate([
            // A GUID. This is all SemantIQ can check: with no Graph permission
            // it cannot confirm the identifier names a real person, and the
            // screen says so rather than implying otherwise.
            'object_id' => ['required', 'string', 'regex:/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $user = $this->directory->provision(
                $this->organisation($request),
                $attributes,
                $this->actor($request)
            );
        } catch (PeopleViolation $violation) {
            return $this->refuse($violation);
        }

        return $this->confirm('people.user', 'User added.', $user->id);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->refuseIfOutsideOrganisation($request, $user->organisation_id);

        $attributes = $request->validate([
            'organisation_id' => ['nullable', 'integer'],
        ]);

        $organisationId = $attributes['organisation_id'] ?? null;

        try {
            $this->directory->assignOrganisation(
                $user,
                $organisationId === null || $organisationId === '' ? null : (int) $organisationId,
                $this->actor($request)
            );
        } catch (PeopleViolation $violation) {
            return $this->refuse($violation);
        }

        return $this->confirm('people.user', 'Organisation updated.', $user->id);
    }

    public function deactivate(Request $request, User $user): RedirectResponse
    {
        $this->refuseIfOutsideOrganisation($request, $user->organisation_id);

        try {
            $this->directory->deactivate($user, $this->actor($request));
        } catch (PeopleViolation $violation) {
            return $this->refuse($violation);
        }

        return $this->confirm('people.user', 'User deactivated.', $user->id);
    }

    public function reactivate(Request $request, User $user): RedirectResponse
    {
        $this->refuseIfOutsideOrganisation($request, $user->organisation_id);

        $this->directory->reactivate($user, $this->actor($request));

        return $this->confirm('people.user', 'User reactivated.', $user->id);
    }

    public function purge(Request $request, User $user): RedirectResponse
    {
        $this->refuseIfOutsideOrganisation($request, $user->organisation_id);

        try {
            $this->directory->purge($user, $this->actor($request));
        } catch (PeopleViolation $violation) {
            return $this->refuse($violation);
        }

        return $this->confirm('people.users', 'User removed permanently.');
    }

    /**
     * Reveal ONE identifier - D-37, the P1-02 pattern reused.
     *
     * POST rather than GET, and therefore CSRF-protected, for the same reason
     * auth.logout is POST: a GET that returns a value is triggerable by any
     * third-party page the administrator happens to be visiting.
     */
    public function reveal(Request $request, User $user): JsonResponse
    {
        $this->refuseIfOutsideOrganisation($request, $user->organisation_id);

        $value = match ($request->input('field')) {
            'object_id' => $user->external_subject,
            'tenant' => $user->tenant_id,
            default => null,
        };

        if ($value === null) {
            return response()->json(['message' => 'That cannot be revealed.'], 422);
        }

        return response()->json(['value' => $value]);
    }
}
