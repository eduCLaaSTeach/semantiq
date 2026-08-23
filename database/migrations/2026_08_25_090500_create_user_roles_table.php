<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which roles a person holds. Feature ADM-005, "Additional Roles".
 *
 * `users.role` stays as the person's PRIMARY tier and is unchanged by this
 * table. These are the additional, controlled extensions ADM-005 lists
 * separately from the primary role, and they are additive only within the
 * primary tier's ceiling: holding a role whose tier is higher than the
 * person's own grants nothing, because `Authorization::allows()` filters every
 * effective permission through the tier before returning it.
 *
 * That ceiling is the reason this table cannot become a privilege-escalation
 * path. Assigning a System Administrator role to a Viewer changes what is
 * recorded here and changes nothing about what they can do.
 *
 * `assigned_by_user_id` is kept because "who decided this" is the first
 * question at an access review, and the answer has to survive the assigner's
 * account being deleted - hence `nullOnDelete` and a matching audit event.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();

            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_roles');
    }
};
