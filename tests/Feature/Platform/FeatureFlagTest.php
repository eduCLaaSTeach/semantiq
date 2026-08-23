<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Enums\Role;
use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Platform\Models\FeatureFlag;
use App\Modules\Platform\Support\FeatureFlags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ADM-021 feature flags.
 *
 * The two behaviours worth guarding hardest are the ones a reasonable person
 * would get wrong: an undeclared flag must read as OFF, and a switch that is
 * safe in one direction only must refuse the unsafe one.
 */
class FeatureFlagTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::query()->create(['name' => 'Ada Admin', 'email' => 'ada@example.test']);
        $user->forceFill(['role' => Role::SystemAdmin])->save();

        return $user->refresh();
    }

    private function configureMicrosoftSignIn(): void
    {
        config()->set('services.microsoft.tenant', 'a-tenant');
        config()->set('services.microsoft.client_id', 'an-application');
        config()->set('services.microsoft.client_secret', 'a-secret');
        config()->set('services.microsoft.redirect', 'https://example.test/callback');
    }

    #[Test]
    public function a_flag_with_no_row_reads_as_its_declared_default(): void
    {
        $this->assertSame(0, FeatureFlag::query()->count());
        $this->assertTrue(app(FeatureFlags::class)->enabled('identity.local_sign_in'));
        $this->assertFalse(app(FeatureFlags::class)->enabled('platform.extended_diagnostics'));
    }

    #[Test]
    public function an_undeclared_flag_is_off_rather_than_an_error(): void
    {
        // Reading a missing switch is a degraded state, so it degrades to
        // "capability unavailable" rather than to a 500. Deleting a declaration
        // must never turn a capability on.
        $this->assertFalse(app(FeatureFlags::class)->enabled('something.nobody.declared'));
    }

    #[Test]
    public function an_undeclared_flag_cannot_be_written(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // Creating one IS a mistake, even though reading one is not: a row for
        // an undeclared key would then be read by whatever name it was given.
        app(FeatureFlags::class)->set('something.nobody.declared', true, $this->admin());
    }

    #[Test]
    public function toggling_a_flag_records_the_change_and_the_reason(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->put('/admin/system/feature-flags/platform.extended_diagnostics', [
                'enabled' => '1',
                'reason' => 'Investigating a queue problem',
            ])
            ->assertRedirect('/admin/system/feature-flags');

        app(FeatureFlags::class)->flush();

        $this->assertTrue(app(FeatureFlags::class)->enabled('platform.extended_diagnostics'));

        $event = AuditEvent::query()->where('action', 'feature_flag.toggled')->firstOrFail();

        $this->assertSame('platform.extended_diagnostics', $event->resource_id);
        $this->assertSame(['enabled' => false], $event->before_summary);
        $this->assertSame(['enabled' => true], $event->after_summary);
        $this->assertSame('Investigating a queue problem', $event->reason);
    }

    #[Test]
    public function a_switch_that_is_safe_in_one_direction_only_refuses_the_other(): void
    {
        // Microsoft sign-in is not configured here, so turning off the local
        // form would leave nobody a way in.
        config()->set('services.microsoft.client_id', null);

        $this->actingAs($this->admin())
            ->from('/admin/system/feature-flags')
            ->put('/admin/system/feature-flags/identity.local_sign_in', ['enabled' => '0'])
            ->assertRedirect('/admin/system/feature-flags')
            ->assertSessionHasErrors('flag');

        app(FeatureFlags::class)->flush();

        $this->assertTrue(app(FeatureFlags::class)->enabled('identity.local_sign_in'));

        // The refusal is evidence too.
        $denied = AuditEvent::query()->where('action', 'feature_flag.toggled')->firstOrFail();
        $this->assertSame('denied', $denied->outcome->value);
    }

    #[Test]
    public function the_same_switch_is_allowed_once_its_precondition_holds(): void
    {
        $this->configureMicrosoftSignIn();

        $this->actingAs($this->admin())
            ->put('/admin/system/feature-flags/identity.local_sign_in', ['enabled' => '0'])
            ->assertSessionHasNoErrors();

        app(FeatureFlags::class)->flush();

        $this->assertFalse(app(FeatureFlags::class)->enabled('identity.local_sign_in'));
    }

    #[Test]
    public function an_unknown_flag_key_in_the_url_is_refused_before_the_body_is_read(): void
    {
        $this->actingAs($this->admin())
            ->from('/admin/system/feature-flags')
            ->put('/admin/system/feature-flags/invented.flag', ['enabled' => '1'])
            ->assertRedirect('/admin/system/feature-flags')
            ->assertSessionHasErrors('flag');

        $this->assertSame(0, FeatureFlag::query()->count());
    }

    #[Test]
    public function the_screen_says_a_flag_is_not_an_access_control(): void
    {
        // Load-bearing text, not decoration: a switch labelled "sign-in"
        // invites exactly the wrong reading.
        $this->actingAs($this->admin())
            ->get('/admin/system/feature-flags')
            ->assertOk()
            ->assertSee('It never decides who may')
            ->assertSee('Local password sign-in');
    }

    #[Test]
    public function the_screen_stays_behind_the_system_administration_boundary(): void
    {
        $person = User::query()->create(['name' => 'Ann Admin', 'email' => 'ann@example.test']);
        $person->forceFill(['role' => Role::Admin])->save();

        $this->actingAs($person->refresh())->get('/admin/system/feature-flags')->assertForbidden();
    }

    #[Test]
    public function a_guest_is_sent_to_sign_in_rather_than_forbidden(): void
    {
        $this->get('/admin/system/feature-flags')->assertRedirect('/sign-in');
    }
}
