<?php

declare(strict_types=1);

namespace App\Modules\Identity\Health;

/**
 * P1-02 identity health AS ALREADY ESTABLISHED, for a reader that must not
 * contact Microsoft.
 *
 * Three fields and no checks array, deliberately. A stored snapshot knows the
 * state P1-02 last recorded and when; it does not know, and must not appear to
 * know, which individual check produced it - that is the live report's answer,
 * and it is on the SSO Health screen where a live re-check is one press away.
 *
 * NOT_CHECKED IS A FIRST-CLASS ANSWER, not an error. It is what "nobody has
 * looked" reads as, and the whole reason this class exists rather than a
 * nullable string: a caller cannot accidentally treat absence as health,
 * because absence has a name.
 */
final readonly class StoredIdentityHealth
{
    /**
     * @param  string  $state  one of IdentityHealthReport's four constants
     * @param  string|null  $checkedAt  ISO-8601 instant of the stored result
     * @param  string|null  $lastProbeAt  ISO-8601 instant of the last live probe
     */
    public function __construct(
        public string $state,
        public ?string $checkedAt = null,
        public ?string $lastProbeAt = null,
    ) {}

    public function wasChecked(): bool
    {
        return $this->state !== IdentityHealthReport::NOT_CHECKED;
    }
}
