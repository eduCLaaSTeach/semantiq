<?php

declare(strict_types=1);

namespace Tests\Feature\Administration;

use App\Modules\Access\Support\ActionClass;
use App\Modules\Access\Support\RoleCatalogue;
use App\Modules\Access\Support\RoleCode;
use App\Modules\Organisation\Support\SystemAdministratorNavigationAuthorizer;
use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;
use App\Modules\Platform\Models\User;
use App\Modules\Platform\Models\UserStatus;
use App\Shared\Navigation\ApprovedMenu;
use App\Shared\Navigation\NavigationNode;
use App\Shared\Navigation\ProductArea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Support\AccessFactory;
use Tests\Support\OrganisationFactory;
use Tests\TestCase;

/**
 * CL-12 AMENDED - THE ORGANISATION ADMINISTRATOR ADMINISTRATION SURFACE.
 *
 * THE FIRST CL-12 RULING WAS AN EIGHT-KEY LIST, delivered in #147 and #148 and
 * verified. It was correct for the instruction it was given: show the System
 * Administration destinations an Organisation Administrator was ALREADY
 * authorised for, and keep the three PlatformAdmin ones hidden.
 *
 * THE PRODUCT OWNER THEN CLARIFIED THE ROLE MODEL ITSELF. Fabric Configuration
 * and System Administration are both ADMINISTRATION areas; an Organisation
 * Administrator is entitled to the complete current administration surface.
 * RoleCatalogue was amended to grant PlatformAdmin, so Identity & SSO, System
 * Health and Integrations are now genuinely theirs rather than shown-and-
 * refused. This file tests that model; it does not pretend the first never
 * existed.
 *
 * WHAT IS BEING WIDENED IS REAL, AND SAYING SO IS THE POINT. An Organisation
 * Administrator can now write the Entra client secret and the deployment-wide
 * integration credentials. The amended Gate B design named that consequence
 * and the Product Owner chose it. The assertions below are therefore not only
 * "can they see it" but "what did NOT move with it": BusinessData absent,
 * system_administrator still not grantable, step-up unchanged.
 *
 * THE ASSERTION THIS FILE EXISTS FOR IS STILL THE AUDITOR.
 *
 * Both administrator roles now hold the SAME four action classes, which makes
 * an ActionClass-derived navigation rule look more tempting than ever. It is
 * still wrong: an AUDITOR holds EvidenceRead, so such a rule hands them
 * Security Status, Access Reviews and Audit as a side effect of a change about
 * administrators. The role is checked FIRST and an Auditor is neither
 * administrator. A10 is an EQUALITY on the Auditor's own areas, kept
 * independent of the other-roles loop, so a failure names them.
 */
final class OrganisationAdministratorNavigationTest extends TestCase
{
    use RefreshDatabase;

    /** The two administration areas, in ProductArea's own render order. */
    private const ADMIN_AREAS = [
        'fabric-configuration',
        'system-administration',
    ];

    /** All 11 System Administration nodes, in ApprovedMenu's declaration order. */
    private const SYSTEM_ADMINISTRATION_LABELS = [
        'Administration Home',
        'Organisation',
        'Users & Groups',
        'Roles & Access',
        'Business Domains',
        'Identity & SSO',
        'Security Status',
        'Access Reviews',
        'Audit',
        'System Health',
        'Integrations',
    ];

    /** All 14 Fabric Configuration nodes. Every one locked. */
    private const FABRIC_LABELS = [
        'Overview',
        'Data Sources',
        'Connect Source',
        'Discovery',
        'Data Classification',
        'Ingestion',
        'Data Quality',
        'Business Model',
        'Security Mapping',
        'Semantic Model',
        'AI Readiness',
        'Pipelines & Refresh',
        'Power BI Publication',
        'Monitoring',
    ];

    /** The three the first CL-12 ruling hid, and the amendment opened. */
    private const NEWLY_AUTHORISED_ROUTES = [
        'identity.entra',
        'system-health.show',
        'integrations.show',
    ];

    /** The eight the first ruling already allowed. They must not regress. */
    private const PREVIOUSLY_AUTHORISED_ROUTES = [
        'administration.home',
        'organisation.profile',
        'people.users',
        'access.index',
        'domains.index',
        'security.baseline',
        'access-reviews.privileged',
        'audit.user-access',
    ];

    private OrganisationFactory $make;

    private AccessFactory $access;

    protected function setUp(): void
    {
        parent::setUp();

        $this->make = new OrganisationFactory;
        $this->access = new AccessFactory;
    }

    // =================================================================
    // A1 - A4. The two areas, and what is in them.
    // =================================================================

