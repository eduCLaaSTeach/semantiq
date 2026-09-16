<?php

declare(strict_types=1);

namespace App\Modules\Security\Posture\Adapters;

use App\Modules\Access\Http\Middleware\RequireActionClass;
use App\Modules\Access\Support\ActionClass;
use App\Modules\Security\Catalogue\ControlCatalogue;
use App\Modules\Security\Posture\Evidence;
use App\Modules\Security\Posture\PostureState;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;

/**
 * B-6 - every administration screen is checked by the server.
 *
 * READ FROM THE ROUTE TABLE ITSELF, never from a hand-maintained list. A list
 * kept here would go stale the first time somebody added a route, and would then
 * report full coverage over a gap.
 *
 * P1-05 already makes an unclassified route unconstructable - the class is a
 * required middleware parameter - and RouteAuthorizationMatrixTest asserts the
 * same thing at build time. This row is the RUNTIME statement of that fact for
 * an administrator who cannot read a test suite.
 */
final class RouteCoverageAdapter implements SourceAdapter
{
    public function __construct(private readonly Router $router) {}

    public function answers(): array
    {
        return [ControlCatalogue::ROUTE_COVERAGE];
    }

    public function evidence(): array
    {
        $total = 0;
        $uncovered = 0;

        foreach ($this->router->getRoutes() as $route) {
            $uri = ltrim($route->uri(), '/');

            if (! str_starts_with($uri, 'console/')) {
                continue;
            }

            /*
             * console and console/ themselves are exempt, and this adapter uses
             * the SAME exemption RouteAuthorizationMatrixTest already applies -
             * deliberately, rather than a similar one written from memory.
             *
             * It is the D-11 confirmation page: it carries no menu, no business
             * data and no record, and authenticating is the whole of what it
             * proves. Counting it as uncovered would paint a correctly built
             * deployment Critical, and a false red teaches people to ignore the
             * screen just as reliably as a false green.
             */
            if ($uri === 'console' || $uri === 'console/') {
                continue;
            }

            $total++;

            if (! $this->declaresActionClass($route)) {
                $uncovered++;
            }
        }

        if ($total === 0) {
            return [Evidence::unavailable(
                ControlCatalogue::ROUTE_COVERAGE,
                'No administration screens were found to check.'
            )];
        }

        if ($uncovered > 0) {
            return [Evidence::state(
                ControlCatalogue::ROUTE_COVERAGE,
                PostureState::Critical,
                $uncovered === 1
                    ? 'One administration screen does not say what authority it needs, so the server cannot check it.'
                    : "{$uncovered} administration screens do not say what authority they need, so the server cannot check them.",
            )];
        }

        return [Evidence::state(
            ControlCatalogue::ROUTE_COVERAGE,
            PostureState::Healthy,
            "All {$total} administration screens state the authority they need, and the server checks every request.",
        )];
    }

    /**
     * Whether this route carries RequireActionClass with a class the catalogue
     * recognises. A parameter that is not a real ActionClass fails closed at
     * request time, so it is not coverage.
     */
    private function declaresActionClass(Route $route): bool
    {
        foreach ($route->gatherMiddleware() as $middleware) {
            if (! is_string($middleware)) {
                continue;
            }

            if (! str_starts_with($middleware, RequireActionClass::class.':')) {
                continue;
            }

            $parameter = substr($middleware, strlen(RequireActionClass::class) + 1);

            if (ActionClass::tryFrom($parameter) !== null) {
                return true;
            }
        }

        return false;
    }
}
