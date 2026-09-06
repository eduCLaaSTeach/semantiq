<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Modules\Access\Models\RoleAssignment;
use App\Modules\Access\Services\RoleAssignmentService;
use App\Modules\Access\Support\AccessViolation;
use App\Modules\Access\Support\RoleCode;
use App\Modules\Organisation\Models\Organisation;
use App\Modules\Platform\Bootstrap\BootstrapState;
use App\Modules\Platform\Models\User;
use App\Modules\Platform\Models\UserStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\OrganisationFactory;
use Tests\TestCase;

/**
 * N-M1 to N-M14. THE D-49 MIGRATION.
 *
 * The most dangerous change in Phase 1 so far, because getting it wrong locks
 * the only administrator out of a live deployment and bootstrap does not
 * reopen.
 *
 * migrate/rollback/migrate against MySQL runs in CI's MySQL job. What is
 * asserted here is the DATA CONTRACT: what up() produces, and what down()
 * reconstructs.
 */
final class MigrationTest extends TestCase
{
    use RefreshDatabase;

    private OrganisationFactory $make;

    protected function setUp(): void
    {
        parent::setUp();

        $this->make = new OrganisationFactory;
    }

    /**
     * N-M7. THE COLUMN AND THE ENUM ARE GONE.
     *
     * Not deprecated, not left readable - gone. A readable column is a SECOND
     * authority that can disagree with role_assignments, and the disagreement
     * appears the first time somebody updates one of them.
     */
    public function test_the_platform_role_column_and_enum_no_longer_exist(): void
    {
        $this->assertFalse(Schema::hasColumn('users', 'platform_role'));
        $this->assertFalse(class_exists('App\\Modules\\Platform\\Models\\PlatformRole'));
    }

    /**
     * N-M1 and N-M2. system_administrator is ACCEPTED with a NULL organisation;
     * every other role is REFUSED.
     *
     * The first half is what makes a fresh deployment bootstrappable at all -
     * requiring an organisation would mean the first administrator could never
     * be created, because the Company Profile does not exist yet.
     *
     * Mutation: require one (bootstrap breaks); permit a NULL for another role
     * (a role escapes its tenancy boundary).
     */
    public function test_only_the_platform_scoped_role_may_be_held_without_an_organisation(): void
    {
        $service = app(RoleAssignmentService::class);

        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);

        // Accepted, with NULL.
        $subject = $this->make->user($organisation);
        $assignment = $service->assign($subject, RoleCode::SystemAdministrator, null, $admin);

        $this->assertNull($assignment->organisation_id);

        // Refused, for every other role.
        $unassociated = $this->make->user();

