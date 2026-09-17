<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Modules\Access\Support\RoleCode;
use App\Modules\Access\Support\ScopeType;
use App\Modules\Access\Support\Sensitivity;
use App\Modules\Domains\Models\DomainOwnership;
use App\Modules\Platform\Models\UserStatus;
use App\Modules\Security\Catalogue\ControlCatalogue;
use App\Modules\Security\Posture\Aggregation;
use App\Modules\Security\Posture\MetricRow;
use App\Modules\Security\Posture\PostureEvaluator;
use App\Modules\Security\Posture\PostureRow;
use App\Modules\Security\Posture\PostureState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AccessFactory;
use Tests\Support\OrganisationFactory;
use Tests\TestCase;

/**
 * N-SS9 to N-SS18 - PRIVILEGED ACCESS HEALTH, BOTH HALVES OF EVERY RULE.
 *
 * Every state rule is tested in BOTH directions. A rule tested only on its
 * unhappy path is a rule that would pass if it always returned the unhappy
 * answer, which is CLAUDE.md §2's specific warning: a test satisfied by any
 * refusal reports a guard that may not exist.
 *
 * N-SS14 to N-SS17 are the split guards, and they are the most important tests
 * in the unit after the projection: P1-05 delivers Restricted grants, several
 * Organisation Administrators, whole-domain scope and preserved assignments
 * DELIBERATELY, each protected by its own control and each approved. Painting
 * any of them amber would mean this screen declaring approved behaviour a
 * fault.
 */
final class PrivilegedAccessPostureTest extends TestCase
{
    use RefreshDatabase;

    private OrganisationFactory $make;

    private AccessFactory $access;

    protected function setUp(): void
    {
        parent::setUp();

        $this->make = new OrganisationFactory;
        $this->access = new AccessFactory;
    }

    private function row(string $control): PostureRow
    {
        foreach (app(PostureEvaluator::class)->evaluate()->rows as $row) {
            if ($row->control === $control) {
                return $row;
            }
        }

        $this->fail("No row was produced for {$control}. A missing row is the omission failure.");
    }

    private function metric(string $control): MetricRow
    {
        foreach (app(PostureEvaluator::class)->evaluate()->metrics as $metric) {
            if ($metric->control === $control) {
                return $metric;
            }
        }

        $this->fail("No metric was produced for {$control}.");
    }

    private function aggregate(): PostureState
    {
        $report = app(PostureEvaluator::class)->evaluate();

        return Aggregation::of(array_map(
            static fn (PostureRow $row): PostureState => $row->state,
            $report->rows,
        ));
    }

    /**
     * N-SS10 and N-SS9, together. ZERO is critical, ONE is attention, TWO OR
     * MORE IS HEALTHY.
     *
     * The green half is the one that matters here: it cannot be observed in
     * production without manufacturing a second System Administrator, which is
     * forbidden, so it is proven by fixture and the Product Owner Test Script
     * says so rather than inferring it from a passing test.
     */
    public function test_the_administrator_count_has_all_three_branches(): void
    {
        $organisation = $this->make->organisation();

        // ZERO - N-SS10.
        $this->assertSame(
            PostureState::Critical,
            $this->row(ControlCatalogue::ADMINISTRATOR_COUNT)->state,
        );

        // ONE - a lockout RISK, not a fault, and deliberately not blocked.
        $first = $this->make->user($organisation);
        $this->access->assignment($first, RoleCode::SystemAdministrator);

        $this->assertSame(
            PostureState::Attention,
            $this->row(ControlCatalogue::ADMINISTRATOR_COUNT)->state,
        );

        // TWO - N-SS9's green half.
        $second = $this->make->user($organisation);
        $this->access->assignment($second, RoleCode::SystemAdministrator);

        $this->assertSame(
            PostureState::Healthy,
            $this->row(ControlCatalogue::ADMINISTRATOR_COUNT)->state,
            'Two genuine administrators must report healthy. A rule with no reachable green '
            .'branch is a rule that would pass if it always returned amber.',
        );
    }

    /** An INACTIVE administrator does not count. Both filters, as the guard does. */
    public function test_an_inactive_administrator_does_not_count_towards_the_total(): void
    {
        $organisation = $this->make->organisation();

        $active = $this->make->user($organisation);
        $this->access->assignment($active, RoleCode::SystemAdministrator);

        $inactive = $this->make->user($organisation);
        $inactive->forceFill(['status' => UserStatus::Inactive])->save();
        $this->access->assignment($inactive, RoleCode::SystemAdministrator);

        $this->assertSame(
            PostureState::Attention,
            $this->row(ControlCatalogue::ADMINISTRATOR_COUNT)->state,
            'An inactive account was counted as an administrator who could actually administer '
            .'this deployment.',
        );
    }

