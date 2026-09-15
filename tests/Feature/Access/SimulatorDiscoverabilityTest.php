<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Modules\Access\Support\ActionClass;
use App\Modules\Access\Support\RoleCode;
use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;
use App\Modules\Platform\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Support\AccessFactory;
use Tests\Support\OrganisationFactory;
use Tests\Support\ScreenSource;
use Tests\TestCase;

/**
 * N-SIM1 to N-SIM5. THE SIMULATOR WAS DELIVERED AND NOBODY COULD FIND IT.
 *
 * Product Owner acceptance testing reached L with everything passing and one
 * observation left over: Roles & Access offered exactly one action, "Grant a
 * role", and the Access Simulator could only be reached by typing its URL.
 *
 * A capability reachable only by typing a URL is, to a customer, a capability
 * that was not delivered. CLAUDE.md §4 names this exactly - "navigation that
 * exists technically but the user cannot actually discover".
 *
 * THIS IS A DISCOVERABILITY CORRECTION AND NOTHING ELSE. No second simulator,
 * no second engine, no change to who may reach it. The route, the controller
 * and AccessEngine are untouched; what changed is that the screen now says the
 * door is there.
 */
final class SimulatorDiscoverabilityTest extends TestCase
{
    use RefreshDatabase;

    private const ROUTE = '/console/access/simulator/run';

    private OrganisationFactory $make;

    private AccessFactory $access;

    protected function setUp(): void
    {
        parent::setUp();

        $this->make = new OrganisationFactory;
        $this->access = new AccessFactory;
    }

    /**
     * N-SIM1 and N-SIM2. THE ENTRY POINT EXISTS, IS LABELLED, AND POINTS AT THE
     * EXISTING ROUTE.
     *
     * Asserted against the screen with its comments stripped, because this file
     * documents the decision in prose that quotes the label - and an assertion a
     * docblock can satisfy proves nothing. P1-03 shipped that defect once.
     *
     * Mutation: remove the link; relabel it to something a person would not
     * look for; point it at a route that does not exist.
     */
    public function test_the_roles_and_access_screen_offers_a_labelled_way_into_the_simulator(): void
    {
        $source = ScreenSource::rendered('Pages/Access/Index.jsx');

        $this->assertStringContainsString(
            'Access Simulator',
            $source,
            'Roles & Access offers no visible way into the Access Simulator.'
        );

        $this->assertStringContainsString(
            'href="'.self::ROUTE.'"',
            $source,
            'The entry point does not point at the existing simulator route.'
        );

        // It is a SECONDARY action. Granting access is what this screen is for;
        // checking access must not compete with it.
        $this->assertStringContainsString(
            'org-action org-action-quiet',
            $source,
            'The simulator entry point is not the established secondary action style.'
        );

        /*
         * AND IT IS ALWAYS THERE.
         *
         * Deleting the link fails the assertions above. HIDING it does not, and
         * hiding is the likelier regression: somebody gates it behind a prop
         * that is false in production, or marks it hidden, and the entry point
         * is gone while every assertion above still passes. Both shapes are
         * refused here.
         *
         * WHAT THIS STILL CANNOT DO. There is no JavaScript test runner in this
         * project, so nothing in CI renders the DOM. That the control is
         * genuinely visible, the same height as the primary action and beside
         * it, is established by browser verification and recorded there - not
         * by this file, and this file does not pretend otherwise.
         */
        $this->assertDoesNotMatchRegularExpression(
            '/(\?|&&)\s*\(?\s*<a className="org-action org-action-quiet"/',
            $source,
            'The simulator entry point is rendered conditionally. It must always be there.'
        );

        $this->assertDoesNotMatchRegularExpression(
            '/<a className="org-action org-action-quiet"[^>]*\shidden/',
            $source,
            'The simulator entry point is marked hidden.'
        );
    }

    /**
     * ...and the route it names actually resolves, so the label cannot outlive
     * the thing it opens.
     *
     * Mutation: rename the simulator route.
     */
    public function test_the_route_the_entry_point_names_is_a_real_registered_route(): void
    {
        $uris = [];

        foreach (Route::getRoutes() as $route) {
            $uris[] = '/'.ltrim($route->uri(), '/');
        }

        $this->assertContains(
            self::ROUTE,
            $uris,
            'The screen links to a route that is not registered. The button would 404.'
        );
    }

