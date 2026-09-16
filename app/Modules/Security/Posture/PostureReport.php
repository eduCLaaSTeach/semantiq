<?php

declare(strict_types=1);

namespace App\Modules\Security\Posture;

/**
 * EVERY ROW, UNPROJECTED. NEVER RENDERED.
 *
 * This is the full truth about the deployment, including values a particular
 * viewer may not see. It is turned into a ViewerReport by PostureProjection
 * BEFORE any controller touches it, and N-SS26's architecture guard asserts
 * that no controller and nothing under resources/js references this class,
 * PostureRow, MetricRow or PostureState at all.
 *
 * That is why the projection cannot be forgotten: a controller has nothing to
 * render unless it has already projected.
 */
final class PostureReport
{
    /**
     * @param  list<PostureRow>  $rows
     * @param  list<MetricRow>  $metrics
     * @param  list<DomainPosture>  $domains
     */
    public function __construct(
        public readonly array $rows,
        public readonly array $metrics,
        public readonly array $domains = [],
    ) {}

    /** @return list<PostureRow> */
    public function rowsIn(string ...$controls): array
    {
        return array_values(array_filter(
            $this->rows,
            static fn (PostureRow $row): bool => in_array($row->control, $controls, true),
        ));
    }

    /** @return list<MetricRow> */
    public function metricsIn(string ...$controls): array
    {
        return array_values(array_filter(
            $this->metrics,
            static fn (MetricRow $row): bool => in_array($row->control, $controls, true),
        ));
    }
}
