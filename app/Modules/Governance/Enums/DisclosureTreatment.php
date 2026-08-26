<?php

declare(strict_types=1);

namespace App\Modules\Governance\Enums;

/**
 * What is actually done with a collected item.
 *
 * `Describe` is the one that carries the design. It discloses the FACT without
 * the second person: "You granted a business-domain entitlement to another user
 * on 3 March 2026." The subject learns what they did; the other party is not
 * named. Decision D5.
 *
 * WIDENING IS THE DANGEROUS DIRECTION. `rank()` orders these by how much is
 * disclosed, and the service uses it to tell narrowing from widening: narrowing
 * is one reviewer's call, widening needs a second approver who is not the
 * first. A reviewer under time pressure can always be more careful alone; being
 * less careful should take two people.
 */
enum DisclosureTreatment: string
{
    case Exclude = 'exclude';
    case Describe = 'describe';
    case Include = 'include';

    public function label(): string
    {
        return match ($this) {
            self::Exclude => 'Not collected',
            self::Describe => 'Described',
            self::Include => 'Disclosed in full',
        };
    }

    public function explanation(): string
    {
        return match ($this) {
            self::Exclude => 'Not gathered at all. The subject is not told it exists, beyond a count where one is meaningful.',
            self::Describe => 'The fact is disclosed without naming anybody else involved.',
            self::Include => 'Disclosed verbatim.',
        };
    }

    /**
     * How much this treatment discloses. Higher is more.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Exclude => 0,
            self::Describe => 1,
            self::Include => 2,
        };
    }

    public function isWiderThan(self $other): bool
    {
        return $this->rank() > $other->rank();
    }

    public function badge(): string
    {
        return match ($this) {
            self::Exclude => 'badge-neutral',
            self::Describe => 'badge-info',
            self::Include => 'badge-success',
        };
    }
}
