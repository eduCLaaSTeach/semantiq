<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The legal axis. Deliberately NOT a level in Business Unit > Department > Team:
 * D-14 records that the two axes do not align, and modelling it as a level
 * would write that misalignment into the tree.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_entities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organisation_id')->constrained('organisations');
            $table->string('name');
            $table->string('registration_number', 64)->nullable();
            $table->string('jurisdiction', 64)->nullable();
            $table->text('registered_address')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->unique(['organisation_id', 'name'], 'legal_entities_org_name_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_entities');
    }
};
