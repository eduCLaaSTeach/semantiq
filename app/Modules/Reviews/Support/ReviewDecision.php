<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Support;

/**
 * What a reviewer chose.
 *
 * RETAIN WRITES NOTHING TO THE ACCESS MODEL. Not a row, not an updated_at. That
 * is why a review can never revive stale access - a thing that writes nothing
 * cannot revive anything - and RetainWritesNothingTest asserts the access rows
 * are byte-identical before and after.
 */
enum ReviewDecision: string
{
    case Retain = 'retain';
    case Revoke = 'revoke';

    public function resultingState(): ReviewState
    {
        return match ($this) {
            self::Retain => ReviewState::Retained,
            self::Revoke => ReviewState::Revoked,
        };
    }
}
