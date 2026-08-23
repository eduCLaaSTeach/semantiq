<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Enums\Role;
use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Platform\Enums\SettingType;
use App\Modules\Platform\Models\SystemSetting;
use App\Modules\Platform\Support\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ADM-021 System Configuration.
 *
 * Three things are being guarded: that the catalogue is the only source of
 * truth about what a setting is, that no credential can be stored through this
 * path, and that every change lands in the audit trail.
 */
class SystemConfigurationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::query()->create(['name' => 'Ada Admin', 'email' => 'ada@example.test']);
        $user->forceFill(['role' => Role::SystemAdmin])->save();

        return $user->refresh();
    }

    private function personOn(Role $role): User
    {
        $user = User::query()->create(['name' => 'Test Person', 'email' => uniqid().'@example.test']);
        $user->forceFill(['role' => $role])->save();

        return $user->refresh();
    }

    #[Test]
    public function an_unset_setting_reads_as_its_catalogue_default(): void
    {
        // No seeder runs in production, so the default has to come from the
        // catalogue rather than from a row somebody remembered to insert.
        $this->assertSame(0, SystemSetting::query()->count());
        $this->assertSame(25, app(SystemSettings::class)->get('app.pagination_default'));
        $this->assertSame('UTC', app(SystemSettings::class)->get('app.default_time_zone'));
    }

    #[Test]
    public function an_unknown_key_throws_rather_than_reading_as_null(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // A typo that read as null would be a feature silently taking its
        // fallback path, which is far harder to notice than an exception.
        app(SystemSettings::class)->get('app.does_not_exist');
    }

    #[Test]
    public function the_general_screen_renders_the_catalogue(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/system/settings/general')
            ->assertOk()
            ->assertSee('General Settings')
            ->assertSee('Application display name')
            ->assertSee('Rows per page')
            // Environment settings belong to the other screen and must not leak
            // onto this one: the category is a route segment, not a filter.
            ->assertDontSee('Maintenance notice text');
    }

    #[Test]
    public function the_time_zone_field_is_offered_as_a_list_rather_than_free_text(): void
    {
        // The change that prompted this: free text accepted the right answer
        // and rejected a typo with no hint at the wanted spelling.
        $this->actingAs($this->admin())
            ->get('/admin/system/settings/general')
            ->assertOk()
            ->assertSee('Singapore (UTC+08:00)')
            ->assertSee('<optgroup label="Asia">', false)
            // UTC is the default and leads the list.
            ->assertSee('<optgroup label="Coordinated Universal Time">', false);
    }

    #[Test]
    public function a_time_zone_that_php_does_not_recognise_is_still_refused(): void
    {
        // A select removes the typo; it does not remove the crafted post, so
        // the rule stays.
        $this->actingAs($this->admin())
            ->from('/admin/system/settings/general')
            ->put('/admin/system/settings/general', [
                'settings' => [
                    'app__display_name' => 'SemantIQ',
                    'app__support_contact' => '',
                    'app__default_locale' => 'en',
                    'app__default_time_zone' => 'Mars/Olympus',
                    'app__pagination_default' => '25',
                    'notifications__default_channel' => 'none',
                ],
            ])
            ->assertSessionHasErrors('settings.app__default_time_zone');
    }

    #[Test]
    public function a_category_that_does_not_exist_is_a_404(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/system/settings/invented')
            ->assertNotFound();
    }

    #[Test]
    public function saving_a_change_stores_it_and_audits_it(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->put('/admin/system/settings/general', [
                'settings' => [
                    'app__display_name' => 'Acme Intelligence',
                    'app__support_contact' => 'support@example.test',
                    'app__default_locale' => 'en',
                    'app__default_time_zone' => 'Asia/Singapore',
                    'app__pagination_default' => '50',
                    'notifications__default_channel' => 'none',
                ],
            ])
            ->assertRedirect('/admin/system/settings/general');

        app(SystemSettings::class)->flush();

        $this->assertSame('Acme Intelligence', app(SystemSettings::class)->get('app.display_name'));
        $this->assertSame(50, app(SystemSettings::class)->get('app.pagination_default'));

        $event = AuditEvent::query()->where('resource_id', 'app.display_name')->firstOrFail();

        $this->assertSame('system.setting.updated', $event->action);
        $this->assertSame($admin->id, $event->actor_user_id);
        // Both sides recorded: "it changed" is not an answer without them.
        $this->assertSame(['value' => 'SemantIQ'], $event->before_summary);
        $this->assertSame(['value' => 'Acme Intelligence'], $event->after_summary);
    }

    #[Test]
    public function saving_the_same_value_writes_no_audit_event(): void
    {
        $this->actingAs($this->admin())
            ->put('/admin/system/settings/general', [
                'settings' => [
                    'app__display_name' => 'SemantIQ',
                    'app__support_contact' => '',
                    'app__default_locale' => 'en',
                    'app__default_time_zone' => 'UTC',
                    'app__pagination_default' => '25',
                    'notifications__default_channel' => 'none',
                ],
            ]);

        // A trail full of non-changes is a trail nobody reads.
        $this->assertSame(0, AuditEvent::query()->count());
    }

    #[Test]
    public function a_value_outside_the_catalogue_rules_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->from('/admin/system/settings/general')
            ->put('/admin/system/settings/general', [
                'settings' => [
                    'app__display_name' => '',
                    'app__support_contact' => 'not-an-address',
                    'app__default_locale' => 'klingon',
                    'app__default_time_zone' => 'Mars/Olympus',
                    // An unbounded page size is a cheap way to ask the database
                    // for a whole table.
                    'app__pagination_default' => '100000',
                    'notifications__default_channel' => 'carrier-pigeon',
                ],
            ])
            ->assertRedirect('/admin/system/settings/general')
            ->assertSessionHasErrors([
                'settings.app__display_name',
                'settings.app__support_contact',
                'settings.app__default_locale',
                'settings.app__default_time_zone',
                'settings.app__pagination_default',
                'settings.notifications__default_channel',
            ]);

        $this->assertSame(0, SystemSetting::query()->count());
    }

    #[Test]
    public function a_field_from_another_category_is_ignored_rather_than_applied(): void
    {
        $this->actingAs($this->admin())
            ->put('/admin/system/settings/general', [
                'settings' => [
                    'app__display_name' => 'SemantIQ',
                    'app__support_contact' => '',
                    'app__default_locale' => 'en',
                    'app__default_time_zone' => 'UTC',
                    'app__pagination_default' => '25',
                    'notifications__default_channel' => 'none',
                    // Posted by a crafted request against a screen that never
                    // offered it. It belongs to the environment category.
                    'environment__maintenance_mode' => '1',
                ],
            ]);

        app(SystemSettings::class)->flush();

        $this->assertFalse(app(SystemSettings::class)->get('environment.maintenance_mode'));
        $this->assertSame(0, SystemSetting::query()->where('key', 'environment.maintenance_mode')->count());
    }

    #[Test]
    public function an_unchecked_box_stores_false_rather_than_failing_validation(): void
    {
        $admin = $this->admin();
        $settings = app(SystemSettings::class);
        $settings->set('environment.maintenance_mode', true, $admin);
        $settings->flush();
        $this->assertTrue($settings->get('environment.maintenance_mode'));

        // An unchecked checkbox posts nothing at all. Absence has to mean off,
        // or no switch could ever be turned back off.
        $this->actingAs($admin)
            ->put('/admin/system/settings/environment', [
                'settings' => [
                    'environment__label' => '',
                    'environment__maintenance_message' => '',
                ],
            ])
            ->assertSessionHasNoErrors();

        app(SystemSettings::class)->flush();

        $this->assertFalse(app(SystemSettings::class)->get('environment.maintenance_mode'));
    }

    #[Test]
    public function a_secret_bearing_key_can_never_be_written(): void
    {
        // Declared as an ordinary setting on purpose: the guard must hold even
        // when the catalogue itself has been edited carelessly.
        config()->set('platform.settings.smtp.password', [
            'category' => 'general',
            'type' => SettingType::Text,
            'default' => '',
            'label' => 'SMTP password',
            'help' => 'Should never be storable here.',
            'rules' => ['nullable', 'string'],
            'editable' => Role::SystemAdmin,
            'audited' => true,
            'approval' => false,
            'sensitive' => false,
        ]);

        $this->expectException(InvalidArgumentException::class);

        app(SystemSettings::class)->set('smtp.password', 'hunter2', $this->admin());
    }

    #[Test]
    public function an_actor_below_the_editing_tier_is_refused_and_the_refusal_is_audited(): void
    {
        $viewer = $this->personOn(Role::Viewer);

        try {
            app(SystemSettings::class)->set('app.display_name', 'Nope', $viewer);
            $this->fail('A Viewer was allowed to change a system setting.');
        } catch (InvalidArgumentException) {
            // Expected. The tier is checked in the writer as well as on the
            // route, because console and queue callers reach neither.
        }

        $this->assertSame(0, SystemSetting::query()->count());

        $event = AuditEvent::withoutOrganisationScope()->where('action', 'system.setting.updated')->firstOrFail();
        $this->assertSame('denied', $event->outcome->value);
    }

    #[Test]
    public function the_screens_stay_behind_the_system_administration_boundary(): void
    {
        $this->actingAs($this->personOn(Role::Admin))->get('/admin/system/settings/general')->assertForbidden();
        $this->actingAs($this->personOn(Role::Admin))->put('/admin/system/settings/general', [])->assertForbidden();
    }

    #[Test]
    public function a_guest_is_sent_to_sign_in_rather_than_forbidden(): void
    {
        $this->get('/admin/system/settings/general')->assertRedirect('/sign-in');
    }
}
