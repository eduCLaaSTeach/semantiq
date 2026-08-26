<?php

declare(strict_types=1);

namespace Tests\Feature\Privacy;

use App\Enums\Role;
use App\Models\User;
use App\Modules\Identity\Support\Authorization;
use App\Modules\Identity\Support\OrganisationContext;
use App\Modules\Identity\Support\PermissionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Who may reach a privacy request, and who deliberately may not.
 *
 * THE AUDITOR IS THE INTERESTING CASE. R1.4b gave the Auditor capability a
 * clear boundary: it grants audit READ and nothing else. A privacy request
 * contains one named person's assembled personal data - account, access,
 * activity - gathered into one place precisely because they asked for it.
 * Reading the trail and reading that are different things, and the capability
 * does not stretch.
 *
 * Every test here goes through `Authorization` or a real HTTP request, never
 * through navigation. Hiding a menu item is never authorization.
 */
class PrivacyAuthorizationTest extends TestCase
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
    public function no_privacy_permission_carries_the_auditor_capability(): void
    {
        $registry = app(PermissionRegistry::class);

        foreach (['view', 'manage', 'release'] as $action) {
            $permission = $registry->get('admin.privacy_requests.'.$action);

            $this->assertNotNull($permission);
            $this->assertFalse(
                $permission->orAuditor,
                "admin.privacy_requests.{$action} carries orAuditor. The Auditor capability grants audit "
                .'read only; a privacy request is one person\'s assembled personal data.',
            );
        }
    }

    #[Test]
    public function an_auditor_who_is_a_viewer_cannot_read_privacy_requests(): void
    {
        $auditor = $this->personOn(Role::Viewer, auditor: true);

        $this->assertFalse(
            app(Authorization::class)->allows($auditor, 'admin.privacy_requests.view'),
        );
    }

    #[Test]
    public function a_typed_url_refuses_an_auditor(): void
    {
        $auditor = $this->personOn(Role::Viewer, auditor: true);

        $response = $this->actingAs($auditor)->get('/admin/governance/privacy-requests');

        $this->assertContains($response->getStatusCode(), [403, 404]);
    }

    #[Test]
    public function an_auditor_still_reads_the_audit_trail(): void
    {
        $auditor = $this->personOn(Role::Viewer, auditor: true);

        $this->assertTrue(
            app(Authorization::class)->allows($auditor, 'admin.audit.view'),
            'the R1.4b capability must be unaffected by this batch',
        );
    }

    #[Test]
    public function an_administrator_may_manage_but_not_release(): void
    {
        $admin = $this->personOn(Role::Admin);
        $authorization = app(Authorization::class);

        $this->assertTrue($authorization->allows($admin, 'admin.privacy_requests.view'));
        $this->assertTrue($authorization->allows($admin, 'admin.privacy_requests.manage'));

        $this->assertFalse(
            $authorization->allows($admin, 'admin.privacy_requests.release'),
            'assembling a response and authorising its disclosure are different acts',
        );
    }

    #[Test]
    public function a_system_administrator_may_release(): void
    {
        $this->assertTrue(
            app(Authorization::class)->allows($this->personOn(Role::SystemAdmin), 'admin.privacy_requests.release'),
        );
    }

    #[Test]
    public function a_domain_owner_cannot_read_privacy_requests(): void
    {
        $this->assertFalse(
            app(Authorization::class)->allows($this->personOn(Role::DomainOwner), 'admin.privacy_requests.view'),
            'governance READ sits at Domain Owner, but a privacy request is not governance metadata - '
            .'it is one person\'s personal data',
        );
    }

    #[Test]
    public function a_typed_release_post_refuses_an_administrator(): void
    {
        $response = $this->actingAs($this->personOn(Role::Admin))
            ->post('/admin/governance/privacy-requests/1/release', ['evidence_reference' => 'Posted.']);

        $this->assertContains($response->getStatusCode(), [403, 404]);
    }

    #[Test]
    public function no_privacy_permission_names_a_business_domain(): void
    {
        $registry = app(PermissionRegistry::class);

        foreach (['view', 'manage', 'release'] as $action) {
            $key = 'admin.privacy_requests.'.$action;

            foreach (['finance', 'sales', 'people', 'hr'] as $domain) {
                $this->assertStringNotContainsString($domain, $key);
                $this->assertStringNotContainsString(
                    $domain,
                    strtolower($registry->get($key)->description),
                );
            }
        }
    }
}
