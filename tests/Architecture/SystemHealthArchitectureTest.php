<?php

declare(strict_types=1);

namespace Tests\Architecture;

use App\Modules\Platform\Security\SecurityEventLogger;
use App\Modules\SystemHealth\Report\HealthRow;
use App\Modules\SystemHealth\Report\HealthStatus;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionEnum;

/**
 * THE STRUCTURAL GUARANTEES OF P1-09.
 *
 * Each one makes a failure UNREPRESENTABLE rather than discouraged, the way
 * SecurityEventLogger's closed key list already does for its own leak. They
 * read the SOURCE, because that is where the property lives: a runtime test can
 * only observe that nothing bad happened on the paths it happened to exercise,
 * and the network boundary in particular has to survive a refactor no runtime
 * test covers.
 */
final class SystemHealthArchitectureTest extends TestCase
{
    private const MODULE = __DIR__.'/../../app/Modules/SystemHealth';

    /** @return array<string, string> path => source, comments stripped */
    private function moduleSources(): array
    {
        $sources = [];

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::MODULE));

        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $sources[$file->getPathname()] = $this->withoutComments(
                    (string) file_get_contents($file->getPathname())
                );
            }
        }

        $this->assertNotSame([], $sources, 'The System Health module has no source files to check.');

        return $sources;
    }

    /** A guard must not be satisfied by prose. */
    private function withoutComments(string $source): string
    {
        $source = (string) preg_replace('#/\*.*?\*/#s', '', $source);

        return (string) preg_replace('#^\s*//.*$#m', '', $source);
    }

    /** The body of the route group beginning at $start, by brace matching. */
    private function groupBody(string $routes, int $start): string
    {
        $open = strpos($routes, '{', $start);
        $this->assertNotFalse($open, 'The System Health route group has no body.');

        $depth = 0;

        for ($i = $open; $i < strlen($routes); $i++) {
            if ($routes[$i] === '{') {
                $depth++;
            }

            if ($routes[$i] === '}') {
                $depth--;

                if ($depth === 0) {
                    return substr($routes, $open, $i - $open + 1);
                }
            }
        }

        $this->fail('The System Health route group is not closed.');
    }

    /**
     * SystemHealthHasNoWriteRoute. ONE GET, AS AN EQUALITY.
     *
     * Not "no DELETE" - exactly one GET and nothing else - so a POST added
     * later fails the build rather than quietly becoming a restart button. The
     * group body is found by BRACE MATCHING rather than a character count: the
     * first version of the equivalent guard in P1-06 took a fixed 1400
     * characters, ran on into the next group and reported a POST belonging to
     * another unit. A false red is also an assertion satisfied by something
     * other than what it claims to check.
     *
     * Mutation: add Route::post('recheck', ...) under this prefix.
     */
    public function test_system_health_is_exactly_one_get(): void
    {
        $routes = $this->withoutComments((string) file_get_contents(__DIR__.'/../../routes/web.php'));

        $start = strpos($routes, "->prefix('system-health')");
        $this->assertNotFalse($start, 'The system-health prefix has gone from the route file.');

        $body = $this->groupBody($routes, $start);

        preg_match_all('/Route::(get|post|put|patch|delete|any|match)\(/', $body, $matches);

        $this->assertSame(['get'], array_values(array_unique($matches[1])),
            'A verb other than GET appears under the System Health prefix: '.implode(', ', $matches[1])
            .'. A page that can change something is a page that can break something.');

        $this->assertSame(1, substr_count($body, 'Route::get('),
            'The System Health route set is no longer exactly one GET.');
    }

    /**
     * SystemHealthReachesNoNetwork. THE CORRECTION, ENFORCED STRUCTURALLY.
     *
     * The module may not name EntraDiscovery, the Http facade,
     * IdentityHealthCheck::report(), forInspector(), or
     * HealthInspector::inspect() - every one of which is, or leads to, an
     * outbound call to Microsoft on a cold discovery cache.
     *
     * A STATIC GUARD BECAUSE THE RUNTIME ONE IS NOT ENOUGH. The boundary tests
     * prove no request happens on the paths they exercise; this proves there is
     * no path. A refactor that reintroduces report() would be caught here even
     * if no runtime case happened to cover the new caller.
     *
     * Mutation: change storedReport() back to report() in SystemHealthReport.
     */
    public function test_the_module_names_no_path_to_microsoft(): void
    {
        $forbidden = [
            'EntraDiscovery' => 'the discovery client, whose metadata() and signingKeys() are read-through HTTP',
            'Http::' => 'the HTTP facade',
            'Illuminate\Support\Facades\Http' => 'the HTTP facade',
            '->report()' => 'IdentityHealthCheck::report(), which calls trustAvailability() and asks Microsoft on a cold cache',
            'forInspector' => 'forInspector(), which calls report()',
            '->inspect()' => 'HealthInspector::inspect(), which includes identity and therefore reaches report()',
            'recheck(' => 'the live probe',
            'file_get_contents(\'http' => 'a raw fetch',
            'curl_' => 'a raw fetch',
        ];

        foreach ($this->moduleSources() as $path => $source) {
            foreach ($forbidden as $needle => $why) {
                $this->assertStringNotContainsString(
                    $needle,
                    $source,
                    basename($path)." names [{$needle}] - {$why}. Opening System Health must contact nobody."
                );
            }
        }
    }

    /** And it calls the two network-free entry points that replaced them. */
    public function test_the_module_uses_the_network_free_entry_points(): void
    {
        $all = implode("\n", $this->moduleSources());

        $this->assertStringContainsString('storedReport()', $all,
            'Nothing in the module reads the stored identity answer any more.');
        $this->assertStringContainsString('inspectLocal()', $all,
            'Nothing in the module reads the local-only projection any more.');
    }

    /**
     * SystemHealthRowIsAllowlisted. FOUR PROPERTIES. A FIFTH FAILS THE BUILD.
     *
     * D-119 and D-120 are satisfied by there being nowhere to put a hostname, a
     * path, a driver or an exception - not by remembering to strip them.
     *
     * Mutation: add a `public ?string $detail` to HealthRow.
     */
    public function test_the_row_carries_exactly_four_properties(): void
    {
        $properties = array_map(
            fn ($p): string => $p->getName(),
            (new ReflectionClass(HealthRow::class))->getProperties()
        );

        sort($properties);

        $this->assertSame(['checkedAt', 'explanation', 'name', 'status'], $properties,
            'HealthRow has grown a field. The leak is unrepresentable only while there is nowhere to put it.');

        $this->assertTrue((new ReflectionClass(HealthRow::class))->isReadOnly(),
            'HealthRow is no longer readonly, so a row can be edited after the check returned it.');
    }

    /**
     * THE SIX STATUSES AND THEIR BACKING VALUES - correction 3.
     *
     * The strings are the wire format the React layer matches on, so a rename
     * on either side must be a visible change rather than a silent one.
     *
     * Mutation: change a backing value, or drop NotChecked.
     */
    public function test_the_six_statuses_and_their_backing_values_are_fixed(): void
    {
        $this->assertSame('string', (string) (new ReflectionEnum(HealthStatus::class))->getBackingType(),
            'HealthStatus is no longer string-backed, so it cannot cross to the screen as data.');

        $cases = [];

        foreach (HealthStatus::cases() as $case) {
            $cases[$case->name] = $case->value;
        }

        $this->assertSame([
            'Available' => 'available',
            'Degraded' => 'degraded',
            'Unavailable' => 'unavailable',
            'NotConfigured' => 'not_configured',
            'NotApplicable' => 'not_applicable',
            'NotChecked' => 'not_checked',
        ], $cases);
    }

    /**
     * SystemHealthAddsNoEventKey. D-116 and D-127.
     *
     * Reading a health page is not a security event. An event here would write
     * a row into the audit chain on every refresh, and the catalogue's closed
     * key list is the reason a token cannot be logged by accident - it is not
     * widened to make a screen feel instrumented.
     *
     * Mutation: add SecurityEventLogger::SYSTEM_HEALTH_VIEWED.
     */
    public function test_the_unit_adds_no_security_event_key(): void
    {
        $this->assertSame(77, count(SecurityEventLogger::events()),
            'The event catalogue changed size. P1-09 adds no event key.');

        foreach ($this->moduleSources() as $path => $source) {
            $this->assertStringNotContainsString('SecurityEventLogger', $source,
                basename($path).' records a security event. Rendering a health page records nothing.');
            $this->assertStringNotContainsString('AuditWriter', $source,
                basename($path).' writes audit evidence. Rendering a health page records nothing.');
        }
    }

    /**
     * SystemHealthDuplicatesNoCheck. One authoritative check per fact.
     *
     * The module may not reimplement a check that already exists elsewhere: no
     * second `select 1`, no second is_writable(), no second manifest lookup, no
     * second chain walk. A screen may PROJECT a check into an area; it may
     * never reimplement one to appear there.
     *
     * Mutation: inline a storage check so the row does not depend on
     * HealthInspector.
     */
    public function test_the_module_reimplements_no_existing_check(): void
    {
        foreach ($this->moduleSources() as $path => $source) {
            foreach ([
                'select 1' => 'the database check',
                'is_writable' => 'the storage check',
                'build/manifest.json' => 'the assets check',
                'repositoryExists' => 'the migration check',
                'getMigrationFiles' => 'the migration check',
                'previous_hash' => 'the audit chain walk',
                'row_hash' => 'the audit chain walk',
            ] as $needle => $owner) {
                $this->assertStringNotContainsString(
                    $needle,
                    $source,
                    basename($path)." reimplements {$owner}. Two authoritative answers to one fact is the "
                    .'duplication this unit exists to prevent.'
                );
            }
        }
    }

    /**
     * SystemHealthImplementsNoSessionDriverAdapter - correction 2.
     *
     * The session check branches on `database` and returns Not checked
     * otherwise. There is no redis, file, memcached or dynamodb branch, because
     * none of those stores is deployed and a transaction cannot roll back a
     * write to any of them.
     *
     * Mutation: add a `case 'file':` arm that writes a session file.
     */
    public function test_the_session_check_carries_no_adapter_for_an_undeployed_store(): void
    {
        $source = $this->moduleSources()[self::MODULE.'/Checks/SessionStoreCheck.php'] ?? null;

        $this->assertNotNull($source, 'SessionStoreCheck has moved; this guard no longer reads it.');

        foreach (['redis', 'Redis', 'dynamodb', 'DynamoDb', 'memcached', 'Memcached', 'file_put_contents'] as $needle) {
            $this->assertStringNotContainsString($needle, $source,
                "SessionStoreCheck names [{$needle}]. Phase 1 supports the deployed database store only.");
        }

        $this->assertStringContainsString("SUPPORTED_DRIVER = 'database'", $source);
        $this->assertStringContainsString('NotChecked', $source,
            'An unsupported driver must still be reported as Not checked.');
    }

    /**
     * NO SCHEMA. P1-09 creates no table, no column and no migration.
     *
     * The DESIGN raised this as a finding rather than adding a table, and this
     * is the guard on it: the module holds no model and no migration, and the
     * migration directory gained nothing for this unit.
     */
    public function test_the_unit_adds_no_schema(): void
    {
        foreach ($this->moduleSources() as $path => $source) {
            $this->assertStringNotContainsString('extends Model', $source,
                basename($path).' declares a model. P1-09 stores nothing.');
            $this->assertStringNotContainsString('Schema::', $source,
                basename($path).' touches the schema. P1-09 creates no table, and D-128 forbids DDL.');
        }

        $migrations = glob(__DIR__.'/../../database/migrations/*.php') ?: [];

        foreach ($migrations as $migration) {
            $this->assertStringNotContainsStringIgnoringCase('system_health', (string) $migration,
                'A System Health migration exists. The design records that no schema is needed.');
        }
    }

    /**
     * NO CONTROLLER IN THE MODULE DECLARES A WRITING METHOD.
     *
     * The route set proves nothing is reachable; this proves nothing is written
     * that a future route could reach.
     */
    public function test_no_controller_offers_a_write(): void
    {
        foreach ($this->moduleSources() as $path => $source) {
            if (! str_contains($path, 'Http/Controllers')) {
                continue;
            }

            foreach (['store', 'update', 'destroy', 'restart', 'clear', 'acknowledge', 'dismiss', 'run'] as $verb) {
                $this->assertDoesNotMatchRegularExpression(
                    '/function\s+'.$verb.'\s*\(/',
                    $source,
                    basename($path)." declares a {$verb}() method. System Health changes nothing."
                );
            }
        }
    }

    /**
     * THE MODULE NAMES NO BUSINESS MODEL.
     *
     * D-124: System Health reports that the evidence store is working. It shows
     * no evidence, no learner, no customer and no business record.
     */
    public function test_the_module_names_no_business_model(): void
    {
        foreach ($this->moduleSources() as $path => $source) {
            foreach ([
                'BusinessDomain', 'Organisation', 'LegalEntity', 'BusinessUnit', 'Department',
                'Team', 'RoleAssignment', 'DomainEntitlement', 'AuditEvent', 'AccessReview',
            ] as $model) {
                $this->assertStringNotContainsString($model, $source,
                    basename($path)." names [{$model}]. System Health reports on services, never on business records.");
            }
        }
    }
}
