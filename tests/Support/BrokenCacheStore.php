<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Contracts\Cache\Store;
use RuntimeException;

/**
 * A cache STORE that behaves the way a broken cache actually behaves.
 *
 * THE STORE, NOT THE REPOSITORY, and the difference matters. The repository is
 * Laravel's real Illuminate\Cache\Repository, wrapping this - so put(), get()
 * and forget() go through the same code the application uses, with the same
 * serialisation and the same event dispatching, and only the storage underneath
 * is broken. Faking the repository would have tested a class the application
 * never runs.
 *
 * The four behaviours are the ones a real cache outage produces, and only one
 * of them throws:
 *
 *   FORGETFUL   accepts every write, reports success, stores nothing. A full
 *               or misconfigured store. Throws nothing.
 *   EMPTY       returns null for everything. Throws nothing.
 *   LYING       returns somebody else's value. Throws nothing.
 *   THROWING    the connection is refused.
 *
 * Three of the four satisfy "no exception was thrown" perfectly, which is why
 * the check compares the value rather than merely retrieving it.
 */
final class BrokenCacheStore implements Store
{
    public const FORGETFUL = 'forgetful';

    public const EMPTY = 'empty';

    public const LYING = 'lying';

    public const THROWING = 'throwing';

    /**
     * IGNORES_FORGET stores and returns values perfectly and simply never
     * removes one. It reports success, as a cache that cannot unlink does.
     *
     * It is the only behaviour here that is not an outage. It is the behaviour
     * of a FILE cache - which is what production runs - when the unlink fails:
     * a permissions change, a full disk, a directory somebody moved. Nothing
     * throws, nothing is logged, and the stale entry stays perfectly readable.
     *
     * It exists because "we call forget() on change" is the obvious way to
     * invalidate a cached health result, and this is the cache against which
     * that obvious way silently does nothing.
     */
    public const IGNORES_FORGET = 'ignores_forget';

    /** @var array<string, mixed> */
    private array $values = [];

    /** @var list<string> */
    public array $forgotten = [];

    /** @var list<mixed> */
    public array $written = [];

    public function __construct(private readonly string $behaviour) {}

    public function get($key): mixed
    {
        return match ($this->behaviour) {
            self::THROWING => throw new RuntimeException('connection to redis://secret-host.internal:6379 refused'),
            self::EMPTY, self::FORGETFUL => null,
            self::LYING => 'a value belonging to somebody else',
            self::IGNORES_FORGET => $this->values[$key] ?? null,
            default => $this->values[$key] ?? null,
        };
    }

    public function put($key, $value, $seconds): bool
    {
        $this->written[] = $value;

        if ($this->behaviour === self::THROWING) {
            throw new RuntimeException('connection to redis://secret-host.internal:6379 refused');
        }

        if ($this->behaviour !== self::FORGETFUL) {
            $this->values[$key] = $value;
        }

        return true;
    }

    public function forget($key): bool
    {
        $this->forgotten[] = $key;

        if ($this->behaviour !== self::IGNORES_FORGET) {
            unset($this->values[$key]);
        }

        // TRUE EITHER WAY. A cache that could tell you it had failed to remove
        // something would be a cache you could rely on forget() with.
        return true;
    }

    /** @param array<int, string> $keys */
    public function many(array $keys): array
    {
        return array_map(fn (string $key): mixed => $this->get($key), $keys);
    }

    /** @param array<string, mixed> $values */
    public function putMany(array $values, $seconds): bool
    {
        foreach ($values as $key => $value) {
            $this->put($key, $value, $seconds);
        }

        return true;
    }

    public function increment($key, $value = 1): int|bool
    {
        return false;
    }

    public function decrement($key, $value = 1): int|bool
    {
        return false;
    }

    public function forever($key, $value): bool
    {
        return $this->put($key, $value, 0);
    }

    public function touch($key, $seconds): bool
    {
        return true;
    }

    public function flush(): bool
    {
        $this->values = [];

        return true;
    }

    public function getPrefix(): string
    {
        return '';
    }

    /**
     * What is still held. Used to prove a health check left nothing behind -
     * and that one check did not delete another's entry.
     *
     * @return array<string, mixed>
     */
    public function remaining(): array
    {
        return $this->values;
    }
}
