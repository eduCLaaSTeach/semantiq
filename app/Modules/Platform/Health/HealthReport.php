<?php

declare(strict_types=1);

namespace App\Modules\Platform\Health;

final class HealthReport
{
    /** @param array<string, array{ok: bool, detail: string}> $checks */
    public function __construct(public readonly array $checks) {}

    public function isHealthy(): bool
    {
        foreach ($this->checks as $check) {
            if (! $check['ok']) {
                return false;
            }
        }

        return true;
    }

    /** @return list<string> */
    public function failing(): array
    {
        return array_keys(array_filter($this->checks, fn (array $c): bool => ! $c['ok']));
    }
}
