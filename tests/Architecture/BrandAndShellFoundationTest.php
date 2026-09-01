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
     * The approved Login copy, verbatim.
     *
     * Mutation: paraphrase any line.
     */
    public function test_the_login_page_carries_the_approved_copy_verbatim(): void
    {
        $entry = $this->source('Pages/Entry.jsx');

        foreach ([
            'title="SemantIQ"',
            'Turn business data into confident decisions.',
            'See what changed. Understand why. Decide what',
            'SemantIQ brings governed data, business context and intelligent insights together in',
            'one secure decision-intelligence experience.',
            'Sign in with Microsoft',
        ] as $approved) {
            $this->assertStringContainsString(
                $approved,
                $entry,
                "Approved Login copy is missing or has been paraphrased: [{$approved}]."
            );
        }

        // The Microsoft path itself is unchanged by this unit.
        $this->assertStringContainsString('href="/auth/microsoft/redirect"', $entry);
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
        $this->assertLessThan($mark === false ? 0 : $heading, $mark, 'The product name sits above the company mark.');
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
                basename($page).' uses neither the authenticated shell nor the Auth card, directly '
                .'or through a shared component. Every screen is built from a shared archetype.'
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
        if (str_contains($source, 'AppShell') || str_contains($source, 'AuthCard')) {
            return true;
        }

        if ($depth > 2) {
            return false;
        }

        preg_match_all("#from '[^']*/Components/([A-Za-z]+)'#", $source, $imports);

        foreach ($imports[1] ?? [] as $component) {
            $path = self::JS.'/Components/'.$component.'.jsx';

            if (is_file($path) && $this->reachesAnArchetype(file_get_contents($path), $depth + 1)) {
                return true;
            }
        }

        return false;
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
