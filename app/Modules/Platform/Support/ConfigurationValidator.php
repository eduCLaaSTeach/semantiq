<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support;

use Illuminate\Contracts\Config\Repository;

/**
 * Fails closed on misconfiguration.
 *
 * The failure mode this guards against is a deployment that succeeds while the
 * application quietly misbehaves - which is harder to notice, and therefore
 * worse, than an application that refuses to start.
 *
 * Findings name the key and never its value. A validator that reported
 * "APP_KEY is 'base64:...'" would put the secret into the log it was written to
 * protect.
 */
final class ConfigurationValidator
{
    public function __construct(private readonly Repository $config) {}

    /**
     * @return list<string> Human-readable problems; empty means valid.
     */
    public function problems(): array
    {
        $problems = [];

        foreach (ConfigurationRequirements::required() as $key) {
            if ($this->isBlank($this->config->get($key))) {
                $problems[] = "Required configuration [{$key}] is missing or empty.";
            }
        }

        if ($this->config->get('app.env') === 'production') {
            foreach (ConfigurationRequirements::requiredInProduction() as $key) {
                if ($this->isBlank($this->config->get($key))) {
                    $problems[] = "Required configuration [{$key}] is missing or empty.";
                }
            }
        }

        $problems = [...$problems, ...$this->connectionProblems()];

        // Debug output in production leaks stack traces, environment and query
        // contents to anyone who can trigger an error.
        if ($this->config->get('app.env') === 'production' && $this->config->get('app.debug') === true) {
            $problems[] = 'APP_DEBUG must be false when APP_ENV is production.';
        }

        return $problems;
    }

    public function isValid(): bool
    {
        return $this->problems() === [];
    }

    /**
     * Validate the connection the application will actually use.
     *
     * @return list<string>
     */
    private function connectionProblems(): array
    {
        $connection = $this->config->get('database.default');

        if (! is_string($connection) || $connection === '') {
            return [];
        }

        $driver = $this->config->get("database.connections.{$connection}.driver");

        if (! is_string($driver)) {
            return ["Database connection [{$connection}] has no driver configured."];
        }

        $problems = [];

        foreach (ConfigurationRequirements::connectionKeysByDriver()[$driver] ?? [] as $key) {
            if ($this->isBlank($this->config->get("database.connections.{$connection}.{$key}"))) {
                $problems[] = "Required configuration [database.connections.{$connection}.{$key}] is missing or empty.";
            }
        }

        return $problems;
    }

    private function isBlank(mixed $value): bool
    {
        return $value === null || $value === '';
    }
}
