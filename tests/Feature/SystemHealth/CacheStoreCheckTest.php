<?php

declare(strict_types=1);

namespace Tests\Feature\SystemHealth;

use App\Modules\SystemHealth\Checks\CacheStoreCheck;
use App\Modules\SystemHealth\Report\HealthStatus;
use Illuminate\Cache\Repository;
use Tests\Support\BrokenCacheStore;
use Tests\TestCase;

/**
 * H4. THE VALUE IS COMPARED, NOT MERELY RETRIEVED.
 *
 * THIS FILE EXISTS BECAUSE A MUTATION SURVIVED. Removing the comparison
 * entirely - so the check reports Available whenever nothing threw - passed the
 * whole suite, because nothing anywhere exercised a cache that answers without
 * failing. That is the weaker assertion this check was written to avoid, and
 * the tests had it anyway.
 *
 * A broken cache rarely throws. It returns null for everything, or serves a
 * stale value, or silently drops writes under memory pressure. Every one of
 * those satisfies "no exception was thrown" perfectly.
 *
 * The repository is an INTERFACE, so each case is a real cache boundary
 * replaced by one that behaves the way a broken cache behaves - no config
 * change, nothing global, nothing that outlives the case.
 */
final class CacheStoreCheckTest extends TestCase
{
    private function checkAgainst(string $behaviour, ?BrokenCacheStore &$store = null): array
    {
        $store = new BrokenCacheStore($behaviour);

        return (new CacheStoreCheck(new Repository($store)))->run();
    }

    /** Honest: what went in comes back. */
    public function test_a_working_cache_is_available(): void
    {
        $this->assertSame(HealthStatus::Available, $this->checkAgainst('working')['status']);
    }

    /**
     * THE MUTATION THAT SURVIVED. Three of these throw nothing at all, so
     * "no exception was thrown" reports every one of them healthy.
     *
     * Mutation: remove the `$read !== $written` comparison. Before this file
     * existed it survived the entire suite, because nothing anywhere exercised
     * a cache that answers wrongly without failing.
     */
    public function test_a_cache_that_answers_wrongly_is_unavailable(): void
    {
        foreach ([
            BrokenCacheStore::EMPTY => 'returns null for everything',
            BrokenCacheStore::LYING => 'serves somebody else\'s value',
            BrokenCacheStore::FORGETFUL => 'accepts the write and stores nothing',
        ] as $behaviour => $description) {
            $this->assertSame(
                HealthStatus::Unavailable,
                $this->checkAgainst($behaviour)['status'],
                "A cache that {$description} was reported as healthy."
            );
        }
    }

    /** A cache that throws is Unavailable, and leaks nothing from the message. */
    public function test_a_cache_that_throws_is_unavailable_and_leaks_nothing(): void
    {
        $result = $this->checkAgainst(BrokenCacheStore::THROWING);

        $this->assertSame(HealthStatus::Unavailable, $result['status']);

        foreach (['secret-host.internal', 'redis', '6379', 'RuntimeException'] as $secret) {
            $this->assertStringNotContainsStringIgnoringCase($secret, $result['explanation']);
        }
    }

    /**
     * NOTHING IS LEFT IN THE CACHE. The key is namespaced, unmistakable, and
     * forgotten on the way out.
     *
     * Mutation: drop the forget() call. The health key would then sit in the
     * real store for its full lifetime, written afresh on every render.
     */
    public function test_the_probe_key_is_namespaced_and_forgotten(): void
    {
        $this->checkAgainst('working', $store);

        $this->assertCount(1, $store->forgotten, 'The health check did not forget its key.');
        $this->assertStringStartsWith('semantiq:system-health:', $store->forgotten[0]);
    }

    /**
     * A FRESH VALUE EVERY RUN, so a stale read cannot pass by accident.
     *
     * Mutation: write a constant. A cache still serving last week's copy of
     * that constant would then read as a successful round trip.
     */
    public function test_each_run_writes_a_different_value(): void
    {
        $this->checkAgainst('working', $first);
        $this->checkAgainst('working', $second);

        $this->assertCount(1, $first->written);
        $this->assertCount(1, $second->written);
        $this->assertNotSame($first->written[0], $second->written[0], 'The same value is written every time.');
    }
}
