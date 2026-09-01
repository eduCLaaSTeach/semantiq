<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * The UI / Brand / Navigation foundation, guarded at the source.
 *
 * There is no JavaScript test runner in this project, so the front-end
 * contracts are asserted against the source and against the delivered HTML
 * (EntryPageTest, ConsoleNavigationTest) rather than by rendering React. That
 * is a real limit and it is stated here rather than papered over: these guards
 * prove the code says what it must say, and the recorded browser verification
 * proves the screen looks how it must look. Neither substitutes for the other.
 */
final class BrandAndShellFoundationTest extends TestCase
{
    private const JS = __DIR__.'/../../resources/js';

    private const BRAND = __DIR__.'/../../resources/brand';

    /**
     * A roadmap row is not a link, in the markup as well as in the data.
     *
     * The server already withholds the route (ConsoleNavigationTest). This is
     * the second, independent half: even handed a route, LockedRow renders a
     * div - it has no href to follow, is announced as disabled and is out of
     * the tab order.
     *
     * Mutation: render LockedRow as an <a>.
     */
    public function test_a_locked_navigation_row_is_not_a_link(): void
    {
        $shell = $this->source('Layouts/AppShell.jsx');

        $locked = $this->functionBody($shell, 'LockedRow');

        $this->assertNotSame('', $locked, 'LockedRow was not found, so this guard proves nothing.');

        $this->assertStringNotContainsString(
            '<a',
            $locked,
            'A locked navigation row renders as an anchor. A roadmap entry must have no '
            .'destination to follow at all.'
        );

        $this->assertStringNotContainsString(
            'href',
            $locked,
            'A locked navigation row carries an href.'
        );

        $this->assertStringContainsString('aria-disabled="true"', $locked);
        $this->assertStringContainsString('shell-soon', $locked, 'The "Soon" treatment is missing.');
    }

    /**
     * One disabled treatment, used everywhere. A second one would tell the user
     * that two different kinds of unavailable exist.
     */
    public function test_there_is_exactly_one_disabled_treatment(): void
    {
        $shell = $this->source('Layouts/AppShell.jsx');

        $this->assertSame(
            2,
            substr_count($shell, 'shell-soon'),
            'The "Soon" pill appears somewhere other than the locked leaf row and the locked '
            .'group header, or a second disabled treatment has been introduced.'
        );

        foreach (['Coming soon', 'Not available', 'TBD', 'Disabled', 'Locked'] as $rival) {
            $this->assertStringNotContainsString(
                ">{$rival}<",
                $shell,
                "The shell shows [{$rival}] as well as the approved \"Soon\" treatment."
            );
        }
    }

    /**
     * The shared standard's Shell Dimensions table, asserted rather than trusted.
     *
     * The rail shipped at 264px and the top bar at 56px - close enough to look
     * right and wrong against the standard. These are fixed values there, so
     * they are fixed here.
     *
     * Mutation: change any of these numbers.
     */
    public function test_the_shell_dimensions_match_the_shared_standard(): void
    {
        $css = preg_replace('#/\*.*?\*/#s', '', file_get_contents(__DIR__.'/../../resources/css/app.css'));

        foreach ([
            '--rail-width' => '240px',
            '--rail-width-collapsed' => '56px',
            '--topbar-height' => '52px',
        ] as $token => $value) {
            $this->assertMatchesRegularExpression(
                '/'.preg_quote($token, '/').':\s*'.preg_quote($value, '/').'\s*;/',
                $css,
                "Shell dimension [{$token}] is not the standard's {$value}."
            );
        }

        // Wide logo: height 22px, width auto, in the rail head.
        $this->assertMatchesRegularExpression(
            '/\.brand-mark-full img\s*\{[^}]*height:\s*22px/',
            $css,
            "The wide logo is not the standard's 22px."
        );

        // C2S short mark: the 40x34 slot, contained rather than stretched.
        $this->assertMatchesRegularExpression(
            '/\.brand-mark-short img\s*\{[^}]*width:\s*40px[^}]*height:\s*34px[^}]*object-fit:\s*contain/',
            $css,
            "The C2S short mark is not in the standard's 40x34 contained slot."
        );
    }

