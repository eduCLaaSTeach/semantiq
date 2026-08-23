<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Enums\BusinessDomain;
use App\Enums\Role;
use App\Models\User;
use App\Modules\Identity\Support\Authorization;
use App\Modules\Identity\Support\OrganisationContext;
use App\Modules\Identity\Support\PermissionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ControlsSecurityPolicy;
use Tests\TestCase;

/**
 * The gate 3 boundary: who may reach the Security screens, and whether the rail
 * and the routes agree about it.
 *
 * These are structural tests. They do not exercise a feature; they assert
 * properties of the wiring that a reviewer would otherwise have to hold in
 * their head - and that would silently stop holding the next time somebody adds
 * a route.
 */
class SecurityAccessTest extends TestCase
{
    use ControlsSecurityPolicy, RefreshDatabase;

    private function personOn(Role $role): User
    {
        $user = User::query()->create(['name' => 'Test Person', 'email' => uniqid().'@example.test']);

        $user->forceFill([
            'role' => $role,
            'organisation_id' => app(OrganisationContext::class)->require()->getKey(),
        ])->save();

        return $user->refresh();
    }

    /**
     * @return list<string>
     */
    private function securityRouteNames(): array
    {
        $names = [];

        foreach (Route::getRoutes() as $route) {
            $name = (string) $route->getName();

            if (str_starts_with($name, 'admin.security.')) {
                $names[] = $name;
            }
        }

        return array_values(array_unique($names));
    }

    #[Test]
    public function the_route_family_is_exactly_the_one_dec_001_settled(): void
    {
        // DEC-001 fixed these five URLs in advance so gate 3 implemented
        // against a recorded decision rather than re-opening the question.
        $this->assertSame('http://localhost:8000/admin/security', route('admin.security.overview'));
        $this->assertSame('http://localhost:8000/admin/security/authentication', route('admin.security.authentication'));
        $this->assertSame('http://localhost:8000/admin/security/sessions', route('admin.security.sessions'));
        $this->assertSame('http://localhost:8000/admin/security/api', route('admin.security.api'));
        $this->assertSame('http://localhost:8000/admin/security/secrets', route('admin.security.secrets'));
    }

    #[Test]
    public function every_security_read_route_is_refused_below_system_administrator(): void
    {
        $admin = $this->personOn(Role::Admin);

        foreach ([
            'admin.security.overview',
            'admin.security.authentication',
            'admin.security.sessions',
            'admin.security.api',
            'admin.security.secrets',
        ] as $name) {
            $this->actingAs($admin)->get(route($name))->assertForbidden();
        }
    }

    #[Test]
    public function a_business_user_is_refused_by_url(): void
    {
        $viewer = $this->personOn(Role::Viewer);

        $this->actingAs($viewer)->get(route('admin.security.overview'))->assertForbidden();
        $this->actingAs($viewer)->get(route('admin.security.secrets'))->assertForbidden();
    }

    #[Test]
    public function a_guest_is_sent_to_sign_in_rather_than_refused(): void
    {
        $this->get(route('admin.security.overview'))->assertRedirect(route('sign-in'));
    }

    #[Test]
    public function every_security_write_route_is_gated_by_a_permission(): void
    {
        $ungated = [];

        foreach (Route::getRoutes() as $route) {
            $name = (string) $route->getName();

            if (! str_starts_with($name, 'admin.security.')) {
                continue;
            }

            if (! array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
                continue;
            }

            $gated = false;

            foreach ($route->gatherMiddleware() as $middleware) {
                if (is_string($middleware) && str_starts_with($middleware, 'permission:')) {
                    $gated = true;

                    break;
                }
            }

            if (! $gated) {
                $ungated[] = $name;
            }
        }

        $this->assertSame([], $ungated, 'Ungated security write routes: '.implode(', ', $ungated));
    }

    #[Test]
    public function every_security_write_route_demands_a_recent_identity_confirmation(): void
    {
        // ADM-010's critical actions. A policy change or a secret-reference
        // change that only needed a live session would be satisfied by an
        // unlocked machine.
        $unconfirmed = [];

        foreach (Route::getRoutes() as $route) {
            $name = (string) $route->getName();

            if (! str_starts_with($name, 'admin.security.')) {
                continue;
            }

            if (! array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
                continue;
            }

            $confirmed = false;

            foreach ($route->gatherMiddleware() as $middleware) {
                if (is_string($middleware) && str_starts_with($middleware, 'confirm:')) {
                    $confirmed = true;

                    break;
                }
            }

            if (! $confirmed) {
                $unconfirmed[] = $name;
            }
        }

        $this->assertSame([], $unconfirmed, 'Security write routes with no confirmation: '.implode(', ', $unconfirmed));
    }

