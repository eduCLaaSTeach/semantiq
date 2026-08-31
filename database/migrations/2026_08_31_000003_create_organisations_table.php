<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P1-01 owns this table.
 *
 * Release 1 is single-tenant, so exactly one row is expected. The table exists
 * anyway because every other P1-01 table carries organisation_id, and that
 * column is what keeps the tenancy boundary real without building multi-tenancy.
 * There is no tenant resolution, no switching and no cross-organisation query.
 *
 * No seed row. A default organisation created by migration would be invented
 * business content; the Company Profile screen creates it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organisations', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('country', 2)->nullable();
            $table->string('timezone', 64)->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organisations');
    }
};
