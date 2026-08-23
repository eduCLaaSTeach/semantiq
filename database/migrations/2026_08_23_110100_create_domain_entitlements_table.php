<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which business domains an account may see.
 *
 * The second access dimension from doc/ROLE_MODEL.md section 1: a platform role
 * says what someone may do, an entitlement says which business information they
 * may do it to, and neither implies the other.
 *
 * A table rather than a column on `users`, for three reasons. Granting and
 * revoking are auditable events with their own timestamps. A future scope
 * qualifier - business unit, region, cost centre, all of which section 3 lists -
 * hangs off the row rather than forcing a nested structure into a JSON blob.
 * And "who can see Finance" is a question worth being able to ask directly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domain_entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            /* The BusinessDomain backing value. */
            $table->string('domain', 32);

            /*
             * Reserved for the constraints section 3 names: business unit,
             * team, geography, legal entity, cost centre. Null means the
             * entitlement is unqualified within the domain, which is the only
             * shape Phase 00 grants.
             */
            $table->json('scope')->nullable();

            $table->foreignId('granted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // One row per person per domain: granting twice is not two grants.
            $table->unique(['user_id', 'domain']);
            $table->index('domain');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domain_entitlements');
    }
};