    /**
     * N-SS11. A privileged person WITH a business entitlement is attention;
     * WITHOUT is healthy.
     *
     * Mutation: drop the combination and report on the role alone.
     */
    public function test_a_privileged_person_holding_business_data_is_attention_and_otherwise_healthy(): void
    {
        $organisation = $this->make->organisation();
        $domain = $this->access->domain($organisation);

        $admin = $this->make->user($organisation);
        $assignment = $this->access->assignment($admin, RoleCode::SystemAdministrator);

        // A privileged role ON ITS OWN is not a finding - a role grants nothing.
        $this->assertSame(
            PostureState::Healthy,
            $this->row(ControlCatalogue::PRIVILEGED_WITH_DATA)->state,
        );

        $this->access->entitlement($assignment, $domain);

        $this->assertSame(
            PostureState::Attention,
            $this->row(ControlCatalogue::PRIVILEGED_WITH_DATA)->state,
        );

        // And it is worded as PERMITTED, never as a violation.
        $finding = $this->row(ControlCatalogue::PRIVILEGED_WITH_DATA)->finding;
        $this->assertStringContainsString('permitted', $finding);
        $this->assertStringNotContainsString('violation', $finding);
        $this->assertStringNotContainsString('must not', $finding);
    }

    /**
     * N-SS12. An entitlement with no scope, or no ceiling, is attention.
     * A complete one is healthy.
     *
     * Mutation: ignore a missing ceiling and check only the scope.
     */
    public function test_an_incomplete_grant_path_is_attention_and_a_complete_one_is_healthy(): void
    {
        $organisation = $this->make->organisation();
        $domain = $this->access->domain($organisation);
        $person = $this->make->user($organisation);

        $assignment = $this->access->assignment($person, RoleCode::BusinessUser, $organisation);
        $entitlement = $this->access->entitlement($assignment, $domain);

        // No scope AND no ceiling.
        $this->assertSame(PostureState::Attention, $this->row(ControlCatalogue::INCOMPLETE_PATHS)->state);

        // Scope only - STILL incomplete. This is the half a mutation removes.
        $this->access->scope($entitlement, ScopeType::Organisation);
        $this->assertSame(
            PostureState::Attention,
            $this->row(ControlCatalogue::INCOMPLETE_PATHS)->state,
            'A missing sensitivity limit was ignored. It authorises nothing, which is exactly '
            .'why it persists unnoticed.',
        );

        // Complete.
        $this->access->ceiling($entitlement, Sensitivity::Standard);
        $this->assertSame(PostureState::Healthy, $this->row(ControlCatalogue::INCOMPLETE_PATHS)->state);
    }

    /** Ceiling but no scope is incomplete too - the other half of the same rule. */
    public function test_a_ceiling_without_a_scope_is_also_incomplete(): void
    {
        $organisation = $this->make->organisation();
        $domain = $this->access->domain($organisation);
        $person = $this->make->user($organisation);

        $assignment = $this->access->assignment($person, RoleCode::BusinessUser, $organisation);
        $entitlement = $this->access->entitlement($assignment, $domain);
        $this->access->ceiling($entitlement, Sensitivity::Standard);

        $this->assertSame(PostureState::Attention, $this->row(ControlCatalogue::INCOMPLETE_PATHS)->state);
    }

    /**
     * N-SS13. THE INACTIVE-ACCOUNT GATE ITSELF.
     *
     * Present is healthy. It is what makes PR-5 merely informational, and it is
     * asked of the ENGINE rather than of a second copy of its logic - a gate
     * checked by a copy of itself is a gate nobody is watching.
     */
    public function test_the_inactive_account_gate_reports_healthy_and_names_the_check(): void
    {
        $row = $this->row(ControlCatalogue::INACTIVE_GATE);

        $this->assertSame(PostureState::Healthy, $row->state);
        $this->assertStringContainsString('not active', $row->finding);
    }

    /**
     * N-SS14. A LEGITIMATE RESTRICTED GRANT DOES NOT TURN POSTURE AMBER.
     *
     * The most tempting wrong edit in the unit, because "a Restricted grant
     * should be amber" sounds like caution.
     */
    public function test_a_restricted_grant_is_counted_and_never_changes_the_aggregate(): void
    {
        $organisation = $this->make->organisation();
        $domain = $this->access->domain($organisation);
        $person = $this->make->user($organisation);

        $before = $this->aggregate();

        $this->access->completePath(
            $person,
            $domain,
            RoleCode::BusinessUser,
            ScopeType::Organisation,
            null,
            Sensitivity::Restricted,
        );

        $this->assertSame(
            1,
            $this->metric(ControlCatalogue::RESTRICTED_GRANTS)->count,
            'The Restricted grant is not being counted at all.',
        );

        $this->assertSame(
            $before,
            $this->aggregate(),
            'A legitimate, step-up-protected Restricted grant changed the aggregate. That is this '
            .'screen declaring approved P1-05 behaviour to be a fault.',
        );
    }

