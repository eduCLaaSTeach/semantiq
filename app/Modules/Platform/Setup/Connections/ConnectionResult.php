<?php

declare(strict_types=1);

namespace App\Modules\Platform\Setup\Connections;

use App\Modules\SystemHealth\Report\HealthStatus;

/**
 * What a connection test returns. Three fields, and no room for a fourth.
 *
 * `explanation` IS A CHOSEN SENTENCE, NEVER A CAUGHT ONE. Here that is a
 * security control rather than a polish one: provider error bodies routinely
 * echo credentials, endpoints and internal hostnames, and a connection test is
 * the single most likely place for one to be rendered straight onto a screen
 * and then into a screenshot. Every adapter catches Throwable and returns one
 * of its OWN declared sentences; the caught value reaches nothing.
 *
 * HealthStatus IS P1-09'S ENUM, IMPORTED - not re-declared. A second status
 * vocabulary would give one deployment two ways to say the same thing and two
 * places to get the neutral treatments wrong.
 */
final readonly class ConnectionResult
{
    public function __construct(
        public HealthStatus $status,
        public string $explanation,
    ) {}

    public static function available(string $explanation): self
    {
        return new self(HealthStatus::Available, $explanation);
    }

    /**
     * A TIMEOUT IS Degraded, NEVER Unavailable - D-156.
     *
     * "We could not reach it within ten seconds" is not "it is broken", and
     * reporting the second would send an administrator to re-enter a
     * configuration that was correct.
     */
    public static function degraded(string $explanation): self
    {
        return new self(HealthStatus::Degraded, $explanation);
    }

    public static function unavailable(string $explanation): self
    {
        return new self(HealthStatus::Unavailable, $explanation);
    }

    /**
     * NOBODY HAS LOOKED, and this is the honest answer when a provider cannot
     * validate credentials without doing real work - D-175.
     *
     * The tempting alternative is "just a tiny test prompt", which is always
     * available and always looks harmless. It is inference, it costs money, and
     * it is the first step of exactly the capability this unit is defined as
     * not having.
     */
    public static function notChecked(string $explanation): self
    {
        return new self(HealthStatus::NotChecked, $explanation);
    }
}
