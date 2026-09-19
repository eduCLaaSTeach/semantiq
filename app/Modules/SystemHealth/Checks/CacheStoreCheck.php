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
 * The key is namespaced and unmistakable, carries a ten-second lifetime, and is
 * forgotten immediately. Nothing reads it back later, and it cannot collide
 * with an application key.
 */
final class CacheStoreCheck
{
    private const KEY = 'semantiq:system-health:cache-round-trip';

    private const LIFETIME_SECONDS = 10;

    public function __construct(private readonly Repository $cache) {}

    /** @return array{status: HealthStatus, explanation: string} */
    public function run(): array
    {
        $written = bin2hex(random_bytes(16));

        try {
            $this->cache->put(self::KEY, $written, self::LIFETIME_SECONDS);
            $read = $this->cache->get(self::KEY);
            $this->cache->forget(self::KEY);
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
}
