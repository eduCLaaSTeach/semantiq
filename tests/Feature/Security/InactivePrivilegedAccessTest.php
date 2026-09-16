<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Modules\Access\Models\DomainEntitlement;
use App\Modules\Access\Support\RoleCode;
use App\Modules\Access\Support\ScopeType;
use App\Modules\Access\Support\Sensitivity;
use App\Modules\Domains\Models\BusinessDomain;
use App\Modules\Domains\Models\DomainOwnership;
use App\Modules\Platform\Models\User;
use App\Modules\Platform\Models\UserStatus;
use App\Modules\Security\Catalogue\ControlCatalogue;
use App\Modules\Security\Posture\Adapters\DomainAdapter;
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
 * GATE C BLOCKER — AN INACTIVE ADMINISTRATOR IS NOT A PRIVILEGED HOLDER.
 *
 * PR-3 and the per-domain privileged row counted a PRESERVED assignment on a
 * deactivated account, and reported "an administrator also holds business
 * access" about somebody who cannot reach a single row: AccessEngine denies an
 * inactive user at the GLOBAL GATE, before any role, entitlement, scope or
 * ceiling is considered.
 *
 * That is the posture-control/metric failure arriving through the front door.
 * P1-03 preserves relationships DELIBERATELY so access can be restored, and a
 * preserved assignment belongs in PR-5 - a count, with the sentence explaining
 * why it is harmless - not in a row that turns the deployment amber.
 *
 * EVERY CASE BELOW IS TESTED IN THREE STATES: active, inactive, and reactivated.
 * Testing only the first two would leave the reactivation path - the one that
 * must work with NOTHING RE-GRANTED - unproven.
 */