    /** A1. EXACTLY two product areas. Not assertContains, which a superset passes. */
    public function test_an_organisation_administrator_sees_exactly_the_two_administration_areas(): void
    {
        $this->assertSame(
            self::ADMIN_AREAS,
            array_column($this->productAreas($this->renderConsoleAs($this->orgAdministrator())), 'key'),
            'The Organisation Administrator product areas are not exactly the two administration '
            .'areas. Either SemantIQ Workplace leaked in, or an administration area is missing.'
        );
    }

    /**
     * A2. SEMANTIQ WORKPLACE IS ABSENT, stated on its own.
     *
     * A1 covers it by equality. This exists because "an administrator was
     * handed the business area" is the failure this ruling most needs to be
     * legible, and a failed array comparison does not say it in words.
     */
    public function test_an_organisation_administrator_does_not_receive_semantiq_workplace(): void
    {
        $this->assertReaderIsNotBroken();

        $this->assertNotContains(
            ProductArea::SemantiqWorkplace->value,
            array_column($this->productAreas($this->renderConsoleAs($this->orgAdministrator())), 'key'),
            'An Organisation Administrator received SemantIQ Workplace. Administration authority '
            .'is not business-data authority; Workplace is governed separately.'
        );
    }

    /** A3. All 11 System Administration nodes, as an equality. */
    public function test_an_organisation_administrator_sees_all_eleven_system_administration_nodes(): void
    {
        $this->assertSame(
            self::SYSTEM_ADMINISTRATION_LABELS,
            $this->labelsIn($this->renderConsoleAs($this->orgAdministrator()), ProductArea::SystemAdministration),
            'The Organisation Administrator System Administration set is not all eleven delivered '
            .'nodes.'
        );
    }

    /**
     * A4. All 14 Fabric Configuration nodes, AND EVERY ONE STILL LOCKED.
     *
     * The second half is the half that matters. Showing the area is only safe
     * because nothing in it is reachable: fourteen locked nodes, route null,
     * no controller, no service. If a node here ever gained a route, this area
     * would stop being a roadmap and start being access - so route === null is
     * asserted per node rather than assumed from the label list.
     */
    public function test_an_organisation_administrator_sees_all_fourteen_fabric_nodes_still_locked(): void
    {
        $area = $this->areaOf($this->renderConsoleAs($this->orgAdministrator()), ProductArea::FabricConfiguration);

        $this->assertSame(
            self::FABRIC_LABELS,
            array_column($area['nodes'], 'label'),
            'The Fabric Configuration set is not the fourteen declared roadmap nodes.'
        );

        foreach ($area['nodes'] as $node) {
            $this->assertNull(
                $node['route'] ?? null,
                "Fabric node [{$node['label']}] carries a route. It is a roadmap entry and must "
                .'have no destination; a route here turns a locked node into real access.'
            );
            $this->assertTrue(
                $node['locked'] ?? false,
                "Fabric node [{$node['label']}] is no longer locked."
            );
        }
    }

    // =================================================================
    // A5. The System Administrator did not move.
    // =================================================================

    /** A5. Their whole roadmap, read from ApprovedMenu rather than typed here. */
    public function test_a_system_administrator_navigation_is_unchanged(): void
    {
        $response = $this->renderConsoleAs($this->userWith(RoleCode::SystemAdministrator));

        $this->assertSame(
            array_map(static fn (ProductArea $a): string => $a->value, ProductArea::cases()),
            array_column($this->productAreas($response), 'key'),
            'The System Administrator no longer sees the complete approved roadmap. CL-12 is '
            .'about the Organisation Administrator and must not move anything else.'
        );

        $this->assertSame(
            $this->everyLabelIn(ProductArea::SystemAdministration),
            $this->labelsIn($response, ProductArea::SystemAdministration),
            'The System Administrator System Administration set changed.'
        );
    }

    /** A5b. And SemantIQ Workplace is still theirs, which is what separates the two roles. */
    public function test_a_system_administrator_still_sees_semantiq_workplace(): void
    {
        $this->assertContains(
            ProductArea::SemantiqWorkplace->value,
            array_column($this->productAreas($this->renderConsoleAs($this->userWith(RoleCode::SystemAdministrator))), 'key'),
            'The System Administrator lost SemantIQ Workplace. With both roles now holding the '
            .'same action classes, roadmap visibility is what still distinguishes them.'
        );
    }

    // =================================================================
    // A6. The grant matrix - what moved, and what must not have.
    // =================================================================

