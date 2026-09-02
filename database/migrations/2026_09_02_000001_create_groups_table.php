<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P1-03 groups: SemantIQ-owned, flat, and inert - D-35.
 *
 * WHAT IS DELIBERATELY ABSENT IS THE POINT. There is no role, permission,
 * scope, domain, sensitivity, entitlement or administrator column, and no
 * boolean that could be read as one. A group is an organisational label and a
 * membership container; P1-05 owns deciding whether groups ever participate in
 * access, and a column added "ready for that" would be P1-05 arriving early
 * through the back door.
 *
 * PeopleBoundaryTest asserts the physical column set, timestamps included, and
 * additionally fails on any column name containing role, permission, scope,
 * domain, sensitivity, entitlement, admin or grant - so a plausible-looking
 * owner_role is caught even if somebody updated the expected list without
 * thinking about why it was there.
 *
 * Not mirrors of Entra security groups. Mirroring needs Graph, which Release 1
 * deliberately does not have, and a rule for what happens when a directory
 * group changes underneath a membership somebody is relying on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('organisation_id');

            $table->string('name');
            $table->string('code', 32)->nullable();
            $table->string('description', 500)->nullable();

            $table->enum('status', ['active', 'inactive'])->default('active');

            $table->timestamps();

            // Declared BEFORE the constraint so MySQL reuses this index instead
            // of creating a second implicit one for the foreign key.
            $table->index('organisation_id', 'groups_org_idx');

            $table->foreign('organisation_id', 'groups_org_fk')
                ->references('id')
                ->on('organisations');

            // One "Finance" per organisation. Two is an administrative accident,
            // not a structure.
            $table->unique(['organisation_id', 'name'], 'groups_org_name_uq');
            $table->unique(['organisation_id', 'code'], 'groups_org_code_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('groups');
    }
};
