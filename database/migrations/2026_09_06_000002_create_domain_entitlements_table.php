<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which business domain a role assignment is entitled to participate in.
 *
 * A CHILD OF ONE ROLE ASSIGNMENT. That parentage is the whole point: ending the
 * assignment ends its current entitlements in the same transaction, so a role
 * revoked and re-granted months later for an unrelated reason does NOT bring
 * back a Finance entitlement somebody set in a different context, granted by
 * nobody, appearing in no change record. Orphan rows are the mechanism; the
 * foreign key removes it.
 *
 * NO ENTITLEMENT IS EVER CREATED AUTOMATICALLY - D-57. Not by domain ownership,
 * not by the domain_owner role, not by a group, not by a business unit. Every
 * row here was granted by somebody, and N-B15 breaks the shortcut.
 *
 * The domain being DISABLED does not end an entitlement. Disable is a state
 * change, not a revocation - P1-04 D-42 - so the row is retained and the engine
 * denies through the global gate. Re-enabling restores exactly the prior
 * access, because nothing was deleted to restore from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domain_entitlements', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('role_assignment_id');
            $table->foreignId('business_domain_id');

            $table->dateTime('granted_at');
            $table->dateTime('ended_at')->nullable();

            $table->unsignedBigInteger('granted_by_user_id')->nullable();
            $table->unsignedBigInteger('ended_by_user_id')->nullable();

            $table->timestamps();

            $table->index(['role_assignment_id', 'ended_at'], 'entitlements_assignment_ended_idx');
            $table->index(['business_domain_id', 'ended_at'], 'entitlements_domain_ended_idx');

            $table->foreign('role_assignment_id', 'entitlements_assignment_fk')
                ->references('id')
                ->on('role_assignments');

            $table->foreign('business_domain_id', 'entitlements_domain_fk')
                ->references('id')
                ->on('business_domains');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domain_entitlements');
    }
};
