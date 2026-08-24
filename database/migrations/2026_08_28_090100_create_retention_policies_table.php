<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-category retention policy. Feature PDPA-03, gate 4 batch R1.4b.
 *
 * One row per personal data category, per organisation: how long it is kept, on
 * what basis, from what event, what happens at the end, and who owns the
 * answer.
 *
 * THIS TABLE STORES POLICY AND EXECUTES NOTHING. There is no sweep, no job, no
 * deletion path anywhere in gate 4 (SEC-DEC-038). A retention policy that
 * deleted data would be the single most destructive feature in the application,
 * and it is not being built by the batch that first writes the periods down.
 * The screens say so plainly rather than letting a filled-in table read as
 * protection.
 *
 * WHY THIS REPLACES ONE SEVEN-YEAR NUMBER. The repository recorded a blanket
 * seven-year retention for everything. DEC-002 traced that to a gap: the PDPA
 * expects a stated basis per category, and one number applied to every kind of
 * data is a position nobody can defend per category. `retention_months` here is
 * per category and nullable, and null means Not Configured rather than
 * "forever" or "seven years".
 *
 * THE COMPLIANCE-OWNED COLUMNS SHIP EMPTY AND SEMANTIQ NEVER FILLS THEM.
 * `retention_months`, `basis` and `lawful_basis` are judgements about law and
 * about the customer's own obligations. A plausible default written by software
 * would be a compliance claim nobody made. They start null; the screen shows
 * Not Configured; the approval state records whether a human has signed off.
 *
 * AUDIT REDACTOR CHECK. Every column name was run through
 * `Redaction::isSensitiveKey()`. None matches. `lawful_basis` and
 * `disposal_action` are both clean; a column named `retention_key` or
 * `certification_basis` would not have been. SEC-DEC-044.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retention_policies', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();

            /*
             * One policy per category. `cascadeOnDelete` is safe here because a
             * category is RETIRED rather than deleted - there is no delete path
             * on `personal_data_categories` either.
             */
            $table->foreignId('personal_data_category_id')->constrained()->cascadeOnDelete();

            /* How long. NULL means Not Configured, and the screen says so. */
            $table->unsignedSmallInteger('retention_months')->nullable();

            /* Why that long. Compliance-owned; ships empty. */
            $table->text('basis')->nullable();

            /* Which legal ground the data is held under. Compliance-owned. */
            $table->string('lawful_basis', 190)->nullable();

            /*
             * WHEN THE CLOCK STARTS. Without this a period is unusable: "three
             * years" from what? Account closure, last activity, record
             * creation, and contract end are different dates and produce
             * different answers.
             */
            $table->string('start_event', 64)->nullable();

            /* What happens at the end: delete, anonymise, archive, review. */
            $table->string('disposal_action', 32)->nullable();

            /* Who is accountable for this category's retention. A name or a
             * role, never a credential and never a login. */
            $table->string('owner', 190)->nullable();

            /*
             * A stated carve-out: a legal hold, an ongoing dispute, a
             * regulatory obligation that overrides the period. Free text
             * because the shape of an exception is not knowable in advance;
             * its EXISTENCE is what the screen surfaces.
             */
            $table->text('exception_rule')->nullable();

            /* When somebody should look at this again. */
            $table->date('next_review_on')->nullable();

            /* draft or approved. A period nobody approved is a proposal. */
            $table->string('status', 16)->default('draft');

            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            /* One policy per category. Two would mean two answers to one
             * question. */
            $table->unique(['organisation_id', 'personal_data_category_id']);
            $table->index(['organisation_id', 'status']);
            $table->index(['organisation_id', 'next_review_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retention_policies');
    }
};
