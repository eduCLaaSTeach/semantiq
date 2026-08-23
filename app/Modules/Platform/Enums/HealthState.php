<?php

declare(strict_types=1);

namespace App\Modules\Platform\Enums;

/**
 * The result of one health check. Features ADM-001 and ADM-024.
 *
 * Four states rather than the three ADM-001 names, because `Unknown` is a real
 * and different answer from `Critical`. A queue worker that cannot be reached
 * from a web request is not the same as a queue worker that is down, and
 * reporting the first as the second trains an administrator to ignore the
 * colour. Whether an unknown should page somebody is a policy question; it is
 * not the probe's job to guess.
 */
enum HealthState: string
{
    case Healthy = 'healthy';
    case Warning = 'warning';
    case Critical = 'critical';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Healthy => 'Healthy',
            self::Warning => 'Warning',
            self::Critical => 'Critical',
            self::Unknown => 'Unknown',
        };
    }

    /** The design system's six badge roles. No check invents a colour. */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Healthy => 'badge badge-success',
            self::Warning => 'badge badge-warning',
            self::Critical => 'badge badge-danger',
            self::Unknown => 'badge',
        };
    }

    /**
     * The worst state in a set, which is the state of the whole.
     *
     * A platform is only as healthy as its unhealthiest dependency, so the
     * roll-up takes the maximum severity rather than an average: one critical
     * check among nine healthy ones is a critical platform.
     *
     * @param  list<self>  $states
     */
    public static function worst(array $states): self
    {
        foreach ([self::Critical, self::Warning, self::Unknown, self::Healthy] as $candidate) {
            if (in_array($candidate, $states, true)) {
                return $candidate;
            }
        }

        /* No checks ran at all, which tells us nothing rather than tells us
         * everything is fine. */
        return self::Unknown;
    }
}
