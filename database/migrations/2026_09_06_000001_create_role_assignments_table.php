<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Role assignment periods - the SOLE authority for what role somebody holds
 * once D-49's data migration has run.
 *
 * `ended_at IS NULL` means current. No such row means the person holds no role.
 * Absence, not a NULL column - the same shape P1-04 chose for domain ownership,
 * and for the same reason: a column that can disagree with a history table
 * eventually does.
 *
 * organisation_id IS NULLABLE, and that is load-bearing rather than lax.
 * system_administrator is PLATFORM-scoped: bootstrap must create one before a
 * Company Profile exists, so requiring an organisation here would make a fresh
 * deployment unbootstrappable. Every OTHER role requires one, and the service
 * refuses a NULL for them - a database CHECK cannot express "nullable for
 * exactly one enum value" portably across MySQL 8.4 and SQLite, so the guard
 * lives in the service and N-M1/N-M2 break it in both directions.
 *
 * DATETIME, not DATE. P1-01 keyed team membership on date-valued timing, could
 * not represent two periods in one day, and production produced exactly that
 * case on its first day of use. Two genuine assignment periods on one calendar
 * day are distinguishable here.
 *
 * There is NO uniqueness involving assigned_at. The invariant worth enforcing
 * is "at most one CURRENT assignment of this role to this person", which MySQL
 * 8.4 cannot declare without a partial index; it is enforced by locking reads
 * inside the write transaction.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_assignments', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('user_id');

            // NULL is legitimate for system_administrator only. See above.
            $table->unsignedBigInteger('organisation_id')->nullable();

            $table->string('role_code', 40);

            $table->dateTime('assigned_at');
            $table->dateTime('ended_at')->nullable();

            // Who granted it. Nullable because bootstrap has no actor - the
            // first administrator is created by the deployment, not by a person.
            $table->unsignedBigInteger('assigned_by_user_id')->nullable();
            $table->unsignedBigInteger('ended_by_user_id')->nullable();

            $table->timestamps();

            // The current-assignment lookup, which the engine runs on every
            // decision and the administrator-set guard locks.
            $table->index(['user_id', 'ended_at'], 'role_assignments_user_ended_idx');
            $table->index(['role_code', 'ended_at'], 'role_assignments_role_ended_idx');
            $table->index('organisation_id', 'role_assignments_organisation_idx');

            $table->foreign('user_id', 'role_assignments_user_fk')
                ->references('id')
                ->on('users');

            $table->foreign('organisation_id', 'role_assignments_organisation_fk')
                ->references('id')
                ->on('organisations');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_assignments');
    }
};
