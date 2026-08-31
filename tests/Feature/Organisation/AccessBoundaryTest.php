<?php

declare(strict_types=1);

namespace Tests\Feature\Organisation;

use App\Modules\Organisation\Http\Middleware\RequireSystemAdministrator;
use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;
use App\Modules\Platform\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use ReflectionClass;
use Tests\Support\OrganisationFactory;
use Tests\TestCase;

/**
 * Negative cases 1-4 and 13: the authorisation boundary and the absent verb.
 */
final class AccessBoundaryTest extends TestCase
{
    use RefreshDatabase;

    private OrganisationFactory $make;

    protected function setUp(): void
    {
        parent::setUp();

        $this->make = new OrganisationFactory;
    }

    /** Case 1. Mutation: remove the session middleware. */
    public function test_an_anonymous_request_discloses_no_structure(): void
    {
        $organisation = $this->make->organisation();
        $unit = $this->make->businessUnit($organisation, 'Confidential Unit');

        foreach ($this->organisationRoutes() as $uri) {
            $response = $this->get($uri);

            $response->assertRedirect(route('entry'));
            $this->assertStringNotContainsString('Confidential Unit', $response->getContent());
        }

        $this->assertNotNull($unit->id);
    }

    /** Case 2. Mutation: drop the platform-role gate. */
    public function test_an_authenticated_non_administrator_is_refused(): void
    {
        $organisation = $this->make->organisation();
        $this->make->businessUnit($organisation, 'Confidential Unit');

        $response = $this->actingAsUser($this->make->user($organisation))
            ->get('/console/organisation/business-units');

        $response->assertRedirect(route('auth.access-denied'));
        $this->assertStringNotContainsString('Confidential Unit', $response->getContent());
    }

    /** Case 3, the positive control. Without it, cases 1 and 2 could pass on a broken route. */
    public function test_a_system_administrator_reads_structure(): void
    {
        $organisation = $this->make->organisation();
        $this->make->businessUnit($organisation, 'Delivery');

        $this->actingAsUser($this->make->user($organisation, administrator: true))
            ->get('/console/organisation/business-units')
            ->assertOk();
    }

    /**
     * Case 4. SYS-004, asserted against the authorisation boundary.
     *
     * This is the case most likely to be written vacuously. There is no business
     * data in P1-01 to withhold, so a test that merely finds nothing would pass
     * for the wrong reason and keep passing after the boundary was removed.
     *
     * So it asserts the boundary instead: no class in the Organisation module
     * answers a question about business-domain access, and administering
     * structure therefore cannot be a route to it.
     *
     * Mutation: add a domain accessor to the organisation service.
     */
    public function test_administering_structure_exposes_no_business_domain_accessor(): void
    {
        $forbidden = ['domain', 'permission', 'entitlement', 'scope', 'sensitivity', 'canaccess', 'mayaccess'];

        foreach ($this->moduleClasses() as $class) {
            foreach ((new ReflectionClass($class))->getMethods() as $method) {
                if ($method->class !== $class) {
                    continue;
                }

                // An Eloquent local scope is a query builder, not an
                // authorisation scope. Strip the convention prefix and judge
                // what is left, so scopeCurrent passes while scopeForDomain -
                // or any method actually named for authorisation scope - does
                // not.
                $name = strtolower($this->withoutEloquentScopePrefix($method->getName()));

                foreach ($forbidden as $needle) {
                    $this->assertStringNotContainsString(
                        $needle,
                        $name,
                        "{$class}::{$method->getName()} answers a question about business access. "
                        .'P1-01 records structure and grants nothing; P1-05 owns the access engine.'
                    );
                }
            }
        }
    }

