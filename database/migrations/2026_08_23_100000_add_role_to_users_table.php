<?php

declare(strict_types=1);

use App\Enums\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The authority tier an account holds.
 *
 * Stored as the template's tier code, not the display label, so renaming a role
 * in the interface never becomes a data migration.
 *
 * Defaulted to the lowest tier rather than left nullable. A null role would have
 * to be interpreted somewhere, and every interpretation is a guess: the safe one
 * is "the least access there is", which is exactly what Viewer means, so the
 * column says so instead of leaving it to be inferred.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 32)
                ->default(Role::default()->value)
                ->after('entra_tenant_id')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn('role');
        });
    }
};
