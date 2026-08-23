<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stored overrides of the security policy catalogue. Features ADM-009 to ADM-011.
 *
 * ONE table, not the two the release plan originally listed. Authentication
 * policy and session policy are the same thing - a typed key with a value and a
 * reason - viewed through two screens, and splitting them by subject matter
 * would have doubled the machinery for fourteen values. Decision D1, approved
 * 25 August 2026, recorded in `doc/execution/R1.3-GATE-3-SECURITY-PLAN.md`.
 *
 * OVERRIDES ONLY. A key with no row reads as the default in
 * `config/security.php`, so a fresh install needs no seeder and a policy cannot
 * be left in a half-configured state by a failed one.
 *
 * WHY `reason` IS A COLUMN AND NOT A NOTE IN THE AUDIT EVENT. It is both. The
 * audit event is the evidence; this column is the CURRENT answer to "why is the
 * idle timeout twelve hours" without reading the trail backwards. A security
 * policy that nobody can explain is one nobody dares change back.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_policies', function (Blueprint $table) {
            $table->id();

            /*
             * Whose policy this is. Nullable so a platform-wide default can
             * exist alongside a customer override, matching `system_settings`.
             * The global scope in BelongsToOrganisation fails closed when no
             * context is in force.
             */
            $table->foreignId('organisation_id')->nullable()->constrained()->cascadeOnDelete();

            /* Must exist in config/security.php. The service refuses anything
             * else, so an unreviewed key cannot reach this column. */
            $table->string('key', 120);

            /*
             * One text column for every type. What gives the string meaning -
             * its type, default, validation, editing tier and risk - lives in
             * the catalogue, because that is reviewed code and this is editable
             * data.
             */
            $table->text('value')->nullable();

            /*
             * Why it was changed. Required by the service for any key the
             * catalogue marks high_risk, which is rule 4 of the gate.
             */
            $table->string('reason', 500)->nullable();

            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            /*
             * Matched on the same columns the writer writes on, so two
             * concurrent changes collide at the database rather than producing
             * two rows that disagree about the current policy.
             */
            $table->unique(['organisation_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_policies');
    }
};
