<?php

declare(strict_types=1);

namespace Tests\Feature\Setup;

use App\Modules\Access\Models\RoleAssignment;
use App\Modules\Access\Support\RoleCode;
use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;
use App\Modules\Platform\Models\User;
use App\Modules\Platform\Models\UserStatus;
use App\Modules\Platform\Setup\IntegrationFamily;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * GATE D UI CORRECTION. PLATFORM INTEGRATIONS USES THE SHARED TAB LAYOUT.
 *
 * The Product Owner compared this screen with Organisation and found it was the
 * only System Administration feature that had invented its own information
 * architecture: four detached cards stacked on one URL, where every other
 * feature has FEATURE -> TAB -> CONTENT with route-backed Pattern B links.
 *
 * WHY THIS NEEDS TESTS AT ALL, given there is no JavaScript test runner. Almost
 * everything a tab strip promises is decided on the SERVER and is checkable
 * here: which tabs exist, what they are called, where they point, which one the
 * route makes active, and what each one is allowed to send to the browser. What
 * is left - that it LOOKS like Organisation - is a browser check and is
 * recorded as one, not inferred from anything below.
 *
 * THE LABELS ARE THE POINT OF SEVERAL OF THESE. D-148 named the four
 * integrations and the implementation had drifted to two other names, which is
 * how a screen, a decision record and the body of a test email came to disagree
 * about what a feature is called.
 */
final class IntegrationsFollowTheSharedTabLayoutTest extends TestCase
{
    use RefreshDatabase;

    /** D-148's words, exactly. */
    private const LABELS = [
        'Microsoft Entra ID',
        'Email & Notifications',
        'AI Provider',
        'Microsoft Fabric',
    ];

    private function signedIn(): self
    {
        $admin = User::query()->firstOrCreate(
            ['external_subject' => 'oid-tab-admin'],
            [
                'provider' => 'microsoft',
                'tenant_id' => 'tenant-1',
                'email' => 'admin@example.test',
                'display_name' => 'The Administrator',
                'status' => UserStatus::Active,
            ],
        );

        RoleAssignment::query()->firstOrCreate([
            'user_id' => $admin->id,
            'organisation_id' => null,
            'role_code' => RoleCode::SystemAdministrator,
        ], ['assigned_at' => now()]);

        return $this->withSession([
            EnsureSessionIsCurrent::SESSION_USER_ID => $admin->id,
            EnsureSessionIsCurrent::SESSION_AUTHENTICATED_AT => now()->toIso8601String(),
        ]);
    }

    /** @return array<string, mixed> */
    private function propsAt(string $uri): array
    {
        $response = $this->signedIn()->get($uri);

        $response->assertOk();

        return $response->viewData('page')['props'];
    }

    /**
     * 1. EXACTLY FOUR TABS.
     *
     * Mutation: add a fifth entry, or drop one.
     */
    public function test_there_are_exactly_four_tabs(): void
    {
        $tabs = $this->propsAt('/console/integrations')['tabs'];

        $this->assertCount(4, $tabs,
            'Platform Integrations no longer offers exactly four tabs. The four integrations are '
            .'D-148 and a fifth would be a product decision, not a layout one.');

        $this->assertSame(
            ['identity', 'email', 'ai', 'fabric'],
            array_column($tabs, 'family'),
            'The tab order changed. Microsoft sign-in is first because it is the only required '
            .'integration and the only one another team owns.',
        );
    }

    /**
     * 2. THE LABELS ARE EXACTLY D-148's.
     *
     * Mutation: change any one of the four names in IntegrationFamily.
     */
    public function test_the_tab_labels_are_exactly_the_approved_names(): void
    {
        $tabs = $this->propsAt('/console/integrations')['tabs'];

        $this->assertSame(self::LABELS, array_column($tabs, 'label'),
            'The tab labels are not D-148\'s four names. The implementation drifted to '
            .'"Email delivery" and "AI service" once already, and the screen, the decision '
            .'record and the body of the test message then disagreed about what this is called.');
    }

    /**
     * THE LABELS COME FROM ONE PLACE. Otherwise the case above passes on a
     * hard-coded copy in the controller while the rest of the product uses the
     * enum, and the two drift the moment anybody renames a family.
     *
     * Mutation: hard-code the label strings in renderTab().
     */
    public function test_the_labels_are_the_family_names_and_not_a_second_copy(): void
    {
        $tabs = $this->propsAt('/console/integrations')['tabs'];

        foreach ($tabs as $tab) {
            $family = IntegrationFamily::from($tab['family']);

            $this->assertSame($family->inWords(), $tab['label'],
                "The [{$tab['family']}] tab label is not IntegrationFamily::inWords(), so this "
                .'screen has its own name for a family.');
        }
    }

