<?php

declare(strict_types=1);

namespace Tests\Architecture;

use App\Modules\Platform\Security\SecurityEventLogger;
use App\Modules\Security\Catalogue\EventCatalogue;
use PHPUnit\Framework\TestCase;

/**
 * N-SS24, N-SS28 to N-SS35 - THE STRUCTURAL GUARANTEES.
 *
 * Each of these makes a failure UNREPRESENTABLE rather than discouraged, in the
 * way SecurityEventLogger's closed key list already does for its own leak. They
 * read the source, because that is where the property lives: a runtime test
 * could only observe that nothing bad happened on the paths it happened to
 * exercise.
 */
final class SecurityStatusArchitectureTest extends TestCase
{
    private const MODULE = __DIR__.'/../../app/Modules/Security';

    private const JS = __DIR__.'/../../resources/js';

    /** @return array<string, string> path => source */
    private function moduleSources(): array
    {
        $sources = [];

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::MODULE));

        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $sources[$file->getPathname()] = (string) file_get_contents($file->getPathname());
            }
        }

        $this->assertNotSame([], $sources, 'The Security module has no source files to check.');

        return $sources;
    }

    /** Source with comments stripped: a guard must not be satisfied by prose. */
    private function withoutComments(string $source): string
    {
        $source = (string) preg_replace('#/\*.*?\*/#s', '', $source);

        return (string) preg_replace('#^\s*//.*$#m', '', $source);
    }

    /**
     * N-SS24. EVERY SECURITY STATUS ROUTE IS A GET, and no other verb exists
     * under the prefix.
     *
     * This is how "a mandatory control cannot be switched off from these
     * screens" becomes structural: there is no route that could carry the
     * switch. Mutation: add a POST for acknowledging an exception.
     */
    public function test_every_security_status_route_is_a_get(): void
    {
        $routes = $this->withoutComments((string) file_get_contents(__DIR__.'/../../routes/web.php'));

        $start = strpos($routes, "->prefix('security')");
        $this->assertNotFalse($start, 'The security prefix has gone from the route file.');

        /*
         * The group body, bounded by BRACE MATCHING rather than by a character
         * count. The first version of this guard took a fixed 1400 characters
         * and ran on into the identity group, reporting a POST that belongs to
         * P1-02 - a false red, and exactly the kind of assertion that is
         * satisfied by something other than what it claims to check.
         */
        $body = $this->groupBody($routes, $start);

        preg_match_all('/Route::(get|post|put|patch|delete|any|match)\(/', $body, $matches);

        $verbs = array_values(array_unique($matches[1]));

        $this->assertSame(
            ['get'],
            $verbs,
            'A verb other than GET appears under the Security Status prefix: '
            .implode(', ', $verbs).'. A mutating route is a route that could switch a mandatory '
            .'control off.',
        );

        $this->assertSame(
            4,
            substr_count($body, 'Route::get('),
            'The Security Status route set is no longer exactly four GETs.',
        );
    }

    /**
     * The body of the route group beginning at $start, by brace matching.
     */
    private function groupBody(string $routes, int $start): string
    {
        $open = strpos($routes, '{', $start);
        $this->assertNotFalse($open, 'The security route group has no body.');

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

        $this->fail('The security route group is not closed.');
    }

    /** And no controller in the module declares a writing method. */
    public function test_no_security_controller_offers_a_write(): void
    {
        foreach ($this->moduleSources() as $path => $source) {
            if (! str_contains($path, 'Http/Controllers')) {
                continue;
            }

            $code = $this->withoutComments($source);

            foreach (['store', 'update', 'destroy', 'acknowledge', 'dismiss', 'resolve', 'reveal'] as $verb) {
                $this->assertDoesNotMatchRegularExpression(
                    '/function\s+'.$verb.'\s*\(/',
                    $code,
                    basename($path)." declares a {$verb}() method. Security Status changes nothing.",
                );
            }
        }
    }

    /**
     * NOTHING IN THE MODULE WRITES ANYTHING - not the database, not the cache.
     *
     * D-81: no P1-06 cache. A cached posture is wrong at exactly the moment
     * something has just changed.
     */
    public function test_the_security_module_performs_no_write_and_holds_no_cache(): void
    {
        $forbidden = [
            '->save(' => 'writes a model',
            '->delete(' => 'deletes a model',
            '->insert(' => 'writes rows',
            '->truncate(' => 'destroys rows',
            'Cache::put' => 'caches posture, which D-81 forbids',
            'Cache::remember' => 'caches posture, which D-81 forbids',
            'Cache::forever' => 'caches posture, which D-81 forbids',
            'DB::statement' => 'executes raw SQL',
        ];

        foreach ($this->moduleSources() as $path => $source) {
            $code = $this->withoutComments($source);

            foreach ($forbidden as $needle => $why) {
                $this->assertStringNotContainsString(
                    $needle,
                    $code,
                    basename($path)." {$why}.",
                );
            }
        }

        // ->update( is checked separately: an Eloquent BUILDER update is a
        // write, but the word also appears in harmless identifiers.
        foreach ($this->moduleSources() as $path => $source) {
            $this->assertDoesNotMatchRegularExpression(
                '/->update\(\s*\[/',
                $this->withoutComments($source),
                basename($path).' performs a mass update.',
            );
        }
    }

    /**
     * N-SS28. P1-06 READS NO LOG FILE.
     *
     * Parsing the log would build a second audit system with worse properties
     * than the one P1-08 will build, present a partial history as though it
     * were complete, and put unbounded file I/O behind an administration
     * screen.
     */
    public function test_the_security_module_reads_no_log_file(): void
    {
        $forbidden = [
            'file_get_contents', 'fopen', 'fgets', 'fread', 'file(', 'SplFileObject',
            'storage_path', 'glob(', 'scandir', 'readfile', 'Storage::',
            'laravel.log', 'LogRecord', 'Log::getLogger',
        ];

        foreach ($this->moduleSources() as $path => $source) {
            $code = $this->withoutComments($source);

            foreach ($forbidden as $needle) {
                $this->assertStringNotContainsString(
                    $needle,
                    $code,
                    basename($path)." uses {$needle}. P1-08 owns durable audit; reading the log "
                    .'here would present a partial history as though it were complete.',
                );
            }
        }
    }

    /** N-SS29. P1-06 CREATES NO TABLE, and has no model to need one. */
    public function test_the_security_module_creates_no_table_and_has_no_model(): void
    {
        foreach ($this->moduleSources() as $path => $source) {
            $code = $this->withoutComments($source);

            $this->assertStringNotContainsString(
                'extends Model',
                $code,
                basename($path).' declares an Eloquent model. A posture module with a model is a '
                .'posture module that has started storing something.',
            );

            $this->assertStringNotContainsString('Schema::create', $code);
        }

        $this->assertDirectoryDoesNotExist(
            self::MODULE.'/Models',
            'The Security module has a Models directory.',
        );

        // And no migration mentions a security-status table.
        foreach (glob(__DIR__.'/../../database/migrations/*.php') ?: [] as $migration) {
            $contents = (string) file_get_contents($migration);

            foreach (['security_status', 'posture', 'security_exceptions', 'security_events'] as $table) {
                $this->assertStringNotContainsString(
                    $table,
                    $contents,
                    basename($migration)." creates a {$table} table. P1-06 adds no schema.",
                );
            }
        }
    }

    /**
     * N-SS30. P1-06 EMITS NO SECURITY EVENT, ON ANY PATH.
     *
     * access.state.unrecognised already exists and is emitted by AccessEngine -
     * the engine that MAKES access decisions. A reporting screen that recorded
     * one would be writing history it has also declared it cannot read.
     */
    public function test_the_security_module_records_no_security_event(): void
    {
        foreach ($this->moduleSources() as $path => $source) {
            $code = $this->withoutComments($source);

            $this->assertStringNotContainsString(
                '->record(',
                $code,
                basename($path).' records a security event.',
            );

            $this->assertStringNotContainsString('Log::info', $code);
            $this->assertStringNotContainsString('Log::warning', $code);
            $this->assertStringNotContainsString('Log::error', $code);
        }
    }

    /**
     * N-SS31. THE EVENT VOCABULARY IS UNCHANGED BY THIS UNIT.
     *
     * A unit that added an event or a context key would be changing a
     * P1-08-bound vocabulary for a reporting screen's convenience.
     *
     * THE FIRST HALF USED TO BE A TOTAL COUNT OF 71, which asserted something
     * slightly different from what it claimed: it failed whenever ANY later
     * unit declared an event, which is legitimate, rather than when P1-06
     * declared one, which is not. P1-07 tripped it by adding six of its own.
     * It now asserts what it always meant - that NO event is declared inside
     * the Security module - so it is strictly harder to satisfy by accident
     * and no longer needs editing every time another unit ships.
     *
     * Mutation: declare a `security.*` constant in SecurityEventLogger from
     * inside this unit, or add a 16th ALLOWED_KEY.
     */
    public function test_this_unit_adds_no_event_and_no_context_key(): void
    {
        $declaredHere = array_values(array_filter(
            SecurityEventLogger::events(),
            static fn (string $event): bool => str_starts_with($event, 'security.'),
        ));

        $this->assertSame(
            [],
            $declaredHere,
            'P1-06 declared an event of its own. It reports; it does not create a vocabulary.',
        );

        $sources = glob(__DIR__.'/../../app/Modules/Security/**/*.php', GLOB_BRACE) ?: [];

        foreach ($sources as $source) {
            $this->assertStringNotContainsString(
                'public const ',
                (string) preg_replace('/^(?!.*public const [A-Z_]+ = \'[a-z_.]+\';).*$/m', '', (string) file_get_contents($source)),
                "[{$source}] declares an event-shaped constant inside the Security module.",
            );
        }

        $reflection = new \ReflectionClass(SecurityEventLogger::class);

        $this->assertCount(
            15,
            (array) $reflection->getConstant('ALLOWED_KEYS'),
            'The permitted context key list has changed. P1-06 adds none, and neither may a later unit '
            .'without an explicit decision - P1-07 added six events and NO key.',
        );
    }

    /**
     * N-SS32 and N-SS33. THE EVENT MAPPING IS COMPLETE IN BOTH DIRECTIONS, and
     * no raw identifier is display text.
     *
     * A new event added by a future unit fails HERE, until somebody writes its
     * label - which is what stops one appearing raw on a screen.
     */
    public function test_every_declared_event_has_a_category_and_a_readable_label(): void
    {
        $declared = SecurityEventLogger::events();
        $mapped = array_keys(EventCatalogue::mapping());

        $missing = array_values(array_diff($declared, $mapped));
        $phantom = array_values(array_diff($mapped, $declared));

        $this->assertSame([], $missing, 'Declared events with no label: '.implode(', ', $missing));
        $this->assertSame([], $phantom, 'Labels for events that no longer exist: '.implode(', ', $phantom));

        $labels = [];

        foreach (EventCatalogue::mapping() as $key => [$category, $label]) {
            $this->assertContains($category, EventCatalogue::categories(), "{$key} has an unknown category.");

            $this->assertDoesNotMatchRegularExpression(
                '/^[a-z_]+(\.[a-z_]+)+$/',
                $label,
                "The label for {$key} is an internal identifier.",
            );

            $this->assertNotSame($key, $label);

            $labels[] = $label;
        }

        $this->assertSame(
            count($labels),
            count(array_unique($labels)),
            'Two events share a label, so a reader cannot tell them apart.',
        );
    }

    /** The catalogue is read from the logger at runtime and cannot drift. */
    public function test_the_events_screen_reads_the_logger_rather_than_a_copied_list(): void
    {
        $source = $this->withoutComments(
            (string) file_get_contents(self::MODULE.'/Catalogue/EventCatalogue.php')
        );

        $this->assertStringContainsString(
            'SecurityEventLogger::events()',
            $source,
            'The catalogue no longer reads the logger, so its coverage claim can go stale.',
        );
    }

    /**
     * N-SS34. EXACTLY ONE POSTURE EVALUATOR.
     *
     * The natural second one is not a rival service - it is a summary component
     * that takes rows and works out the worst, or a controller that re-filters
     * instead of reading the projection. Both would drift from Aggregation
     * within a unit or two.
     */
    public function test_the_precedence_rule_exists_in_exactly_one_place(): void
    {
        /*
         * WHAT A SECOND IMPLEMENTATION ACTUALLY LOOKS LIKE.
         *
         * An adapter naming two states is not one - deciding that nought
         * administrators is Critical and one is Attention is that control's own
         * rule, and it belongs in the adapter. A SECOND PRECEDENCE RULE is an
         * ORDERED LIST of states walked in order, and that is what this looks
         * for.
         *
         * The first version of this guard flagged any file mentioning two
         * states, which would have made every adapter a violation. Loosening it
         * was not the answer either: the correct narrowing found a REAL second
         * implementation in IdentityAdapter, which had its own copy of
         * Critical-then-Attention-then-Unverified. That adapter now calls
         * Aggregation.
         */
        $ordered = '/PostureState::Critical\s*,\s*PostureState::Attention/';

        $offenders = [];

        foreach ($this->moduleSources() as $path => $source) {
            if (str_ends_with($path, 'Aggregation.php')) {
                continue;
            }

            if (preg_match($ordered, $this->withoutComments($source)) === 1) {
                $offenders[] = basename($path);
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'A second precedence rule lives in: '.implode(', ', $offenders).'. It would drift '
            .'from Aggregation within a unit or two, and then two parts of one screen would '
            .'disagree about the same deployment.',
        );

        $aggregation = $this->withoutComments(
            (string) file_get_contents(self::MODULE.'/Posture/Aggregation.php')
        );

        $this->assertMatchesRegularExpression(
            $ordered,
            $aggregation,
            'Aggregation no longer holds the precedence order, so the guard above is checking '
            .'for something that exists nowhere.',
        );

        // And NOTHING in the JavaScript ranks or sorts by state.
        foreach ($this->javascriptSources() as $path => $source) {
            foreach (['.sort(', 'PRECEDENCE', "'critical'", '"critical"'] as $needle) {
                $this->assertStringNotContainsString(
                    $needle,
                    $this->withoutComments($source),
                    basename($path)." contains {$needle}. Posture is decided on the server; React "
                    .'chooses a class from a state it was GIVEN and computes nothing.',
                );
            }
        }
    }

    /**
     * A controller never touches the unprojected truth.
     *
     * This is what makes the projection impossible to forget: a controller has
     * NOTHING TO RENDER unless it has already projected.
     */
    public function test_no_controller_and_no_screen_touches_the_unprojected_report(): void
    {
        /*
         * WORD-BOUNDARY MATCHED. A plain substring search for "PostureRow"
         * matches the PostureRows COMPONENT, which is a perfectly legitimate
         * screen import - the first version of this guard failed on it and
         * would have pushed somebody to rename the component rather than fix
         * anything real.
         */
        $forbidden = [
            '/\bPostureReport\b/',
            '/\bPostureRow\b(?!s)/',
            '/\bMetricRow\b/',
            '/PostureState::/',
        ];

        foreach ($this->moduleSources() as $path => $source) {
            if (! str_contains($path, 'Http/Controllers')) {
                continue;
            }

            foreach ($forbidden as $pattern) {
                $this->assertDoesNotMatchRegularExpression(
                    $pattern,
                    $this->withoutComments($source),
                    basename($path)." references {$pattern}. A controller must receive an already "
                    .'projected ViewerReport, or the projection can be forgotten.',
                );
            }
        }

        foreach ($this->javascriptSources() as $path => $source) {
            foreach ($forbidden as $pattern) {
                $this->assertDoesNotMatchRegularExpression(
                    $pattern,
                    $source,
                    basename($path)." references {$pattern}.",
                );
            }
        }
    }

    /**
     * N-SS37. RENDERING TRIGGERS NO OUTBOUND NETWORK CALL.
     *
     * "Live" means recomputed from current authoritative state, not "phone
     * Microsoft on every page view". Mutation: call recheck() instead of
     * report().
     */
    public function test_nothing_in_the_module_probes_an_external_service(): void
    {
        $forbidden = [
            '->probe(', '->recheck(', 'Http::get', 'Http::post', 'Http::withToken',
            'curl_init', 'file_get_contents(\'http', 'EntraDiscovery',
        ];

        foreach ($this->moduleSources() as $path => $source) {
            $code = $this->withoutComments($source);

            foreach ($forbidden as $needle) {
                $this->assertStringNotContainsString(
                    $needle,
                    $code,
                    basename($path)." uses {$needle}. Opening a posture screen must not make an "
                    .'outbound request.',
                );
            }
        }
    }

    /** N-SS35. NO NUMERIC SCORE, anywhere. */
    public function test_there_is_no_numeric_score(): void
    {
        foreach (array_merge($this->moduleSources(), $this->javascriptSources()) as $path => $source) {
            $code = $this->withoutComments($source);

            foreach (['score', 'Score', '/100', 'percent', 'rating'] as $needle) {
                $this->assertStringNotContainsString(
                    $needle,
                    $code,
                    basename($path)." mentions {$needle}. A composite number compresses away the "
                    .'one thing an administrator needs: WHICH thing is wrong.',
                );
            }
        }
    }

    /** No indicator reads access_expectation - D-61 makes it context only. */
    public function test_nothing_reads_access_expectation(): void
    {
        foreach ($this->moduleSources() as $path => $source) {
            $this->assertStringNotContainsString(
                'access_expectation',
                $this->withoutComments($source),
                basename($path).' reads access_expectation. D-61 makes it context only and the '
                .'engine never reads it; a posture screen that did would be a second opinion '
                .'about access.',
            );
        }
    }

    /** @return array<string, string> */
    private function javascriptSources(): array
    {
        $sources = [];

        foreach (['Pages/Security', 'Components'] as $directory) {
            foreach (glob(self::JS.'/'.$directory.'/*.jsx') ?: [] as $file) {
                if ($directory === 'Components' && ! str_contains(basename($file), 'Security')
                    && ! str_contains(basename($file), 'Posture')) {
                    continue;
                }

                $sources[$file] = (string) file_get_contents($file);
            }
        }

        $this->assertNotSame([], $sources, 'No Security screens were found to check.');

        return $sources;
    }
}