    /**
     * A collapsed rail can always be reopened.
     *
     * The rail hid its own toggle when collapsed, leaving 43 unlabelled glyphs
     * and no control to get the labels back. Every test passed; the screenshot
     * showed it. This asserts an expand control is RENDERED in the collapsed
     * branch and is not hidden by CSS.
     *
     * Mutation: restore `.shell-collapsed .shell-rail-toggle { display: none }`,
     * or drop the collapsed branch's button.
     */
    public function test_a_collapsed_rail_can_always_be_reopened(): void
    {
        $shell = $this->source('Layouts/AppShell.jsx');

        $this->assertMatchesRegularExpression(
            '/collapsed \? \(\s*<button/',
            $shell,
            'The collapsed rail head renders no control, so a collapsed rail cannot be reopened.'
        );

        $this->assertStringContainsString(
            'Expand navigation',
            $shell,
            'The expand control has no accessible name.'
        );

        $css = preg_replace('#/\*.*?\*/#s', '', file_get_contents(__DIR__.'/../../resources/css/app.css'));

        foreach ($this->declarationBlocks($css) as $selector => $body) {
            if (! str_contains($selector, 'rail-expand') && ! str_contains($selector, 'rail-toggle')) {
                continue;
            }

            $this->assertStringNotContainsString(
                'display: none',
                $body,
                "Rule [{$selector}] hides the rail's own expand control, so a collapsed rail "
                .'cannot be reopened.'
            );
        }
    }

    /**
     * Every navigation row carries its name, so an icon-only collapsed rail is
     * still navigable and still announced.
     *
     * Mutation: remove aria-label from LinkRow or LockedRow.
     */
    public function test_every_navigation_row_carries_an_accessible_name(): void
    {
        $shell = $this->source('Layouts/AppShell.jsx');

        foreach (['LockedRow', 'LinkRow', 'Group'] as $row) {
            $body = $this->functionBody($shell, $row);

            $this->assertNotSame('', $body, "{$row} was not found, so this guard proves nothing.");

            $this->assertStringContainsString(
                'aria-label',
                $body,
                "{$row} renders a row with no accessible name. Collapsed, the label is not "
                .'displayed, so the row would be an unnamed icon.'
            );

            $this->assertStringContainsString(
                'title',
                $body,
                "{$row} offers no hover title, so a collapsed icon row cannot be identified."
            );
        }
    }

    /**
     * D-17: the approved marks are used as supplied, and all four exist.
     *
     * Mutation: point an import at a file that is not in the asset pack.
     */
    public function test_the_approved_brand_assets_exist_and_are_the_ones_imported(): void
    {
        $brandMark = $this->source('Components/BrandMark.jsx');

        preg_match_all("#from '\.\./\.\./brand/([^']+)'#", $brandMark, $imports);

        $imported = $imports[1] ?? [];

        $this->assertSame(
            ['logo-full-dark.png', 'logo-full-light.png', 'logo-short-dark.png', 'logo-short-light.png'],
            $this->sorted($imported),
            'BrandMark does not import exactly the four approved marks.'
        );

        foreach ($imported as $asset) {
            $path = self::BRAND.'/'.$asset;

            $this->assertFileExists($path, "Approved brand asset [{$asset}] is missing.");
            $this->assertGreaterThan(1024, filesize($path), "Brand asset [{$asset}] looks empty.");
            $this->assertSame(
                'image/png',
                mime_content_type($path),
                "Brand asset [{$asset}] is not the supplied PNG."
            );
        }
    }

