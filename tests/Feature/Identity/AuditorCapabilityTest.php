<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Enums\BusinessDomain;
use App\Enums\Role;
use App\Models\User;
use App\Modules\Identity\Support\Authorization;
use App\Modules\Identity\Support\OrganisationContext;
use App\Modules\Identity\Support\Permission;
use App\Modules\Identity\Support\PermissionRegistry;
use App\Modules\Identity\Support\PermissionRisk;
use App\Modules\Platform\Enums\LifecycleStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The Auditor capability. Decision D2, SEC-DEC-062.
 *
 * **This modifies gate 2's authorization core**, which is the most
 * security-critical code in the application, so it is tested on its own and
 * before the screen that needs it.
 *
 * WHAT THE CAPABILITY IS. `ROLE_MODEL.md` describes an Auditor as somebody who
 * reads the audit trail and reviews governance evidence without operating the
 * platform. Before this change the authorization layer could not express that:
 * `users.is_auditor` was understood by `Navigation` and by nothing else, so the
 * navigation rail was the only thing standing between a typed URL and the audit
 * trail. CLAUDE.md is explicit that hiding a menu item is never authorization.
 *
 * WHAT THESE TESTS HOLD IN PLACE, and each is a way the capability could have
 * become a hole:
 *
 *   It admits ONLY permissions that declare `orAuditor`.
 *   It admits ONLY read actions, refused at construction rather than caught.
 *   It does NOT raise the tier ceiling, so it cannot become authority in
 *     general.
 *   It grants NO business-domain entitlement.
 *   It does nothing at all for an account without the flag.
 *   A disabled Auditor holds nothing.
 *   A TYPED URL obeys it, in both directions.
 */
class AuditorCapabilityTest extends TestCase
{
    use RefreshDatabase;

    private function personOn(Role $role, bool $auditor = false): User
    {
        $user = User::query()->create(['name' => 'Test Person', 'email' => uniqid().'@example.test']);

        $user->forceFill([
            'role' => $role,
            'is_auditor' => $auditor,
            'organisation_id' => app(OrganisationContext::class)->require()->getKey(),
        ])->save();

        return $user->refresh();
    }

    #[Test]
    public function an_auditor_can_read_the_audit_trail_from_the_lowest_tier(): void
    {
        /*
         * The case the whole decision exists for. A Viewer who is an Auditor
         * sits five tiers below `admin.audit.view`'s old ceiling, and before
         * this change could not hold it by any route.
         */
        $auditor = $this->personOn(Role::Viewer, auditor: true);

        $this->assertTrue(app(Authorization::class)->allows($auditor, 'admin.audit.view'));
    }

    #[Test]
    public function the_same_account_without_the_flag_holds_nothing(): void
    {
        /* The capability must do nothing by default, which is every account. */
        $viewer = $this->personOn(Role::Viewer, auditor: false);

        foreach (app(PermissionRegistry::class)->auditorReadableKeys() as $key) {
            $this->assertFalse(
                app(Authorization::class)->allows($viewer, $key),
                "A Viewer who is not an auditor holds `{$key}`."
            );
        }
    }

    #[Test]
    public function an_auditor_holds_every_permission_that_declares_the_flag_and_no_other(): void
    {
        $auditor = $this->personOn(Role::Viewer, auditor: true);
        $plain = $this->personOn(Role::Viewer, auditor: false);

        $authorization = app(Authorization::class);
        $registry = app(PermissionRegistry::class);

        $gained = array_values(array_diff(
            $authorization->effectiveFor($auditor),
            $authorization->effectiveFor($plain),
        ));

        sort($gained);
        $expected = $registry->auditorReadableKeys();
        sort($expected);

        $this->assertSame(
            $expected,
            $gained,
            'The Auditor capability granted something other than exactly the permissions declaring it.'
        );
    }

    #[Test]
    public function every_auditor_permission_is_a_read(): void
    {
        /*
         * The invariant that makes the capability safe to widen later. If
         * somebody adds `orAuditor` to a write, this fails - and `Permission`
         * would have thrown before this test ran.
         */
        $registry = app(PermissionRegistry::class);

        foreach ($registry->auditorReadableKeys() as $key) {
            $permission = $registry->get($key);

            $this->assertNotNull($permission);
            $this->assertTrue(
                $permission->isRead(),
                "`{$key}` carries the Auditor capability on a `{$permission->action}` action."
            );
        }
    }

    #[Test]
    public function declaring_the_capability_on_a_write_is_refused_at_construction(): void
    {
        /*
         * Not a lint, not a test that runs later - the object cannot be built.
         * A write permission carrying this flag would hand an Auditor the
         * ability to change what they are supposed to be reviewing.
         */
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/read permissions only/');

        new Permission(
            key: 'admin.retention.manage',
            module: 'Admin',
            resource: 'retention',
            action: 'manage',
            description: 'Should not be constructible.',
            minimumTier: Role::Admin,
            risk: PermissionRisk::High,
            orAuditor: true,
        );
    }

