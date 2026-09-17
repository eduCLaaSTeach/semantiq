<?php

declare(strict_types=1);

namespace App\Modules\Security\Projection;

/**
 * A COUNT, with context. THERE IS NO $state FIELD. Not null - ABSENT.
 *
 * So a metric cannot enter aggregation, cannot be defaulted to Healthy, and
 * cannot appear among unresolved Exceptions - in each case because there is no
 * state to read, not because a rule says so. N-SS16 and N-SS17 break it from
 * both directions.
 */
final class ViewerMetric
{
    public function __construct(
        public readonly string $control,
        public readonly string $label,
        public readonly int $count,
        public readonly string $context,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'control' => $this->control,
            'label' => $this->label,
            'count' => $this->count,
            'context' => $this->context,
        ];
    }
}
