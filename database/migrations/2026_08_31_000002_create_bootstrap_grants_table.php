<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Single-use first-administrator grants.
 *
 * consumed_at being NULL is the single-use guard, and it is enforced in the
 * WHERE clause of the consuming UPDATE rather than by an application check -
 * so two concurrent redemptions cannot both succeed regardless of timing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bootstrap_grants', function (Blueprint $table): void {
            $table->id();

            // SHA-256 only. The plaintext grant is never stored.
            $table->string('token_hash', 64);

            $table->string('expected_subject');
            $table->string('expected_tenant', 64);
            $table->string('issued_by')->nullable();

            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->unsignedBigInteger('consumed_by_user_id')->nullable();

            $table->timestamps();

            $table->unique('token_hash', 'boot_grants_token_uq');
            $table->index('expires_at', 'boot_grants_expires_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bootstrap_grants');
    }
};
