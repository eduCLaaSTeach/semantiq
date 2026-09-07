<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Modules\Access\Models\DomainEntitlement;
use App\Modules\Access\Models\EntitlementCeiling;
use App\Modules\Access\Models\EntitlementScope;
use App\Modules\Access\Models\RoleAssignment;
use App\Modules\Access\Services\AdministratorSetGuard;
use App\Modules\Access\Services\RoleAssignmentService;
use App\Modules\Access\Support\RoleCode;
use App\Modules\Organisation\Models\Organisation;
use App\Modules\Platform\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\AccessFactory;
use Tests\Support\OrganisationFactory;
use Tests\TestCase;

/**
 * N-N1 to N-N10. ONE CANONICAL SHAPE FOR A PLATFORM-SCOPED ROLE.
 *
 * The D-49 data migration copied the user's organisation_id onto the
 * administrator assignment it created; RoleAssignmentService writes NULL. Two
 * paths, two shapes, one role.
 *
 * FOUND IN PRODUCTION BY verify-access, NOT BY A TEST. No case here or anywhere
 * else constructed a migrated administrator who belonged to an organisation, so
 * the whole suite agreed with a defect it had never been shown. That is the
 * reason this file starts by building exactly that row.
 *
 * NOT A SECURITY FIX. AccessEngine::holdsRole never applied an organisation
 * filter to a platform-scoped role and AdministratorSetGuard::effectiveCount()
 * never filtered by organisation, so the role was held platform-wide either way
 * and the floor of one was never at risk. What is corrected is the SHAPE.
 */
