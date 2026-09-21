<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Projection;

/**
 * WHAT P1-07 IS WILLING TO SAY ABOUT REVIEW WORK IN ONE SENTENCE.
 *
 * TWO NUMBERS AND NO ITEM. There is no field for a subject, a role, a domain,
 * a reviewer, a decision or a row, so no review EVIDENCE can reach a consumer
 * through this object - only how much of it is outstanding. Reading the
 * evidence is Access Reviews, gated on EvidenceRead, and this is not a second
 * way in.
 *
 * `0` AND `null` MUST NEVER COLLAPSE. `overdue === 0` is the good news a
 * reviewer wants; `overdue === null` means nobody asked. They render
 * identically the moment somebody writes `?? 0`, and this is the one on the
 * screen most likely to be believed.
 */
final readonly class ReviewSummary
{
    public function __construct(
        public bool $valued,
        public ?int $outstanding,
        public ?int $overdue,
    ) {}

    /** Asked and answered. */
    public static function of(int $outstanding, int $overdue): self
    {
        return new self(true, $outstanding, $overdue);
    }

    /** Not this viewer's to be told, or no scope in which to ask. */
    public static function withheld(): self
    {
        return new self(false, null, null);
    }
}