    #[Test]
    public function an_auditor_cannot_manage_request_or_approve_anything(): void
    {
        $auditor = $this->personOn(Role::Viewer, auditor: true);
        $authorization = app(Authorization::class);

        foreach (app(PermissionRegistry::class)->all() as $key => $permission) {
            if ($permission->isRead()) {
                continue;
            }

            $this->assertFalse(
                $authorization->allows($auditor, $key),
                "An Auditor holds the write permission `{$key}`."
            );
        }
    }

    #[Test]
    public function the_capability_does_not_raise_the_tier_ceiling(): void
    {
        /*
         * The difference between "admits one declared permission" and "is a
         * promotion". An Auditor must not pick up everything a Domain Owner
         * holds just because one Domain Owner permission admits them.
         */
        $auditor = $this->personOn(Role::Viewer, auditor: true);
        $administrator = $this->personOn(Role::Admin, auditor: false);

        $authorization = app(Authorization::class);

        $auditorHolds = $authorization->effectiveFor($auditor);

        /* Nothing beyond the declared flag set and their own tier's defaults. */
        $beyondTheFlag = array_diff(
            $auditorHolds,
            app(PermissionRegistry::class)->auditorReadableKeys(),
            $authorization->effectiveFor($this->personOn(Role::Viewer, auditor: false)),
        );

        $this->assertSame([], array_values($beyondTheFlag));

        /*
         * And specifically NOT a promotion toward Administrator. Compared
         * against Administrator rather than Domain Owner deliberately: in this
         * catalogue a Domain Owner auto-holds only the four governance reads
         * and NOT `admin.audit.view`, which is auto-granted from System
         * Administrator - so an Auditor legitimately holds one permission a
         * Domain Owner does not, and comparing the two proves nothing.
         */
        $administratorOnly = array_diff($authorization->effectiveFor($administrator), $auditorHolds);

        $this->assertNotEmpty($administratorOnly, 'An Administrator should hold much more than an Auditor.');
        $this->assertContains('admin.users.view', $administratorOnly);
        $this->assertFalse($authorization->allows($auditor, 'admin.users.view'));
    }

    #[Test]
    public function an_auditor_gains_no_business_domain(): void
    {
        /*
         * ROLE_MODEL.md section 1: a platform role never grants business data,
         * and neither does a capability. An Auditor reading the audit trail
         * still reads no Finance figure.
         */
        $auditor = $this->personOn(Role::Viewer, auditor: true);

        foreach (BusinessDomain::cases() as $domain) {
            $this->assertFalse(
                $auditor->isEntitledTo($domain),
                "The Auditor capability granted entitlement to {$domain->value}."
            );
        }
    }

    #[Test]
    public function a_disabled_auditor_holds_nothing(): void
    {
        /* SEC-DEC-026. A live session can outlive the moment somebody was
         * disabled, so the status check runs before anything else. */
        $auditor = $this->personOn(Role::Viewer, auditor: true);
        $auditor->forceFill(['status' => LifecycleStatus::Disabled])->save();

        $this->assertFalse(app(Authorization::class)->allows($auditor->refresh(), 'admin.audit.view'));
        $this->assertSame([], app(Authorization::class)->effectiveFor($auditor));
    }

    #[Test]
    public function an_auditor_reaches_the_governance_read_screens_by_typed_url(): void
    {
        /*
         * The point of the whole decision: authorization, not navigation. These
         * are direct requests with no link clicked, and they must succeed
         * because the PERMISSION admits the auditor - not because a rail node
         * happened to render.
         */
        $auditor = $this->personOn(Role::Viewer, auditor: true);

        foreach ([
            'admin.governance.data-protection',
            'admin.governance.personal-data',
            'admin.governance.sovereignty',
        ] as $route) {
            $this->actingAs($auditor)->get(route($route))->assertOk();
        }
    }

    #[Test]
    public function an_auditor_is_refused_every_governance_write_by_typed_url(): void
    {
        $auditor = $this->personOn(Role::Viewer, auditor: true);

        $this->actingAs($auditor)
            ->put(route('admin.governance.sovereignty.update'), ['storage_geography' => 'sg'])
            ->assertForbidden();

        $this->actingAs($auditor)
            ->post(route('admin.governance.sovereignty.approve'), ['reason' => 'A reason long enough to pass.'])
            ->assertForbidden();

        $this->actingAs($auditor)
            ->put(route('admin.governance.data-protection.update'), ['applicable_regime' => 'EU GDPR'])
            ->assertForbidden();
    }

    #[Test]
    public function a_non_auditor_viewer_is_refused_the_same_screens(): void
    {
        /* The other half of the typed-URL check: the capability is what admits
         * them, so without it the same URL must refuse. */
        $viewer = $this->personOn(Role::Viewer, auditor: false);

        $this->actingAs($viewer)->get(route('admin.governance.sovereignty'))->assertForbidden();
    }
}
