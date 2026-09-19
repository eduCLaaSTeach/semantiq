<?php

declare(strict_types=1);

namespace Tests\Architecture;

use App\Modules\Platform\Security\SecurityEventLogger;
use App\Modules\Security\Catalogue\EventCatalogue;
use App\Modules\Security\Catalogue\OutcomeClass;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * D-111, ENFORCED AT BUILD TIME.
 *
 * AuditWriter refuses at RUNTIME to record a state change outside a
 * transaction, which catches any emitter a test exercises. This catches the one
 * no test exercises: a new state-changing emitter added outside the boundary
 * fails the build even if nothing ever calls it.
 *
 * WHY BOTH. The runtime guard is the real enforcement and proves the boundary
 * is taken in the paths that run. A static guard alone would be a guess about
 * reachability; a runtime guard alone would ship a hole in any code the suite
 * happens not to reach. Neither is sufficient and each covers the other's gap.
 *
 * WHAT THIS EXISTS BECAUSE OF. The DESIGN claimed mutation and evidence shared
 * a transaction. Fourteen emitters did not, across six modules, and the claim
 * had been reviewed and approved. Nothing in the code said otherwise, because
 * nothing in the code said anything.
 */
final class AuditAtomicityTest extends TestCase
{
    /**
     * Mutation: remove DB::transaction from any state-changing service method -
     * OrganisationService::updateProfile is the one the Product Owner found.
     */
    public function test_every_state_changing_emitter_is_inside_a_transaction(): void
    {
        $offenders = [];
        $checked = 0;

        foreach ($this->sourceFiles() as $file) {
            $source = (string) file_get_contents($file);

            if (! str_contains($source, '->record(')) {
                continue;
            }

            $methods = $this->methods($source);

            foreach ($this->emitSites($source) as $line => $events) {
                if (array_filter($events, fn (string $e): bool => $this->isStateChange($e)) === []) {
                    continue;
                }

                $checked++;
                $method = $this->methodAt($methods, $line);

                if ($method === null) {
                    $offenders[] = basename($file).' line '.($line + 1).' (no enclosing method)';

                    continue;
                }

                if (! $this->atomic($method, $methods, $source)) {
                    $offenders[] = basename($file).'::'.$method['name'];
                }
            }
        }

        $this->assertGreaterThan(
            40,
            $checked,
            'Almost no emit sites were examined, so this guard would pass against an empty codebase.'
        );

        $this->assertSame(
            [],
            $offenders,
            'A state-changing event is recorded outside a database transaction. A change already '
            ."committed cannot be rolled back when its evidence fails, so D-111's fail-closed "
            .'guarantee does not hold there. Wrap the whole operation in DB::transaction().'
        );
    }

    /**
     * AND NOTHING IS EXEMPT BY LIST.
     *
     * The two events that cannot use a transaction say so in their own
     * classification - a sign-in is StateChangeRecordedFirst because a session
     * is not in the database, and the identity health note is BestEffort
     * because it is a cache entry. Neither is an exception to the rule; each is
     * a different rule, declared where an implementer will read it.
     *
     * An exemption list is the thing somebody appends to at 5pm.
     */
    public function test_the_guard_has_no_exemption_list(): void
    {
        $source = (string) file_get_contents(base_path('app/Modules/Audit/Services/AuditWriter.php'));

        foreach (['EXEMPT', 'allowlist', 'ALLOW_LIST', 'skipAtomicity'] as $smell) {
            $this->assertStringNotContainsString($smell, $source);
        }

        $this->assertSame(
            OutcomeClass::StateChangeRecordedFirst,
            EventCatalogue::semanticsFor('auth.login.succeeded')->outcome,
        );
    }

    private function isStateChange(string $constant): bool
    {
        $value = constant(SecurityEventLogger::class.'::'.$constant);

        return EventCatalogue::semanticsFor($value)->outcome === OutcomeClass::StateChange;
    }

