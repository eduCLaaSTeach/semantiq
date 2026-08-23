<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Working teams beneath business units. Feature ADM-004.
 *
 * Like a business unit, a team is a SCOPE and never a permission.
 *
 * `business_unit_id` is NOT NULL and restricted on delete, because ADM-004's
 * first rule is that a team belongs to exactly one business unit
 * (VAL-TEAM-BU-001). A nullable column would make an orphan team representable,
 * and anything representable eventually gets represented.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();

            /*
             * Restricted rather than cascaded: a business unit holding teams
             * cannot be deleted out from under them. The administrator has to
             * move or remove the teams first, which is the conversation that
             * should happen.
             */
            $table->foreignId('business_unit_id')->constrained()->restrictOnDelete();

            $table->string('code', 32);
            $table->string('name');
            $table->string('description', 500)->nullable();

            $table->foreignId('lead_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('status', 24)->default('active');

            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organisation_id', 'code']);
            $table->index('business_unit_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};
