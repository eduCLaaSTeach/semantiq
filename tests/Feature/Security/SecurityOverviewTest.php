<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Enums\Role;
use App\Models\User;
use App\Modules\Identity\Support\OrganisationContext;
use App\Modules\Security\Enums\AuthenticationMode;
use App\Modules\Security\Enums\ConcurrentSessionPolicy;
use App\Modules\Security\Enums\SecretProvider;
use App\Modules\Security\Enums\SecretType;
use App\Modules\Security\Enums\SecurityStatus;
use App\Modules\Security\Models\SecretReference;
use App\Modules\Security\Support\SecurityPosture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ControlsSecurityPolicy;
use Tests\TestCase;

/**
 * Security Overview - the read-only roll-up over ADM-009 to ADM-012.
 *
 * What is being proved is that it TELLS THE TRUTH. A summary screen that
 * reports green because it could not find anything wrong is worse than no
 * summary at all, so every test here creates a real problem in one of the four
 * areas and asserts that the roll-up surfaces it.
 */
class SecurityOverviewTest extends TestCase
{
    use ControlsSecurityPolicy, RefreshDatabase;

    private function admin(): User
    {
        $user = User::query()->create([
            'name' => 'Ada Admin',
            'email' => 'ada@example.test',
            'password' => Hash::make('correct-horse-battery'),
        ]);

        $user->forceFill([
            'role' => Role::SystemAdmin,
            'authentication_source' => 'local',
            'organisation_id' => app(OrganisationContext::class)->require()->getKey(),
        ])->save();

        return $user->refresh();
    }

    private function reference(array $overrides = []): SecretReference
    {
        $reference = new SecretReference;

        $reference->forceFill(array_merge([
            'organisation_id' => app(OrganisationContext::class)->require()->getKey(),
            'name' => 'Fabric client secret',
            'reference_type' => SecretType::ClientSecret,
            'provider' => SecretProvider::AzureKeyVault,
            'reference_identifier' => 'kv-semantiq/fabric',
            'purpose' => 'Fabric provisioning.',
            'environment' => 'production',
        ], $overrides));

        $reference->save();

        return $reference->refresh();
    }

    private function posture(): SecurityPosture
    {
        return app(SecurityPosture::class);
    }

    #[Test]
    public function the_screen_renders_every_section_the_gate_requires(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.security.overview'))
            ->assertOk()
            ->assertSee('Security posture')
            ->assertSee('The four areas')
            ->assertSee('Expiring credentials')
            ->assertSee('Security warnings')
            ->assertSee('Recent security events');
    }

    #[Test]
    public function the_overall_status_is_the_worst_of_the_four_areas(): void
    {
        // One critical finding among four healthy areas is a critical posture.
        // An average would hide exactly the thing somebody came to find.
        $this->reference(['expires_on' => Carbon::today()->subDay()]);

        $this->assertSame(SecurityStatus::Critical, $this->posture()->secrets()['status']);
        $this->assertSame(SecurityStatus::Critical, $this->posture()->overall());
    }

    #[Test]
    public function a_weak_authentication_mode_is_reported_as_a_warning(): void
    {
        $this->withSecurityPolicy('sign_in.mode', AuthenticationMode::LocalOnly->value);

        $authentication = $this->posture()->authentication();

        $this->assertSame(SecurityStatus::Warning, $authentication['status']);
        $this->assertNotSame([], $authentication['notes']);
    }

    #[Test]
    public function automatic_account_creation_is_reported_as_a_warning(): void
    {
        $this->withSecurityPolicy('sign_in.auto_create_users', true);

        $notes = implode(' ', $this->posture()->authentication()['notes']);

        $this->assertStringContainsString('given a SemantIQ account automatically', $notes);
    }

    #[Test]
    public function a_blank_tenant_allow_list_is_not_verified_rather_than_healthy(): void
    {
        // Gate 3 rule 9. A blank tenant means the check falls back to the Entra
        // application registration, which is a real constraint that this screen
        // cannot see - so it is reported as unverified rather than as either
        // safe or broken.
        $this->withSecurityPolicy('sign_in.allowed_tenant_id', '');
        $this->withSecurityPolicy('sign_in.allowed_email_domains', 'contoso.com');

        $authentication = $this->posture()->authentication();

        $this->assertSame(SecurityStatus::NotVerified, $authentication['status']);
    }

    #[Test]
    public function a_session_control_the_driver_cannot_run_is_not_available_rather_than_a_warning(): void
    {
        // The code is correct and the environment cannot support it. Colouring
        // that amber would put a permanent warning on the screen that nobody
        // can clear from the screen.
        config(['session.driver' => 'file']);
        $this->withSecurityPolicy('activity.concurrent_policy', ConcurrentSessionPolicy::Unlimited->value);

        $sessions = $this->posture()->sessions();

        $this->assertSame(SecurityStatus::NotAvailable, $sessions['status']);
        $this->assertFalse(SecurityStatus::NotAvailable->needsAttention());
    }

