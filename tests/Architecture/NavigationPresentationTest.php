<?php

declare(strict_types=1);

namespace Tests\Architecture;

use App\Shared\Navigation\NavigationRegistry;
use PHPUnit\Framework\TestCase;

/**
 * The sidebar rendered the word "building".
 *
 * AppShell printed {node.icon} directly, so the registry KEY became visible
 * user-facing text. Every automated test passed: the node was registered, the
 * route resolved, the authorisation was right. Nobody had asked what the screen
 * actually said.
 *
 * These guards make that class of defect unrepresentable rather than merely
 * discouraged.
 */
final class NavigationPresentationTest extends TestCase
{
    private const SHELL = __DIR__.'/../../resources/js/Layouts/AppShell.jsx';

    private const REGISTRY = __DIR__.'/../../resources/js/Components/Icon.jsx';

    /**
     * The defect itself: an icon key may never be rendered as text.
     *
     * Mutation: put {node.icon} back into AppShell.
     */
    public function test_the_shell_never_renders_an_icon_key_as_text(): void
    {
        $shell = file_get_contents(self::SHELL);

        $this->assertMatchesRegularExpression(
            '/<Icon\s+name=\{node\.icon\}/',
            $shell,
            'The shell does not resolve node.icon through the icon registry.'
        );

        // {node.icon} anywhere OUTSIDE an Icon name attribute is the defect.
        $withoutIconComponent = preg_replace('/<Icon\s+name=\{node\.icon\}\s*\/?>/', '', $shell);

        $this->assertStringNotContainsString(
            '{node.icon}',
            (string) $withoutIconComponent,
            'node.icon is rendered somewhere other than through the Icon registry, so the raw '
            .'key can reach the screen as visible text. That is how "building" got into the sidebar.'
        );
    }

    /**
     * An unknown key must render NOTHING, never its own name.
     *
     * Mutation: make Icon fall back to rendering {name}.
     */
    public function test_an_unknown_icon_key_renders_nothing_rather_than_its_name(): void
    {
        $registry = file_get_contents(self::REGISTRY);

        $this->assertMatchesRegularExpression(
            '/if\s*\(!\s*glyph\)\s*\{\s*return null/',
            $registry,
            'The icon registry does not return null for an unknown key, so a bad key could be '
            .'rendered as text instead of simply not appearing.'
        );

        $this->assertStringNotContainsString(
            '{name}',
            $registry,
            'The icon registry renders the key itself somewhere. An unknown glyph must produce '
            .'nothing at all.'
        );
    }

    /**
     * Every registered node's icon must exist in the ONE registry.
     *
     * This is the guard that would have caught "building" at the source: it was
     * never a registered concept, so it could only ever have been text.
     */
    public function test_every_navigation_icon_exists_in_the_central_registry(): void
    {
        $registered = $this->registryKeys();

        $this->assertNotEmpty($registered, 'The icon registry is empty, so this test proves nothing.');

        foreach ($this->declaredNavigationIcons() as $file => $icon) {
            $this->assertContains(
                $icon,
                $registered,
                "Navigation in {$file} declares icon [{$icon}], which is not in the central registry. "
                .'Add the glyph to Icon.jsx in the approved style, or use a registered concept.'
            );
        }
    }

    /** Symbol ids follow the standard's i-<concept> naming. */
    public function test_registry_keys_follow_the_approved_naming(): void
    {
        foreach ($this->registryKeys() as $key) {
            $this->assertMatchesRegularExpression(
                '/^i-[a-z][a-z0-9-]*$/',
                $key,
                "Icon key [{$key}] does not follow the standard's i-<concept> naming."
            );
        }
    }