        foreach (RoleCode::cases() as $role) {
            if ($role->isPlatformScoped()) {
                continue;
            }

            try {
                $service->assign($unassociated, $role, null, $admin);
                $this->fail("[{$role->value}] was granted with no organisation.");
            } catch (AccessViolation $violation) {
                $this->assertSame('organisation_required', $violation->reason);
            }
        }
    }

    /**
     * N-M4. BOOTSTRAP WORKS ON A GENUINELY EMPTY DEPLOYMENT.
     *
     * No organisation, no users, no assignments. The migration must leave that
     * state bootstrappable rather than merely not crash on it.
     */
    public function test_a_genuinely_empty_deployment_is_unconfigured_and_bootstrappable(): void
    {
        $this->assertSame(0, User::query()->count());
        $this->assertSame(0, RoleAssignment::query()->count());
        $this->assertSame(0, Organisation::query()->count());

        $this->assertTrue(app(BootstrapState::class)->isUnconfigured());
    }

    /**
     * N-M6 and N-M11 to N-M13. THE DATA CONTRACT, both directions.
     *
     * up(): one column row becomes EXACTLY ONE current assignment, carrying
     * the user's organisation_id - which may legitimately be NULL.
     *
     * down(): the column is reconstructed from the CURRENT assignment state,
     * never from a remembered original value, and never refuses because the
     * assignments have changed.
     *
     * The migrations are run directly against a rebuilt table, because the
     * suite's schema already has the column dropped.
     */
    public function test_the_migration_moves_one_column_row_to_exactly_one_assignment(): void
    {
        $organisation = $this->make->organisation();

        // Rebuild the pre-migration shape.
        Schema::table('users', function ($table): void {
            $table->string('platform_role', 32)->nullable();
        });

        $admin = $this->make->user($organisation);
        $other = $this->make->user($organisation);
        RoleAssignment::query()->delete();

        DB::table('users')->where('id', $admin->id)->update(['platform_role' => 'system_administrator']);

        $up = require database_path('migrations/2026_09_06_000006_migrate_platform_role_to_assignments.php');
        $up->up();

        $assignments = RoleAssignment::query()->where('role_code', 'system_administrator')->get();

        $this->assertCount(1, $assignments, 'One column row did not produce exactly one assignment.');
        $this->assertSame($admin->id, $assignments->first()->user_id);
        $this->assertSame($organisation->id, $assignments->first()->organisation_id);
        $this->assertNull($assignments->first()->ended_at);
        $this->assertNull($assignments->first()->assigned_by_user_id, 'A person was recorded as granting it.');

        // Idempotent: running it again produces no second assignment.
        $up->up();
        $this->assertSame(1, RoleAssignment::query()->where('role_code', 'system_administrator')->count());

        /*
         * ROLLBACK RESTORES CURRENT AUTHORITY, NOT THE ORIGINAL VALUE.
         *
         * The assignments are changed after the migration - the original
         * administrator is revoked and somebody else is granted. down() must
         * reconstruct from THAT, because once migrated the assignments are the
         * single source of truth. Restoring the pre-migration value is the
         * "time machine" behaviour the Product Owner rejected.
         */
        RoleAssignment::query()->update(['ended_at' => now()]);
        RoleAssignment::query()->create([
            'user_id' => $other->id,
            'organisation_id' => null,
            'role_code' => RoleCode::SystemAdministrator,
            'assigned_at' => now(),
        ]);

        $up->down();

        $this->assertNull(
            DB::table('users')->where('id', $admin->id)->value('platform_role'),
            'Rollback restored the ORIGINAL administrator, whose role had since been revoked. It '
            .'must reconstruct current authority, not the pre-migration value.'
        );

        $this->assertSame(
            'system_administrator',
            DB::table('users')->where('id', $other->id)->value('platform_role'),
            'Rollback did not restore the CURRENT administrator.'
        );
    }

    /**
     * N-M12 and N-M13. down() IGNORES ENDED ASSIGNMENTS, AND IGNORES ACCOUNT
     * STATUS.
     *
     * "Current" means ended_at IS NULL and nothing else. P1-03 deactivation
     * preserves access relationships, so an INACTIVE user holding a current
     * assignment HAS the role reconstructed - while users.status = inactive
     * continues to prevent them signing in.
     *
     * This is deliberately DIFFERENT from the lockout guard, which does filter
     * by active user because it asks a different question.
     *
     * Mutation: filter down() by user status - collapsing assignment state into
     * account status.
     */
    public function test_rollback_restores_an_inactive_holder_and_leaves_status_alone(): void
    {
        $organisation = $this->make->organisation();

        Schema::table('users', function ($table): void {
            $table->string('platform_role', 32)->nullable();
        });

        RoleAssignment::query()->delete();

        $inactive = $this->make->user($organisation);
        $formerly = $this->make->user($organisation);

        $inactive->forceFill(['status' => UserStatus::Inactive])->save();

        RoleAssignment::query()->create([
            'user_id' => $inactive->id,
            'organisation_id' => null,
            'role_code' => RoleCode::SystemAdministrator,
            'assigned_at' => now(),
        ]);

        // An ENDED assignment, which must not be restored as a current role.
        RoleAssignment::query()->create([
            'user_id' => $formerly->id,
            'organisation_id' => null,
            'role_code' => RoleCode::SystemAdministrator,
            'assigned_at' => now()->subDay(),
            'ended_at' => now()->subHour(),
        ]);

        $migration = require database_path('migrations/2026_09_06_000006_migrate_platform_role_to_assignments.php');
        $migration->down();

        $this->assertSame(
            'system_administrator',
            DB::table('users')->where('id', $inactive->id)->value('platform_role'),
            'An inactive user holding a CURRENT assignment did not have the role reconstructed. '
            .'Assignment state and account status are different questions.'
        );

        $this->assertSame(
            UserStatus::Inactive->value,
            DB::table('users')->where('id', $inactive->id)->value('status'),
            'Rollback altered users.status, which it must never touch.'
        );

        $this->assertNull(
            DB::table('users')->where('id', $formerly->id)->value('platform_role'),
            'An ENDED assignment was restored as a current role.'
        );
    }

    /**
     * N-M14. THE DEPLOYMENT NOTE STATES THAT ROLLBACK IS NOT LOSSLESS.
     *
     * The guard exists so the project cannot quietly come to promise reversible
     * business data. The old column cannot represent multiple roles,
     * entitlements, scopes, ceilings or history, and a rollback after P1-05
     * administration has been used therefore needs the approved backup
     * procedure.
     *
     * Mutation: delete the statement.
     */
    public function test_the_deployment_note_records_that_rollback_is_not_lossless(): void
    {
        $path = base_path('doc/v2/phase-1/P1-05-DEPLOYMENT-NOTE.md');

        $this->assertFileExists($path, 'The P1-05 deployment note is missing.');

        $note = (string) file_get_contents($path);

        foreach ([
            'not lossless',
            'entitlements',
            'scopes',
            'ceilings',
            'backup',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                mb_strtolower($note),
                "The deployment note does not mention [{$required}]. A rollback after P1-05 has "
                .'been used loses what the old column cannot represent, and saying so is the point.'
            );
        }

        // And the second redirect URI, without which step-up cannot work in
        // production at all.
        $this->assertStringContainsString(
            'auth/microsoft/step-up',
            $note,
            'The deployment note does not record that a second Entra redirect URI must be '
            .'registered. Step-up would fail on its first use in production.'
        );
    }
}
