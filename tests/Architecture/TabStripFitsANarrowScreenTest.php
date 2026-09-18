<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * The shared Pattern B tab strip must fit a phone.
 *
 * The strip was built to scroll ITSELF on a narrow screen - `overflow-x: auto`
 * on the nav, `min-width: max-content` on the list - and to hide its scrollbar
 * entirely. On Security Status at 390px that put two of the four tabs wholly
 * outside the viewport with no affordance of any kind, and the Product Owner
 * found it on a phone.
 *
 * The browser check that should have caught it asserted PAGE-level horizontal
 * overflow, which this design specifically prevents: the strip clipped its own
 * content while the page reported no overflow at all. So the assertion passed
 * for a reason unrelated to what it claimed to check.
 *
 * These are source-text guards, and they are not the evidence. The evidence is
 * the browser observation recorded in
 * `doc/v2/phase-1/P1-06-SECURITY-STATUS-VERIFICATION.md` §11, where each tab's
 * own box, its own label and a hit test at its own centre were measured. What
 * these guards do is stop the three declarations that caused the clipping from
 * coming back silently.
 */
final class TabStripFitsANarrowScreenTest extends TestCase
{
    private const CSS = __DIR__.'/../../resources/css/app.css';

    /** The narrowest screen the standard requires the console to serve. */
    private const PHONE_WIDTH = 390;

    /**
     * On a narrow screen the strip wraps instead of clipping.
     *
     * Mutations, each observed to fail this test:
     *  - delete `flex-wrap: wrap`      -> the list refuses to wrap
     *  - delete `min-width: 0`         -> `min-width: max-content` still forces one row
     *  - delete `overflow: visible`    -> the nav still clips what it cannot show
     */
    public function test_the_shared_tab_strip_wraps_rather_than_clipping_on_a_phone(): void
    {
        $block = $this->narrowWidthBlockGoverning('.org-tabs');

        $this->assertNotNull(
            $block,
            'No narrow-width rule governs .org-tabs, so the strip falls back to scrolling itself '
            .'behind a hidden scrollbar - which is how two tabs went off-screen unannounced.'
        );

        $this->assertGreaterThanOrEqual(
            self::PHONE_WIDTH,
            $block['breakpoint'],
            'The narrow-width rule exists but starts below phone width, so a phone never receives it.'
        );

        $this->assertMatchesRegularExpression(
            '/\.org-tabs\s+ul\s*\{[^}]*flex-wrap:\s*wrap/',
            $block['css'],
            'The tab list does not wrap at narrow width, so it can only extend past the screen edge.'
        );

        $this->assertMatchesRegularExpression(
            '/\.org-tabs\s+ul\s*\{[^}]*min-width:\s*0/',
            $block['css'],
            'The tab list keeps `min-width: max-content`, which forces one row however narrow the '
            .'screen is - wrapping is declared but can never take effect.'
        );

        $this->assertMatchesRegularExpression(
            '/\.org-tabs\s*\{[^}]*overflow:\s*visible/',
            $block['css'],
            'The strip still clips its own overflow at narrow width, so anything that does not fit '
            .'disappears rather than showing.'
        );
    }

    /**
     * A tab that has wrapped is no longer attached to the strip's rule, so the
     * active state has to be carried some other way. The standard is explicit
     * that fill, border and weight carry it together - never colour alone.
     *
     * Mutation: delete `font-weight: 700` from the narrow-width active rule.
     */
    public function test_the_active_tab_is_still_marked_by_more_than_colour_when_wrapped(): void
    {
        $block = $this->narrowWidthBlockGoverning('.org-tabs');
        $this->assertNotNull($block);

        $this->assertMatchesRegularExpression(
            '/\.org-tab-active[^{]*\{[^}]*border-color:\s*var\(--card-border-strong\)/',
            $block['css'],
            'The wrapped active tab has no border of its own, leaving only fill to say which '
            .'section the user is in.'
        );

        $this->assertMatchesRegularExpression(
            '/\.org-tab-active[^{]*\{[^}]*font-weight:\s*700/',
            $block['css'],
            'The wrapped active tab is not carried by weight, so it relies on colour alone.'
        );
    }

    /**
     * The desktop strip is untouched: this was a responsive correction, not a
     * redesign. The browser-tab shape, the attachment offset and the strip's
     * rule are what make FEATURE -> TAB -> CONTENT legible on a wide screen.
     *
     * Mutation: move `border-radius: 10px 10px 0 0` out of the base rule.
     */
    public function test_the_desktop_browser_tab_shape_is_unchanged(): void
    {
        $base = $this->ruleBody('.org-tab', $this->stylesheetOutsideMediaQueries());

        $this->assertNotNull($base, 'The base .org-tab rule is gone.');
        $this->assertStringContainsString('border-radius: 10px 10px 0 0', $base);
        $this->assertStringContainsString('transform: translateY(1px)', $base);
        $this->assertStringContainsString('white-space: nowrap', $base);
    }

    /**
     * @return array{breakpoint:int, css:string}|null
     */
    private function narrowWidthBlockGoverning(string $selector): ?array
    {
        $css = $this->stylesheet();
        $offset = 0;

        while (preg_match('/@media\s*\(max-width:\s*(\d+)px\)\s*\{/', $css, $m, PREG_OFFSET_CAPTURE, $offset)) {
            $open = $m[0][1] + strlen($m[0][0]);
            $body = $this->balancedBody($css, $open);
            $offset = $open + strlen($body);

            // The block must actually carry a rule FOR the strip, not merely
            // mention it inside some other selector.
            if (preg_match('/(^|[,}\s])'.preg_quote($selector, '/').'[\s,{]/', $body) === 1) {
                return ['breakpoint' => (int) $m[1][0], 'css' => $body];
            }
        }

        return null;
    }

    /** Everything at the top level, with every @media block removed. */
    private function stylesheetOutsideMediaQueries(): string
    {
        $css = $this->stylesheet();
        $out = '';
        $offset = 0;

        while (preg_match('/@media[^{]*\{/', $css, $m, PREG_OFFSET_CAPTURE, $offset)) {
            $start = $m[0][1];
            $open = $start + strlen($m[0][0]);
            $out .= substr($css, $offset, $start - $offset);
            $offset = $open + strlen($this->balancedBody($css, $open)) + 1;
        }

        return $out.substr($css, $offset);
    }

    private function ruleBody(string $selector, string $css): ?string
    {
        $pattern = '/(^|\})\s*'.preg_quote($selector, '/').'\s*\{([^}]*)\}/m';

        return preg_match($pattern, $css, $m) === 1 ? $m[2] : null;
    }

    /** The text between an opening brace and its matching close. */
    private function balancedBody(string $css, int $open): string
    {
        $depth = 1;
        $i = $open;

        while ($i < strlen($css) && $depth > 0) {
            $depth += match ($css[$i]) { '{' => 1, '}' => -1, default => 0 };
            $i++;
        }

        return substr($css, $open, $i - $open - 1);
    }

    private function stylesheet(): string
    {
        $css = file_get_contents(self::CSS);
        $this->assertIsString($css);

        return $css;
    }
}
