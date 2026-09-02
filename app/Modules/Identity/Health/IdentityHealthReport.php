<?php

declare(strict_types=1);

namespace App\Modules\Identity\Health;

/**
 * One health report: an overall state, and the rows that produced it.
 *
 * Four row states, not three. `NotChecked` exists because the live-check row has
 * nothing to say until somebody presses the button, and a deployment clears the
 * cache - so without it every deployment would sit permanently in amber for a
 * condition that is not a fault. A warning nobody can act on teaches people to
 * ignore the screen, which is the one thing this screen cannot afford.
 */
final class IdentityHealthReport
{
    public const HEALTHY = 'healthy';

    public const DEGRADED = 'degraded';

    public const FAILED = 'failed';

    public const NOT_CHECKED = 'not_checked';

    /**
     * @param  list<array{key: string, label: string, state: string, finding: string, action: string|null}>  $checks
     */
    public function __construct(
        public readonly array $checks,
        public readonly ?string $establishedAt = null,
        public readonly ?string $lastProbeAt = null,
    ) {}

    /**
     * Any Failed wins; else any Degraded; else Healthy. NotChecked contributes
     * nothing - it is information, not a finding.
     */
    public function state(): string
    {
        foreach ($this->checks as $check) {
            if ($check['state'] === self::FAILED) {
                return self::FAILED;
            }
        }

        foreach ($this->checks as $check) {
            if ($check['state'] === self::DEGRADED) {
                return self::DEGRADED;
            }
        }

        return self::HEALTHY;
    }

    /**
     * What a person reads.
     *
     * Deliberately cautious. This unit performs no authentication transaction
     * and cannot see a client secret's expiry, so "Healthy" states what was
     * checked rather than promising an outcome it has not observed. The first
     * time a screen says sign-in works over a broken sign-in, nobody believes it
     * again.
     */
    public function stateInWords(): string
    {
        return match ($this->state()) {
            self::FAILED => 'Sign-in unavailable',
            self::DEGRADED => 'Needs attention',
            default => 'Healthy',
        };
    }

    public function summary(): string
    {
        return match ($this->state()) {
            self::FAILED => 'Sign-in cannot succeed until the conditions below are resolved.',
            self::DEGRADED => 'An identity configuration or provider condition needs attention and may affect sign-in.',
            default => 'No issue was detected by the available identity checks.',
        };
    }

    /** The first thing that is not healthy, for the security event's reason. */
    public function firstConcern(): ?string
    {
        foreach ($this->checks as $check) {
            if ($check['state'] === self::FAILED || $check['state'] === self::DEGRADED) {
                return $check['key'];
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'state' => $this->state(),
            'stateInWords' => $this->stateInWords(),
            'summary' => $this->summary(),
            'checks' => $this->checks,
            'establishedAt' => $this->establishedAt,
            'lastProbeAt' => $this->lastProbeAt,
        ];
    }
}
