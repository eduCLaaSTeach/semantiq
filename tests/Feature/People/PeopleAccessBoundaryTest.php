<?php

declare(strict_types=1);

namespace Tests\Feature\People;

use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;
use App\Modules\Platform\Models\PlatformRole;
use App\Modules\Platform\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Support\OrganisationFactory;
use Tests\Support\PeopleFactory;
use Tests\TestCase;

/**
 * Who may reach People at all, and what being in a group is worth.
 *
 * The answer to the second question is NOTHING, and it is asserted behaviourally
 * here rather than only as a source rule: a source scan proves a string is
 * absent, and a query written a different way would slip past it.
 */
final class PeopleAccessBoundaryTest extends TestCase
{
    use RefreshDatabase;

    private OrganisationFactory $make;

    private PeopleFactory $people;

    protected function setUp(): void
    {
        parent::setUp();

        $this->make = new OrganisationFactory;
        $this->people = new PeopleFactory;
    }

    /**
     * Negative case 1, first half. EVERY People route refuses an anonymous
     * caller.
     *
     * Enumerated from the route table rather than listed by hand, because a
     * route added later without a gate is exactly the defect - and a hand-written
     * list would not mention it.
     *
     * Mutation: drop RequireSystemAdministrator from the People group.
     */
    public function test_every_people_route_refuses_an_anonymous_caller(): void
    {
        $checked = 0;

        foreach ($this->peopleRoutes() as [$method, $uri]) {
            $response = $this->call($method, '/'.$uri);

            $this->assertTrue(
                $response->isRedirect() || $response->status() === 401,
                "[{$method} /{$uri}] served an anonymous caller with status {$response->status()}."
            );

            if ($response->isRedirect()) {
                $this->assertStringNotContainsString(
                    '/console/people',
                    (string) $response->headers->get('Location'),
                    "[{$method} /{$uri}] redirected an anonymous caller further into People."
                );
            }

            $checked++;
        }

        $this->assertGreaterThan(
            10,
            $checked,
            'Almost nothing was checked, so this guard would pass against an empty route table.'
        );
    }

    /**
     * Negative case 1, second half. Every People route refuses an authenticated
     * NON-ADMINISTRATOR.
     *
     * A separate control from the one above, and the more likely one to be got
     * wrong: the caller has a valid session, so only the role check stands
     * between them and the whole directory.
     *
     * Mutation: as above.
     */
    public function test_every_people_route_refuses_an_authenticated_non_administrator(): void
    {
        $organisation = $this->make->organisation();
        $member = $this->make->user($organisation);

        $checked = 0;

        foreach ($this->peopleRoutes() as [$method, $uri]) {
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

        $this->assertGreaterThan(10, $checked);
    }

    /**
     * Negative case 2. A NEWLY CREATED USER HAS NOTHING.
     *
     * Not "is not an administrator" - has nothing. The person is created through
     * the real provisioning route by a real administrator, and then every
     * administrative surface P1-01 and P1-02 deliver is tried as them.
     *
     * This is the case that would catch a provisioning path which set a role
     * "so the person can log in and see something".
     *
     * Mutation: give provision() a platform_role.
     */
    public function test_a_newly_provisioned_user_can_reach_nothing(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);

        $this->actingAsUser($admin)->post('/console/people/users', [
            'object_id' => '3f2504e0-4f89-11d3-9a0c-0305e82c3301',
            'email' => 'newcomer@example.test',
            'display_name' => 'Newcomer',
        ])->assertRedirect();

        $newcomer = User::query()->where('email', 'newcomer@example.test')->sole();

        $this->assertNull($newcomer->platform_role, 'Provisioning granted a platform role.');
        $this->assertFalse($newcomer->isSystemAdministrator());

        foreach ([
            '/console/people/users',
            '/console/people/groups',
            '/console/organisation',
            '/console/organisation/legal-entities',
            '/console/organisation/business-units',
            '/console/organisation/departments',
            '/console/organisation/teams',
            '/console/organisation/hierarchy',
            '/console/identity',
            '/console/identity/providers',
            '/console/identity/health',
            '/console/identity/session-policy',
        ] as $uri) {
            $this->actingAsUser($newcomer)
                ->get($uri)
                ->assertRedirect(route('auth.access-denied'));
        }

        // And the console itself serves them nothing to navigate to.
        $response = $this->actingAsUser($newcomer)->get('/console');

        $this->assertSame(
            [],
            $response->viewData('page')['props']['productAreas'] ?? [],
            'A newly provisioned user was offered navigation.'
        );
    }

    /**
     * Negative case 3, behavioural half. BEING IN A GROUP CHANGES NOTHING.
     *
     * Two ordinary users, identical in every respect except that one is in a
     * group. Every protected route is tried as each, and the answers must be
     * the same everywhere.
     *
     * Asserted as an EQUALITY BETWEEN THE TWO, not as "the member is refused":
     * a test asserting refusal would keep passing if group membership started
     * granting something that happened not to be a route - and, more likely,
     * would be quietly satisfied by any refusal for any reason.
     *
     * Mutation: have SystemAdministratorNavigationAuthorizer, or any middleware,
     * consult group membership.
     */
    public function test_group_membership_changes_no_authorisation_answer(): void
    {
        $organisation = $this->make->organisation();

        $member = $this->make->user($organisation);
        $stranger = $this->make->user($organisation);

        $group = $this->people->group($organisation, 'Finance');
        $this->people->membership($group, $member);

        $answers = ['member' => [], 'stranger' => []];

        foreach ($this->protectedRoutes() as [$method, $uri]) {
            foreach (['member' => $member, 'stranger' => $stranger] as $label => $user) {
                $response = $this->actingAsUser($user)->call($method, '/'.$uri);

                $answers[$label][$method.' '.$uri] = $response->status().' '
                    .(string) $response->headers->get('Location');
            }
        }

        $this->assertNotEmpty($answers['member']);

        $this->assertSame(
            $answers['stranger'],
            $answers['member'],
            'A user in a group got a different answer from at least one route than an identical '
            .'user who is not. In P1-03 a group grants nothing, so the two must be indistinguishable.'
        );
    }

