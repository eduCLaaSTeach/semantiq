<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Exactly one row of typed platform state - NOT a settings key/value store.
 *
 * A generic settings table is the thing this deliberately is not. Every column
 * here is named, typed and enumerated, so a future value cannot arrive
 * unvalidated by being spelled into a `key` column. That was a PLAN ruling, and
 * it is enforced by the shape of the table rather than by a convention.
 *
 * SINGLETON, ENFORCED BY THE DATABASE. `singleton` is a fixed value with a
 * unique constraint, so a second row cannot be inserted whatever the
 * application believes. "There is only one" in application code is a comment;
 * here it is a constraint.
 *
 * identity_source IS THE ONLY IDENTITY AUTHORITY SWITCH. Two values, `env` and
 * `store`, and there is deliberately no third meaning "try the store and fall
 * back". A silent fallback is how two credential authorities coexist, and it
 * fails in the worst way: the store is edited, the old .env value keeps
 * working, and nobody notices until the .env secret expires.
 *
 * identity_config_revision IS A CACHE CORRECTNESS MECHANISM, not a version
 * label. The identity health cache keys are namespaced by it, so a
 * configuration change makes the previous tenant's stored result UNREADABLE
 * rather than merely unwanted. A cache forget() is best-effort - it can fail
 * silently, and production runs a file cache where a missed unlink leaves the
 * entry readable - so nothing is allowed to depend on it succeeding.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table): void {
            $table->id();

            // The singleton guard. One permitted value, uniquely constrained.
            $table->string('singleton', 16)->unique();

            // `env` | `store`. Not an enum column: MySQL enum changes are DDL,
            // and a CHECK-equivalent lives in the writer's typed value object
            // where the test can reach it.
            $table->string('identity_source', 16)->default('env');

            // Monotonic. Incremented in the SAME transaction as any identity
            // configuration or secret write.
            $table->unsignedBigInteger('identity_config_revision')->default(1);

            // The cutover trail. Timestamps only - never a value, never a key
            // name, never anything that could carry a secret.
            $table->timestamp('identity_imported_at')->nullable();
            $table->timestamp('identity_verified_at')->nullable();
            $table->timestamp('identity_committed_at')->nullable();

            $table->timestamps();
        });

        // The one row. Created here rather than by a seeder, because every
        // reader below assumes it exists and a seeder is not guaranteed to run.
        DB::table('platform_settings')->insert([
            'singleton' => 'platform',
            'identity_source' => 'env',
            'identity_config_revision' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
    }
};