    /**
     * Every `->record(` line, with the event constants it could be emitting.
     *
     * A call site whose event is a VARIABLE is resolved from the constants
     * named in the enclosing method - StructureService and GroupService both
     * pass `$event` through a private helper, and ignoring those would skip the
     * majority of P1-01's emitters.
     *
     * @return array<int, list<string>>
     */
    private function emitSites(string $source): array
    {
        $lines = explode("\n", $source);
        $known = $this->declaredConstants();
        $sites = [];

        foreach ($lines as $i => $line) {
            if (! str_contains($line, '->record(')) {
                continue;
            }

            preg_match_all('/SecurityEventLogger::(\w+)/', $line, $direct);
            $events = array_values(array_intersect($direct[1], $known));

            if ($events === []) {
                // A variable event. Everything the enclosing method names.
                $window = implode("\n", array_slice($lines, max(0, $i - 40), 41));
                preg_match_all('/SecurityEventLogger::(\w+)/', $window, $nearby);
                $events = array_values(array_unique(array_intersect($nearby[1], $known)));
            }

            $sites[$i] = $events;
        }

        return $sites;
    }

    /** @return list<string> */
    private function declaredConstants(): array
    {
        $source = (string) file_get_contents(base_path('app/Modules/Platform/Security/SecurityEventLogger.php'));

        preg_match_all("/public const (\w+)\s*=\s*'[^']+';/", $source, $m);

        return $m[1];
    }

    /**
     * Every method in the file, with the line range it spans.
     *
     * @return list<array{name: string, visibility: string, from: int, to: int}>
     */
    private function methods(string $source): array
    {
        $lines = explode("\n", $source);
        $methods = [];

        foreach ($lines as $i => $line) {
            if (preg_match('/^\s*(public|private|protected)\s+(?:static\s+)?function\s+(\w+)\s*\(/', $line, $m) !== 1) {
                continue;
            }

            $depth = 0;
            $to = count($lines) - 1;

            for ($k = $i; $k < count($lines); $k++) {
                $depth += substr_count($lines[$k], '{') - substr_count($lines[$k], '}');

                if ($depth === 0 && $k > $i && str_contains($lines[$k], '}')) {
                    $to = $k;

                    break;
                }
            }

            $methods[] = ['name' => $m[2], 'visibility' => $m[1], 'from' => $i, 'to' => $to];
        }

        return $methods;
    }

    /** @param  list<array{name: string, visibility: string, from: int, to: int}>  $methods */
    private function methodAt(array $methods, int $line): ?array
    {
        $best = null;

        foreach ($methods as $method) {
            if ($line >= $method['from'] && $line <= $method['to']) {
                // The innermost enclosing method.
                if ($best === null || $method['from'] > $best['from']) {
                    $best = $method;
                }
            }
        }

        return $best;
    }

    /**
     * Does this method open a transaction, or is it only ever reached from one
     * that does?
     *
     * A PRIVATE HELPER IS FOLLOWED TO ITS CALLERS. ReviewDecisionService,
     * StructureService and GroupService all record through one, and refusing to
     * look would report every single one of them as a defect - a guard that
     * cries wolf is a guard somebody switches off.
     *
     * @param  array{name: string, visibility: string, from: int, to: int}  $method
     * @param  list<array{name: string, visibility: string, from: int, to: int}>  $methods
     */
    private function atomic(array $method, array $methods, string $source, int $depth = 0): bool
    {
        $lines = explode("\n", $source);
        $body = implode("\n", array_slice($lines, $method['from'], $method['to'] - $method['from'] + 1));

        if (str_contains($body, 'DB::transaction(')) {
            return true;
        }

        // The administrator-set boundary IS a transaction - it is where P1-05
        // serialises the lockout invariant, and it opens one around the caller's
        // operation. Naming the mechanism rather than the class keeps this from
        // becoming an exemption.
        if (str_contains($body, 'administrators->serialise(') || str_contains($body, '->serialise(')) {
            return true;
        }

        if ($method['visibility'] === 'public' || $depth >= 2) {
            return false;
        }

        $callers = array_values(array_filter(
            $methods,
            fn (array $m): bool => $m['name'] !== $method['name']
                && str_contains(
                    implode("\n", array_slice($lines, $m['from'], $m['to'] - $m['from'] + 1)),
                    '$this->'.$method['name'].'('
                )
        ));

        if ($callers === []) {
            return false;
        }

        foreach ($callers as $caller) {
            if (! $this->atomic($caller, $methods, $source, $depth + 1)) {
                return false;
            }
        }

        return true;
    }

    /** @return list<string> */
    private function sourceFiles(): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path('app/Modules')));

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }
}