    /**
     * The mark is never recoloured, boxed, plated or stretched.
     *
     * Mutation: add a filter, a background or a fixed width/height pair to the
     * brand rules.
     */
    public function test_the_brand_mark_is_never_distorted(): void
    {
        $css = $this->extractRules(
            file_get_contents(__DIR__.'/../../resources/css/app.css'),
            'brand-mark'
        );

        $this->assertNotSame('', $css, 'No brand-mark rules were found, so this guard proves nothing.');

        foreach ([
            'filter:' => 'recolours the mark',
            'background' => 'plates the mark',
            'border' => 'boxes the mark',
            'transform' => 'transforms the mark',
        ] as $property => $why) {
            $this->assertStringNotContainsString(
                $property,
                $css,
                "A brand-mark rule uses [{$property}], which {$why}. The supplied artwork is used as is."
            );
        }

        // Height only. A fixed width alongside it is what stretches a logo;
        // width: auto is the setting that prevents exactly that.
        $this->assertStringContainsString(
            'width: auto',
            $css,
            'The mark is not set to width: auto, so scaling its height can stretch it.'
        );

        // A fixed width is allowed in exactly one place: the standard's 40x34
        // slot for the C2S short mark, which pairs it with object-fit: contain
        // so the artwork is centred in the slot rather than stretched to it.
        foreach ($this->declarationBlocks($css) as $selector => $body) {
            if (! preg_match('/width:\s*\d/', $body)) {
                continue;
            }

            $this->assertStringContainsString(
                'object-fit: contain',
                $body,
                "Brand-mark rule [{$selector}] sets a fixed width without object-fit: contain, so "
                .'the mark is stretched to the box. Scale by height and let the width follow.'
            );
        }
    }

    /**
     * Exactly ONE mark is visible in each theme.
     *
     * Both variants ship in the DOM and CSS hides one. The hide rule started
     * life as `.brand-mark-dark { display: none }`, which is LESS SPECIFIC than
     * `.brand-mark img { display: block }` - so the dark mark stayed visible in
     * the light theme and both logos rendered side by side, overflowing the
     * Login card. Every automated test passed; the browser showed it at a
     * glance.
     *
     * This guard is about specificity, because that is what actually broke:
     * every rule that shows or hides a variant must qualify the img element, so
     * none of them can be outranked by the base rule.
     *
     * Mutation: drop the `img` qualifier from any of these selectors.
     */
    public function test_each_theme_shows_exactly_one_brand_mark(): void
    {
        // Comments first: this file explains the bug in prose that names the
        // very selector being checked, and a scan that reads comments as
        // selectors reports the wrong rule (and can pass on the wrong one too).
        $css = preg_replace(
            '#/\*.*?\*/#s',
            '',
            file_get_contents(__DIR__.'/../../resources/css/app.css')
        );

        preg_match_all('/([^{}\n]*brand-mark-(?:light|dark)[^{}]*)\{([^}]*)\}/', $css, $rules, PREG_SET_ORDER);

        $this->assertNotEmpty($rules, 'No brand-mark variant rules were found, so this proves nothing.');

        $toggles = 0;

        foreach ($rules as [, $selector, $body]) {
            if (! str_contains($body, 'display')) {
                continue;
            }

            $toggles++;

            $this->assertStringContainsString(
                'img.brand-mark-',
                $selector,
                "Rule [{$selector}] toggles a brand mark without qualifying the img element, so "
                .'`.brand-mark img { display: block }` outranks it and both marks render at once.'
            );
        }

        $this->assertGreaterThanOrEqual(
            7,
            $toggles,
            'Fewer variant rules than the theme matrix needs: default, the two prefers-color-scheme '
            .'rules, and the four explicit data-theme rules.'
        );
    }

    /**
     * D-20: System / Light / Dark, and ONE theme architecture.
     *
     * Mutation: set data-theme from a second component, or store the preference
     * under a second key.
     */
    public function test_there_is_exactly_one_theme_architecture(): void
    {
        $switcher = $this->source('Components/ThemeSwitcher.jsx');

        foreach (['system', 'light', 'dark'] as $option) {
            $this->assertStringContainsString("value: '{$option}'", $switcher);
        }

        // System removes the attribute so prefers-color-scheme decides.
        $this->assertStringContainsString("root.removeAttribute('data-theme')", $switcher);

        $writers = [];

        foreach ($this->frontendFiles() as $file) {
            $source = file_get_contents($file);

            if (str_contains($source, "setAttribute('data-theme'") || str_contains($source, 'semantiq.theme')) {
                $writers[] = basename($file);
            }
        }

        $this->assertSame(
            ['ThemeSwitcher.jsx'],
            $writers,
            'More than one component decides the theme. The standard requires one architecture.'
        );

        // The pre-paint script in the root view must agree with it, or a dark
        // reload flashes light before React mounts.
        $blade = file_get_contents(__DIR__.'/../../resources/views/app.blade.php');

        $this->assertStringContainsString('semantiq.theme', $blade, 'The pre-paint script uses a different key.');
        $this->assertStringContainsString('data-theme', $blade);
    }