final class InactivePrivilegedAccessTest extends TestCase
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

        $this->fail("No row was produced for {$control}.");
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

    private function domainRow(string $name, string $facet): PostureState
    {
        foreach (app(PostureEvaluator::class)->evaluate()->domains as $domain) {
            if ($domain->name !== $name) {
                continue;
            }

            foreach ($domain->rows as $row) {
                if (str_starts_with($row->control, $facet.'#')) {
                    return $row->state;
                }
            }
        }

        $this->fail("No {$facet} row for the domain {$name}.");
    }

    private function deactivate(User $user): void
    {
        $user->forceFill(['status' => UserStatus::Inactive])->save();
    }

    private function reactivate(User $user): void
    {
        $user->forceFill(['status' => UserStatus::Active])->save();
    }

    /**
     * A grant to an administrator who is ACTIVE, then INACTIVE, then ACTIVE
     * again — the whole lifecycle, on one grant that is never touched.
     *
     * @return array{0: User, 1: BusinessDomain}
     */
    private function privilegedGrant(RoleCode $role): array
    {
        $organisation = $this->make->organisation();
        $domain = $this->access->domain($organisation, 'finance', 'Finance');

        $admin = $this->make->user($organisation);
        $assignment = $this->access->assignment($admin, $role, $organisation);
        $entitlement = $this->access->entitlement($assignment, $domain);
        $this->access->scope($entitlement, ScopeType::Organisation);
        $this->access->ceiling($entitlement, Sensitivity::Standard);

        return [$admin, $domain];
    }

    /**
     * SYSTEM ADMINISTRATOR — active, inactive, reactivated.
     */
    public function test_pr3_follows_a_system_administrators_account_status_through_the_whole_lifecycle(): void
    {
        [$admin] = $this->privilegedGrant(RoleCode::SystemAdministrator);

        // ACTIVE — the grant is real and reaches business information.
        $this->assertSame(
            PostureState::Attention,
            $this->row(ControlCatalogue::PRIVILEGED_WITH_DATA)->state,
            'An active administrator holding a business entitlement must be surfaced.',
        );

        // INACTIVE — the engine denies them at the global gate, so the grant
        // authorises nothing and must not turn posture amber.
        $this->deactivate($admin);

        $this->assertSame(
            PostureState::Healthy,
            $this->row(ControlCatalogue::PRIVILEGED_WITH_DATA)->state,
            'A preserved assignment on a deactivated account is still being reported as an '
            .'administrator holding business access. AccessEngine denies them at the global gate, '
            .'so this is inventing risk from a legitimate state.',
        );

        // REACTIVATED — the SAME assignment and the SAME entitlement count
        // again, with nothing re-granted.
        $this->reactivate($admin);

        $this->assertSame(
            PostureState::Attention,
            $this->row(ControlCatalogue::PRIVILEGED_WITH_DATA)->state,
            'Reactivating the account did not restore the finding, so the fix removed the grant '
            .'rather than reading the account status.',
        );
    }

    /**
     * ORGANISATION ADMINISTRATOR — the same three states.
     *
     * Both roles are in RoleCatalogue::requiringStepUp(), so both reach this
     * row; a fix applied to one and not the other would leave half the control
     * wrong.
     */
    public function test_pr3_follows_an_organisation_administrators_account_status_through_the_whole_lifecycle(): void
    {
        [$admin] = $this->privilegedGrant(RoleCode::OrganisationAdministrator);

        $this->assertSame(PostureState::Attention, $this->row(ControlCatalogue::PRIVILEGED_WITH_DATA)->state);

        $this->deactivate($admin);
        $this->assertSame(
            PostureState::Healthy,
            $this->row(ControlCatalogue::PRIVILEGED_WITH_DATA)->state,
            'The Organisation Administrator half of the rule was not fixed.',
        );

        $this->reactivate($admin);
        $this->assertSame(PostureState::Attention, $this->row(ControlCatalogue::PRIVILEGED_WITH_DATA)->state);
    }

    /**
     * THE PRESERVED ASSIGNMENT IS STILL COUNTED — informationally.
     *
     * This is the other half of the correction, and the half that makes it
     * honest: the relationship has NOT been hidden, ended or deleted. It moves
     * from a row that implies a finding to the count that explains why it is
     * harmless.
     */
    public function test_a_deactivated_administrators_preserved_access_is_still_counted_informationally(): void
    {
        [$admin] = $this->privilegedGrant(RoleCode::SystemAdministrator);

        $this->assertSame(
            0,
            $this->metric(ControlCatalogue::INACTIVE_WITH_ASSIGNMENTS)->count,
            'Nobody is inactive yet, so the informational count must be zero.',
        );

        $this->deactivate($admin);

        $this->assertSame(
            1,
            $this->metric(ControlCatalogue::INACTIVE_WITH_ASSIGNMENTS)->count,
            'The preserved assignment vanished from the informational metric. It must still be '
            .'visible - it has not been removed, only recognised as granting nothing today.',
        );

        $this->assertStringContainsString(
            'no effective access',
            $this->metric(ControlCatalogue::INACTIVE_WITH_ASSIGNMENTS)->context,
        );

        // And the underlying rows are genuinely untouched.
        $this->assertDatabaseHas('role_assignments', [
            'user_id' => $admin->id,
            'role_code' => RoleCode::SystemAdministrator->value,
            'ended_at' => null,
        ]);

        $this->assertSame(
            1,
            DomainEntitlement::query()->current()->count(),
            'The entitlement was ended or deleted. History must be preserved.',
        );
    }

    /**
     * PER-DOMAIN privileged posture, through the same three states.
     */
    public function test_per_domain_privileged_posture_follows_account_status_through_the_whole_lifecycle(): void
    {
        [$admin] = $this->privilegedGrant(RoleCode::SystemAdministrator);

        $this->assertSame(
            PostureState::Attention,
            $this->domainRow('Finance', DomainAdapter::PRIVILEGED_GRANTS),
        );

        $this->deactivate($admin);

        $this->assertSame(
            PostureState::Healthy,
            $this->domainRow('Finance', DomainAdapter::PRIVILEGED_GRANTS),
            'The domain is still amber for an administrator who cannot reach it.',
        );

        $this->reactivate($admin);

        $this->assertSame(
            PostureState::Attention,
            $this->domainRow('Finance', DomainAdapter::PRIVILEGED_GRANTS),
        );
    }

    /**
     * AN INACTIVE PRIVILEGED USER IN ONE DOMAIN LEAVES THAT DOMAIN CLEAN — and
     * does not disturb another domain that has a genuinely active one.
     *
     * The two domains are given DELIBERATELY DIFFERENT populations, so a fix
     * that filtered globally rather than per domain changes an assertion rather
     * than coincidentally matching.
     */
    public function test_an_inactive_privileged_user_does_not_turn_their_domain_amber_and_an_active_one_elsewhere_still_does(): void
    {
        $organisation = $this->make->organisation();

        $finance = $this->access->domain($organisation, 'finance', 'Finance');
        $people = $this->access->domain($organisation, 'people', 'People');

        // Finance: an administrator who is about to be deactivated.
        $leaving = $this->make->user($organisation);
        $leavingAssignment = $this->access->assignment($leaving, RoleCode::SystemAdministrator);
        $leavingEntitlement = $this->access->entitlement($leavingAssignment, $finance);
        $this->access->scope($leavingEntitlement, ScopeType::Organisation);
        $this->access->ceiling($leavingEntitlement, Sensitivity::Standard);

        // People: an administrator who stays active.
        $staying = $this->make->user($organisation);
        $stayingAssignment = $this->access->assignment($staying, RoleCode::OrganisationAdministrator, $organisation);
        $stayingEntitlement = $this->access->entitlement($stayingAssignment, $people);
        $this->access->scope($stayingEntitlement, ScopeType::Organisation);
        $this->access->ceiling($stayingEntitlement, Sensitivity::Standard);

        // Both amber while both are active.
        $this->assertSame(PostureState::Attention, $this->domainRow('Finance', DomainAdapter::PRIVILEGED_GRANTS));
        $this->assertSame(PostureState::Attention, $this->domainRow('People', DomainAdapter::PRIVILEGED_GRANTS));

        $this->deactivate($leaving);

        $this->assertSame(
            PostureState::Healthy,
            $this->domainRow('Finance', DomainAdapter::PRIVILEGED_GRANTS),
            'Finance is amber for an administrator who cannot reach it.',
        );

        $this->assertSame(
            PostureState::Attention,
            $this->domainRow('People', DomainAdapter::PRIVILEGED_GRANTS),
            'Deactivating somebody in Finance cleared the finding in People. The status filter is '
            .'not scoped to the domain being evaluated.',
        );

        // And the deployment-wide row agrees with the domains: one genuinely
        // active administrator still holds business access.
        $this->assertSame(
            PostureState::Attention,
            $this->row(ControlCatalogue::PRIVILEGED_WITH_DATA)->state,
        );
    }

    /**
     * THE TWO CALL SITES AGREE. A domain reading amber while the
     * deployment-wide row reads healthy - or the reverse - is the kind of
     * disagreement that makes a reader distrust both.
     */
    public function test_the_deployment_row_and_the_domain_row_agree_about_an_inactive_administrator(): void
    {
        [$admin] = $this->privilegedGrant(RoleCode::SystemAdministrator);

        $this->deactivate($admin);

        $this->assertSame(
            PostureState::Healthy,
            $this->row(ControlCatalogue::PRIVILEGED_WITH_DATA)->state,
        );

        $this->assertSame(
            PostureState::Healthy,
            $this->domainRow('Finance', DomainAdapter::PRIVILEGED_GRANTS),
        );
    }

    /**
     * AN INACTIVE ADMINISTRATOR DOES NOT CHANGE THE AGGREGATE.
     *
     * The consequence that matters to a reader: the badge at the top of the
     * screen must not go amber for somebody who cannot reach anything.
     */
    public function test_a_deactivated_administrator_with_preserved_access_does_not_change_the_aggregate(): void
    {
        $organisation = $this->make->organisation();
        $domain = $this->access->domain($organisation, 'finance', 'Finance');

        DomainOwnership::query()->create([
            'business_domain_id' => $domain->id,
            'user_id' => $this->make->user($organisation)->id,
            'assigned_at' => now(),
        ]);

        $before = $this->aggregate();

        $admin = $this->make->user($organisation);
        $assignment = $this->access->assignment($admin, RoleCode::SystemAdministrator);
        $entitlement = $this->access->entitlement($assignment, $domain);
        $this->access->scope($entitlement, ScopeType::Organisation);
        $this->access->ceiling($entitlement, Sensitivity::Standard);

        $this->deactivate($admin);

        $this->assertSame(
            $before,
            $this->aggregate(),
            'Deactivated access changed the deployment aggregate.',
        );
    }

    private function aggregate(): PostureState
    {
        $report = app(PostureEvaluator::class)->evaluate();

        $states = array_map(static fn (PostureRow $row): PostureState => $row->state, $report->rows);

        foreach ($report->domains as $domain) {
            foreach ($domain->rows as $row) {
                $states[] = $row->state;
            }
        }

        return Aggregation::of($states);
    }
}
