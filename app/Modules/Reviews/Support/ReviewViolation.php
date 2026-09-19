<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Support;

use RuntimeException;

/**
 * A refused review operation, carrying a stable reason and a message written
 * for the reviewer.
 *
 * The same shape as P1-05's AccessViolation, deliberately separate rather than
 * an extension of it: P1-07 refuses for its own reasons, and adding cases to an
 * accepted unit's violation type would widen it for everybody.
 *
 * THE MESSAGES DISCLOSE NOTHING. "You are not able to review this access" is
 * the same sentence whether the item exists and is outside the actor's
 * authority or does not exist at all - a different sentence would be an oracle.
 */
final class ReviewViolation extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function alreadyDecided(): self
    {
        return new self(
            'already_decided',
            'This review has already been decided. Reviews are decided once; a later review raises a new one.'
        );
    }

    /** Used for "not permitted" AND for "no such item". Identical on purpose. */
    public static function notPermitted(): self
    {
        return new self(
            'not_permitted',
            'You are not able to review this access.'
        );
    }

    public static function selfReviewNotPermitted(): self
    {
        return new self(
            'self_review_not_permitted',
            'Somebody else is able to review this access, so it is not yours to confirm.'
        );
    }
}
