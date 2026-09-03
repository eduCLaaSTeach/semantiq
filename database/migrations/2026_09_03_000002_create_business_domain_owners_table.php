<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Domain ownership periods - and the AUTHORITATIVE record of who owns a domain.
 *
 * ended_at IS NULL means current. No such row means nobody is accountable.
 * There is no owner column on business_domains to disagree with this.
 *
 * TWO DECISIONS TAKEN FROM P1-03's CORRECTION 4, at the schema rather than
 * after the fact:
 *
 *   1. assigned_at and ended_at are DATETIME, not DATE. Two genuine ownership
 *      periods on one calendar day are distinguishable by their boundaries.
 *      P1-01 keyed team membership on date-valued timing, could not represent
 *      hand-over-and-hand-back in a day, and refused the second row with an
 *      integrity error the administrator did nothing to cause.
 *
 *   2. There is NO uniqueness involving assigned_at. The invariant worth
 *      enforcing is "at most one CURRENT owner", not "no two periods share a
 *      start".
 *
 * MySQL 8.4 has no partial index, so that invariant cannot be declared here. It
 * is enforced by locking reads inside the write transaction - and the lock is
 * taken on the PARENT business_domains ROW FIRST.
 *
 * NOT for the reason first written here. The claim was that with no current
 * owner there is no ownership row to lock, so nothing is held; measured against
 * MySQL 8.4 that is FALSE, because InnoDB gap-locks the empty range under
 * REPEATABLE READ. The parent row is the boundary because every operation
 * decides from the domain's STATUS and its OWNERSHIP together - and because the
 * gap lock depends on this very index and on the isolation level, neither of
 * which is visible to anyone reading the service.
 *
 * Lock order everywhere: DOMAIN -> OWNERSHIP -> DEPENDENCY CHECKS.
 *
 * An ownership row is never deleted. Ending sets ended_at. The row is the
 * evidence that somebody was once accountable, which is the only reason to keep
 * ownership history rather than a single current-owner field.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_domain_owners', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_domain_id');
            $table->foreignId('user_id');

            // Timestamps, not dates. See the note above.
            $table->dateTime('assigned_at');
            $table->dateTime('ended_at')->nullable();

            $table->timestamps();

            // The current-owner lookup, which every screen and every guard runs.
            $table->index(['business_domain_id', 'ended_at'], 'domain_owners_domain_ended_idx');
            $table->index('user_id', 'domain_owners_user_idx');

            $table->foreign('business_domain_id', 'domain_owners_domain_fk')
                ->references('id')
                ->on('business_domains');

            $table->foreign('user_id', 'domain_owners_user_fk')
                ->references('id')
                ->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_domain_owners');
    }
};
