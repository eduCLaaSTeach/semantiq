<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Privacy requests. Feature PDPA-01, gate 4 batch R1.4c-i.
 *
 * One row per request from a person asking what personal data SemantIQ holds
 * about them, or asking for it to be corrected or withdrawn.
 *
 * THE SUBJECT NEED NOT HAVE AN ACCOUNT. `subject_user_id` is nullable by
 * decision D6. A contractor whose account was deleted still has personal data
 * in `audit_events`, and the PDPA does not stop applying because the account
 * did. The subject's name and contact details are therefore held on this row
 * independently of any `users` record, and survive that record's deletion.
 *
 * THAT IS ALSO THE OBVIOUS ATTACK: assemble a stranger's data by asserting you
 * are them. `identity_verified_at` is the control. Nothing is collected before
 * it is set, the transition guard lives in the service rather than in the
 * screen, and the method and what was actually checked are both recorded.
 *
 * `due_at` IS FROZEN, NOT DERIVED. It is written once at verification. A
 * deadline recomputed from a policy that somebody later edits would silently
 * move a date a person is being held to, which is the same reasoning D7 applies
 * to the breach register's PDPC deadline.
 *
 * NO FILE IS EVER PRODUCED. Decision D9. `evidence_reference` records how the
 * response was delivered outside the application; SemantIQ writes nothing to
 * disk and sends no mail, which is what keeps `public/storage`, a mail
 * transport and a queue worker out of this gate.
 *
 * AUDIT REDACTOR CHECK. Every column name was run through
 * `Redaction::isSensitiveKey()`. None matches. `identity_verification_method`
 * and `identity_verification_note` are both clean; a column named
 * `verification_token` or `subject_credential` would not have been, and would
 * have had its value replaced in every audit summary. SEC-DEC-044.
 *
 * INDEX NAMES ARE EXPLICIT where the generated name would be long. R1.4b's
 * production migration failed on MySQL error 1059 because Laravel derived a 67
 * character name and the limit is 64. `MigrationIdentifierLengthTest` now
 * enforces this statically, but naming them here makes the intent legible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('privacy_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();

            /* Quotable in correspondence: PR-0001. Unique per organisation. */
            $table->string('reference', 32);

            /* received, identity_verification, assembling, in_review,
             * awaiting_decision, responded, refused, closed. */
            $table->string('status', 32)->default('received');

            /* access, correction, withdrawal. */
            $table->string('request_type', 16);

            /*
             * NULLABLE BY DESIGN - D6. Null means the subject has no SemantIQ
             * account, or no longer has one. `nullOnDelete` rather than cascade:
             * deleting an account must not delete the record of a request that
             * person made, or the trail of how it was answered.
             */
            $table->foreignId('subject_user_id')->nullable()->constrained('users')->nullOnDelete();

            /* Held independently of any account, so the request stands alone. */
            $table->string('subject_name', 190);
            $table->string('subject_email', 190);
            $table->string('subject_reference', 190)->nullable();

            /* When the request arrived, which may predate this row. */
            $table->timestamp('received_at');
            $table->string('received_channel', 32)->nullable();

            /*
             * THE GATE. Null means unverified, and unverified means nothing is
             * collected. Verification carries the weight an authenticated
             * session would otherwise carry.
             */
            $table->timestamp('identity_verified_at')->nullable();
            $table->foreignId('identity_verified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('identity_verification_method', 64)->nullable();
            $table->text('identity_verification_note')->nullable();

            $table->timestamp('assembled_at')->nullable();

            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            /* released or refused. A refusal is a lawful outcome. */
            $table->string('decision', 16)->nullable();
            $table->text('decision_reason')->nullable();

            $table->timestamp('released_at')->nullable();
            $table->foreignId('released_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            /* How the response was delivered, since SemantIQ delivers nothing. */
            $table->string('evidence_reference', 190)->nullable();

            $table->timestamp('closed_at')->nullable();

            /* Frozen at verification. Never recomputed. */
            $table->timestamp('due_at')->nullable();

            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['organisation_id', 'reference'], 'privacy_requests_org_reference_unique');
            $table->index(['organisation_id', 'status', 'due_at'], 'privacy_requests_org_status_due_index');
            $table->index(['organisation_id', 'subject_user_id'], 'privacy_requests_org_subject_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('privacy_requests');
    }
};
