<?php

declare(strict_types=1);

namespace Tests\Architecture;

use Illuminate\Http\Request;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

/**
 * Negative case 15 - correction 1, and the reason it was a correction.
 *
 * The first design put user records at /console/people/{user} beside
 * /console/people/groups, and claimed the collision was gone because the prefix
 * had been renamed from `users` to `people`. It had not. A dynamic segment and a
 * static one at the same depth clash whatever their parent is called, and the
 * proof was in the design's own test list: correctness needed BOTH declaration
 * order AND a whereNumber constraint.
 *
 * A test that only asserted "/console/people/groups resolves to the group list"
 * would have passed against that arrangement, because Laravel matches in
 * declaration order and `groups` was declared first. So it is not what this
 * asserts.
 *
 * This asserts STRUCTURAL DISJOINTNESS - a property of the route set that holds
 * regardless of the order it is read in - and then proves the claim by
 * re-matching the whole set against a REVERSED route collection.
 */
final class PeopleRoutingTest extends TestCase
{
    /**
     * Every People URI lives under one of the two static collection roots.
     *
     * Mutation: move a user route back to console/people/{user}.
     */
    public function test_the_user_and_group_route_sets_are_structurally_disjoint(): void
    {
        $people = $this->peopleUris();

        $this->assertNotEmpty($people, 'No People routes were found, so this test proves nothing.');

        foreach ($people as $uri) {
            // The redirect at the collection root is the one exception, and it
            // carries no parameter, so it cannot collide with anything.
            if ($uri === 'console/people') {
                continue;
            }

            $this->assertTrue(
                str_starts_with($uri, 'console/people/users') || str_starts_with($uri, 'console/people/groups'),
                "Route [{$uri}] is neither under console/people/users nor console/people/groups. "
                .'Both sets must be reachable by their own static prefix, or a record id and a '
                .'collection name compete for the same segment.'
            );
        }
    }

    /**
     * The property that makes order irrelevant: at no depth does a dynamic
     * segment sit where another route has a static one.
     *
     * This is the assertion the earlier arrangement could not have passed, and
     * the one that makes the whereNumber constraints defence in depth rather
     * than the mechanism.
     *
     * Mutation: register Route::get('people/{user}') alongside people/groups.
     */
    public function test_no_route_has_a_dynamic_segment_where_another_has_a_static_one(): void
    {
        $statics = [];
        $dynamics = [];

        foreach ($this->peopleUris() as $uri) {
            foreach (explode('/', $uri) as $depth => $segment) {
                if (str_starts_with($segment, '{')) {
                    $dynamics[$depth][] = $uri;
                } else {
                    $statics[$depth][$segment] = $uri;
                }
            }
        }

        foreach ($dynamics as $depth => $uris) {
            $collisions = array_keys($statics[$depth] ?? []);

            $this->assertSame(
                [],
                $collisions,
                "At depth {$depth} a dynamic segment (in ".implode(', ', $uris).') sits where '
                .'another route has a static one ('.implode(', ', $collisions).'). Whichever is '
                .'declared first wins, which makes correctness a property of file order.'
            );
        }

        $this->assertNotEmpty($dynamics, 'No parameterised People routes were found.');
    }

    /**
     * The claim, proved rather than argued: reverse the collection and every
     * route still resolves to the same action.
     *
     * If order mattered anywhere, this is where it shows.
     *
     * Mutation: as above - with a dynamic route at the collection depth, the
     * reversed collection matches `groups` as a user id and this fails.
     */
    public function test_every_people_route_still_resolves_when_the_route_set_is_reversed(): void
    {
        $forwards = Route::getRoutes();

        $expected = [];

        foreach ($forwards as $route) {
            if (! str_starts_with($route->uri(), 'console/people')) {
                continue;
            }

            $expected[$route->methods()[0].' '.$route->uri()] = $this->actionOf($route);
        }

        $this->assertNotEmpty($expected);

        $reversed = new RouteCollection;

        foreach (array_reverse(iterator_to_array($forwards->getIterator())) as $route) {
            $reversed->add($route);
        }

        foreach ($expected as $signature => $action) {
            [$method, $uri] = explode(' ', $signature, 2);

            // A concrete address for the shape, so matching is a real match and
            // not a comparison of patterns.
            $concrete = preg_replace('/\{[^}]+\}/', '7', $uri);

            $request = Request::create('/'.$concrete, $method);

            $matched = $reversed->match($request);

            $this->assertSame(
                $action,
                $this->actionOf($matched),
                "[{$signature}] resolves to a different action when the route file is read in "
                .'reverse. Correctness that depends on declaration order is one refactor from '
                .'being wrong.'
            );
        }
    }

    /**
     * A collection name where a record id would go is NOT FOUND, not a lookup.
     *
     * Mutation: remove the whereNumber constraints. /console/people/users/groups
     * then reaches the record page and asks the database for a user called
     * "groups".
     */
    public function test_a_collection_name_in_a_record_position_is_not_found(): void
    {
        foreach ([
            'console/people/users/groups',
            'console/people/users/users',
            'console/people/groups/users',
            'console/people/groups/groups',
        ] as $uri) {
            $request = Request::create('/'.$uri, 'GET');

            try {
                $matched = Route::getRoutes()->match($request);

                $this->fail(
                    "[{$uri}] matched [{$matched->uri()}]. A collection name in a record position "
                    .'must be Not Found, never a lookup for a record with that name.'
                );
            } catch (NotFoundHttpException) {
                $this->assertTrue(true);
            }
        }
    }

    /**
     * @return list<string>
     */
    private function peopleUris(): array
    {
        $uris = [];

        foreach (Route::getRoutes() as $route) {
            if (str_starts_with($route->uri(), 'console/people')) {
                $uris[$route->uri()] = true;
            }
        }

        return array_keys($uris);
    }

    private function actionOf(RoutingRoute $route): string
    {
        $action = $route->getAction();

        if (isset($action['controller'])) {
            return (string) $action['controller'];
        }

        return $route->uri().' (closure)';
    }
}
