<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D-25, approved 1 September 2026.
 *
 * The PLAN listed "primary legal entity" among the Organisation's data points.
 * The DESIGN's organisations table did not carry it and recorded no decision to
 * drop it. That omission was found by the P1-01 scope-completeness audit and is
 * closed here — it is a genuine gap, not a field that was always designed.
 *
 * WHAT THIS IS: the organisation's corporate identity. One optional pointer from
 * the organisation to one of its own legal entities.
 *
 * WHAT THIS IS NOT, and the distinction is the whole reason it is safe: it is
 * NOT a "primary" flag on business_unit_legal_entity. D-14 is unchanged by it —
 * business units and legal entities remain many-to-many, the junction still
 * carries no attributes of any kind, and the primary legal entity is NOT the
 * parent of the business units. An organisation's primary legal entity need not
 * be associated with any business unit at all. The two answer different
 * questions: this one is "who are we, on paper"; D-14 is "which entity does this
 * business unit operate under".
 *
 * It grants nothing. Like every other column in this unit, it records structure
 * and answers no question about access.
 *
 * Nullable, additive, with NO seed and NO backfill. The existing production
 * organisation stays NULL after this runs and acquires a value only when an
 * administrator chooses one on the Company Profile screen — the same rule the
 * rest of P1-01 follows, because a value written by a migration would be
 * invented business content.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organisations', function (Blueprint $table): void {
            $table->foreignId('primary_legal_entity_id')->nullable()->after('legal_name');

            // Index before constraint, as with users.organisation_id: MySQL
            // creates an index for a foreign key that has none, so declaring the
            // constraint first would leave two indexes on one column. In this
            // order the constraint reuses this one.
            $table->index('primary_legal_entity_id', 'organisations_primary_le_idx');

            // No ON DELETE clause, so RESTRICT. That is deliberate and it is
            // what makes the D-24 purge guard pick this reference up for free:
            // the guard reads the schema, so a legal entity that is somebody's
            // primary becomes un-purgeable the moment this migration lands, with
            // no special case written anywhere.
            $table->foreign('primary_legal_entity_id', 'organisations_primary_le_fk')
                ->references('id')
                ->on('legal_entities');
        });
    }

    public function down(): void
    {
        Schema::table('organisations', function (Blueprint $table): void {
            // Constraint first: MySQL refuses to drop an index a foreign key
            // still depends on. Dropping the column then takes the index.
            $table->dropForeign('organisations_primary_le_fk');
            $table->dropColumn('primary_legal_entity_id');
        });
    }
};
