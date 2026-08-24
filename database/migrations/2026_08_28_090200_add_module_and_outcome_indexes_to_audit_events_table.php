<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two indexes for the Audit Log screen. Feature ADM-013, gate 4 batch R1.4b.
 *
 * READ THIS BEFORE CHANGING ANYTHING HERE.
 *
 * `audit_events` carries the append-only protection: two database triggers,
 * BEFORE UPDATE and BEFORE DELETE, raising SQLSTATE 45000. They are NOT in any
 * migration, deliberately, because a migration that creates a trigger can also
 * drop one (SEC-DEC-037). They were applied to production by hand and they are
 * the only thing standing between the audit trail and a mass delete, since
 * model hooks do not fire on one and MySQL has no DENY.
 *
 * SEC-DEC-039 states the constraint this migration is written to respect: if
 * the table is ever REBUILT, the triggers go with it and must be re-applied by
 * hand, and re-running a create migration would restore the table WITHOUT them
 * while every screen looked normal.
 *
 * SO THIS MIGRATION DOES EXACTLY ONE THING: it adds two indexes.
 *
 *   It does NOT rebuild the table.
 *   It does NOT modify, insert or delete a single row.
 *   It does NOT drop, recreate or reference the triggers.
 *   It does NOT change a column type, which on MySQL can rewrite a table.
 *
 * `CREATE INDEX` and `DROP INDEX` are in-place operations on InnoDB and leave
 * triggers untouched. `RetentionAndAuditTest` asserts both triggers survive
 * this migration on a database where they exist, so the claim is checked rather
 * than asserted in a comment.
 *
 * WHY ONLY TWO INDEXES, when the gate 4 plan said "audit log filter indexes".
 *
 * The table already carries four:
 *
 *     organisation_id + occurred_at
 *     action          + occurred_at
 *     actor_user_id   + occurred_at
 *     resource_type   + resource_id
 *
 * Between them those cover the tenancy scope, the time ordering, filtering by
 * action, filtering by actor, and finding everything about one resource. The
 * ONLY columns ADM-013's presets filter on that nothing covers are `module` -
 * which is what separates Administrative from Security from Configuration
 * changes - and `outcome`, which is how a reader finds denials.
 *
 * Adding the indexes the plan assumed were missing would have put dead weight
 * on the largest and fastest-growing table in the schema. Two is what is
 * needed.
 *
 * Both are composite and both lead with `organisation_id`, because every query
 * this screen makes is already scoped to one organisation by
 * `BelongsToOrganisation` - an index on `module` alone would be skipped in
 * favour of the tenancy index and earn nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_events', function (Blueprint $table) {
            /* Drives the four presets. */
            $table->index(['organisation_id', 'module', 'occurred_at'], 'audit_events_org_module_occurred_index');

            /* Finds denials and failures, which is what an incident review
             * opens this screen for. */
            $table->index(['organisation_id', 'outcome', 'occurred_at'], 'audit_events_org_outcome_occurred_index');
        });
    }

    public function down(): void
    {
        Schema::table('audit_events', function (Blueprint $table) {
            $table->dropIndex('audit_events_org_module_occurred_index');
            $table->dropIndex('audit_events_org_outcome_occurred_index');
        });

        /*
         * Dropping an index is also in-place and also leaves the triggers
         * alone. Rolling this back costs query speed on the audit screen and
         * nothing else - no row is touched and no protection is lifted.
         */
    }
};
