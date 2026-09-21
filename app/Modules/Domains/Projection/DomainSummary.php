<?php

declare(strict_types=1);

namespace App\Modules\Domains\Projection;

/**
 * WHAT P1-04 IS WILLING TO SAY ABOUT BUSINESS DOMAINS - D-181.
 *
 * THESE ARE FACTS, NOT A VERDICT, and that distinction is D-181 itself.
 *
 * "Ready", "Needs attention" and "Not configured" are an INTERPRETATION of
 * these two numbers, and the interpretation belongs to whoever is asking -
 * D-130 gave it to Administration Home. Putting it here would make P1-04 own a
 * judgement about its own data that only one screen asked for, and the next
 * consumer wanting a different reading would have to work around it.
 *
 * THREE FIELDS, AND THE ABSENCE OF THE FOURTH IS THE DESIGN. There is no field
 * for a domain name, a code, an owner, a person, a status or a row, so none can
 * reach a consumer. A domain grants nothing and this says nothing about access:
 * no entitlement, no scope, no sensitivity, no role.
 *
 * `0` AND `null` MUST NEVER COLLAPSE. `enabled === 0` means the organisation has
 * switched none of its domains on; `enabled === null` means this viewer is not
 * being told. They read identically the moment somebody writes `?? 0`.
 */
final readonly class DomainSummary
{
    public function __construct(
        public bool $valued,
        public ?int $enabled,
        public ?int $enabledUnowned,
    ) {}

    /** Asked and answered. */
    public static function of(int $enabled, int $enabledUnowned): self
    {
        return new self(true, $enabled, $enabledUnowned);
    }

    /**
     * Not this viewer's to be told, or no scope in which to ask.
     *
     * BOTH COUNTS NULL, never 0. "No enabled domains are unowned" is the most
     * reassuring thing this object can say, and it must never be said by
     * accident.
     */
    public static function withheld(): self
    {
        return new self(false, null, null);
    }
}
