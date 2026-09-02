<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * A solid CTA's label must stay readable in every link state.
 *
 * THE DEFECT THIS EXISTS FOR. The Product Owner clicked Sign out, landed on
 * /auth/signed-out, and saw a blue button with no visible label at all. Same on
 * the Login page: the Microsoft logo, and no "Continue with Microsoft" beside
 * it.
 *
 * The cause was the cascade, not the markup. The global rule
 *
 *     a:visited { color: var(--accent); }          specificity (0,1,1)
 *
 * outranks the button's own
 *
 *     .auth-action { color: var(--accent-contrast); }   specificity (0,1,0)
 *
 * so the moment somebody had actually been to the link's destination - and a
 * button whose entire job is to send you back where you came from is visited
 * almost by definition - the label took the accent colour and sat on an accent
 * background. Measured in the real cascade: text and background both resolved
 * to rgb(25,62,107) in the light theme and rgb(127,173,225) in the dark one.
 * 1.00:1, in both.
 *
 * WHY A COMPONENT TEST WOULD NOT HAVE CAUGHT IT. There are already tests
 * asserting the label exists in the component and reaches the page. Every one of
 * them passed while the button was blank, because the text WAS there - painted
 * in the background colour. Text that exists and text that can be read are
 * different claims, and only the second one matters to the person signing in.
 *
 * This guard is about the CASCADE. The rendered result is verified separately
 * in a real browser, because the specificity being right is necessary and not
 * sufficient - and because this environment's headless Chromium does not render
 * :visited at all, which is recorded in the verification document.
 */
final class AuthCallToActionVisibilityTest extends TestCase
{
    private const CSS = __DIR__.'/../../resources/css/app.css';

    /** The solid, button-like authentication anchors. */
    private const CTA_CLASSES = ['signin-action', 'auth-action'];

    /**
     * Visited-colour rules that are deliberately scoped away from the auth CTAs.
     *
     * These live inside the authenticated console; an authentication CTA is only
     * ever on a pre-authentication screen, so they cannot collide. The list is
     * SHORT AND EXPLICIT on purpose: a new scoped visited rule fails this test
     * until somebody looks at it and decides, which is the whole point - the
     * defect happened because a global link rule silently reached somewhere
     * nobody was thinking about.
     *
     * @var list<string>
     */
    private const SCOPED_ELSEWHERE = [
        '.org-table a:visited',
        '.org-list a:visited',
    ];

    /**
     * 1. Ordinary textual links keep the theme accent in the visited state.
     *
     * The fix must not have been "delete the global rule", which would have made
     * every visited link in the product revert to the browser's purple - the
     * defect ReadableInBothThemesTest was written for.
     *
     * Mutation: change the global a:visited colour, or remove the rule.
     */
    public function test_ordinary_visited_links_keep_the_accent_colour(): void
    {
        $this->assertMatchesRegularExpression(
            '/^a:visited\s*\{\s*color:\s*var\(--accent\)\s*;?\s*\}/m',
            $this->stylesheet(),
            'Ordinary visited links no longer take the theme accent. Solid CTAs are fixed by '
            .'owning their own colour, never by weakening the treatment every other link relies on.'
        );
    }

    /**
     * 2. Each solid CTA sets accent-contrast explicitly, in the visited state.
     *
     * Mutation: drop `:visited` from the CTA rule, or set var(--accent).
     */
    public function test_each_solid_cta_owns_its_text_colour_when_visited(): void
    {
        foreach (self::CTA_CLASSES as $class) {
            $rule = $this->visitedColourRuleFor($class);

            $this->assertNotNull(
                $rule,
                "[.{$class}] has no rule setting its colour in the visited state, so a visited "
                .'button takes the global link colour and its label disappears into its own '
                .'background.'
            );

            $this->assertSame(
                'var(--accent-contrast)',
                $rule['color'],
                "[.{$class}] sets something other than the theme contrast token when visited. A "
                .'hardcoded colour would fix one theme and leave the other unreadable.'
            );
        }
    }

    /** ...and in every other link state, so no single state can be forgotten. */
    public function test_each_solid_cta_covers_every_link_state(): void
    {
        $css = $this->stylesheet();

        foreach (self::CTA_CLASSES as $class) {
            foreach ([':link', ':visited', ':hover', ':focus', ':active'] as $state) {
                $this->assertStringContainsString(
                    ".{$class}{$state}",
                    $css,
                    "[.{$class}] does not name the [{$state}] state. Every state is named so a "
                    .'later rule cannot reintroduce this from a different direction.'
                );
            }
        }
    }

