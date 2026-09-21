<?php

declare(strict_types=1);

namespace App\Modules\Administration\Support;

/**
 * ONE TILE, AND THE ABSENCE OF EVERY OTHER FIELD IS THE DESIGN.
 *
 * There is no field here for a person, an email address, a domain name, a
 * credential, a secret, a hostname, an exception message or a business record -
 * SO NONE CAN REACH THE SCREEN. That is HealthRow's rule and IntegrationView's
 * before it: the leak is unrepresentable rather than stripped on the way out.
 *
 * A COUNT IS A NUMBER AND A LABEL. Never a status, never a colour - D-138, and
 * sec-metric-count already carries that sentence in the stylesheet. `metrics`
 * is empty unless the tile is Valued, because a withheld or unavailable tile
 * has no number to show and no place to put one.
 *
 * `status` IS SOMETHING A SOURCE SAID. Either a readiness word this unit
 * derived under D-130, or a HealthStatus another unit returned - never a value
 * this file invented for a source that did not give it one. D-144: missing
 * organisation scope is NEVER rewritten as Not configured or 0.
 *
 * `href` IS A SUGGESTION, NOT A GRANT. It is null wherever the viewer could not
 * open the destination (D-145), and every destination re-authorises on arrival
 * anyway.
 */
final readonly class Tile
{
    /**
     * @param  list<array{label: string, count: int}>  $metrics
     * @param  list<array{name: string, status: string, statusInWords: string, required: bool}>  $rows
     */
    public function __construct(
        public string $key,
        public string $name,
        public TileState $state,
        public string $note,
        /** @var null|array{kind: string, tone: string, words: string} */
        public ?array $badge = null,
        public array $metrics = [],
        public array $rows = [],
        public ?string $href = null,
        public ?string $linkLabel = null,
    ) {}

    /**
     * The viewer may not be told. NO NUMBER, NO STATUS, NO LINK.
     *
     * The note says so in business words rather than leaving an empty box: a
     * tile that renders nothing reads as a bug, and a reader cannot tell it
     * apart from a tile that failed to load.
     */
    public static function withheld(string $key, string $name, string $note): self
    {
        return new self($key, $name, TileState::Withheld, $note);
    }

    /**
     * The source could not answer this render.
     *
     * NEVER `0`, NEVER A POSITIVE STATE, AND NEVER A MISSING TILE. The tile
     * stays on the screen saying it does not know, because a tile that
     * disappeared would leave a reader believing the page was complete.
     */
    public static function unavailable(string $key, string $name): self
    {
        return new self(
            $key,
            $name,
            TileState::Unavailable,
            'This could not be read just now, so nothing is known about it. The rest of this page is unaffected.',
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'name' => $this->name,
            'state' => $this->state->value,
            'note' => $this->note,
            'badge' => $this->badge,
            'metrics' => $this->metrics,
            'rows' => $this->rows,
            'href' => $this->href,
            'linkLabel' => $this->linkLabel,
        ];
    }
}
