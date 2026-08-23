<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Enums\BusinessDomain;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Identity\Enums\UserType;
use App\Modules\Identity\Models\AccessRole;
use App\Modules\Identity\Models\BusinessUnit;
use App\Modules\Identity\Models\Team;
use App\Modules\Identity\Policies\SystemAdministratorGuard;
use App\Modules\Identity\Services\UserRegistry;
use App\Modules\Identity\Support\Authorization;
use App\Modules\Platform\Enums\LifecycleStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;

/**
 * The user registry. Feature ADM-005.
 *
 * The most consequential screen in the release: it is where the two dimensions
 * of the access model are granted, and where they must stay visibly separate.
 *
 *   TIER and ROLES  -> what a person may DO to the platform
 *   ENTITLEMENTS    -> which business information they may do it to
 *
 * They are separate forms, separate actions and separate audit events on the
 * account screen, deliberately. A single "access" form that set both would
 * make "made them an administrator" and "gave them Finance" one decision, and
 * `ROLE_MODEL.md` section 1 is that they are never one decision.
 *
 * Every rule lives in `UserRegistry`. This controller validates shape and turns
 * a refusal into a message; it enforces nothing itself, so a console command or
 * an access review gets exactly the same refusals.
 */
class UserController extends Controller
{
    public function __construct(
        private readonly UserRegistry $registry,
        private readonly Authorization $authorization,
        private readonly SystemAdministratorGuard $guard,
    ) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');

