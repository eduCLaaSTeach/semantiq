<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * A screen's source with its comments removed.
 *
 * A test that asserts what a screen RENDERS must not be satisfiable by a comment
 * explaining what it renders - and these screens document their copy decisions
 * in prose that quotes the copy. The N13 mutation proved the point: the cell was
 * changed to render nothing at all, and the assertion still passed because the
 * docblock above it said "Not signed in yet".
 *
 * Applied to every screen-source assertion rather than only the one that was
 * caught, because the next one would be caught by a different mutation on a
 * different day.
 */
final class ScreenSource
{
    public static function rendered(string $relativePath): string
    {
        $source = (string) file_get_contents(base_path('resources/js/'.$relativePath));

        $source = (string) preg_replace('#/\*.*?\*/#s', '', $source);

        return (string) preg_replace('#^\s*//.*$#m', '', $source);
    }
}
