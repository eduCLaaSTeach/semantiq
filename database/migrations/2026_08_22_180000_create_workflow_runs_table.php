<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per attempt at a multi-step operation: readiness assessment, source
 * onboarding, deployment promotion, validation sweep.
 *
 * Fields follow the SRS section 17 WorkflowRun entity. The extra columns beyond
 * that list exist because a run has to survive a worker restart, which the SRS
 * requires in section 6.1 but does not spell out storage for. They are named in
 * the comments below rather than left to be reverse-engineered.
 *
 * This table replaces the project, blueprint, stage and step tables sketched in
 * doc/00 section 4.2. The SRS has no project or blueprint concept; a workflow
 * type and a current step express the same thing without a second hierarchy to
 * keep in step. Recorded as decision D2 in doc/execution/PHASE-00-PLAN.md.
 *
 * Requirement IDs: NFR-PERF-02, NFR-OBS-01, NFR-SUP-01. SRS sections 6.1, 17, 18.1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_runs', function (Blueprint $table) {
            $table->id();

            /*
             * Cascade because a workflow run is meaningless without the
             * organisation it ran for. Audit history is what must outlive a
             * deletion, and that lives in its own table with its own rule.
             */
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();

            $table->uuid('workflow_run_uid')->unique();

            /*
             * The kind of work, not the code that does it. Stored as a string
             * rather than a class name so renaming a job class does not
             * invalidate the history of every run it ever performed.
             */
            $table->string('workflow_type', 64);

            $table->string('status', 32);

            $table->string('current_step', 128)->nullable();

            /*
             * Minted once per run and carried into every log line, outbound
             * request header and audit row the run produces, so one identifier
             * reconstructs the whole story across three systems (NFR-OBS-01).
             */
            $table->uuid('correlation_id');

            /*
             * Orchestration state only: step markers, Microsoft long-running
             * operation URLs, resource IDs already created. Never customer
             * business data. A resumed run reads this to work out what it
             * already did rather than repeating it (NFR-PERF-02), and the
             * control plane stays free of payload copies (NFR-COMP-01).
             */
            $table->json('state')->nullable();

            /*
             * Attempts, not retries, so the first pass counts as one. Bounding
             * retries needs a number that cannot be confused with an offset.
             */
            $table->unsignedSmallInteger('attempts')->default(0);

            /*
             * The SRS section 18.2 category, kept separate from the message.
             * The category decides what the product does next - a rate limit is
             * retried, an unsupported feature switches to the guided path - and
             * that decision must not depend on parsing an error string.
             */
            $table->string('failure_category', 64)->nullable();
            $table->text('failure_message')->nullable();

            /*
             * The Microsoft request identifier for the call that failed, which
             * is what Microsoft support asks for first.
             */
            $table->string('api_request_id', 128)->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            /*
             * When a resumable run may next be picked up. Long-running Microsoft
             * operations are polled by re-queueing with a delay rather than by
             * sleeping in a worker or holding a browser request open.
             */
            $table->timestamp('resume_after')->nullable();

            $table->timestamps();

            /*
             * The operations dashboard asks "what is happening for this customer
             * right now", so the composite leads with the organisation. The
             * correlation index serves the opposite question, asked during
             * support: "what happened under this identifier".
             */
            $table->index(['organisation_id', 'status']);
            $table->index(['organisation_id', 'workflow_type']);
            $table->index('correlation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_runs');
    }
};
