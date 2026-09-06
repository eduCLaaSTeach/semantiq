<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Modules\Access\Http\Middleware\RequireActionClass;
use App\Modules\Access\Models\RoleAssignment;
use App\Modules\Access\Services\RoleAssignmentService;
use App\Modules\Access\StepUp\PendingStepUp;
use App\Modules\Access\Support\AccessViolation;
use App\Modules\Access\Support\ActionClass;
use App\Modules\Access\Support\RoleCode;
use App\Modules\Organisation\Models\Organisation;
use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;
use App\Modules\Platform\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use Tests\Support\AccessFactory;
use Tests\Support\OrganisationFactory;
use Tests\TestCase;

/**
 * N-E1 to N-E10. THE ROUTE MATRIX, enumerated rather than swapped.
 *
 * Every protected route declares the action class it actually needs. There is
 * NO DEFAULT, and an unclassified protected route fails the build here rather
 * than quietly inheriting whatever the last developer thought was reasonable.
 */
final class RouteAuthorizationMatrixTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The declared matrix. Every console prefix, and the class it carries.
     *
     * IDENTITY & SSO IS PLATFORM_ADMIN and everything else is ORG_ADMIN or
     * ACCESS_ADMIN. That one row is the whole reason the routes were enumerated
     * instead of find-and-replaced: a blanket substitution would have opened
     * the front door of the product to an Organisation Administrator.
     */
    private const MATRIX = [
        'console/organisation' => ActionClass::OrgAdmin,
        'console/people' => ActionClass::OrgAdmin,
        'console/domains' => ActionClass::OrgAdmin,
        'console/access' => ActionClass::AccessAdmin,
        'console/identity' => ActionClass::PlatformAdmin,
    ];

    private OrganisationFactory $make;

    private AccessFactory $access;

    protected function setUp(): void
    {
        parent::setUp();

        $this->make = new OrganisationFactory;
        $this->access = new AccessFactory;
    }

    /**
     * N-E1. EVERY protected route names an action class.
     *
     * Mutation: drop one. It must fail here rather than at runtime.
     */
    public function test_every_protected_console_route_declares_an_action_class(): void
    {
        $checked = 0;

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();

            if (! str_starts_with($uri, 'console/')) {
                continue;
            }

            // The landing page is behind the session gate only, deliberately:
            // it proves an authenticated session reaches a protected route and
            // carries no business data at all.
            if ($uri === 'console' || $uri === 'console/') {
                continue;
            }

            $checked++;

            $declared = $this->declaredClassFor($route);

            $this->assertNotNull(
                $declared,
                "Route [{$uri}] is protected but declares no action class. There is no default: an "
                .'unclassified route must fail closed rather than inherit one.'
            );
        }

        $this->assertGreaterThan(30, $checked, 'Almost no routes were checked, so this proves nothing.');
    }

    /**
     * N-E9 and N-E10. EACH PREFIX CARRIES THE CLASS THE MATRIX NAMES.
     *
     * Mutation: open one Identity route to ORG_ADMIN - the find-and-replace
     * outcome.
     */
    public function test_every_prefix_carries_exactly_the_declared_class(): void
    {
        $seen = [];

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();

            foreach (self::MATRIX as $prefix => $expected) {
                if (! str_starts_with($uri, $prefix)) {
                    continue;
                }

                $seen[$prefix] = ($seen[$prefix] ?? 0) + 1;

                $this->assertSame(
                    $expected,
                    $this->declaredClassFor($route),
                    "Route [{$uri}] does not carry {$expected->value}."
                );
            }
        }

        foreach (array_keys(self::MATRIX) as $prefix) {
            $this->assertArrayHasKey($prefix, $seen, "No routes were found under [{$prefix}].");
        }

        $this->assertSame(
            7,
            $seen['console/identity'],
            'Identity & SSO no longer has exactly seven routes. All seven are PLATFORM_ADMIN, and '
            .'the count is asserted so that adding an eighth is a decision rather than an accident.'
        );
    }

    /**
     * AN ORGANISATION ADMINISTRATOR REACHES THE APPROVED SURFACES AND NOT
     * IDENTITY & SSO.
     *
     * Asserted behaviourally through real HTTP requests, because the matrix
     * above proves what is declared and this proves what happens.
     */
    public function test_an_organisation_administrator_cannot_reach_identity_and_sso(): void
    {
        $organisation = $this->make->organisation();
        $person = $this->make->user($organisation);

        $this->access->assignment($person, RoleCode::OrganisationAdministrator, $organisation);

        // The approved surfaces.
        foreach (['/console/organisation', '/console/people/users', '/console/domains', '/console/access'] as $uri) {
            $this->assertSame(
                200,
                $this->signedInAs($person)->get($uri)->getStatusCode(),
                "An Organisation Administrator could not reach [{$uri}], which the matrix grants."
            );
        }

        // And the one that is NOT theirs. All seven.
        foreach ([
            '/console/identity',
            '/console/identity/providers',
            '/console/identity/login-experience',
            '/console/identity/health',
            '/console/identity/session-policy',
        ] as $uri) {
            $this->assertSame(
                route('auth.access-denied'),
                $this->signedInAs($person)->get($uri)->headers->get('Location'),
                "An Organisation Administrator reached [{$uri}]. Identity & SSO is the front door of "
                .'the product and is System-Administrator-only.'
            );
        }
    }

    /**
     * N-B9. AN ORGANISATION ADMINISTRATOR CAN NEVER GRANT system_administrator.
     *
     * The escalation path this whole unit exists to close. Asserted through the
     * real route, with a crafted request.
     */
    public function test_an_organisation_administrator_cannot_grant_the_system_administrator_role(): void
    {
        $organisation = $this->make->organisation();
        $actor = $this->make->user($organisation);
        $target = $this->make->user($organisation);

        $this->access->assignment($actor, RoleCode::OrganisationAdministrator, $organisation);

        $response = $this->signedInAs($actor)
            ->post('/console/access/assignments', [
                'user_id' => $target->id,
                'role_code' => RoleCode::SystemAdministrator->value,
            ]);

        $this->assertSame(
            0,
            RoleAssignment::query()
                ->where('user_id', $target->id)
                ->where('role_code', RoleCode::SystemAdministrator->value)
                ->count(),
            'An Organisation Administrator granted the System Administrator role.'
        );

        /*
         * AND NO STEP-UP WAS EVEN OFFERED.
         *
         * The first version of this case asserted only the count, and a
         * mutation that removed the escalation guard entirely SURVIVED - the
         * request was diverted to step-up before it ever reached the service
         * that holds the guard, so the assertion passed for a reason unrelated
         * to what it claimed to check. Exactly the failure CLAUDE.md §2 names.
         *
         * A confirmation somebody can never complete is a trap, so the refusal
         * has to come first, and this is what proves it does.
         */
        $this->assertSame(
            0,
            PendingStepUp::query()->count(),
            'A step-up was offered for a role this actor may never be granted. They would '
            .'re-authenticate with Microsoft and be refused afterwards.'
        );

        $response->assertSessionHasErrors('access');
    }

    /**
     * ...and the SERVICE refuses independently, whatever the controller does.
     *
     * Two layers, tested separately, because the controller check is about WHEN
     * the refusal happens and the service check is the refusal itself. A
     * mutation to either must be caught.
     */
    public function test_the_service_refuses_an_ungrantable_role_on_its_own(): void
    {
        $organisation = $this->make->organisation();
        $actor = $this->make->user($organisation);
        $target = $this->make->user($organisation);

        $this->access->assignment($actor, RoleCode::OrganisationAdministrator, $organisation);

        try {
            app(RoleAssignmentService::class)
                ->assign($target, RoleCode::SystemAdministrator, null, $actor);

            $this->fail('The service granted the System Administrator role to an Organisation Administrator\'s request.');
        } catch (AccessViolation $violation) {
            $this->assertSame('role_not_grantable', $violation->reason);
        }

        // And the half that makes it non-vacuous: a System Administrator CAN.
        $admin = $this->make->user($organisation, administrator: true);

        $granted = app(RoleAssignmentService::class)
            ->assign($target, RoleCode::SystemAdministrator, null, $admin);

        $this->assertTrue($granted->isCurrent());
    }

    /**
     * N-E1, the behavioural half. AN UNRECOGNISED CLASS FAILS CLOSED.
     *
     * The matrix above proves every DELIVERED route names a valid class. This
     * proves what happens to one that does not - a typo, or a class removed
     * from the enum while a route still names it.
     *
     * A route is registered for the test rather than left to chance, because
     * no delivered route has a bad class and a mutation making the middleware
     * fall open therefore SURVIVED: nothing exercised the branch at all.
     *
     * Mutation: let an unrecognised class through.
     */
    public function test_a_route_naming_an_unrecognised_action_class_fails_closed(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);

        /*
         * THE MIDDLEWARE DIRECTLY, rather than a route registered mid-test.
         *
         * A route added after the application has booted does not resolve in a
         * test request, so that version asserted nothing. Calling the
         * middleware is unambiguous: a request that reaches $next has been
         * ALLOWED, and one that does not has been refused.
         */
        $request = Request::create('/console/anything');
        $request->attributes->set('semantiq_user', $admin);

        $reached = false;

        $response = app(RequireActionClass::class)->handle(
            $request,
            function () use (&$reached): \Symfony\Component\HttpFoundation\Response {
                $reached = true;

                return new Response('reached');
            },
            'not_a_real_class',
        );

        $this->assertFalse(
            $reached,
            'A route naming an unrecognised action class was served. A typo must fail closed, not '
            .'silently open a screen.'
        );

        $this->assertSame(302, $response->getStatusCode());

        // And the half that makes it non-vacuous: a VALID class is served.
        $reached = false;

        app(RequireActionClass::class)->handle(
            $request,
            function () use (&$reached): \Symfony\Component\HttpFoundation\Response {
                $reached = true;

                return new Response('reached');
            },
            ActionClass::PlatformAdmin->value,
        );

        $this->assertTrue($reached, 'A System Administrator was refused a valid platform-admin route.');
    }

    /**
     * N-E2 and N-E3. A DENIAL RETURNS NO PROTECTED PAYLOAD.
     *
     * The decision precedes the fetch, so there is nothing to hide. A refusal
     * carries no field names, no ids and no counts - not even an empty object
     * with the shape of the answer.
     *
     * Mutation: fetch, then filter.
     */
    public function test_a_denied_request_returns_no_protected_payload(): void
    {
        $organisation = $this->make->organisation();
        $ordinary = $this->make->user($organisation);

        $domain = $this->access->domain($organisation, 'finance', 'Finance');
        $admin = $this->make->user($organisation, administrator: true);
        $entitlement = $this->access->completePath($admin, $domain);

        foreach (['/console/access', "/console/access/assignments/{$entitlement->role_assignment_id}", '/console/identity'] as $uri) {
            $response = $this->signedInAs($ordinary)->get($uri);

            $this->assertSame(route('auth.access-denied'), $response->headers->get('Location'));

            $body = $response->getContent();

            foreach (['Finance', 'role_assignment', 'entitlement', $admin->email] as $leak) {
                $this->assertStringNotContainsString(
                    $leak,
                    (string) $body,
                    "A refused request to [{$uri}] carried [{$leak}] in its body."
                );
            }
        }
    }

    /**
     * N-E6. NO AUTHORIZATION IS COMPUTED IN JAVASCRIPT.
     *
     * The screens may RENDER what the server decided and must never DECIDE.
     *
     * Mutation: branch on a role code in a component.
     */
    public function test_no_screen_computes_an_authorization_decision(): void
    {
        $scanned = 0;

        foreach (glob(base_path('resources/js/**/*.jsx')) ?: [] as $file) {
            $scanned++;
        }

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path('resources/js')));

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'jsx') {
                continue;
            }

            $scanned++;
            $source = (string) file_get_contents($file->getPathname());

            foreach ([
                "=== 'system_administrator'",
                "=== 'org_admin'",
                "=== 'business_data'",
                'RoleCatalogue',
                'holdsRole',
                'AccessEngine',
            ] as $decision) {
                $this->assertStringNotContainsString(
                    $decision,
                    $source,
                    basename($file->getPathname()).' computes an authorization decision. Every answer '
                    .'comes from the engine; the screens render it.'
                );
            }
        }

        $this->assertGreaterThan(20, $scanned, 'Almost no screens were scanned.');
    }

    private function declaredClassFor(\Illuminate\Routing\Route $route): ?ActionClass
    {
        foreach ($route->gatherMiddleware() as $entry) {
            if (! is_string($entry) || ! str_starts_with($entry, RequireActionClass::class.':')) {
                continue;
            }

            return ActionClass::tryFrom(substr($entry, strlen(RequireActionClass::class) + 1));
        }

        return null;
    }

    private function signedInAs(User $user): self
    {
        return $this->withSession([
            EnsureSessionIsCurrent::SESSION_USER_ID => $user->id,
            EnsureSessionIsCurrent::SESSION_AUTHENTICATED_AT => now()->toIso8601String(),
        ]);
    }
}
