<?php

declare(strict_types=1);

namespace Tests\Feature\Administration;

use App\Modules\Access\Support\RoleCode;
use App\Modules\Domains\Models\DomainOwnership;
use App\Modules\People\Models\Group;
use App\Modules\People\Models\GroupStatus;
use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;
use App\Modules\Platform\Models\User;
use App\Modules\Security\Posture\PostureEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Support\AccessFactory;
use Tests\Support\OrganisationFactory;
use Tests\TestCase;

/**
 * D-140 - THE QUERY AND RENDER BUDGET, MEASURED RATHER THAN ASSERTED.
 *
 * A budget nobody counts is a target. These cases count.
 *
 * THE FIXTURE IS DELIBERATELY NOT MINIMAL. An N+1 is invisible at a fixture of
 * one, so the same page is rendered at two data sizes and the counts compared.
 * A query count that grows with the data fails even when the absolute number is
 * still small.
 *
 * ---------------------------------------------------------------------------
 * A FINDING, MEASURED AT EXECUTE AND NOT FIXED HERE
 * ---------------------------------------------------------------------------
 *
 * THE POSTURE SOURCE CARRIES A PER-DOMAIN COST, AND IT IS P1-06'S, NOT THIS
 * UNIT'S. Measured on this fixture at twenty business domains:
 *
 *     /console/administration          236 queries
 *     /console/security                189 queries
 *     /console/security/exceptions     189 queries
 *     /console/system-health            58 queries
 *     /console/domains                  44 queries
 *
 * PostureEvaluator's DomainAdapter issues five aggregates per business domain -
 * entitlements, scopes and ceilings - so Security Status already pays 189 of
 * those 236 today, on a screen that was accepted five units ago. P1-11 does not
 * add to it: it evaluates P1-06 ONCE and reads two tiles from the one report.
 *
 * SO THIS FILE MEASURES THE TWO SEPARATELY. P1-11's own composition must not
 * grow with the data at all, and that is asserted. The inherited growth is
 * asserted to be NO WORSE than the screen it comes from, which is the honest
 * claim this unit can make about somebody else's query.
 *
 * It is raised for the Product Owner in P1-11-VERIFICATION.md rather than fixed
 * here. Widening P1-11 to re-engineer an accepted unit's evaluator is exactly
 * the scope creep the professional-polish gate says it does not authorise.
 */
