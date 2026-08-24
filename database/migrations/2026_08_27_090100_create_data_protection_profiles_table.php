<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The data protection profile. Feature ADM-014, gate 4 batch R1.4a.
 *
 * The organisation's stated position on privacy: which regime applies, who is
 * accountable, and how long it has to notify a regulator about a breach.
 *
 * VERSIONED, NOT MUTABLE. Decision D4, recorded as SEC-DEC-065. A row is a
 * VERSION, not a settings record. An approved version is immutable; changing it
 * writes a new version and supersedes the old one. The alternative - one
 * editable row - is simpler and cannot answer "what was in force in March",
 * which is exactly the question a regulator, an auditor or a breach assessment
 * asks. A position that can be edited after the fact is not evidence.
 *
 * At most ONE approved version per organisation at a time. Enforced by a
 * partial-unique arrangement the service maintains and a test asserts, rather
 * than by a database constraint: MySQL cannot express "unique where status =
 * approved", and a full unique on (organisation_id, status) would also forbid a
 * second superseded version, which is the normal case.
 *
 * THE COMPLIANCE-OWNED FIELDS ARE NULLABLE ON PURPOSE. `regime_basis` and
 * `breach_notification_basis` hold the legal reasoning, and engineering does not
 * write legal reasoning. They start null and the screen shows Not Configured, so
 * a blank profile reads as incomplete rather than as a compliance claim nobody
 * made.
 *
 * AUDIT REDACTOR CHECK. Every column name here was run through
 * `Redaction::isSensitiveKey()`. None contains `auth`, `cert`, `key`, `secret`,
 * `session`, `private` or any other matched fragment, so a change to any of
 * them records its real before and after value in the audit trail instead of
 * "[redacted]". SEC-DEC-044.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_protection_profiles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();

            /* 1, 2, 3... per organisation. Never reused, never renumbered. */
            $table->unsignedInteger('version')->default(1);

            /* ProfileStatus: draft, approved, superseded. */
            $table->string('status', 16)->default('draft');

            /*
             * Which privacy regime applies. A field rather than a constant,
             * because the product is meant to be sold to customers in other
             * jurisdictions. SEC-DEC-041 determined the Singapore PDPA applies
             * to the current deployment; that determination is a value here,
             * not an assumption in code.
             */
            $table->string('applicable_regime', 64)->nullable();

            /* The legal reasoning. Compliance-owned, so it starts null. */
            $table->text('regime_basis')->nullable();

            /*
             * Whether somebody has actually been designated. A boolean rather
             * than inferring it from the privacy contact being filled in: a
             * name in a field is not the same fact as a person having been
             * appointed, and conflating them would let the screen report an
             * appointment nobody made.
             */
            $table->boolean('privacy_officer_designated')->default(false);

            /*
             * Decision D7. Three calendar days is accepted for implementation
             * and is the catalogue default; the BASIS remains compliance-owned
             * and starts null.
             *
             * A breach record freezes the resolved deadline at the moment the
             * notification decision is taken (R1.4c), so editing this later
             * cannot move a date somebody is being held to.
             */
            $table->unsignedSmallInteger('breach_notification_due_days')->nullable();
            $table->text('breach_notification_basis')->nullable();

            /* Free text for anything the fields above do not cover. */
            $table->text('notes')->nullable();

            /*
             * Approval. `approved_by_user_id` is nullOnDelete so the record
             * survives the person leaving - the approval still happened, and
             * losing who made it would be worse than showing a departed
             * account.
             */
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('superseded_at')->nullable();
            $table->foreignId('superseded_by_id')->nullable()
                ->constrained('data_protection_profiles')->nullOnDelete();

            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['organisation_id', 'version']);
            $table->index(['organisation_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_protection_profiles');
    }
};
