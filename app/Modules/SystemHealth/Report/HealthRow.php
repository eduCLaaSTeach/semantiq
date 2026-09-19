<?php

declare(strict_types=1);

namespace App\Modules\SystemHealth\Report;

/**
 * FOUR FIELDS, AND THE ABSENCE OF THE FIFTH IS THE DESIGN.
 *
 * There is no field for a hostname, a database name, a username, a connection
 * string, a path, a driver, a version, a capacity figure, an endpoint, a
 * configuration value or an exception - SO NONE CAN REACH THE SCREEN. D-119 and
 * D-120 are satisfied by there being nowhere to put them rather than by
 * remembering to strip them, which is the ALLOWED_KEYS pattern P1-00
 * established and /up's two-word allowlist repeats: the leak is
 * unrepresentable, not discouraged.
 *
 * $explanation IS CHOSEN, NEVER CAUGHT. Every check returns one of its own
 * declared sentences. HealthInspector::database() already does exactly this -
 * it catches the exception and returns a fixed sentence BECAUSE the message
 * carries the host, database name and user - and that precedent is the rule
 * here.
 *
 * $checkedAt is present only for a STORED result - today, Microsoft Entra ID
 * alone. A live check has no age to report, and a live row carrying one would
 * suggest its value might be stale. The reverse matters more: the one row that
 * is not live always shows its age, so a stored "healthy" from last week cannot
 * be read as a measurement taken now.
 */
final readonly class HealthRow
{
    public function __construct(
        public string $name,
        public HealthStatus $status,
        public string $explanation,
        public ?string $checkedAt = null,
    ) {}

    /** @return array{name: string, status: string, explanation: string, checkedAt: string|null} */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'status' => $this->status->value,
            'explanation' => $this->explanation,
            'checkedAt' => $this->checkedAt,
        ];
    }
}