    /** Both favicons are referenced, and both files are actually there. */
    public function test_the_favicons_are_declared_and_present(): void
    {
        $blade = file_get_contents(__DIR__.'/../../resources/views/app.blade.php');

        foreach (['favicon-light.ico', 'favicon-dark.ico'] as $icon) {
            $this->assertStringContainsString($icon, $blade, "The root view does not declare [{$icon}].");
            $this->assertFileExists(__DIR__.'/../../public/'.$icon);
        }
    }

    /**
     * The approved Login copy, verbatim, hero and panel alike.
     *
     * The Product Owner supplies this wording; it is not paraphrased, tightened
     * or "improved". Mutation: reword any line.
     */
    public function test_the_login_page_carries_the_approved_copy_verbatim(): void
    {
        $copy = $this->source('Pages/Entry.jsx').$this->source('Layouts/SignInLayout.jsx');

        foreach ([
            // Hero.
            'Business Decision Intelligence',
            'From business data to',
            'confident decisions',
            'in moments.',
            'Bring governed data, business context and intelligent analysis together to understand',
            'what changed, why it matters and what to do next.',
            // The product journey.
            "'Connect', 'Govern', 'Understand', 'Ask', 'Decide'",
            // Benefit cards.
            'Unified Intelligence',
            'Bring trusted business information together in one governed intelligence experience.',
            'Ask SemantIQ',
            'Explore performance, change, risk and opportunity using natural business questions.',
            'Decision Intelligence',
            'Turn insights into clearer priorities, recommendations and informed next actions.',
            // Authentication panel.
            'Welcome to SemantIQ',
            'Sign in securely to continue to your decision intelligence workspace.',
            'Continue with Microsoft',
            'Access is managed by your organisation',
            'Contact your administrator if you cannot access SemantIQ.',
            // Trust row.
            'Secure sign-in',
            'Role-aware access',
            'Governed intelligence',
        ] as $approved) {
            $this->assertStringContainsString(
                $approved,
                $copy,
                "Approved Login copy is missing or has been paraphrased: [{$approved}]."
            );
        }

        // The Microsoft path itself is unchanged by this unit.
        $this->assertStringContainsString('href="/auth/microsoft/redirect"', $this->source('Pages/Entry.jsx'));
    }

    /**
     * Release 1 supports Microsoft and nothing else, so nothing else is offered.
     *
     * A reference layout showing Google, email-and-password and social tabs is a
     * layout reference, not a feature list. An authentication method the product
     * does not have must never appear on the screen that authenticates people.
     *
     * Mutation: add a second provider, a password field, or a provider tab strip.
     */
    public function test_the_login_page_offers_no_authentication_method_the_product_lacks(): void
    {
        $login = $this->withoutComments(
            $this->source('Pages/Entry.jsx').$this->source('Layouts/SignInLayout.jsx')
        );

        foreach ([
            'Google',
            'Apple',
            'LinkedIn',
            'password',
            'Password',
            'type="email"',
            'Or continue with',
            'Sign up',
            'Create account',
            'Forgot',
        ] as $absent) {
            $this->assertStringNotContainsString(
                $absent,
                $login,
                "The Login page offers [{$absent}], which SemantIQ Release 1 does not support. "
                .'A control that cannot work is worse than no control.'
            );
        }

        // Exactly one authentication destination exists on the page.
        preg_match_all('/href="([^"]*)"/', $this->source('Pages/Entry.jsx'), $links);

        $this->assertSame(
            ['/auth/microsoft/redirect'],
            array_values(array_unique($links[1] ?? [])),
            'The Login page links somewhere other than the Microsoft sign-in path.'
        );
    }

