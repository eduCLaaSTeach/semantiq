<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The organisational and security context of an account. Feature ADM-005.
 *
 * Everything here is ADDITIVE. `users.role`, `users.is_auditor`,
 * `users.entra_object_id` and the rest are untouched, so every existing account
 * and every existing test keeps working exactly as before.
 *
 * Two columns change behaviour rather than only recording it, and both are the
 * reason this migration is in gate 2 rather than later:
 *
 *   `status` - a disabled, locked or expired account CANNOT AUTHENTICATE
 *              (VAL-USER-DISABLED-001). Enforced in both sign-in paths.
 *   `access_start` / `access_end` - outside the window, sign-in is refused
 *              (VAL-USER-WINDOW-001). A contractor's access ending on a date is
 *              a promise the system should keep without anybody remembering.
 *
 * Existing rows are backfilled to `active` with the organisation gate 1
 * created, so nobody is locked out by this migration. That backfill is the one
 * place in the release where a data write happens outside a screen, and it is
 * deliberately the least surprising value.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            /*
             * Nullable rather than NOT NULL, because a sign-in attempt can
             * create an account through Entra before any organisation is
             * resolved. The scope trait treats null as "not yet placed", never
             * as "belongs to everyone".
             */
            $table->foreignId('organisation_id')->nullable()->after('id')->constrained()->nullOnDelete();

            /* Scope, never permission. Both optional per ADM-005. */
            $table->foreignId('business_unit_id')->nullable()->after('organisation_id')->constrained()->nullOnDelete();
            $table->foreignId('team_id')->nullable()->after('business_unit_id')->constrained()->nullOnDelete();

            /* internal, external or service. A UserType backing value. */
            $table->string('user_type', 16)->default('internal')->after('email');

            /* LifecycleStatus: invited, active, disabled, locked or expired. */
            $table->string('status', 24)->default('active')->after('user_type');

            /* local or entra. Recorded rather than inferred from the presence
             * of a password, so an account can be moved to federated sign-in
             * without the old password column deciding the answer. */
            $table->string('authentication_source', 16)->default('local')->after('status');

            /* The customer's own employee or reference number, ADM-005. */
            $table->string('external_reference_id', 64)->nullable()->after('authentication_source');

            /* The access window. Dates, not timestamps: access ends on a day. */
            $table->date('access_start')->nullable()->after('external_reference_id');
            $table->date('access_end')->nullable()->after('access_start');

            $table->index(['organisation_id', 'status']);
        });

        /*
         * Backfill. Every account that already exists belongs to the
         * organisation gate 1 created and is active, which is exactly what was
         * true before this migration ran. `status` and `authentication_source`
         * already default correctly for existing rows; only the organisation
         * needs stating, and federated accounts need their real source.
         */
        $organisationId = DB::table('organisations')
            ->where('code', config('platform.bootstrap_organisation_code', 'PRIMARY'))
            ->value('id');

        if ($organisationId !== null) {
            DB::table('users')->whereNull('organisation_id')->update(['organisation_id' => $organisationId]);
        }

        DB::table('users')->whereNotNull('entra_object_id')->update(['authentication_source' => 'entra']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            /*
             * The foreign keys have to go before their columns on MySQL, and
             * Laravel needs them named the way it created them.
             */
            $table->dropForeign(['organisation_id']);
            $table->dropForeign(['business_unit_id']);
            $table->dropForeign(['team_id']);

            $table->dropIndex(['organisation_id', 'status']);

            $table->dropColumn([
                'organisation_id',
                'business_unit_id',
                'team_id',
                'user_type',
                'status',
                'authentication_source',
                'external_reference_id',
                'access_start',
                'access_end',
            ]);
        });
    }
};
