<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organisation_id')->constrained('organisations');
            $table->foreignId('department_id')->constrained('departments');
            $table->string('name');
            $table->string('code', 32)->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->unique(['department_id', 'name'], 'teams_dept_name_uq');
            $table->index('organisation_id', 'teams_org_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};
