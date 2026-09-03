<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Business domains - P1-04.
 *
 * THIS MIGRATION WRITES NO ROW. Structure is a migration's job; the seven
 * baseline domains are business rows and are created by an explicit,
 * idempotent initialisation that runs when an organisation exists and can be
 * named (D-46). A migration runs before any organisation exists, so it could
 * not set organisation_id even if seeding here were acceptable.
 *
 * NO owner_user_id COLUMN. Ownership lives entirely in business_domain_owners,
 * where the row with ended_at IS NULL is the current owner. A column here would
 * be a second writable record of one fact, able to disagree with the first,
 * with nothing in the schema to say which is right.
 *
 * NO sensitivity COLUMN, of any name. D-47 defers the entire sensitivity
 * dimension - Standard, Confidential, Restricted and the enforced ceilings - to
 * P1-05, and P1-04 does not pre-model it even inertly.
 *
 * access_expectation is ADVISORY. Nothing reads it to make a decision, and
 * DomainsBoundaryTest fails if anything ever does.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_domains', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('organisation_id');

            // The stable identity. Never editable, on any path. Every later
            // unit joins to this, so a mutable one would silently retarget
            // every reference - the same rule as users.external_subject.
            $table->string('code', 32);

            // The organisation's word for it. Freely editable (D-41): `sales`
            // may display as "Commercial" and remain the same domain.
            $table->string('name');

            $table->text('description')->nullable();

            $table->string('kind', 16);
            $table->string('status', 16);
            $table->string('access_expectation', 16)->default('undecided');

            $table->timestamps();

            $table->unique(['organisation_id', 'code'], 'business_domains_org_code_uq');
            $table->unique(['organisation_id', 'name'], 'business_domains_org_name_uq');

            $table->foreign('organisation_id', 'business_domains_org_fk')
                ->references('id')
                ->on('organisations');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_domains');
    }
};
