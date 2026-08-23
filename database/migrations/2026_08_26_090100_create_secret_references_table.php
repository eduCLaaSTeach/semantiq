<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where credentials are managed. Feature ADM-012.
 *
 * THIS TABLE NEVER HOLDS A SECRET. Not a password, not a client-secret value,
 * not an API key, not an access or refresh token, not a private key, not a
 * connection string containing a password. There is no column that could hold
 * one, `SecretReference` refuses a credential-shaped value at the model, and
 * the request refuses one at the boundary. Three layers, because the field this
 * is guarding is called "reference identifier" and somebody will eventually
 * paste the credential into it.
 *
 * WHAT IT IS FOR. Answering "what credentials does this system depend on, where
 * do they live, who owns them, and when do they lapse" without holding any of
 * them. That question is what turns an expired client secret from a Monday
 * morning outage into a diary entry.
 *
 * THE ROW IS ITSELF SENSITIVE. A list of every credential a system depends on,
 * where each one lives and when it expires, is a map for anybody attacking it.
 * Both permissions sit at System Administrator, and the screen shows a provider
 * and a pointer but never resolves the pointer to anything.
 *
 * WHY `reference_type` AND NOT `secret_type`. The audit writer runs before and
 * after summaries through `Redaction::summarise()`, which replaces the value of
 * any key containing "secret". A column called `secret_type` would have its
 * value redacted out of its own audit trail, so a change from "Client secret"
 * to "Certificate" would be recorded as "[redacted] -> [redacted]". The label
 * on screen still reads "Secret type". SEC-DEC-044.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('secret_references', function (Blueprint $table) {
            $table->id();

            /* Customer-owned. The global scope fails closed with no context. */
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();

            $table->string('name', 120);

            /* Enum-backed: SecretType. Named to survive Redaction - see above. */
            $table->string('reference_type', 40);

            /* Enum-backed: SecretProvider. */
            $table->string('provider', 40);

            /*
             * A POINTER. A Key Vault secret name, a certificate thumbprint, an
             * environment variable name. Length-capped at 190 because a pointer
             * is short and a credential usually is not, which makes the cap a
             * cheap second line of defence rather than only a column width.
             */
            $table->string('reference_identifier', 190);

            /* What depends on it, in words, so the next person knows what
             * breaks when it lapses. */
            $table->string('purpose', 500);

            /* Which environment this reference is for: production, staging. A
             * string rather than an enum because the set of environments is a
             * deployment fact, not an application one. */
            $table->string('environment', 40);

            /* Who is accountable for rotating it. Nullable because a credential
             * may outlive the person who created its reference. */
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->date('expires_on')->nullable();
            $table->date('rotation_due_on')->nullable();

            /*
             * Retired, never deleted. A credential that used to exist is part
             * of the history an incident review reads, and a deleted row
             * answers no questions. SecretStatus::derive() reads this first.
             */
            $table->timestamp('retired_at')->nullable();

            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            /* One name per organisation. Two references called "Fabric client
             * secret" is how the wrong one gets rotated. */
            $table->unique(['organisation_id', 'name']);

            /* The two questions the screen actually asks. */
            $table->index(['organisation_id', 'expires_on']);
            $table->index(['organisation_id', 'rotation_due_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('secret_references');
    }
};