    /**
     * One registry, one style. Every glyph is a 24px outline at 2px stroke with
     * round caps and joins, and there is no second icon source anywhere.
     */
    public function test_there_is_exactly_one_icon_registry_in_the_approved_style(): void
    {
        $registry = file_get_contents(self::REGISTRY);

        foreach ([
            'viewBox="0 0 24 24"',
            'strokeWidth="2"',
            'strokeLinecap="round"',
            'strokeLinejoin="round"',
            'fill="none"',
            'aria-hidden="true"',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $registry,
                "The icon registry is missing [{$required}], required by the shared standard."
            );
        }

        // No second icon source: no other component may define an inline <svg>,
        // and no emoji may stand in for a glyph.
        foreach ($this->frontendFiles() as $file) {
            if (realpath($file) === realpath(self::REGISTRY)) {
                continue;
            }

            $this->assertStringNotContainsString(
                '<svg',
                file_get_contents($file),
                basename($file).' defines its own inline SVG. The standard requires ONE central '
                .'registry; add the glyph to Icon.jsx instead.'
            );
        }
    }

    /**
     * No developer terminology, enum value, route name or debug text may appear
     * as a user-facing navigation label.
     */
    public function test_navigation_labels_are_written_for_people(): void
    {
        foreach ($this->declaredNavigationLabels() as $file => $label) {
            $this->assertMatchesRegularExpression(
                '/^[A-Z][A-Za-z0-9 &-]*$/',
                $label,
                "Navigation label [{$label}] in {$file} does not read as a product label. Labels "
                .'are written for people: no snake_case, no dots, no route names, no keys.'
            );

            foreach (['_', '.', '::', 'i-'] as $developerMarker) {
                $this->assertStringNotContainsString(
                    $developerMarker,
                    $label,
                    "Navigation label [{$label}] contains [{$developerMarker}], which is internal "
                    .'terminology reaching a user-facing surface.'
                );
            }
        }
    }

    /** @return list<string> */
    private function registryKeys(): array
    {
        preg_match_all("/'(i-[a-z0-9-]+)':/", file_get_contents(self::REGISTRY), $keys);

        return $keys[1] ?? [];
    }

    /** @return array<string, string> */
    private function declaredNavigationIcons(): array
    {
        return $this->declaredNavigationAttribute('icon');
    }

    /** @return array<string, string> */
    private function declaredNavigationLabels(): array
    {
        return $this->declaredNavigationAttribute('label');
    }

    /** @return array<string, string> */
    private function declaredNavigationAttribute(string $attribute): array
    {
        $found = [];

        foreach (glob(__DIR__.'/../../app/Modules/*/Providers/*.php') ?: [] as $provider) {
            preg_match_all(
                '/'.$attribute."\s*:\s*'([^']+)'/",
                file_get_contents($provider),
                $matches
            );

            foreach ($matches[1] ?? [] as $index => $value) {
                $found[basename($provider).'#'.$index] = $value;
            }
        }

        return $found;
    }

    /** @return list<string> */
    private function frontendFiles(): array
    {
        $files = [];
        $root = __DIR__.'/../../resources/js';

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

        foreach ($iterator as $file) {
            if ($file->getExtension() === 'jsx') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /** The registry class must remain the single navigation source. */
    public function test_the_navigation_registry_is_the_only_source_of_nodes(): void
    {
        $this->assertTrue(class_exists(NavigationRegistry::class));
    }

    /**
     * No development or verification shortcut may reach the route file.
     *
     * Visual verification needs a signed-in session locally, which is easy to
     * arrange with a throwaway route and catastrophic to ship. This asserts the
     * route file is clean rather than trusting that it was removed.
     */
    public function test_no_development_sign_in_shortcut_exists_in_the_routes(): void
    {
        $routes = file_get_contents(__DIR__.'/../../routes/web.php');

        foreach (['__visual-check', 'VISUAL_CHECK', 'loginAs', 'actingAs', 'dev-login'] as $shortcut) {
            $this->assertStringNotContainsString(
                $shortcut,
                $routes,
                "routes/web.php contains [{$shortcut}], a development sign-in shortcut. It must "
                .'never exist outside a local working copy.'
            );
        }
    }
}
