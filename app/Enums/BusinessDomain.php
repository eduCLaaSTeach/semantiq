<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * A business intelligence domain, from doc/ROLE_MODEL.md section 3.
 *
 * The second dimension of access. A platform role says what someone may DO;
 * a domain entitlement says which business information they may do it TO.
 * Neither implies the other, which is the whole point: a System Administrator
 * configures the platform without thereby reading the payroll.
 *
 * "Custom" is in the baseline list as an extension point. It is deliberately
 * not modelled here yet, because a custom domain needs a name, an owner and an
 * entitlement story of its own, and inventing that shape before a customer asks
 * for one would fix decisions nobody has taken.
 */
enum BusinessDomain: string
{
    case Executive = 'executive';
    case Sales = 'sales';
    case Finance = 'finance';
    case People = 'people';
    case Operations = 'operations';
    case Customer = 'customer';
    case Learning = 'learning';

    public function label(): string
    {
        return match ($this) {
            self::Executive => 'Executive Intelligence',
            self::Sales => 'Sales Intelligence',
            self::Finance => 'Finance Intelligence',
            self::People => 'People Intelligence',
            self::Operations => 'Operations Intelligence',
            self::Customer => 'Customer Intelligence',
            self::Learning => 'Learning Intelligence',
        };
    }

    /**
     * A short business description, for the My Intelligence domain cards.
     */
    public function description(): string
    {
        return match ($this) {
            self::Executive => 'Enterprise performance across every function.',
            self::Sales => 'Revenue, pipeline, customers and forecast.',
            self::Finance => 'Profitability, cash flow and budget performance.',
            self::People => 'Workforce, attrition, skills and cost.',
            self::Operations => 'Service levels, throughput and capacity.',
            self::Customer => 'Retention, engagement and growth opportunities.',
            self::Learning => 'Enrolment, progress, completion and outcomes.',
        };
    }

    /**
     * Whether this domain carries data needing stricter field, purpose and role
     * controls than ordinary business metrics.
     *
     * People is named in MENU_STRUCTURE.md section 5.4 for exactly this. It is
     * recorded on the domain rather than left to each screen, so a later screen
     * cannot forget which domains are sensitive.
     */
    public function isSensitive(): bool
    {
        return match ($this) {
            self::People, self::Finance => true,
            default => false,
        };
    }

    /**
     * The route segment for /intelligence/{domain}.
     */
    public function slug(): string
    {
        return $this->value;
    }
}