    /**
     * 3. IDENTITY REMAINS SUMMARY AND LINK ONLY.
     *
     * The claim IdentityIsNotWritableOnTheConsole makes about the landing page,
     * re-made about the tab, because the tab is the landing page now and a
     * future edit could give it a form without touching a route.
     *
     * Mutation: send $view->toArray() for identity.
     */
    public function test_the_identity_tab_is_a_summary_and_a_link(): void
    {
        $props = $this->propsAt('/console/integrations');

        $this->assertNull($props['integration']);
        $this->assertSame('identity', $props['summary']['family']);
        $this->assertArrayNotHasKey('fields', $props['summary']);
        $this->assertArrayNotHasKey('secrets', $props['summary']);
        $this->assertArrayNotHasKey('choices', $props['summary']);
    }

    /**
     * 4. EMAIL, AI AND FABRIC KEEP THEIR FORMS AND THEIR ACTIONS.
     *
     * Mutation: drop the fields from the writable branch of renderTab().
     */
    public function test_the_writable_tabs_keep_their_forms(): void
    {
        $expected = [
            'email' => ['host', 'port', 'encryption', 'username', 'from_address', 'from_name'],
            'ai' => ['provider', 'endpoint', 'deployment'],
            'fabric' => ['tenant_id', 'client_id', 'workspace_id'],
        ];

        foreach ($expected as $family => $fields) {
            $props = $this->propsAt("/console/integrations/{$family}");

            $this->assertSame($fields, array_keys($props['integration']['fields']),
                "[{$family}] no longer offers the fields it offered before the tab correction.");

            $this->assertSame(
                IntegrationFamily::from($family)->secrets(),
                array_keys($props['integration']['secrets']),
                "[{$family}] lost its credential handling.",
            );
        }
    }

    /**
     * 5. THE ACTIVE TAB FOLLOWS THE ROUTE.
     *
     * This is what makes it Pattern B rather than a client-side switch: a
     * refresh keeps the section, browser Back behaves, and a section URL can be
     * shared. None of that survives if the server always answers with the same
     * tab.
     *
     * Mutation: hard-code Identity as the active family in renderTab().
     */
    public function test_the_active_tab_follows_the_route(): void
    {
        $this->assertSame('identity', $this->propsAt('/console/integrations')['active']);

        foreach (['email', 'ai', 'fabric'] as $family) {
            $this->assertSame(
                $family,
                $this->propsAt("/console/integrations/{$family}")['active'],
                "[{$family}] is not the active tab on its own URL, so the strip cannot be "
                .'deep-linked and a refresh would not keep the section.',
            );
        }
    }

    /**
     * EVERY TAB'S HREF RESOLVES. A strip whose links 404 is worse than no
     * strip, and the href is built in the enum rather than in the router.
     *
     * Mutation: change consolePath() to return a path that does not exist.
     */
    public function test_every_tab_href_is_a_real_route(): void
    {
        foreach ($this->propsAt('/console/integrations')['tabs'] as $tab) {
            $this->signedIn()->get($tab['href'])->assertOk();
        }
    }

