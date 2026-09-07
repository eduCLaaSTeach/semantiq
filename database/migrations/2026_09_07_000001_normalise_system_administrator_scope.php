<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ONE CANONICAL SHAPE FOR A PLATFORM-SCOPED ROLE.
 *
 * `system_administrator` is held across the platform, not within an
 * organisation, so its assignment must carry `organisation_id = NULL`.
 *
 * TWO PATHS WERE WRITING TWO SHAPES. RoleAssignmentService writes NULL for a
 * platform-scoped role, and always has. The D-49 data migration copied the
 * user's organisation_id onto the assignment it created, so the ONE
 * administrator a live deployment already had came out organisation-scoped
 * while every administrator granted afterwards came out platform-scoped. Found
 * by verify-access during deployment validation window A, on real production
 * data - not by any test, because no test constructed a migrated administrator
 * belonging to an organisation.
 *
 * IT WAS NOT A SECURITY DEFECT, AND THIS IS NOT A SECURITY FIX.
 * AccessEngine::holdsRole deliberately does not apply an organisation filter to
 * a platform-scoped role - "a category error rather than a tightening" - and
 * AdministratorSetGuard::effectiveCount() does not filter by organisation
 * either, so the administrator held the role platform-wide throughout and the
 * floor of one was never at risk. What was wrong was the SHAPE: one role with
 * two stored forms, where a later reader has to know which path wrote a row
 * before they can trust it.
 *
 * WHY A NEW MIGRATION RATHER THAN A CORRECTED D-49. The D-49 migration has run
 * in production. Editing it would make the deployed history describe something
 * that did not happen, and would silently do nothing on any database that had
 * already migrated. The correction is its own forward step, recorded as one.
 *
 * IT APPLIES TO ENDED ROWS TOO. A historical assignment is evidence, and
 * evidence in two shapes is evidence somebody has to interpret. Normalising
 * only the current row would leave the same ambiguity in the audit history,
 * which is the part nobody can re-derive later.
 *
 * WHAT IT DOES NOT TOUCH. No other role code. No user, domain, entitlement,
 * scope or ceiling row. It creates nothing and revokes nothing: `ended_at`,
 * `assigned_at`, `user_id` and `role_code` are untouched, so no role is
 * granted, no role is removed, and no count changes.
 */
return new class extends Migration
{
    private const ROLE = 'system_administrator';

    public function up(): void
    {
        if (! Schema::hasTable('role_assignments')) {
            return;
        }

        /*
         * IDEMPOTENT IN EFFECT, by the WHERE clause rather than by a flag.
         *
         * The second run matches no rows and updates nothing - including
         * updated_at, which would otherwise churn on every deployment and make
         * the audit trail say a row changed when it did not.
         *
         * Current AND ended rows: there is deliberately no ended_at filter.
         */
        DB::table('role_assignments')
            ->where('role_code', self::ROLE)
            ->whereNotNull('organisation_id')
            ->update([
                'organisation_id' => null,
                'updated_at' => now(),
            ]);
    }

    /**
     * DELIBERATELY A NO-OP, AND THAT IS THE SAFE CHOICE RATHER THAN THE LAZY
     * ONE.
     *
     * The only thing a truthful down() could do is put the organisation_id
     * back - which would recreate the invalid shape this migration exists to
     * remove, on the way to a rollback that is already an emergency. A rollback
     * must not reintroduce a defect.
     *
     * It is also not restorable in principle. The pre-correction value is not
     * stored anywhere by this migration, and reconstructing it from
     * users.organisation_id would be a guess: an administrator's own
     * organisation is not necessarily the value D-49 wrote, and after P1-03 a
     * person can move.
     *
     * NOTHING DEPENDS ON IT. Rolling back past this point unwinds D-49, whose
     * own down() reconstructs users.platform_role from the CURRENT assignment
     * state and reads only user_id and role_code - never organisation_id. So a
     * normalised row and an organisation-scoped row roll back identically, and
     * the D-49 rollback contract is unchanged. N-N10 proves that.
     */
    public function down(): void
    {
        // Intentionally empty. See the docblock: restoring the organisation
        // would recreate the invalid platform-scoped shape.
    }
};
