<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The management chain. Both sides are users (D-15).
 *
 * A user has one current manager: one row with effective_to IS NULL. That is
 * enforced in the application and asserted by test rather than by a partial
 * unique index, which is not portable across MySQL and the SQLite the suite
 * uses - and a guard that only exists in production is a guard nobody has run.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('management_relationships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organisation_id')->constrained('organisations');

            // The report.
            $table->foreignId('user_id')->constrained('users');

            // The manager.
            $table->foreignId('manager_id')->constrained('users');

            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'effective_from'], 'mgmt_user_from_uq');
            $table->index('manager_id', 'mgmt_manager_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('management_relationships');
    }
};
