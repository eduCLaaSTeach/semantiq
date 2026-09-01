<?php

declare(strict_types=1);

namespace Tests\Feature\Organisation;

use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;
use App\Modules\Platform\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Support\OrganisationFactory;
use Tests\TestCase;

/**
 * The Organisation sub-navigation.
 *
 * Six sections used to be reachable only from a row of buttons at the bottom of
 * Company Profile - so from any other section there was no way across at all,
 * and the controls were styled as actions when they are navigation.
 *
 * They are now one route-backed tab strip on every Organisation screen. The
 * "route-backed" part is the requirement, not a detail: real <a href> links are
 * what make the URL change, browser back and forward behave, a refresh keep the
 * section, and a pasted URL select the right tab. A client-only switch hiding
 * six screens behind one URL would break all of those, so these guards assert
 * links and real routes rather than merely "six things are rendered".
 */
final class OrganisationTabNavigationTest extends TestCase
{
    use RefreshDatabase;

    private const TABS = __DIR__.'/../../../resources/js/Components/OrganisationTabs.jsx';

    private const SHELL = __DIR__.'/../../../resources/js/Components/OrganisationPage.jsx';

    /** The six sections, in the Product Owner's order, with their routes. */
    private const EXPECTED = [
        'Company Profile' => '/console/organisation',
        'Legal Entities' => '/console/organisation/legal-entities',
        'Business Units' => '/console/organisation/business-units',
        'Departments' => '/console/organisation/departments',
        'Teams' => '/console/organisation/teams',
        'Management Hierarchy' => '/console/organisation/hierarchy',
    ];

    private OrganisationFactory $make;

    protected function setUp(): void
    {
        parent::setUp();

        $this->make = new OrganisationFactory;
    }

    /** 1 and 2. All six tabs, in the approved order. */
    public function test_all_six_tabs_appear_in_the_approved_order(): void
    {
        $this->assertSame(
            self::EXPECTED,
            $this->declaredTabs(),
            'The Organisation tabs are not the six approved sections in the approved order.'
        );
    }

    /**
     * The tabs the component actually declares.
     *
     * Read from the component, never from this test's own constant. The route
     * checks below first asserted EXPECTED against the router - which validated
     * the constant and not the code, so pointing a real tab at a route that did
     * not exist passed. Caught by mutation.
     *
     * @return array<string, string>
     */
    private function declaredTabs(): array
    {
        preg_match_all(
            "/\{ label: '([^']+)', href: '([^']+)' \}/",
            file_get_contents(self::TABS),
            $matches,
            PREG_SET_ORDER
        );

        $declared = [];

        foreach ($matches as [, $label, $href]) {
            $declared[$label] = $href;
        }

        $this->assertNotEmpty($declared, 'No tabs were found, so the guards using them prove nothing.');

        return $declared;
    }

    /**
     * 3. Every tab points at a route that actually exists.
     *
     * A tab pointing at nothing is the placeholder the design forbids, and it
     * would 404 for whoever clicked it.
     *
     * Mutation: change a tab href to a path no route serves.
     */
    public function test_every_tab_points_at_a_delivered_route(): void
    {
        $uris = [];

        foreach (Route::getRoutes() as $route) {
            if (in_array('GET', $route->methods(), true)) {
                $uris[] = '/'.trim($route->uri(), '/');
            }
        }

        foreach ($this->declaredTabs() as $label => $href) {
            $this->assertContains(
                $href,
                $uris,
                "The [{$label}] tab points at [{$href}], which no GET route serves."
            );
        }
    }

    /**
     * 3, continued. Each route really does serve its screen to an administrator.
     *
     * The route existing is not the claim; the claim is that clicking the tab
     * lands on a page.
     */
    public function test_every_tab_route_serves_its_screen(): void
    {
        $admin = $this->administrator();

        foreach ($this->declaredTabs() as $label => $href) {
            $this->actingAsUser($admin)
                ->get($href)
                ->assertOk("The [{$label}] tab's route did not serve a screen.");
        }
    }

