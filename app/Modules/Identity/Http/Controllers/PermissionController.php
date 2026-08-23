<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Models\AccessRole;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Support\PermissionRegistry;
use Illuminate\View\View;

/**
 * The permission catalogue. Feature ADM-007, and gap M3 from DEC-001.
 *
 * READ ONLY, and that is the point rather than a limitation. The catalogue is
 * code - `PermissionRegistry` - because a permission an administrator can
 * invent is not a permission: nothing checks a key no line of code names. So
 * this screen shows what exists, what each one means, how much damage it can
 * do, and which roles hold it. Changing WHO holds one is the role editor's job.
 *
 * The "held by" column is the question this screen exists to answer: given a
 * permission, who can use it. The Users screen answers the other direction.
 */
class PermissionController extends Controller
{
    public function __construct(
        private readonly PermissionRegistry $permissions,
    ) {}

    public function __invoke(): View
    {
        return view('pages.admin.permissions', [
            'byModule' => $this->permissions->byModule(),
            'holders' => $this->holdersByPermission(),
            'tierRoles' => $this->rolesByTier(),
        ]);
    }

    /**
     * Which roles explicitly grant each permission.
     *
     * Explicit grants only. A built-in role's authority comes from its TIER
     * through the registry's defaults rather than from rows, so it correctly
     * appears here as granting nothing explicitly - and the screen says so
     * beside the tier column rather than leaving a confusing blank.
     *
     * @return array<string, list<string>>
     */
    private function holdersByPermission(): array
    {
        $rows = RolePermission::query()
            ->with('role:id,name,code,status')
            ->get();

        $map = [];

        foreach ($rows as $row) {
            $role = $row->role;

            if ($role === null) {
                continue;
            }

            /* A disabled role grants nothing, so listing it as a holder would
             * overstate who can use the permission. */
            if (! $role->isActive()) {
                continue;
            }

            $map[$row->permission_key][] = $role->name;
        }

        return $map;
    }

    /**
     * The lowest tier that reaches each permission, expressed as its built-in
     * role name, so an administrator reads "Administrator" rather than a code.
     *
     * @return array<string, string>
     */
    private function rolesByTier(): array
    {
        $names = [];

        foreach (AccessRole::query()->where('is_system', true)->get() as $role) {
            $names[$role->tier->value] = $role->name;
        }

        return $names;
    }
}
