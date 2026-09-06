<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D-49, MIGRATION 5 - THE COLUMN GOES.
 *
 * This runs only after migration 4 has moved the data. Leaving the column
 * readable "just in case" is the second authorization model the whole unit
 * exists to prevent: two places that can answer "is this person a System
 * Administrator", drifting the first time somebody updates one of them.
 *
 * There is deliberately NO compatibility column, no view and no accessor left
 * behind. N-M7 breaks it by leaving the column readable.
 *
 * down() restores the column as nullable and indexed exactly as P1-00 created
 * it, and leaves it EMPTY - migration 4's down() is what fills it, from the
 * current assignment state. Splitting it that way keeps each migration
 * reversible on its own terms.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_platform_role_idx');
            $table->dropColumn('platform_role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('platform_role', 32)->nullable();
            $table->index('platform_role', 'users_platform_role_idx');
        });
    }
};
