<?php

declare(strict_types=1);

namespace App\Modules\Domains\Models;

/**
 * Whether this organisation is currently using this domain.
 *
 * AVAILABILITY AND READINESS, NOT AUTHORIZATION. There is no authorization in
 * P1-04 to describe: a disabled domain takes nothing away and an enabled one
 * grants nothing, because nothing anywhere reads this value to decide what a
 * person may see. DomainsBoundaryTest asserts that rather than trusting it.
 *
 * Two states and no third, for the reason GroupStatus already records: a
 * "deleted" case is a third state every query then has to remember to exclude.
 *
 * The words are enabled/disabled rather than active/inactive on purpose. A
 * USER is active or inactive; a DOMAIN is switched on or off by the
 * organisation. Sharing the vocabulary would invite the reader to assume the
 * two mean the same thing to the system, and they do not.
 */
enum DomainStatus: string
{
    case Enabled = 'enabled';
    case Disabled = 'disabled';

    public function isEnabled(): bool
    {
        return $this === self::Enabled;
    }

    public function label(): string
    {
        return $this === self::Enabled ? 'Enabled' : 'Disabled';
    }
}
