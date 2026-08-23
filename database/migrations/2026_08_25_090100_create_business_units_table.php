<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Major organisational divisions. Feature ADM-003.
 *
 * A business unit is a SCOPE, not a permission. It answers "which slice of the
 * organisation is this person part of" and later narrows what a domain
 * entitlement covers. It never grants anything by itself, and no authorization
 * check may read it as though it did.
 *
 * The hierarchy is a self-reference rather than a nested set or a path string.
 * A real organisation chart is shallow and reorganises often; a materialised
 * path optimises the read this application does not do often and makes every
 * reorganisation an update of every descendant. Loop prevention is
 * VAL-BU-LOOP-001, enforced in the service because no relational constraint can
 * express "not an ancestor of itself".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();

            $table->string('code', 32);
            $table->string('name');

            /*
             * `nullOnDelete` rather than cascade: deleting a parent must not
             * silently delete a subtree of units that people are assigned to.
             * The children become roots, which is visible and fixable, rather
             * than disappearing.
             */
            $table->foreignId('parent_id')->nullable()->constrained('business_units')->nullOnDelete();

            /* The person accountable for the unit, ADM-003. Not a permission. */
            $table->foreignId('manager_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('cost_centre', 64)->nullable();
            $table->string('country', 2)->nullable();

            /*
             * ADM-003 asks for effective dates so historical assignments stay
             * auditable. Dates rather than timestamps: a unit becomes effective
             * on a day, not at an instant, and a timestamp would invite a
             * time-zone argument with no business meaning.
             */
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();

            /* LifecycleStatus, restricted to active and disabled. */
            $table->string('status', 24)->default('active');

            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            /* VAL-BU-CODE-001: unique WITHIN the organisation, not globally. */
            $table->unique(['organisation_id', 'code']);
            $table->index(['organisation_id', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_units');
    }
};
