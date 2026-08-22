<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The customer's data protection and sovereignty policy, versioned.
 *
 * Fields follow section 4 of doc/reference/DATA_PROTECTION_SOVEREIGNTY_STANDARD.md.
 * The standard requires the profile to be versioned, privileged and audited, so
 * a change writes a new row rather than editing the current one: the question
 * "what policy was in force when this was provisioned" has to be answerable
 * years later, and an updated row cannot answer it.
 *
 * Every permissive flag defaults to false, and the two geography lists default
 * to null. That is the deny-by-default rule expressed in the schema rather than
 * left to the code that reads it. A profile that has never been configured must
 * not be a profile that permits everything, so `VAL-SOV-GEO-001` treats unset
 * geographies as BLOCKED for production activation, not as unrestricted.
 *
 * Values are deliberately not seeded in this phase. Decision D5 in
 * doc/execution/PHASE-00-PLAN.md defers the customer's approved geographies to
 * Phase 02, where something is actually provisioned; the mechanism ships now so
 * that nothing can be provisioned before the values exist.
 *
 * Requirement IDs: FR-DPS-001, FR-DPS-003, FR-DPS-007, NFR-COMP-02.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_protection_profiles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();

            /*
             * Monotonic per organisation. Superseded versions are kept, so the
             * profile in force at any past moment can be reconstructed.
             */
            $table->unsignedInteger('version');

            /*
             * Marks the version in force. MySQL cannot express "at most one true
             * per organisation" as a unique index, so the invariant is held by
             * the model, which demotes the previous current row inside the same
             * transaction that promotes the new one.
             */
            $table->boolean('is_current')->default(false);

            /*
             * Approved geographies. Null means "not yet stated", which is not
             * the same as "none approved" and is definitely not "all approved".
             * The sovereignty check refuses production activation on null.
             */
            $table->json('approved_storage_geographies')->nullable();
            $table->json('approved_processing_geographies')->nullable();

            /* The three cross-geo permissions. All default false, per the standard. */
            $table->boolean('cross_geo_processing_allowed')->default(false);
            $table->boolean('cross_geo_storage_allowed')->default(false);
            $table->boolean('conversation_history_outside_geo_allowed')->default(false);

            /*
             * Network and key policy. False here does not mean a control is off;
             * it means the customer has not required it, and restricted data
             * makes the evaluation mandatory regardless.
             */
            $table->boolean('public_internet_access_allowed')->default(false);
            $table->boolean('customer_managed_key_required')->default(false);

            /* Purview and DLP posture, evaluated from Phase 03. */
            $table->boolean('purview_sensitivity_labels_required')->default(false);
            $table->boolean('dlp_policy_required')->default(false);

            /*
             * Retention as policy, not as a constant buried in code. Seven years
             * for audit and compliance per the CLAUDE.md project baseline, and
             * ninety days for operational metadata per the standard's rule that
             * nothing is retained indefinitely by default. Both are overridable
             * by an approved customer policy, which is the point of storing them.
             */
            $table->string('default_retention_class', 64)->default('operational-90-day');
            $table->unsignedInteger('operational_retention_days')->default(90);
            $table->unsignedInteger('audit_retention_days')->default(2555);

            /*
             * Whether production request and response payloads may be captured
             * in observability. False by default and expected to stay false:
             * FR-DPS-007 requires logs that are safe to read.
             */
            $table->boolean('production_payload_logging')->default(false);

            $table->boolean('data_export_allowed')->default(false);

            /*
             * Support capture is a time-bound exception in the standard, never a
             * standing permission, so the expiry lives beside the flag. A flag
             * without an expiry becomes permanent by neglect.
             */
            $table->boolean('support_data_capture_allowed')->default(false);
            $table->timestamp('support_data_capture_expires_at')->nullable();

            /*
             * Who set this version and why. A privileged change with no recorded
             * author is not auditable, and the free-text note is where a
             * documented exception is justified.
             */
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['organisation_id', 'version']);
            $table->index(['organisation_id', 'is_current']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_protection_profiles');
    }
};
