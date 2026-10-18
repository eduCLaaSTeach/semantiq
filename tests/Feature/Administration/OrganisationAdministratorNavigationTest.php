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
 * CL-12 - THE ORGANISATION ADMINISTRATOR NAVIGATION SET.
 *
 * An Organisation Administrator was authorised for EIGHT System Administration
 * screens and could discover ONE. All eight returned 200 when typed into the
 * address bar; seven were absent from the sidebar. That is "navigation that
 * exists technically but the user cannot actually discover" - the last item on
 * the professional-polish gate, and a discoverability defect rather than an
 * access defect.
 *
 * WHAT THIS FILE PROVES, AND WHAT IT DELIBERATELY DOES NOT.
 *
 * It proves the MENU now shows the eight approved nodes and not the three
 * platform ones, and - separately - that ROUTE AUTHORISATION did not move an
 * inch. Those are two code paths on purpose: the blueprint's rule is that menu
 * hiding is never the access control, so this file asserts both ends and never
 * lets one stand in for the other.
 *
 * THE ASSERTION THIS FILE EXISTS FOR IS A10.
 *
 * The obvious "improvement" to the authorizer is to compute visibility from the
 * ActionClass each route requires. It is wrong, and it is wrong in a way no
 * Organisation Administrator assertion can see: an AUDITOR holds EvidenceRead,
 * so a class-derived rule hands them Security Status, Access Reviews and Audit
 * as a side effect of a change about somebody else. A10 is an EQUALITY on the
 * Auditor's own set, kept independent of A11's role loop, because a loose
 * absence check on one label would miss the other two.
 */
final class OrganisationAdministratorNavigationTest extends TestCase
{
    use RefreshDatabase;

    /** The eight CL-12 approves, in ApprovedMenu's existing declaration order. */
    private const APPROVED_LABELS = [
        'Administration Home',
        'Organisation',
        'Users & Groups',
        'Roles & Access',
        'Business Domains',
        'Security Status',
        'Access Reviews',
        'Audit',
    ];

    /** PlatformAdmin, and an Organisation Administrator is not one. */
    private const PLATFORM_ONLY_LABELS = [
        'Identity & SSO',
        'System Health',
        'Integrations',
    ];

    /** The seven approved destinations beyond the landing point, by route name. */
    private const APPROVED_ROUTES = [
        'organisation.profile',
        'people.users',
        'access.index',
        'domains.index',
        'security.baseline',
        'access-reviews.privileged',
        'audit.user-access',
    ];

