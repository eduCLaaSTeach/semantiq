<?php

declare(strict_types=1);

namespace Tests\Feature\Administration;

use App\Modules\Access\Support\RoleCode;
use App\Modules\Organisation\Support\SystemAdministratorNavigationAuthorizer;
use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;
use App\Modules\Platform\Models\User;
use App\Modules\Platform\Models\UserStatus;
use App\Shared\Navigation\ApprovedMenu;
use App\Shared\Navigation\DenyAllNavigationAuthorizer;
use App\Shared\Navigation\NavigationRegistry;
use App\Shared\Navigation\ProductArea;
use Illuminate\Contracts\Routing\Registrar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Support\AccessFactory;
use Tests\Support\OrganisationFactory;
use Tests\TestCase;

/**
 * G18 - D-182, THE ONE NARROW EXCEPTION TO D-19.
 *
 * "D-19 remains in force for System Administration navigation generally, except
 * that D-182 explicitly makes Administration Home visible to Organisation
 * Administrator because the route itself is OrgAdmin and the screen is their
 * authorised administration landing point."
 *
 * V4 IS AN EQUALITY, NOT FOUR ABSENCES, and that is deliberate. "Does not see
 * Users & Groups" is satisfied by a menu that renders nothing at all, so an
 * authorizer that accidentally admitted a FIFTH node would pass four separate
 * absence assertions. The whole node set is asserted instead.
 *
 * V5 KEEPS THE TWO CONTROLS APART. Navigation visibility is UX; the route
 * authorises on its own. If this exception were deleted the menu entry would
 * disappear and NOBODY'S 200 would change - which is the property that makes
 * the sidebar safe to widen at all.
 */
