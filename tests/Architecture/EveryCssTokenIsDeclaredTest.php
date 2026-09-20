<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * EVERY var(--token) USED IS A TOKEN THAT EXISTS.
 *
 * WHY THIS EXISTS. Writing the P1-10 setup chrome produced five invented
 * tokens - --page, --hover, --selected, --focus, --danger - all of which look
 * exactly like the real ones. CSS does not complain about an undefined custom
 * property; the declaration is simply dropped. The result is not a build
 * failure, it is:
 *
 *   - a focus ring that does not appear, so the screen fails keyboard
 *     accessibility silently;
 *   - a hover state that does nothing, so a link looks unclickable;
 *   - an error message in the body colour rather than the danger colour, so a
 *     failure reads as a note;
 *   - a panel with no background, which in dark mode is text on nothing.
 *
 * Every one of those is on the professional-polish gate, and NONE of them
 * fails a build, a unit test or a page render. They fail only when somebody
 * looks - which is precisely the class of defect an automated guard should
 * catch instead.
 *
 * Mutation: use var(--focus) anywhere in app.css.
 */
final class EveryCssTokenIsDeclaredTest extends TestCase
{
    public function test_no_stylesheet_uses_a_token_it_never_declares(): void
    {
        // COMMENTS STRIPPED FIRST. The notes explaining WHY a token was
        // wrong necessarily name it, so a guard that read them would be
        // failed by its own explanation - and the fix would be to delete the
        // explanation, which is the wrong lesson. Same rule as the connection
        // guards: a guard must not be satisfiable, or defeatable, by prose.
        $css = $this->withoutComments(
            (string) file_get_contents(__DIR__.'/../../resources/css/app.css'),
        );

        preg_match_all('/--[a-z0-9-]+\s*:/i', $css, $declaredMatches);

        $declared = array_unique(array_map(
            static fn (string $token): string => rtrim(rtrim($token), ':'),
            $declaredMatches[0],
        ));

        $this->assertGreaterThan(30, count($declared),
            'Almost no custom properties were found, so this guard would pass against anything.');

        preg_match_all('/var\(\s*(--[a-z0-9-]+)/i', $css, $usedMatches);

        $used = array_unique($usedMatches[1]);

        $this->assertNotEmpty($used);

        $undeclared = array_values(array_diff($used, $declared));

        sort($undeclared);

        $this->assertSame(
            [],
            $undeclared,
            'These custom properties are used and never declared: '.implode(', ', $undeclared)
            .'. CSS drops an undefined custom property silently, so this does not fail a build or '
            .'a render - it produces a missing focus ring, a dead hover state or an error message '
            .'in the wrong colour, and only fails when somebody looks.',
        );
    }

    /**
     * ...and every token is declared in BOTH themes, or inherits one that is.
     *
     * A token declared only in the light block renders as the light value on a
     * dark card. That is how a badge ends up at 1.15:1 - which this project has
     * already shipped once, and which the token comments in app.css describe.
     */
    private function withoutComments(string $css): string
    {
        return (string) preg_replace('#/\*.*?\*/#s', '', $css);
    }

    public function test_the_dark_theme_overrides_every_colour_it_needs_to(): void
    {
        $css = $this->withoutComments(
            (string) file_get_contents(__DIR__.'/../../resources/css/app.css'),
        );

        $darkStart = strpos($css, 'prefers-color-scheme: dark');

        $this->assertNotFalse($darkStart, 'There is no dark theme block.');

        $dark = substr($css, $darkStart);

        // The surfaces and text colours the setup chrome rests on. If any of
        // these had no dark value, the whole First-Run surface would render
        // light-on-light or dark-on-dark.
        foreach (['--canvas', '--card', '--card-border', '--text', '--text-muted', '--chrome', '--accent'] as $token) {
            $this->assertStringContainsString($token.':', $dark,
                "[{$token}] has no dark value, so every surface built on it renders the light "
                .'colour on a dark card.');
        }
    }
}
