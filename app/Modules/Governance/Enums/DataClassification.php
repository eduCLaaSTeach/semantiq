<?php

declare(strict_types=1);

namespace App\Modules\Governance\Enums;

/**
 * How sensitive a category of personal data is. Feature ADM-014.
 *
 * A codified list rather than free text, because CLAUDE.md's schema rules
 * require codified reference lists and because a classification an
 * administrator can invent is a classification no policy can act on.
 *
 * The order is deliberate and is relied upon by `atLeast()`: each case is more
 * sensitive than the one before it. A later gate that needs "anything at
 * Confidential or above" asks here rather than listing cases at the call site.
 */
enum DataClassification: string
{
    case Public = 'public';
    case Internal = 'internal';
    case Confidential = 'confidential';
    case Restricted = 'restricted';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Public',
            self::Internal => 'Internal',
            self::Confidential => 'Confidential',
            self::Restricted => 'Restricted',
        };
    }

    /**
     * What this classification means, in the words the screen shows.
     *
     * Read from the catalogue rather than duplicated here, so the enum and the
     * help text cannot drift apart.
     */
    public function description(): string
    {
        return (string) config('governance.classifications.'.$this->value, '');
    }

    /**
     * Rank, low to high. Only for comparison; never stored.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Public => 1,
            self::Internal => 2,
            self::Confidential => 3,
            self::Restricted => 4,
        };
    }

    public function atLeast(self $other): bool
    {
        return $this->rank() >= $other->rank();
    }

    /**
     * The design system's badge class for this classification.
     *
     * Returns the WHOLE class rather than a fragment a view concatenates, so a
     * value that has no badge variant fails to compile here rather than
     * rendering as an unstyled pill nobody notices.
     *
     * RESTRICTED IS NOT RENDERED AS AN ERROR. `badge-danger` is the error
     * palette, and a correctly classified restricted category is the system
     * working, not something wrong. It uses the violet emphasis instead, so the
     * four classifications read as increasing seriousness rather than as three
     * states and one fault.
     */
    public function badge(): string
    {
        return match ($this) {
            /* The bare class IS the neutral variant in the design system. */
            self::Public => 'badge',
            self::Internal => 'badge badge-info',
            self::Confidential => 'badge badge-warning',
            self::Restricted => 'badge badge-violet',
        };
    }
}