    /**
     * The headline keeps its three deliberate lines.
     *
     * Left to wrap on its own it broke as "in / moments." - an orphan that
     * reads as an accident. Mutation: collapse the three spans back into one
     * run of text.
     */
    public function test_the_headline_is_set_as_three_deliberate_lines(): void
    {
        $hero = $this->source('Layouts/SignInLayout.jsx');

        $this->assertMatchesRegularExpression(
            '/<h1 className="signin-headline">\s*<span>From business data to<\/span>\s*'
            .'<span className="signin-highlight">confident decisions<\/span>\s*'
            .'<span>in moments\.<\/span>\s*<\/h1>/',
            $hero,
            'The headline is no longer set as three deliberate lines.'
        );

        $css = preg_replace('#/\*.*?\*/#s', '', file_get_contents(__DIR__.'/../../resources/css/app.css'));

        $this->assertStringContainsString(
            '.signin-headline span { display: block; }',
            $css,
            'The headline lines are not laid out as blocks, so they run together again.'
        );
    }

    /**
     * Green Gold carries the highlight, through the token rather than a hex.
     *
     * The standard reserves Green Gold for the highlight and active-nav
     * treatment, and forbids hardcoding a token's hex anywhere but the token
     * definition.
     *
     * Mutation: colour the highlight with a raw hex, or with another hue.
     */
    public function test_the_headline_highlight_uses_the_green_gold_token(): void
    {
        $css = preg_replace('#/\*.*?\*/#s', '', file_get_contents(__DIR__.'/../../resources/css/app.css'));

        $this->assertMatchesRegularExpression(
            '/\.signin-highlight\s*\{\s*color:\s*var\(--brand-gold\);?\s*\}/',
            $css,
            'The headline highlight does not use the Green Gold token.'
        );

        // And no sign-in rule restates a palette hex that already has a token.
        foreach ($this->declarationBlocks($css) as $selector => $body) {
            if (! str_contains($selector, '.signin')) {
                continue;
            }

            foreach (['#193E6B', '#B3A125', '#7F3F98', '#448E9D'] as $tokenised) {
                $this->assertStringNotContainsStringIgnoringCase(
                    $tokenised,
                    $body,
                    "Rule [{$selector}] hardcodes [{$tokenised}], which is a token. Read the token."
                );
            }
        }
    }

    /**
     * One icon style, with one documented exception.
     *
     * Microsoft's mark is a third-party brand logo, used as supplied on the
     * control that signs people in with it - a logo is never restyled into
     * someone else's outline system. It lives in the ONE registry so there is
     * still a single SVG source, and it is the only key allowed to depart from
     * the approved style.
     *
     * Mutation: add a second filled glyph without listing it as a brand mark.
     */
    public function test_only_a_declared_third_party_brand_mark_departs_from_the_icon_style(): void
    {
        $registry = $this->source('Components/Icon.jsx');

        preg_match('/export const BRAND_MARKS = \[([^\]]*)\]/', $registry, $declared);

        $this->assertNotEmpty($declared, 'The registry declares no brand-mark allowlist.');

        // Every glyph entry, and which of them paint a fill instead of a stroke.
        preg_match_all('/\'(i-[a-z0-9-]+)\':(.*?)(?=\n    \'i-|\n};)/s', $registry, $entries, PREG_SET_ORDER);

        $this->assertNotEmpty($entries, 'No glyphs were found, so this guard proves nothing.');

        $filled = [];

        foreach ($entries as [, $key, $body]) {
            if (str_contains($body, 'fill="#')) {
                $filled[] = $key;
            }
        }

        foreach ($filled as $key) {
            $this->assertStringContainsString(
                "'{$key}'",
                $declared[1],
                "Glyph [{$key}] is filled rather than the approved outline style and is not a "
                .'declared third-party brand mark. One style, one exception, and it is declared.'
            );
        }

        $this->assertStringContainsString(
            'i-microsoft',
            $declared[1],
            'The Microsoft brand mark is no longer declared.'
        );
    }

