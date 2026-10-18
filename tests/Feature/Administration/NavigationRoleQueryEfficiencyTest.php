<?php

declare(strict_types=1);

namespace Tests\Feature\Administration;

use App\Modules\Access\Support\RoleCode;
use App\Modules\Organisation\Support\SystemAdministratorNavigationAuthorizer;
use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;
use App\Modules\Platform\Models\User;
use App\Shared\Navigation\ApprovedMenu;
use App\Shared\Navigation\NavigationNode;
use App\Shared\Navigation\ProductArea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Support\AccessFactory;
use Tests\Support\OrganisationFactory;
use Tests\TestCase;

/**
 * CL-12 FOLLOW-UP - THE NAVIGATION ROLE LOOKUP IS ASKED ONCE PER REQUEST.
 *
 * Raised as a P2 on PR #147: NavigationRegistry::visibleFor() calls
 * allows() once per menu node, and AccessEngine::holdsRole() is a database
 * exists(). Widening the allow-list from one key to eight therefore added
 * seven more round trips to every page render - including for viewers who do
 * not hold the role at all.
 *
 * MEASURED ON /console BEFORE THE FIX, counting real role_assignments queries:
 *
 *   System Administrator        37   (37 SysAdmin, 0 OrgAdmin)
 *   Organisation Administrator  45   (37 SysAdmin, 8 OrgAdmin)
 *   Auditor                     45   (37 SysAdmin, 8 OrgAdmin)
 *   Business User               45   (37 SysAdmin, 8 OrgAdmin)
 *
 * 36 of each SysAdmin count come from the authorizer - one per menu node. The
 * 37th is ConsoleController's own call, which this change does not touch and
 * which is why the HTTP assertions below allow two rather than one.
 *
 * WHAT THESE CASES GUARD, AND IT IS NOT ONLY SPEED.
 *
 * The obvious way to make the count drop is to remember the answer on the
 * object. That is a SECURITY BUG here, because NavigationRegistry holds this
 * authorizer as a singleton: the memo would outlive the request and answer the
 * next viewer with the previous viewer's roles. So the request-boundary and
 * two-user cases below are not performance tests at all - they are the
 * assertions that fail if somebody "optimises" this into a cross-request cache.
 *
 * Q1 and Q2 fall to a correct instance-level cache as readily as to a correct
 * request-scoped one. Q4, Q5 and Q6 are the ones that tell them apart.
 */
final class NavigationRoleQueryEfficiencyTest extends TestCase
{
    use RefreshDatabase;

    private OrganisationFactory $make;

    private AccessFactory $access;

    /** @var list<string> */
    private array $roleQueries = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->make = new OrganisationFactory;
        $this->access = new AccessFactory;

