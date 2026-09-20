<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gate C round 3. A STAGED CHANGE CARRIES ITS DESTINATION, NOT JUST ITS SECRET.
 *
 * THE DEFECT THIS CLOSES. D-159 originally required re-authentication only when
 * the credential ITSELF was replaced or removed, and the controller saved the
 * non-secret fields immediately, before sending the administrator to Microsoft.
 * So:
 *
 *   an email password is already saved
 *     -> somebody with a stolen console session changes the SMTP HOST
 *     -> the password is untouched, so no step-up is required
 *     -> the change is saved at once
 *     -> the next test or send hands the SAVED CREDENTIAL to the new host.
 *
 * The credential never moved. It did not need to: the destination moved to meet
 * it. The same shape exists for the AI endpoint, and for the Fabric directory
 * and application identifiers, which decide who the client secret authenticates
 * TO.
 *
 * WHY A COLUMN RATHER THAN A SEVENTH TABLE. What is being staged is one
 * privileged change to one family, and it already has a row here. Splitting the
 * fields into a table of their own would make "the whole change" something the
 * completion handler has to reassemble from two places - and a partial apply is
 * precisely the mixed old-secret / new-destination state this exists to
 * prevent.
 *
 * `fields` IS NOT SENSITIVE AND IS NOT ENCRYPTED, deliberately. A host, a port
 * and a directory identifier are values the administrator typed and can see on
 * the screen they typed them into; encrypting them would suggest a protection
 * that the screen itself does not offer, and would put non-secret data through
 * the one decryption path that exists to be small and auditable.
 *
 * The `operation` column gains `reconfigure`: a staged change that may carry
 * fields, a secret, or both.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staged_integration_changes', function (Blueprint $table): void {
            // Nullable, because `remove` carries neither fields nor a payload
            // and a removal must stay the shape it already is.
            $table->json('fields')->nullable()->after('ciphertext');
        });
    }

    public function down(): void
    {
        Schema::table('staged_integration_changes', function (Blueprint $table): void {
            $table->dropColumn('fields');
        });
    }
};
