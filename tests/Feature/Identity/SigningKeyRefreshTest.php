<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Modules\Platform\Identity\AuthenticationFailed;
use App\Modules\Platform\Identity\Microsoft\EntraDiscovery;
use App\Modules\Platform\Identity\Microsoft\IdTokenValidator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Support\EntraTokenFactory;
use Tests\TestCase;

/**
 * D-32 - a forced refresh must not destroy a still-usable key set.
 *
 * The defect: signingKeys(forceRefresh: true) took the refetch lock, then
 * Cache::forget() the JWKS, then fetched. The validator calls it for exactly one
 * reason - a token arrived carrying a signing key we do not recognise - which is
 * what a key rotation looks like. So the destructive step ran at the worst
 * possible moment, and a network blip during the fetch destroyed keys that were
 * still perfectly good. The person holding the new key failed either way, which
 * is correct; everyone else signing in on a previously known key failed too, and
 * the five-minute lock then blocked another attempt for five minutes.
 *
 * K4 is the case this correction lives or dies by, and it is the one to read
 * first: preserving the old cache must never become a way to accept a key we
 * never fetched.
 */
final class SigningKeyRefreshTest extends TestCase
{
    private EntraTokenFactory $entra;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entra = new EntraTokenFactory;
        $this->entra->configure();

