<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The D-14 junction: optional many-to-many.
 *
 * Association ONLY. No dates, no percentages, no "primary" flag, no attributes
 * of any kind. An attribute here would be the first thing a later unit reads as
 * employment or entitlement, and D-14 states the association grants nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_unit_legal_entity', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organisation_id')->constrained('organisations');
            $table->foreignId('business_unit_id')->constrained('business_units');
            $table->foreignId('legal_entity_id')->constrained('legal_entities');
            $table->timestamps();

            $table->unique(['business_unit_id', 'legal_entity_id'], 'bu_le_pair_uq');
            $table->index('legal_entity_id', 'bu_le_entity_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_unit_legal_entity');
    }
};