    /**
     * 4. The right tab is active on every Organisation route, including the
     * detail screens.
     *
     * Company Profile is the case that catches a naive implementation: every
     * other Organisation URL starts with its path, so a "starts with" test
     * would light it up on all six screens at once.
     *
     * Mutation: match Company Profile with startsWith rather than an exact path.
     */
    public function test_the_active_tab_is_correct_for_every_organisation_route(): void
    {
        $source = file_get_contents(self::TABS);

        // The active-tab rule lives in one exported function, so it is testable
        // rather than buried in the render.
        $this->assertStringContainsString(
            'export function activeTab(path)',
            $source,
            'There is no single, inspectable rule deciding which tab is active.'
        );

        $this->assertMatchesRegularExpression(
            "/clean === '\\/console\\/organisation' \\? '\\/console\\/organisation' : null/",
            $source,
            'Company Profile does not match on its exact path, so it would appear active on every '
            .'Organisation screen.'
        );

        // A detail screen keeps its list's tab selected.
        $this->assertMatchesRegularExpression(
            '/clean\.startsWith\(`\$\{tab\.href\}\/`\)/',
            $source,
            'A detail screen does not keep its section tab selected.'
        );
    }

    /**
     * 5. The old bottom button row is gone, from the markup and the styles.
     *
     * Mutation: restore the "Organisation sections" block in Profile.jsx.
     */
    public function test_the_old_organisation_sections_button_row_is_gone(): void
    {
        $profile = file_get_contents(__DIR__.'/../../../resources/js/Pages/Organisation/Profile.jsx');

        $this->assertStringNotContainsString(
            'org-next',
            $profile,
            'The bottom section-button row is still on Company Profile.'
        );

        $this->assertStringNotContainsString(
            'Organisation sections',
            $profile,
            'The "Organisation sections" heading is still on Company Profile.'
        );

        // And no Organisation screen navigates between sections by script.
        foreach (glob(__DIR__.'/../../../resources/js/Pages/Organisation/*.jsx') ?: [] as $page) {
            $source = file_get_contents($page);

            foreach (array_values($this->declaredTabs()) as $href) {
                $this->assertStringNotContainsString(
                    "router.get('{$href}')",
                    $source,
                    basename($page).' moves between sections with a scripted visit. Sections are '
                    .'navigation: they are links.'
                );
            }
        }
    }

    /**
     * 6. Route-backed navigation, so browser history behaves.
     *
     * This is the requirement that a client-only tab switch would fail, and it
     * cannot be observed from PHP - so what is asserted here is the property
     * that produces it: real anchors with real hrefs and aria-current, and no
     * ARIA tab widget mixed in (the standard forbids mixing the two).
     *
     * The observable half - back, forward, refresh and a pasted URL - is walked
     * in a browser and recorded in the verification document.
     *
     * Mutation: render the tabs as buttons, or add role="tab".
     */
    public function test_the_tabs_are_route_backed_links_not_a_client_side_switch(): void
    {
        $source = file_get_contents(self::TABS);

        $this->assertMatchesRegularExpression(
            '/<a\s+href=\{tab\.href\}/',
            $source,
            'The tabs are not real links, so the URL would not change and browser history would break.'
        );

        $this->assertStringContainsString(
            "aria-current={active === tab.href ? 'page' : undefined}",
            $source,
            'The active tab is not marked with aria-current="page".'
        );

        foreach (['<button', 'role="tab"', 'role="tablist"', 'role="tabpanel"', 'onClick'] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source,
                "The tab strip uses [{$forbidden}]. Route-backed links and the ARIA tab widget are "
                .'two different patterns and the standard forbids mixing them on one strip.'
            );
        }

