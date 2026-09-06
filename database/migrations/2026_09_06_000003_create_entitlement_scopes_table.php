<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which records inside an entitled domain a grant path may reach.
 *
 * SEVERAL CURRENT SCOPES ON ONE ENTITLEMENT ARE LEGITIMATE, AND THEY UNION. A
 * record is in scope when ANY current row here covers it. A manager over three
 * teams holds three rows; one never reduces or cancels another, and a broader
 * row does not cap a narrower one - Organisation plus Team A means the Team row
 * is REDUNDANT, not restrictive.
 *
 * team_id and business_unit_id are BOTH nullable because the required target
 * depends on the scope type: `team` requires team_id, `business_unit` requires
 * business_unit_id, and `own`, `domain` and `organisation` must carry NEITHER. A
 * target where none applies is a stored contradiction the screen cannot
 * explain. The rule needs one column to be required conditionally on the value
 * of another, which neither MySQL 8.4 nor SQLite expresses portably, so it is
 * enforced in the service and N-SC11/N-SC12 break it in both directions.
 *
 * REVOKING THE LAST SCOPE DOES NOT REVOKE THE ENTITLEMENT. Removing a child
 * must never silently mean the parent was revoked. The entitlement stays
 * current and becomes an incomplete, non-authorising grant path - access
 * through it is zero immediately, and the screen says so rather than showing it
 * as though it works.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entitlement_scopes', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('domain_entitlement_id');

            $table->string('scope_type', 32);

            // Required for exactly one scope type each, refused for the rest.
            $table->unsignedBigInteger('team_id')->nullable();
            $table->unsignedBigInteger('business_unit_id')->nullable();

            $table->dateTime('assigned_at');
            $table->dateTime('ended_at')->nullable();

            $table->unsignedBigInteger('assigned_by_user_id')->nullable();
            $table->unsignedBigInteger('ended_by_user_id')->nullable();

            $table->timestamps();

            $table->index(['domain_entitlement_id', 'ended_at'], 'scopes_entitlement_ended_idx');
            $table->index('team_id', 'scopes_team_idx');
            $table->index('business_unit_id', 'scopes_business_unit_idx');

            $table->foreign('domain_entitlement_id', 'scopes_entitlement_fk')
                ->references('id')
                ->on('domain_entitlements');

            $table->foreign('team_id', 'scopes_team_fk')
                ->references('id')
                ->on('teams');

            $table->foreign('business_unit_id', 'scopes_business_unit_fk')
                ->references('id')
                ->on('business_units');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entitlement_scopes');
    }
};
