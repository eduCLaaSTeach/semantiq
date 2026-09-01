<?php

declare(strict_types=1);

namespace Tests\Unit\Navigation;

use App\Shared\Navigation\ProductArea;
use PHPUnit\Framework\TestCase;

/**
 * D-23: the navigation order and the delivery-phase order are different, and
 * both are asserted here so neither can quietly become the other.
 *
 * Before D-23 no document stated a navigation order at all. The sidebar simply
 * rendered ProductArea::cases() in declaration order, and that declaration
 * order happened to match the phase numbering - so the two looked like one
 * thing. They are not, and this test is what keeps them apart.
 */
final class ProductAreaOrderTest extends TestCase
{
    /**
     * 1. The navigation order the sidebar renders.
     *
     * Mutation: reorder the enum cases.
     */
    public function test_the_navigation_order_is_workplace_then_fabric_then_administration(): void
    {
        $this->assertSame(
            ['SemantIQ Workplace', 'Fabric Configuration', 'System Administration'],
            array_map(
                static fn (ProductArea $area): string => $area->label(),
                ProductArea::cases()
            ),
            'The sidebar renders ProductArea::cases() in declaration order, so this IS the '
            .'navigation order. D-23 fixes it as Workplace, Fabric Configuration, System '
            .'Administration.'
        );
    }

    /**
     * 5. Reordering navigation changed no phase boundary.
     *
     * Mutation: derive deliveryPhase() from case position, or renumber it.
     */
    public function test_delivery_phase_ownership_is_unchanged_by_the_navigation_order(): void
    {
        $this->assertSame(1, ProductArea::SystemAdministration->deliveryPhase());
        $this->assertSame(2, ProductArea::FabricConfiguration->deliveryPhase());
        $this->assertSame(3, ProductArea::SemantiqWorkplace->deliveryPhase());
    }

    /**
     * The two orderings must not coincide.
     *
     * If a future change made navigation order equal phase order again, the
     * distinction this test exists to protect would have quietly collapsed -
     * and the next person would have no way to tell which one they were
     * looking at.
     */
    public function test_the_two_orderings_are_genuinely_different(): void
    {
        $navigation = array_map(
            static fn (ProductArea $area): int => $area->deliveryPhase(),
            ProductArea::cases()
        );

        $this->assertNotSame(
            [1, 2, 3],
            $navigation,
            'Navigation order has become identical to phase order. D-23 made them different on '
            .'purpose; if the Product Owner has changed that, update D-23 and this test together.'
        );

        $this->assertSame([3, 2, 1], $navigation);
    }

    /**
     * 3. System Administration stays THIRD while still opening expanded.
     *
     * Position and default expansion are independent. Conflating them is how a
     * cluster would get promoted to first just to make it open.
     *
     * Mutation: make expandedByDefault() depend on position, or expand a second
     * area.
     */
    public function test_system_administration_is_third_and_still_expanded_by_default(): void
    {
        $cases = ProductArea::cases();

        $this->assertSame(
            ProductArea::SystemAdministration,
            $cases[2],
            'System Administration must render third under D-23.'
        );

        $this->assertTrue(
            ProductArea::SystemAdministration->expandedByDefault(),
            'System Administration holds the only delivered capability, so it opens expanded.'
        );

        $expanded = array_values(array_filter(
            $cases,
            static fn (ProductArea $area): bool => $area->expandedByDefault()
        ));

        $this->assertSame(
            [ProductArea::SystemAdministration],
            $expanded,
            'Exactly one cluster starts expanded. Workplace and Fabric Configuration hold no '
            .'delivered capability, so opening them would show only unavailable features.'
        );
    }

    /** The closed set is unchanged: three areas, no fourth. */
    public function test_the_product_areas_are_unchanged(): void
    {
        $this->assertCount(3, ProductArea::cases());

        $this->assertEqualsCanonicalizing(
            ['semantiq-workplace', 'fabric-configuration', 'system-administration'],
            array_map(static fn (ProductArea $a): string => $a->value, ProductArea::cases()),
            'An area was added, removed or renamed. D-23 was a reorder and nothing else.'
        );
    }
}
