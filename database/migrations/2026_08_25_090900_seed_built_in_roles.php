<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Modules\Identity\Services\RoleRegistry;
use App\Modules\Identity\Support\PermissionRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The six built-in roles, one per tier. Feature ADM-006.
 *
 * A MIGRATION AND NOT A SEEDER, for the same reason the bootstrap organisation
 * was: production runs `migrate --force` and never runs seeders. A role model
 * that only exists on machines where somebody remembered to seed is not an
 * access model.
 *
 * `organisation_id` is null on all six, meaning shared by every organisation.
 * A customer's own roles carry their id and are invisible to any other
 * customer.
 *
 * NO PERMISSIONS ARE GRANTED HERE. A built-in role's authority comes from its
 * TIER, through `PermissionRegistry::defaultsFor()`, not from rows in
 * `role_permissions`. Writing the tier defaults out as rows would create a
 * second copy of the same fact, and the day somebody added a permission to the
 * registry the rows would silently disagree with it. The permission set is
 * derived, and this migration asserts the registry can derive it rather than
 * duplicating the answer.
 */
return new class extends Migration
{
    public function up(): void
    {
        $registry = new PermissionRegistry;
        $now = now();

        foreach (RoleRegistry::builtIn() as $code => $definition) {
            $exists = DB::table('roles')
                ->where('code', $code)
                ->whereNull('organisation_id')
                ->exists();

            if ($exists) {
                continue;
            }

            /*
             * Asserting rather than storing: if the registry cannot describe
             * what this tier may do, the access model is broken and the
             * migration should say so here rather than the application
             * discovering it at a 403 later.
             */
            $derived = $registry->defaultsFor($definition['tier']);

            if ($definition['tier']->atLeast(Role::Admin) && $derived === []) {
                throw new RuntimeException(
                    'PermissionRegistry derives no permissions for the '.$definition['name']
                    .' tier. The built-in roles would grant nothing.'
                );
            }

            DB::table('roles')->insert([
                'organisation_id' => null,
                'code' => $code,
                'name' => $definition['name'],
                'description' => $definition['description'],
                'tier' => $definition['tier']->value,
                'is_system' => true,
                'status' => 'active',
                'version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('roles')
            ->whereNull('organisation_id')
            ->where('is_system', true)
            ->whereIn('code', array_keys(RoleRegistry::builtIn()))
            ->delete();
    }
};
