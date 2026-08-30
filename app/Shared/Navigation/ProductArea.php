<?php

declare(strict_types=1);

namespace App\Shared\Navigation;

/**
 * The three SemantIQ product areas.
 *
 * Approved as D-02: SemantIQ uses its own information architecture rather than
 * the shared design system's four generic clusters. The design system continues
 * to govern presentation; it does not govern this.
 *
 * Audit, Access Reviews and Security Status belong inside System Administration.
 * There is deliberately no top-level Compliance area.
 */
enum ProductArea: string
{
    case SystemAdministration = 'system-administration';
    case FabricConfiguration = 'fabric-configuration';
    case SemantiqWorkplace = 'semantiq-workplace';

    public function label(): string
    {
        return match ($this) {
            self::SystemAdministration => 'System Administration',
            self::FabricConfiguration => 'Fabric Configuration',
            self::SemantiqWorkplace => 'SemantIQ Workplace',
        };
    }

    /**
     * The delivery phase that owns this area's screens.
     *
     * Recorded so that a node registered against an area whose phase has not
     * been delivered is a visible contradiction rather than a quiet one.
     */
    public function deliveryPhase(): int
    {
        return match ($this) {
            self::SystemAdministration => 1,
            self::FabricConfiguration => 2,
            self::SemantiqWorkplace => 3,
        };
    }
}
