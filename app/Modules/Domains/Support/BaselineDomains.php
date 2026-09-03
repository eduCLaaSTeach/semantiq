<?php

declare(strict_types=1);

namespace App\Modules\Domains\Support;

/**
 * The seven baseline domains, and the set is closed - D-44.
 *
 * This is PRODUCT VOCABULARY, not the organisation's data. That distinction is
 * the whole justification for creating these rows at all under the standing
 * rule against seeding business data: SemantIQ supplies the words, and the
 * organisation supplies which of them it enables, what it calls them, and who
 * owns them. Every one of those starts empty.
 *
 * THIS IS NOT A SOURCE OF TRUTH. D-46 explicitly rejected "a static catalogue
 * in code, with rows created on first use". The catalogue is INPUT to one
 * explicit write (BaselineDomainInitialiser); the table is the source of truth
 * from the first moment. No screen merges this list with the database, and no
 * read path creates anything from it.
 *
 * The codes are also the RESERVED set: a custom domain may not take one, even
 * in a deployment where that baseline domain is disabled or absent. The check
 * runs against this constant rather than against the rows present, which is
 * what makes it hold in both of those cases.
 */
final class BaselineDomains
{
    /**
     * Code => display name. The code is permanent; the name is a starting point
     * the organisation may change (D-41).
     *
     * @var array<string, string>
     */
    public const CATALOGUE = [
        'executive' => 'Executive',
        'sales' => 'Sales',
        'finance' => 'Finance',
        'people' => 'People',
        'operations' => 'Operations',
        'customer' => 'Customer',
        'learning' => 'Learning',
    ];

    /** @return list<string> */
    public static function codes(): array
    {
        return array_keys(self::CATALOGUE);
    }

    public static function isReserved(string $code): bool
    {
        return array_key_exists(mb_strtolower(trim($code)), self::CATALOGUE);
    }
}
