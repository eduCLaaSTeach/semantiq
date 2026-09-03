<?php

declare(strict_types=1);

namespace Tests\Feature\Domains;

use App\Modules\Domains\Models\BusinessDomain;
use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;
use App\Modules\Platform\Models\PlatformRole;
use App\Modules\Platform\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Support\DomainFactory;
use Tests\Support\OrganisationFactory;
use Tests\TestCase;

/**
 * Who may reach Business Domains at all, and what owning one is worth.
 *
 * The answer to the second question is NOTHING, and it is asserted
 * BEHAVIOURALLY here rather than only as a source rule: a source scan proves a
 * string is absent, and a query written a different way would slip past it.
 */
final class DomainAccessBoundaryTest extends TestCase
{
    use RefreshDatabase;

    private OrganisationFactory $make;

    private DomainFactory $domains;

    protected function setUp(): void
    {
        parent::setUp();

        $this->make = new OrganisationFactory;
        $this->domains = new DomainFactory;
    }

    /**
     * N1, first half. EVERY Domains route refuses an anonymous caller.
     *
     * Enumerated from the route table rather than listed by hand, because a
     * route added later without a gate is exactly the defect - and a
     * hand-written list would not mention it.
     *
     * Mutation: drop RequireSystemAdministrator from the domains group.
     */
    public function test_every_domain_route_refuses_an_anonymous_caller(): void
    {
        $checked = 0;

        foreach ($this->domainRoutes() as [$method, $uri]) {
            $response = $this->call($method, '/'.$uri);

            $this->assertTrue(
                $response->isRedirect() || $response->status() === 401,
                "[{$method} /{$uri}] served an anonymous caller with status {$response->status()}."
            );

            $checked++;
        }

        $this->assertGreaterThan(
            7,
            $checked,
            'Almost nothing was checked, so this guard would pass against an empty route table.'
        );
    }

    /**
     * N1, second half. Every route refuses an authenticated ORDINARY user.
     *
     * Mutation: drop the gate from one route.
     */
    public function test_every_domain_route_refuses_an_ordinary_user(): void
    {
        $organisation = $this->make->organisation();
        $member = $this->make->user($organisation);

        $this->domains->domain($organisation);

        $checked = 0;

        foreach ($this->domainRoutes() as [$method, $uri]) {
            $response = $this->actingAsUser($member)->call($method, '/'.$uri);

            $this->assertTrue(
                $response->isRedirect(),
                "[{$method} /{$uri}] served an ordinary user with status {$response->status()}."
            );

            $this->assertSame(
                route('auth.access-denied'),
                $response->headers->get('Location'),
                "[{$method} /{$uri}] did not refuse an ordinary user."
            );

            $checked++;
        }

        $this->assertGreaterThan(7, $checked);
    }

    /**
     * N6. AN OWNER GETS THE IDENTICAL ANSWER TO A NON-OWNER, ON EVERY ROUTE.
     *
     * This is the behavioural core of the whole unit. Being accountable for a
     * domain is not a role, so a user who owns three domains and an otherwise
     * identical user who owns none must be INDISTINGUISHABLE to every route in
     * the application.
     *
     * IT IS RUN FOR BOTH KINDS OF USER, and the second pair is the one that
     * does the work. Two ORDINARY users are refused by the middleware before
     * any controller runs, so comparing only those two would pass against a
     * controller that consulted ownership on every line - the mutation proved
     * exactly that, and this test was rewritten because of it.
     *
     * Mutation: have any route consult ownership. Caught by the administrator
     * pair, which actually reaches the controllers.
     */
    public function test_a_domain_owner_gets_the_same_answer_as_anybody_else(): void
    {
        foreach ([false, true] as $administrator) {
            $organisation = $this->make->organisation($administrator ? 'Admins' : 'Ordinary');

            $owner = $this->make->user($organisation, administrator: $administrator);
            $stranger = $this->make->user($organisation, administrator: $administrator);

            foreach ([['Finance', 'finance'], ['Sales', 'sales'], ['People', 'people']] as [$name, $code]) {
                $domain = $this->domains->domain($organisation, $name, $code);
                $this->domains->ownership($domain, $owner);
            }

            $answers = ['owner' => [], 'stranger' => []];

            foreach ($this->everyConsoleRoute() as [$method, $uri]) {
                foreach (['owner' => $owner, 'stranger' => $stranger] as $label => $user) {
                    $response = $this->actingAsUser($user)->call($method, '/'.$uri);

                    $answers[$label][$method.' '.$uri] = $response->status().' '
                        .(string) $response->headers->get('Location');
                }
            }

            $this->assertNotEmpty($answers['owner']);

            $kind = $administrator ? 'System Administrator' : 'ordinary user';

            $this->assertSame(
                $answers['stranger'],
                $answers['owner'],
                "A {$kind} who owns three domains got a different answer from at least one route "
                ."than an identical {$kind} who owns none. Owning a domain grants nothing, so the "
                .'two must be indistinguishable.'
            );

            // And the administrator pair must genuinely have REACHED the
            // controllers, or the comparison above is between two refusals.
            if ($administrator) {
                $this->assertContains(
                    '200 ',
                    $answers['owner'],
                    'The administrator pair was refused everywhere, so nothing compared here ever '
                    .'reached a controller.'
                );
            }
        }
    }

