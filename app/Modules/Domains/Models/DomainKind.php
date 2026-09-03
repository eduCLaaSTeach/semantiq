<?php

declare(strict_types=1);

namespace App\Modules\Domains\Models;

/**
 * Where a domain came from, and it is never convertible.
 *
 * Baseline domains are SemantIQ's vocabulary - the seven that ship with the
 * product. Custom domains are the organisation's own. The two are the same
 * object with a different origin, which is why they share one screen (DESIGN
 * §2) and why `kind` is set once and never read from a request afterwards.
 *
 * "Custom Domains" in the source scope names the CAPABILITY to add your own,
 * not an eighth baseline entry - D-44.
 */
enum DomainKind: string
{
    case Baseline = 'baseline';
    case Custom = 'custom';

    public function isBaseline(): bool
    {
        return $this === self::Baseline;
    }

    public function label(): string
    {
        return $this === self::Baseline ? 'Baseline' : 'Custom';
    }
}
