<?php

declare(strict_types=1);

namespace Tests\Unit\Platform;

use App\Modules\Platform\Enums\HealthState;
use App\Modules\Platform\Enums\LifecycleStatus;
use App\Modules\Platform\Enums\SettingType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The status vocabulary and the two enums that render beside it.
 *
 * These are pure functions with no database, so they are unit tests. What they
 * are guarding is not the arithmetic but the CLOSED SETS: the six badge roles
 * the design system allows, and the five per-record vocabularies section 31
 * defines. Both are easy to widen by accident and hard to notice afterwards.
 */
class LifecycleStatusTest extends TestCase
{
    #[Test]
    public function every_status_maps_onto_one_of_the_six_badge_roles(): void
    {
        $allowed = ['badge', 'badge badge-success', 'badge badge-warning', 'badge badge-danger', 'badge badge-info', 'badge badge-violet'];

        foreach (LifecycleStatus::cases() as $status) {
            // The design system's badge set is closed. A seventh colour would
            // render as an unstyled pill rather than fail, so nothing but a
            // test catches it.
            $this->assertContains($status->badgeClass(), $allowed, $status->value.' invents a badge role');
            $this->assertNotSame('', $status->label());
        }
    }

    #[Test]
    public function the_vocabulary_covers_every_status_section_31_names(): void
    {
        $specified = [
            'invited', 'active', 'disabled', 'locked', 'expired',
            'draft', 'configured', 'connected', 'warning', 'error',
            'approved', 'superseded',
            'open', 'completed', 'cancelled',
            'pending', 'rejected', 'revoked',
        ];

        $shipped = array_map(fn (LifecycleStatus $s): string => $s->value, LifecycleStatus::cases());

        // Both directions. A missing value means a later gate invents its own;
        // an extra one means a state nobody specified became available.
        $this->assertSame(sort($specified) ? $specified : $specified, $specified);
        $this->assertEqualsCanonicalizing($specified, $shipped);
    }

    #[Test]
    public function a_vocabulary_rejects_a_status_from_another_record_family(): void
    {
        // The reason section 31 lists five vocabularies rather than one: an
        // organisation cannot be "connected", however valid that word is
        // elsewhere in the enum.
        $this->assertTrue(LifecycleStatus::isWithin('active', LifecycleStatus::forOrganisation()));
        $this->assertFalse(LifecycleStatus::isWithin('connected', LifecycleStatus::forOrganisation()));
        $this->assertFalse(LifecycleStatus::isWithin('not-a-status', LifecycleStatus::forOrganisation()));
        $this->assertFalse(LifecycleStatus::isWithin(null, LifecycleStatus::forOrganisation()));
    }

    #[Test]
    public function the_platform_is_as_healthy_as_its_worst_dependency(): void
    {
        $this->assertSame(HealthState::Critical, HealthState::worst([HealthState::Healthy, HealthState::Critical, HealthState::Warning]));
        $this->assertSame(HealthState::Warning, HealthState::worst([HealthState::Healthy, HealthState::Warning, HealthState::Unknown]));
        $this->assertSame(HealthState::Unknown, HealthState::worst([HealthState::Healthy, HealthState::Unknown]));
        $this->assertSame(HealthState::Healthy, HealthState::worst([HealthState::Healthy]));

        // No checks at all tells us nothing rather than tells us all is well.
        $this->assertSame(HealthState::Unknown, HealthState::worst([]));
    }

    #[Test]
    public function a_boolean_setting_only_accepts_the_two_forms_it_writes(): void
    {
        $this->assertTrue(SettingType::Boolean->cast('1'));
        $this->assertFalse(SettingType::Boolean->cast('0'));

        // The regression this guards: loose truthiness reads the string
        // "false" as true, which would turn a switch on by reading it.
        $this->assertNull(SettingType::Boolean->cast('maybe'));
        $this->assertSame('1', SettingType::Boolean->toStorage(true));
        $this->assertSame('0', SettingType::Boolean->toStorage(false));
    }

    #[Test]
    public function an_unparseable_value_falls_back_rather_than_reading_as_zero(): void
    {
        // Returning 0 would make "never set" indistinguishable from
        // "deliberately set to nothing", and the caller could not then apply
        // the catalogue default.
        $this->assertNull(SettingType::Integer->cast('not a number'));
        $this->assertNull(SettingType::Integer->cast(null));
        $this->assertSame(42, SettingType::Integer->cast('42'));
    }
}
