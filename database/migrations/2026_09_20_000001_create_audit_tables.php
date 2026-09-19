<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P1-08 Audit. TWO tables, both new. Nothing existing is altered.
 *
 * NO FOREIGN KEY ON actor_user_id OR subject_user_id, DELIBERATELY. P1-03
 * permits a user purge, and an audit row must SURVIVE the purge of the person
 * it names. A foreign key would either block the purge - making audit a veto
 * over a delivered capability - or, with a cascade, take the evidence with the
 * person. The id is a HISTORIC REFERENCE and the screen renders "Account
 * removed" where it no longer resolves.
 *
 * organisation_id IS NULL MEANS PLATFORM-SCOPED, not unknown. D-99 reads it as
 * System Administrator only.
 *
 * NO ip_address, NO user_agent, NO FREE TEXT. D-101 and the ALLOWED_KEYS
 * discipline: a leak stays unrepresentable rather than discouraged.
 *
 * DOWN() EXISTS FOR CI AND DEVELOPMENT. Once production holds evidence, a
 * rollback destroys it - deployment never runs it, and an operator rollback
 * needs explicit approval and a database snapshot first. See the deployment
 * documentation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table): void {
            $table->id();

            // The chain position. UNIQUE, so a duplicate cannot be inserted to
            // disguise a removal.
            $table->unsignedBigInteger('sequence')->unique('audit_sequence_unique');

            /*
             * THE SERVER'S CLOCK, AT WRITE, AS AN EXACT STRING.
             *
             * A `datetime` column would be truncated to whole seconds on the
             * way in - Laravel formats dates with the connection's own
             * 'Y-m-d H:i:s' - and a value that changes as it is stored cannot
             * be hashed: the chain would read as broken on every untouched row.
             * Found by the chain failing to verify a chain nobody had touched.
             *
             * 'Y-m-d H:i:s.u' sorts lexicographically in the right order, so
             * ordering and range filters behave exactly as a datetime would.
             */
            $table->char('occurred_at', 26);

            $table->string('event', 64);
            $table->string('category', 24);

            $table->string('actor_type', 16);
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('actor_subject', 255)->nullable();
            $table->string('actor_tenant', 64)->nullable();
            $table->string('actor_provider', 32)->nullable();

            $table->unsignedBigInteger('organisation_id')->nullable();
            $table->unsignedBigInteger('subject_user_id')->nullable();
            $table->string('subject_external', 255)->nullable();

            $table->string('target_type', 48)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();

            $table->string('outcome', 24)->nullable();
            $table->string('reason', 64)->nullable();

            $table->string('role', 40)->nullable();
            $table->unsignedBigInteger('domain_id')->nullable();
            $table->string('scope', 32)->nullable();
            $table->string('sensitivity', 24)->nullable();

            // The one permitted key the first draft of the design forgot.
            // Stored exactly, for the same reason as occurred_at.
            $table->char('context_expires_at', 26)->nullable();

            $table->char('previous_hash', 64);
            $table->char('row_hash', 64);

            $table->timestamps();

            // Every index is prefixed by what a listing filters on FIRST, so no
            // tab scans.
            $table->index(['organisation_id', 'category', 'occurred_at'], 'audit_org_cat_at_idx');
            $table->index(['category', 'occurred_at'], 'audit_cat_at_idx');
            $table->index(['event', 'occurred_at'], 'audit_event_at_idx');
            $table->index(['actor_user_id', 'occurred_at'], 'audit_actor_at_idx');
            $table->index(['subject_user_id', 'occurred_at'], 'audit_subject_at_idx');
            $table->index(['outcome', 'occurred_at'], 'audit_outcome_at_idx');

            $table->foreign('organisation_id', 'audit_org_fk')
                ->references('id')->on('organisations')->restrictOnDelete();
        });

        Schema::create('audit_chain_head', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('sequence');
            $table->char('row_hash', 64);

            /*
             * THE EVIDENCE START INSTANT, written once and never updated.
             *
             * A STORED FACT rather than MIN(occurred_at), which would silently
             * move if the earliest row were ever removed - the screen would
             * then report a later start date and look entirely consistent while
             * concealing exactly what the chain exists to reveal.
             */
            $table->dateTime('started_at', 6);
            $table->timestamps();
        });

        $now = now();

        DB::table('audit_chain_head')->insert([
            'id' => 1,
            'sequence' => 0,
            // Genesis. The first real row therefore has a predecessor, so a
            // chain of length one is still verifiable.
            'row_hash' => hash('sha256', 'semantiq.audit.genesis|'.$now->toIso8601String()),
            'started_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('audit_chain_head');
    }
};
