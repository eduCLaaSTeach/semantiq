<?php

declare(strict_types=1);

namespace Tests\Architecture;

use App\Modules\Platform\Setup\Bootstrap\BootstrapPrincipal;
use App\Modules\Platform\Setup\Http\Middleware\RequireBootstrapSession;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use ReflectionClass;
use Tests\TestCase;

/**
 * B1, B2 and B9. THE BOOTSTRAP PRINCIPAL CANNOT BECOME A USER, AND CANNOT
 * REACH ANYTHING A USER REACHES.
 *
 * These are TYPE and ROUTE-TABLE facts, not behaviour. A behavioural test can
 * only show that a bootstrap request was refused on the paths it tried; these
 * show there is no path, and they fail when somebody ADDS one - which is the
 * case no behavioural test can cover, because the test for a route that does
 * not exist yet does not exist either.
 */
final class BootstrapIsNotAUserTest extends TestCase
{
    /**
     * B2. The principal is a different type, with nothing the access model can
     * accept.
     *
     * Mutation: make BootstrapPrincipal extend Model, or implement
     * Authenticatable "so the guard can be reused".
     */
    public function test_the_principal_is_not_a_model_and_not_authenticatable(): void
    {
        $class = new ReflectionClass(BootstrapPrincipal::class);

        $this->assertFalse($class->isSubclassOf(Model::class),
            'BootstrapPrincipal is an Eloquent model. It can then be saved, related to, serialised '
            .'into a response and handed to anything that takes a model.');

        $this->assertFalse($class->implementsInterface(Authenticatable::class),
            'BootstrapPrincipal is Authenticatable, so Laravel\'s auth layer would accept it '
            .'wherever a User is expected.');

        $this->assertTrue($class->isReadOnly(),
            'BootstrapPrincipal is mutable, so a request-scoped principal can be edited after the '
            .'guard set it.');

        $properties = array_map(fn ($p): string => $p->getName(), $class->getProperties());
        sort($properties);

        $this->assertSame(['email', 'id'], $properties,
            'BootstrapPrincipal has grown a field. It has no roles, no organisation and no status '
            .'only while there is nowhere to put one.');
    }

    /**
     * B2. The setup module names no part of the access model, so "give the
     * bootstrap principal an entitlement" has nothing to do it with.
     */
    public function test_the_setup_module_can_grant_nothing(): void
    {
        $sources = [];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(__DIR__.'/../../app/Modules/Platform/Setup'),
        );

        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $sources[$file->getPathname()] = (string) preg_replace(
                    '#/\*.*?\*/#s',
                    '',
                    (string) file_get_contents($file->getPathname()),
                );
            }
        }

        $this->assertNotSame([], $sources);

        foreach ($sources as $path => $source) {
            foreach (['AccessEngine', 'RoleAssignment::', 'DomainEntitlement', 'RoleCatalogue'] as $needle) {
                $this->assertStringNotContainsString($needle, $source,
                    basename($path)." names [{$needle}]. Setting up an integration is not an "
                    .'entitlement, and the setup module must not be able to become one.');
            }
        }
    }

    /**
     * B1. EVERY console route requires EnsureSessionIsCurrent - AS AN EQUALITY
     * over the route table, not a sample.
     *
     * Mutation: drop the middleware from one console route. A sampled test
     * would pass unless it happened to pick that one.
     */
    public function test_every_console_route_requires_a_user_session(): void
    {
        $withoutUserSession = [];
        $checked = 0;

        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'console')) {
                continue;
            }

            $checked++;

            $middleware = implode(' ', $route->gatherMiddleware());

            if (! str_contains($middleware, 'EnsureSessionIsCurrent')) {
                $withoutUserSession[] = implode('|', $route->methods()).' '.$route->uri();
            }
        }

        $this->assertGreaterThan(20, $checked,
            'Almost no console routes were examined, so this guard would pass against an empty '
            .'route table.');

        $this->assertSame([], $withoutUserSession,
            'These console routes do not require a User session, so a bootstrap request could '
            .'reach them: '.implode(', ', $withoutUserSession));
    }

    /** ...and no First-Run route requires one, which is the other half. */
    public function test_no_first_run_route_requires_a_user_session(): void
    {
        $checked = 0;

        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'first-run')) {
                continue;
            }

            $checked++;

            $this->assertStringNotContainsString(
                'EnsureSessionIsCurrent',
                implode(' ', $route->gatherMiddleware()),
                "[{$route->uri()}] requires a User session. The bootstrap principal is not a User, "
                .'so this route is unreachable during the only state it exists for.',
            );
        }

        $this->assertGreaterThan(10, $checked);
    }

    /** The two guards set disjoint attributes, which is what keeps them apart. */
    public function test_the_two_guards_set_different_request_attributes(): void
    {
        $bootstrap = (string) file_get_contents(
            (new ReflectionClass(RequireBootstrapSession::class))->getFileName(),
        );

        $this->assertStringContainsString("'semantiq_bootstrap'", $bootstrap);

        $this->assertStringNotContainsString("set('semantiq_user'", $bootstrap,
            'The bootstrap guard sets semantiq_user, which is the attribute every console route '
            .'reads. The two surfaces would then share a key, and the type boundary would be the '
            .'only thing left between a bootstrap principal and the console.');
    }
}
