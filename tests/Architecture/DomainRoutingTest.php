<?php

declare(strict_types=1);

namespace Tests\Architecture;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The Business Domains route set is structurally sound, not accidentally sound.
 *
 * P1-03 correction 1 is the reason this file exists. Its first design put user
 * records at /console/people/{user} beside /console/people/groups and claimed
 * the collision was gone because the prefix had been renamed. It had not: a
 * dynamic segment and a static one at the same depth clash whatever their
 * parent is called, and the proof was that correctness needed BOTH declaration
 * order and a whereNumber to hold.
 *
 * P1-04 has one dynamic segment and nothing static beside it, so it needs
 * neither - and the reversal test below is what demonstrates that rather than
 * asserting it.
 */
final class DomainRoutingTest extends TestCase
{
    /**
     * N16. THE ROUTE SET RESOLVES IDENTICALLY WHEN THE FILE IS READ IN REVERSE.
     *
     * If declaration order matters, reversing it changes an answer. This is the
     * test that tells the difference between "correct" and "correct today".
     *
     * Mutation: add a static segment beside {domain} - say
     * /console/domains/archived - and watch a domain called "archived" become
     * unreachable in one order and reachable in the other.
     */
    public function test_the_route_set_resolves_the_same_read_in_reverse(): void
    {
        $forward = $this->resolutions($this->domainRoutes());
        $reversed = $this->resolutions(array_reverse($this->domainRoutes()));

        $this->assertNotEmpty($forward, 'No domain routes were found, so this proves nothing.');

        $this->assertSame(
            $forward,
            $reversed,
            'The route set depends on declaration order. A dynamic segment is sitting at the depth '
            .'of a static one.'
        );
    }

    /**
     * Every dynamic segment sits one level below a static one, and there is no
     * static segment at {domain}'s depth to collide with.
     *
     * Mutation: register /console/domains/archived.
     */
    public function test_no_static_segment_sits_at_the_depth_of_the_dynamic_one(): void
    {
        $dynamicDepths = [];
        $staticSegments = [];

        foreach ($this->domainRoutes() as $uri) {
            $segments = explode('/', $uri);

            foreach ($segments as $depth => $segment) {
                if (str_starts_with($segment, '{')) {
                    $dynamicDepths[] = $depth;
                } else {
                    $staticSegments[$depth][] = $segment;
                }
            }
        }

        $this->assertNotEmpty($dynamicDepths);

        foreach (array_unique($dynamicDepths) as $depth) {
            $atThatDepth = array_unique($staticSegments[$depth] ?? []);

            $this->assertSame(
                [],
                $atThatDepth,
                'A static segment ['.implode(', ', $atThatDepth).'] sits at the same depth as a '
                .'dynamic one. A domain with that name becomes unreachable, and which one wins '
                .'depends on declaration order.'
            );
        }
    }

    /**
     * The complete method set under console/domains.
     *
     * Asserted as an EQUALITY. A subset check would pass while an eighth route
     * appeared, and the one DELETE here is the guarded purge - clearing an
     * owner is a PATCH precisely so this set stays honest.
     *
     * Mutation: add any route; spell owner/clear as a DELETE.
     */
    public function test_the_domain_routes_are_exactly_the_declared_set(): void
    {
        $registered = [];

        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'console/domains')) {
                continue;
            }

            foreach ($route->methods() as $method) {
                if (in_array($method, ['HEAD', 'OPTIONS'], true)) {
                    continue;
                }

                $registered[] = $method.' '.$route->uri();
            }
        }

        sort($registered);

        $this->assertSame([
            'DELETE console/domains/{domain}',
            'GET console/domains',
            'GET console/domains/{domain}',
            'PATCH console/domains/{domain}/disable',
            'PATCH console/domains/{domain}/enable',
            'PATCH console/domains/{domain}/owner',
            'PATCH console/domains/{domain}/owner/clear',
            'POST console/domains',
            'PUT console/domains/{domain}',
        ], $registered, 'The Business Domains route set is not the declared one.');
    }

    /** @return list<string> */
    private function domainRoutes(): array
    {
        $uris = [];

        foreach (Route::getRoutes() as $route) {
            if (str_starts_with($route->uri(), 'console/domains')) {
                $uris[] = $route->uri();
            }
        }

        return array_values(array_unique($uris));
    }

    /**
     * What each candidate path resolves to, given a route order.
     *
     * @param  list<string>  $order
     * @return array<string, string>
     */
    private function resolutions(array $order): array
    {
        $candidates = [
            'console/domains',
            'console/domains/1',
            'console/domains/1/enable',
            'console/domains/1/disable',
            'console/domains/1/owner',
            'console/domains/1/owner/clear',
            // The names a collision would swallow.
            'console/domains/owner',
            'console/domains/enable',
            'console/domains/archived',
        ];

        $answers = [];

        foreach ($candidates as $candidate) {
            $answers[$candidate] = 'no match';

            foreach ($order as $uri) {
                $pattern = '#^'.preg_replace('/\{[^}]+\}/', '[^/]+', preg_quote($uri, '#')).'$#';
                $pattern = str_replace('\{', '{', $pattern);

                if (preg_match($pattern, $candidate) === 1) {
                    $answers[$candidate] = $uri;
                    break;
                }
            }
        }

        return $answers;
    }
}