final class AdministrationHomeNavigationTest extends TestCase
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

    private function userWith(?RoleCode $role, UserStatus $status = UserStatus::Active): User
    {
        $organisation = $this->make->organisation();
        $user = $this->make->user($organisation, status: $status);

        if ($role !== null) {
            $this->access->assignment($user, $role, $organisation);
        }

        return $user;
    }

    /**
     * The System Administration labels this viewer actually receives.
     *
     * THE KEY COMES FROM THE ENUM, not from a string typed here. The first
     * version of this helper looked for 'system_administration' - underscores
     * where the enum has hyphens - so it returned [] for EVERY viewer, and the
     * "an unrelated role sees nothing" case passed while checking nothing. That
     * is the second failure shape CLAUDE.md names, caught here only because the
     * positive cases failed alongside it.
     *
     * isReadable() below is what stops it recurring: no absence is asserted in
     * this file without the same helper first being shown to return something.
     *
     * @return list<string>
     */
    private function systemAdministrationNodes(TestResponse $response): array
    {
        $areas = $response->viewData('page')['props']['productAreas'] ?? [];

        foreach ($areas as $area) {
            if ($area['key'] !== ProductArea::SystemAdministration->value) {
                continue;
            }

            return array_map(static fn (array $node): string => $node['label'], $area['nodes']);
        }

        return [];
    }

    /**
     * The reader works. Called before every absence assertion, because "[]" is
     * what a broken reader and a correctly filtered menu both look like.
     */
    private function assertTheReaderIsNotBroken(): void
    {
        $labels = $this->systemAdministrationNodes(
            $this->actingAsUser($this->userWith(RoleCode::SystemAdministrator))->get('/console')
        );

        $this->assertGreaterThan(
            5,
            count($labels),
            'The navigation reader returned almost nothing for a System Administrator, so every '
            .'absence asserted in this file would pass against a reader that reads nothing.'
        );
    }

    /** V1. A System Administrator sees it, as they saw everything before. */
    public function test_a_system_administrator_sees_administration_home(): void
    {
        $response = $this->actingAsUser($this->userWith(RoleCode::SystemAdministrator))->get('/console');

        $this->assertContains('Administration Home', $this->systemAdministrationNodes($response));
    }

    /**
     * V2. AN ORGANISATION ADMINISTRATOR SEES IT. This is the whole of D-182,
     * and it is the assertion the mutation must kill.
     */
    public function test_an_organisation_administrator_sees_administration_home(): void
    {
        $response = $this->actingAsUser($this->userWith(RoleCode::OrganisationAdministrator))->get('/console');

        $this->assertContains(
            'Administration Home',
            $this->systemAdministrationNodes($response),
            'An Organisation Administrator cannot find the screen they are authorised for. D-182 '
            .'exists because a home page nobody can navigate to is not a home page.'
        );
    }

    /**
     * V4. AND NOTHING ELSE. Asserted as an EQUALITY over the whole area.
     *
     * The other four OrgAdmin System Administration screens - Organisation,
     * Users & Groups, Roles & Access, Business Domains - stay hidden. They are
     * reachable by URL and that remains a carried navigation item; D-182 was
     * granted to one node for one reason, and widening the rest would have been
     * a change nobody asked for.
     *
     * Mutation: make the exception `return true` for any key. This fails with
     * eleven labels instead of one, and the System Administrator cases above
     * stay green - which is exactly why the equality is here.
     */
    public function test_an_organisation_administrator_sees_that_node_and_no_other(): void
    {
        $response = $this->actingAsUser($this->userWith(RoleCode::OrganisationAdministrator))->get('/console');

        $this->assertSame(
            ['Administration Home'],
            $this->systemAdministrationNodes($response),
            'D-182 is one explicit exception. Something else in System Administration became '
            .'visible to an Organisation Administrator as a side effect.'
        );
    }

    /**
     * V3. UNRELATED ROLES SEE NOTHING, including the roles that can reach other
     * console screens.
     *
     * An Auditor holds EvidenceRead and opens all four Security Status screens,
     * so "they hold no console authority" is not why they are excluded - the
     * exception names one key and one role, and an Auditor is neither.
     */
    public function test_unrelated_roles_do_not_see_administration_home(): void
    {
        $this->assertTheReaderIsNotBroken();

        foreach ([RoleCode::Auditor, RoleCode::Executive, RoleCode::BusinessUser, RoleCode::DomainOwner, null] as $role) {
            $labels = $this->systemAdministrationNodes(
                $this->actingAsUser($this->userWith($role))->get('/console')
            );

            $this->assertNotContains(
                'Administration Home',
                $labels,
                'A role D-182 does not name can see Administration Home.'
            );
        }
    }

    /** An inactive Organisation Administrator is refused before D-182 is consulted. */
    public function test_an_inactive_organisation_administrator_sees_nothing(): void
    {
        $user = $this->userWith(RoleCode::OrganisationAdministrator, UserStatus::Inactive);

        // The session middleware refuses them outright, so the menu never
        // renders. The authorizer's own check is asserted directly below,
        // because "the middleware stopped it" is not the same claim.
        $this->actingAsUser($user)->get('/console')->assertRedirect(route('auth.account-inactive'));

        $request = request();
        $request->attributes->set('semantiq_user', $user);

        $this->assertFalse(
            app(SystemAdministratorNavigationAuthorizer::class)->allows(
                SystemAdministratorNavigationAuthorizer::ORGANISATION_ADMINISTRATOR_EXCEPTION
            ),
            'The D-182 exception admitted an inactive person.'
        );
    }

    /**
     * V5. THE ROUTE AUTHORISES ON ITS OWN.
     *
     * Menu visibility is never the control. Both administrator kinds get 200
     * with the navigation authorizer replaced by one that shows NOTHING - so
     * the 200 cannot be coming from the sidebar, and removing D-182 tomorrow
     * would change who can find the screen and not who can open it.
     */
    public function test_the_route_authorises_independently_of_the_menu(): void
    {
        /*
         * The REGISTRY is rebound, not just the authorizer. NavigationRegistry
         * is a singleton that takes its authorizer at construction, so binding
         * the interface alone changes nothing - and this test passed with the
         * full menu still rendering until the assertion below caught it. That
         * is the third failure shape CLAUDE.md names: a guard the framework was
         * quietly defeating.
         */
        $this->app->singleton(
            NavigationRegistry::class,
            fn ($app): NavigationRegistry => new NavigationRegistry(
                new DenyAllNavigationAuthorizer,
                $app->make(Registrar::class),
            ),
        );

        foreach ([RoleCode::SystemAdministrator, RoleCode::OrganisationAdministrator] as $role) {
            $user = $this->userWith($role);

            $response = $this->actingAsUser($user)->get('/console/administration');

            $response->assertOk();

            $this->assertSame(
                [],
                $this->systemAdministrationNodes($response),
                'The menu was not actually suppressed, so this proves nothing about the route.'
            );

            $this->assertNotEmpty(
                $response->viewData('page')['props']['areas'] ?? [],
                'The page rendered without its areas, so the 200 says nothing about the screen.'
            );
        }
    }

    /**
     * THE EXCEPTION NAMES THE KEY THE MENU USES, not a second copy of the
     * string.
     *
     * Mutation: change the constant to 'administration.home'. The authorizer
     * then admits nothing, V2 fails - and without this assertion the failure
     * would look like a navigation bug rather than two strings that drifted.
     */
    public function test_the_exception_names_the_policy_key_the_menu_declares(): void
    {
        $keys = [];

        foreach (ApprovedMenu::roadmap() as $node) {
            if ($node->label === 'Administration Home') {
                $keys[] = $node->policyKey;
            }
        }

        $this->assertSame(
            [SystemAdministratorNavigationAuthorizer::ORGANISATION_ADMINISTRATOR_EXCEPTION],
            $keys,
            'The D-182 exception and the Administration Home node name different policy keys.'
        );
    }
}
