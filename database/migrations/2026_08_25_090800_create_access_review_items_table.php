<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One access grant, awaiting a keep-or-revoke decision. Feature ADM-008.
 *
 * One row per person per grant, generated when the review opens. Two kinds of
 * grant are reviewed, and keeping them in one table rather than two is
 * deliberate: a reviewer works through a list of decisions, not through two
 * lists that happen to look similar.
 *
 *   `role`        - an additional role from `user_roles`
 *   `entitlement` - a business domain from `domain_entitlements`
 *
 * A person's PRIMARY tier is deliberately not reviewable here. Changing
 * somebody's tier is a user-registry action with its own invariants - the last
 * System Administrator among them - and burying it in a bulk review screen
 * would route it around them.
 *
 * `subject_label` is the snapshot. It holds what the grant was CALLED when the
 * review opened, so the evidence survives the role being renamed or the grant
 * being revoked, and an auditor reading the review a year later sees what the
 * reviewer saw rather than what is true now.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_review_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('access_review_id')->constrained()->cascadeOnDelete();

            /*
             * Cascade on delete: if the account is removed entirely there is
             * nothing left to decide about. The review's own audit events
             * survive, and they are the durable evidence.
             */
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            /* role or entitlement. */
            $table->string('subject_type', 16);

            /* The role id or the BusinessDomain backing value. */
            $table->string('subject_key', 64);

            /* What it was called when the snapshot was taken. */
            $table->string('subject_label', 190);

            /* pending, keep or revoke. Not a LifecycleStatus: these are
             * decisions about a grant, not states of a record. */
            $table->string('decision', 16)->default('pending');

            $table->string('note', 500)->nullable();

            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();

            /* Whether the revocation this item asked for was carried out. */
            $table->boolean('applied')->default(false);

            $table->timestamps();

            /* One decision per grant per review. */
            $table->unique(['access_review_id', 'user_id', 'subject_type', 'subject_key'], 'access_review_items_grant_unique');
            $table->index(['access_review_id', 'decision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_review_items');
    }
};
