<?php

declare(strict_types=1);

namespace Tests\Architecture;

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The guard that would have caught the P1-01 scope gap.
 *
 * P1-01 shipped, passed CI and reached the Product Owner with Update missing on
 * four of its five scoped entities. Nothing failed, because nothing was looking:
 * every test asserted that the operations which existed behaved correctly, and
 * an operation that does not exist has no test to fail.
 *
 * So this test asserts the opposite thing. It does not check that an operation
 * works; it checks that the operation IS THERE. A unit cannot be called complete
 * while one of its declared lifecycle actions has no route.
 *
 * Two halves, and the second is the one that keeps this honest:
 *
 *  1. Every entity in the catalogue below must expose every action listed
 *     against it.
 *  2. Every entity REACHABLE IN THE ROUTE TABLE must appear in the catalogue.
 *     Without this, a future entity could be added with a create route and no
 *     update, and the catalogue - being hand-written - would simply not mention
 *     it. The route table is the thing that cannot be forgotten.
 */
final class LifecycleCompletenessTest extends TestCase
{
    /**
     * The scoped entities of P1-01 and the lifecycle each one owes.
     *
     * Read as: HTTP verb, then the URI suffix after the collection root. `''` is
     * the collection itself; `{}` stands for the record.
     *
     * @var array<string, array<string, array{string, string}>>
     */
    private const LIFECYCLE = [
        'console/organisation' => [
            'read' => ['GET', ''],
            'create' => ['POST', ''],
            'update' => ['PUT', ''],
        ],
        'console/organisation/legal-entities' => [
            'read' => ['GET', ''],
            'create' => ['POST', ''],
            'update' => ['PUT', '/{}'],
            'deactivate' => ['PATCH', '/{}/deactivate'],
            'reactivate' => ['PATCH', '/{}/reactivate'],
        ],
        'console/organisation/business-units' => [
            'read' => ['GET', ''],
            'create' => ['POST', ''],
            'update' => ['PUT', '/{}'],
            'deactivate' => ['PATCH', '/{}/deactivate'],
            'reactivate' => ['PATCH', '/{}/reactivate'],
        ],
        'console/organisation/departments' => [
            'read' => ['GET', ''],
            'create' => ['POST', ''],
            'update' => ['PUT', '/{}'],
            'move' => ['PATCH', '/{}/move'],
            'deactivate' => ['PATCH', '/{}/deactivate'],
            'reactivate' => ['PATCH', '/{}/reactivate'],
        ],
        'console/organisation/teams' => [
            'read' => ['GET', ''],
            'create' => ['POST', ''],
            'update' => ['PUT', '/{}'],
            'move' => ['PATCH', '/{}/move'],
            'deactivate' => ['PATCH', '/{}/deactivate'],
            'reactivate' => ['PATCH', '/{}/reactivate'],
        ],
        // The hierarchy has no deactivate: a relationship is ended, not
        // suspended, and Set and Change are one operation to the server.
        'console/organisation/hierarchy' => [
            'read' => ['GET', ''],
            'set' => ['POST', ''],
            'clear' => ['PATCH', '/{}/clear'],
        ],
    ];

    /** Half one. Mutation: delete one PUT route from routes/web.php. */
    public function test_every_scoped_entity_exposes_its_whole_lifecycle(): void
    {
        $routes = $this->routeSignatures();

        foreach (self::LIFECYCLE as $root => $actions) {
            foreach ($actions as $action => [$verb, $suffix]) {
                $this->assertContains(
                    $verb.' '.$root.$suffix,
                    $routes,
                    "[{$root}] has no {$action} action. A scoped entity is not complete until every "
                    .'lifecycle action the design lists is reachable - which is the defect this test exists for.'
                );
            }
        }
    }

    /** Half two. Mutation: add a create route for a new entity and no update. */
    public function test_no_scoped_entity_escapes_the_catalogue(): void
    {
        $roots = [];

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();

            if (! str_starts_with($uri, 'console/organisation')) {
                continue;
            }

            // The collection root is the URI up to its first parameter.
            $root = explode('/{', $uri)[0];
            $roots[rtrim($root, '/')] = true;
        }

        // Sub-collections of an entity already covered - membership and
        // association live inside their parent's lifecycle, not beside it.
        $nested = [
            'console/organisation/teams/members',
            'console/organisation/business-units/legal-entities',
        ];

        foreach (array_keys($roots) as $root) {
            if (in_array($root, $nested, true)) {
                continue;
            }

            $this->assertArrayHasKey(
                $root,
                self::LIFECYCLE,
                "[{$root}] is reachable but declares no lifecycle. Add it to this catalogue together with "
                .'every action it owes, so that a missing Update cannot pass unnoticed again.'
            );
        }

        $this->assertNotEmpty($roots, 'No Organisation routes were found, so this test proves nothing.');
    }

    /**
     * P1-01 has no hard delete, anywhere. Deactivate preserves the record; a
     * DELETE route would make the retention guarantees in the Product Owner
     * script untrue.
     */
    public function test_no_lifecycle_action_is_a_hard_delete(): void
    {
        $offenders = collect(Route::getRoutes())
            ->filter(fn (RoutingRoute $route): bool => str_starts_with($route->uri(), 'console/organisation'))
            ->filter(fn (RoutingRoute $route): bool => in_array('DELETE', $route->methods(), true))
            ->map(fn (RoutingRoute $route): string => $route->uri())
            ->values()
            ->all();

        $this->assertSame([], $offenders, 'Organisation exposes a hard delete. P1-01 has none, by design.');
    }

    /**
     * @return list<string>
     */
    private function routeSignatures(): array
    {
        $signatures = [];

        foreach (Route::getRoutes() as $route) {
            // Parameter names differ per entity; the shape is what matters.
            $uri = preg_replace('/\{[^}]+\}/', '{}', $route->uri());

            foreach ($route->methods() as $method) {
                $signatures[] = $method.' '.$uri;
            }
        }

        return $signatures;
    }
}