    /**
     * The hero states what SemantIQ is for, never what this deployment holds.
     *
     * It is the one page an anonymous caller can read in full, so it must carry
     * no menu, no product area, no count, no version and no customer name.
     *
     * Mutation: put a product-area name or a roadmap label into the hero copy.
     */
    public function test_the_login_hero_reveals_nothing_about_the_deployment(): void
    {
        $login = $this->withoutComments(
            $this->source('Pages/Entry.jsx').$this->source('Layouts/SignInLayout.jsx')
        );

        foreach ([
            'System Administration',
            'Fabric Configuration',
            'SemantIQ Workplace',
            'Organisation',
            'Business Domains',
            'Power BI',
            'Fabric',
            'tenant',
            'database',
            'API',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $login,
                "The Login page mentions [{$forbidden}]. The hero says what SemantIQ is for; it "
                .'never describes what is behind authentication or how it is built.'
            );
        }
    }

    /**
     * The journey chips describe the product; they do not navigate.
     *
     * Mutation: render a chip as a link or a button.
     */
    public function test_the_journey_chips_are_not_navigation(): void
    {
        $hero = $this->functionBody($this->source('Layouts/SignInLayout.jsx'), 'SignInLayout');

        $journey = substr($hero, strpos($hero, 'signin-journey'), 600);

        foreach (['<a ', '<button', 'href', 'onClick'] as $interactive) {
            $this->assertStringNotContainsString(
                $interactive,
                $journey,
                "A journey chip uses [{$interactive}]. The chips are informational: there is "
                .'nothing behind them to reach.'
            );
        }
    }

    /**
     * D-17 hierarchy: company mark above product name, on every Auth screen.
     */
    public function test_every_auth_screen_shows_the_company_mark_above_the_product_name(): void
    {
        $card = $this->source('Components/AuthCard.jsx');

        $mark = strpos($card, '<BrandMark');
        $heading = strpos($card, '<h1>');

        $this->assertNotFalse($mark, 'The Auth card does not show the company mark.');
        $this->assertNotFalse($heading);
        $this->assertLessThan($heading, $mark, 'The product name sits above the company mark.');

        // The Login hero states the same hierarchy: the CLaaS2SaaS mark, then
        // the SemantIQ product name - one logo and one wordmark, so they read as
        // company and product rather than two competing logos.
        $hero = $this->source('Layouts/SignInLayout.jsx');

        $heroMark = strpos($hero, '<BrandMark');
        $product = strpos($hero, 'signin-product');

        $this->assertNotFalse($heroMark, 'The Login hero does not show the company mark.');
        $this->assertNotFalse($product, 'The Login hero does not name the product.');
        $this->assertLessThan($product, $heroMark, 'The product name sits above the company mark.');

        // And exactly one logo image: the hero is Midnight Blue in both themes,
        // so its mark is pinned rather than swapped, and a swapped pair here
        // would render both at once.
        $this->assertStringContainsString(
            'on="dark"',
            $hero,
            'The hero mark follows the theme. The hero surface does not, so the light-chrome '
            .'logo would end up on a dark panel.'
        );
    }

    /**
     * No internal key, enum value, route name or debug text may be written into
     * user-visible text anywhere in the front end.
     *
     * Mutation: put {node.icon}, a route name or a snake_case key into a label.
     */
    public function test_no_internal_identifier_is_written_as_user_facing_text(): void
    {
        $checked = 0;

        foreach ($this->frontendFiles() as $file) {
            foreach ($this->visibleText(file_get_contents($file)) as $text) {
                $checked++;

                foreach (['i-', 'console.', 'auth.', 'organisation.', '_id', 'undefined', 'null'] as $marker) {
                    $this->assertStringNotContainsString(
                        $marker,
                        $text,
                        basename($file)." renders [{$text}], which exposes internal terminology "
                        ."[{$marker}] on a user-facing surface."
                    );
                }

                $this->assertDoesNotMatchRegularExpression(
                    '/^[a-z]+(_[a-z]+)+$/',
                    $text,
                    basename($file)." renders the raw key [{$text}] as text."
                );
            }
        }

        $this->assertGreaterThan(
            20,
            $checked,
            'Almost no visible text was found, so this guard would pass against an empty front end.'
        );
    }