    /**
     * Negative case 4, behavioural half.
     *
     * A crafted request carrying platform_role changes nothing. The source guard
     * in PeopleBoundaryTest proves the string is not assigned; this proves the
     * behaviour, which a source scan could never establish for a column written
     * through a variable.
     *
     * Mutation: add 'platform_role' to the update request's validated fields and
     * pass it to the service.
     */
    public function test_a_request_carrying_a_platform_role_grants_nothing(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);
        $person = $this->make->user($organisation);

        $this->actingAsUser($admin)->post('/console/people/users', [
            'object_id' => '9b2f4c1e-1111-2222-3333-444455556666',
            'email' => 'aspiring@example.test',
            'platform_role' => PlatformRole::SystemAdministrator->value,
        ])->assertRedirect();

        $created = User::query()->where('email', 'aspiring@example.test')->sole();

        $this->assertNull($created->platform_role, 'Provisioning accepted a role from the request.');

        $this->actingAsUser($admin)->put("/console/people/users/{$person->id}", [
            'organisation_id' => $organisation->id,
            'platform_role' => PlatformRole::SystemAdministrator->value,
        ]);

        $this->assertNull($person->fresh()->platform_role, 'An update accepted a role from the request.');

        $this->actingAsUser($admin)
            ->patch("/console/people/users/{$person->id}/reactivate", [
                'platform_role' => PlatformRole::SystemAdministrator->value,
            ]);

        $this->assertNull($person->fresh()->platform_role, 'Reactivation accepted a role from the request.');
    }

    /**
     * A record belonging to another organisation is Not Found.
     *
     * Release 1 has one organisation, so this is unreachable through the
     * screens - which is exactly why it is asserted rather than assumed. A
     * numeric id in the address bar is the whole attack.
     *
     * 404 rather than 403 deliberately: a refusal that distinguishes "exists but
     * not yours" from "does not exist" confirms the record exists.
     *
     * Mutation: remove refuseIfOutsideOrganisation from the People controllers.
     */
    public function test_a_record_of_another_organisation_is_not_found(): void
    {
        $ours = $this->make->organisation('Ours');
        $theirs = $this->make->organisation('Theirs');

        $admin = $this->make->user($ours, administrator: true);

        $outsider = $this->make->user($theirs);
        $foreignGroup = $this->people->group($theirs, 'Their Finance');

        foreach ([
            ['GET', "console/people/users/{$outsider->id}"],
            ['PUT', "console/people/users/{$outsider->id}"],
            ['PATCH', "console/people/users/{$outsider->id}/deactivate"],
            ['PATCH', "console/people/users/{$outsider->id}/reactivate"],
            ['POST', "console/people/users/{$outsider->id}/reveal"],
            ['DELETE', "console/people/users/{$outsider->id}"],
            ['GET', "console/people/groups/{$foreignGroup->id}"],
            ['PUT', "console/people/groups/{$foreignGroup->id}"],
            ['PATCH', "console/people/groups/{$foreignGroup->id}/deactivate"],
            ['PATCH', "console/people/groups/{$foreignGroup->id}/reactivate"],
            ['POST', "console/people/groups/{$foreignGroup->id}/members"],
            ['DELETE', "console/people/groups/{$foreignGroup->id}"],
        ] as [$method, $uri]) {
            $this->actingAsUser($admin)
                ->call($method, '/'.$uri)
                ->assertNotFound();
        }

        // Nothing was changed by any of it.
        $this->assertTrue($outsider->fresh()->isActive());
        $this->assertSame($theirs->id, $foreignGroup->fresh()->organisation_id);
    }

    /**
     * A membership id from another group cannot be ended through this group.
     *
     * Both ids are bound independently, so nothing says they belong together
     * unless something checks. Without the check the administrator sees
     * "Membership ended" on a group where nothing changed, and somebody else
     * silently leaves a different group.
     *
     * Mutation: remove the group_id comparison in removeMember.
     */
    public function test_a_membership_cannot_be_ended_through_a_different_group(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);
        $person = $this->make->user($organisation);

        $finance = $this->people->group($organisation, 'Finance');
        $safety = $this->people->group($organisation, 'Safety');

        $membership = $this->people->membership($finance, $person);

        $this->actingAsUser($admin)
            ->patch("/console/people/groups/{$safety->id}/members/{$membership->id}/remove")
            ->assertNotFound();

        $this->assertNull(
            $membership->fresh()->left_at,
            'A membership was ended through a group it does not belong to.'
        );
    }

    /**
     * Every route that requires a session and a role, so the group-membership
     * comparison above is made against the whole application rather than
     * against People alone.
     *
     * @return list<array{0: string, 1: string}>
     */
    private function protectedRoutes(): array
    {
        $routes = [];

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();

            if (! str_starts_with($uri, 'console')) {
                continue;
            }

            if (str_contains($uri, '{')) {
                continue;
            }

            $routes[] = [$route->methods()[0], $uri];
        }

        return $routes;
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function peopleRoutes(): array
    {
        $routes = [];

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();

            if (! str_starts_with($uri, 'console/people')) {
                continue;
            }

            // A concrete id, so the request is a real one rather than a pattern.
            $routes[] = [$route->methods()[0], preg_replace('/\{[^}]+\}/', '7', $uri)];
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