    /**
     * Case 1, the part that was actually broken.
     *
     * Route-model binding used to run before the session gate, so an anonymous
     * request answered 302 for a record that existed and 404 for one that did
     * not. That difference is a directory-enumeration oracle: it lets an
     * unauthenticated visitor map the organisation by probing identifiers,
     * without ever being allowed to read one.
     *
     * Mutation: remove the middleware priority in bootstrap/app.php that puts
     * EnsureSessionIsCurrent ahead of SubstituteBindings.
     */
    public function test_an_anonymous_probe_cannot_tell_an_existing_record_from_a_missing_one(): void
    {
        $organisation = $this->make->organisation();
        $unit = $this->make->businessUnit($organisation);
        $team = $this->make->team($this->make->department($unit));

        foreach ([
            ['/console/organisation/business-units/', $unit->id],
            ['/console/organisation/teams/', $team->id],
        ] as [$path, $existingId]) {
            $existing = $this->get($path.$existingId);
            $missing = $this->get($path.'999999');

            $this->assertSame(
                $existing->getStatusCode(),
                $missing->getStatusCode(),
                "An anonymous request to {$path} answers differently for a record that exists than "
                .'for one that does not, which discloses existence to a visitor who may read neither.'
            );
        }
    }

    /** The same oracle, for an authenticated user who is not an administrator. */
    public function test_a_non_administrator_probe_cannot_tell_an_existing_record_from_a_missing_one(): void
    {
        $organisation = $this->make->organisation();
        $unit = $this->make->businessUnit($organisation);

        $session = $this->actingAsUser($this->make->user($organisation));

        $existing = $session->get('/console/organisation/business-units/'.$unit->id);
        $missing = $session->get('/console/organisation/business-units/999999');

        $this->assertSame($existing->getStatusCode(), $missing->getStatusCode());
    }

    /** Case 13. Mutation: register a DELETE route. */
    public function test_no_delete_verb_is_registered_anywhere_in_this_unit(): void
    {
        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'console/organisation')) {
                continue;
            }

            $this->assertNotContains(
                'DELETE',
                $route->methods(),
                "Route [{$route->uri()}] registers DELETE. P1-01 offers no hard delete on any type: "
                .'deactivation and left_at exist so history stays answerable.'
            );
        }
    }

    /**
     * "scopeCurrent" is Eloquent's local-scope convention: the method name
     * begins with scope and continues in upper case. Nothing else is stripped.
     */
    private function withoutEloquentScopePrefix(string $method): string
    {
        return preg_match('/^scope[A-Z]/', $method) === 1
            ? substr($method, 5)
            : $method;
    }

    /** @return list<string> */
    private function organisationRoutes(): array
    {
        $uris = [];

        foreach (Route::getRoutes() as $route) {
            if (in_array('GET', $route->methods(), true) && str_starts_with($route->uri(), 'console/organisation')) {
                // Every binding placeholder, not a hand-kept list: a route added
                // later with a new parameter name must still be swept, and a
                // literal "{legalEntity}" in the path would 404 for the wrong
                // reason and quietly stop testing the middleware.
                $uris[] = '/'.preg_replace('/\{[^}]+\}/', '1', $route->uri());
            }
        }

        return $uris;
    }

    /** @return list<class-string> */
    private function moduleClasses(): array
    {
        $classes = [];
        $root = __DIR__.'/../../../app/Modules/Organisation';

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = str_replace([$root.'/', '.php', '/'], ['', '', '\\'], $file->getPathname());
            $class = 'App\\Modules\\Organisation\\'.$relative;

            if (class_exists($class)) {
                $classes[] = $class;
            }
        }

        $this->assertNotEmpty($classes, 'No Organisation classes were found, so this test proves nothing.');

        return $classes;
    }

    private function actingAsUser(User $user): self
    {
        return $this->withSession([
            EnsureSessionIsCurrent::SESSION_USER_ID => $user->id,
            EnsureSessionIsCurrent::SESSION_AUTHENTICATED_AT => now()->toIso8601String(),
        ]);
    }

    /** The gate must stay a single explicit check, not grow into a role framework. */
    public function test_the_authorisation_gate_reads_only_the_platform_role(): void
    {
        $source = file_get_contents((new ReflectionClass(RequireSystemAdministrator::class))->getFileName());

        $this->assertStringContainsString('isSystemAdministrator()', $source);
        $this->assertStringNotContainsString('tenant_id', $source);
    }
}
