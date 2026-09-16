<?php

declare(strict_types=1);

namespace App\Modules\Security\Posture;

/**
 * INTERNAL TRUTH for one informational metric. Never rendered.
 *
 * THERE IS NO $state FIELD. Not null - ABSENT. So there is no code path that
 * gives a metric a state, because there is nowhere to put one; and
 * Aggregation::of() takes a list of PostureState, so a MetricRow cannot be
 * passed to it even by accident.
 *
 * That is the F-5 guard built into the type rather than asserted in prose.
 * Adding a state field here is the mutation N-SS14, N-SS15, N-SS16 and N-SS17
 * break, from both directions: a legitimate Restricted grant must not turn the
 * aggregate amber, and a count must never be converted into Healthy.
 */
final class MetricRow
{
    private function __construct(
        public readonly string $control,
        public readonly string $label,
        public readonly ControlScope $scope,
        public readonly int $count,
        public readonly string $context,
    ) {}

    /**
     * Constructed by PostureEvaluator only.
     *
     * @internal
     */
    public static function make(
        string $control,
        string $label,
        ControlScope $scope,
        int $count,
        string $context,
    ): self {
        return new self($control, $label, $scope, $count, $context);
    }
}