    /**
     * AN ACTION THAT NAVIGATES MUST NOT LOOK LIKE A LINK.
     *
     * The entry point is an anchor, because it navigates. Without an explicit
     * rule it inherits the global link underline and renders as a link wearing
     * a button's clothes - which is what the browser found on the first run,
     * and what no unit test would ever have noticed.
     *
     * Asserted in the design system rather than on the one screen, because the
     * next action that navigates would inherit the same defect.
     *
     * Mutation: remove the declaration.
     */
    public function test_an_action_that_navigates_is_not_underlined(): void
    {
        $css = (string) file_get_contents(base_path('resources/css/app.css'));

        $start = strpos($css, '.org-action,');
        $this->assertNotFalse($start, 'The shared action rule is gone.');

        $block = substr($css, $start, (int) strpos($css, '}', $start) - $start);

        $this->assertStringContainsString(
            'text-decoration: none',
            $block,
            'An .org-action rendered as an anchor would be underlined and read as a link, not a control.'
        );
    }

    /**
     * N-SIM3. THERE IS STILL EXACTLY ONE SIMULATOR.
     *
     * The correction must not have produced a second implementation, and the
     * screen must not have started answering the question itself.
     *
     * Mutation: add a second controller; have the index evaluate access.
     */
    public function test_no_second_simulator_implementation_exists(): void
    {
        $controllers = glob(base_path('app/Modules/Access/Http/Controllers/*Simulator*.php')) ?: [];

        $this->assertCount(
            1,
            $controllers,
            'There is more than one simulator controller. One engine, one simulator.'
        );

        $index = ScreenSource::rendered('Pages/Access/Index.jsx');

        foreach (['AccessEngine', 'decide(', 'explain(', 'denied_', 'allowed_by_path'] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $index,
                "Roles & Access now contains [{$forbidden}]. The screen must present decisions, never make them."
            );
        }
    }

    /**
     * N-SIM4. ROUTE AUTHORIZATION IS UNCHANGED.
     *
     * Making something visible is not making it permitted. The simulator still
     * carries the ACCESS_ADMIN action class, and somebody who may not reach it
     * still cannot - whether or not a button now exists.
     *
     * Mutation: drop the action class from the simulator route to "match" the
     * new button.
     */
    public function test_the_simulator_route_still_carries_its_action_class(): void
    {
        $matched = null;

        foreach (Route::getRoutes() as $route) {
            if ('/'.ltrim($route->uri(), '/') === self::ROUTE && in_array('GET', $route->methods(), true)) {
                $matched = $route;
                break;
            }
        }

        $this->assertNotNull($matched, 'The simulator route was not found.');

        $declared = implode(' ', $matched->gatherMiddleware());

        $this->assertStringContainsString(
            ActionClass::AccessAdmin->value,
            $declared,
            'The simulator route no longer declares ACCESS_ADMIN. Visibility is not permission.'
        );
    }

    /**
     * ...and the refusal still happens, through the real route.
     *
     * Somebody holding an ordinary business role is refused exactly as before.
     */
    public function test_somebody_without_access_administration_still_cannot_open_the_simulator(): void
    {
        $organisation = $this->make->organisation();
        $person = $this->make->user($organisation);

        $this->access->assignment($person, RoleCode::BusinessUser, $organisation);

        $this->signedInAs($person)
            ->get(self::ROUTE)
            ->assertRedirect(route('auth.access-denied'));
    }

    /**
     * N-SIM5. EVERYTHING THAT WAS ALREADY ON THIS SCREEN IS STILL THERE.
     *
     * The half that stops a "tidy-up" from quietly removing the grant form or a
     * filter while adding the new action.
     *
     * Mutation: drop the role filter; drop the grant action.
     */
    public function test_the_existing_actions_and_filters_are_unchanged(): void
    {
        $source = ScreenSource::rendered('Pages/Access/Index.jsx');

        foreach (['Grant a role', 'Cancel', 'search', 'role', 'state'] as $kept) {
            $this->assertStringContainsString(
                $kept,
                $source,
                "[{$kept}] disappeared from Roles & Access while the simulator entry point was added."
            );
        }

        // And the screen still serves, with its filters intact.
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);

        $props = $this->signedInAs($admin)->get('/console/access')->viewData('page')['props'];

        $this->assertArrayHasKey('filters', $props);
        $this->assertSame(['search' => '', 'role' => '', 'state' => ''], $props['filters']);
        $this->assertArrayHasKey('candidates', $props);
        $this->assertArrayHasKey('roles', $props);
    }

    private function signedInAs(User $user): self
    {
        return $this->withSession([
            EnsureSessionIsCurrent::SESSION_USER_ID => $user->id,
            EnsureSessionIsCurrent::SESSION_AUTHENTICATED_AT => now()->toIso8601String(),
        ]);
    }
}
