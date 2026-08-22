<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The append-only record of every privileged configuration change.
 *
 * Fields follow the SRS section 17 AuditEvent entity. Three shape decisions here
 * are the whole point of the table and are deliberate:
 *
 *  1. Hashes, not payloads. `before_hash` and `after_hash` prove that a value
 *     changed and let a later comparison confirm which value it was, without the
 *     control plane holding a copy of customer configuration it does not need
 *     (NFR-COMP-01, and the standard's data-minimisation rule).
 *  2. The actor is denormalised. `actor_label` and `actor_entra_object_id` are
 *     written at the time of the event and never updated, so deleting a user
 *     cannot quietly rewrite who did what seven years ago.
 *  3. No `updated_at`. The column would imply an edit path, and there is none.
 *     Immutability is enforced in the model as well; this is the schema saying
 *     the same thing so a future reader is not misled.
 *
 * Retention is seven years, per the CLAUDE.md project baseline, and is applied
 * by policy from the data protection profile rather than by a constant here.
 *
 * Requirement IDs: NFR-COMP-01, NFR-SEC-01, NFR-OBS-01. SRS sections 13.2, 17.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();

            /*
             * Restricted rather than cascaded. An organisation cannot be deleted
             * while its audit history exists, which is the correct order of
             * priorities: the history is the evidence that the deletion, and
             * everything before it, was authorised.
             */
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();

            $table->uuid('audit_uid')->unique();

            /*
             * Nullable and null-on-delete: the foreign key is a convenience for
             * joining to a live user, not the record of who acted. That is the
             * denormalised pair below, which no deletion can touch.
             */
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_label', 191);
            $table->string('actor_entra_object_id', 64)->nullable();

            /*
             * A stable verb code such as `organisation.updated`, not a sentence.
             * Sentences are for rendering; codes are for filtering an audit
             * screen and for alerting.
             */
            $table->string('action', 128);

            $table->string('target_type', 64)->nullable();
            $table->string('target_id', 191)->nullable();

            /*
             * SHA-256, hex encoded, so exactly 64 characters. Fixed width states
             * the algorithm in the schema and refuses a truncated value.
             */
            $table->char('before_hash', 64)->nullable();
            $table->char('after_hash', 64)->nullable();

            $table->string('api_request_id', 128)->nullable();
            $table->uuid('correlation_id')->nullable();

            /*
             * Whether the attempt succeeded. A denied or failed privileged
             * action is more interesting than a successful one, so failures are
             * recorded here rather than only in application logs.
             */
            $table->string('result', 32);

            /*
             * Where it came from. Enough to investigate an anomaly, not enough
             * to profile a person: no full user agent, no session identifier.
             */
            $table->string('source_ip', 45)->nullable();

            /*
             * When the action happened, which is not always when the row was
             * written - a queued workflow can audit an event minutes after the
             * fact. Both are kept so the difference stays visible.
             */
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->nullable();

            $table->index(['organisation_id', 'occurred_at']);
            $table->index(['organisation_id', 'action']);
            $table->index(['target_type', 'target_id']);
            $table->index('correlation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
    }
};