        Cache::flush();
    }

    private function discovery(): EntraDiscovery
    {
        return new EntraDiscovery(EntraTokenFactory::TENANT);
    }

    private function cacheKey(string $suffix): string
    {
        return 'semantiq:entra:'.EntraTokenFactory::TENANT.':'.$suffix;
    }

    /**
     * K1. A failed forced refresh preserves the cached key set byte for byte.
     *
     * Mutation: Cache::forget($key) before the fetch - the defect itself. The
     * cached set is then gone and this fails.
     */
    public function test_a_failed_forced_refresh_preserves_the_cached_keys(): void
    {
        $known = $this->entra->jwks();

        Cache::put($this->cacheKey('jwks'), $known, now()->addHours(24));

        Http::fake(['*' => Http::response('', 503)]);

        $returned = $this->discovery()->signingKeys(forceRefresh: true);

        $this->assertSame($known, Cache::get($this->cacheKey('jwks')), 'The cached key set was destroyed by a failed refresh.');
        $this->assertSame($known, $returned, 'A failed refresh returned something other than the keys we still hold.');
    }

    /** The same, when the network throws rather than answering. */
    public function test_a_thrown_forced_refresh_preserves_the_cached_keys(): void
    {
        $known = $this->entra->jwks();

        Cache::put($this->cacheKey('jwks'), $known, now()->addHours(24));

        Http::fake(fn () => throw new \RuntimeException('connection reset'));

        $this->assertSame($known, $this->discovery()->signingKeys(forceRefresh: true));
        $this->assertSame($known, Cache::get($this->cacheKey('jwks')));
    }

    /**
     * K2. A successful forced refresh replaces the cached set.
     *
     * Mutation: keep the old set on success. Rotation would then never take
     * effect, which is the opposite failure and just as bad.
     */
    public function test_a_successful_forced_refresh_replaces_the_cached_keys(): void
    {
        $stale = $this->entra->jwks(['kid' => 'yesterdays-key']);
        $fresh = $this->entra->jwks();

        Cache::put($this->cacheKey('jwks'), $stale, now()->addHours(24));

        $this->entra->fakeEndpoints(keys: $fresh);

        $returned = $this->discovery()->signingKeys(forceRefresh: true);

        $this->assertSame($fresh, $returned);
        $this->assertSame($fresh, Cache::get($this->cacheKey('jwks')));
    }

    /**
     * An EMPTY key set is a failed refresh, not a successful one.
     *
     * A 200 carrying no keys is not a rotation, it is an answer we cannot use.
     * Writing it to the cache would destroy a working set just as thoroughly as
     * the forget-then-fetch D-32 removed - the defect would simply have moved
     * from the network path to the parsing one.
     *
     * Found by re-reading the change adversarially before merging, not by a
     * mutation. Mutation: accept an empty set as fresh.
     */
    public function test_an_empty_key_set_does_not_replace_the_cache(): void
    {
        $known = $this->entra->jwks();

        Cache::put($this->cacheKey('jwks'), $known, now()->addHours(24));
        Cache::put($this->cacheKey('metadata'), [
            'issuer' => $this->entra->issuer(),
            'jwks_uri' => 'https://login.microsoftonline.test/keys',
        ], now()->addHours(24));

        Http::fake(['*/keys' => Http::response(['keys' => []])]);

        $returned = $this->discovery()->signingKeys(forceRefresh: true);

        $this->assertSame($known, Cache::get($this->cacheKey('jwks')), 'An empty key set replaced a working one.');
        $this->assertSame($known, $returned);
    }

    /**
     * K3. A sign-in on a key we still hold works after a failed refresh.
     *
     * This is the consequence the whole correction is for: one unlucky request
     * during a rotation must not take everybody else's sign-in with it.
     */
    public function test_a_token_on_a_still_cached_key_validates_after_a_failed_refresh(): void
    {
        Cache::put($this->cacheKey('jwks'), $this->entra->jwks(), now()->addHours(24));
        Cache::put($this->cacheKey('metadata'), [
            'issuer' => $this->entra->issuer(),
            'jwks_uri' => 'https://login.microsoftonline.test/keys',
        ], now()->addHours(24));

        // The refresh that fails - triggered by somebody else's unknown key.
        Http::fake(['*' => Http::response('', 503)]);
        $this->discovery()->signingKeys(forceRefresh: true);

        // ...and now an ordinary sign-in on a key we still hold.
        $claims = $this->validator()->validate($this->entra->token(), 'test-nonce');

        $this->assertSame(EntraTokenFactory::TENANT, $claims['tid']);
    }

    /**
     * K4. THE CASE THIS LIVES OR DIES BY.
     *
     * The token that triggered the refresh must still fail when Microsoft cannot
     * supply its key. Preserving the old cache is a reliability fix; it must
     * never become a way to accept a key we never fetched.
     *
     * Mutation: match the kid loosely, or fall back to the first cached key.
     */
    public function test_the_unknown_key_token_still_fails_when_microsoft_cannot_be_reached(): void
    {
        Cache::put($this->cacheKey('jwks'), $this->entra->jwks(), now()->addHours(24));
        Cache::put($this->cacheKey('metadata'), [
            'issuer' => $this->entra->issuer(),
            'jwks_uri' => 'https://login.microsoftonline.test/keys',
        ], now()->addHours(24));

        Http::fake(['*' => Http::response('', 503)]);

        // Signed with a DIFFERENT key, announcing a kid we have never seen.
        $stranger = new EntraTokenFactory;

        $this->expectException(AuthenticationFailed::class);

        $this->validator()->validate($stranger->token(['iss' => $this->entra->issuer()]), 'test-nonce');
    }

    /**
     * K5. The provider-wide refetch lock still bounds forced refreshes.
     *
     * Mutation: remove the Cache::add lock. The second refresh then reaches the
     * network and the request count doubles.
     */
    public function test_the_refetch_lock_still_bounds_forced_refreshes(): void
    {
        $this->entra->fakeEndpoints();

        Cache::put($this->cacheKey('jwks'), $this->entra->jwks(['kid' => 'old']), now()->addHours(24));

        $discovery = $this->discovery();

        $discovery->signingKeys(forceRefresh: true);
        $afterFirst = count(Http::recorded());

        $discovery->signingKeys(forceRefresh: true);

        $this->assertSame(
            $afterFirst,
            count(Http::recorded()),
            'A second forced refresh reached the network within the lock window.'
        );
    }

    /**
     * K6. Nothing about validation is relaxed by a failed refresh.
     *
     * A wrong issuer, a wrong audience and a wrong tenant are each still
     * refused, on the preserved cache, exactly as they are on a fresh one.
     */
    public function test_validation_is_not_weakened_by_a_failed_refresh(): void
    {
        Cache::put($this->cacheKey('jwks'), $this->entra->jwks(), now()->addHours(24));
        Cache::put($this->cacheKey('metadata'), [
            'issuer' => $this->entra->issuer(),
            'jwks_uri' => 'https://login.microsoftonline.test/keys',
        ], now()->addHours(24));

        Http::fake(['*' => Http::response('', 503)]);
        $this->discovery()->signingKeys(forceRefresh: true);

        $refused = 0;

        foreach ([
            ['iss' => 'https://login.microsoftonline.com/somebody-else/v2.0'],
            ['aud' => 'a-different-application'],
            ['tid' => '99999999-9999-9999-9999-999999999999'],
            ['nonce' => 'a-replayed-nonce'],
        ] as $tampered) {
            try {
                $this->validator()->validate($this->entra->token($tampered), 'test-nonce');
                $this->fail('A token with '.json_encode(array_keys($tampered)).' wrong was accepted.');
            } catch (AuthenticationFailed) {
                $refused++;
            }
        }

        $this->assertSame(4, $refused);
    }

    private function validator(): IdTokenValidator
    {
        return new IdTokenValidator(
            $this->discovery(),
            EntraTokenFactory::CLIENT_ID,
            EntraTokenFactory::TENANT,
        );
    }
}
