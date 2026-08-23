<?php

declare(strict_types=1);

namespace App\Modules\Security\Enums;

/**
 * The result of one security control check. Features ADM-011 and Security Overview.
 *
 * Six states, not the four `HealthState` carries, because a security screen has
 * to distinguish three different kinds of "not green" that a health screen can
 * blur together:
 *
 *   NotConfigured  - the control exists and nobody has turned it on.
 *   NotAvailable   - the control cannot work here, for a stated reason. Session
 *                    revocation under SESSION_DRIVER=file is the case that
 *                    forced it: the code is correct and the environment cannot
 *                    support it.
 *   NotVerified    - we could not establish the answer. NEVER reported as
 *                    Healthy. A control we cannot check is a control we cannot
 *                    claim, and reporting an unproven control as green is how a
 *                    security page becomes decoration.
 *
 * The separation matters because the three have different owners. NotConfigured
 * is an administrator's decision, NotAvailable is an environment change, and
 * NotVerified is a defect in this application.
 */
enum SecurityStatus: string
{
    case Healthy = 'healthy';
    case Warning = 'warning';
    case Critical = 'critical';
    case NotConfigured = 'not_configured';
    case NotAvailable = 'not_available';
    case NotVerified = 'not_verified';

    public function label(): string
    {
        return match ($this) {
            self::Healthy => 'Healthy',
            self::Warning => 'Warning',
            self::Critical => 'Critical',
            self::NotConfigured => 'Not Configured',
            self::NotAvailable => 'Not Available',
            self::NotVerified => 'Not Verified',
        };
    }

    /**
     * The design system's six badge roles. No status invents a colour.
     *
     * NotConfigured and NotAvailable are neutral rather than warning: neither
     * is a fault, and colouring a deliberate choice amber trains people to
     * ignore amber. NotVerified IS a warning, because not knowing is a problem
     * with this application.
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Healthy => 'badge badge-success',
            self::Warning, self::NotVerified => 'badge badge-warning',
            self::Critical => 'badge badge-danger',
            self::NotConfigured, self::NotAvailable => 'badge',
        };
    }

    /**
     * Whether this status should draw an administrator's attention now.
     *
     * Used by Security Overview to build its warnings list. NotAvailable is
     * excluded deliberately: it has already been decided and explained, and
     * repeating it as a warning on every page load is noise.
     */
    public function needsAttention(): bool
    {
        return match ($this) {
            self::Critical, self::Warning, self::NotVerified => true,
            self::Healthy, self::NotConfigured, self::NotAvailable => false,
        };
    }

    /**
     * The worst status in a set, which is the status of the whole.
     *
     * Severity order, worst first. NotVerified outranks NotConfigured because
     * "we do not know" is worse than "we decided not to".
     *
     * @param  list<self>  $states
     */
    public static function worst(array $states): self
    {
        foreach ([
            self::Critical,
            self::Warning,
            self::NotVerified,
            self::NotConfigured,
            self::NotAvailable,
            self::Healthy,
        ] as $candidate) {
            if (in_array($candidate, $states, true)) {
                return $candidate;
            }
        }

        /* Nothing was checked, which tells us nothing rather than tells us
         * everything is fine. */
        return self::NotVerified;
    }
}
