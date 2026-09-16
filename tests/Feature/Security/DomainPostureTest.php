<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Modules\Access\Support\RoleCode;
use App\Modules\Access\Support\ScopeType;
use App\Modules\Access\Support\Sensitivity;
use App\Modules\Domains\Models\DomainOwnership;
use App\Modules\Security\Posture\Adapters\DomainAdapter;
use App\Modules\Security\Posture\DomainPosture;
use App\Modules\Security\Posture\PostureEvaluator;
use App\Modules\Security\Posture\PostureState;
use App\Modules\Security\Projection\PostureProjection;
use App\Modules\Security\Projection\Viewer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AccessFactory;
use Tests\Support\OrganisationFactory;
use Tests\TestCase;

/**
 * N-SS19 to N-SS22 - DOMAIN POSTURE, AND NO CROSS-CONTAMINATION.
 *
 * The contamination fixture uses two domains with DELIBERATELY DIFFERENT
 * counts. Two domains with identical populations cannot detect a missing where
 * clause: the wrong answer and the right answer coincide, and the test reports
 * a safety it is not providing. CLAUDE.md §2 names exactly this failure - "a
 * column that happens to hold the right value today".
 */
final class DomainPostureTest extends TestCase
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

    private function domain(string $name): DomainPosture
    {
        foreach (app(PostureEvaluator::class)->evaluate()->domains as $posture) {
            if ($posture->name === $name) {
                return $posture;
            }
        }

        $this->fail("No posture was produced for the domain {$name}.");
    }

    private function rowState(DomainPosture $posture, string $facet): PostureState
    {
        foreach ($posture->rows as $row) {
            if (str_starts_with($row->control, $facet.'#')) {
                return $row->state;
            }
        }

        $this->fail("No {$facet} row for {$posture->name}.");
    }

    private function metricCount(DomainPosture $posture, string $facet): int
    {
        foreach ($posture->metrics as $metric) {
            if (str_starts_with($metric->control, $facet.'#')) {
                return $metric->count;
            }
        }

        $this->fail("No {$facet} metric for {$posture->name}.");
    }

    /**
     * N-SS19. A DISABLED DOMAIN IS A FAIL-CLOSED SUCCESS, NOT A FAULT.
     *
     * Calling it a fault teaches people to ignore the screen, which is the one
     * thing a posture screen cannot afford.
     */
    public function test_a_disabled_domain_is_reported_as_fail_closed_rather_than_as_a_fault(): void
    {
        $organisation = $this->make->organisation();
        $this->access->domain($organisation, 'archive', 'Archive', 'disabled');

        $posture = $this->domain('Archive');

        $this->assertFalse($posture->enabled);
        $this->assertSame(PostureState::Healthy, $posture->state());

        $finding = $posture->rows[0]->finding;
        $this->assertStringContainsString('nobody reaches its information', $finding);
    }

    /**
     * N-SS20. AN ENABLED DOMAIN WITH NO CURRENT OWNER IS ATTENTION.
     *
     * P1-04 refuses that state through its own UI, so the fixture constructs it
     * directly: a stored state that escaped the UI is exactly what a posture
     * screen is for.
     */
    public function test_an_enabled_domain_with_no_owner_is_attention_and_with_one_is_healthy(): void
    {
        $organisation = $this->make->organisation();
        $domain = $this->access->domain($organisation, 'finance', 'Finance');

        $this->assertSame(
            PostureState::Attention,
            $this->rowState($this->domain('Finance'), DomainAdapter::OWNER_MISSING),
        );

        DomainOwnership::query()->create([
            'business_domain_id' => $domain->id,
            'user_id' => $this->make->user($organisation)->id,
            'assigned_at' => now(),
        ]);

        $this->assertSame(
            PostureState::Healthy,
            $this->rowState($this->domain('Finance'), DomainAdapter::OWNER_MISSING),
            'A domain with a current owner must report healthy, or the rule has no green branch.',
        );
    }

    /** And the screen says, in its own words, that owning is not access - D-51. */
    public function test_a_domain_row_says_that_owning_grants_nothing(): void
    {
        $organisation = $this->make->organisation();
        $domain = $this->access->domain($organisation, 'finance', 'Finance');

        DomainOwnership::query()->create([
            'business_domain_id' => $domain->id,
            'user_id' => $this->make->user($organisation)->id,
            'assigned_at' => now(),
        ]);

        $finding = $this->domain('Finance')->rows[0]->finding;

        $this->assertStringContainsString('grants no access', $finding);
    }

    /**
     * N-SS21. A DOMAIN'S POSTURE NEVER INCLUDES ANOTHER DOMAIN'S ROWS.
     *
     * Two domains, DELIBERATELY DIFFERENT populations. Mutation: drop the
     * business_domain_id predicate from any per-domain query - Finance's totals
     * would then pick up People's rows and the assertions below change.
     */
    public function test_one_domains_posture_never_includes_another_domains_rows(): void
    {
        $organisation = $this->make->organisation();

        $finance = $this->access->domain($organisation, 'finance', 'Finance');
        $people = $this->access->domain($organisation, 'people', 'People');

        // Finance: ONE complete grant, standard sensitivity, whole-domain scope.
        $this->access->completePath(
            $this->make->user($organisation),
            $finance,
            RoleCode::BusinessUser,
            ScopeType::Organisation,
            null,
            Sensitivity::Standard,
        );

        // People: THREE grants, all Restricted. Deliberately a different shape,
        // so a contamination bug changes an assertion rather than coinciding.
        foreach (range(1, 3) as $ignored) {
            $this->access->completePath(
                $this->make->user($organisation),
                $people,
                RoleCode::BusinessUser,
                ScopeType::Organisation,
                null,
                Sensitivity::Restricted,
            );
        }

        $financePosture = $this->domain('Finance');
        $peoplePosture = $this->domain('People');

        $this->assertSame(1, $this->metricCount($financePosture, DomainAdapter::ENTITLEMENTS));
        $this->assertSame(3, $this->metricCount($peoplePosture, DomainAdapter::ENTITLEMENTS));

        $this->assertSame(
            0,
            $this->metricCount($financePosture, DomainAdapter::RESTRICTED),
            'Finance is reporting a Restricted grant that belongs to People.',
        );

        $this->assertSame(3, $this->metricCount($peoplePosture, DomainAdapter::RESTRICTED));

        $this->assertSame(1, $this->metricCount($financePosture, DomainAdapter::BROAD_SCOPES));
        $this->assertSame(3, $this->metricCount($peoplePosture, DomainAdapter::BROAD_SCOPES));
    }

    /** The same isolation for the STATE-BEARING rows, not only the counts. */
    public function test_a_privileged_grant_into_one_domain_does_not_mark_another(): void
    {
        $organisation = $this->make->organisation();

        $finance = $this->access->domain($organisation, 'finance', 'Finance');
        $people = $this->access->domain($organisation, 'people', 'People');

        foreach ([$finance, $people] as $domain) {
            DomainOwnership::query()->create([
                'business_domain_id' => $domain->id,
                'user_id' => $this->make->user($organisation)->id,
                'assigned_at' => now(),
            ]);
        }

        // An administrator entitled to FINANCE only.
        $admin = $this->make->user($organisation);
        $assignment = $this->access->assignment($admin, RoleCode::SystemAdministrator);
        $entitlement = $this->access->entitlement($assignment, $finance);
        $this->access->scope($entitlement, ScopeType::Organisation);
        $this->access->ceiling($entitlement, Sensitivity::Standard);

        $this->assertSame(
            PostureState::Attention,
            $this->rowState($this->domain('Finance'), DomainAdapter::PRIVILEGED_GRANTS),
        );

        $this->assertSame(
            PostureState::Healthy,
            $this->rowState($this->domain('People'), DomainAdapter::PRIVILEGED_GRANTS),
            'People is reporting an administrator grant that belongs to Finance.',
        );
    }

    /**
     * N-SS22. NO DOMAIN ROW IMPLIES DATA CLASSIFICATION OR FABRIC SECURITY.
     *
     * Sensitivity is a CEILING ON A GRANT, not a label on data, and there is no
     * data in Phase 1. A screen that implied otherwise would be promising a
     * capability nobody has built.
     */
    public function test_no_domain_row_implies_data_classification_or_fabric_security_exists(): void
    {
        $organisation = $this->make->organisation();
        $domain = $this->access->domain($organisation, 'finance', 'Finance');

        $this->access->completePath(
            $this->make->user($organisation),
            $domain,
            RoleCode::BusinessUser,
            ScopeType::Organisation,
            null,
            Sensitivity::Restricted,
        );

        $posture = $this->domain('Finance');

        $text = strtolower(json_encode([
            array_map(static fn ($r): array => [$r->label, $r->finding], $posture->rows),
            array_map(static fn ($m): array => [$m->label, $m->context], $posture->metrics),
        ]) ?: '');

        foreach (['classif', 'fabric', 'power bi', 'data source', 'ingest'] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $text,
                "A domain row mentions \"{$forbidden}\", implying a capability Phase 1 does not have.",
            );
        }

        // What it DOES say is that this is a limit on a grant.
        $this->assertStringContainsString('grants', $text);
    }

    /**
     * A DOMAIN CONDITION REACHES THE DEPLOYMENT AGGREGATE AND THE EXCEPTIONS
     * LIST.
     *
     * Domain posture is posture. Leaving it out would let the badge read
     * Healthy while the section below it showed a domain needing attention -
     * an inconsistency a reader would notice immediately, and would be right to
     * distrust. It would also keep "enabled with nobody accountable for it" out
     * of Exceptions, which is exactly the kind of stored state that escaped a
     * UI refusal and is worth surfacing.
     *
     * Mutation: compute the aggregate over the deployment rows only.
     */
    public function test_a_domain_condition_reaches_the_deployment_aggregate_and_the_exceptions(): void
    {
        $organisation = $this->make->organisation();

        // Enabled, with nobody accountable for it - an Attention condition that
        // exists ONLY at the domain level.
        $this->access->domain($organisation, 'finance', 'Finance');

        $projected = PostureProjection::for(
            app(PostureEvaluator::class)->evaluate(),
            Viewer::withPlatformValues(true),
        );

        $controls = array_map(
            static fn ($row): string => $row->control,
            $projected->exceptions(),
        );

        $ownerRows = array_values(array_filter(
            $controls,
            static fn (string $c): bool => str_starts_with($c, DomainAdapter::OWNER_MISSING.'#'),
        ));

        $this->assertNotSame(
            [],
            $ownerRows,
            'A domain with nobody accountable for it never reaches the Exceptions list, so the '
            .'badge and the domain section can disagree.',
        );

        // And the row NAMES the domain, or the entry is useless in a flat list.
        foreach ($projected->exceptions() as $row) {
            if (str_starts_with($row->control, DomainAdapter::OWNER_MISSING.'#')) {
                $this->assertSame('Finance', $row->qualifier);
            }
        }

        $this->assertContains(
            $projected->aggregate(),
            [PostureState::Critical, PostureState::Attention, PostureState::Unverified],
            'The deployment aggregate ignores its domains.',
        );
    }

    /** Each domain's rows carry a control id unique to that domain. */
    public function test_two_domains_do_not_collide_on_control_identifiers(): void
    {
        $organisation = $this->make->organisation();
        $this->access->domain($organisation, 'finance', 'Finance');
        $this->access->domain($organisation, 'people', 'People');

        $ids = [];

        foreach (app(PostureEvaluator::class)->evaluate()->domains as $domain) {
            foreach ($domain->rows as $row) {
                $ids[] = $row->control;
            }
        }

        $this->assertSame(
            count($ids),
            count(array_unique($ids)),
            'Two domains share a control identifier, so one domain\'s row would replace the '
            .'other\'s in any lookup keyed by control - cross-contamination through the back door.',
        );
    }

    /** A domain with nothing granted reports healthy, not empty. */
    public function test_a_domain_with_no_grants_reports_healthy_rather_than_nothing(): void
    {
        $organisation = $this->make->organisation();
        $domain = $this->access->domain($organisation, 'finance', 'Finance');

        DomainOwnership::query()->create([
            'business_domain_id' => $domain->id,
            'user_id' => $this->make->user($organisation)->id,
            'assigned_at' => now(),
        ]);

        $posture = $this->domain('Finance');

        $this->assertSame(PostureState::Healthy, $posture->state());
        $this->assertNotSame([], $posture->rows);
        $this->assertSame(0, $this->metricCount($posture, DomainAdapter::ENTITLEMENTS));
    }
}
