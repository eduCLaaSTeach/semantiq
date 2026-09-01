<?php

declare(strict_types=1);

namespace Tests\Architecture;

use App\Modules\Platform\Support\DeploymentLayout;
use Tests\TestCase;

/**
 * Guards the production root layout: public_html is the document root, the
 * deployment root, and the home of the front controller.
 *
 * The failure this exists to prevent is specific and quiet. public_path()
 * resolving to the wrong directory does not throw - it returns a path that
 * simply is not there, so the Vite manifest is "missing", assets 404, and the
 * health check reports a build problem that is really a layout problem. Worse,
 * it can be right for web requests and wrong for the CLI at the same time,
 * because only one of those loads the front controller.
 *
 * So both layouts are exercised against real directories rather than asserted
 * about in prose.
 */
final class RootDeploymentLayoutTest extends TestCase
{
    private string $scratch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scratch = sys_get_temp_dir().'/semantiq-layout-'.bin2hex(random_bytes(6));
        mkdir($this->scratch.'/public', 0o777, true);
    }

    protected function tearDown(): void
    {
        foreach ([$this->scratch.'/index.php', $this->scratch.'/public'] as $path) {
            is_dir($path) ? @rmdir($path) : @unlink($path);
        }
        @rmdir($this->scratch);

        parent::tearDown();
    }

    // ---- the resolver, against both real layouts -------------------------

    public function test_the_repository_layout_serves_from_public(): void
    {
        $this->assertFalse(DeploymentLayout::isRootLayout($this->scratch));

        $this->assertSame(
            $this->scratch.'/public',
            DeploymentLayout::publicPath($this->scratch),
            'Without a front controller at the base path the layout is the repository one.'
        );
    }

    public function test_a_front_controller_at_the_base_path_is_the_root_layout(): void
    {
        file_put_contents($this->scratch.'/index.php', '<?php // front controller');

        $this->assertTrue(DeploymentLayout::isRootLayout($this->scratch));

        $this->assertSame(
            $this->scratch,
            DeploymentLayout::publicPath($this->scratch),
            'With a front controller at the base path the servable directory IS the base path.'
        );
    }

    /**
     * A trailing separator must not produce "…//public" or a base path with a
     * stray slash: both are real paths that silently miss.
     */
    public function test_a_trailing_separator_does_not_corrupt_the_resolved_path(): void
    {
        $this->assertSame(
            $this->scratch.'/public',
            DeploymentLayout::publicPath($this->scratch.'/')
        );

        file_put_contents($this->scratch.'/index.php', '<?php');

        $this->assertSame($this->scratch, DeploymentLayout::publicPath($this->scratch.'/'));
    }

    /**
     * The whole scheme rests on the repository never containing a base-path
     * index.php. One would make every developer machine and CI run resolve
     * public_path() to the repository root, where no assets live.
     */
    public function test_the_repository_has_no_front_controller_at_its_base_path(): void
    {
        $this->assertFileDoesNotExist(
            base_path('index.php'),
            'A base-path index.php makes the repository impersonate the production layout. '
            .'The production front controller belongs at deployment/public_html.index.php.'
        );
    }

    /**
     * CI and local development must keep resolving to public/, or the suite
     * stops testing the layout developers actually run.
     */
    public function test_the_running_application_resolves_public_path_for_this_layout(): void
    {
        $this->assertSame(
            DeploymentLayout::publicPath(base_path()),
            public_path(),
            'The application is not using the resolver, so the two can drift apart.'
        );

        $this->assertSame(base_path('public'), public_path());
        $this->assertFileExists(public_path('build/manifest.json'));
    }

    // ---- the storage link collision --------------------------------------

    /**
     * Under the root layout public_path('storage') IS the real storage
     * directory. Declaring the conventional link would point storage:link at
     * live runtime state.
     */
    public function test_the_public_storage_link_is_refused_under_the_root_layout(): void
    {
        file_put_contents($this->scratch.'/index.php', '<?php');

        $this->assertFalse(DeploymentLayout::allowsPublicStorageLink($this->scratch));

        $this->assertSame(
            DeploymentLayout::publicPath($this->scratch).'/storage',
            $this->scratch.'/storage',
            'This is the collision: the link location and the real storage directory are one path.'
        );
    }

    public function test_the_public_storage_link_is_declared_for_the_repository_layout(): void
    {
        $this->assertTrue(DeploymentLayout::allowsPublicStorageLink($this->scratch));

        $this->assertSame(
            [public_path('storage') => storage_path('app/public')],
            config('filesystems.links'),
            'The conventional link should still be configured for the layout that can use it.'
        );
    }

    /**
     * Belt and braces: even with the config empty, the command must never be
     * added to the deployment.
     */
    public function test_the_deployment_never_creates_the_storage_link(): void
    {
        $this->assertStringNotContainsString(
            'storage:link',
            file_get_contents(__DIR__.'/../../.github/workflows/deploy.yml'),
            'storage:link under the root layout would target the real storage directory.'
        );
    }

    // ---- the staged production front controller --------------------------

    public function test_the_production_front_controller_resolves_its_siblings(): void
    {
        $controller = file_get_contents(__DIR__.'/../../deployment/public_html.index.php');

        foreach ([
            "__DIR__.'/vendor/autoload.php'",
            "__DIR__.'/bootstrap/app.php'",
            "__DIR__.'/storage/framework/maintenance.php'",
        ] as $expected) {
            $this->assertStringContainsString(
                $expected,
                $controller,
                "The production front controller must resolve [{$expected}] as a sibling."
            );
        }

        $this->assertStringNotContainsString(
            "__DIR__.'/../",
            $controller,
            'The production front controller walks up out of the deployment root, where nothing it needs lives.'
        );
    }

    /**
     * Maintenance mode has to be reachable from the root controller, or
     * `artisan down` leaves the site serving normally while reporting itself
     * down - the deployment window would be a lie.
     */
    public function test_the_production_front_controller_still_honours_maintenance_mode(): void
    {
        $this->assertStringContainsString(
            'maintenance.php',
            file_get_contents(__DIR__.'/../../deployment/public_html.index.php'),
            'The production front controller does not check for maintenance mode.'
        );
    }

    /**
     * Every web-facing file the repository ships reaches the deployment root.
     *
     * The assemble step used to move a HARDCODED LIST out of public/ and then
     * delete what was left. favicon-light.ico and favicon-dark.ico were added to
     * public/, were not on that list, and were deleted before the transfer.
     * They 404'd in production while CI was green, the deployment reported
     * success, and every test here passed - because nothing connected "a file
     * exists in public/" to "the server has it".
     *
     * This is that connection, made on every pull request rather than after a
     * deployment. Mutation: put the hardcoded list back, or add a file to
     * public/ that the workflow cannot carry.
     */
    public function test_every_web_facing_file_reaches_the_deployment_root(): void
    {
        $workflow = file_get_contents(__DIR__.'/../../.github/workflows/deploy.yml');

        // The move must be driven by what is IN public/, not by a fixed list.
        $this->assertMatchesRegularExpression(
            '/for asset in _deploy\/public\/\*/',
            $workflow,
            'The assemble step moves a fixed list out of public/ rather than its actual contents, '
            .'so a newly added web-facing file is deleted before the transfer instead of shipped.'
        );

        $this->assertStringNotContainsString(
            'for asset in build favicon.ico robots.txt',
            $workflow,
            'The hardcoded asset list is back. It is what dropped the two favicons.'
        );

        // And the assemble step must verify the arrival rather than assume it.
        $this->assertStringContainsString(
            'did not reach the deployment root',
            $workflow,
            'Nothing checks that public/ actually arrived at the deployment root.'
        );

        // The live check must NOT demand that .htaccess is served. It is the
        // server's own configuration; demanding 200 for it failed the very
        // deployment that fixed the favicons, and a rule that made it serve
        // would hand over the security boundary.
        // Matched against the .htaccess check specifically. Looking for
        // "expected 403" alone passed against a DIFFERENT check already in this
        // workflow, so the guard reported safety it was not providing.
        $this->assertMatchesRegularExpression(
            '/hcode.*\.htaccess.*\n\s*\[ "\$hcode" = "403" \]/',
            $workflow,
            'Nothing asserts that /.htaccess itself is refused with 403.'
        );

        $this->assertMatchesRegularExpression(
            '/case "\$name" in \.\*\) continue/',
            $workflow,
            'The live check does not skip dotfiles, so it demands that .htaccess be served.'
        );

        // Finally, the files themselves: everything the root view references
        // must exist to be shipped in the first place.
        $view = file_get_contents(__DIR__.'/../../resources/views/app.blade.php');

        preg_match_all('/href="\/([A-Za-z0-9._-]+\.(?:ico|png|svg|webmanifest))"/', $view, $referenced);

        $this->assertNotEmpty(
            $referenced[1] ?? [],
            'The root view references no web-facing files, so this guard proves nothing.'
        );

        foreach (array_unique($referenced[1]) as $file) {
            $this->assertFileExists(
                __DIR__.'/../../public/'.$file,
                "The root view references /{$file}, which is not in public/, so it can only 404."
            );
        }
    }
}