    /**
     * Every screen the user can reach is built from the shared archetypes.
     *
     * Mutation: add a page that lays itself out from scratch.
     */
    public function test_every_page_uses_a_shared_archetype(): void
    {
        $pages = glob(self::JS.'/Pages/**/*.jsx') ?: [];
        $pages = [...$pages, ...(glob(self::JS.'/Pages/*.jsx') ?: [])];

        $this->assertNotEmpty($pages);

        foreach ($pages as $page) {
            $source = file_get_contents($page);

            $this->assertTrue(
                $this->reachesAnArchetype($source),
                basename($page).' uses none of the three shared archetypes - the authenticated '
                .'shell, the Auth card or the sign-in layout - directly or through a shared '
                .'component. Every screen is built from a shared archetype.'
            );
        }
    }

    /**
     * Does this source reach a shared archetype, directly or through one shared
     * component?
     *
     * Pages compose: an Organisation screen uses OrganisationPage, which uses
     * AppShell. Requiring the archetype to be named in the page itself would
     * have forced every page to reach past its own wrapper.
     */
    private function reachesAnArchetype(string $source, int $depth = 0): bool
    {
        // The three archetypes, and only these three. AuthCard stays the shape
        // of every refusal state; SignInLayout is the Login page alone.
        foreach (['AppShell', 'AuthCard', 'SignInLayout'] as $archetype) {
            if (str_contains($source, $archetype)) {
                return true;
            }
        }

        if ($depth > 2) {
            return false;
        }

        preg_match_all("#from '[^']*/(Components|Layouts)/([A-Za-z]+)'#", $source, $imports, PREG_SET_ORDER);

        foreach ($imports as [, $directory, $component]) {
            $path = self::JS.'/'.$directory.'/'.$component.'.jsx';

            if (is_file($path) && $this->reachesAnArchetype(file_get_contents($path), $depth + 1)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Source with its comments removed.
     *
     * These files EXPLAIN in prose that the page must not offer Google or name a
     * tenant, so a scan that reads comments reports the explanation as the
     * defect. Strip them and the scan sees only what can reach a screen.
     */
    private function withoutComments(string $source): string
    {
        return (string) preg_replace(['#/\*.*?\*/#s', '#^\s*//.*$#m'], '', $source);
    }

    /** @return list<string> */
    private function visibleText(string $source): array
    {
        // Text sitting between JSX tags: >Sign out<, >Soon<, and so on.
        preg_match_all('/>\s*([A-Za-z][^<>{}\n]{1,60}?)\s*</', $source, $matches);

        $text = [];

        foreach ($matches[1] ?? [] as $candidate) {
            $candidate = trim($candidate);

            if ($candidate !== '') {
                $text[] = $candidate;
            }
        }

        return $text;
    }

    private function functionBody(string $source, string $name): string
    {
        $start = strpos($source, "function {$name}(");

        if ($start === false) {
            return '';
        }

        $next = strpos($source, "\nfunction ", $start + 1);

        return substr($source, $start, $next === false ? null : $next - $start);
    }

    /**
     * @return array<string, string>
     */
    private function declarationBlocks(string $rules): array
    {
        preg_match_all('/([^{}]*)\{([^}]*)\}/', $rules, $matches, PREG_SET_ORDER);

        $blocks = [];

        foreach ($matches as [, $selector, $body]) {
            $blocks[trim($selector)] = $body;
        }

        return $blocks;
    }

    private function extractRules(string $css, string $needle): string
    {
        $rules = '';
        $offset = 0;

        while (($at = strpos($css, $needle, $offset)) !== false) {
            $open = strpos($css, '{', $at);
            $close = strpos($css, '}', $at);

            if ($open === false || $close === false) {
                break;
            }

            $rules .= substr($css, $at, $close - $at + 1);
            $offset = $close;
        }

        return $rules;
    }

    private function source(string $relative): string
    {
        $path = self::JS.'/'.$relative;

        $this->assertFileExists($path);

        return file_get_contents($path);
    }

    /** @return list<string> */
    private function frontendFiles(): array
    {
        $files = [];

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::JS));

        foreach ($iterator as $file) {
            if ($file->getExtension() === 'jsx') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    /**
     * @param  list<string>  $values
     * @return list<string>
     */
    private function sorted(array $values): array
    {
        sort($values);

        return array_values($values);
    }
}