    private const PLATFORM_ONLY_ROUTES = [
        'identity.entra',
        'system-health.show',
        'integrations.show',
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
    // A1 - A4. The menu an Organisation Administrator receives.
    // =================================================================

    /** A1. The whole set, as an EQUALITY. Not assertContains, which a superset passes. */
    public function test_an_organisation_administrator_sees_exactly_the_approved_eight(): void
    {
        $labels = $this->systemAdministrationNodes(
            $this->actingAsUser($this->orgAdministrator())->get('/console')
        );

        $this->assertSame(
            self::APPROVED_LABELS,
            $labels,
            'The Organisation Administrator navigation is not exactly the eight CL-12 approves.'
        );
    }

    /**
     * A2, A3, A4. Each platform-only node named separately.
     *
     * A1 already covers these by equality. They are here anyway because a
     * failure that says "Identity & SSO became visible" is a different message
     * from a failed array comparison, and the three are the ones a reviewer
     * will look for by name.
     */
    public function test_an_organisation_administrator_does_not_see_identity_and_sso(): void
    {
        $this->assertPlatformNodeIsHidden('Identity & SSO');
    }

    public function test_an_organisation_administrator_does_not_see_system_health(): void
    {
        $this->assertPlatformNodeIsHidden('System Health');
    }

    public function test_an_organisation_administrator_does_not_see_integrations(): void
    {
        $this->assertPlatformNodeIsHidden('Integrations');
    }

    // =================================================================
    // A5. The System Administrator is untouched.
    // =================================================================

    /**
     * A5. Their System Administration set is the complete menu, unchanged.
     *
     * Read from ApprovedMenu rather than typed here, so adding a node to the
     * menu without deciding whether an Organisation Administrator may see it
     * fails A1 instead of quietly passing both.
     */
    public function test_a_system_administrator_sees_the_whole_area_unchanged(): void
    {
        $labels = $this->systemAdministrationNodes(
            $this->actingAsUser($this->userWith(RoleCode::SystemAdministrator))->get('/console')
        );

        $this->assertSame(
            $this->everySystemAdministrationLabel(),
            $labels,
            'The System Administrator navigation changed. CL-12 is about the Organisation '
            .'Administrator and must not move anything else.'
        );

        foreach (self::PLATFORM_ONLY_LABELS as $label) {
            $this->assertContains($label, $labels, "A System Administrator lost [{$label}].");
        }
    }

    /** A5b. And they still see all three product areas. */
    public function test_a_system_administrator_still_sees_every_product_area(): void
    {
        $areas = $this->productAreas(
            $this->actingAsUser($this->userWith(RoleCode::SystemAdministrator))->get('/console')
        );

        $this->assertSame(
            array_map(static fn (ProductArea $a): string => $a->value, ProductArea::cases()),
            array_column($areas, 'key'),
            'The System Administrator no longer sees the complete approved roadmap.'
        );
    }

    // =================================================================
    // A6. Authorisation did not move.
    // =================================================================

    /** A6. The grant matrix is exactly what it was. CL-12 widens no permission. */
    public function test_the_organisation_administrator_action_classes_are_unchanged(): void
    {
        $this->assertSame(
            [ActionClass::OrgAdmin, ActionClass::AccessAdmin, ActionClass::EvidenceRead],
            RoleCatalogue::classesFor(RoleCode::OrganisationAdministrator),
            'The Organisation Administrator grant matrix changed. CL-12 is a presentation '
            .'correction and must widen no permission.'
        );

        $this->assertFalse(
            RoleCatalogue::permits(RoleCode::OrganisationAdministrator, ActionClass::PlatformAdmin),
            'An Organisation Administrator gained PlatformAdmin.'
        );
    }

    // =================================================================
    // A7. The seven open. EXACT 200, not "not 403".
    // =================================================================

    /**
     * A7. Each approved destination returns 200 for a real Organisation
     * Administrator holding the organisation context those routes require.
     *
     * assertOk(), NOT "assert not 403". A 302, a 404 and a 500 are all "not
     * 403", and all three are ways of calling a broken screen a success - the
     * exact shape CLAUDE.md section 2 names.
     */
    public function test_every_approved_destination_opens_for_an_organisation_administrator(): void
    {
        $user = $this->orgAdministrator();

        foreach (self::APPROVED_ROUTES as $name) {
            $this->actingAsUser($user)
                ->get(route($name, absolute: false))
                ->assertOk();
        }
    }

    // =================================================================
    // A8. The three deny, in the way they actually deny.
    // =================================================================

    /**
     * A8. An HTML request to a PlatformAdmin route REDIRECTS to
     * auth.access-denied - it does not return 403.
     *
     * Read from RequireActionClass::refuse(), which returns a 403 only when
     * $request->expectsJson(). A test asserting an HTML 403 here would fail
     * against correct code, which is worse than no test: it would be removed
     * rather than believed.
     */
    public function test_every_platform_only_destination_denies_an_organisation_administrator(): void
    {
        $user = $this->orgAdministrator();

        foreach (self::PLATFORM_ONLY_ROUTES as $name) {
            $this->actingAsUser($user)
                ->get(route($name, absolute: false))
                ->assertRedirect(route('auth.access-denied', absolute: false));
        }
    }

    /** A8b. And a System Administrator still opens all three, so A8 is not passing on a broken route. */
    public function test_a_system_administrator_still_opens_every_platform_only_destination(): void
    {
        $user = $this->userWith(RoleCode::SystemAdministrator);

        foreach (self::PLATFORM_ONLY_ROUTES as $name) {
            $this->actingAsUser($user)->get(route($name, absolute: false))->assertOk();
        }
    }

    // =================================================================
    // A9. No Phase 2 or Phase 3 menu appears.
    // =================================================================

    /** A9. One product area, System Administration, and nothing from a phase that is not delivered. */
    public function test_no_future_phase_area_becomes_visible_to_an_organisation_administrator(): void
    {
        $areas = $this->productAreas($this->actingAsUser($this->orgAdministrator())->get('/console'));

        $this->assertSame(
            [ProductArea::SystemAdministration->value],
            array_column($areas, 'key'),
            'An Organisation Administrator gained a product area from a phase that is not delivered.'
        );
    }

    // =================================================================
    // A10. THE ONE THAT KILLS THE CLASS-DERIVED IMPLEMENTATION.
    // =================================================================

    /**
     * A10. An Auditor's System Administration navigation is EXACTLY [].
     *
     * An Auditor holds EvidenceRead and genuinely opens all four Security
     * Status screens, so "they hold no console authority" is not why they see
     * nothing here - CL-12 names a role, and an Auditor is not it.
     *
     * AN EQUALITY, NOT AN ABSENCE. The rejected class-derived implementation
     * gives an Auditor THREE labels; asserting the absence of one would miss
     * the other two. Kept out of A11's loop so a failure names the Auditor.
     */
    public function test_an_auditor_sees_no_system_administration_navigation(): void
    {
        $this->assertTheReaderIsNotBroken();

        $this->assertSame(
            [],
            $this->systemAdministrationNodes(
                $this->actingAsUser($this->userWith(RoleCode::Auditor))->get('/console')
            ),
            'An Auditor gained System Administration navigation. If this is Security Status, '
            .'Access Reviews and Audit, the authorizer is deriving visibility from ActionClass '
            .'instead of the explicit CL-12 allow-list.'
        );
    }

    // =================================================================
    // A11, A12. Everybody else, and the inactive case.
    // =================================================================

    /** A11. No other role, and no signed-out viewer, gains anything. */
    public function test_no_other_role_gains_system_administration_navigation(): void
    {
        $this->assertTheReaderIsNotBroken();

        foreach ([RoleCode::Executive, RoleCode::DomainOwner, RoleCode::Manager, RoleCode::BusinessUser, null] as $role) {
            $this->assertSame(
                [],
                $this->systemAdministrationNodes(
                    $this->actingAsUser($this->userWith($role))->get('/console')
                ),
                'A role CL-12 does not name gained System Administration navigation: '
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
    // A13, A14. Properties of the allow-list itself.
    // =================================================================

    /** A13. Every key is one ApprovedMenu actually declares, so none is silently dead. */
    public function test_every_allow_list_key_is_a_policy_key_the_menu_declares(): void
    {
        $declared = $this->systemAdministrationPolicyKeys();

        $this->assertNotEmpty($declared, 'No policy keys were read, so this guard asserts nothing.');

        foreach (SystemAdministratorNavigationAuthorizer::ORGANISATION_ADMINISTRATOR_POLICY_KEYS as $key) {
            $this->assertContains(
                $key,
                $declared,
                "The allow-list names [{$key}], which ApprovedMenu does not declare. It is a dead "
                .'entry that shows nothing and hides a typo.'
            );
        }
    }

    /**
     * A14. No PlatformAdmin-gated key, stated as its own property.
     *
     * A2-A4 would catch this through the rendered menu. This catches it in the
     * list itself, so the failure names the constant rather than a screen.
     */
    public function test_the_allow_list_contains_no_platform_only_policy_key(): void
    {
        foreach (['identity.view', 'system-health.view', 'integrations.view', 'fabric.view', 'workplace.view'] as $forbidden) {
            $this->assertNotContains(
                $forbidden,
                SystemAdministratorNavigationAuthorizer::ORGANISATION_ADMINISTRATOR_POLICY_KEYS,
                "The allow-list names [{$forbidden}], which an Organisation Administrator is not "
                .'authorised for.'
            );
        }

        $this->assertCount(
            8,
            SystemAdministratorNavigationAuthorizer::ORGANISATION_ADMINISTRATOR_POLICY_KEYS,
            'The allow-list is no longer exactly eight keys.'
        );
    }

    /**
     * A15. The membership comparison is STRICT, asserted structurally.
     *
     * THIS GUARD IS STRUCTURAL BECAUSE THE MUTATION IS EQUIVALENT, and that was
     * measured rather than assumed. Dropping `true` from in_array() was run as
     * mutation M11 and every behavioural test stayed green - correctly, because
     * with a `string` parameter and eight non-numeric string keys, loose and
     * strict agree on every input. Probing the five platform keys, the empty
     * string, "0", "0.0", "1e2", "100", a trailing-space key and a
     * different-case key produced ZERO differences.
     *
     * So there is no behavioural assertion to write. The Product Owner
     * nevertheless required strict comparison, and a requirement whose
     * violation is invisible at runtime is held at the source or not at all -
     * the alternative is to claim a guard that cannot fire, which is the
     * failure CLAUDE.md section 2 names.
     *
     * It matters prospectively: the day a key is added that PHP would compare
     * numerically, loose membership stops being equivalent and starts being a
     * hole. This fails then, before that key ships.
     */
    public function test_the_allow_list_membership_comparison_is_strict(): void
    {
        $source = (string) file_get_contents(
            (new \ReflectionClass(SystemAdministratorNavigationAuthorizer::class))->getFileName()
        );

        $this->assertStringContainsString(
            'in_array($policyKey, self::ORGANISATION_ADMINISTRATOR_POLICY_KEYS, true)',
            $source,
            'The allow-list membership test is no longer strict. It is equivalent today for these '
            .'eight keys, and stops being equivalent the moment a numeric-looking key is added.'
        );
    }

    // =================================================================
    // Helpers.
    // =================================================================

    private function assertPlatformNodeIsHidden(string $label): void
    {
        $this->assertTheReaderIsNotBroken();

        $this->assertNotContains(
            $label,
            $this->systemAdministrationNodes(
                $this->actingAsUser($this->orgAdministrator())->get('/console')
            ),
            "[{$label}] is PlatformAdmin and became visible to an Organisation Administrator."
        );
    }

    /** An active Organisation Administrator holding the organisation those routes require. */
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

    /** @return list<array<string, mixed>> */
    private function productAreas(TestResponse $response): array
    {
        return $response->viewData('page')['props']['productAreas'] ?? [];
    }

    /** @return list<string> */
    private function systemAdministrationNodes(TestResponse $response): array
    {
        foreach ($this->productAreas($response) as $area) {
            if ($area['key'] !== ProductArea::SystemAdministration->value) {
                continue;
            }

            return array_map(static fn (array $node): string => $node['label'], $area['nodes']);
        }

        return [];
    }

    /**
     * The reader works.
     *
     * "[]" is what a correctly filtered menu and a broken reader both look
     * like, so no absence in this file is asserted without first showing the
     * same helper returns something for somebody.
     */
    private function assertTheReaderIsNotBroken(): void
    {
        $this->assertGreaterThan(
            5,
            count($this->systemAdministrationNodes(
                $this->actingAsUser($this->userWith(RoleCode::SystemAdministrator))->get('/console')
            )),
            'The navigation reader returned almost nothing for a System Administrator, so every '
            .'absence asserted in this file would pass against a reader that reads nothing.'
        );
    }

    /** @return list<string> */
    private function everySystemAdministrationLabel(): array
    {
        return array_values(array_map(
            static fn (NavigationNode $node): string => $node->label,
            array_filter(
                ApprovedMenu::roadmap(),
                static fn (NavigationNode $node): bool => $node->area === ProductArea::SystemAdministration
            )
        ));
    }

    /** @return list<string> */
    private function systemAdministrationPolicyKeys(): array
    {
        return array_values(array_map(
            static fn (NavigationNode $node): string => $node->policyKey,
            array_filter(
                ApprovedMenu::roadmap(),
                static fn (NavigationNode $node): bool => $node->area === ProductArea::SystemAdministration
            )
        ));
    }
}
