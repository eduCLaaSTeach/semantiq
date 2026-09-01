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
 *
 * TWO ORDERINGS, DELIBERATELY DIFFERENT
 *
 * The case order below is the NAVIGATION order the sidebar renders - Workplace,
 * then Fabric Configuration, then System Administration - because
 * NavigationRegistry::visibleFor() iterates cases() in declaration order.
 *
 * deliveryPhase() is the separate and unchanged question of which phase OWNS an
 * area's screens: System Administration is Phase 1, Fabric Configuration Phase
 * 2, SemantIQ Workplace Phase 3.
 *
 * Reading case order as phase order is the mistake this comment exists to
 * prevent, and ProductAreaOrderTest asserts both orderings independently so the
 * two cannot drift into each other.
 *
 * D-23 (Product Owner, 31 August 2026) set the navigation order. It is a
 * presentation change only: no area's meaning, ownership or delivery phase
 * moved with it. See the blueprint, section 2.4a.
 */
enum ProductArea: string
{
    // Navigation order. See the note above before reordering these.
    case SemantiqWorkplace = 'semantiq-workplace';
    case FabricConfiguration = 'fabric-configuration';
    case SystemAdministration = 'system-administration';

    public function label(): string
    {
        return match ($this) {
            self::SemantiqWorkplace => 'SemantIQ Workplace',
            self::FabricConfiguration => 'Fabric Configuration',
            self::SystemAdministration => 'System Administration',
        };
    }

    /**
     * The delivery phase that owns this area's screens.
     *
     * Recorded so that a node registered against an area whose phase has not
     * been delivered is a visible contradiction rather than a quiet one.
     *
     * UNCHANGED by the D-23 navigation reorder, and deliberately not derived
     * from case position.
     */
    public function deliveryPhase(): int
    {
        return match ($this) {
            self::SystemAdministration => 1,
            self::FabricConfiguration => 2,
            self::SemantiqWorkplace => 3,
        };
    }

    /**
     * Whether this area's cluster starts expanded in the sidebar.
     *
     * D-23: System Administration is expanded because Organisation is the only
     * delivered capability, so the one working area is open on arrival rather
     * than behind two collapsed sections of unavailable features. The other two
     * start collapsed.
     *
     * This is a default, not a lock: it is the starting state, and a person may
     * open or close any cluster.
     */
    public function expandedByDefault(): bool
    {
        return $this === self::SystemAdministration;
    }
}
