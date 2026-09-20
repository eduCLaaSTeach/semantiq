<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A credential change waiting for step-up re-authentication - D-159.
 *
 * WHY THE VALUE CANNOT LIVE IN pending_step_ups. That table is P1-05's, its
 * columns are structural - a role code, a domain id, a sensitivity - and every
 * one of them is safe to read. Putting a client secret in it would make a
 * privileged-action table into a credential store, and would mean the P1-05
 * step-up screens and any future listing of pending confirmations were one
 * careless select away from rendering one.
 *
 * So pending_step_ups carries only an OPAQUE REFERENCE to a row here, through
 * the subject_type / subject_id seam P1-07 already uses, and the sensitive
 * value stays behind this table's own encryption.
 *
 * ENCRYPTED, SHORT-LIVED, SINGLE-USE. The payload is Crypt::encryptString over
 * the application key, the TTL is the step-up lifetime and no longer, and
 * consumption is a conditional UPDATE whose guard is in the WHERE clause - the
 * same shape as BootstrapGrant and bootstrap_recovery_tokens.
 *
 * WHY STAGE AT ALL. Completing a secret change needs the new secret, and the
 * administrator is away at Microsoft between requesting the change and
 * confirming it. The alternatives are worse: holding it in the session puts a
 * plaintext credential in the session store - which on this deployment is a
 * database table - and asking for it again after the round trip means typing a
 * credential twice and makes the confirmation screen a second place to phish.
 *
 * A REFUSED OR EXPIRED STEP-UP LEAVES THE CONFIGURATION UNCHANGED. Nothing here
 * is applied until the completion handler runs inside the transaction that
 * consumes the step-up reference.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staged_integration_changes', function (Blueprint $table): void {
            $table->id();

            // Which family and which named secret. Both come from the closed
            // allowlists, never from free input.
            $table->string('family', 32);
            $table->string('secret_name', 64);

            /*
             * `replace` or `remove`.
             *
             * REMOVAL IS STAGED TOO, and carries no payload. It could have gone
             * straight through the step-up row as an intent - but then the two
             * paths would differ in shape, and the one that carries a
             * credential would be the unusual one. Keeping them identical means
             * the completion handler has a single code path to get right.
             */
            $table->string('operation', 16);

            // Null for a removal. Crypt::encryptString for a replacement.
            $table->text('ciphertext')->nullable();

            $table->unsignedBigInteger('requested_by_user_id');

            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staged_integration_changes');
    }
};
