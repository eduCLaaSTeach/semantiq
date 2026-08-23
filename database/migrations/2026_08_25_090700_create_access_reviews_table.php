<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Periodic verification that access is still appropriate. Feature ADM-008.
 *
 * The workflow is: create a review, generate its items from what access looks
 * like RIGHT NOW, decide each item, apply the approved changes, audit
 * everything.
 *
 * The property this schema is built around is that a review is EVIDENCE. Once
 * items are generated the review holds a snapshot of what access looked like at
 * that moment, and the snapshot must stay readable even after the access it
 * describes has been changed or the account deleted. That is why
 * `access_review_items` records the role name and entitlement as text as well
 * as by id: an auditor asking "what did we approve in March" must get an answer
 * that does not depend on March's rows still existing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('description', 500)->nullable();

            /* LifecycleStatus: draft, open, completed or cancelled. */
            $table->string('status', 24)->default('draft');

            $table->date('due_at')->nullable();

            /*
             * When items were generated, which is the instant the snapshot was
             * taken. Null while the review is still a draft.
             */
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            /*
             * When the approved revocations were actually carried out. Separate
             * from `completed_at` because deciding and applying are different
             * events, and a review that was decided but never applied is a
             * finding in its own right.
             */
            $table->timestamp('applied_at')->nullable();

            $table->foreignId('opened_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organisation_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_reviews');
    }
};
