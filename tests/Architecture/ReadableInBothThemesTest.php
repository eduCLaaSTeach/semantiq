<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * Text must be readable in BOTH themes.
 *
 * A business unit's name was invisible on the dark card, because nothing in the
 * application defined a link colour at all - so a bare anchor fell through to
 * the browser default, and the VISITED default is a dark purple. And the Active
 * status pill hardcoded a dark-green hex with no dark-theme value, measuring
 * 1.15:1 on the dark card.
 *
 * The shared standard names this failure exactly: "Semantic colors used as
 * text, icons, or thin edges on a surface always go through the theme-aware
 * readable tokens, never the raw semantic hex; raw Violet-Red on a dark card is
 * about 1.6:1 and invisible."
 *
 * The measurement itself is a browser job - a computed colour against the
 * surface it is actually painted on - and that audit is recorded in the
 * verification document. What these guards do is make the two CAUSES
 * unrepresentable: an undefined link colour, and a colour that exists in one
 * theme only.
 */
final class ReadableInBothThemesTest extends TestCase
{
    private const CSS = __DIR__.'/../../resources/css/app.css';

    /**
     * Every link has a defined colour, so none can fall back to the browser's.
     *
     * Mutation: delete the `a { color: ... }` rule.
     */
    public function test_links_never_fall_back_to_the_browser_default(): void
    {
        $css = $this->stylesheet();

        $this->assertMatchesRegularExpression(
            '/\na\s*\{[^}]*color:\s*var\(--accent\)/',
            $css,
            'No rule gives a bare link a colour, so any anchor without a class renders in the '
            .'browser default - and the VISITED default is invisible on the dark card.'
        );

        // A visited link must not fade out. Its default is what made the
        // business unit name unreadable in the first place.
        // Anchored to a BARE a:visited at the start of a line. Without the
        // anchor this matched ".org-list a:visited" and passed with the global
        // rule deleted - reporting safety it was not providing.
        $this->assertMatchesRegularExpression(
            '/^a:visited\s*\{\s*color:\s*var\(--accent\)/m',
            $css,
            'Visited links are not pinned to the link colour, so they revert to the browser purple.'
        );

        // The links that surfaced the defect are the ones inside tables.
        $this->assertMatchesRegularExpression(
            '/\.org-table a,\s*\n\.org-list a\s*\{[^}]*color:\s*var\(--accent\)/',
            $css,
            'A link inside a table or list has no colour of its own.'
        );
    }

    /**
     * A semantic colour used as TEXT goes through a theme-aware token.
     *
     * Mutation: put a raw hex back into .org-pill-active.
     */
    public function test_a_status_pill_uses_theme_aware_tokens_rather_than_a_raw_hex(): void
    {
        $css = $this->stylesheet();

        preg_match('/\.org-pill-active\s*\{([^}]*)\}/', $css, $rule);

        $this->assertNotEmpty($rule, 'No .org-pill-active rule was found, so this proves nothing.');

        $this->assertDoesNotMatchRegularExpression(
            '/#[0-9A-Fa-f]{3,8}/',
            $rule[1],
            'The Active pill hardcodes a hex. A semantic colour used as text goes through a '
            .'theme-aware token, or it is readable in one theme and not the other.'
        );

        $this->assertStringContainsString('var(--badge-success-fg)', $rule[1]);
        $this->assertStringContainsString('var(--badge-success-bg)', $rule[1]);
    }

    /**
     * Every token defined for the light theme has a dark value, and vice versa.
     *
     * This is the general form of the pill defect: a token that exists in one
     * theme only silently keeps its light value on the dark card.
     *
     * Mutation: add a token to :root without adding it to both dark blocks.
     */
    public function test_every_theme_token_is_defined_in_both_themes(): void
    {
        $css = $this->stylesheet();

        $light = $this->tokensIn($css, '/:root \{(.*?)\n\}/s');
        $preference = $this->tokensIn($css, "/:root:not\(\[data-theme='light'\]\) \{(.*?)\n  \}/s");
        $explicit = $this->tokensIn($css, "/:root\[data-theme='dark'\] \{(.*?)\n\}/s");

        $this->assertNotEmpty($light, 'No light tokens were found, so this guard proves nothing.');
        $this->assertNotEmpty($preference, 'No dark-preference tokens were found.');
        $this->assertNotEmpty($explicit, 'No explicit-dark tokens were found.');

        // Structural tokens - spacing, radius, type, dimensions - are the same
        // in both themes by design. Only the ones the dark theme redefines have
        // to be complete, and both dark blocks must agree with each other.
        $this->assertSame(
            $preference,
            $explicit,
            'The two dark blocks define different tokens, so choosing Dark explicitly gives a '
            .'different palette from having the system set to dark.'
        );

        foreach ($preference as $token) {
            $this->assertContains(
                $token,
                $light,
                "Token [{$token}] has a dark value but no light one, so it is undefined in the "
                .'light theme.'
            );
        }
    }

    /**
     * @return list<string>
     */
    private function tokensIn(string $css, string $pattern): array
    {
        if (! preg_match($pattern, $css, $block)) {
            return [];
        }

        preg_match_all('/(--[a-z0-9-]+):/', $block[1], $tokens);

        $found = $tokens[1] ?? [];

        sort($found);

        return array_values(array_unique($found));
    }

    private function stylesheet(): string
    {
        // Comments first: this file explains the defect in prose that names the
        // very selectors and hexes being checked.
        return (string) preg_replace('#/\*.*?\*/#s', '', file_get_contents(self::CSS));
    }
}
