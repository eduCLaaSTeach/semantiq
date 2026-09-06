<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The sensitivity ceiling on one entitlement - D-60.
 *
 * ONE CEILING, ON THE ENTITLEMENT. There is no person-level ceiling: two
 * independent cap paths would leave an entitlement raised to Confidential still
 * capped by an invisible person-level Standard, on a screen that could not
 * explain why.
 *
 * A CLEARED CEILING AND AN ABSENT CEILING ARE DIFFERENT STATES, and this table
 * is why the distinction is representable. "Clear" writes a CURRENT `standard`
 * row - it is a deliberate act that leaves evidence. It does not delete. If no
 * current row exists at all, that is a malformed state and the engine FAILS
 * CLOSED with denied_ceiling_missing; it never assumes standard, because an
 * assumed default is an invisible permissive default by another name.
 *
 * A ceiling is a child of the entitlement like a scope, and ending it does not
 * end the entitlement either.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entitlement_ceilings', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('domain_entitlement_id');

            $table->string('sensitivity', 32);

            $table->dateTime('assigned_at');
            $table->dateTime('ended_at')->nullable();

            $table->unsignedBigInteger('assigned_by_user_id')->nullable();
            $table->unsignedBigInteger('ended_by_user_id')->nullable();

            $table->timestamps();

            $table->index(['domain_entitlement_id', 'ended_at'], 'ceilings_entitlement_ended_idx');

            $table->foreign('domain_entitlement_id', 'ceilings_entitlement_fk')
                ->references('id')
                ->on('domain_entitlements');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entitlement_ceilings');
    }
};