    /**
     * N2, behavioural half. Assigning an owner writes NOTHING to that user.
     *
     * The source guard proves the string `platform_role` is not assigned in the
     * module; this proves the behaviour, which a source scan could never
     * establish for a column written through a variable.
     *
     * Mutation: have DomainOwnershipService::set() write platform_role, a
     * group membership, or any users column.
     */
    public function test_assigning_an_owner_changes_nothing_about_that_user(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);
        $owner = $this->make->user($organisation);

        $domain = $this->domains->domain($organisation);

        $before = $owner->fresh()->getAttributes();

        $this->actingAsUser($admin)
            ->patch("/console/domains/{$domain->id}/owner", ['user_id' => $owner->id])
            ->assertRedirect();

        $after = $owner->fresh()->getAttributes();

        unset($before['updated_at'], $after['updated_at']);

        $this->assertSame($before, $after, 'Assigning an owner altered the users row.');
        $this->assertNull($owner->fresh()->platform_role, 'Assigning an owner granted a platform role.');
        $this->assertFalse($owner->fresh()->isSystemAdministrator());

        // And it made no difference to what they can reach.
        foreach (['/console/domains', '/console/people/users', '/console/organisation'] as $uri) {
            $this->assertSame(
                route('auth.access-denied'),
                $this->actingAsUser($owner)->get($uri)->headers->get('Location'),
                "Owning a domain opened [{$uri}]."
            );
        }
    }

    /**
     * N4. No P1-04 path writes platform_role, however the request is crafted.
     *
     * Mutation: add platform_role to the accepted fields of store or update.
     */
    public function test_a_crafted_request_cannot_grant_a_platform_role(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);
        $target = $this->make->user($organisation);

        $domain = $this->domains->domain($organisation);

        $this->actingAsUser($admin)->post('/console/domains', [
            'name' => 'Crafted',
            'code' => 'crafted',
            'platform_role' => PlatformRole::SystemAdministrator->value,
            'user_id' => $target->id,
        ]);

        $this->actingAsUser($admin)->put("/console/domains/{$domain->id}", [
            'name' => 'Renamed',
            'access_expectation' => 'undecided',
            'platform_role' => PlatformRole::SystemAdministrator->value,
        ]);

        $this->assertNull($target->fresh()->platform_role);
        $this->assertSame(1, User::query()->where('platform_role', PlatformRole::SystemAdministrator->value)->count());
    }

    /**
     * N5. PlatformRole still has exactly one case.
     *
     * P1-05 owns the role model. A second case appearing here would mean it had
     * arrived early, through whichever unit added it.
     *
     * Mutation: add a second case.
     */
    public function test_the_platform_role_enum_still_has_one_case(): void
    {
        $this->assertCount(1, PlatformRole::cases());
        $this->assertSame(PlatformRole::SystemAdministrator, PlatformRole::cases()[0]);
    }

    /**
     * N17. A domain of another organisation is NOT FOUND, never forbidden.
     *
     * 403 would confirm the record exists, which is the whole disclosure. 404
     * says nothing either way, and says the same thing for an id that does not
     * exist at all.
     *
     * Mutation: return 403, or drop the check.
     */
    public function test_a_domain_of_another_organisation_is_not_found(): void
    {
        $ours = $this->make->organisation('Ours');
        $theirs = $this->make->organisation('Theirs');

        $admin = $this->make->user($ours, administrator: true);
        $foreign = $this->domains->domain($theirs, 'Theirs', 'theirs');

        $missingId = BusinessDomain::query()->max('id') + 1000;

        foreach ([
            ['get', "/console/domains/{$foreign->id}"],
            ['put', "/console/domains/{$foreign->id}"],
            ['patch', "/console/domains/{$foreign->id}/enable"],
            ['patch', "/console/domains/{$foreign->id}/disable"],
            ['patch', "/console/domains/{$foreign->id}/owner"],
            ['patch', "/console/domains/{$foreign->id}/owner/clear"],
            ['delete', "/console/domains/{$foreign->id}"],
        ] as [$method, $uri]) {
            $this->actingAsUser($admin)->call($method, $uri)->assertNotFound();
        }

        // And the answer for a foreign record is the SAME as for one that does
        // not exist, which is what makes it disclose nothing.
        $this->actingAsUser($admin)->get("/console/domains/{$missingId}")->assertNotFound();
    }

    /** @return list<array{0: string, 1: string}> */
    private function domainRoutes(): array
    {
        $routes = [];

        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'console/domains')) {
                continue;
            }

            foreach ($route->methods() as $method) {
                if (in_array($method, ['HEAD', 'OPTIONS'], true)) {
                    continue;
                }

                $routes[] = [$method, str_replace('{domain}', '1', $route->uri())];
            }
        }

        return $routes;
    }

    /**
     * Every console route, so the owner/non-owner comparison is not limited to
     * the screens this unit happens to have built.
     *
     * @return list<array{0: string, 1: string}>
     */
    private function everyConsoleRoute(): array
    {
        $routes = [];

        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'console')) {
                continue;
            }

            foreach ($route->methods() as $method) {
                if ($method !== 'GET') {
                    continue;
                }

                $routes[] = [$method, preg_replace('/\{[^}]+\}/', '1', $route->uri())];
            }
        }

        return $routes;
    }

    private function actingAsUser(User $user): self
    {
        return $this->withSession([
            EnsureSessionIsCurrent::SESSION_USER_ID => $user->id,
            EnsureSessionIsCurrent::SESSION_AUTHENTICATED_AT => now()->toIso8601String(),
        ]);
    }
}
