<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which permission keys a role holds. Feature ADM-007.
 *
 * THERE IS NO `permissions` TABLE, and that is a deliberate difference from
 * section 29's table list. The permission CATALOGUE lives in
 * `App\Modules\Identity\Support\PermissionRegistry` - reviewed code - for the
 * same reason the settings catalogue lives in `config/platform.php`: a
 * permission an administrator can invent is not a permission, because nothing
 * in the codebase checks it. A key that exists as a row but is named nowhere in
 * the code grants exactly nothing while looking on screen as though it grants
 * something, which is the worst of both.
 *
 * So this table stores the key as a string, validated against the registry when
 * it is written AND again when it is checked. VAL-PERM-DENY-001 - an unknown
 * key denies - is then structural rather than a rule somebody has to remember:
 * a key removed from the code stops granting the moment the code is deployed,
 * without a data migration.
 *
 * The trade-off accepted: no foreign key on `permission_key`, so a stale row can
 * outlive its declaration. It is harmless, because a stale row denies, and
 * `semantiq:permissions-prune` reports them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();

            /* A registry key in `<module>.<resource>.<action>` form. */
            $table->string('permission_key', 96);

            $table->foreignId('granted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            /* Granting twice is not two grants. */
            $table->unique(['role_id', 'permission_key']);
            $table->index('permission_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
    }
};
