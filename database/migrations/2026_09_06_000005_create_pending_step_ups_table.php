<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The privileged action waiting for a fresh Microsoft authentication - D-73.
 *
 * THE ACTION LIVES HERE, SERVER-SIDE. Nothing about it travels in the URL: a
 * URL somebody can edit is an action somebody can substitute, so the redirect
 * carries only an opaque reference and the target, the role, the domain and the
 * level are read back from this row.
 *
 * The reference is stored HASHED, the same shape as BootstrapGrant. The
 * plaintext exists only in the redirect; a reader of this table cannot replay
 * one.
 *
 * SINGLE-USE, ENFORCED BY THE DATABASE. consumed_at is set by a conditional
 * UPDATE whose guard is in the WHERE clause, inside the same transaction as the
 * privileged write - so a replay cannot both succeed, whatever the application
 * timing. One step-up authorises ONE action, ONCE. There is deliberately no
 * time window in which everything is privileged.
 *
 * requested_at is what auth_time is measured against. Freshness comes from the
 * PROVIDER's claim, never from a flag this application set, because an
 * application-set flag is the application asserting freshness rather than
 * proving it.
 *
 * A CANCELLED OR FAILED STEP-UP CONSUMES THE REFERENCE. Leaving it alive "so
 * they can try again" converts a cancelled step-up into a reusable one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pending_step_ups', function (Blueprint $table): void {
            $table->id();

            $table->string('reference_hash', 64)->unique();

            // Bound to the person AND the session. A reference stolen from a
            // log is useless in another browser.
            $table->foreignId('user_id');
            $table->string('session_id', 64);

            $table->string('action', 64);

            // The exact target, read back on return. Never trusted from input.
            $table->unsignedBigInteger('subject_user_id')->nullable();
            $table->unsignedBigInteger('business_domain_id')->nullable();
            $table->unsignedBigInteger('domain_entitlement_id')->nullable();
            $table->string('role_code', 40)->nullable();
            $table->string('sensitivity', 32)->nullable();
            $table->unsignedBigInteger('organisation_id')->nullable();

            $table->dateTime('requested_at');
            $table->dateTime('expires_at');
            $table->dateTime('consumed_at')->nullable();

            // Why it ended, when it was not consumed by a successful action:
            // cancelled, provider_error, expired, stale_freshness, replayed.
            $table->string('outcome', 32)->nullable();

            $table->timestamps();

            $table->index(['user_id', 'consumed_at'], 'step_ups_user_consumed_idx');
            $table->index('expires_at', 'step_ups_expires_idx');

            $table->foreign('user_id', 'step_ups_user_fk')
                ->references('id')
                ->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_step_ups');
    }
};
