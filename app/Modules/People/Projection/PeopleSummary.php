<?php

declare(strict_types=1);

namespace App\Modules\People\Projection;

/**
 * WHAT P1-03 IS WILLING TO SAY ABOUT PEOPLE IN ONE SENTENCE - D-180.
 *
 * FOUR FIELDS, AND THE ABSENCE OF THE FIFTH IS THE DESIGN. There is no field
 * here for a name, an email address, a group membership, an identifier or a
 * row, SO NONE CAN REACH A CONSUMER. That is the HealthRow pattern and the
 * ALLOWED_KEYS pattern before it: the leak is unrepresentable rather than
 * stripped on the way out, because the branch that forgets to strip is always
 * the error path.
 *
 * THREE NUMBERS AND A BOOLEAN THAT SAYS WHETHER TO BELIEVE THEM.
 *
 *   valued === true    the counts were asked and answered. Each is an int,
 *                      and 0 is a real answer meaning "none".
 *   valued === false   this viewer is not being told, so every count is NULL
 *                      and there is no number to render at all.
 *
 * `0` AND `null` MUST NEVER COLLAPSE INTO EACH OTHER. They are the difference
 * between "there are no inactive accounts" and "you may not be told how many
 * there are", and they look identical the moment somebody writes `?? 0`. The
 * counts are nullable ints rather than ints precisely so that the type system
 * refuses the shortcut.
 *
 * IT SAYS NOTHING ABOUT READINESS. D-180: a deployment with no groups is not a
 * deployment with a problem. These are facts; whoever consumes them decides
 * what, if anything, they mean.
 */
final readonly class PeopleSummary
{
    public function __construct(
        public bool $valued,
        public ?int $activeUsers,
        public ?int $inactiveUsers,
        public ?int $activeGroups,
    ) {}

    /** Asked and answered. */
    public static function of(int $activeUsers, int $inactiveUsers, int $activeGroups): self
    {
        return new self(true, $activeUsers, $inactiveUsers, $activeGroups);
    }

    /**
     * Not this viewer's to be told, or no scope in which to ask.
     *
     * EVERY COUNT IS NULL, never 0. A withheld summary carrying zeroes would
     * render as a real, reassuring answer about an organisation nobody looked
     * at.
     */
    public static function withheld(): self
    {
        return new self(false, null, null, null);
    }
}
