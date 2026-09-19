<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P1-07 Access Reviews. TWO tables, both new.
 *
 * NOTHING IS ADDED TO OR ALTERED ON ANY P1-05 TABLE, and there is no data
 * migration. Rollback drops these two and nothing else, so a rollback cannot
 * lose a grant.
 *
 * EVERY FOREIGN KEY IS RESTRICT, NEVER CASCADE. A review record must survive
 * the access it reviewed - that is the whole point of the evidence. P1-05 never
 * deletes, so RESTRICT can never fire in practice; it is here so that a future
 * delete cannot silently take the evidence with it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_review_cycles', function (Blueprint $table): void {
            $table->id();
            // Nullable to match role_assignments, which is platform-scoped for
            // the System Administrator role.
            $table->unsignedBigInteger('organisation_id')->nullable();
            $table->dateTime('started_at');
            // Stored and compared as an INSTANT. Overdue must mean the same
            // thing in every timezone.
            $table->dateTime('due_at');
            $table->unsignedBigInteger('started_by_user_id');
            $table->timestamps();

            $table->index('due_at', 'review_cycles_due_idx');

            $table->foreign('organisation_id', 'review_cycles_org_fk')
                ->references('id')->on('organisations')->restrictOnDelete();
            $table->foreign('started_by_user_id', 'review_cycles_starter_fk')
                ->references('id')->on('users')->restrictOnDelete();
        });

        Schema::create('access_review_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('access_review_cycle_id');
            // Denormalised from the cycle so every listing, count and authority
            // query is organisation-scoped WITHOUT a join. A review raised in
            // one organisation must never appear in another's screen, and the
            // System Administrator role is platform-scoped, so "the actor's
            // assignment has no organisation" cannot be allowed to mean "every
            // organisation".
            $table->unsignedBigInteger('organisation_id')->nullable();
            $table->string('kind', 16);
            $table->unsignedBigInteger('subject_user_id');

            // EXACTLY ONE of these two is set - the reviewed object (D-84).
            $table->unsignedBigInteger('role_assignment_id')->nullable();
            $table->unsignedBigInteger('domain_entitlement_id')->nullable();

            // Denormalised for authority resolution and filtering, never as a
            // second source of truth.
            $table->unsignedBigInteger('business_domain_id')->nullable();
            $table->string('role_code', 40);

            $table->string('state', 16);
            $table->dateTime('due_at');

            // What the reviewer was shown, and its hash. D-93 compares the hash
            // against the live composition at decision time.
            $table->json('composition')->nullable();
            $table->string('composition_fingerprint', 64);

            // Write-once decision columns.
            $table->dateTime('decided_at')->nullable();
            $table->unsignedBigInteger('decided_by_user_id')->nullable();
            $table->string('decision_basis', 32)->nullable();
            $table->boolean('self_review')->default(false);
            $table->string('superseded_reason', 40)->nullable();

            $table->timestamps();

            $table->index(['state', 'due_at'], 'review_items_state_due_idx');
            $table->index(['access_review_cycle_id', 'state'], 'review_items_cycle_state_idx');
            $table->index('subject_user_id', 'review_items_subject_idx');
            $table->index('business_domain_id', 'review_items_domain_idx');
            $table->index(['kind', 'state'], 'review_items_kind_state_idx');
            $table->index(['organisation_id', 'state'], 'review_items_org_state_idx');

            /*
             * IDEMPOTENT GENERATION. MySQL permits many NULLs in a unique
             * index, which is exactly what is wanted: privileged rows carry a
             * null entitlement and do not collide with one another.
             */
            $table->unique(['access_review_cycle_id', 'role_assignment_id'], 'review_items_cycle_assignment_uq');
            $table->unique(['access_review_cycle_id', 'domain_entitlement_id'], 'review_items_cycle_entitlement_uq');

            $table->foreign('access_review_cycle_id', 'review_items_cycle_fk')
                ->references('id')->on('access_review_cycles')->restrictOnDelete();
            $table->foreign('subject_user_id', 'review_items_subject_fk')
                ->references('id')->on('users')->restrictOnDelete();
            $table->foreign('role_assignment_id', 'review_items_assignment_fk')
                ->references('id')->on('role_assignments')->restrictOnDelete();
            $table->foreign('domain_entitlement_id', 'review_items_entitlement_fk')
                ->references('id')->on('domain_entitlements')->restrictOnDelete();
            $table->foreign('business_domain_id', 'review_items_business_domain_fk')
                ->references('id')->on('business_domains')->restrictOnDelete();
            $table->foreign('decided_by_user_id', 'review_items_decider_fk')
                ->references('id')->on('users')->restrictOnDelete();
            $table->foreign('organisation_id', 'review_items_org_fk')
                ->references('id')->on('organisations')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_review_items');
        Schema::dropIfExists('access_review_cycles');
    }
};