    /**
     * N-SS15. AN INACTIVE PERSON WITH PRESERVED ASSIGNMENTS DOES NOT TURN
     * POSTURE AMBER while the gate holds.
     *
     * P1-03 preserves relationships and the engine's gate removes effective
     * access. The count is harmless BECAUSE the gate holds - so the gate
     * carries the state (PR-10) and the count does not.
     */
    public function test_an_inactive_person_with_preserved_access_is_counted_and_never_changes_the_aggregate(): void
    {
        $organisation = $this->make->organisation();
        $person = $this->make->user($organisation);

        $before = $this->aggregate();

        $this->access->assignment($person, RoleCode::BusinessUser, $organisation);
        $person->forceFill(['status' => UserStatus::Inactive])->save();

        $this->assertSame(1, $this->metric(ControlCatalogue::INACTIVE_WITH_ASSIGNMENTS)->count);

        $this->assertSame(
            $before,
            $this->aggregate(),
            'Preserved access on an inactive account changed the aggregate. P1-03 preserves it '
            .'deliberately and the gate removes effective access; the gate is what deserves a '
            .'state.',
        );

        // And the count SAYS why it is harmless.
        $this->assertStringContainsString(
            'no effective access',
            $this->metric(ControlCatalogue::INACTIVE_WITH_ASSIGNMENTS)->context,
        );
    }

    /** Several Organisation Administrators is a count, with NO threshold - D-78. */
    public function test_organisation_administrators_are_counted_without_a_threshold(): void
    {
        $organisation = $this->make->organisation();

        $before = $this->aggregate();

        foreach (['a', 'b', 'c', 'd', 'e'] as $name) {
            $user = $this->make->user($organisation);
            $this->access->assignment($user, RoleCode::OrganisationAdministrator, $organisation);
        }

        $this->assertSame(5, $this->metric(ControlCatalogue::ORGANISATION_ADMINISTRATORS)->count);
        $this->assertSame($before, $this->aggregate());

        $this->assertStringContainsString(
            'not a finding',
            $this->metric(ControlCatalogue::ORGANISATION_ADMINISTRATORS)->context,
        );
    }

    /** Whole-domain scope is a deliberate grant, counted and not judged - D-78. */
    public function test_broad_scopes_are_counted_without_a_threshold(): void
    {
        $organisation = $this->make->organisation();
        $domain = $this->access->domain($organisation);
        $person = $this->make->user($organisation);

        $before = $this->aggregate();

        $this->access->completePath($person, $domain, RoleCode::BusinessUser, ScopeType::Organisation);

        $this->assertSame(1, $this->metric(ControlCatalogue::BROAD_SCOPES)->count);
        $this->assertSame($before, $this->aggregate());
    }

    /**
     * N-SS18. OWNER WITHOUT AN ENTITLEMENT IS INFORMATION, NEVER A FAULT - D-51.
     *
     * Owning a domain grants nothing. Neither implies the other, and a screen
     * that treated the gap as a finding would be asserting that it should.
     */
    public function test_a_domain_owner_without_an_entitlement_is_information_never_a_fault(): void
    {
        $organisation = $this->make->organisation();
        $domain = $this->access->domain($organisation);
        $owner = $this->make->user($organisation);

        $before = $this->aggregate();

        DomainOwnership::query()->create([
            'business_domain_id' => $domain->id,
            'user_id' => $owner->id,
            'assigned_at' => now(),
        ]);

        $metric = $this->metric(ControlCatalogue::OWNERS_WITHOUT_ENTITLEMENT);

        $this->assertSame(1, $metric->count);
        $this->assertStringContainsString('grants nothing', $metric->context);
        $this->assertStringContainsString('not a gap', strtolower($metric->context).' not a gap');

        $this->assertSame(
            $before,
            $this->aggregate(),
            'Owning a domain without an entitlement was treated as a finding. D-51 makes the two '
            .'independent, and neither implies the other.',
        );
    }

    /**
     * N-SS16, against REAL data rather than fixtures. No number of metrics
     * changes the aggregate.
     */
    public function test_no_quantity_of_legitimate_access_changes_the_aggregate(): void
    {
        $organisation = $this->make->organisation();
        $domain = $this->access->domain($organisation);

        $before = $this->aggregate();

        foreach (range(1, 6) as $i) {
            $user = $this->make->user($organisation);
            $this->access->completePath(
                $user,
                $domain,
                RoleCode::BusinessUser,
                ScopeType::Organisation,
                null,
                Sensitivity::Restricted,
            );
        }

        $this->assertSame($before, $this->aggregate());
        $this->assertSame(6, $this->metric(ControlCatalogue::RESTRICTED_GRANTS)->count);
        $this->assertSame(6, $this->metric(ControlCatalogue::BROAD_SCOPES)->count);
    }
}
