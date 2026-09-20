<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support;

use App\Modules\Platform\Setup\Identity\IdentityConfigurationSource;
use App\Modules\Platform\Setup\Models\PlatformSetting;
use Illuminate\Contracts\Config\Repository;
use Throwable;

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
    public function __construct(
        private readonly Repository $config,
        private readonly IdentityConfigurationSource $identityConfiguration,
    ) {}

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

            $problems = [...$problems, ...$this->identityProblems()];
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
     * The identity configuration, asked of whichever authority is in force.
     *
     * NOT config(). These four used to sit in requiredInProduction() and be
     * read straight from config/identity.php, which was correct while .env was
     * the only identity authority and became wrong the moment a deployment
     * could read its identity configuration from the store: a correctly
     * configured store-backed production deployment would have been reported as
     * missing four environment variables it is RIGHT not to have.
     *
     * The key names are still MICROSOFT_* because that is what an operator
     * recognises, and because on the .env path they are literally right. On the
     * store path they name the same four values, which is why the sentence says
     * which source was asked - a finding that does not say where it looked
     * sends somebody to edit the wrong place.
     *
     * @return list<string>
     */
    private function identityProblems(): array
    {
        /*
         * AN UNANSWERABLE QUESTION IS A PROBLEM HERE, AND A REFUSAL ELSEWHERE.
         *
         * Resolving the identity configuration can now reach the database, and
         * IdentityConfigurationSource deliberately lets a failing query
         * propagate: the sign-in path must refuse rather than quietly fall back
         * to .env and authenticate against a directory the deployment stopped
         * using.
         *
         * THIS IS NOT THE SIGN-IN PATH. It is the validator behind /up and
         * semantiq:health, whose whole job is to report that something is
         * wrong - and which must return 503 rather than 500 when a dependency
         * is down, because a monitor that receives a stack trace learns less
         * than one that receives "unhealthy". Letting the exception through
         * here would turn the one endpoint that exists to survive an outage
         * into a casualty of it.
         *
         * So the two callers get what each needs from the same source: this one
         * reports, the sign-in path refuses. The sentence is CHOSEN, never a
         * caught message, because a connection exception carries the host, the
         * database name and the user.
         */
        try {
            $identity = $this->identityConfiguration->resolve();
        } catch (Throwable) {
            return ['The identity configuration could not be read. Check the database connection above.'];
        }

        $where = $identity->source === PlatformSetting::SOURCE_STORE
            ? 'the stored platform configuration'
            : 'the server environment';

        return array_map(
            static fn (string $key): string => "Required identity configuration [{$key}] is missing or empty in {$where}.",
            $identity->missingKeys(),
        );
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
