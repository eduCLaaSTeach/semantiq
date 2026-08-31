<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The top of the structural tree.
 *
 * There is deliberately NO legal_entity_id column. A single parent was proposed
 * and rejected under D-14: one legal entity may span several business units and
 * one business unit may operate across several legal entities, so a single
 * parent would write a falsehood into the data the first day that stopped being
 * true. The association lives in business_unit_legal_entity.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_units', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organisation_id')->constrained('organisations');
            $table->string('name');

            // Administrator correlation and import only. Never an identity key.
            $table->string('code', 32)->nullable();

            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->unique(['organisation_id', 'name'], 'business_units_org_name_uq');
            $table->unique(['organisation_id', 'code'], 'business_units_org_code_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_units');
    }
};
