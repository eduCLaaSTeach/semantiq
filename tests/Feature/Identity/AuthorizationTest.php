<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Enums\BusinessDomain;
use App\Enums\Role;
use App\Models\DomainEntitlement;
use App\Models\User;
use App\Modules\Identity\Services\RoleRegistry;
use App\Modules\Identity\Services\UserRegistry;
use App\Modules\Identity\Support\Authorization;
use App\Modules\Identity\Support\OrganisationContext;
use App\Modules\Identity\Support\PermissionRegistry;
use App\Modules\Platform\Enums\LifecycleStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * The permission model. Feature ADM-007, plan decision D2.
 *
 * The claim under test is that the TIER and the GRANT must both agree, with the
 * tier as a ceiling nothing can raise. If that ceiling can be bought around -
 * by assigning a role, by editing a role, by any route at all - then
 * `user_roles` is a privilege-escalation table and the whole model is theatre.
 */
class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

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

    private function authorization(): Authorization
    {
        app(Authorization::class)->flush();

        return app(Authorization::class);
    }

    #[Test]
    public function an_unknown_permission_denies(): void
    {
        // VAL-PERM-DENY-001. A typo must never become a grant, and a
        // permission deleted from the registry must stop working on deploy
        // rather than on a data migration.
        $this->assertFalse($this->authorization()->allows($this->person(Role::SystemAdmin), 'admin.invented.thing'));
        $this->assertFalse(app(PermissionRegistry::class)->has('admin.invented.thing'));
    }

    #[Test]
    public function a_tier_holds_its_defaults_with_no_role_assigned(): void
    {
        // The compatibility guarantee: an account with a tier and no assigned
        // role reaches everything that tier always reached, so nothing that
        // passed before this release starts failing.
        $this->assertTrue($this->authorization()->allows($this->person(Role::SystemAdmin), 'admin.platform.view'));
        $this->assertTrue($this->authorization()->allows($this->person(Role::Admin), 'admin.users.view'));
    }

    #[Test]
    public function a_lower_tier_is_refused_a_higher_permission(): void
    {
        $auth = $this->authorization();

        $this->assertFalse($auth->allows($this->person(Role::Admin), 'admin.platform.view'));
        $this->assertFalse($auth->allows($this->person(Role::Analyst), 'admin.users.view'));
        $this->assertFalse($auth->allows($this->person(Role::Viewer), 'admin.users.view'));
    }

    #[Test]
    public function a_role_carrying_a_permission_above_its_holders_tier_grants_nothing(): void
    {
        // THE ESCALATION TEST. If this ever fails, `user_roles` has become a
        // way to buy authority the tier was supposed to cap.
        $admin = $this->person(Role::SystemAdmin);
        $viewer = $this->person(Role::Viewer);

        $powerful = app(RoleRegistry::class)->create('powerful', 'Powerful', Role::SystemAdmin, null, $admin);
        app(RoleRegistry::class)->setPermissions($powerful, ['admin.platform.view'], $admin);

        // Assigned by hand, bypassing UserRegistry's own tier check, so that
        // what is under test is the CEILING rather than the assignment guard.
        $viewer->accessRoles()->attach($powerful->getKey());

        $this->assertFalse($this->authorization()->allows($viewer->refresh(), 'admin.platform.view'));
        $this->assertNotContains('admin.platform.view', $this->authorization()->effectiveFor($viewer));
    }

    #[Test]
    public function a_role_can_narrow_within_a_tier(): void
    {
        // The other half: a role is useful because it can carry LESS than its
        // tier. If a role could only ever be inert, it would be pointless.
        $admin = $this->person(Role::SystemAdmin);
        $narrow = app(RoleRegistry::class)->create('reader', 'Reader', Role::Admin, null, $admin);
        app(RoleRegistry::class)->setPermissions($narrow, ['admin.users.view'], $admin);

        $this->assertSame(['admin.users.view'], $narrow->permissionKeys());
        $this->assertTrue($this->authorization()->allows($admin, 'admin.users.view'));
    }

    #[Test]
    public function a_role_grants_a_permission_the_tier_does_not_carry_by_itself(): void
    {
        // The test that proves roles are load-bearing rather than decoration.
        // `admin.roles.manage` has an Administrator ceiling but is auto-granted
        // only from System Administrator, so this is a permission an
        // Administrator can hold ONLY through a role.
        $sysAdmin = $this->person(Role::SystemAdmin);
        $admin = $this->person(Role::Admin);

        $this->assertFalse($this->authorization()->allows($admin, 'admin.roles.manage'));

        $role = app(RoleRegistry::class)->create('role_manager', 'Role Manager', Role::Admin, null, $sysAdmin);
        app(RoleRegistry::class)->setPermissions($role, ['admin.roles.manage'], $sysAdmin);
        app(UserRegistry::class)->assignRole($admin, $role, $sysAdmin);

        $this->assertTrue($this->authorization()->allows($admin->refresh(), 'admin.roles.manage'));
    }

    #[Test]
    public function a_disabled_role_grants_nothing_while_it_keeps_its_assignments(): void
    {
        $sysAdmin = $this->person(Role::SystemAdmin);
        $admin = $this->person(Role::Admin);

        $role = app(RoleRegistry::class)->create('role_manager', 'Role Manager', Role::Admin, null, $sysAdmin);
        app(RoleRegistry::class)->setPermissions($role, ['admin.roles.manage'], $sysAdmin);
        app(UserRegistry::class)->assignRole($admin, $role, $sysAdmin);

        $this->assertTrue($this->authorization()->allows($admin->refresh(), 'admin.roles.manage'));

        app(RoleRegistry::class)->update($role, 'Role Manager', null, LifecycleStatus::Disabled, $sysAdmin);

        // Disabling is reversible and keeps history; that is the point of being
        // able to disable rather than delete.
        $this->assertTrue($admin->refresh()->accessRoles()->whereKey($role->getKey())->exists());
        $this->assertFalse($this->authorization()->allows($admin->refresh(), 'admin.roles.manage'));
    }

    #[Test]
    public function a_suspended_account_holds_no_permission_at_all(): void
    {
        // A live session can outlive the moment somebody was disabled, so the
        // check has to be here and not only at sign-in.
        $disabled = $this->person(Role::SystemAdmin, LifecycleStatus::Disabled);

        $this->assertFalse($this->authorization()->allows($disabled, 'admin.platform.view'));
        $this->assertSame([], $this->authorization()->effectiveFor($disabled));
    }

    #[Test]
    public function effective_permissions_can_be_determined_for_a_user(): void
    {
        // ADM-007 requires this explicitly, and it is what the account screen
        // shows: the union of tier defaults and assigned roles, already
        // filtered by the ceiling, so it is what they can actually do.
        $effective = $this->authorization()->effectiveFor($this->person(Role::Admin));

        $this->assertContains('admin.users.view', $effective);
        $this->assertContains('admin.teams.view', $effective);
        $this->assertNotContains('admin.platform.view', $effective);
        $this->assertNotContains('admin.roles.manage', $effective);
    }

    #[Test]
    public function nobody_may_delegate_a_permission_they_do_not_hold(): void
    {
        // VAL-USER-ELEVATE-001, and the escalation route it closes: an
        // Administrator who has been granted `admin.roles.manage` can edit
        // roles - but must not be able to put a System Administrator permission
        // into one and then wear it.
        $sysAdmin = $this->person(Role::SystemAdmin);
        $admin = $this->person(Role::Admin);

        $manager = app(RoleRegistry::class)->create('role_manager', 'Role Manager', Role::Admin, null, $sysAdmin);
        app(RoleRegistry::class)->setPermissions($manager, ['admin.roles.manage'], $sysAdmin);
        app(UserRegistry::class)->assignRole($admin, $manager, $sysAdmin);

        $target = app(RoleRegistry::class)->create('escalate', 'Escalate', Role::Admin, null, $sysAdmin);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('you do not hold it yourself');

        app(RoleRegistry::class)->setPermissions($target, ['admin.platform.view'], $admin->refresh());
    }

    #[Test]
    public function nobody_may_grant_a_tier_above_their_own(): void
    {
        $admin = $this->person(Role::Admin);
        $subject = $this->person(Role::Viewer);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('more authority than you hold');

        app(UserRegistry::class)->changeTier($subject, Role::SystemAdmin, $admin);
    }

    #[Test]
    public function nobody_may_act_on_an_account_that_outranks_them(): void
    {
        $admin = $this->person(Role::Admin);
        $sysAdmin = $this->person(Role::SystemAdmin);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('more authority than you do');

        app(UserRegistry::class)->changeStatus($sysAdmin, LifecycleStatus::Disabled, $admin);
    }

    /* ---- The two dimensions stay separate --------------------------- */

    #[Test]
    public function a_system_administrator_with_no_entitlement_reads_no_business_data(): void
    {
        // ROLE_MODEL.md section 1, and the example the brief gives verbatim.
        // The single most important property of the whole access model.
        $sysAdmin = $this->person(Role::SystemAdmin);

        $this->assertTrue($this->authorization()->allows($sysAdmin, 'admin.platform.view'));

        $this->assertFalse($sysAdmin->isEntitledTo(BusinessDomain::Finance));
        $this->assertFalse($sysAdmin->isEntitledTo(BusinessDomain::People));
        $this->assertSame([], $sysAdmin->entitledDomains());
    }

    #[Test]
    public function granting_a_domain_grants_no_platform_authority(): void
    {
        // And the same claim from the other side, which is the one that would
        // be broken by somebody "helpfully" mapping domains onto permissions.
        $viewer = $this->person(Role::Viewer);
        DomainEntitlement::query()->create(['user_id' => $viewer->id, 'domain' => BusinessDomain::Finance->value]);

        $this->assertTrue($viewer->refresh()->isEntitledTo(BusinessDomain::Finance));
        $this->assertFalse($this->authorization()->allows($viewer, 'admin.users.view'));
        $this->assertSame([], $this->authorization()->effectiveFor($viewer));
    }

    #[Test]
    public function no_permission_in_the_registry_mentions_a_business_domain(): void
    {
        // A structural guard rather than a behavioural one. The day somebody
        // adds `admin.finance.view` to the registry, the two dimensions have
        // started merging and this test is where that gets noticed.
        $domains = array_map(fn (BusinessDomain $d): string => $d->value, BusinessDomain::cases());

        foreach (array_keys(app(PermissionRegistry::class)->all()) as $key) {
            foreach ($domains as $domain) {
                $this->assertStringNotContainsString(
                    '.'.$domain.'.',
                    $key,
                    'Permission "'.$key.'" names a business domain. Domain access is the second '
                    .'dimension and is never a permission.',
                );
            }
        }
    }
}
