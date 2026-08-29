<?php

declare(strict_types=1);

namespace Tests\Feature\Schema;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Every migration name written into an operator SQL script must exist.
 *
 * WHY THIS EXISTS. The R1.4c-i pre-check has been wrong about this twice, in
 * opposite directions, and both times a correct production system was reported
 * as broken or a broken one would have been reported as correct.
 *
 *   First  it expected SEVEN migrations from LIKE patterns that only four
 *          filenames can ever match. Production returned 4, which was right,
 *          and the script called it STOP.
 *
 *   Second the fix replaced the patterns with SIX exact names and silently
 *          dropped `add_module_and_outcome_indexes_to_audit_events_table`. It
 *          would then PASS with an R1.4b migration missing, while claiming the
 *          previous batches were fully migrated.
 *
 * Both had one cause: a list written by hand. An operator cannot check it -
 * that is the whole point of giving them a script - so the repository has to.
 *
 * WHAT THIS ASSERTS. Every `'2026_..._name'` literal in the operator scripts
 * names a migration that actually exists, and the two batch groups are
 * COMPLETE: a script naming six of seven R1.4a/R1.4b migrations fails here.
 */
class MigrationExpectationTest extends TestCase
{
    /** Operator scripts that embed migration names. */
    private const SCRIPTS = [
        'doc/execution/CHECK-R1.4c-i-PRE.sql',
        'doc/execution/CONFIRM-R1.4a-R1.4b.sql',
        'doc/execution/CONFIRM-MIGRATION-LEDGER.sql',
    ];

    /**
     * @return list<string>
     */
    private function migrationsOnDisk(): array
    {
        $names = [];

        foreach (glob(database_path('migrations/*.php')) as $path) {
            $names[] = basename($path, '.php');
        }

        sort($names);

        return $names;
    }

    /**
     * Migration names quoted inside a script.
     *
     * @return list<string>
     */
    private function namesIn(string $script): array
    {
        $sql = file_get_contents(base_path($script));

        $this->assertIsString($sql, $script.' could not be read');

        /* Ignore comment lines: the file explains its own history, and those
         * sentences mention names that are deliberately no longer expected. */
        $sql = preg_replace('/^\s*--.*$/m', '', $sql);

        preg_match_all("/'([0-9]{4}_[0-9]{2}_[0-9]{2}_[0-9]{6}_[a-z0-9_]+)'/", (string) $sql, $m);

        return array_values(array_unique($m[1]));
    }

    #[Test]
    public function every_migration_named_in_an_operator_script_exists(): void
    {
        $onDisk = array_flip($this->migrationsOnDisk());
        $offenders = [];

        foreach (self::SCRIPTS as $script) {
            foreach ($this->namesIn($script) as $name) {
                if (! isset($onDisk[$name])) {
                    $offenders[] = basename($script).' -> '.$name;
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "These operator scripts name migrations that do not exist:\n  ".implode("\n  ", $offenders)
        );
    }

    #[Test]
    public function the_batch_groups_named_in_the_operator_scripts_are_complete(): void
    {
        $all = $this->migrationsOnDisk();

        /* The batches, derived from the repository rather than listed here.
         * A new R1.4a/R1.4b or R1.4c-i migration therefore fails this test
         * until the operator scripts are updated to name it. */
        $groups = [
            'R1.4a + R1.4b' => array_values(array_filter(
                $all,
                fn (string $n): bool => str_starts_with($n, '2026_08_27_') || str_starts_with($n, '2026_08_28_')
            )),
            'R1.4c-i' => array_values(array_filter(
                $all,
                fn (string $n): bool => str_starts_with($n, '2026_08_29_')
            )),
        ];

        $this->assertCount(7, $groups['R1.4a + R1.4b'], 'the R1.4a/R1.4b group changed shape');
        $this->assertCount(3, $groups['R1.4c-i'], 'the R1.4c-i group changed shape');

        $pre = $this->namesIn('doc/execution/CHECK-R1.4c-i-PRE.sql');
        $confirm = $this->namesIn('doc/execution/CONFIRM-R1.4a-R1.4b.sql');

        foreach ([['CHECK-R1.4c-i-PRE.sql', $pre], ['CONFIRM-R1.4a-R1.4b.sql', $confirm]] as [$label, $named]) {
            foreach ($groups as $group => $expected) {
                /* A script that mentions a group at all must name ALL of it.
                 * Six of seven is the exact defect this test exists for. */
                $overlap = array_intersect($expected, $named);

                if ($overlap === []) {
                    continue;
                }

                $missing = array_values(array_diff($expected, $named));

                $this->assertSame(
                    [],
                    $missing,
                    $label.' names part of the '.$group.' batch but omits:'."\n  ".implode("\n  ", $missing)
                );
            }
        }
    }
}
