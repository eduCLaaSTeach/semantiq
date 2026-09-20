<?php

declare(strict_types=1);

namespace Tests\Architecture;

use App\Modules\Platform\Bootstrap\GrantIssuer;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * CORRECTION 1. EVERY STATIC FIRST-RUN ROUTE REACHES ITS OWN CONTROLLER, AND
 * NOT BECAUSE OF THE ORDER THEY HAPPEN TO BE DECLARED IN.
 *
 * `/first-run/{grant}` has been in production since P1-00 with no constraint.
 * `/first-run/closed` survived it only because it is declared first - and
 * declaration order is invisible at the call site, lost by any refactor that
 * sorts or regroups the lines, and silently stops protecting anything the
 * moment a static route is added below.
 *
 * P1-10 adds eight static routes at that same depth, so this stopped being
 * theoretical.
 *
 * THE POINT OF THIS TEST IS THE REVERSAL. A test that simply resolved each URI
 * against the live router would pass on the UNCONSTRAINED route set too,
 * because the live set is in the lucky order. Re-registering the collection
 * backwards removes the luck and leaves only the constraint - which is the
 * property being claimed.
 *
 * Mutation: remove ->where('grant', '[A-Za-z0-9]{64}') from routes/web.php.
 * The forward case still passes. THE REVERSED CASE FAILS, which is the whole
 * reason it exists.
 */
final class FirstRunRoutesDoNotCollideTest extends TestCase
{
    /**
     * Every static route under /first-run. A grant must never match one.
     *
     * @return list<string>
     */
    private function staticUris(): array
    {
        $uris = [];

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();

            if (! str_starts_with($uri, 'first-run')) {
                continue;
            }

            // The parameterised grant route itself is the thing being guarded
            // against, not a case.
            if (str_contains($uri, '{')) {
                continue;
            }

            $uris[] = $uri;
        }

        return array_values(array_unique($uris));
    }

    /** The constraint is present, and it is the shape GrantIssuer really emits. */
    public function test_the_grant_route_is_constrained_to_the_token_shape(): void
    {
        $grantRoute = null;

        foreach (Route::getRoutes() as $route) {
            if ($route->getName() === 'first_run.begin') {
                $grantRoute = $route;
            }
        }

        $this->assertNotNull($grantRoute, 'The first-run grant route has gone.');

        $pattern = $grantRoute->wheres['grant'] ?? null;

        $this->assertNotNull($pattern,
            'The grant route is unconstrained again. Every static First-Run route now depends on '
            .'declaration order, which is a habit rather than a guarantee.');

        // THE CONSTRAINT MUST ACCEPT WHAT GrantIssuer ACTUALLY PRODUCES. A
        // pattern that rejected real grants would "pass" this file while
        // breaking first-run entirely - a guard that is satisfied by being
        // wrong in the other direction.
        for ($i = 0; $i < 20; $i++) {
            $this->assertMatchesRegularExpression(
                '/^'.$pattern.'$/',
                Str::random(64),
                'The constraint rejects a grant of the shape GrantIssuer emits.',
            );
        }

        // ...and it must reject every static route name.
        foreach ($this->staticUris() as $uri) {
            $segment = substr($uri, strlen('first-run/'));

            if ($segment === '' || $uri === 'first-run') {
                continue;
            }

            $this->assertDoesNotMatchRegularExpression(
                '/^'.$pattern.'$/',
                $segment,
                "[{$segment}] matches the grant constraint, so it can be swallowed by the grant route.",
            );
        }
    }

    /**
     * THE REVERSED CASE. Every static URI still reaches its own route when the
     * collection is registered backwards.
     */
    public function test_every_static_route_resolves_with_the_declaration_order_reversed(): void
    {
        $original = Route::getRoutes();

        $firstRun = [];
        $expected = [];

        foreach ($original as $route) {
            if (str_starts_with($route->uri(), 'first-run')) {
                $firstRun[] = $route;

                if (! str_contains($route->uri(), '{')) {
                    $expected[$route->uri()] = $route->getName();
                }
            }
        }

        $this->assertNotEmpty($expected, 'No static First-Run routes exist, so this proves nothing.');

        $reversed = new RouteCollection;

        foreach (array_reverse($firstRun) as $route) {
            $reversed->add($route);
        }

        foreach ($expected as $uri => $name) {
            $matched = $this->matchIn($reversed, $uri);

            $this->assertSame(
                $name,
                $matched?->getName(),
                "[{$uri}] resolved to [".($matched?->getName() ?? 'nothing').'] with the declaration '
                .'order reversed. It reaches its own controller only because of where it happens to '
                .'be declared, which is not a guarantee.',
            );
        }
    }

    /** And a real grant still reaches the grant route in that reversed set. */
    public function test_a_real_grant_still_resolves_with_the_order_reversed(): void
    {
        $original = Route::getRoutes();
        $firstRun = [];

        foreach ($original as $route) {
            if (str_starts_with($route->uri(), 'first-run')) {
                $firstRun[] = $route;
            }
        }

        $reversed = new RouteCollection;

        foreach (array_reverse($firstRun) as $route) {
            $reversed->add($route);
        }

        // Exactly what GrantIssuer puts in the link it hands an operator.
        $grant = Str::random(64);

        $matched = $this->matchIn($reversed, 'first-run/'.$grant);

        $this->assertSame('first_run.begin', $matched?->getName(),
            'A real grant no longer reaches the grant route, so the constraint is too narrow and '
            .'first-run is broken for everyone.');
    }

    /** GrantIssuer still emits the shape the constraint was written for. */
    public function test_the_issuer_still_emits_the_shape_the_constraint_assumes(): void
    {
        $source = (string) file_get_contents(
            (new \ReflectionClass(GrantIssuer::class))->getFileName(),
        );

        $this->assertStringContainsString('Str::random(64)', $source,
            'GrantIssuer no longer emits a 64-character token, so the route constraint is now '
            .'describing a shape that does not exist and real grants will 404.');
    }

    private function matchIn(RouteCollection $routes, string $uri): ?RoutingRoute
    {
        $request = Request::create('/'.$uri, 'GET');

        try {
            return $routes->match($request);
        } catch (HttpException) {
            return null;
        }
    }
}
