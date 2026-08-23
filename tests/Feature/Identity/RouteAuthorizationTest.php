<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Enums\Role;
use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Identity\Models\AccessRole;
use App\Modules\Identity\Support\Authorization;
use App\Modules\Identity\Support\OrganisationContext;
use App\Modules\Platform\Enums\LifecycleStatus;
use App\Support\Navigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ControlsSecurityPolicy;
use Tests\TestCase;

/**
 * The route boundary. ADM-007's second enforcement layer.
 *
 * The claim: menu visibility is never authorization. Every one of these screens
 * has to refuse a typed URL exactly as the rail refuses to show the link, and
 * the two have to agree for every tier - if the rail is stricter, people are
 * confused; if the route is stricter, nothing breaks; if the ROUTE is looser,
 * the whole model is decorative.
 */
class RouteAuthorizationTest extends TestCase
{
    use ControlsSecurityPolicy, RefreshDatabase;

    /**
     * An account placed in the organisation currently in force.
     *
     * Placement is not incidental to these tests. `UserRegistry` refuses any
     * mutation on a subject outside the current organisation
     * (VAL-ORG-SUBJECT-001), so an unplaced account is unmanageable - which is
     * exactly what a real account looks like, because both the registry and
     * Microsoft sign-in place one at creation. A helper that skipped it would
     * be testing a state the application cannot produce.
     */
    private function person(Role $role, LifecycleStatus $status = LifecycleStatus::Active): User
    {
        $user = User::query()->create(['name' => 'Test Person', 'email' => uniqid().'@example.test']);
        $user->forceFill([
            'role' => $role,
            'status' => $status,
            'organisation_id' => app(OrganisationContext::class)->currentId(),
        ])->save();

        return $user->refresh();
    }