    /** A6. Exactly the four administration classes. */
    public function test_the_organisation_administrator_holds_the_four_administration_action_classes(): void
    {
        $this->assertSame(
            [
                ActionClass::PlatformAdmin,
                ActionClass::OrgAdmin,
                ActionClass::AccessAdmin,
                ActionClass::EvidenceRead,
            ],
            RoleCatalogue::classesFor(RoleCode::OrganisationAdministrator),
            'The Organisation Administrator action classes are not the four the amended CL-12 '
            .'ruling grants.'
        );
    }

    /**
     * A6b. BUSINESSDATA IS ABSENT, and this is the architectural rule.
     *
     * Administration authority is not business-data authority. Every other
     * assertion in this file could pass with BusinessData quietly added.
     */
    public function test_the_organisation_administrator_does_not_hold_business_data(): void
    {
        $this->assertFalse(
            RoleCatalogue::permits(RoleCode::OrganisationAdministrator, ActionClass::BusinessData),
            'An Organisation Administrator gained BusinessData. Administration authority must '
            .'never imply business-data authority.'
        );
    }

    /**
     * A6c. THEY STILL CANNOT MAKE A SYSTEM ADMINISTRATOR.
     *
     * This is the privilege-escalation path the whole role separation exists
     * to close, and it matters MORE after the amendment, not less: the two
     * roles now hold identical action classes, so grantability is one of the
     * two things still separating them.
     */
    public function test_the_organisation_administrator_cannot_grant_system_administrator(): void
    {
        $grantable = RoleCatalogue::grantableBy(RoleCode::OrganisationAdministrator);

        $this->assertNotEmpty($grantable, 'No roles were read, so this guard asserts nothing.');

        $this->assertNotContains(
            RoleCode::SystemAdministrator,
            $grantable,
            'An Organisation Administrator can grant System Administrator. That is the '
            .'privilege-escalation path the role separation exists to close.'
        );

        $this->assertContains(
            RoleCode::SystemAdministrator,
            RoleCatalogue::grantableBy(RoleCode::SystemAdministrator),
            'A System Administrator can no longer grant their own role, so the assertion above '
            .'would pass against a catalogue that grants nobody anything.'
        );
    }

    /** A6d. Step-up is unchanged by the amendment. */
    public function test_step_up_requirements_are_unchanged(): void
    {
        $this->assertSame(
            [RoleCode::SystemAdministrator, RoleCode::OrganisationAdministrator],
            RoleCatalogue::requiringStepUp(),
            'The step-up requirement changed. CL-12 widens navigation and one action class; it '
            .'must not relax re-authentication.'
        );
    }

    /** A6e. The System Administrator role definition itself is untouched. */
    public function test_the_system_administrator_role_definition_is_unchanged(): void
    {
        $this->assertSame(
            [
                ActionClass::PlatformAdmin,
                ActionClass::OrgAdmin,
                ActionClass::AccessAdmin,
                ActionClass::EvidenceRead,
            ],
            RoleCatalogue::classesFor(RoleCode::SystemAdministrator),
            'The System Administrator action classes changed.'
        );
    }

    // =================================================================
    // A7, A8. Route authorisation - the three that opened, the eight that stayed.
    // =================================================================

    /**
     * A7. THE THREE FORMERLY-REFUSED DESTINATIONS NOW RETURN 200.
     *
     * assertOk(), not "not 403". A 302, a 404 and a 500 are all "not 403" and
     * all three would be a broken screen reported as a success.
     *
     * READS ONLY. The write routes behind these screens handle deployment-wide
     * credentials, and proving authorisation is not a reason to touch one -
     * see the write-path test, which asserts the authorisation boundary
     * without performing a credential mutation.
     */
    public function test_the_three_platform_destinations_now_open_for_an_organisation_administrator(): void
    {
        $user = $this->orgAdministrator();

        foreach (self::NEWLY_AUTHORISED_ROUTES as $name) {
            $this->actingAsUser($user)
                ->get(route($name, absolute: false))
                ->assertOk();
        }
    }

    /** A8. And the eight that already worked still do. */
    public function test_every_previously_authorised_destination_still_opens(): void
    {
        $user = $this->orgAdministrator();

        foreach (self::PREVIOUSLY_AUTHORISED_ROUTES as $name) {
            $this->actingAsUser($user)
                ->get(route($name, absolute: false))
                ->assertOk();
        }
    }