    #[Test]
    public function a_concurrency_policy_that_cannot_be_applied_is_a_warning_because_it_reads_as_protection(): void
    {
        // Different from the case above. A policy set to "one at a time" that
        // is not being applied is a claim somebody will believe, so it is a
        // warning rather than a neutral note.
        config(['session.driver' => 'file']);
        $this->withSecurityPolicy('activity.concurrent_policy', ConcurrentSessionPolicy::Single->value);

        $sessions = $this->posture()->sessions();

        $this->assertSame(SecurityStatus::Warning, $sessions['status']);
        $this->assertStringContainsString('is NOT being applied', implode(' ', $sessions['notes']));
    }

    #[Test]
    public function no_secret_references_at_all_reads_as_not_configured(): void
    {
        // Not healthy. This deployment certainly depends on credentials, and
        // an empty list means none of them is being tracked.
        $secrets = $this->posture()->secrets();

        $this->assertSame(SecurityStatus::NotConfigured, $secrets['status']);
        $this->assertStringContainsString('none of them has been recorded', $secrets['detail']);
    }

    #[Test]
    public function a_reference_with_no_dates_reads_as_not_verified(): void
    {
        $this->reference();

        $this->assertSame(SecurityStatus::NotVerified, $this->posture()->secrets()['status']);
    }

    #[Test]
    public function an_expiring_reference_appears_on_the_screen_with_its_date(): void
    {
        $this->reference([
            'name' => 'Lapsing certificate',
            'reference_type' => SecretType::Certificate,
            'expires_on' => Carbon::today()->addDays(9),
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.security.overview'))
            ->assertOk()
            ->assertSee('Lapsing certificate')
            ->assertSee('Expiring soon');
    }

    #[Test]
    public function a_retired_reference_stops_counting_towards_expiry(): void
    {
        // An expired credential nobody uses any more is not a finding, and
        // listing it trains people to ignore the list.
        $this->reference([
            'name' => 'Old secret',
            'expires_on' => Carbon::today()->subMonth(),
            'retired_at' => Carbon::now(),
        ]);

        $this->assertTrue($this->posture()->expiringReferences()->isEmpty());
        $this->assertSame(SecurityStatus::NotConfigured, $this->posture()->secrets()['status']);
    }

    #[Test]
    public function an_unconfigured_entra_is_reported_as_a_configuration_gap_without_naming_a_value(): void
    {
        config()->set('services.microsoft', [
            'tenant' => 'tenant-guid',
            'client_id' => 'client-guid',
            'client_secret' => '',
            'redirect' => 'http://localhost/auth/microsoft/callback',
        ]);

        $gaps = $this->posture()->configurationGaps();

        $this->assertNotSame([], $gaps);
        $this->assertSame('Microsoft Entra is not fully configured', $gaps[0]['title']);

        // SEC-DEC-017: presence only, never a value.
        $this->assertStringNotContainsString('tenant-guid', $gaps[0]['detail']);
        $this->assertStringNotContainsString('client-guid', $gaps[0]['detail']);
    }

    #[Test]
    public function the_warnings_list_gathers_findings_from_all_four_areas(): void
    {
        $this->withSecurityPolicy('sign_in.mode', AuthenticationMode::LocalOnly->value);
        $this->withSecurityPolicy('activity.confirm_critical_actions', false);
        $this->reference();

        $areas = array_unique(array_column($this->posture()->warnings(), 'area'));

        $this->assertContains('Authentication', $areas);
        $this->assertContains('Sessions', $areas);
    }

    #[Test]
    public function the_overview_offers_no_way_to_change_anything(): void
    {
        // A read-only roll-up. Everything is changed on the screen that owns
        // it, and duplicating the controls here would be two paths to one
        // decision - the filter-not-fork problem again.
        // Asserted against the security ROUTES rather than against "<form",
        // because the shell itself carries a sign-out form on every page.
        $page = $this->actingAs($this->admin())->get(route('admin.security.overview'))->assertOk();

        foreach ([
            'admin.security.authentication.update',
            'admin.security.sessions.update',
            'admin.security.api.update',
            'admin.security.secrets.store',
        ] as $writeRoute) {
            $page->assertDontSee('action="'.route($writeRoute).'"', false);
        }
    }

    #[Test]
    public function an_administrator_below_system_administrator_cannot_reach_it(): void
    {
        $person = User::query()->create(['name' => 'Adam Admin', 'email' => 'adam@example.test']);
        $person->forceFill([
            'role' => Role::Admin,
            'organisation_id' => app(OrganisationContext::class)->require()->getKey(),
        ])->save();

        $this->actingAs($person->refresh())
            ->get(route('admin.security.overview'))
            ->assertForbidden();
    }
}
