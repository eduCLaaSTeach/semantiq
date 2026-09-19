<?php

declare(strict_types=1);

namespace Tests\Feature\SystemHealth;

use App\Modules\Identity\Health\IdentityHealthCheck;
use App\Modules\Identity\Health\IdentityHealthReport;
use App\Modules\Platform\Health\HealthInspector;
use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;
use App\Modules\Platform\Identity\Microsoft\EntraDiscovery;
use App\Modules\SystemHealth\Report\HealthStatus;
use App\Modules\SystemHealth\Report\SystemHealthReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Support\EntraTokenFactory;
use Tests\Support\OrganisationFactory;
use Tests\TestCase;

/**
 * H7a AND H15 - THE BOUNDARY, AND THE ONE CASE WHOSE MUTATION IS A PREVIOUSLY
 * APPROVED DESIGN.
 *
 * The first draft of the P1-09 design sourced the sign-in row from
 * IdentityHealthCheck::report() and stated, on the strength of P1-02's own
 * class comment, that opening System Health contacts nobody. The code says
 * otherwise: report() calls trustAvailability(), and when either cached value
 * is absent that method calls EntraDiscovery::metadata() and signingKeys(),
 * both Cache::remember wrappers around an outbound Http::get to Microsoft with
 * a ten-second timeout. HealthInspector::inspect() reached the same call a
 * second way, through forInspector().
 *
 * THE CACHES ARE EMPTIED FIRST, and that is the whole point. A test that runs
 * with trust already cached takes the network-free branch and passes on the
 * ORIGINAL code, which would make it a test that measures something other than
 * what it names - the M-A5 failure from P1-08, repeated. Cold cache is the
 * condition under which the defect actually fired.
 *
 * TWO INDEPENDENT OBSERVATIONS, because one is not enough here:
 *
 *   1. every outbound request is RECORDED BY A CLOSURE, not asserted by one.
 *      Calling $this->fail() inside the fake would throw an AssertionFailedError
 *      that trustAvailability()'s `catch (Throwable)` swallows silently - the
 *      trap this arrangement exists to avoid. The record survives the catch.
 *
 *   2. the discovery cache keys are still EMPTY afterwards. A read-through that
 *      succeeded would have populated them, so this catches a call the request
 *      recorder somehow missed.
 */
final class NetworkBoundaryTest extends TestCase
{
    use RefreshDatabase;

    private EntraTokenFactory $entra;

    private OrganisationFactory $make;

    /** @var list<string> */
    private array $contacted = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->entra = new EntraTokenFactory;
        $this->entra->configure();
        $this->make = new OrganisationFactory;

        // COLD. Not a fresh cache that happens to be warm from a fixture: the
        // discovery metadata, the signing keys, the last result and the last
        // probe are all gone, which is a fresh deployment, a cleared cache, or
        // simply 24 hours of quiet.
        Cache::flush();

        $this->contacted = [];

