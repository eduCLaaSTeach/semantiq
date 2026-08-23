<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Identity\Models\AccessRole;
use App\Modules\Identity\Services\RoleRegistry;
use App\Modules\Identity\Support\Authorization;
use App\Modules\Identity\Support\PermissionRegistry;
use App\Modules\Platform\Enums\LifecycleStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;

/**
 * Roles and the permissions they carry. Features ADM-006 and ADM-007.
 *
 * A role NARROWS its tier and can never widen it. The permission editor only
 * offers permissions the ACTOR themselves holds, so an Administrator cannot
 * build a role more powerful than they are and then wear it. `RoleRegistry`
 * refuses it again on save, because a filtered checkbox list is not an
 * authorization control.
 */
class AccessRoleController extends Controller
{
    public function __construct(
        private readonly RoleRegistry $roles,
        private readonly PermissionRegistry $permissions,
        private readonly Authorization $authorization,
    ) {}

    public function index(): View
    {
        $roles = AccessRole::query()
            ->withCount(['holders', 'permissions'])
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get();

        /** @var User $actor */
        $actor = Auth::user();

        return view('pages.admin.roles', [
            'roles' => $roles,
            'permissions' => $this->permissions,
            /*
             * `admin.roles.manage` is opt-in below System Administrator, so an
             * Administrator can reach this list and not the editor. The manage
             * controls are hidden rather than rendered as links that 403 - a
             * link to a refusal is the "nothing left hanging" rule broken in
             * the direction that wastes somebody's time.
             */
            'mayManage' => $this->authorization->allows($actor, 'admin.roles.manage'),
        ]);
    }

    public function create(): View
    {
        return view('pages.admin.role-form', [
            'role' => null,
            'tiers' => $this->grantableTiers(),
        ]);
    }

    public function edit(AccessRole $role): View
    {
        return view('pages.admin.role-form', [
            'role' => $role,
            'tiers' => $this->grantableTiers(),
        ]);
    }

    /**
     * The permission editor for one role.
     */
    public function permissions(AccessRole $role): View
    {
        /** @var User $actor */
        $actor = Auth::user();

        /*
         * What this role could hold AT ALL. When it is empty the whole editor
         * would be forty disabled checkboxes with no explanation, so the view
         * shows an empty state instead - see the template's rule that every
         * screen ships one.
         */
        $withinCeiling = $this->permissions->ceilingFor($role->tier);

        return view('pages.admin.role-permissions', [
            'role' => $role,
            'byModule' => $this->permissions->byModule(),
            'withinCeiling' => $withinCeiling,
            'held' => $role->permissionKeys(),
            /*
             * What the ACTOR holds. Anything outside this is shown disabled
             * with the reason, rather than hidden: an administrator who cannot
             * grant something should be able to see that it exists and why it
             * is out of reach, not wonder whether the screen is broken.
             */
            'grantable' => $this->authorization->effectiveFor($actor),
            /* Permissions above the ROLE's own tier are inert whoever holds
             * them, so they are shown as such rather than offered. */
            'roleTier' => $role->tier,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:190'],
            'tier' => ['required', 'string'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $tier = Role::tryFrom($validated['tier']);

        if ($tier === null) {
            return back()->withInput()->withErrors(['form' => 'That is not a tier this application recognises.']);
        }

        /** @var User $actor */
        $actor = Auth::user();

        try {
            $role = $this->roles->create($validated['code'], $validated['name'], $tier, $validated['description'] ?? null, $actor);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return back()->withInput()->withErrors(['form' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.roles.permissions', $role)
            ->with('status', 'Role created. It carries no permissions until you choose some.');
    }

    public function update(Request $request, AccessRole $role): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['required', 'string'],
        ]);

        $status = LifecycleStatus::tryFrom($validated['status']) ?? LifecycleStatus::Active;

        /** @var User $actor */
        $actor = Auth::user();

        try {
            $this->roles->update($role, $validated['name'], $validated['description'] ?? null, $status, $actor);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return back()->withInput()->withErrors(['form' => $exception->getMessage()]);
        }

        return redirect()->route('admin.roles')->with('status', 'Role saved.');
    }

    /**
     * Replace the role's permission set.
     */
    public function updatePermissions(Request $request, AccessRole $role): RedirectResponse
    {
        $validated = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'max:96'],
            'reason' => ['nullable', 'string', 'max:200'],
        ]);

        /** @var User $actor */
        $actor = Auth::user();

        try {
            $this->roles->setPermissions($role, $validated['permissions'] ?? [], $actor, $validated['reason'] ?? null);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return back()->withInput()->withErrors(['form' => $exception->getMessage()]);
        }

        return redirect()->route('admin.roles')->with('status', 'Role permissions saved.');
    }

    public function destroy(AccessRole $role): RedirectResponse
    {
        /** @var User $actor */
        $actor = Auth::user();

        try {
            $this->roles->delete($role, $actor);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['form' => $exception->getMessage()]);
        }

        return redirect()->route('admin.roles')->with('status', 'Role deleted.');
    }

    /**
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
}
