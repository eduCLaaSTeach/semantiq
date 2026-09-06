<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Modules\Access\Models\DomainEntitlement;
use App\Modules\Access\Models\EntitlementCeiling;
use App\Modules\Access\Models\EntitlementScope;
use App\Modules\Access\Models\RoleAssignment;
use App\Modules\Access\Services\EntitlementService;
use App\Modules\Access\Services\RoleAssignmentService;
use App\Modules\Access\Support\AccessViolation;
use App\Modules\Access\Support\RoleCode;
use App\Modules\Access\Support\ScopeType;
use App\Modules\Access\Support\Sensitivity;
use App\Modules\Domains\Models\BusinessDomain;
use App\Modules\Domains\Models\DomainStatus;
use App\Modules\Organisation\Models\Organisation;
use App\Modules\Platform\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\Support\AccessFactory;
use Tests\Support\OrganisationFactory;
use Tests\TestCase;

/**
 * N-L1 to N-L13. HISTORICAL PARENTAGE.
 *
 * Ending a parent ends its CURRENT children, in one transaction. Re-granting a
 * parent creates NEW children and never revives old ones. Nothing is ever
 * deleted - the row is the evidence that somebody held access and when.
 */
final class LifecycleTest extends TestCase
{
    use RefreshDatabase;

    private OrganisationFactory $make;

    private AccessFactory $access;

    private Organisation $organisation;

    private BusinessDomain $finance;

    private User $person;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->make = new OrganisationFactory;
        $this->access = new AccessFactory;

