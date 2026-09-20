<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The ONLY thing that can reopen local password login once bootstrap has
 * closed.
 *
 * Issued over SSH by a trusted operator, never by a screen and never
 * automatically. Once bootstrap_administrators.disabled_at is set, the computed
 * UNCONFIGURED predicate no longer reopens the local password on its own; this
 * table does, deliberately, once, with a record of who asked.
 *
 * IT REUSES THE bootstrap_grants PATTERN, WHICH P1-00 VERIFIED, rather than
 * inventing a second one: hash at rest, a TTL, and single-use consumption whose
 * guard is in the WHERE clause of a conditional UPDATE. Two concurrent
 * redemptions cannot both succeed, because the database decides and not the
 * application's timing.
 *
 * IT IS A SEPARATE TABLE FROM THE PRINCIPAL ON PURPOSE. A token is transient
 * and a principal is not. Merging them would mean consuming a token mutates the
 * principal's row, and would give a row two lifetimes.
 *
 * NOT bootstrap_grants ITSELF. That table's semantics are Entra-bound - an
 * expected subject and an expected tenant, checked before any UPDATE - and they
 * stay untouched. A pre-SSO local principal has no Entra subject to bind to, so
 * reusing that table would mean weakening its binding to fit a case it was not
 * built for.
 *
 * RECOVERY IS AN EPISODE, NOT A MODE. Redemption opens a temporary recovery
 * context explicitly - it does not un-close the principal as a side effect -
 * and the context closes again by the same write as the first transition, once
 * a restored SSO administrator signs in successfully.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bootstrap_recovery_tokens', function (Blueprint $table): void {
            $table->id();

            // SHA-256 of a high-entropy generated token. The plaintext exists
            // only in the operator's terminal and is never stored, logged or
            // re-displayable.
            $table->string('token_hash', 64)->unique();

            $table->timestamp('expires_at');

            $table->timestamp('consumed_at')->nullable();

            // Free text from an operator at an SSH prompt: who asked and why.
            // It is evidence, and it never reaches a security event context -
            // ALLOWED_KEYS carries no key that could hold it.
            $table->string('issued_by', 255)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bootstrap_recovery_tokens');
    }
};
