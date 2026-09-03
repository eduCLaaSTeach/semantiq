<?php

declare(strict_types=1);

namespace App\Modules\Domains\Models;

/**
 * What the organisation EXPECTS about how widely this domain will be seen.
 *
 * D-48. This is a written statement of intent and nothing else. Nothing reads
 * it, nothing enforces it, and DomainsBoundaryTest fails if anything ever
 * branches on it. It is a note to P1-05 from the person who defined the domain,
 * captured while they were thinking about it rather than reconstructed later.
 *
 * WHY NOT "confidential" AND "restricted".
 *
 * Those two words belong to P1-05's SENSITIVITY dimension - Standard,
 * Confidential, Restricted - which will be ENFORCED. Reusing them for an
 * ADVISORY field would put two different concepts behind one vocabulary: an
 * administrator who set "Confidential" here and later met "Confidential" there
 * would reasonably believe they had already answered that question.
 *
 * Access expectation says HOW WIDELY ACCESS IS EXPECTED TO BE GIVEN.
 * Sensitivity says WHAT THE DATA IS. Different questions, different words.
 */
enum AccessExpectation: string
{
    case Undecided = 'undecided';
    case Broad = 'broad';
    case Limited = 'limited';
    case Exceptional = 'exceptional';

    /**
     * The sentence an administrator reads. The stored value is never shown.
     */
    public function label(): string
    {
        return match ($this) {
            self::Undecided => 'Not yet determined',
            self::Broad => 'Broad access is expected',
            self::Limited => 'Access is expected to be limited to selected roles or functions',
            self::Exceptional => 'Access is expected to be tightly limited and reviewed',
        };
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => ['value' => $case->value, 'label' => $case->label()],
            self::cases()
        );
    }
}
