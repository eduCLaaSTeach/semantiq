<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\Role;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The platform tier ladder.
 *
 * A unit test, deliberately: this is pure logic with no database, and it runs
 * in microseconds. The tier ORDER is the thing worth pinning, because every
 * access decision in the product is a comparison against it, and reordering the
 * cases by accident would change what a stored value means.
 */
class RoleTest extends TestCase
{
    #[Test]
    public function the_six_tiers_are_in_the_documented_order(): void
    {
        $this->assertSame(
            ['system_admin', 'admin', 'domain_owner', 'analyst', 'contributor', 'viewer'],
            array_map(fn (Role $r): string => $r->value, Role::cases()),
        );
    }

    #[Test]
    public function the_backing_values_are_stable(): void
    {
        // These are in the database. Changing one is a data migration, not a
        // rename, and this is where that gets noticed.
        $this->assertSame('domain_owner', Role::DomainOwner->value);
        $this->assertSame('analyst', Role::Analyst->value);
    }

    #[Test]
    public function ranks_are_strictly_descending_through_the_ladder(): void
    {
        $ranks = array_map(fn (Role $r): int => $r->rank(), Role::cases());

        $this->assertSame($ranks, array_values(array_unique($ranks)), 'Two tiers share a rank');

        $sorted = $ranks;
        rsort($sorted);
        $this->assertSame($sorted, $ranks, 'The cases are not ordered highest authority first');
    }

    #[Test]
    public function tiers_are_cumulative(): void
    {
        foreach (Role::cases() as $higher) {
            foreach (Role::cases() as $lower) {
                $this->assertSame(
                    $higher->rank() >= $lower->rank(),
                    $higher->atLeast($lower),
                    $higher->value.' vs '.$lower->value,
                );
            }
        }
    }

    #[Test]
    public function a_new_account_starts_on_the_lowest_tier(): void
    {
        $this->assertSame(Role::Viewer, Role::default());
        $this->assertSame(
            min(array_map(fn (Role $r): int => $r->rank(), Role::cases())),
            Role::default()->rank(),
        );
    }

    #[Test]
    public function only_the_viewer_tier_is_read_only(): void
    {
        $readOnly = array_values(array_filter(Role::cases(), fn (Role $r): bool => $r->isReadOnly()));

        $this->assertSame([Role::Viewer], $readOnly);
    }

    #[Test]
    public function every_tier_carries_a_label_and_a_purpose(): void
    {
        foreach (Role::cases() as $role) {
            $this->assertNotSame('', $role->label(), $role->value.' has no label');
            $this->assertNotSame('', $role->purpose(), $role->value.' has no purpose');
        }
    }
}