final class AdministrationHomeBudgetTest extends TestCase
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

    private function actingAsUser(User $user): self
    {
        return $this->withSession([
            EnsureSessionIsCurrent::SESSION_USER_ID => $user->id,
            EnsureSessionIsCurrent::SESSION_AUTHENTICATED_AT => now()->toIso8601String(),
        ]);
    }

    /** Builds `$size` of everything and returns the administrator. */
    private function deploymentOf(int $size): User
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation);
        $this->access->assignment($admin, RoleCode::SystemAdministrator, $organisation);

        for ($i = 0; $i < $size; $i++) {
            $this->make->user($organisation);

            Group::query()->create([
                'organisation_id' => $organisation->id,
                'name' => "Group {$i}",
                'status' => GroupStatus::Active,
            ]);

            $domain = $this->access->domain($organisation, "domain-{$i}", "Domain {$i}");

            // Half owned, half not, so whereDoesntHave has real work to do.
            if ($i % 2 === 0) {
                DomainOwnership::query()->create([
                    'business_domain_id' => $domain->id,
                    'user_id' => $admin->id,
                    'assigned_at' => now(),
                    'ended_at' => null,
                ]);
            }
        }

        return $admin;
    }

    /** @return array{queries: int, ms: float} */
    private function render(User $admin): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $started = microtime(true);

        $this->actingAsUser($admin)->get('/console/administration')->assertOk();

        $elapsed = (microtime(true) - $started) * 1000;

        $queries = count(DB::getQueryLog());

        DB::disableQueryLog();

        return ['queries' => $queries, 'ms' => $elapsed];
    }

    /**
     * P1-11'S OWN COMPOSITION DOES NOT GROW WITH THE DATA - AT ALL.
     *
     * The posture source is forced to fail, which removes P1-06's per-domain
     * cost and leaves exactly the seven sources this unit composes. Every
     * figure among them is an aggregate, so the query count must be IDENTICAL
     * at two data sizes: no model row is loaded to be counted, and the
     * unowned-domain figure is one correlated subquery rather than a loop
     * asking each domain who owns it.
     *
     * FORCING THE FAILURE IS NOT HIDING THE COST - the case below measures it
     * against Security Status. It is how this case asks ONLY about the part
     * P1-11 wrote.
     *
     * Mutation: replace whereDoesntHave('currentOwnership') with a loop over
     * enabled domains calling currentOwnership on each. The small render is
     * unchanged; this fails with twenty extra queries at the larger size.
     */
    public function test_p1_11s_own_composition_does_not_grow_with_the_data(): void
    {
        $withoutPosture = function (int $size): int {
            $this->app->bind(PostureEvaluator::class, function (): object {
                throw new RuntimeException('Posture is out of scope for this measurement.');
            });

            return $this->render($this->deploymentOf($size))['queries'];
        };

        $small = $withoutPosture(2);

        $this->refreshApplication();
        $this->artisan('migrate:fresh');
        $this->make = new OrganisationFactory;
        $this->access = new AccessFactory;

        $large = $withoutPosture(20);

        $this->assertSame(
            $small,
            $large,
            "P1-11's own composition took {$small} queries against a small deployment and "
            ."{$large} against one ten times the size. Every figure this unit asks for is an "
            .'aggregate, so the count must not move at all.'
        );
    }

    /**
     * THE INHERITED COST IS NO WORSE THAN THE SCREEN IT COMES FROM.
     *
     * P1-06 is evaluated ONCE and feeds two tiles - ViewerReport's own docblock
     * says exceptions() is "the single source of the list, the tab count and
     * the heading count". So Administration Home must not pay more for posture
     * than Security Status does, on the same data.
     *
     * The margin covers what every console page pays and what the other six
     * sources add; what it does not cover is a SECOND evaluation, which would
     * roughly double the posture cost and fail here.
     *
     * Mutation: evaluate PostureEvaluator again for the exceptions tile
     * instead of reading the one report twice. That is G8, and this is where it
     * shows up as a number.
     */
    public function test_administration_home_does_not_evaluate_posture_more_than_security_status(): void
    {
        $admin = $this->deploymentOf(20);

        $home = $this->render($admin)['queries'];

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAsUser($admin)->get('/console/security')->assertOk();
        $securityStatus = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(
            $securityStatus * 1.5,
            $home,
            "Administration Home took {$home} queries and Security Status {$securityStatus} on "
            .'the same data. P1-06 is evaluated ONCE per render and feeds two tiles; a count near '
            .'double the source screen means it was evaluated twice.'
        );
    }

    /**
     * THE BUDGET, AS THE CLAIM THE PRODUCT ACTUALLY MAKES: reading the summary
     * costs LESS than opening the screens it summarises.
     *
     * A ceiling set to "the number we measured, plus a bit" would be a
     * restatement of today's behaviour rather than a property. This is a
     * property: Administration Home exists so an administrator does not have to
     * visit six screens to find out whether anything needs them, and the cost
     * of the summary must be smaller than the cost of the tour.
     *
     * IT ALSO MEANS SOMETHING WHEN IT FAILS. A tile that started loading rows,
     * a source evaluated twice, or a count written as a loop all push the
     * summary toward the sum of its parts, and this says so in the terms the
     * screen was approved in.
     *
     * The posture source is out of the way on BOTH sides of the comparison, so
     * the margin is about this unit's composition rather than about P1-06's
     * per-domain evaluator. The measured figures are recorded in
     * P1-11-VERIFICATION.md.
     */
    public function test_the_summary_costs_less_than_visiting_the_screens_it_summarises(): void
    {
        $this->app->bind(PostureEvaluator::class, function (): object {
            throw new RuntimeException('Posture is measured separately, in the case above.');
        });

        $admin = $this->deploymentOf(20);

        $summary = $this->render($admin)['queries'];

        $tour = 0;

        foreach ([
            '/console/organisation',
            '/console/people/users',
            '/console/domains',
            '/console/integrations',
            '/console/access-reviews',
            '/console/system-health',
        ] as $screen) {
            DB::flushQueryLog();
            DB::enableQueryLog();

            $this->actingAsUser($admin)->get($screen)->assertOk();

            $tour += count(DB::getQueryLog());

            DB::disableQueryLog();
        }

        $this->assertLessThan(
            $tour,
            $summary,
            "Administration Home issued {$summary} queries to summarise six screens that cost "
            ."{$tour} queries to visit. A summary that is not cheaper than the tour is not a "
            .'summary - it is a seventh screen.'
        );
    }

    /**
     * AN ORGANISATION ADMINISTRATOR PAYS LESS, because two sources are not
     * evaluated at all - DESIGN CORRECTION 3.
     *
     * This is the cost half of G16. The call-count spy proves the objects are
     * never built; this proves the queries behind them are never issued, which
     * is the part a viewer would actually feel.
     *
     * Mutation: evaluate the platform-only sources and withhold the result
     * afterwards. The counts become equal and this fails.
     */
    public function test_an_organisation_administrator_issues_fewer_queries_than_a_system_administrator(): void
    {
        $organisation = $this->make->organisation();

        $system = $this->make->user($organisation);
        $this->access->assignment($system, RoleCode::SystemAdministrator, $organisation);

        $organisationAdmin = $this->make->user($organisation);
        $this->access->assignment($organisationAdmin, RoleCode::OrganisationAdministrator, $organisation);

        $platform = $this->render($system);
        $narrow = $this->render($organisationAdmin);

        $this->assertLessThan(
            $platform['queries'],
            $narrow['queries'],
            "An Organisation Administrator paid {$narrow['queries']} queries and a System "
            ."Administrator {$platform['queries']}. The viewer who may not receive the "
            .'platform-only values should not be paying for them to be read.'
        );
    }
}
