<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The ONE local Bootstrap Administrator, for the state before SSO exists.
 *
 * IT IS NOT A USER AND CANNOT BECOME ONE. There is no users row, no
 * role_assignments row and no organisation. BootstrapPrincipal is a distinct
 * readonly type with no relationship to User, and AccessEngine has no method
 * that accepts it - so "give the bootstrap principal an entitlement" does not
 * typecheck. That is a type boundary rather than a policy, which is the point.
 *
 * EXACTLY ONE, ENFORCED BY THE DATABASE. `singleton` is a fixed value with a
 * unique constraint. Application logic that counts rows before inserting is a
 * race; a unique index is not.
 *
 * disabled_at IS WRITTEN, AND THAT IS THE WHOLE POINT OF THIS COLUMN.
 *
 * An earlier draft of this design gated the local password purely on the
 * computed "no System Administrator exists" predicate, and carried this column
 * without ever writing it. The consequence the draft missed: establish the
 * first administrator, then deactivate every System Administrator, and the
 * system is UNCONFIGURED again - so the ORIGINAL bootstrap password starts
 * working. A standing local backdoor with a permanent password, reachable by
 * deactivating one account.
 *
 * So the transition WRITES. In the same transaction that creates the first
 * permanent System Administrator: disabled_at is set, AND password_hash is
 * replaced with an unusable value. Both, not either. Clearing disabled_at by
 * hand afterwards therefore restores nothing, because the old hash is gone.
 *
 * AFTER THAT, UNCONFIGURED ALONE MUST NOT REOPEN LOCAL PASSWORD LOGIN. The
 * predicate remains necessary and stops being sufficient. The only thing that
 * reopens it is a trusted operator issuing a recovery token
 * (bootstrap_recovery_tokens), and recovery closes again once a restored SSO
 * administrator signs in.
 *
 * THIS SUPERSEDES BootstrapState's DOCBLOCK FOR THE LOCAL PASSWORD ONLY. The
 * SSH operator grant channel it describes is unchanged and still requires SSH,
 * a fresh auditable grant and full Entra SSO. That channel predates this table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bootstrap_administrators', function (Blueprint $table): void {
            $table->id();

            $table->string('singleton', 16)->unique();

            $table->string('email');

            // Hash::make - the configured adaptive hash. NEVER SHA-256: that is
            // the right primitive for a high-entropy generated token and the
            // wrong one for a human-chosen password.
            $table->string('password_hash');

            // Set atomically with the first permanent administrator. See above.
            $table->timestamp('disabled_at')->nullable();

            $table->timestamp('last_signed_in_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bootstrap_administrators');
    }
};