        $users = $this->registry->query()
            ->with(['businessUnit:id,name', 'team:id,name'])
            ->when($search !== '', function ($query) use ($search): void {
                /* Bound parameters through the builder. The wildcards are added
                 * here rather than taken from the request, so a submitted `%`
                 * is matched literally instead of widening the search. */
                $term = '%'.$search.'%';
                $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('email', 'like', $term));
            })
            ->when(
                LifecycleStatus::isWithin($status, LifecycleStatus::forUser()),
                fn ($query) => $query->where('status', $status),
            )
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('pages.admin.users', [
            'users' => $users,
            'search' => $search,
            'status' => $status,
            'statuses' => LifecycleStatus::forUser(),
            /* Shown so an administrator sees the number BEFORE it becomes a
             * refusal, rather than discovering the invariant by hitting it. */
            'administratorCount' => $this->guard->activeCount(),
        ]);
    }

    public function create(): View
    {
        return view('pages.admin.user-form', [
            'user' => null,
            'units' => $this->activeUnits(),
            'teams' => $this->activeTeams(),
            'tiers' => $this->grantableTiers(),
            'types' => UserType::cases(),
        ]);
    }

    public function edit(User $user): View
    {
        $this->authorizeSubject($user);

        return view('pages.admin.user-form', [
            'user' => $user,
            'units' => $this->activeUnits(),
            'teams' => $this->activeTeams(),
            'tiers' => $this->grantableTiers(),
            'types' => UserType::cases(),
        ]);
    }

    /**
     * One account, with everything about its access on one page.
     */
    public function show(User $user): View
    {
        $this->authorizeSubject($user);

        return view('pages.admin.user-access', [
            'user' => $user->load(['accessRoles', 'businessUnit:id,name', 'team:id,name']),
            'tiers' => $this->grantableTiers(),
            'statuses' => LifecycleStatus::forUser(),
            'assignableRoles' => $this->assignableRoles($user),
            'domains' => BusinessDomain::cases(),
            'entitled' => array_map(fn (BusinessDomain $d): string => $d->value, $user->entitledDomains()),
            /* ADM-007: effective permissions must be determinable for a user.
             * This is that answer, and it is the union already filtered by the
             * tier ceiling, so it is what the person can actually do. */
            'effective' => $this->authorization->effectiveFor($user),
            /* Whether the last-administrator invariant would refuse a change.
             * The screen asks so the control is absent rather than fatal; the
             * service asks again, because a hidden button is not a control. */
            'mayChangeAuthority' => $this->guard->permits($user),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request, null);

        /** @var User $actor */
        $actor = Auth::user();

        try {
            $user = $this->registry->create($validated, $actor);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return back()->withInput()->withErrors(['form' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.users.show', $user)
            ->with('status', 'Account created. It is invited until they sign in for the first time.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorizeSubject($user);

        $validated = $this->validated($request, $user);

        /** @var User $actor */
        $actor = Auth::user();

        try {
            $this->registry->update($user, $validated, $actor);
        } catch (RuntimeException $exception) {
            return back()->withInput()->withErrors(['form' => $exception->getMessage()]);
        }

        return redirect()->route('admin.users.show', $user)->with('status', 'Account saved.');
    }

    /**
     * Change the primary tier. Its own action, its own audit event.
     */
    public function changeTier(Request $request, User $user): RedirectResponse
    {
        $this->authorizeSubject($user);

        $validated = $request->validate([
            'role' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'max:200'],
        ]);

        $tier = Role::tryFrom($validated['role']);

        if ($tier === null) {
            return back()->withErrors(['authority' => 'That is not a role this application recognises.']);
        }

        /** @var User $actor */
        $actor = Auth::user();

        try {
            $this->registry->changeTier($user, $tier, $actor, $validated['reason'] ?? null);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return back()->withErrors(['authority' => $exception->getMessage()]);
        }

        return redirect()->route('admin.users.show', $user)->with('status', 'Role changed.');
    }

    /**
     * Disable, lock, unlock or re-activate.
     */
    public function changeStatus(Request $request, User $user): RedirectResponse
    {
        $this->authorizeSubject($user);

        $validated = $request->validate([
            'status' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'max:200'],
        ]);

        $status = LifecycleStatus::tryFrom($validated['status']);

        if ($status === null || ! LifecycleStatus::isWithin($status->value, LifecycleStatus::forUser())) {
            return back()->withErrors(['authority' => 'That is not a state an account can hold.']);
        }

        /** @var User $actor */
        $actor = Auth::user();

        try {
            $this->registry->changeStatus($user, $status, $actor, $validated['reason'] ?? null);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return back()->withErrors(['authority' => $exception->getMessage()]);
        }

        return redirect()->route('admin.users.show', $user)->with('status', 'Account status changed.');
    }

    /**
     * Add or remove an additional role.
     */
    public function changeRole(Request $request, User $user): RedirectResponse
    {
        $this->authorizeSubject($user);

        $validated = $request->validate([
            'role_id' => ['required', 'integer'],
            'operation' => ['required', 'string', 'in:assign,remove'],
            'reason' => ['nullable', 'string', 'max:200'],
        ]);

        $role = AccessRole::query()->find($validated['role_id']);

        if ($role === null) {
            return back()->withErrors(['roles' => 'That role does not exist.']);
        }

        /** @var User $actor */
        $actor = Auth::user();

        try {
            $validated['operation'] === 'assign'
                ? $this->registry->assignRole($user, $role, $actor, $validated['reason'] ?? null)
                : $this->registry->removeRole($user, $role, $actor, $validated['reason'] ?? null);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['roles' => $exception->getMessage()]);
        }

        return redirect()->route('admin.users.show', $user)->with('status', 'Roles updated.');
    }

    /**
     * Grant or revoke a business domain.
     *
     * THE SECOND DIMENSION. Separate route, separate form, separate audit
     * event from everything above, because granting somebody Finance is not
     * the same kind of decision as making them an administrator and the trail
     * must never blur the two.
     */
    public function changeEntitlement(Request $request, User $user): RedirectResponse
    {
        $this->authorizeSubject($user);

        $validated = $request->validate([
            'domain' => ['required', 'string'],
            'operation' => ['required', 'string', 'in:grant,revoke'],
            'reason' => ['nullable', 'string', 'max:200'],
        ]);

        $domain = BusinessDomain::tryFrom($validated['domain']);

        if ($domain === null) {
            return back()->withErrors(['entitlements' => 'That is not a business domain this application recognises.']);
        }

        /** @var User $actor */
        $actor = Auth::user();

        try {
            $validated['operation'] === 'grant'
                ? $this->registry->grantEntitlement($user, $domain, $actor, $validated['reason'] ?? null)
                : $this->registry->revokeEntitlement($user, $domain, $actor, $validated['reason'] ?? null);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['entitlements' => $exception->getMessage()]);
        }

        return redirect()->route('admin.users.show', $user)->with('status', 'Business domain access updated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?User $user): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:190'],
            'user_type' => ['required', 'string'],
            'business_unit_id' => ['nullable', 'integer'],
            'team_id' => ['nullable', 'integer'],
            'external_reference_id' => ['nullable', 'string', 'max:64'],
            'access_start' => ['nullable', 'date'],
            'access_end' => ['nullable', 'date', 'after_or_equal:access_start'],
        ];

        if ($user === null) {
            /* VAL-USER-EMAIL-001. The unique index is the real enforcement;
             * the service turns a collision into a message on this field. */
            $rules['email'] = ['required', 'string', 'email:rfc', 'max:190'];
            $rules['role'] = ['required', 'string'];
            $rules['authentication_source'] = ['required', 'string', 'in:local,entra'];
        }

        $validated = $request->validate($rules, [
            'access_end.after_or_equal' => 'Access cannot end before it starts.',
        ]);

        $validated['user_type'] = UserType::tryFrom($validated['user_type']) ?? UserType::Internal;

        if ($user === null) {
            $validated['role'] = Role::tryFrom($validated['role']) ?? Role::default();
        }

        return $validated;
    }

    /**
     * Refuse a subject outside the actor's authority, or outside their
     * organisation.
     *
     * Called by EVERY method that reads or writes an account, including the
     * five mutation routes. Route-model binding resolves by primary key and
     * knows nothing about either boundary, and the ids are sequential integers.
     *
     * THIS IS THE EARLY CHECK, NOT THE AUTHORITATIVE ONE. `UserRegistry`
     * refuses a cross-organisation subject again on every write, because a
     * console command, a queued job, a future API endpoint and the
     * access-review applier never pass through this method at all. Having it
     * here as well buys a clean 404 with no exception in the log, and costs one
     * line. Removing it would be safe; removing the service check would not.
     *
     * 404 for the tenancy boundary and 403 for the authority one, deliberately.
     * A 403 confirms the id exists and belongs to somebody; from this
     * organisation's point of view another customer's account genuinely is not
     * found, and saying so tells an id-probing attacker nothing.
     */
    private function authorizeSubject(User $subject): void
    {
        /** @var User $actor */
        $actor = Auth::user();

        /* Asked of the service, so "in this organisation" has one definition
         * that the write path and the screen cannot drift apart on. */
        abort_unless($this->registry->isInOrganisation($subject), 404);
        abort_unless($this->authorization->mayActOn($actor, $subject), 403, 'That account holds more authority than you do.');
    }

    /**
     * The tiers this actor may grant.
     *
     * VAL-USER-ELEVATE-001 made visible: an Administrator is not offered
     * System Administrator at all. The service refuses it regardless.
     *
     * @return list<Role>
     */
    private function grantableTiers(): array
    {
        /** @var User $actor */
        $actor = Auth::user();

        return array_values(array_filter(
            Role::cases(),
            fn (Role $tier): bool => $actor->role->atLeast($tier),
        ));
    }

    /**
     * Roles this actor may assign to this account.
     *
     * @return Collection<int, AccessRole>
     */
    private function assignableRoles(User $subject): Collection
    {
        /** @var User $actor */
        $actor = Auth::user();

        return AccessRole::query()
            ->where('status', LifecycleStatus::Active->value)
            ->orderBy('name')
            ->get()
            ->filter(fn (AccessRole $role): bool => $actor->role->atLeast($role->tier))
            ->values();
    }

    /**
     * @return Collection<int, BusinessUnit>
     */
    private function activeUnits(): Collection
    {
        return BusinessUnit::query()
            ->where('status', LifecycleStatus::Active->value)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * @return Collection<int, Team>
     */
    private function activeTeams(): Collection
    {
        return Team::query()
            ->where('status', LifecycleStatus::Active->value)
            ->orderBy('name')
            ->get(['id', 'name', 'business_unit_id']);
    }
}
