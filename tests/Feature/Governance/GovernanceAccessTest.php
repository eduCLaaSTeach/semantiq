<?php

declare(strict_types=1);

namespace Tests\Feature\Governance;

use App\Enums\BusinessDomain;
use App\Enums\Role;
use App\Models\User;
use App\Modules\Identity\Support\Authorization;
use App\Modules\Identity\Support\OrganisationContext;
use App\Modules\Identity\Support\PermissionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The gate 4 boundary: who may reach the Governance screens, and whether the
 * rail and the routes agree about it.
 *
 * DECISION D13, SEC-DEC-067, expressed as tests:
 *
 *   READ     Domain Owner and up.
 *   MANAGE   Administrator and up.
 *   APPROVE  System Administrator only.
 *
 * And the rule that outranks all three: a governance permission NEVER grants
 * business-domain data. `ROLE_MODEL.md` section 1 says a role alone never
 * grants business data, and a Domain Owner who can read the sovereignty profile
 * must still hold no Finance figure.
 *
 * THE AUDITOR IS ABSENT FROM THESE TESTS ON PURPOSE. Decision D2 extends the
 * authorization layer with an Auditor capability, and it lands in R1.4b with
 * the audit log screen that needs it. Asserting an Auditor's access here would
 * be asserting behaviour this batch deliberately does not have.
 */
class GovernanceAccessTest extends TestCase
{
    use RefreshDatabase;

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
    private function governanceRouteNames(): array
    {
        $names = [];

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();

            if (is_string($name) && str_starts_with($name, 'admin.governance.')) {
                $names[] = $name;
            }
        }

        return array_values(array_unique($names));
    }

    #[Test]
    public function every_governance_route_names_a_permission(): void
    {
        /*
         * Structural. A route that reaches a governance screen on the cluster
         * policy alone would be gated at Domain Owner for reading AND for
         * approving, which is the separation D13 exists to create.
         */
        foreach ($this->governanceRouteNames() as $name) {
            $route = Route::getRoutes()->getByName($name);
            $middleware = $route?->gatherMiddleware() ?? [];

            $named = array_filter(
                $middleware,
                static fn ($m): bool => is_string($m) && str_starts_with($m, 'permission:'),
            );

            $this->assertNotEmpty($named, "The route `{$name}` names no permission.");
        }
    }

    #[Test]
    public function a_viewer_reaches_no_governance_screen(): void
    {
        $viewer = $this->personOn(Role::Viewer);

        foreach (['admin.governance.data-protection', 'admin.governance.personal-data', 'admin.governance.sovereignty'] as $route) {
            $this->actingAs($viewer)->get(route($route))->assertForbidden();
        }
    }

    #[Test]
    public function a_domain_owner_may_read_but_not_write(): void
    {
        $owner = $this->personOn(Role::DomainOwner);
        $authorization = app(Authorization::class);

        $this->assertTrue($authorization->allows($owner, 'admin.data_protection.view'));
        $this->assertTrue($authorization->allows($owner, 'admin.sovereignty.view'));

        $this->assertFalse($authorization->allows($owner, 'admin.data_protection.manage'));
        $this->assertFalse($authorization->allows($owner, 'admin.sovereignty.manage'));
        $this->assertFalse($authorization->allows($owner, 'admin.data_protection.approve'));
        $this->assertFalse($authorization->allows($owner, 'admin.sovereignty.approve'));

        $this->actingAs($owner)->get(route('admin.governance.sovereignty'))->assertOk();
        $this->actingAs($owner)
            ->put(route('admin.governance.sovereignty.update'), ['storage_geography' => 'sg'])
            ->assertForbidden();
    }

    #[Test]
    public function an_administrator_may_write_but_not_approve(): void
    {
        /*
         * The separation of duties D13 asks for, at the tier level. A person
         * who can weaken a sovereignty profile must not also be the person who
         * blesses it.
         */
        $admin = $this->personOn(Role::Admin);
        $authorization = app(Authorization::class);

        $this->assertTrue($authorization->allows($admin, 'admin.sovereignty.manage'));
        $this->assertFalse($authorization->allows($admin, 'admin.sovereignty.approve'));
        $this->assertFalse($authorization->allows($admin, 'admin.data_protection.approve'));

        $this->actingAs($admin)
            ->post(route('admin.governance.sovereignty.approve'), ['reason' => 'A reason long enough to pass.'])
            ->assertForbidden();
    }

    #[Test]
    public function a_system_administrator_may_approve(): void
    {
        $systemAdmin = $this->personOn(Role::SystemAdmin);
        $authorization = app(Authorization::class);

        $this->assertTrue($authorization->allows($systemAdmin, 'admin.data_protection.approve'));
        $this->assertTrue($authorization->allows($systemAdmin, 'admin.sovereignty.approve'));
    }

    #[Test]
    public function no_governance_permission_grants_any_business_domain(): void
    {
        /*
         * The gate 2 rule, asserted rather than assumed. This is the assertion
         * that stops the D13 widening turning into a data leak: a Domain Owner
         * who can now read governance holds no Finance, Sales or People data by
         * virtue of it.
         */
        $owner = $this->personOn(Role::DomainOwner);

        foreach (BusinessDomain::cases() as $domain) {
            $this->assertFalse(
                $owner->isEntitledTo($domain),
                "Reading governance granted entitlement to {$domain->value}."
            );
        }
    }

    #[Test]
    public function no_governance_permission_names_a_business_domain(): void
    {
        /* Structural counterpart to the test above: the catalogue itself must
         * not contain a governance permission that mentions a domain. */
        $domains = array_map(
            static fn (BusinessDomain $d): string => strtolower($d->value),
            BusinessDomain::cases(),
        );

        foreach (app(PermissionRegistry::class)->all() as $key => $permission) {
            if (! str_contains($key, 'data_protection') && ! str_contains($key, 'sovereignty')) {
                continue;
            }

            foreach ($domains as $domain) {
                $this->assertStringNotContainsString($domain, strtolower($key));
            }
        }
    }

    #[Test]
    public function a_disabled_account_reaches_nothing_however_high_its_tier(): void
    {
        /* SEC-DEC-026: a live session can outlive the moment somebody was
         * disabled, so the check is in the authorization layer and not only at
         * sign-in. */
        $person = $this->personOn(Role::SystemAdmin);
        $person->forceFill(['status' => 'disabled'])->save();

        $this->assertFalse(app(Authorization::class)->allows($person->refresh(), 'admin.sovereignty.view'));
    }

    #[Test]
    public function no_governance_permission_carries_the_auditor_capability_yet(): void
    {
        /*
         * Guards the batch boundary. Decision D2's Auditor capability belongs to
         * R1.4b, and a half-declared `orAuditor` here would be a capability the
         * authorization layer cannot honour - a node that appears and then
         * denies. When R1.4b lands, this test is REPLACED by its positive
         * counterpart rather than deleted.
         */
        foreach (app(PermissionRegistry::class)->all() as $permission) {
            $this->assertFalse(
                property_exists($permission, 'orAuditor') && $permission->orAuditor === true,
                'An Auditor capability is declared before the authorization layer can honour it.'
            );
        }
    }
}
