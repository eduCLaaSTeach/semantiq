<?php

declare(strict_types=1);

namespace App\Modules\SystemHealth\Checks;

use App\Modules\SystemHealth\Report\HealthStatus;
use Illuminate\Contracts\Cache\Repository;
use Throwable;

/**
 * A ROUND TRIP, AND THE VALUE IS COMPARED.
 *
 * Merely retrieving is the weaker assertion and the one that passes on a broken
 * cache: a store that returns null for everything satisfies "no exception was
 * thrown" perfectly well. So the value written is the value looked for, and
 * anything else - null, a stale value, a different value - is Unavailable.
 *
 * A UNIQUE KEY PER INVOCATION, AND THAT IS A CORRECTION.
 *
 * The first version used one fixed key for every check. Two System Health
 * renders overlapping by milliseconds then raced on it:
 *
 *   A writes its value -> B overwrites with its own -> A reads B's value ->
 *   A reports UNAVAILABLE, on a cache that is working perfectly.
 *
 * Worse, either request's forget() removed the other's key mid-flight, so the
 * loser could just as easily read null. A health screen that invents an
 * incident when two people open it at once is worse than no health screen: the
 * first false red is the one that teaches everybody to ignore the next real
 * one.
 *
 * The fix is a key nobody else can be using, NOT A LOCK. Serialising renders
 * behind a mutex to protect a throwaway diagnostic value would make the screen
 * slower and could itself stall under contention - a health page must not
 * become the thing that needs diagnosing. Two checks that never touch the same
 * key cannot race at all, which is the boundary rather than a guard around one.
 *
 * Every key is namespaced and unmistakable, carries a ten-second lifetime, and
 * is forgotten immediately. Nothing reads one back later, and none can collide
 * with an application key.
 */
final class CacheStoreCheck
{
    /**
     * The shared namespace. NEVER USED ON ITS OWN - see key().
     *
     * It is a prefix rather than a key so that the namespace stays greppable
     * and assertable while no two invocations share an address.
     */
    public const KEY_PREFIX = 'semantiq:system-health:cache-round-trip:';

    private const LIFETIME_SECONDS = 10;

    public function __construct(private readonly Repository $cache) {}

    /** @return array{status: HealthStatus, explanation: string} */
    public function run(): array
    {
        $key = $this->key();
        $written = bin2hex(random_bytes(16));

        try {
            $this->cache->put($key, $written, self::LIFETIME_SECONDS);
            $read = $this->cache->get($key);
            $this->cache->forget($key);
        } catch (Throwable) {
            // The exception can carry a path, a host or a connection string.
            return [
                'status' => HealthStatus::Unavailable,
                'explanation' => 'A test value could not be stored and read back, so parts of the application may be slower or may not work.',
            ];
        }

        if ($read !== $written) {
            return [
                'status' => HealthStatus::Unavailable,
                'explanation' => 'A test value was stored but did not come back unchanged, so cached information cannot be relied on.',
            ];
        }

        return [
            'status' => HealthStatus::Available,
            'explanation' => 'A test value was stored, read back unchanged and discarded.',
        ];
    }

    /**
     * One address per invocation, from the same source of randomness the value
     * uses. 32 hex characters is not a number two concurrent renders reach.
     *
     * The value stays random and is still compared strictly: a unique key stops
     * two health checks colliding, and the comparison is what still catches a
     * store that answers this key with somebody else's value or with null.
     */
    private function key(): string
    {
        return self::KEY_PREFIX.bin2hex(random_bytes(16));
    }
}
