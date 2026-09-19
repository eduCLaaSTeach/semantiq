<?php

declare(strict_types=1);

namespace App\Modules\SystemHealth\Report;

/**
 * SIX, BECAUSE FIVE CANNOT SAY "WE DO NOT KNOW".
 *
 * NotChecked is the whole reason this enum is not five long. A health page
 * whose worst honest answer is Available reports safety it never measured,
 * which is the failure CLAUDE.md section 2 describes and the one a health
 * screen is most prone to: nobody writes `return Available` on purpose, but
 * `?? Available`, a default arm and a boolean that absence satisfies all get
 * there by accident.
 *
 * NotConfigured and NotApplicable are neither failures nor successes. They
 * render neutral - never green, never red - so a reader is not invited to treat
 * "the scheduler is not configured" as either good news or an incident.
 *
 * BACKED, and the strings are part of the contract. A pure enum has no value to
 * serialise, so the payload would have had to carry ->name and the wire format
 * would be an accident of PHP identifiers. The React layer matches on these
 * exact strings.
 */
enum HealthStatus: string
{
    /** A check ran and passed. */
    case Available = 'available';

    /** A check ran; the thing works but needs attention. */
    case Degraded = 'degraded';

    /** A check ran and failed. */
    case Unavailable = 'unavailable';

    /** Deliberately absent, and correct for this deployment. */
    case NotConfigured = 'not_configured';

    /** Cannot apply to this architecture at all. */
    case NotApplicable = 'not_applicable';

    /** NOBODY HAS LOOKED. Never a synonym for Available. */
    case NotChecked = 'not_checked';
}
