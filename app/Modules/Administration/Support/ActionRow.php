<?php

declare(strict_types=1);

namespace App\Modules\Administration\Support;

/**
 * ONE ROW OF THE ACTION QUEUE. It owns no task - D-135.
 *
 * There is no assignee, no state, no due date, no completion and no identifier,
 * because there is no task model behind this and no table for one. A row is a
 * sentence and a destination, derived on every render from the objects the
 * tiles above already used, and it stops existing the moment the condition
 * that produced it stops being true.
 *
 * EVERY ROW HAS A DESTINATION THE VIEWER CAN OPEN - D-145. A row is only
 * derived at all where the source behind it was evaluated, and a platform-only
 * source is not evaluated for a viewer who may not receive it, so there is no
 * suppressed row to leak: the condition was never asked.
 */
final readonly class ActionRow
{
    public function __construct(
        public string $key,
        public string $sentence,
        public string $href,
        public string $destination,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'sentence' => $this->sentence,
            'href' => $this->href,
            'destination' => $this->destination,
        ];
    }
}
