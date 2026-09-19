<?php

declare(strict_types=1);

namespace App\Modules\SystemHealth\Report;

/**
 * Five areas, in the order a reader needs them.
 *
 * An AREA is a heading with rows, and nothing more: no status of its own, no
 * count, no badge. An area-level verdict would be a twelfth status nothing
 * measured, computed from rows whose meanings do not combine - "one Not
 * applicable and one Unavailable" has no summary that is not a lie.
 */
final readonly class SystemHealthArea
{
    /** @param list<HealthRow> $rows */
    public function __construct(
        public string $name,
        public string $description,
        public array $rows,
    ) {}

    /** @return array{name: string, description: string, rows: list<array<string, mixed>>} */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'rows' => array_map(static fn (HealthRow $row): array => $row->toArray(), $this->rows),
        ];
    }
}
