<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sovereignty exceptions. Feature ADM-016, gate 4 batch R1.4b.
 *
 * A recorded, approved, time-bounded departure from the approved sovereignty
 * profile. Somewhere data is permitted to sit or be processed that the profile
 * would otherwise forbid, for a stated reason, until a stated date.
 *
 * THE EXCEPTION NEVER CHANGES THE PROFILE. That is the whole design. An
 * exception that edited `data_sovereignty_profiles` would make the approved
 * position a lie and would be indistinguishable, a year later, from somebody
 * having approved a weaker position. The profile stays as approved; the
 * exception sits beside it, visible, dated, and attributable.
 *
 * SEPARATION OF DUTIES, at the database as well as in the service. The
 * requester and the approver are two columns, and the service refuses an
 * approval where they would be the same person. A tier split alone cannot
 * express that: a System Administrator may both request and approve in general,
 * just never the same request.
 *
 * STATUS IS STORED, EXPIRY IS DERIVED. `status` records what a person decided.
 * Whether an approved exception is still in force is a question about today's
 * date and is computed on read, never written by a job - the same reasoning
 * that keeps gate 4 free of a queue dependency (SEC-DEC-069). An exception that
 * lapsed at midnight stops applying at midnight, with nothing needing to run.
 *
 * AUDIT REDACTOR CHECK. Every column name here was run through
 * `Redaction::isSensitiveKey()`. None contains a matched fragment. In
 * particular the geography columns are named `requested_*` rather than
 * `authorised_*`, because `auth` is matched and the value would be stored as
 * "[redacted]" in the trail for exactly the change an auditor came to read.
 * SEC-DEC-044.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sovereignty_exceptions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();

            /*
             * Which approved profile this is an exception TO. Kept so a reader
             * can see what the position was at the time, rather than comparing
             * against whatever is current.
             */
            $table->foreignId('data_sovereignty_profile_id')->nullable()
                ->constrained('data_sovereignty_profiles')->nullOnDelete();

            $table->string('title', 190);
            $table->text('justification');

            /*
             * What the exception permits. `requested_*`, never `authorised_*` -
             * see the redactor note above.
             */
            $table->string('aspect', 32);
            $table->string('requested_geography', 32)->nullable();
            $table->text('scope_note')->nullable();

            /*
             * The window. `starts_on` may be null for an exception that applies
             * from approval; `ends_on` may NOT be, because an exception without
             * an end date is a permanent change to the position pretending to
             * be an exception.
             */
            $table->date('starts_on')->nullable();
            $table->date('ends_on');

            /* requested, approved, rejected, revoked. Never `expired`: expiry
             * is a fact about a date, derived on read. */
            $table->string('status', 16)->default('requested');

            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at')->nullable();

            /*
             * The approver. The service refuses to write this equal to
             * `requested_by_user_id`: a requester never approves their own
             * request, which the tier split alone cannot express.
             */
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();

            /* Revocation is a separate act from rejection: one ends something
             * that was in force, the other refuses something that never was. */
            $table->foreignId('revoked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->text('revocation_reason')->nullable();

            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['organisation_id', 'status']);
            $table->index(['organisation_id', 'ends_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sovereignty_exceptions');
    }
};
