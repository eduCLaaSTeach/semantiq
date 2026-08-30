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
    private const PROTECTED_ROOTS = [
        'app', 'bootstrap', 'config', 'database', 'doc', 'deployment',
        'node_modules', 'resources', 'routes', 'storage', 'tests', 'vendor',
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
     */
    public function test_the_protected_list_matches_the_forwarder(): void
    {
        $htaccess = file_get_contents(__DIR__.'/../../deployment/public_html.htaccess');

        foreach (self::PROTECTED_ROOTS as $dir) {
            $this->assertMatchesRegularExpression(
                '/\b'.preg_quote($dir, '/').'\b/',
                $htaccess,
                "[{$dir}] is treated as protected here but is not named in the forwarder."
            );
        }
    }
}