    #[Test]
    public function the_rail_leaves_and_their_routes_are_gated_alike(): void
    {
        // The drift this guards against: a node offered under one permission
        // pointing at a route that enforces another.
        $expected = [
            'Security Overview' => ['admin.security.overview', 'admin.security.view'],
            'Authentication Policy' => ['admin.security.authentication', 'admin.security.view'],
            'Session Policy' => ['admin.security.sessions', 'admin.security.view'],
            'API Security' => ['admin.security.api', 'admin.security.view'],
            'Secret References' => ['admin.security.secrets', 'admin.secrets.view'],
        ];

        $group = null;

        foreach ((array) config('navigation.clusters.System Administration', []) as $node) {
            if (($node['label'] ?? null) === 'Security') {
                $group = $node;

                break;
            }
        }

        $this->assertNotNull($group, 'The Security group is missing from the rail.');

        foreach ($group['children'] as $leaf) {
            [$routeName, $permission] = $expected[$leaf['label']];

            $this->assertSame($routeName, $leaf['route'] ?? null, $leaf['label'].' points at the wrong route');
            $this->assertSame(
                $permission,
                config('navigation.policies.'.$leaf['policy'].'.permission'),
                $leaf['label'].' is offered under the wrong permission',
            );

            $this->assertContains(
                'permission:'.$permission,
                Route::getRoutes()->getByName($routeName)->gatherMiddleware(),
                $leaf['label'].' route does not enforce the permission its rail node names',
            );
        }
    }

    #[Test]
    public function no_security_leaf_is_still_marked_unbuilt(): void
    {
        // The other half of "nothing hanging": a leaf DEC-001 authored as a
        // "Soon" destination that gate 3 forgot to wire would look finished in
        // the plan and be a dead end on the screen.
        foreach ((array) config('navigation.clusters.System Administration', []) as $node) {
            if (($node['label'] ?? null) !== 'Security') {
                continue;
            }

            foreach ($node['children'] as $leaf) {
                $this->assertArrayHasKey('route', $leaf, $leaf['label'].' still has no route');
                $this->assertTrue(Route::has($leaf['route']), $leaf['label'].' points at a route that does not exist');
            }
        }
    }

    #[Test]
    public function the_four_new_permissions_are_declared_and_sit_at_system_administrator(): void
    {
        $registry = app(PermissionRegistry::class);

        foreach ([
            'admin.security.view',
            'admin.security.update',
            'admin.secrets.view',
            'admin.secrets.manage',
        ] as $key) {
            $permission = $registry->get($key);

            $this->assertNotNull($permission, $key.' is not declared');
            $this->assertSame(Role::SystemAdmin, $permission->minimumTier, $key.' has the wrong ceiling');
        }
    }

    #[Test]
    public function no_security_permission_names_a_business_domain(): void
    {
        // ROLE_MODEL.md section 1. Administering the platform never grants
        // business data: an administrator who can weaken the session policy
        // still holds no Finance figures.
        $domains = array_map(
            static fn (BusinessDomain $domain): string => $domain->value,
            BusinessDomain::cases(),
        );

        foreach (app(PermissionRegistry::class)->all() as $key => $permission) {
            if (! str_starts_with($key, 'admin.security') && ! str_starts_with($key, 'admin.secrets')) {
                continue;
            }

            foreach ($domains as $domain) {
                $this->assertStringNotContainsString($domain, $key, $key.' names a business domain');
            }
        }
    }

    #[Test]
    public function a_system_administrator_holds_no_business_domain_by_virtue_of_the_security_grants(): void
    {
        // The two-dimension model, asserted at the gate that most looks like it
        // should grant everything.
        $admin = $this->personOn(Role::SystemAdmin);
        $authorization = app(Authorization::class);

        $this->assertTrue($authorization->allows($admin, 'admin.security.update'));
        $this->assertTrue($authorization->allows($admin, 'admin.secrets.manage'));

        $this->assertSame([], $admin->entitledDomains());
    }

    #[Test]
    public function an_unknown_confirmation_action_on_a_route_blocks_rather_than_passes(): void
    {
        // A typo in a route definition must not quietly remove a control.
        Route::middleware(['web', 'auth', 'confirm:not_a_real_action'])
            ->get('/testing/confirm-typo', fn (): string => 'reached')
            ->name('testing.confirm-typo');

        $this->actingAs($this->personOn(Role::SystemAdmin))
            ->get('/testing/confirm-typo')
            ->assertForbidden();
    }
}