        Http::fake(function ($request) {
            $this->contacted[] = $request->url();

            return Http::response([], 200);
        });
    }

    private function metadataKey(): string
    {
        return 'semantiq:entra:'.EntraTokenFactory::TENANT.':metadata';
    }

    private function jwksKey(): string
    {
        return 'semantiq:entra:'.EntraTokenFactory::TENANT.':jwks';
    }

    private function assertNothingWasContacted(string $because): void
    {
        $this->assertSame([], $this->contacted, $because.' It contacted: '.implode(', ', $this->contacted));

        $this->assertNull(Cache::get($this->metadataKey()), 'Discovery metadata was fetched and cached.');
        $this->assertNull(Cache::get($this->jwksKey()), 'Signing keys were fetched and cached.');
    }

    /**
     * THE PREMISE, asserted rather than assumed.
     *
     * If report() did not reach the network on a cold cache, every case below
     * would pass for the wrong reason and this whole file would be theatre. So
     * the defect is demonstrated first: calling report() with the caches empty
     * DOES contact Microsoft.
     *
     * If this case ever starts failing, P1-02 has changed and the guards below
     * need re-reading - they do not become wrong, but they stop being load
     * bearing, and that is worth knowing.
     */
    public function test_the_defect_this_guards_against_is_real(): void
    {
        app(IdentityHealthCheck::class)->report();

        $this->assertNotSame(
            [],
            $this->contacted,
            'report() no longer reaches the network on a cold cache. The guards below may no longer be load bearing.'
        );
    }

    /** H7a. storedReport() contacts nobody, on a cold cache. */
    public function test_stored_report_makes_no_outbound_call_on_a_cold_cache(): void
    {
        $stored = app(IdentityHealthCheck::class)->storedReport();

        $this->assertSame(IdentityHealthReport::NOT_CHECKED, $stored->state);
        $this->assertNothingWasContacted('storedReport() reached the network.');
    }

    /** And it does not touch EntraDiscovery even when a result IS stored. */
    public function test_stored_report_makes_no_outbound_call_when_a_result_is_stored(): void
    {
        Cache::put(IdentityHealthCheck::LAST_RESULT_KEY, [
            'state' => IdentityHealthReport::DEGRADED,
            'at' => now()->subHour()->toIso8601String(),
        ], now()->addDay());

        $stored = app(IdentityHealthCheck::class)->storedReport();

        $this->assertSame(IdentityHealthReport::DEGRADED, $stored->state);
        $this->assertNothingWasContacted('storedReport() reached the network with a stored result present.');
    }

    /**
     * M-4 CAUGHT THIS ONE, AND IT SURVIVED THE FIRST TIME.
     *
     * The absence cases were written against the RENDERED ROW. Loosening
     * storedReport()'s check from "one of P1-02's three states" to "not null"
     * left them all green: an unrecognised state came back from storedReport()
     * unchanged, and the row's match() fell through its default arm to Not
     * checked, so the screen looked right while the method returned rubbish.
     * The test was measuring the row's default, not the method's guard - the
     * M-A5 failure from P1-08, repeated in a new place.
     *
     * So the contract is asserted WHERE IT LIVES. storedReport() returns one of
     * P1-02's four constants and nothing else, whatever is in the cache -
     * because the row is not its only caller, and the next caller will not have
     * a default arm to hide behind.
     *
     * Mutation: `$trustworthy = $state !== null;`
     */
    public function test_stored_report_returns_one_of_the_four_states_whatever_is_cached(): void
    {
        $permitted = [
            IdentityHealthReport::HEALTHY,
            IdentityHealthReport::DEGRADED,
            IdentityHealthReport::FAILED,
            IdentityHealthReport::NOT_CHECKED,
        ];

        $rubbish = [
            'an unrecognised state' => ['state' => 'probably_fine', 'at' => '2026-01-01T00:00:00+00:00'],
            'a state from a future release' => ['state' => 'partially_degraded'],
            'an empty state' => ['state' => ''],
            'a numeric state' => ['state' => 1],
            'an array state' => ['state' => ['healthy']],
            'a state with the right word inside a longer one' => ['state' => 'not_healthy'],
            /*
             * M-4b. `true` is the one value that LOOSE comparison accepts:
             * in_array(true, ['healthy', ...], false) is true, because true ==
             * any non-empty string. Without this entry, dropping the strict
             * flag survived every case here - PHP 8 no longer compares 1 or ''
             * equal to 'healthy', so none of the other rubbish above
             * distinguishes strict matching from loose. A guard that cannot
             * tell the two apart is not guarding the comparison.
             */
            'a boolean true' => ['state' => true],
            'a boolean false' => ['state' => false],
            'a non-array entry' => 'healthy',
            'an entry with no state at all' => ['at' => '2026-01-01T00:00:00+00:00'],
        ];

        foreach ($rubbish as $description => $stored) {
            Cache::flush();
            Cache::put(IdentityHealthCheck::LAST_RESULT_KEY, $stored, now()->addDay());

            $state = app(IdentityHealthCheck::class)->storedReport()->state;

            $this->assertContains(
                $state,
                $permitted,
                "With {$description} in the cache, storedReport() returned [{$state}]."
            );

            $this->assertSame(
                IdentityHealthReport::NOT_CHECKED,
                $state,
                "With {$description} in the cache, storedReport() did not report Not checked."
            );
        }

        $this->assertNothingWasContacted('storedReport() reached the network while reading rubbish.');
    }

    /**
     * A STATE WITHOUT A USABLE TIME IS NOT A RESULT - correction 2.
     *
     * The first version validated the state and took the instant on trust, so
     * ['state' => 'healthy'] with no 'at' rendered as
     * "Microsoft Entra ID - Available" with NO AGE BENEATH IT. The age is the
     * screen's only defence against a stale cached answer, so an answer whose
     * age is unknown must not be shown as an answer at all. D-114.
     *
     * Mutation: remove the timestamp validation - accept the state alone, or
     * restore `checkedAt: is_string($at) ? $at : null`. Every case below that
     * carries a bad instant then reports a real state with no age.
     */
    public function test_a_stored_state_without_a_usable_time_is_not_checked(): void
    {
        $withoutATime = [
            'no at key at all' => ['state' => IdentityHealthReport::HEALTHY],
            'a null at' => ['state' => IdentityHealthReport::HEALTHY, 'at' => null],
            'an empty at' => ['state' => IdentityHealthReport::DEGRADED, 'at' => ''],
            'a whitespace at' => ['state' => IdentityHealthReport::FAILED, 'at' => '   '],
            'a word where a time belongs' => ['state' => IdentityHealthReport::HEALTHY, 'at' => 'recently'],
            'a malformed instant' => ['state' => IdentityHealthReport::HEALTHY, 'at' => '2026-13-45T99:99:99'],
            'an integer at' => ['state' => IdentityHealthReport::DEGRADED, 'at' => 1700000000],
            'an array at' => ['state' => IdentityHealthReport::FAILED, 'at' => ['2026-01-01']],
        ];

        foreach ($withoutATime as $description => $stored) {
            Cache::flush();
            Cache::put(IdentityHealthCheck::LAST_RESULT_KEY, $stored, now()->addDay());

            $reported = app(IdentityHealthCheck::class)->storedReport();

            $this->assertSame(
                IdentityHealthReport::NOT_CHECKED,
                $reported->state,
                "With {$description}, a state was reported without a time to qualify it."
            );

            $this->assertNull(
                $reported->checkedAt,
                "With {$description}, an age was invented."
            );
        }

        $this->assertNothingWasContacted('Validating the stored instant reached the network.');
    }

    /** Garbage state plus a perfectly good time is still Not checked. */
    public function test_a_garbage_state_with_a_valid_time_is_not_checked(): void
    {
        Cache::put(IdentityHealthCheck::LAST_RESULT_KEY, [
            'state' => 'probably_fine',
            'at' => now()->subHour()->toIso8601String(),
        ], now()->addDay());

        $reported = app(IdentityHealthCheck::class)->storedReport();

        $this->assertSame(IdentityHealthReport::NOT_CHECKED, $reported->state);
        $this->assertNull($reported->checkedAt);
        $this->assertNothingWasContacted('Reading a garbage state reached the network.');
    }

    /**
     * ON THE RENDERED ROW, because that is where a Product Owner would have
     * seen it.
     *
     * The method-level cases above prove the contract; this proves the screen
     * honours it. A state with no time must render Not checked, never
     * Available, and must carry no age.
     */
    public function test_the_screen_never_shows_a_state_without_its_age(): void
    {
        Cache::put(IdentityHealthCheck::LAST_RESULT_KEY, [
            'state' => IdentityHealthReport::HEALTHY,
        ], now()->addDay());

        $row = null;

        foreach (app(SystemHealthReport::class)->toArray() as $area) {
            foreach ($area['rows'] as $candidate) {
                if ($candidate['name'] === 'Microsoft Entra ID') {
                    $row = $candidate;
                }
            }
        }

        $this->assertNotNull($row);
        $this->assertSame(HealthStatus::NotChecked->value, $row['status'],
            'A stored state with no measurement time rendered as a result.');
        $this->assertNull($row['checkedAt']);

        /*
         * AND THE RULE HOLDS BOTH WAYS, on every row: a row carrying a real
         * status must either be measured live (no age) or carry an age. There
         * is no third shape, and the third shape is the defect.
         */
        foreach (app(SystemHealthReport::class)->toArray() as $area) {
            foreach ($area['rows'] as $candidate) {
                if ($candidate['name'] !== 'Microsoft Entra ID') {
                    continue;
                }

                $isAResult = in_array($candidate['status'], [
                    HealthStatus::Available->value,
                    HealthStatus::Degraded->value,
                    HealthStatus::Unavailable->value,
                ], true);

                if ($isAResult) {
                    $this->assertNotNull($candidate['checkedAt'],
                        'The stored sign-in row reported a result with no age.');
                }
            }
        }
    }

    /** And a good value still comes back unchanged, or the case above is met by returning a constant. */
    public function test_stored_report_returns_each_real_state_unchanged(): void
    {
        foreach ([
            IdentityHealthReport::HEALTHY,
            IdentityHealthReport::DEGRADED,
            IdentityHealthReport::FAILED,
        ] as $state) {
            Cache::flush();
            Cache::put(IdentityHealthCheck::LAST_RESULT_KEY, [
                'state' => $state,
                'at' => now()->subHour()->toIso8601String(),
            ], now()->addDay());

            $reported = app(IdentityHealthCheck::class)->storedReport();

            $this->assertSame($state, $reported->state);
            $this->assertNotNull($reported->checkedAt, 'A real stored state came back without its age.');
        }
    }

    /** inspectLocal() contacts nobody, because it never reaches identity. */
    public function test_inspect_local_makes_no_outbound_call_on_a_cold_cache(): void
    {
        $report = app(HealthInspector::class)->inspectLocal();

        $this->assertArrayNotHasKey('identity', $report->checks);
        $this->assertNothingWasContacted('inspectLocal() reached the network.');
    }

    /**
     * H15. RENDERING THE WHOLE PAGE CONTACTS NOBODY.
     *
     * Across the entire render, not just the sign-in row - the Service Health
     * roll-up reached Microsoft by its own route in the first draft, and a
     * guard on one row would not have seen it.
     *
     * Mutation: source the sign-in row from report(), or the roll-up from
     * inspect(). Either is the previous design, and either must fail here.
     */
    public function test_rendering_system_health_contacts_nobody(): void
    {
        $admin = $this->make->user(administrator: true);

        $response = $this->withSession([
            EnsureSessionIsCurrent::SESSION_USER_ID => $admin->id,
            EnsureSessionIsCurrent::SESSION_AUTHENTICATED_AT => now()->toIso8601String(),
        ])->get('/console/system-health');

        $response->assertOk();

        $this->assertNothingWasContacted('Rendering System Health reached the network.');
    }

    /** The row reads Not checked rather than inventing an answer from silence. */
    public function test_a_cold_cache_renders_the_sign_in_row_as_not_checked(): void
    {
        $statuses = [];

        foreach (app(SystemHealthReport::class)->toArray() as $area) {
            foreach ($area['rows'] as $row) {
                $statuses[$row['name']] = $row['status'];
            }
        }

        $this->assertSame(HealthStatus::NotChecked->value, $statuses['Microsoft Entra ID']);
        $this->assertNothingWasContacted('Assembling the report reached the network.');
    }

    /**
     * /up AND semantiq:health ARE UNCHANGED - they still include identity, and
     * they still reach it.
     *
     * The correction removed identity from a NEW projection. If it had removed
     * it from inspect() as well, the deployment gate would have stopped noticing
     * a broken sign-in and nothing in P1-09's own tests would have said so.
     *
     * Mutation: drop identity from inspect() while "simplifying".
     */
    public function test_the_deployment_verdict_still_includes_identity(): void
    {
        $report = app(HealthInspector::class)->inspect();

        $this->assertSame(
            ['database', 'migrations', 'configuration', 'storage', 'assets', 'identity'],
            array_keys($report->checks),
            'inspect() no longer returns the six checks /up and semantiq:health are built on.'
        );
    }

    /** And the two key sets differ by exactly the one key. */
    public function test_the_local_projection_differs_from_the_verdict_by_identity_alone(): void
    {
        $inspector = app(HealthInspector::class);

        $this->assertSame(
            ['identity'],
            array_values(array_diff(
                array_keys($inspector->inspect()->checks),
                array_keys($inspector->inspectLocal()->checks),
            )),
            'inspectLocal() is no longer inspect() minus identity alone.'
        );

        $this->assertSame(
            [],
            array_diff(
                array_keys($inspector->inspectLocal()->checks),
                array_keys($inspector->inspect()->checks),
            ),
            'inspectLocal() invented a check inspect() does not have.'
        );
    }

    /**
     * The values agree, too - not just the keys. A projection that ran a
     * different storage check would satisfy the key comparison above.
     */
    public function test_the_projection_returns_the_same_answers_as_the_verdict(): void
    {
        $inspector = app(HealthInspector::class);

        $verdict = $inspector->inspect()->checks;
        $local = $inspector->inspectLocal()->checks;

        foreach ($local as $name => $check) {
            $this->assertSame($verdict[$name]['ok'], $check['ok'], "[{$name}] disagrees between the two readings.");
            $this->assertSame($verdict[$name]['detail'], $check['detail'], "[{$name}] disagrees between the two readings.");
        }
    }

    /**
     * storedReport()'s OWN BODY names the discovery client nowhere.
     *
     * This was first written as "System Health never RESOLVES EntraDiscovery",
     * which failed - and failed correctly. IdentityHealthCheck takes
     * EntraDiscovery as a constructor argument, so resolving the check
     * constructs the client whatever any method does with it. CONSTRUCTING IT
     * IS NOT CALLING IT: the constructor stores a tenant string and opens
     * nothing. An assertion that cannot hold is not a stricter guard, it is a
     * guard that has to be deleted, and deleting it would have taken the real
     * boundary with it.
     *
     * So the boundary is asserted where it actually lives: the method's source,
     * with comments stripped so prose cannot satisfy it. The runtime cases
     * above prove no request happens; this proves there is no path by which one
     * could.
     *
     * Mutation: reintroduce $this->trustAvailability() into storedReport().
     */
    public function test_stored_report_names_no_network_path_in_its_own_body(): void
    {
        $source = (string) file_get_contents(
            __DIR__.'/../../../app/Modules/Identity/Health/IdentityHealthCheck.php'
        );

        $source = (string) preg_replace('#/\*.*?\*/#s', '', $source);
        $source = (string) preg_replace('#^\s*//.*$#m', '', $source);

        $start = strpos($source, 'public function storedReport()');
        $this->assertNotFalse($start, 'storedReport() has gone from IdentityHealthCheck.');

        $open = strpos($source, '{', $start);
        $depth = 0;
        $end = $open;

        for ($i = $open; $i < strlen($source); $i++) {
            if ($source[$i] === '{') {
                $depth++;
            } elseif ($source[$i] === '}') {
                $depth--;

                if ($depth === 0) {
                    $end = $i;
                    break;
                }
            }
        }

        $body = substr($source, $open, $end - $open);

        $this->assertNotSame('', trim($body), 'The method body could not be read, so this guard proved nothing.');

        foreach ([
            '$this->discovery', 'trustAvailability', 'metadata(', 'signingKeys(',
            'probe(', 'cachedMetadata', 'cachedSigningKeys', 'Http::', 'report(',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $body,
                "storedReport() names [{$forbidden}], which is how a network path gets back in."
            );
        }
    }
}
