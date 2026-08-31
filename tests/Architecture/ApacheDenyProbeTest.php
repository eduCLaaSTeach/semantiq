<?php

declare(strict_types=1);

namespace Tests\Architecture;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Guards the temporary Apache denial probe.
 *
 * The probe answers one question: can Apache refuse a request at the
 * public_html level, before the catch-all forwarder rewrites it into public/?
 * Today every protected path returns 404 from Laravel and none returns 403 from
 * Apache, so the forwarder is doing all the protecting. The planned move of the
 * front controller to public_html/index.php removes that forwarder and makes
 * the deny rules load-bearing, so the capability must be demonstrated first.
 *
 * These tests exist because the probe is only meaningful if three things hold,
 * and each of them is easy to break by accident:
 *
 *  - the rule is present at all;
 *  - it appears BEFORE the catch-all, or the forwarder wins and the result is a
 *    meaningless 404;
 *  - nothing in the application answers the path, or a 200 would look like a
 *    routing success rather than the critical failure it would be.
 */
final class ApacheDenyProbeTest extends TestCase
{
    private const PROBE_PATH = '__semantiq_apache_deny_probe';

    private const HTACCESS = __DIR__.'/../../deployment/public_html.htaccess';

    public function test_the_probe_rule_is_present(): void
    {
        $this->assertStringContainsString(
            self::PROBE_PATH,
            file_get_contents(self::HTACCESS),
            'The deny probe rule is missing from the forwarder.'
        );
    }

    /**
     * Order is the whole point. A deny rule after the catch-all never runs,
     * and the probe would report 404 while proving nothing at all.
     */
    public function test_the_probe_rule_precedes_the_catch_all_forwarder(): void
    {
        $htaccess = file_get_contents(self::HTACCESS);

        $probe = strpos($htaccess, 'RewriteRule ^'.self::PROBE_PATH);
        $catchAll = strpos($htaccess, 'RewriteRule ^(.*)$ public/$1');

        $this->assertNotFalse($probe, 'Probe rule not found.');
        $this->assertNotFalse($catchAll, 'Catch-all forwarder not found.');

        $this->assertLessThan(
            $catchAll,
            $probe,
            'The probe rule sits after the catch-all forwarder, so it can never fire. '
            .'A 404 from that arrangement would prove nothing.'
        );
    }

    public function test_the_probe_rule_denies_rather_than_rewrites(): void
    {
        $this->assertMatchesRegularExpression(
            '/RewriteRule \^'.preg_quote(self::PROBE_PATH, '/').'\(\/\|\$\) - \[F,L\]/',
            file_get_contents(self::HTACCESS),
            'The probe must deny with [F]. Anything else does not test a denial.'
        );
    }

    /**
     * If the application answered this path, a 200 would read as ordinary
     * routing rather than the critical failure it would actually be.
     */
    public function test_no_application_route_answers_the_probe_path(): void
    {
        foreach (Route::getRoutes() as $route) {
            $this->assertStringNotContainsString(
                self::PROBE_PATH,
                $route->uri(),
                "Route [{$route->uri()}] answers the probe path."
            );
        }

        $this->get('/'.self::PROBE_PATH)->assertNotFound();
    }

    public function test_no_file_exists_at_the_probe_path(): void
    {
        foreach ([base_path(self::PROBE_PATH), public_path(self::PROBE_PATH)] as $path) {
            $this->assertFileDoesNotExist($path, 'A real file sits at the probe path.');
        }
    }

    /**
     * .well-known carries the ACME challenge. If the probe were ever inserted
     * above it, a future edit could shadow TLS renewal - which fails silently,
     * weeks later.
     */
    public function test_the_probe_does_not_displace_the_acme_passthrough(): void
    {
        $htaccess = file_get_contents(self::HTACCESS);

        $this->assertLessThan(
            strpos($htaccess, 'RewriteRule ^'.self::PROBE_PATH),
            strpos($htaccess, 'RewriteRule ^\\.well-known/'),
            '.well-known must still be matched first.'
        );
    }
}
