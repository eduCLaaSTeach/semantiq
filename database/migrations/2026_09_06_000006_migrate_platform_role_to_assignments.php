<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * D-49, MIGRATION 4 - THE DATA MIGRATION. The one that must not lose the only
 * administrator of a live deployment.
 *
 * Every users.platform_role = 'system_administrator' becomes ONE current role
 * assignment, carrying the user's organisation_id - which may legitimately be
 * NULL, because system_administrator is platform-scoped.
 *
 * ROLLBACK IS NOT A TIME MACHINE, and that is a Product Owner decision rather
 * than an implementation convenience. down() reconstructs users.platform_role
 * from the CURRENT assignment state:
 *
 *   - a current system_administrator assignment  -> 'system_administrator'
 *   - no current system_administrator assignment -> NULL
 *
 * It does NOT consult, store or restore the original pre-migration value. Once
 * migrated, assignments are the single source of truth, including when
 * unwinding. And down() must NOT refuse merely because assignments have changed
 * since up() ran: that refusal would make an emergency application rollback
 * impossible at exactly the moment rollback is needed.
 *
 * "CURRENT" HERE MEANS ended_at IS NULL, AND NOTHING ELSE. It does not require
 * the account to be active. P1-03 deactivation preserves access relationships,
 * so an INACTIVE user holding a current assignment has the role reconstructed
 * while users.status = inactive continues to prevent them signing in. This is
 * deliberately DIFFERENT from the lockout guard, which does filter by active
 * user because it asks a different question - "can anybody actually administer
 * this deployment?" Two questions, two filters, neither borrowed from the
 * other.
 *
 * REVERSIBLE IS NOT LOSSLESS. Rolling back before any P1-05 access
 * administration has been used loses nothing. Rolling back AFTER it has been
 * used loses what the old column cannot represent - multiple roles, domain
 * entitlements, scopes, sensitivity ceilings and all of P1-05's history - and
 * that case requires the approved backup and recovery procedure. See
 * doc/v2/phase-1/P1-05-DEPLOYMENT-NOTE.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'platform_role')) {
            return;
        }

        $now = now();

        $administrators = DB::table('users')
            ->select('id', 'organisation_id')
            ->where('platform_role', 'system_administrator')
            ->orderBy('id')
            ->get();

        foreach ($administrators as $administrator) {
            // Idempotent: re-running must not produce a second current
            // assignment for the same person. migrate:fresh on a database that
            // already ran this would otherwise double every administrator.
            $exists = DB::table('role_assignments')
                ->where('user_id', $administrator->id)
                ->where('role_code', 'system_administrator')
                ->whereNull('ended_at')
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('role_assignments')->insert([
                'user_id' => $administrator->id,
                'organisation_id' => $administrator->organisation_id,
                'role_code' => 'system_administrator',
                'assigned_at' => $now,
                'ended_at' => null,
                // No actor. The deployment migrated this, not a person, and
                // recording a person who did not do it would be a false entry.
                'assigned_by_user_id' => null,
                'ended_by_user_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'platform_role')) {
            // Migration 5 has not been rolled back yet, so there is no column to
            // reconstruct into. Refusing here would block the rollback; doing
            // nothing lets 5 roll back first and this run afterwards.
            return;
        }

        // Everyone loses the role, then those with a CURRENT assignment get it
        // back. Written this way round so that a person whose assignment was
        // revoked after the migration does NOT keep the column value - which is
        // exactly the "restore the original value" behaviour the Product Owner
        // rejected.
        DB::table('users')->update(['platform_role' => null]);

        $current = DB::table('role_assignments')
            ->where('role_code', 'system_administrator')
            ->whereNull('ended_at')
            ->pluck('user_id')
            ->all();

        if ($current === []) {
            return;
        }

        // users.status is deliberately untouched. Assignment state and account
        // status answer different questions and are never collapsed.
        DB::table('users')
            ->whereIn('id', $current)
            ->update(['platform_role' => 'system_administrator']);
    }
};