        // A <nav> landmark, as the standard requires.
        $this->assertMatchesRegularExpression('/<nav className="org-tabs"/', $source);
    }

    /**
     * 7. The strip scrolls inside itself on a narrow screen, so the PAGE never
     * scrolls sideways.
     *
     * This is the same class of defect as the Organisation tables, which slid
     * the whole page 130px at 390px. A strip of six tabs is wider than a phone
     * by construction, so without this it would do it again.
     *
     * Mutation: drop overflow-x from .org-tabs, or let the strip wrap.
     */
    public function test_the_tab_strip_scrolls_within_itself_on_a_narrow_screen(): void
    {
        $css = preg_replace(
            '#/\*.*?\*/#s',
            '',
            file_get_contents(__DIR__.'/../../../resources/css/app.css')
        );

        preg_match('/\.org-tabs\s*\{([^}]*)\}/', $css, $strip);

        $this->assertNotEmpty($strip, 'No .org-tabs rule was found, so this guard proves nothing.');

        $this->assertStringContainsString('overflow-x: auto', $strip[1], 'The strip does not scroll itself.');
        $this->assertStringContainsString(
            'overflow-y: hidden',
            $strip[1],
            'The standard pins the strip to horizontal scroll only.'
        );

        preg_match('/\.org-tab\s*\{([^}]*)\}/', $css, $tab);

        $this->assertStringContainsString(
            'white-space: nowrap',
            $tab[1],
            'A tab label may wrap, so the strip would grow a second row.'
        );

        $this->assertStringContainsString(
            'min-height: 44px',
            $tab[1],
            "Tabs are below the standard's 44px touch target."
        );
    }

    /** The strip is on every Organisation screen, not just Company Profile. */
    public function test_the_tab_strip_is_on_every_organisation_screen(): void
    {
        $shell = file_get_contents(self::SHELL);

        $this->assertStringContainsString('<OrganisationTabs', $shell);

        foreach (glob(__DIR__.'/../../../resources/js/Pages/Organisation/*.jsx') ?: [] as $page) {
            $this->assertStringContainsString(
                'OrganisationPage',
                file_get_contents($page),
                basename($page).' does not use the shared Organisation chrome, so it would render '
                .'without the tab strip.'
            );
        }
    }

    /**
     * A detail screen offers a local way back to its list.
     *
     * D-21 defers breadcrumbs, so without this there is no visible return at
     * all. It is local to the screen and is NOT a global breadcrumb system.
     *
     * Mutation: remove the `back` prop from BusinessUnit.jsx.
     */
    public function test_a_detail_screen_offers_a_local_way_back_to_its_list(): void
    {
        foreach ([
            'BusinessUnit.jsx' => '/console/organisation/business-units',
            'Team.jsx' => '/console/organisation/teams',
        ] as $page => $href) {
            $source = file_get_contents(__DIR__.'/../../../resources/js/Pages/Organisation/'.$page);

            $this->assertMatchesRegularExpression(
                "/back=\\{\\{ label: '[^']+', href: '".preg_quote($href, '/')."' \\}\\}/",
                $source,
                "{$page} offers no way back to its list."
            );
        }

        // And it is a link, so browser back and the on-screen back agree.
        $this->assertMatchesRegularExpression(
            '/<a className="org-back" href=\{back\.href\}>/',
            file_get_contents(self::SHELL),
            'The back control is not a link.'
        );

        // No internal page opens in a new browser tab.
        foreach (glob(__DIR__.'/../../../resources/js/Pages/Organisation/*.jsx') ?: [] as $page) {
            $this->assertStringNotContainsString(
                'target="_blank"',
                file_get_contents($page),
                basename($page).' opens an internal page in a new browser tab.'
            );
        }
    }

    /** The feature/tab/content hierarchy is stated once, in the shared chrome. */
    public function test_the_page_states_the_feature_then_the_section(): void
    {
        $shell = file_get_contents(self::SHELL);

        $feature = strpos($shell, '<h1>Organisation</h1>');
        $tabs = strpos($shell, '<OrganisationTabs');
        $section = strpos($shell, '<h2>{title}</h2>');

        $this->assertNotFalse($feature, 'The page does not name the feature.');
        $this->assertNotFalse($tabs);
        $this->assertNotFalse($section, 'The section title is not one level below the feature.');

        $this->assertLessThan($tabs, $feature, 'The tab strip sits above the feature name.');
        $this->assertLessThan($section, $tabs, 'The section content sits above the tab strip.');

        $this->assertStringContainsString(
            'Manage company structure, legal entities, business units, departments, teams',
            $shell,
            'The feature has no description, so the heading repeats the top bar with nothing linking them.'
        );
    }

    private function administrator(): User
    {
        return $this->make->user($this->make->organisation(), administrator: true);
    }

    private function actingAsUser(User $user): self
    {
        return $this->withSession([
            EnsureSessionIsCurrent::SESSION_USER_ID => $user->id,
            EnsureSessionIsCurrent::SESSION_AUTHENTICATED_AT => now()->toIso8601String(),
        ]);
    }
}
