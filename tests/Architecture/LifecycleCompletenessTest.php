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
            'purge' => ['DELETE', '/{}'],
            'deactivate' => ['PATCH', '/{}/deactivate'],
            'reactivate' => ['PATCH', '/{}/reactivate'],
        ],
        'console/organisation/business-units' => [
            'read' => ['GET', ''],
            'create' => ['POST', ''],
            'update' => ['PUT', '/{}'],
            'purge' => ['DELETE', '/{}'],
            'deactivate' => ['PATCH', '/{}/deactivate'],
            'reactivate' => ['PATCH', '/{}/reactivate'],
        ],
        'console/organisation/departments' => [
            'read' => ['GET', ''],
            'create' => ['POST', ''],
            'update' => ['PUT', '/{}'],
            'purge' => ['DELETE', '/{}'],
            'move' => ['PATCH', '/{}/move'],
            'deactivate' => ['PATCH', '/{}/deactivate'],
            'reactivate' => ['PATCH', '/{}/reactivate'],
        ],
        'console/organisation/teams' => [
            'read' => ['GET', ''],
            'create' => ['POST', ''],
            'update' => ['PUT', '/{}'],
            'purge' => ['DELETE', '/{}'],
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
     * D-24: permanent deletion exists, on FOUR master types and nowhere else.
     *
     * This case used to read "no DELETE anywhere in the unit". D-24 superseded
     * that part of the earlier lifecycle decision, and the case is amended
     * rather than deleted: the thing worth guarding was never "zero", it was
     * "no delete reaches the records whose history the unit is built to keep".
     * That set is still exact and still asserted, so a DELETE on the
     * organisation, on team memberships or on management relationships fails
     * the build exactly as it did before.
     *
     * Asserted as an equality, not a subset. A subset check would pass while a
     * fifth DELETE quietly appeared.
     *
     * Mutation: register a DELETE for team memberships. CAUGHT.
     */
    public function test_permanent_deletion_reaches_only_the_four_master_types(): void
    {
        $permitted = [
            'console/organisation/business-units/{businessUnit}',
            'console/organisation/departments/{department}',
            'console/organisation/legal-entities/{legalEntity}',
            'console/organisation/teams/{team}',
        ];

        $registered = collect(Route::getRoutes())
            ->filter(fn (RoutingRoute $route): bool => str_starts_with($route->uri(), 'console/organisation'))
            ->filter(fn (RoutingRoute $route): bool => in_array('DELETE', $route->methods(), true))
            ->map(fn (RoutingRoute $route): string => $route->uri())
            ->sort()
            ->values()
            ->all();

        $this->assertSame(
            $permitted,
            $registered,
            'The set of DELETE routes in Organisation is not the D-24 set. Permanent deletion is '
            .'permitted for a legal entity, business unit, department and team, and for nothing '
            .'else - never for the organisation, a team membership or a management relationship.'
        );
    }

    /**
     * The records whose retention the unit is built around, stated as records
     * rather than as routes.
     *
     * The route check above would also catch these, but only while they have no
     * route of any verb to hang a DELETE on. This says the thing itself.
     */
    public function test_history_and_the_organisation_have_no_delete_route(): void
    {
        foreach (Route::getRoutes() as $route) {
            if (! in_array('DELETE', $route->methods(), true)) {
                continue;
            }

            foreach (['members', 'membership', 'hierarchy'] as $protected) {
                $this->assertStringNotContainsString(
                    $protected,
                    $route->uri(),
                    "Route [{$route->uri()}] would destroy retained history. Membership ends with "
                    .'left_at and a management link ends with effective_to; both keep their row.'
                );
            }

            $this->assertNotSame(
                'console/organisation',
                $route->uri(),
                'The organisation itself has a DELETE route. D-24 excludes the Company Profile.'
            );
        }
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
