<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Correction notes. Feature PDPA-01, gate 4 batch R1.4c-i. Decision D11,
 * approved WITH CHANGE, recorded as SEC-DEC-066.
 *
 * WHAT THIS TABLE IS FOR. A data subject asserts that the audit trail is wrong
 * about them. `audit_events` cannot be edited - that is the whole point of it -
 * so the outcome is a NEW row here, linked to the event id, recording what the
 * person asserted and what was decided. The original event is untouched, and
 * anyone reading it sees the annotation beside it.
 *
 * THIS TABLE IS APPEND-ONLY, AND THE DATABASE IS WHAT ENFORCES THAT.
 *
 * A correction note is the record of what somebody disputed. If it can be
 * edited afterwards, the party being disputed can rewrite the dispute, and the
 * note becomes worthless as evidence at exactly the moment it matters.
 *
 * Model hooks on this table's Eloquent model throw on update and delete, but
 * THEY ARE DEFENCE IN DEPTH, NOT THE CONTROL. They do not fire on a mass
 * delete, on a raw query, or on anything that bypasses Eloquent. MySQL has no
 * DENY, so privileges cannot express this either. The control is a pair of
 * BEFORE UPDATE and BEFORE DELETE triggers.
 *
 * THE TRIGGERS ARE DELIBERATELY NOT IN THIS MIGRATION. SEC-DEC-037's reasoning:
 * a migration that can create a trigger can also drop it, which would make the
 * protection removable by the same mechanism that installs it. They are a
 * separately approved production step, run after this migration, with the exact
 * SQL recorded in `doc/execution/R1.4c-PLAN.md` section 1.8 and the proof
 * recorded in the verification document.
 *
 * R1.4c-i IS NOT ACCEPTED UNTIL BOTH TRIGGERS EXIST ON PRODUCTION, their
 * definitions match the approved SQL, and the automated suite proves they fire.
 * SEC-DEC-066 makes that a gate 4 acceptance condition, not a carry-forward.
 *
 * NOTE THERE IS NO `updated_by_user_id` COLUMN. A table that can never be
 * updated has no use for one, and its presence would imply an update path
 * exists. The absence is the documentation.
 *
 * `audit_event_id` USES restrictOnDelete, NOT CASCADE. An annotation must not
 * disappear because something upstream was removed. In practice `audit_events`
 * cannot be deleted at all - its own triggers refuse - so this is belt and
 * braces, and it makes the intent explicit to the next reader.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('privacy_correction_notes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();

            $table->foreignId('privacy_request_id')->constrained('privacy_requests')->cascadeOnDelete();

            /*
             * The disputed event. Nullable because a subject may dispute
             * something that is not a single audit event - a stored value on
             * their own record, for instance.
             */
            $table->foreignId('audit_event_id')->nullable()->constrained('audit_events')->restrictOnDelete();

            /* What the person says is wrong, in their own terms. */
            $table->text('subject_assertion');

            /* noted, applied or refused. */
            $table->string('outcome', 16)->default('noted');

            /*
             * Always required, including on `applied`. "Corrected" without a
             * reason does not say WHAT was corrected or on what basis, and a
             * reader years later needs both.
             */
            $table->text('outcome_reason')->nullable();

            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();

            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            /* No `updated_by_user_id`. See the class comment. */
            $table->timestamps();

            $table->index(
                ['organisation_id', 'audit_event_id'],
                'privacy_correction_notes_org_event_index'
            );
        });
    }

    /**
     * Dropping the table also drops any triggers attached to it, which is the
     * one case where the triggers disappear without a separate action. That is
     * unavoidable and is why the production procedure is: migrate first,
     * install triggers second, prove them third - never the other order.
     */
    public function down(): void
    {
        Schema::dropIfExists('privacy_correction_notes');
    }
};
