<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Encrypted integration secrets, in their own table because the access pattern
 * is different from everything else.
 *
 * A secret is written rarely, read by exactly the adapter that needs it, and
 * NEVER by a listing. Keeping it here means "list the integrations" cannot
 * accidentally load a ciphertext into a response, and the encryption boundary
 * is one table wide.
 *
 * THIS UNIT INTRODUCES APPLICATION-LEVEL ENCRYPTION. Nothing in SemantIQ was
 * encrypted before it. `ciphertext` is Crypt::encryptString output over the
 * application key, and no reader outside the adapter is permitted to call
 * decrypt.
 *
 * key_version RECORDS WHICH KEY ENCRYPTED A ROW. IT DOES NOT MAKE APP_KEY
 * ROTATION SAFE. It cannot decrypt a row whose key is gone; a version column on
 * an undecryptable ciphertext tells you accurately which key you no longer
 * have. Rotation is UNSUPPORTED in Release 1 while rows exist here, unless the
 * old key is available to an approved re-encryption procedure that decrypts
 * with the old key and re-encrypts with the new BEFORE the old key is retired.
 * That restriction is operational and documented; it is not a promise this
 * column makes.
 *
 * THERE IS NO REVEAL. No route, no command and no projection returns a
 * decrypted value to a person. Presence is reported; the value is not.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_secrets', function (Blueprint $table): void {
            $table->id();

            $table->string('family', 32);
            $table->string('name', 64);

            // Long because ciphertext is base64 of a payload with an IV and a
            // MAC, and because a client secret is not a password.
            $table->text('ciphertext');

            $table->unsignedInteger('key_version')->default(1);

            $table->timestamp('last_changed_at')->nullable();
            $table->unsignedBigInteger('last_changed_by_user_id')->nullable();

            $table->timestamps();

            // One current value per named secret per family. A replacement
            // overwrites; there is no history, because a history of secrets is
            // a longer window in which an old one is still readable.
            $table->unique(['family', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_secrets');
    }
};