    /**
     * 3. No global link rule can silently outrank a CTA's own colour.
     *
     * THE REAL GUARD. The two above would pass while a future rule such as
     * `.auth-card a:visited { color: var(--accent) }` - specificity (0,2,1) -
     * quietly beat the CTA's (0,2,0) and put the defect straight back.
     *
     * So every :visited rule that sets a colour is compared against the CTA
     * rules by actual computed specificity. Anything that could win has to name
     * the CTA class, or be listed as deliberately scoped elsewhere.
     *
     * Mutation: add `.auth-card a:visited { color: var(--accent); }`.
     */
    public function test_no_visited_rule_can_outrank_a_cta_colour(): void
    {
        $ctaSpecificity = [];

        foreach (self::CTA_CLASSES as $class) {
            $rule = $this->visitedColourRuleFor($class);
            $this->assertNotNull($rule, "No visited colour rule for [.{$class}].");
            $ctaSpecificity[$class] = $rule['specificity'];
        }

        $checked = 0;

        foreach ($this->colourRules() as $selector => $colour) {
            if (! str_contains($selector, ':visited')) {
                continue;
            }

            $checked++;

            if (in_array($selector, self::SCOPED_ELSEWHERE, true)) {
                continue;
            }

            // A rule that names ANY CTA class is one of the fixes, not a threat
            // to them: no element carries both classes, so .signin-action rules
            // can never decide an .auth-action label. Their own correctness is
            // asserted by test_each_solid_cta_owns_its_text_colour_when_visited.
            if ($this->namesACallToAction($selector)) {
                continue;
            }

            foreach (self::CTA_CLASSES as $class) {

                $this->assertLessThan(
                    $this->weigh($ctaSpecificity[$class]),
                    $this->weigh($this->specificity($selector)),
                    "[{$selector}] sets a visited colour at a specificity that beats "
                    ."[.{$class}], so it would decide the label colour of a solid button. "
                    .'Either scope it away from the authentication CTAs and add it to '
                    .'SCOPED_ELSEWHERE with a reason, or give the CTA the higher specificity.'
                );
            }
        }

        $this->assertGreaterThanOrEqual(
            4,
            $checked,
            'Too few visited rules were examined for this to prove anything.'
        );
    }

    /** 4. Both classes are genuinely in scope here, not just one. */
    public function test_both_solid_cta_classes_are_covered(): void
    {
        $this->assertSame(['signin-action', 'auth-action'], self::CTA_CLASSES);

        foreach (self::CTA_CLASSES as $class) {
            $this->assertStringContainsString(
                ".{$class} {",
                $this->stylesheet(),
                "[.{$class}] is not defined at all, so this guard is watching a class that does "
                .'not exist.'
            );
        }

        // And both are actually used by an authentication screen, so neither is
        // a class the guard protects while nothing renders it.
        $used = [];

        foreach (glob(__DIR__.'/../../resources/js/Pages/Auth/*.jsx') ?: [] as $file) {
            $used[] = (string) file_get_contents($file);
        }

        $used[] = (string) file_get_contents(__DIR__.'/../../resources/js/Pages/Entry.jsx');
        $all = implode('', $used);

        foreach (self::CTA_CLASSES as $class) {
            $this->assertStringContainsString(
                $class,
                $all,
                "[.{$class}] is not used by any authentication screen."
            );
        }
    }

    private function namesACallToAction(string $selector): bool
    {
        foreach (self::CTA_CLASSES as $class) {
            if (str_contains($selector, ".{$class}")) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{color: string, specificity: array{0: int, 1: int, 2: int}}|null
     */
    private function visitedColourRuleFor(string $class): ?array
    {
        foreach ($this->colourRules() as $selector => $colour) {
            if ($selector === ".{$class}:visited") {
                return ['color' => $colour, 'specificity' => $this->specificity($selector)];
            }
        }

        return null;
    }

    /**
     * Every selector that sets a colour, split out of its rule list.
     *
     * @return array<string, string>
     */
    private function colourRules(): array
    {
        preg_match_all('/([^{}]+)\{([^}]*)\}/', $this->stylesheet(), $matches, PREG_SET_ORDER);

        $rules = [];

        foreach ($matches as [, $selectorList, $body]) {
            if (! preg_match('/(?<!-)\bcolor:\s*([^;]+);/', $body, $colour)) {
                continue;
            }

            foreach (explode(',', $selectorList) as $selector) {
                $selector = trim(preg_replace('/\s+/', ' ', $selector));

                if ($selector !== '') {
                    $rules[$selector] = trim($colour[1]);
                }
            }
        }

        return $rules;
    }

    /** @return array{0: int, 1: int, 2: int} */
    private function specificity(string $selector): array
    {
        $ids = preg_match_all('/#[\w-]+/', $selector);

        // Classes, attributes and pseudo-CLASSES. `::` is a pseudo-element and
        // counts with elements, so it is excluded here and added below.
        $classes = preg_match_all('/\.[\w-]+|\[[^\]]*\]|(?<!:):(?!:)[\w-]+/', $selector);

        $elements = preg_match_all('/(?<![\w.#:-])[a-z][\w-]*/i', preg_replace('/\[[^\]]*\]/', '', $selector))
            + preg_match_all('/::[\w-]+/', $selector);

        return [$ids, $classes, $elements];
    }

    /** @param array{0: int, 1: int, 2: int} $s */
    private function weigh(array $s): int
    {
        return $s[0] * 10000 + $s[1] * 100 + $s[2];
    }

    private function stylesheet(): string
    {
        return (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents(self::CSS));
    }
}
