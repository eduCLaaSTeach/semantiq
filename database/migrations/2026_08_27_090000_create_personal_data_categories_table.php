<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The personal data category register. Feature ADM-014, gate 4 batch R1.4a.
 *
 * What kinds of personal data this application holds about people, described in
 * the customer's own terms. PDPA-01 answers "what do you hold about me" from
 * these categories in R1.4c, which is why `source_tables` exists: a table
 * claimed by no category and named in no exclusion list fails the collector
 * coverage test, so the register cannot go stale as later gates add tables.
 *
 * ORGANISATION SCOPED AND NOT NULLABLE. Unlike `system_settings`, there is no
 * platform-wide default worth having: a category register belongs to a customer
 * or it belongs to nobody. The rows a fresh install starts with are written by
 * the service on first visit, stamped with the current organisation, rather than
 * by a seeder migration - so a rollback cannot orphan them and re-running the
 * migration cannot duplicate them.
 *
 * NO RETENTION PERIOD AND NO LAWFUL BASIS HERE. Both are compliance judgements
 * and both arrive with PDPA-03 in R1.4b, in their own table. Declaring the
 * columns now would leave two fields nothing reads, which is the "unwanted
 * parts hanging" problem.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_data_categories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();

            /*
             * The stable identifier. Unique WITHIN an organisation, not
             * globally: two customers both having an `account_identity`
             * category is normal and must not collide.
             */
            $table->string('code', 64);

            $table->string('name', 190);
            $table->text('description');

            /* Codified, never free text. DataClassification. */
            $table->string('classification', 32);

            /*
             * Whether this category includes data the PDPA and most regimes
             * treat with extra care - health, finances, biometrics. Separate
             * from classification because the two answer different questions:
             * one is how much harm disclosure causes, the other is what kind of
             * data it is.
             */
            $table->boolean('contains_sensitive')->default(false);

            /*
             * Where this category actually lives. A JSON list of table names.
             * Read by the R1.4c coverage test, and by the assembler that has to
             * know where to look.
             */
            $table->json('source_tables')->nullable();

            /*
             * Retired rather than deleted. A category somebody classified data
             * under is part of the record of how that data was treated, and
             * deleting it would remove the explanation without removing the
             * data.
             */
            $table->string('status', 16)->default('active');

            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['organisation_id', 'code']);
            $table->index(['organisation_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_data_categories');
    }
};
