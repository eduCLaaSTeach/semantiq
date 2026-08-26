<?php

declare(strict_types=1);

namespace App\Modules\Governance\Enums;

/**
 * How a piece of personal data relates to the subject asking for it.
 *
 * The band decides the DEFAULT treatment, and the default is what makes this
 * safe: a reviewer working at speed does not have to reason about disclosure
 * from first principles for every row.
 *
 *   A  The subject's own record. Theirs alone; disclose it.
 *   B  A record ABOUT the subject. Theirs, but may reference others.
 *   C  The subject's name on SOMEBODY ELSE'S record. Two people's data.
 *   D  Free text that may name a person. Unbounded, so assume the worst.
 *
 * BAND C IS THE WHOLE DESIGN PROBLEM. "Alice approved Bob's Finance
 * entitlement" is personal data about Alice and about Bob. Releasing it
 * verbatim to Alice discloses Bob, who asked for nothing. Withholding it
 * entirely under-answers a lawful request. `Describe` is the resolution:
 * disclose the fact without the second person. Decision D5.
 */
enum DisclosureBand: string
{
    case A = 'A';
    case B = 'B';
    case C = 'C';
    case D = 'D';

    public function label(): string
    {
        return match ($this) {
            self::A => 'Band A - their own record',
            self::B => 'Band B - records about them',
            self::C => 'Band C - their name on another record',
            self::D => 'Band D - free text that may name a person',
        };
    }

    public function explanation(): string
    {
        return match ($this) {
            self::A => 'Held about this person and nobody else. Disclosed in full.',
            self::B => 'A record of something that happened to or about this person.',
            self::C => 'This person acted on a record belonging to somebody else. The action is disclosed; the other person is not named.',
            self::D => 'Text somebody typed, which may mention anybody. Treated as band C unless a reviewer narrows it.',
        };
    }

    public function defaultTreatment(): DisclosureTreatment
    {
        return match ($this) {
            self::A, self::B => DisclosureTreatment::Include,
            self::C, self::D => DisclosureTreatment::Describe,
        };
    }
}
