<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The customer organisation that owns this SemantIQ instance.
 *
 * ADM-002 in Release 1 gate 2 owns the editable profile. This migration exists
 * one gate earlier because the row is the anchor every other record is scoped
 * to: audit events, settings and flags all carry `organisation_id` so a future
 * multi-tenant service can isolate customers without a redesign, which CLAUDE.md
 * requires of every customer-owned record.
 *
 * The current deployment baseline is ONE organisation per instance. A bootstrap
 * row is therefore inserted here rather than left to a seeder, because
 * production runs `migrate --force` and never runs seeders: without the row the
 * organisation context resolves to nothing and, by design, every scoped read
 * returns nothing and every scoped write is refused. ADM-002 edits this row; it
 * does not create a second one.
 *
 * No customer data lives here. Name and code only, both supplied by the
 * customer and neither secret.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organisations', function (Blueprint $table) {
            $table->id();

            /*
             * The stable short code. VAL-ORG-CODE-001 makes it unique and
             * immutable once dependencies exist; the uniqueness half is
             * enforced here so two rows cannot exist even if a later code path
             * forgets to check.
             */
            $table->string('code', 32)->unique();
            $table->string('name');

            /*
             * LifecycleStatus, restricted to the organisation vocabulary
             * (active, disabled). Held as a string rather than a native enum so
             * the vocabulary can widen without an ALTER on a live MySQL table.
             */
            $table->string('status', 24)->default('active');

            /*
             * Section 3 common fields. Nullable because the bootstrap row below
             * has no actor: it is created by the installer, not by a person.
             */
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            /* Optimistic concurrency for ADM-002's edit form. */
            $table->unsignedInteger('version')->default(1);

            $table->timestamps();
        });

        /*
         * The bootstrap organisation. `config('app.name')` is the only value
         * read, and it is a display name rather than a credential.
         */
        DB::table('organisations')->insert([
            'code' => 'PRIMARY',
            'name' => (string) config('app.name', 'SemantIQ'),
            'status' => 'active',
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('organisations');
    }
};
