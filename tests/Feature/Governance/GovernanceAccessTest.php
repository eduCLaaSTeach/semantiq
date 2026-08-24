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
 * THE AUDITOR ARRIVED IN R1.4b. Decision D2 extended the authorization layer
 * with the capability, and the governance reads now admit it. The capability
 * itself is covered by `AuditorCapabilityTest`; what is asserted here is the
 * shape of the GOVERNANCE catalogue - reads admit an Auditor, writes never do -
 * so a later governance permission cannot pick the flag up by accident.
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
    public function the_auditor_capability_is_on_governance_reads_and_on_no_governance_write(): void
    {
        /*
         * The positive counterpart of the R1.4a batch-boundary guard, which
         * asserted that NO permission carried `orAuditor` while `Authorization`
         * could not honour it. R1.4b built both halves in one change, so this
         * now asserts the shape of what was added rather than its absence.
         *
         * The capability belongs on governance READS - `ROLE_MODEL.md` section
         * 2 lists reviewing data-protection and sovereignty evidence among an
         * Auditor's capabilities - and on no governance write. An Auditor
         * reviews; they do not draft, request or approve.
         *
         * `AuditorCapabilityTest` covers the capability itself. This one guards
         * the governance catalogue specifically, so a later governance
         * permission cannot pick the flag up by accident.
         */
        $governanceReads = [];
        $governanceWrites = [];

        foreach (app(PermissionRegistry::class)->all() as $key => $permission) {
            if (! str_contains($key, 'data_protection') && ! str_contains($key, 'sovereignty')
                && ! str_contains($key, 'retention')) {
                continue;
            }

            if ($permission->isRead()) {
                $governanceReads[$key] = $permission->orAuditor;
            } else {
                $governanceWrites[$key] = $permission->orAuditor;
            }
        }

        $this->assertNotEmpty($governanceReads);

        foreach ($governanceReads as $key => $carries) {
            $this->assertTrue($carries, "The governance read `{$key}` does not admit an Auditor.");
        }

        foreach ($governanceWrites as $key => $carries) {
            $this->assertFalse($carries, "The governance write `{$key}` admits an Auditor.");
        }
    }
}
