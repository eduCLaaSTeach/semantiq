<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Support;

/**
 * The four states a review item can be in. DESIGN §2.1, PLAN §5F.
 *
 * OVERDUE IS DELIBERATELY NOT ONE OF THEM. It is `pending` and past the due
 * instant - a derived condition. Making it a state would need a background
 * process to move items into it, and nothing in this deployment runs on a
 * timer; an item's state would then depend on whether that process had run.
 *
 * EVERY TERMINAL STATE IS TERMINAL. There is no reopen: a later review is a new
 * item in a new cycle, which is what keeps the history honest.
 */
enum ReviewState: string
{
    case Pending = 'pending';
    case Retained = 'retained';
    case Revoked = 'revoked';
    case Superseded = 'superseded';

    public function isTerminal(): bool
    {
        return $this !== self::Pending;
    }

    /** Business words. No raw identifier ever reaches the screen. */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Awaiting review',
            self::Retained => 'Access confirmed',
            self::Revoked => 'Access removed',
            self::Superseded => 'No longer reviewable',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Pending => 'Somebody still has to decide whether this access should continue.',
            self::Retained => 'A reviewer confirmed this access should continue. Nothing about the access was changed.',
            self::Revoked => 'A reviewer removed this access. It ended at that moment.',
            self::Superseded => 'The access changed or ended by another route before a decision was made, so no decision was possible.',
        };
    }
}
