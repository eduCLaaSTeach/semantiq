<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\BusinessDomain;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The business domains from doc/ROLE_MODEL.md section 3.
 */
class BusinessDomainTest extends TestCase
{
    #[Test]
    public function the_seven_baseline_domains_are_present(): void
    {
        $this->assertSame(
            ['executive', 'sales', 'finance', 'people', 'operations', 'customer', 'learning'],
            array_map(fn (BusinessDomain $d): string => $d->value, BusinessDomain::cases()),
        );
    }

    #[Test]
    public function every_domain_carries_a_label_a_description_and_a_slug(): void
    {
        foreach (BusinessDomain::cases() as $domain) {
            $this->assertNotSame('', $domain->label());
            $this->assertNotSame('', $domain->description());
            $this->assertSame($domain->value, $domain->slug());
        }
    }

    #[Test]
    public function the_domains_carrying_restricted_fields_are_named_on_the_domain(): void
    {
        // Recorded here rather than left to each screen to remember which ones
        // hold data needing stricter field and purpose controls.
        $sensitive = array_values(array_filter(
            BusinessDomain::cases(),
            fn (BusinessDomain $d): bool => $d->isSensitive(),
        ));

        $this->assertSame([BusinessDomain::Finance, BusinessDomain::People], $sensitive);
    }
}
