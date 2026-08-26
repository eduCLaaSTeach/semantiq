<?php

declare(strict_types=1);

namespace Tests\Feature\Privacy;

use App\Modules\Governance\Privacy\CollectorCatalogue;
use App\Modules\Governance\Privacy\ExclusionRegister;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The most important test in gate 4.
 *
 * When a subject access response says it collected everything held about a
 * person, this is what makes that claim checkable. Every table in the LIVE
 * schema must be either claimed by a collector or listed in the exclusion
 * register with a written reason.
 *
 * WHY IT READS THE SCHEMA RATHER THAN A LIST. A hard-coded expectation - "19
 * tables", "31 tables" - passes forever while the schema moves underneath it.
 * Gates 5, 6 and 7 will add tables. Each one must be a decision somebody takes,
 * not a gap nobody notices, and the way to force that is to fail the build
 * until they do.
 *
 * There is deliberately NO assertion anywhere in this file on how many tables
 * there are.
 */
class PrivacyCoverageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function every_schema_table_is_claimed_or_excluded_with_a_reason(): void
    {
        $catalogue = new CollectorCatalogue;
        $exclusions = new ExclusionRegister;

        $unaccounted = [];

        foreach ($this->schemaTables() as $table) {
            if ($catalogue->covers($table) || $exclusions->covers($table)) {
                continue;
            }

            $unaccounted[] = $table;
        }

        $this->assertSame([], $unaccounted, sprintf(
            "%d table(s) in the live schema are neither collected into a subject access response nor\n"
            ."explicitly excluded with a reason.\n\n"
            ."A table nobody has decided about is a table that silently will not appear in a response,\n"
            ."which makes every response's completeness claim untrue without anybody noticing.\n\n"
            ."Either give it a collector in CollectorCatalogue, or add it to ExclusionRegister with a\n"
            ."reason a person could defend to a regulator.\n\n%s",
            count($unaccounted),
            implode("\n", $unaccounted)
        ));
    }

    /**
     * Every exclusion must carry a reason somebody actually wrote.
     */
    #[Test]
    public function every_exclusion_states_why(): void
    {
        $thin = [];

        foreach ((new ExclusionRegister)->all() as $table => $reason) {
            if (strlen(trim($reason)) < 40) {
                $thin[] = $table.' ("'.trim($reason).'")';
            }
        }

        $this->assertSame([], $thin, sprintf(
            "%d exclusion(s) have no real reason recorded.\n"
            ."\"We do not collect that\" is not a position anybody can defend or challenge later.\n\n%s",
            count($thin),
            implode("\n", $thin)
        ));
    }

    /**
     * A table may be in both lists ONLY where that split is declared.
     *
     * `security_policies` and `secret_references` are excluded in their DETAIL
     * while still producing a band C item - the subject is told they changed a
     * security policy on a date, never which one or what value.
     *
     * That overlap is legitimate and declared. An UNDECLARED overlap is how a
     * table ends up excluded on paper and collected in fact, so it fails here
     * rather than passing as an assumed intention.
     */
    #[Test]
    public function the_only_overlaps_are_the_declared_detail_only_ones(): void
    {
        $catalogue = new CollectorCatalogue;
        $exclusions = new ExclusionRegister;

        $overlap = array_values(array_intersect($catalogue->tables(), $exclusions->tables()));
        sort($overlap);

        $declared = $exclusions->detailOnly();
        sort($declared);

        $this->assertSame($declared, $overlap, sprintf(
            "A table appears in both the collector catalogue and the exclusion register without being\n"
            ."declared as a detail-only split.\n\n"
            ."Declared: %s\nActual:   %s",
            implode(', ', $declared),
            implode(', ', $overlap)
        ));
    }

    /**
     * Nothing is claimed or excluded that does not exist.
     *
     * A stale entry is not harmless: it makes the coverage arithmetic look
     * complete while a real table goes unnoticed, which is the exact failure
     * the first test exists to prevent.
     */
    #[Test]
    public function nothing_is_claimed_or_excluded_that_is_not_in_the_schema(): void
    {
        $catalogue = new CollectorCatalogue;
        $exclusions = new ExclusionRegister;

        $schema = $this->schemaTables();

        $phantom = array_values(array_diff(
            array_merge($catalogue->tables(), $exclusions->tables()),
            $schema,
        ));

        $this->assertSame([], $phantom, sprintf(
            "%d table(s) are claimed or excluded but do not exist in the schema.\n"
            ."A stale entry makes the coverage arithmetic look complete while a real table goes\n"
            ."unnoticed.\n\n%s",
            count($phantom),
            implode("\n", $phantom)
        ));
    }

    /**
     * Every table in the live schema, in a driver-independent way.
     *
     * @return list<string>
     */
    private function schemaTables(): array
    {
        $tables = array_map(
            static fn (array $table): string => $table['name'],
            Schema::getTables(),
        );

        sort($tables);

        return array_values(array_filter(
            $tables,
            static fn (string $name): bool => ! str_starts_with($name, 'sqlite_'),
        ));
    }
}