    /**
     * Every GET screen in this gate, with the permission it is gated by.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function screens(): array
    {
        return [
            'organisation' => ['admin.organisation', 'admin.organisation.view'],
            'business units' => ['admin.business-units', 'admin.business_units.view'],
            'teams' => ['admin.teams', 'admin.teams.view'],
            'users' => ['admin.users', 'admin.users.view'],
            'roles' => ['admin.roles', 'admin.roles.view'],
            'permissions' => ['admin.permissions', 'admin.permissions.view'],
            'entitlements' => ['admin.entitlements', 'admin.entitlements.view'],
            'access reviews' => ['admin.access-reviews', 'admin.access_reviews.view'],
        ];
    }

    #[DataProvider('screens')]
    #[Test]
    public function a_business_user_is_refused_every_gate_two_screen_by_url(string $routeName, string $permission): void
    {
        // The named acceptance criterion, applied to all eight screens rather
        // than to a sample of them.
        foreach ([Role::Viewer, Role::Contributor, Role::Analyst, Role::DomainOwner] as $tier) {
            $this->actingAs($this->person($tier))
                ->get(route($routeName))
                ->assertForbidden();
        }
    }

    #[DataProvider('screens')]
    #[Test]
    public function an_administrator_reaches_every_gate_two_screen(string $routeName, string $permission): void
    {
        $this->actingAs($this->person(Role::Admin))
            ->get(route($routeName))
            ->assertOk();
    }

    #[Test]
    public function the_rail_and_the_route_agree_for_every_tier(): void
    {
        $navigation = app(Navigation::class);

        foreach (self::screens() as $label => [$routeName, $permission]) {
            foreach (Role::cases() as $tier) {
                $person = $this->person($tier);

                $inRail = $this->railContains($navigation->for($person), $routeName);
                $reaches = $this->actingAs($person)->get(route($routeName))->status() === 200;

                // Two implementations of one rule drift, and the drift is
                // invisible until the looser one guards something that matters.
                $this->assertSame(
                    $inRail,
                    $reaches,
                    $tier->label().' sees and reaches '.$label.' differently',
                );
            }
        }
    }

    #[Test]
    public function a_suspended_account_is_refused_even_with_the_highest_tier(): void
    {
        // A live session can outlive the moment somebody was disabled, so this
        // has to hold at the route and not only at sign-in.
        $disabled = $this->person(Role::SystemAdmin, LifecycleStatus::Disabled);

        $this->actingAs($disabled)->get(route('admin.users'))->assertForbidden();
        $this->actingAs($disabled)->get(route('admin.overview'))->assertForbidden();
    }

    #[Test]
    public function every_write_route_in_this_gate_is_gated_by_a_permission(): void
    {
        // A structural guard. The failure it catches is a new POST or PUT added
        // later without middleware - which no functional test would notice,
        // because the screen it belongs to would keep working perfectly.
        $ungated = [];

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();

            if ($name === null || ! str_starts_with($name, 'admin.')) {
                continue;
            }

            if (array_intersect(['POST', 'PUT', 'PATCH', 'DELETE'], $route->methods()) === []) {
                continue;
            }

            $middleware = $route->gatherMiddleware();

            $gated = collect($middleware)->contains(
                fn ($m): bool => is_string($m) && (str_starts_with($m, 'permission:') || str_starts_with($m, 'policy:')),
            );

            if (! $gated) {
                $ungated[] = $name;
            }
        }

        $this->assertSame([], $ungated, 'Ungated write routes: '.implode(', ', $ungated));
    }

    #[Test]
    public function an_administrator_cannot_escalate_through_the_tier_route(): void
    {
        // The escalation attempt spelled out: an Administrator posting directly
        // to the route that changes a tier, asking for the tier above their own.
        $admin = $this->person(Role::Admin);
        $subject = $this->person(Role::Viewer);

        // `withSession($this->confirmedIdentity())` because a tier change is an
        // ADM-010 critical action from gate 3 onwards: without a recent identity
        // confirmation the request is redirected to the confirmation screen and
        // never reaches the authority check this test is about. The escalation
        // is still refused either way; confirming first is what makes the
        // refusal come from the RIGHT control.
        $this->actingAs($admin)
            ->withSession($this->confirmedIdentity())
            ->from(route('admin.users.show', $subject))
            ->post(route('admin.users.tier', $subject), ['role' => Role::SystemAdmin->value])
            ->assertSessionHasErrors('authority');

        $this->assertSame(Role::Viewer, $subject->refresh()->role);
    }

    #[Test]
    public function an_administrator_cannot_escalate_by_assigning_a_higher_role(): void
    {
        $sysAdmin = $this->person(Role::SystemAdmin);
        $admin = $this->person(Role::Admin);

        $powerful = AccessRole::query()->where('code', 'system_admin')->firstOrFail();

        // The recorded grant would be inert anyway - the ceiling sees to that -
        // but a grant that silently does nothing is its own kind of lie, and an
        // access review would show authority the person does not have.
        $this->actingAs($admin)
            ->withSession($this->confirmedIdentity())
            ->from(route('admin.users.show', $admin))
            ->post(route('admin.users.roles', $admin), ['role_id' => $powerful->getKey(), 'operation' => 'assign'])
            ->assertSessionHasErrors('roles');

        $this->assertFalse($admin->refresh()->accessRoles()->whereKey($powerful->getKey())->exists());
        $this->assertFalse(app(Authorization::class)->allows($admin, 'admin.platform.view'));
    }

    #[Test]
    public function an_administrator_cannot_open_an_account_that_outranks_them(): void
    {
        $admin = $this->person(Role::Admin);
        $sysAdmin = $this->person(Role::SystemAdmin);

        $this->actingAs($admin)
            ->get(route('admin.users.show', $sysAdmin))
            ->assertForbidden();
    }

    #[Test]
    public function a_refused_route_is_audited(): void
    {
        $viewer = $this->person(Role::Viewer);

        $this->actingAs($viewer)->get(route('admin.users'))->assertForbidden();

        $event = AuditEvent::withoutOrganisationScope()
            ->where('action', 'privileged.action.denied')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('denied', $event->outcome->value);
        $this->assertSame($viewer->id, $event->actor_user_id);
    }

    /**
     * Whether a route name appears anywhere in a rendered rail.
     *
     * @param  array<string, list<array<string, mixed>>>  $clusters
     */
    private function railContains(array $clusters, string $routeName): bool
    {
        $found = false;

        array_walk_recursive($clusters, function ($value, $key) use ($routeName, &$found): void {
            if ($key === 'route' && $value === $routeName) {
                $found = true;
            }
        });

        return $found;
    }
}
