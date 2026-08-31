<?php

declare(strict_types=1);

namespace Tests\Architecture;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * A route prefix may not share a name with a directory in the deployment root.
 *
 * Under D-08B the whole Laravel tree is deployed inside the document root, so
 * the web server must refuse /app/, /config/, /vendor/ and the rest to protect
 * what is on disk. A URL that starts with one of those names therefore cannot
 * be served, whatever the router thinks.
 *
 * The first live exposure test found this the expensive way: the authenticated
 * area was mounted at /app, and /app/ answered 302 from the deny-by-default
 * redirect where the security gate required 403 or 404. Nothing was exposed -
 * the source files behind it all returned 404 - but a route that the hardened
 * forwarder is obliged to block is a route that cannot work in production.
 *
 * This runs against the real route table, so it fails when someone adds such a
 * route rather than when a deployment discovers it.
 */
final class RoutePrefixCollisionTest extends TestCase
{
    /**
     * Kept in step with the directory list in deployment/public_html.htaccess.
     */
    private const HTACCESS = __DIR__.'/../../deployment/public_html.htaccess';

    private const PROTECTED_ROOTS = [
        'app', 'bootstrap', 'config', 'database', 'doc', 'deployment',
        'node_modules', 'public', 'resources', 'routes', 'storage', 'tests', 'vendor',
    ];

    public function test_no_route_begins_with_a_protected_directory_name(): void
    {
        foreach (Route::getRoutes() as $route) {
            $first = explode('/', trim($route->uri(), '/'))[0] ?? '';

            $this->assertNotContains(
                $first,
                self::PROTECTED_ROOTS,
                "Route [{$route->uri()}] starts with [{$first}], which is a directory the "
                .'hardened forwarder must refuse. Under D-08B this route can never be reached '
                .'in production. Choose a prefix that is not a deployment-root directory.'
            );
        }
    }

    /**
     * The list above is only useful while it matches the forwarder it mirrors.
     *
     * This direction: nothing is claimed as protected that the forwarder does
     * not actually block.
     */
    public function test_every_listed_root_is_named_in_the_forwarder(): void
    {
        $htaccess = file_get_contents(self::HTACCESS);

        foreach (self::PROTECTED_ROOTS as $dir) {
            $this->assertMatchesRegularExpression(
                '/\b'.preg_quote($dir, '/').'\b/',
                $htaccess,
                "[{$dir}] is treated as protected here but is not named in the forwarder."
            );
        }
    }

    /**
     * And the other direction, which was missing and mattered.
     *
     * The mirror only guarded one way: every name in the list appeared in the
     * forwarder, but nothing checked that every directory the forwarder blocks
     * appeared in the list. When "public" was added to the forwarder, the list
     * was not updated and silently stopped being a mirror - so a route
     * beginning /public would have passed CI and returned 403 in production,
     * which is exactly the /app failure this guard exists to prevent.
     */
    public function test_every_forwarder_root_is_present_in_the_list(): void
    {
        foreach ($this->forwarderRoots() as $dir) {
            $this->assertContains(
                $dir,
                self::PROTECTED_ROOTS,
                "The forwarder blocks [{$dir}] but the collision guard does not know about it, "
                .'so a route using that prefix would pass CI and 403 in production.'
            );
        }
    }

    /**
     * The directory names in the forwarder's deny rule, read from the file
     * rather than restated here - a second hand-maintained copy would drift in
     * exactly the way this test exists to catch.
     *
     * @return list<string>
     */
    private function forwarderRoots(): array
    {
        preg_match(
            '/RewriteRule \^\(([a-z_|]+)\)\(\/\|\$\) - \[F,L\]/',
            file_get_contents(self::HTACCESS),
            $matches
        );

        $this->assertNotEmpty($matches, 'The forwarder directory deny rule was not found.');

        return explode('|', $matches[1]);
    }
}
