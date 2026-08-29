<?php

declare(strict_types=1);

namespace Tests\Feature\Ui;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Every class and every icon a view references must actually exist.
 *
 * WHY THIS EXISTS. The R1.4c-i Privacy Requests screens shipped using
 * `card-header`, `card-title`, `empty-state`, `card-note`, `table-wrap`,
 * `table`, `label`, `field-note`, `form-actions` and `required` - **none of
 * which is defined in the stylesheet**. Fourteen of thirty classes on one page
 * were inert. `.card` carries no padding of its own, so the text sat against
 * the border; `.empty` supplies the centring, and was never applied; and
 * `.settings-fields`, which constrains a form to a readable 560px, was missing
 * so the inputs ran the full width of the page.
 *
 * The same page referenced four sprite icons that do not exist. An `<svg>` with
 * an unresolvable `<use href>` still occupies its 18x18 box, so the defect
 * renders as an invisible gap rather than a broken image - nothing looks wrong
 * enough to notice, and the heading is simply misaligned for ever.
 *
 * NONE OF IT FAILED A TEST. Every functional test passed throughout, because a
 * class name that does nothing still renders, and the assertions were about
 * text and behaviour. Only a person looking at the screen could see it, and
 * that person was the product owner, after it reached production.
 *
 * This is the cheap mechanical check that would have caught it: a class used in
 * a view but absent from the stylesheet is either a typo or a design-system
 * invention, and both are defects.
 */
class DesignSystemContractTest extends TestCase
{
    /**
     * Classes that are legitimately absent from the stylesheet.
     *
     * Kept deliberately short. Every entry is a promise that the class does
     * something other than style, and each says which.
     *
     * @var list<string>
     */
    private const NOT_STYLE = [
        /*
         * The navigation group markers. `shell.js` selects these by their
         * `data-nav-group` attributes, six times; the class names sit beside
         * them as readable markers and are deliberately unstyled. Verified
         * rather than assumed: `data-nav-group` appears 0 times in app.css and
         * 6 times in shell.js, and the rail renders correctly.
         */
        'nav-group',
        'nav-group-body',
    ];

    /**
     * @return list<string>
     */
    private function bladeFiles(): array
    {
        $files = [];

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'))
        );

        foreach ($it as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    #[Test]
    public function every_class_a_view_uses_is_defined_in_the_stylesheet(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertIsString($css);

        preg_match_all('/\.([a-zA-Z][a-zA-Z0-9_-]*)/', $css, $m);
        $defined = array_flip($m[1]);

        $offenders = [];

        foreach ($this->bladeFiles() as $path) {
            $html = file_get_contents($path);

            /* Static class attributes only. An attribute containing a Blade
             * expression is resolved at runtime and cannot be read here. */
            preg_match_all('/class="([^"{}@]*)"/', (string) $html, $c);

            foreach ($c[1] as $attr) {
                foreach (preg_split('/\s+/', trim($attr)) as $class) {
                    if ($class === '' || isset($defined[$class])) {
                        continue;
                    }

                    if (in_array($class, self::NOT_STYLE, true)) {
                        continue;
                    }

                    $offenders[] = basename($path).' -> .'.$class;
                }
            }
        }

        $offenders = array_values(array_unique($offenders));

        $this->assertSame(
            [],
            $offenders,
            "These views use classes that no stylesheet rule defines, so the markup renders unstyled:\n  "
            .implode("\n  ", $offenders)
        );
    }

    #[Test]
    public function every_icon_a_view_references_exists_in_the_sprite(): void
    {
        $sprite = file_get_contents(resource_path('views/partials/icons.blade.php'));

        $this->assertIsString($sprite);

        preg_match_all('/id="(i-[a-z0-9-]+)"/', $sprite, $s);
        $available = array_flip($s[1]);

        $this->assertNotEmpty($available, 'the sprite was not read, so this test would pass vacuously');

        $offenders = [];

        foreach ($this->bladeFiles() as $path) {
            preg_match_all('/href="#(i-[a-z0-9-]+)"/', (string) file_get_contents($path), $u);

            foreach ($u[1] as $icon) {
                if (! isset($available[$icon])) {
                    $offenders[] = basename($path).' -> #'.$icon;
                }
            }
        }

        $offenders = array_values(array_unique($offenders));

        $this->assertSame(
            [],
            $offenders,
            'These views reference sprite icons that do not exist. The svg still occupies its box, so '
            ."this renders as an invisible gap rather than a broken image:\n  ".implode("\n  ", $offenders)
        );
    }
}
