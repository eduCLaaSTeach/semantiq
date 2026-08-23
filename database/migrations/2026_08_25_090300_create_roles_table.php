<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reusable authority profiles. Feature ADM-006.
 *
 * This table sits ON TOP OF the six cumulative tiers in `App\Enums\Role`, which
 * remain live and remain the coarse gate. Plan decision D2: the tier says what
 * a person may broadly do, a role's permission set says precisely what, and
 * BOTH must agree or the request is denied. A role can therefore only ever
 * narrow what its tier already allows - it can never widen it, and
 * `Authorization::allows()` enforces that ceiling rather than trusting the
 * grants recorded here.
 *
 * `tier` is that ceiling, stored as the enum's backing value.
 *
 * BUILT-IN ROLES are seeded one per tier and marked `is_system`.
 * VAL-ROLE-SYSTEM-001 makes their codes unrenamable and undeletable, because a
 * built-in code appears in migrations, tests and documentation; letting an
 * administrator rename `system_admin` would break references nobody can see
 * from the screen where they renamed it.
 *
 * `organisation_id` is nullable, and null means a built-in role shared by every
 * organisation. A customer's own role carries their id and is invisible to any
 * other customer through the usual global scope.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('code', 64);
            $table->string('name');
            $table->string('description', 500)->nullable();

            /*
             * The tier this role may never exceed. An `App\Enums\Role` backing
             * value. Held as a string rather than a native enum so the tier set
             * can change without an ALTER on a live MySQL table.
             */
            $table->string('tier', 32);

            /* Built in, and therefore protected by VAL-ROLE-SYSTEM-001. */
            $table->boolean('is_system')->default(false);

            $table->string('status', 24)->default('active');

            /*
             * ADM-006 asks that role and permission definitions support
             * versioning. The counter is bumped whenever the permission set
             * changes, so an access review can record WHICH version of a role
             * it approved rather than just its name.
             */
            $table->unsignedInteger('version')->default(1);

            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organisation_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
