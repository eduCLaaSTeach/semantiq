<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The NON-SECRET, typed half of an integration's configuration.
 *
 * SEPARATE FROM integration_secrets ON PURPOSE. This is the table every
 * listing reads, so it must be queryable without decrypting anything: a status
 * card must not need the application key to render. Merging the two would put
 * a ciphertext column on the row every screen loads, and would make the
 * encryption boundary as wide as the feature instead of one table.
 *
 * ONE ROW PER FAMILY, uniquely constrained. `family` is drawn from a closed
 * vocabulary - identity, email, ai, fabric - validated by the writer's typed
 * value object. There is no fifth family that a caller can invent.
 *
 * `settings` IS JSON AND IS STILL NOT A DUMPING GROUND. Every key inside it
 * passes a closed per-family allowlist on the way in, so the column holds a
 * validated shape rather than whatever a caller sent. The allowlist is what
 * stops a future field arriving unvalidated; JSON is only how the validated
 * result is stored.
 *
 * status AND last_tested_at ARE EVIDENCE, AND EVIDENCE EXPIRES WITH ITS
 * SUBJECT. A meaningful configuration or secret change moves status back to
 * not_checked and CLEARS last_tested_at, in the same transaction as the change.
 * Keeping the timestamp "for reference" is exactly what misleads: it is true,
 * and the claim it appears to support - that this configuration was tested - is
 * false. Only an explicit test may write a positive status.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_configurations', function (Blueprint $table): void {
            $table->id();

            $table->string('family', 32)->unique();

            // Validated against a closed per-family allowlist before it is
            // written. Never a secret: SecretsNeverReachConfigurations asserts
            // the writer refuses a key the secret store owns.
            $table->json('settings');

            // P1-09's HealthStatus values. not_checked is the only status a
            // write may set; the rest come from an explicit test.
            $table->string('status', 24)->default('not_checked');

            // A CHOSEN sentence, never a caught provider message. Provider
            // error bodies routinely echo credentials.
            $table->string('explanation', 255)->nullable();

            $table->timestamp('last_tested_at')->nullable();
            $table->timestamp('last_changed_at')->nullable();

            $table->unsignedBigInteger('last_changed_by_user_id')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_configurations');
    }
};
