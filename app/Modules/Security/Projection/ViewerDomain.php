<?php

declare(strict_types=1);

namespace App\Modules\Security\Projection;

use App\Modules\Security\Posture\Aggregation;
use App\Modules\Security\Posture\PostureState;

/**
 * One domain, projected. Domain posture is organisation-scoped throughout, so
 * every row is valued for anybody who may reach the screen at all - there is no
 * withheld domain row.
 */
final class ViewerDomain
{
    /**
     * @param  list<ViewerRow>  $rows
     * @param  list<ViewerMetric>  $metrics
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly bool $enabled,
        public readonly array $rows,
        public readonly array $metrics,
    ) {}

    public function state(): PostureState
    {
        return Aggregation::of(array_map(
            static fn (ViewerRow $row): PostureState => $row->state,
            $this->rows,
        ));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'enabled' => $this->enabled,
            'state' => $this->state()->value,
            'stateLabel' => $this->state()->label(),
            'rows' => array_map(static fn (ViewerRow $r): array => $r->toArray(), $this->rows),
            'metrics' => array_map(static fn (ViewerMetric $m): array => $m->toArray(), $this->metrics),
        ];
    }
}
