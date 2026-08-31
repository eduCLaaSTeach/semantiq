<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organisation_id')->constrained('organisations');
            $table->foreignId('business_unit_id')->constrained('business_units');
            $table->string('name');
            $table->string('code', 32)->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->unique(['business_unit_id', 'name'], 'departments_bu_name_uq');
            $table->index('organisation_id', 'departments_org_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