        DB::listen(function ($query): void {
            if (! str_contains($query->sql, 'role_assignments')) {
                return;
            }

            /*
             * READS ONLY. The fixtures INSERT into role_assignments, and those
             * statements carry the same role_code binding - counting them made
             * every expectation one too high and would have been "fixed" by
             * loosening the assertion rather than by measuring the right thing.
             */
            if (! str_starts_with(strtolower(ltrim($query->sql)), 'select')) {
                return;
            }

            foreach ($query->bindings as $binding) {
                if (is_string($binding) && in_array($binding, [
                    RoleCode::SystemAdministrator->value,
                    RoleCode::OrganisationAdministrator->value,
                ], true)) {
                    $this->roleQueries[] = $binding;
                }
            }
        });
    }

    // =================================================================
    // Q1, Q2, Q3. The counts the P2 finding asked for.
    // =================================================================

    /**
     * Q1. ONE SystemAdministrator lookup from the authorizer, for all 36 nodes.
     *
     * Asserted by driving allows() directly over EVERY policy key the menu
     * declares, so the number is the authorizer's own and no controller's call
     * is mixed into it. Before the fix this was 36.
     */
    public function test_one_request_asks_for_the_system_administrator_role_once(): void
    {
        $user = $this->userWith(RoleCode::SystemAdministrator);

        $this->inOneRequestAs($user, function () use (&$keys): void {
            $authorizer = app(SystemAdministratorNavigationAuthorizer::class);

            foreach ($this->everyPolicyKey() as $key) {
                $authorizer->allows($key);
            }
        });

        $this->assertGreaterThan(
            30,
            count($this->everyPolicyKey()),
            'The menu declares almost no nodes, so a low query count would prove nothing.'
        );

        $this->assertSame(
            1,
            $this->roleQueryCount(RoleCode::SystemAdministrator),
            'The authorizer asked whether this viewer is a System Administrator more than once '
            .'in a single request. It was 36 before the CL-12 follow-up.'
        );
    }

    /** Q2. ONE OrganisationAdministrator lookup across a real /console render. */
    public function test_one_organisation_administrator_request_asks_for_that_role_once(): void
    {
        $this->renderConsoleAs($this->userWith(RoleCode::OrganisationAdministrator));

        $this->assertSame(
            1,
            $this->roleQueryCount(RoleCode::OrganisationAdministrator),
            'The eight allow-listed nodes each issued their own Organisation Administrator '
            .'lookup. It was 8 before the CL-12 follow-up.'
        );

        $this->assertLessThanOrEqual(
            2,
            $this->roleQueryCount(RoleCode::SystemAdministrator),
            'More than two System Administrator lookups in one render. At most one may come '
            .'from the authorizer and one from ConsoleController.'
        );
    }

    /**
     * Q3. A key OUTSIDE the allow-list costs NO OrganisationAdministrator query.
     *
     * The short-circuit order is the guard: in_array is evaluated before
     * holdsRole, so a platform or future-phase key is refused on the list alone.
     * Swapping the two operands would still return the right answer and would
     * silently reintroduce a query per hidden node - which is why this is
     * asserted on the COUNT and not on the result.
     */
    public function test_keys_outside_the_allow_list_do_not_ask_for_the_organisation_administrator_role(): void
    {
        $user = $this->userWith(RoleCode::OrganisationAdministrator);

        $outside = ['identity.view', 'system-health.view', 'integrations.view', 'fabric.view', 'workplace.view'];

        $this->inOneRequestAs($user, function () use ($outside): void {
            $authorizer = app(SystemAdministratorNavigationAuthorizer::class);

            foreach ($outside as $key) {
                $this->assertFalse(
                    $authorizer->allows($key),
                    "[{$key}] is outside the CL-12 allow-list and was admitted."
                );
            }
        });

        $this->assertSame(
            0,
            $this->roleQueryCount(RoleCode::OrganisationAdministrator),
            'A policy key outside the allow-list triggered an Organisation Administrator '
            .'lookup. The membership test must short-circuit before the role query.'
        );
    }

    // =================================================================
    // Q4, Q5, Q6. The cases that reject a cross-request cache.
    // =================================================================

    /**
     * Q4. TWO REQUESTS ASK TWICE.
     *
     * A memo that survives the request would make the second render free, and
     * would also make a revoked role keep working. This is the assertion that
     * fails against a static or instance-level cache.
     */
    public function test_two_separate_requests_do_not_reuse_the_memo(): void
    {
        $user = $this->userWith(RoleCode::OrganisationAdministrator);

        $this->renderConsoleAs($user);
        $first = $this->roleQueryCount(RoleCode::OrganisationAdministrator);

        $this->roleQueries = [];

        $this->renderConsoleAs($user);
        $second = $this->roleQueryCount(RoleCode::OrganisationAdministrator);

        $this->assertSame(1, $first, 'The first request did not ask exactly once.');
        $this->assertSame(
            1,
            $second,
            'The second request reused the first request\'s answer. The memo outlived the '
            .'request, so a revoked role would keep working until the process restarted.'
        );
    }

    /**
     * Q5. TWO USERS IN ONE REQUEST DO NOT SHARE AN ANSWER.
     *
     * One request normally carries one viewer, so this is not a scenario the
     * application produces - it is the direct test of the memo's KEY. A memo
     * keyed by role alone would hand the second user the first user's answer,
     * and no realistic fixture would reveal it.
     */
    public function test_two_different_users_do_not_share_the_memo(): void
    {
        $administrator = $this->userWith(RoleCode::OrganisationAdministrator);
        $auditor = $this->userWith(RoleCode::Auditor);

        $request = Request::create('/console');
        $this->app->instance('request', $request);

        $authorizer = app(SystemAdministratorNavigationAuthorizer::class);

        $request->attributes->set('semantiq_user', $administrator);
        $this->assertTrue(
            $authorizer->allows('audit.view'),
            'The Organisation Administrator was refused an approved node.'
        );

        $request->attributes->set('semantiq_user', $auditor);
        $this->assertFalse(
            $authorizer->allows('audit.view'),
            'An Auditor was admitted using the Organisation Administrator\'s cached role. The '
            .'memo is keyed by role alone and leaks between users.'
        );

        $this->assertSame(
            2,
            $this->roleQueryCount(RoleCode::OrganisationAdministrator),
            'The second user did not cause a lookup of their own, so their answer came from '
            .'somebody else\'s entry.'
        );
    }

    /** Q6. A role granted between two requests takes effect on the second one. */
    public function test_a_role_change_is_visible_on_the_next_request(): void
    {
        $organisation = $this->make->organisation();
        $user = $this->make->user($organisation);

        $this->assertSame(
            [],
            $this->systemAdministrationLabels($this->renderConsoleAs($user)),
            'A user holding no role already sees System Administration navigation.'
        );

        $this->access->assignment($user, RoleCode::OrganisationAdministrator, $organisation);

        $this->assertCount(
            8,
            $this->systemAdministrationLabels($this->renderConsoleAs($user)),
            'A role granted between two requests was not visible on the second. The memo is '
            .'outliving the request.'
        );
    }

    // =================================================================
    // The memo is where it claims to be.
    // =================================================================

    /**
     * It lives on the REQUEST, and dies with it.
     *
     * Asserted on the attribute bag directly, because every behavioural case
     * above would also pass against a correctly-invalidated cache somewhere
     * else - and "somewhere else" is what D-69 forbids.
     */
    public function test_the_memo_is_held_in_request_attributes_and_nowhere_else(): void
    {
        $user = $this->userWith(RoleCode::OrganisationAdministrator);

        $request = Request::create('/console');
        $this->app->instance('request', $request);
        $request->attributes->set('semantiq_user', $user);

        $this->assertFalse(
            $request->attributes->has(SystemAdministratorNavigationAuthorizer::REQUEST_ROLE_MEMO),
            'The memo exists before anything was asked.'
        );

        app(SystemAdministratorNavigationAuthorizer::class)->allows('audit.view');

        $this->assertTrue(
            $request->attributes->has(SystemAdministratorNavigationAuthorizer::REQUEST_ROLE_MEMO),
            'The role answer was not recorded on the request, so it is being kept somewhere '
            .'that outlives the request.'
        );

        $this->assertSame(
            [],
            Request::create('/console')->attributes->get(
                SystemAdministratorNavigationAuthorizer::REQUEST_ROLE_MEMO,
                []
            ),
            'A brand new request already carries role answers.'
        );
    }

    // =================================================================
    // Helpers.
    // =================================================================

    private function roleQueryCount(RoleCode $role): int
    {
        return count(array_filter(
            $this->roleQueries,
            static fn (string $binding): bool => $binding === $role->value
        ));
    }

    /** @return list<string> */
    private function everyPolicyKey(): array
    {
        return array_values(array_map(
            static fn (NavigationNode $node): string => $node->policyKey,
            ApprovedMenu::roadmap()
        ));
    }

    private function userWith(RoleCode $role): User
    {
        $organisation = $this->make->organisation();
        $user = $this->make->user($organisation);
        $this->access->assignment($user, $role, $organisation);

        return $user;
    }

    /** Run $work against one fresh Request, with the measurement reset first. */
    private function inOneRequestAs(User $user, callable $work): void
    {
        $request = Request::create('/console');
        $this->app->instance('request', $request);
        $request->attributes->set('semantiq_user', $user);

        $this->roleQueries = [];

        $work();
    }

    private function renderConsoleAs(User $user): TestResponse
    {
        $this->roleQueries = [];

        return $this->withSession([
            EnsureSessionIsCurrent::SESSION_USER_ID => $user->id,
            EnsureSessionIsCurrent::SESSION_AUTHENTICATED_AT => now()->toIso8601String(),
        ])->get('/console');
    }

    /** @return list<string> */
    private function systemAdministrationLabels(TestResponse $response): array
    {
        foreach ($response->viewData('page')['props']['productAreas'] ?? [] as $area) {
            if ($area['key'] === ProductArea::SystemAdministration->value) {
                return array_map(static fn (array $node): string => $node['label'], $area['nodes']);
            }
        }

        return [];
    }
}