    /**
     * A8b. THE WRITE ROUTES ADMIT THEM AT THE AUTHORISATION BOUNDARY, AND THEN
     * THEIR OWN VALIDATION TAKES OVER.
     *
     * Posted with a DELIBERATELY EMPTY body, so what is proved is exactly
     * "RequireActionClass did not refuse" and nothing more. A 422 is the pass
     * condition: it means the request reached the controller's validation.
     *
     * NO CREDENTIAL IS WRITTEN. Sending a real secret to prove an
     * authorisation boundary would be the test mutating production-shaped
     * configuration to demonstrate that it may - which is exactly what the
     * Product Owner forbade.
     *
     * The refusal shape is the thing being excluded: RequireActionClass
     * redirects an HTML request to auth.access-denied rather than returning
     * 403, so a redirect here means the boundary refused them.
     */
    public function test_the_platform_write_routes_admit_them_and_then_validate(): void
    {
        $denied = route('auth.access-denied', absolute: false);

        /*
         * The SAME request, as two roles. That contrast is the whole test: an
         * absolute status code would only say what this controller does with
         * an empty body, which is not the question.
         */
        $administrator = $this->actingAsUser($this->orgAdministrator())
            ->put(route('identity.entra.update', absolute: false), []);

        $auditor = $this->actingAsUser($this->userWith(RoleCode::Auditor))
            ->put(route('identity.entra.update', absolute: false), []);

        $auditor->assertRedirect($denied);

        $this->assertNotEquals(
            $denied,
            $administrator->headers->get('Location'),
            'The write route refused the Organisation Administrator at the authorisation '
            .'boundary. After the amended ruling they hold PlatformAdmin and must pass it.'
        );
    }

    /** A8c. A role holding no PlatformAdmin is still refused there, so A7 is not vacuous. */
    public function test_a_non_administrator_is_still_refused_the_platform_destinations(): void
    {
        $user = $this->userWith(RoleCode::Auditor);

        foreach (self::NEWLY_AUTHORISED_ROUTES as $name) {
            $this->actingAsUser($user)
                ->get(route($name, absolute: false))
                ->assertRedirect(route('auth.access-denied', absolute: false));
        }
    }

    // =================================================================
    // A10, A11, A12. Everybody else.
    // =================================================================

    /**
     * A10. AN AUDITOR'S ADMINISTRATION AREAS ARE EXACTLY [].
     *
     * The assertion that kills the ActionClass-derived implementation, and the
     * one that got MORE important with the amendment: both administrators now
     * hold the same four classes, so "derive it from the classes" looks
     * cleaner than ever and would hand an Auditor three evidence screens.
     *
     * An EQUALITY on the whole area list, not an absence of one label.
     */
    public function test_an_auditor_receives_no_administration_navigation(): void
    {
        $this->assertReaderIsNotBroken();

        $this->assertSame(
            [],
            array_column($this->productAreas($this->renderConsoleAs($this->userWith(RoleCode::Auditor))), 'key'),
            'An Auditor gained administration navigation. If this is Security Status, Access '
            .'Reviews and Audit, the authorizer is deriving visibility from ActionClass instead '
            .'of checking the role first.'
        );
    }

    /** A11. No other role, and no signed-out viewer, gains anything. */
    public function test_no_other_role_gains_administration_navigation(): void
    {
        $this->assertReaderIsNotBroken();

        foreach ([RoleCode::Executive, RoleCode::DomainOwner, RoleCode::Manager, RoleCode::BusinessUser, null] as $role) {
            $this->assertSame(
                [],
                array_column($this->productAreas($this->renderConsoleAs($this->userWith($role))), 'key'),
                'A role CL-12 does not name gained administration navigation: '
                .($role?->value ?? 'no role')
            );
        }
    }

    /** A12. The inactive check stays ahead of the role check. */
    public function test_an_inactive_organisation_administrator_is_refused_by_the_authorizer(): void
    {
        $organisation = $this->make->organisation();
        $user = $this->make->user($organisation, status: UserStatus::Inactive);
        $this->access->assignment($user, RoleCode::OrganisationAdministrator, $organisation);

        request()->attributes->set('semantiq_user', $user);

        $authorizer = app(SystemAdministratorNavigationAuthorizer::class);

        foreach (SystemAdministratorNavigationAuthorizer::ORGANISATION_ADMINISTRATOR_POLICY_KEYS as $key) {
            $this->assertFalse(
                $authorizer->allows($key),
                "An inactive Organisation Administrator was admitted to [{$key}]."
            );
        }
    }

    // =================================================================
    // A13 - A15. Properties of the allow-list itself.
    // =================================================================