        $this->organisation = $this->make->organisation();
        $this->finance = $this->access->domain($this->organisation);
        $this->person = $this->make->user($this->organisation);
        $this->actor = $this->make->user($this->organisation, administrator: true);
    }

    /**
     * REVOKING A ROLE ENDS EVERY CURRENT CHILD, IN ONE TRANSACTION.
     *
     * Mutation: leave the children orphaned. They would then return the day the
     * role is re-granted for an unrelated reason - with a scope somebody set in
     * a different context, granted by nobody, appearing in no change record.
     */
    public function test_revoking_a_role_ends_every_current_child_in_one_transaction(): void
    {
        $entitlement = $this->access->completePath($this->person, $this->finance, RoleCode::Manager);
        $assignment = $entitlement->assignment;

        $depths = [];
        DB::listen(function ($query) use (&$depths): void {
            if (str_contains(strtolower($query->sql), 'update')) {
                $depths[] = DB::transactionLevel();
            }
        });

        $baseline = DB::transactionLevel();

        app(RoleAssignmentService::class)->revoke($assignment, $this->actor);

        $this->assertNotEmpty($depths, 'No updates ran, so this test proves nothing.');

        foreach ($depths as $depth) {
            $this->assertGreaterThan(
                $baseline,
                $depth,
                'A child was ended outside the transaction, so a failure part-way through would '
                .'leave a role revoked with its entitlements still live.'
            );
        }

        $this->assertNotNull($assignment->fresh()->ended_at);
        $this->assertNotNull($entitlement->fresh()->ended_at);
        $this->assertNotNull(EntitlementScope::query()->sole()->ended_at);
        $this->assertNotNull(EntitlementCeiling::query()->sole()->ended_at);
    }

    /**
     * N-L9 and N-L10. RE-GRANTING RESURRECTS NOTHING.
     *
     * Two levels, asserted separately, because a developer fixing one would not
     * necessarily fix the other.
     */
    public function test_re_granting_a_parent_never_revives_its_old_children(): void
    {
        $roles = app(RoleAssignmentService::class);
        $entitlements = app(EntitlementService::class);

        $entitlement = $this->access->completePath($this->person, $this->finance, RoleCode::Manager);
        $roles->revoke($entitlement->assignment, $this->actor);

        // Level 1: the role comes back, and brings nothing with it.
        $reassigned = $roles->assign($this->person, RoleCode::Manager, $this->organisation->id, $this->actor);

        $this->assertSame(
            0,
            DomainEntitlement::query()->where('role_assignment_id', $reassigned->id)->count(),
            'Re-assigning a revoked role resurrected its old entitlements.'
        );

        // Level 2: the entitlement comes back, and brings no scope or ceiling.
        $regranted = $entitlements->grant($reassigned, $this->finance, $this->actor);

        $this->assertSame(
            0,
            EntitlementScope::query()->where('domain_entitlement_id', $regranted->id)->count(),
            'Re-granting a revoked entitlement resurrected its old scope.'
        );

        $this->assertSame(
            0,
            EntitlementCeiling::query()->where('domain_entitlement_id', $regranted->id)->count(),
            'Re-granting a revoked entitlement resurrected its old ceiling.'
        );

        // And the OLD rows are still there, ended. Nothing was deleted.
        $this->assertSame(2, DomainEntitlement::query()->count());
        $this->assertSame(1, EntitlementScope::query()->count());
    }

    /**
     * N-L12. DOMAIN DISABLE AND RE-ENABLE PRESERVE GRANTS EXACTLY.
     *
     * Disable is a state change, not a revocation - P1-04 D-42.
     *
     * Mutation: delete entitlements on disable. Re-enable would then restore to
     * a default, which is a grant nobody made.
     */
    public function test_disabling_and_re_enabling_a_domain_preserves_grants_exactly(): void
    {
        $entitlement = $this->access->completePath($this->person, $this->finance, RoleCode::Manager);

        $before = [
            $entitlement->fresh()->getAttributes(),
            EntitlementScope::query()->sole()->getAttributes(),
            EntitlementCeiling::query()->sole()->getAttributes(),
        ];

        $this->finance->forceFill(['status' => DomainStatus::Disabled])->save();
        $this->finance->forceFill(['status' => DomainStatus::Enabled])->save();

        $after = [
            $entitlement->fresh()->getAttributes(),
            EntitlementScope::query()->sole()->getAttributes(),
            EntitlementCeiling::query()->sole()->getAttributes(),
        ];

        $this->assertSame($before, $after, 'Disabling and re-enabling a domain altered a grant.');
    }

    /**
     * N-L2. REPLACE IS ONE TRANSACTION.
     *
     * Never revoke-then-assign as two requests: that leaves a window with no
     * access, and a partial state if the second fails.
     */
    public function test_replacing_a_role_is_one_transaction(): void
    {
        $assignment = $this->access->assignment($this->person, RoleCode::BusinessUser, $this->organisation);

        $baseline = DB::transactionLevel();
        $depths = [];

        DB::listen(function ($query) use (&$depths): void {
            if (str_contains(strtolower($query->sql), 'insert') || str_contains(strtolower($query->sql), 'update')) {
                $depths[] = DB::transactionLevel();
            }
        });

        $replacement = app(RoleAssignmentService::class)->replace($assignment, RoleCode::Manager, $this->actor);

        foreach ($depths as $depth) {
            $this->assertGreaterThan($baseline, $depth, 'A replace step ran outside the transaction.');
        }

        $this->assertNotNull($assignment->fresh()->ended_at);
        $this->assertSame(RoleCode::Manager, $replacement->role_code);
        $this->assertTrue($replacement->isCurrent());
    }

    /**
     * N-L3. TWO PERIODS ON ONE CALENDAR DAY ARE BOTH RECORDED.
     *
     * The P1-01 collision, prevented at the schema: assigned_at is DATETIME and
     * nothing is unique on it. Grant, revoke and grant again within a day must
     * produce two distinguishable periods.
     */
    public function test_two_assignment_periods_on_one_day_are_both_kept(): void
    {
        $roles = app(RoleAssignmentService::class);

        $first = $roles->assign($this->person, RoleCode::Manager, $this->organisation->id, $this->actor);
        $roles->revoke($first, $this->actor);
        $second = $roles->assign($this->person, RoleCode::Manager, $this->organisation->id, $this->actor);

        $this->assertSame(
            2,
            RoleAssignment::query()->where('user_id', $this->person->id)->where('role_code', 'manager')->count(),
            'A second assignment period on the same day was not recorded.'
        );

        $this->assertNotSame($first->id, $second->id);
        $this->assertNotNull($first->fresh()->ended_at);
        $this->assertTrue($second->isCurrent());
    }

    /**
     * The same role cannot be held TWICE at once.
     *
     * Two current assignments of one role to one person make revocation
     * ambiguous and the screen wrong, and grant nothing the first does not.
     */
    public function test_the_same_role_cannot_be_held_twice_at_once(): void
    {
        $roles = app(RoleAssignmentService::class);

        $roles->assign($this->person, RoleCode::Manager, $this->organisation->id, $this->actor);

        $this->expectException(AccessViolation::class);

        $roles->assign($this->person, RoleCode::Manager, $this->organisation->id, $this->actor);
    }

    /**
     * N-L1. NO ROUTE DELETES AN ASSIGNMENT, ENTITLEMENT, SCOPE OR CEILING.
     *
     * Every removal ends a period, so every one is a PATCH. In this codebase
     * DELETE means a record is permanently destroyed, and the complete DELETE
     * set is asserted elsewhere - a DELETE here would both misdescribe the
     * operation and weaken that assertion.
     *
     * Mutation: add one.
     */
    public function test_no_access_route_registers_a_delete(): void
    {
        $found = [];

        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'console/access')) {
                continue;
            }

            if (in_array('DELETE', $route->methods(), true)) {
                $found[] = $route->uri();
            }
        }

        $this->assertSame(
            [],
            $found,
            'A DELETE was registered under Roles & Access. Nothing here destroys a record - every '
            .'removal ends a period, and the history is the point.'
        );
    }

    /**
     * N-L13. NO COLUMN CACHES AN EFFECTIVE-ACCESS ANSWER.
     *
     * Mutation: add `effective_domains` to users. A cached answer is a second
     * source of truth that goes stale the moment anything is revoked.
     */
    public function test_no_column_caches_an_effective_access_answer(): void
    {
        foreach (['users', 'role_assignments', 'domain_entitlements', 'entitlement_scopes'] as $table) {
            foreach (Schema::getColumnListing($table) as $column) {
                foreach (['effective', 'cached', 'computed_access', 'can_'] as $forbidden) {
                    $this->assertStringNotContainsString(
                        $forbidden,
                        $column,
                        "[{$table}.{$column}] looks like a cached access answer. The engine decides "
                        .'on every request; a column that remembers an answer goes stale.'
                    );
                }
            }
        }
    }

    /**
     * CLEARING A CEILING WRITES A STANDARD ROW. It does not delete.
     *
     * N-C8. Absence is a fault that fails closed; "cleared" is a deliberate act
     * that leaves evidence.
     */
    public function test_clearing_a_ceiling_writes_a_standard_row_rather_than_deleting(): void
    {
        $entitlements = app(EntitlementService::class);

        $assignment = $this->access->assignment($this->person, RoleCode::Manager, $this->organisation);
        $entitlement = $this->access->entitlement($assignment, $this->finance);

        $entitlements->setCeiling($entitlement, Sensitivity::Confidential, $this->actor);
        $entitlements->setCeiling($entitlement, Sensitivity::Standard, $this->actor);

        $current = EntitlementCeiling::query()
            ->where('domain_entitlement_id', $entitlement->id)
            ->whereNull('ended_at')
            ->sole();

        $this->assertSame(Sensitivity::Standard, $current->sensitivity);

        // And the previous period is kept, ended.
        $this->assertSame(2, EntitlementCeiling::query()->count());
    }

    /**
     * A SCOPE CANNOT BE ASSIGNED TO A REVOKED ENTITLEMENT, and an entitlement
     * cannot be granted to a revoked assignment.
     *
     * Parentage in the other direction: a child cannot be added under a parent
     * that has already ended.
     */
    public function test_a_child_cannot_be_added_under_an_ended_parent(): void
    {
        $entitlements = app(EntitlementService::class);
        $roles = app(RoleAssignmentService::class);

        $entitlement = $this->access->completePath($this->person, $this->finance, RoleCode::Manager);
        $assignment = $entitlement->assignment;

        $entitlements->revoke($entitlement, $this->actor);

        try {
            $entitlements->assignScope($entitlement->fresh(), ScopeType::Organisation, null, $this->actor);
            $this->fail('A scope was assigned to a revoked entitlement.');
        } catch (AccessViolation $violation) {
            $this->assertSame('entitlement_not_current', $violation->reason);
        }

        $roles->revoke($assignment, $this->actor);

        try {
            $entitlements->grant($assignment->fresh(), $this->finance, $this->actor);
            $this->fail('An entitlement was granted under a revoked role assignment.');
        } catch (AccessViolation $violation) {
            $this->assertSame('role_not_held', $violation->reason);
        }
    }
}
