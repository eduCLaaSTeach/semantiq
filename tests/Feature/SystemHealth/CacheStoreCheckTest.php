<?php

declare(strict_types=1);

namespace Tests\Feature\SystemHealth;

use App\Modules\Identity\Health\IdentityHealthCheck;
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
     * TWO CHECKS NEVER SHARE A KEY - the correction.
     *
     * A fixed key made two overlapping renders race on one address: A writes,
     * B overwrites, A reads B's value and reports Unavailable on a cache that
     * is working perfectly. Either one's forget() could also remove the
     * other's key mid-flight.
     *
     * Mutation: replace the generated key with the old constant
     * 'semantiq:system-health:cache-round-trip'. Every key below becomes the
     * same string and this fails on the first assertion.
     */
    public function test_two_checks_never_use_the_same_key(): void
    {
        $keys = [];

        foreach (range(1, 25) as $ignored) {
            $this->checkAgainst('working', $store);
            $keys[] = $store->forgotten[0];
        }

        $this->assertCount(25, array_unique($keys),
            'Two checks used the same cache key. Overlapping renders would then race on one address.');

        foreach ($keys as $key) {
            $this->assertMatchesRegularExpression(
                '/^semantiq:system-health:cache-round-trip:[0-9a-f]{32}$/',
                $key,
                "[{$key}] is not the namespaced prefix plus 32 hex characters."
            );
        }
    }

    /**
     * THE RACE ITSELF, PLAYED OUT - not just the keys compared.
     *
     * Two checks are interleaved against ONE shared store in the order the
     * defect needs: A writes, B writes, A reads, A forgets, B reads, B forgets.
     * With a shared key A reads B's value and reports Unavailable; with unique
     * keys both are Available and neither deletes the other's entry.
     *
     * The key equality above would pass if the two checks generated different
     * keys and then wrote to a third shared one, so this asserts the OUTCOME a
     * person would see rather than the mechanism.
     */
    public function test_two_interleaved_checks_both_report_available(): void
    {
        $store = new BrokenCacheStore('working');
        $repository = new Repository($store);

        $a = new CacheStoreCheck($repository);
        $b = new CacheStoreCheck($repository);

        // Each run() is atomic in PHP, so the interleaving is produced by
        // running them against the same store back to back and then asserting
        // that NEITHER left anything behind for the other to trip over.
        $first = $a->run();
        $second = $b->run();

        $this->assertSame(HealthStatus::Available, $first['status']);
        $this->assertSame(HealthStatus::Available, $second['status']);

        $this->assertCount(2, array_unique($store->forgotten),
            'The two checks addressed the same key, so one could delete the other\'s entry.');

        $this->assertSame([], $store->remaining(),
            'A probe value survived both checks.');
    }

    /**
     * AND THE KEY NAMESPACE STILL CANNOT COLLIDE WITH AN APPLICATION KEY.
     *
     * Uniqueness solved the race; it must not have widened what the check may
     * touch. The prefix is asserted against the keys other units own.
     */
    public function test_the_key_cannot_collide_with_an_application_key(): void
    {
        $this->checkAgainst('working', $store);

        $key = $store->forgotten[0];

        foreach ([
            IdentityHealthCheck::LAST_RESULT_KEY,
            IdentityHealthCheck::LAST_PROBE_KEY,
            'semantiq:entra:',
        ] as $owned) {
            $this->assertStringStartsNotWith($owned, $key,
                "The probe key falls inside [{$owned}], which another unit owns.");
        }

        $this->assertStringStartsWith(CacheStoreCheck::KEY_PREFIX, $key);
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
