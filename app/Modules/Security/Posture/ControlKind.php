<?php

declare(strict_types=1);

namespace App\Modules\Security\Posture;

/**
 * The line between an OBSERVED CONDITION and a LEGITIMATE STATE worth seeing.
 *
 * A posture control carries a state and contributes to the aggregate. An
 * informational metric carries a COUNT AND CONTEXT ONLY and can never enter
 * aggregation - MetricRow has no state field to put one in, and Aggregation
 * takes a list of PostureState, so a metric is not passable to it.
 *
 * WHY THE LINE EXISTS. P1-05 deliberately delivers Restricted sensitivity
 * grants, several Organisation Administrators, whole-domain scope and
 * assignments preserved across deactivation - each protected by its own control
 * and each approved. Painting them amber would mean P1-06 declaring approved
 * P1-05 behaviour to be a fault, and would train administrators that amber
 * means nothing. P1-07 owns whether a particular grant is overdue. P1-06 shows
 * it exists.
 */
enum ControlKind
{
    case PostureControl;
    case InformationalMetric;
}
