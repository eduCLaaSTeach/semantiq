<?php

declare(strict_types=1);

namespace App\Modules\Security\Posture;

/**
 * One business domain's posture. Organisation-scoped throughout.
 *
 * Its own aggregate is computed through the SAME Aggregation as everything else
 * - there is no second precedence rule for domains.
 */
final class DomainPosture
{
    /**
     * @param  list<PostureRow>  $rows
     * @param  list<MetricRow>  $metrics
     */
    private function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly bool $enabled,
        public readonly array $rows,
        public readonly array $metrics,
    ) {}

    /**
     * @param  list<PostureRow>  $rows
     * @param  list<MetricRow>  $metrics
     *
     * @internal Constructed by PostureEvaluator only.
     */
    public static function make(int $id, string $name, bool $enabled, array $rows, array $metrics): self
    {
        return new self($id, $name, $enabled, $rows, $metrics);
    }

    public function state(): PostureState
    {
        return Aggregation::of(array_map(
            static fn (PostureRow $row): PostureState => $row->state,
            $this->rows,
        ));
    }
}
