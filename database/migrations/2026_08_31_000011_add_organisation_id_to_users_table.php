<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D-16, approved 31 August 2026.
 *
 * The users table is P1-00's; THIS COLUMN AND ITS RULES ARE OWNED BY P1-01 -
 * the same pattern as platform_role, which sits here as the D-09 seam owned by
 * P1-05.
 *
 * Why it is needed: P1-01 validates that a team membership and a management
 * relationship stay inside one organisation, and users carried no SemantIQ
 * organisation key. Entra tenant_id is NOT that key and must never be
 * substituted for it - it is a directory boundary, not a tenancy boundary. In
 * single-tenant Release 1 the two coincide by accident, so a guard written
 * against tenant_id would be green today and wrong the first day a second Entra
 * tenant or a second SemantIQ organisation exists.
 *
 * Nullable, with no backfill and no seed. There is no organisation row to point
 * at: the Company Profile screen creates it, and associates the administrator
 * who creates it in the same transaction. NULL means "not yet associated" and
 * fails closed - such a user cannot join a team or a management chain.
 *
 * It grants nothing. It answers "whose structure may this row participate in",
 * never "may they see Finance".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('organisation_id')->nullable()->after('id');

            // The index is declared BEFORE the constraint on purpose. MySQL
            // creates an index for a foreign key that has none, so declaring the
            // constraint first would leave two indexes on one column - this one
            // and the implicit users_organisation_id_foreign. Declared in this
            // order, the constraint reuses it.
            $table->index('organisation_id', 'users_organisation_idx');

            $table->foreign('organisation_id', 'users_organisation_fk')
                ->references('id')
                ->on('organisations');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // The constraint goes first: MySQL refuses to drop an index a
            // foreign key still depends on. Dropping the column then takes the
            // index with it.
            $table->dropForeign('users_organisation_fk');
            $table->dropColumn('organisation_id');
        });
    }
};