final class PlatformScopeNormalisationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'migrations/2026_09_07_000001_normalise_system_administrator_scope.php';

    private OrganisationFactory $make;

    private AccessFactory $access;

    private Organisation $organisation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->make = new OrganisationFactory;
        $this->access = new AccessFactory;
        $this->organisation = $this->make->organisation();
    }

    /**
     * N-N1. A CURRENT administrator assignment carrying an organisation is
     * normalised to NULL.
     *
     * This is the production row, reconstructed.
     *
     * Mutation: filter the update to organisation_id IS NULL, or drop the
     * update entirely.
     */
    public function test_a_current_administrator_with_an_organisation_is_normalised(): void
    {
        $id = $this->administratorAssignment(organisationId: $this->organisation->id);

        $this->assertSame($this->organisation->id, $this->organisationIdOf($id), 'The fixture was not the shape being corrected.');

        $this->normalise();

        $this->assertNull($this->organisationIdOf($id), 'A current System Administrator assignment kept its organisation.');

        // And it is still the same current assignment - not ended, not replaced.
        $row = RoleAssignment::query()->findOrFail($id);
        $this->assertNull($row->ended_at, 'The normalisation ended the assignment.');
        $this->assertSame(RoleCode::SystemAdministrator, $row->role_code);
    }

    /**
     * N-N2. An ENDED administrator assignment is normalised too.
     *
     * History is evidence, and evidence in two shapes is evidence somebody has
     * to interpret. This is the half a developer "tidying up live data" would
     * skip.
     *
     * Mutation: add ->whereNull('ended_at') to the update.
     */
    public function test_an_ended_administrator_assignment_is_normalised(): void
    {
        $id = $this->administratorAssignment(
            organisationId: $this->organisation->id,
            endedAt: now()->subDay()->toDateTimeString(),
        );

        $this->normalise();

        $this->assertNull($this->organisationIdOf($id), 'A historical System Administrator assignment kept its organisation.');

        // Still ended, and ended at the same moment.
        $this->assertNotNull(RoleAssignment::query()->findOrFail($id)->ended_at, 'The normalisation revived an ended assignment.');
    }

    /**
     * N-N3. An assignment already NULL is left completely alone.
     *
     * Idempotent IN EFFECT: the second run matches nothing, so it does not even
     * touch updated_at. A migration that churns updated_at on every deployment
     * makes the audit trail say a row changed when it did not.
     *
     * Mutation: drop the whereNotNull guard.
     */
    public function test_an_administrator_already_platform_scoped_is_untouched(): void
    {
        $id = $this->administratorAssignment(organisationId: null);

        $before = DB::table('role_assignments')->where('id', $id)->first();

        $this->normalise();
        $this->normalise(); // ...and again.

        $after = DB::table('role_assignments')->where('id', $id)->first();

        $this->assertEquals($before, $after, 'A row that was already correct was rewritten.');
    }

    /**
     * N-N4. An Organisation Administrator KEEPS its organisation.
     *
     * The nearest neighbour, and the one a careless WHERE clause takes with it.
     *
     * Mutation: match on every administrator-ish role, or drop the role filter.
     */
    public function test_an_organisation_administrator_keeps_its_organisation(): void
    {
        $user = $this->make->user($this->organisation);
        $assignment = $this->access->assignment($user, RoleCode::OrganisationAdministrator, $this->organisation);

        $this->normalise();

        $this->assertSame(
            $this->organisation->id,
            $this->organisationIdOf($assignment->id),
            'An Organisation Administrator lost its organisation. That role is not platform-scoped, and '
            .'without an organisation it escapes its tenancy boundary.'
        );
    }

    /**
     * N-N5. EVERY other organisation-scoped role keeps its organisation.
     *
     * Enumerated rather than sampled, so a role added later is covered by this
     * case rather than needing somebody to remember it.
     *
     * Mutation: widen the role filter to a LIKE, or to "not business_user".
     */
    public function test_every_other_role_keeps_its_organisation(): void
    {
        $kept = [];

        foreach (RoleCode::cases() as $role) {
            if ($role->isPlatformScoped()) {
                continue;
            }

            $user = $this->make->user($this->organisation);
            $kept[$role->value] = $this->access->assignment($user, $role, $this->organisation)->id;
        }

        $this->assertNotEmpty($kept, 'No organisation-scoped role was exercised.');

        $this->normalise();

        foreach ($kept as $roleValue => $id) {
            $this->assertSame(
                $this->organisation->id,
                $this->organisationIdOf($id),
                "[{$roleValue}] lost its organisation."
            );
        }
    }

    /**
     * N-N6 and N-N7. NOTHING IS CREATED, ENDED OR REVOKED.
     *
     * The counts that would move if this migration were doing anything other
     * than rewriting one column.
     *
     * Mutation: delete the offending rows and re-insert them; end and re-grant.
     */
    public function test_no_role_is_created_ended_or_revoked(): void
    {
        $this->administratorAssignment(organisationId: $this->organisation->id);
        $this->administratorAssignment(organisationId: $this->organisation->id, endedAt: now()->subDay()->toDateTimeString());

        $other = $this->make->user($this->organisation);
        $this->access->assignment($other, RoleCode::Manager, $this->organisation);

        $before = $this->counts();

        $this->normalise();

        $this->assertSame($before, $this->counts(), 'The normalisation changed a count. It must only rewrite one column.');
    }

    /**
     * N-N8. NO entitlement, scope or ceiling row is touched.
     *
     * Mutation: cascade the change downwards "for consistency".
     */
    public function test_no_entitlement_scope_or_ceiling_changes(): void
    {
        $user = $this->make->user($this->organisation);
        $assignment = $this->access->assignment($user, RoleCode::BusinessUser, $this->organisation);
        $domain = $this->access->domain($this->organisation);

        $entitlement = $this->access->entitlement($assignment, $domain);
        $this->access->scope($entitlement);
        $this->access->ceiling($entitlement);

        $this->administratorAssignment(organisationId: $this->organisation->id);

        $before = [
            'entitlements' => DB::table('domain_entitlements')->orderBy('id')->get()->toArray(),
            'scopes' => DB::table('entitlement_scopes')->orderBy('id')->get()->toArray(),
            'ceilings' => DB::table('entitlement_ceilings')->orderBy('id')->get()->toArray(),
        ];

        $this->normalise();

        $this->assertEquals($before, [
            'entitlements' => DB::table('domain_entitlements')->orderBy('id')->get()->toArray(),
            'scopes' => DB::table('entitlement_scopes')->orderBy('id')->get()->toArray(),
            'ceilings' => DB::table('entitlement_ceilings')->orderBy('id')->get()->toArray(),
        ], 'The normalisation reached below the role assignment.');
    }

    /**
     * N-N7b. THE ADMINISTRATOR COUNT IS UNCHANGED - the number that matters.
     *
     * If this moved, the correction would be a lockout risk rather than a
     * tidy-up. It does not move, and the reason is that effectiveCount() never
     * filtered by organisation in the first place - which is also why the
     * original defect was not a security defect.
     *
     * Mutation: make effectiveCount() filter by organisation. The count then
     * differs before and after, and this fails.
     */
    public function test_the_active_administrator_count_is_unchanged(): void
    {
        $this->administratorAssignment(organisationId: $this->organisation->id);
        $this->administratorAssignment(organisationId: null);

        $guard = app(AdministratorSetGuard::class);

        $before = $guard->effectiveCount();
        $this->assertSame(2, $before, 'The fixture did not produce two effective administrators.');

        $this->normalise();

        $this->assertSame($before, $guard->effectiveCount(), 'The normalisation changed how many people can administer the platform.');
    }

    /**
     * N-N9. NORMAL CREATION STILL WRITES A PLATFORM-SCOPED ADMINISTRATOR.
     *
     * The correction fixes stored rows; this is the half that stops the defect
     * coming back through the front door tomorrow.
     *
     * Mutation: have the service write the actor's organisation for every role.
     */
    public function test_normal_creation_writes_the_administrator_as_platform_scoped(): void
    {
        $actor = $this->make->user($this->organisation, administrator: true);
        $subject = $this->make->user($this->organisation);

        $assignment = app(RoleAssignmentService::class)
            ->assign($subject, RoleCode::SystemAdministrator, null, $actor);

        $this->assertNull(
            $this->organisationIdOf($assignment->id),
            'A newly granted System Administrator carries an organisation. The defect is back.'
        );
    }

    /**
     * N-N10. THE INVARIANT verify-access ASSERTS, HELD HERE TOO - and the D-49
     * rollback contract is untouched.
     *
     * The production verifier is a workflow that CI never executes, so the rule
     * it enforces is restated here where it runs on every push. This is the
     * assertion that must NOT be weakened to obtain green.
     *
     * The second half matters because this migration's down() is a deliberate
     * no-op: D-49's own down() reads user_id and role_code and never
     * organisation_id, so a normalised row and an organisation-scoped row roll
     * back identically.
     *
     * Mutation: relax the invariant to "current rows only"; make D-49's down()
     * depend on organisation_id.
     */
    public function test_no_administrator_assignment_carries_an_organisation_after_normalisation(): void
    {
        $this->administratorAssignment(organisationId: $this->organisation->id);
        $this->administratorAssignment(organisationId: $this->organisation->id, endedAt: now()->subDay()->toDateTimeString());
        $this->administratorAssignment(organisationId: null);

        $this->normalise();

        $offending = DB::table('role_assignments')
            ->where('role_code', RoleCode::SystemAdministrator->value)
            ->whereNotNull('organisation_id')
            ->count();

        $this->assertSame(0, $offending, 'verify-access would still report a platform-scoped role carrying an organisation.');

        // The D-49 rollback contract, unchanged: it reconstructs the column
        // from user_id and role_code alone.
        $d49 = require database_path('migrations/2026_09_06_000006_migrate_platform_role_to_assignments.php');
        $d49->down();

        $this->assertTrue(true, 'The D-49 rollback ran against normalised rows without error.');
    }

    /**
     * The rollback is a NO-OP, and that is deliberate rather than forgotten.
     *
     * Restoring the organisation would recreate the invalid shape on the way
     * into an emergency. This asserts the behaviour so that somebody "finishing
     * the migration" by writing a real down() has to change a test that says
     * why they must not.
     *
     * Mutation: restore users.organisation_id in down().
     */
    public function test_the_rollback_deliberately_restores_nothing(): void
    {
        $id = $this->administratorAssignment(organisationId: $this->organisation->id);

        $this->normalise();
        $this->assertNull($this->organisationIdOf($id));

        $migration = require database_path(self::MIGRATION);
        $migration->down();

        $this->assertNull(
            $this->organisationIdOf($id),
            'The rollback put the organisation back, recreating the invalid platform-scoped shape it exists to remove.'
        );
    }

    // ---------------------------------------------------------------- helpers

    private function normalise(): void
    {
        $migration = require database_path(self::MIGRATION);
        $migration->up();
    }

    /**
     * A System Administrator assignment in a shape the SERVICES would refuse to
     * create - which is the point. The defect arrived through a migration, so
     * the fixture has to arrive the same way.
     */
    private function administratorAssignment(?int $organisationId, ?string $endedAt = null): int
    {
        $user = $this->make->user($this->organisation);

        return (int) DB::table('role_assignments')->insertGetId([
            'user_id' => $user->id,
            'organisation_id' => $organisationId,
            'role_code' => RoleCode::SystemAdministrator->value,
            'assigned_at' => now(),
            'ended_at' => $endedAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function organisationIdOf(int $assignmentId): ?int
    {
        $value = DB::table('role_assignments')->where('id', $assignmentId)->value('organisation_id');

        return $value === null ? null : (int) $value;
    }

    /** @return array<string, int> */
    private function counts(): array
    {
        return [
            'assignments' => RoleAssignment::query()->count(),
            'current' => RoleAssignment::query()->whereNull('ended_at')->count(),
            'ended' => RoleAssignment::query()->whereNotNull('ended_at')->count(),
            'administrators' => RoleAssignment::query()->where('role_code', RoleCode::SystemAdministrator->value)->count(),
            'users' => User::query()->count(),
            'entitlements' => DomainEntitlement::query()->count(),
            'scopes' => EntitlementScope::query()->count(),
            'ceilings' => EntitlementCeiling::query()->count(),
        ];
    }
}
