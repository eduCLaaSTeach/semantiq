<?php

declare(strict_types=1);

namespace App\Modules\Identity\Support;

/**
 * How much damage a permission can do. Feature ADM-007.
 *
 * Shown wherever a permission is assigned, so that granting the ability to
 * approve a sovereignty exception does not look identical to granting the
 * ability to read a list. An administrator ticking forty checkboxes reads none
 * of them; a red pill on three of them gets read.
 *
 * This is presentation and review guidance. It never affects whether a
 * permission is granted - that is the tier and the grant, and nothing else.
 */
enum PermissionRisk: string
{
    case Normal = 'normal';
    case Elevated = 'elevated';
    case High = 'high';

    public function label(): string
    {
        return match ($this) {
            self::Normal => 'Normal',
            self::Elevated => 'Elevated',
            self::High => 'High',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Normal => 'badge',
            self::Elevated => 'badge badge-warning',
            self::High => 'badge badge-danger',
        };
    }
}
