<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The organisation, which is the tenancy boundary for every customer-owned record.
 *
 * Fields follow the SRS section 17 Organisation entity. The current deployment
 * runs one organisation per application instance, but the boundary exists from
 * the first migration so that a later approved multi-tenant service isolates
 * customers without a redesign. Retro-fitting a tenancy column across a live
 * schema is the change this table exists to avoid.
 *
 * Requirement IDs: NFR-SEC-02, FR-DPS-001.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organisations', function (Blueprint $table) {
            $table->id();

            /*
             * The externally stable identifier. Separate from the auto-increment
             * key so an organisation can be referenced in exports, support
             * tickets and audit records without leaking row counts.
             */
            $table->uuid('organisation_uid')->unique();

            $table->string('name');

            /*
             * Lifecycle rather than a boolean. An organisation being suspended
             * is not the same as it being deleted, and a boolean cannot hold
             * the difference.
             */
            $table->string('status', 32)->default('active')->index();

            /*
             * The organisation's own region. Distinct from the approved data
             * geographies on the data protection profile: this is where the
             * customer is, that is where their data may be processed.
             */
            $table->string('region', 64)->nullable();

            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            /*
             * Nullable because a user row exists the moment Entra returns a
             * profile, which can be before any organisation is assigned. The
             * scope treats null as "no access", never as "all access", so a
             * nullable column cannot become a way around the boundary.
             */
            $table->foreignId('organisation_id')
                ->nullable()
                ->after('id')
                ->constrained('organisations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['organisation_id']);
            $table->dropColumn('organisation_id');
        });

        Schema::dropIfExists('organisations');
    }
};
