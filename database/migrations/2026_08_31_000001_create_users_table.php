<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P1-00 owns this table. It is the identity surface and nothing more.
 *
 * No roles table, no permissions, no domains, no scopes, no sensitivity, no
 * organisations, no teams. Those belong to P1-01 through P1-05 and
 * NoBusinessSchemaTest still forbids them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();

            // The identity key. Email is deliberately not part of it: email is
            // mutable and reassignable, and a reassigned mailbox must never
            // inherit a SemantIQ identity.
            $table->string('provider', 32);
            $table->string('external_subject', 64);
            $table->string('tenant_id', 64);

            // Projections of the directory, refreshed on each sign-in. Never
            // used for authorisation.
            $table->string('email');
            $table->string('display_name');

            $table->enum('status', ['active', 'inactive'])->default('active');

            // The D-09 seam. Only 'system_administrator' is ever written, and
            // P1-05 owns replacing this with the real role model.
            $table->string('platform_role', 32)->nullable();

            $table->timestamp('last_signed_in_at')->nullable();
            $table->timestamps();

            // Short, explicit names: MySQL caps identifiers at 64 characters and
            // MigrationIdentifierLengthTest enforces it.
            $table->unique(['provider', 'external_subject', 'tenant_id'], 'users_identity_uq');
            $table->index('platform_role', 'users_platform_role_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