    /**
     * A13. THE LIST IS EXACTLY THE TWO ADMINISTRATION AREAS.
     *
     * Derived from ApprovedMenu rather than retyped, so the hand-written
     * constant cannot drift from the menu it claims to mirror. This is what
     * makes writing the list out by hand safe: add a node to either admin area
     * and forget the constant, and this fails.
     */
    public function test_the_allow_list_is_exactly_the_two_admin_areas(): void
    {
        $expected = [];

        foreach (ApprovedMenu::roadmap() as $node) {
            if ($node->area === ProductArea::SemantiqWorkplace) {
                continue;
            }

            $expected[$node->policyKey] = true;
        }

        $this->assertNotEmpty($expected, 'No policy keys were read, so this guard asserts nothing.');

        $this->assertEqualsCanonicalizing(
            array_keys($expected),
            SystemAdministratorNavigationAuthorizer::ORGANISATION_ADMINISTRATOR_POLICY_KEYS,
            'The allow-list and the two administration areas have diverged. Either a node was '
            .'added to an admin area without being listed, or the list names a key no admin '
            .'area declares.'
        );
    }

    /** A14. workplace.view is not in the list, stated as its own property. */
    public function test_the_allow_list_does_not_contain_the_workplace_key(): void
    {
        $this->assertNotContains(
            'workplace.view',
            SystemAdministratorNavigationAuthorizer::ORGANISATION_ADMINISTRATOR_POLICY_KEYS,
            'The allow-list names workplace.view. Administration authority must not reach the '
            .'business area.'
        );

        $this->assertCount(
            12,
            SystemAdministratorNavigationAuthorizer::ORGANISATION_ADMINISTRATOR_POLICY_KEYS,
            'The allow-list is no longer exactly twelve keys - fabric.view plus the eleven '
            .'System Administration keys.'
        );
    }

    /**
     * A15. The membership comparison is STRICT, asserted structurally.
     *
     * STRUCTURAL BECAUSE THE MUTATION IS EQUIVALENT, measured rather than
     * assumed: with a string parameter and twelve non-numeric string keys,
     * loose and strict in_array agree on every input. A requirement whose
     * violation is invisible at runtime is held at the source or not at all;
     * the alternative is to claim a guard that cannot fire.
     *
     * It matters prospectively: the day a numeric-looking key is added, loose
     * membership stops being equivalent and starts being a hole.
     */
    public function test_the_allow_list_membership_comparison_is_strict(): void
    {
        $source = (string) file_get_contents(
            (new \ReflectionClass(SystemAdministratorNavigationAuthorizer::class))->getFileName()
        );

        $this->assertStringContainsString(
            'in_array($policyKey, self::ORGANISATION_ADMINISTRATOR_POLICY_KEYS, true)',
            $source,
            'The allow-list membership test is no longer strict.'
        );
    }

    // =================================================================
    // Helpers.
    // =================================================================

    private function orgAdministrator(): User
    {
        return $this->userWith(RoleCode::OrganisationAdministrator);
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

    private function renderConsoleAs(User $user): TestResponse
    {
        return $this->actingAsUser($user)->get('/console');
    }

    /** @return list<array<string, mixed>> */
    private function productAreas(TestResponse $response): array
    {
        return $response->viewData('page')['props']['productAreas'] ?? [];
    }

    /** @return array<string, mixed> */
    private function areaOf(TestResponse $response, ProductArea $area): array
    {
        foreach ($this->productAreas($response) as $candidate) {
            if ($candidate['key'] === $area->value) {
                return $candidate;
            }
        }

        $this->fail("The [{$area->value}] area was not rendered at all.");
    }

    /** @return list<string> */
    private function labelsIn(TestResponse $response, ProductArea $area): array
    {
        foreach ($this->productAreas($response) as $candidate) {
            if ($candidate['key'] === $area->value) {
                return array_map(static fn (array $node): string => $node['label'], $candidate['nodes']);
            }
        }

        return [];
    }

    /** @return list<string> */
    private function everyLabelIn(ProductArea $area): array
    {
        return array_values(array_map(
            static fn (NavigationNode $node): string => $node->label,
            array_filter(
                ApprovedMenu::roadmap(),
                static fn (NavigationNode $node): bool => $node->area === $area
            )
        ));
    }

    /**
     * The reader works.
     *
     * "[]" is what a correctly filtered menu and a broken reader both look
     * like, so no absence in this file is asserted without first showing the
     * same helper returns something for somebody.
     */
    private function assertReaderIsNotBroken(): void
    {
        $this->assertGreaterThan(
            2,
            count($this->labelsIn(
                $this->renderConsoleAs($this->userWith(RoleCode::SystemAdministrator)),
                ProductArea::SystemAdministration
            )),
            'The navigation reader returned almost nothing for a System Administrator, so every '
            .'absence asserted in this file would pass against a reader that reads nothing.'
        );
    }
}