    /**
     * 6. THE TABS CREATE NO SECOND CONFIGURATION WRITE PATH.
     *
     * The correction added routes to a screen whose whole guarantee is about
     * what it may not write, so every one of them has to be shown harmless.
     *
     * Mutation: make either new route a PUT, or point it at update().
     */
    public function test_the_tabs_add_no_write_route(): void
    {
        $added = [];

        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'console/integrations')) {
                continue;
            }

            foreach ($route->methods() as $method) {
                if (in_array($method, ['HEAD', 'OPTIONS', 'GET'], true)) {
                    continue;
                }

                $added[] = $method.' '.$route->uri();
            }
        }

        sort($added);

        $this->assertSame(
            [
                'DELETE console/integrations/{family}/secret/{name}',
                'POST console/integrations/email/send-test',
                'POST console/integrations/{family}/test',
                'PUT console/integrations/{family}',
            ],
            $added,
            'The non-read route set under console/integrations changed. The tab correction is '
            .'reads only; a write appearing here is a second configuration path.',
        );
    }

    /**
     * 7. AUTHORISATION IS UNCHANGED.
     *
     * Every tab is PlatformAdmin, exactly as the landing page was. A new route
     * that forgot the middleware would be a Platform Integrations screen
     * anybody signed in could open.
     *
     * Mutation: move either new route outside the RequireActionClass group.
     */
    public function test_every_tab_route_requires_platform_administration(): void
    {
        $paths = ['/console/integrations', '/console/integrations/email',
            '/console/integrations/ai', '/console/integrations/fabric'];

        $member = User::query()->create([
            'provider' => 'microsoft',
            'external_subject' => 'oid-ordinary',
            'tenant_id' => 'tenant-1',
            'email' => 'member@example.test',
            'display_name' => 'An Ordinary Member',
            'status' => UserStatus::Active,
        ]);

        foreach ($paths as $path) {
            // Signed in, but with no platform role at all.
            $response = $this->withSession([
                EnsureSessionIsCurrent::SESSION_USER_ID => $member->id,
                EnsureSessionIsCurrent::SESSION_AUTHENTICATED_AT => now()->toIso8601String(),
            ])->get($path);

            /*
             * THE SHAPE THE REST OF THE CONSOLE REFUSES IN, not one invented
             * here. RouteAuthorizationMatrixTest asserts the same redirect for
             * Identity & SSO, so a tab that refused differently would be a
             * second refusal behaviour as well as a second layout.
             */
            $this->assertSame(
                route('auth.access-denied'),
                $response->headers->get('Location'),
                "[{$path}] did not refuse somebody with no platform role. Every Platform "
                .'Integrations tab is PlatformAdmin.',
            );
        }
    }

    /**
     * 8. NO SECRET IS INTRODUCED INTO THE NAVIGATION OR THE PAGE PROPS.
     *
     * The tab props are new page state and are checked like any other. The
     * strip carries a family, a label and an href, and nothing else - a status
     * on a tab would be a second place a status could disagree with the card,
     * and a value of any kind would be a value in the page source.
     *
     * Mutation: add the secret map, or the fields, to the tab entries.
     */
    public function test_the_tab_props_carry_no_value_at_all(): void
    {
        foreach (['/console/integrations', '/console/integrations/email',
            '/console/integrations/ai', '/console/integrations/fabric'] as $path) {
            $props = $this->propsAt($path);

            foreach ($props['tabs'] as $tab) {
                $this->assertSame(['family', 'label', 'href'], array_keys($tab),
                    'A Platform Integrations tab carries something other than its family, its '
                    .'label and its address.');
            }

            /*
             * NOT A SEARCH FOR THE WORD "api_key".
             *
             * That was the first version of this case and it was wrong in the
             * way this project keeps finding: the credential NAMES are supposed
             * to be in the props - `secrets` is a map of name => configured, and
             * the screen has to say which credentials exist in order to offer
             * removing one. A string scan fails on correct output and would have
             * been "fixed" by weakening it.
             *
             * The guarantee is structural: every value in that map is a BOOLEAN,
             * and no editable field is named after a credential. A secret cannot
             * be in the page source if nothing that could hold one is sent.
             */
            $secrets = $props['integration']['secrets'] ?? [];

            foreach ($secrets as $name => $configured) {
                $this->assertIsBool($configured,
                    "[{$path}] sends something other than a boolean for the [{$name}] "
                    .'credential, so a secret is in the page source.');
            }

            $fields = array_keys($props['integration']['fields'] ?? []);

            $this->assertSame([], array_intersect($fields, array_keys($secrets)),
                "[{$path}] sends a credential as an editable field, which is a field with a "
                .'VALUE rather than a boolean.');

            if ($props['summary'] !== null) {
                $this->assertArrayNotHasKey('secrets', $props['summary'],
                    "[{$path}] sends a credential map for an integration it only summarises.");
            }
        }
    }

    /**
     * THE STRIP IS THE SHARED ONE. Not a proof that it LOOKS right - that is a
     * browser check - but a proof that it is not a second implementation with
     * its own class names, which is the thing that drifts.
     *
     * Mutation: give IntegrationsTabs its own `integrations-tab` classes.
     */
    public function test_the_strip_reuses_the_shared_pattern_b_component_classes(): void
    {
        $strip = (string) file_get_contents(
            base_path('resources/js/Components/IntegrationsTabs.jsx'),
        );

        /*
         * THE ATTRIBUTES, NOT THE WORDS.
         *
         * The first version of this case searched the file for "org-tabs" and
         * SURVIVED the mutation that renames the class, because the docblock
         * above the component explains why it uses org-tabs - so the string was
         * still there while the rendered class was not. An assertion satisfied
         * by a comment about the rule is the failure CLAUDE.md section 2 names.
         *
         * These match what React actually emits: the nav's className and the
         * template literal that builds each tab's.
         */
        foreach ([
            'className="org-tabs"',
            '`org-tab${',
            "' org-tab-active'",
            'aria-current',
        ] as $marker) {
            $this->assertStringContainsString($marker, $strip,
                "The Platform Integrations strip no longer renders [{$marker}], so it has "
                .'stopped sharing the Pattern B treatment with Organisation.');
        }

        $page = (string) file_get_contents(
            base_path('resources/js/Pages/Platform/Integrations.jsx'),
        );

        $this->assertStringContainsString('org-feature', $page,
            'The Platform Integrations page no longer uses the shared feature header.');

        $this->assertStringContainsString('IntegrationsTabs', $page,
            'The Platform Integrations page no longer renders a tab strip.');
    }
}
