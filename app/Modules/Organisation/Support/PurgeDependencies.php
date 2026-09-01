<?php

declare(strict_types=1);

namespace App\Modules\Organisation\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What is still using a structural record — D-24.
 *
 * D-24 permits a permanent delete only when a master record is "completely safe
 * to remove": no children, no associations, and **no other durable P1-01 record
 * referencing it**. That last clause is the one a hand-written checklist gets
 * wrong, because it is a claim about the whole schema and a checklist only knows
 * what was true when it was written.
 *
 * So the references are READ FROM THE SCHEMA, not listed here. Every foreign key
 * pointing at the record's table is a dependency, whatever added it and whenever
 * it was added. A table introduced by a later unit blocks the purge on the day
 * its migration lands, with no change to this file — which is the only version
 * of this guard that stays true.
 *
 * Status is deliberately not consulted. An inactive department still counts, and
 * an ended membership still counts: D-24 says so in as many words, and the point
 * of retaining that history is that it survives.
 */
final class PurgeDependencies
{
    /**
     * How to say, in business language, that rows of a table are in the way.
     *
     * D-24 §5: no database or foreign-key terminology reaches the screen. Each
     * entry is a complete predicate, so the sentences read as sentences rather
     * than as a list of table names with a count bolted on.
     *
     * `counted` phrases take the row count; the others state that history exists
     * without quantifying it, which is how the Product Owner worded it and how a
     * person would say it.
     *
     * A referencing table with no entry here still blocks the purge — the count
     * is what refuses, not the label. It falls back to a generic phrase, and
     * `PurgeGuardTest` fails until a real one is written, so the fallback is a
     * safety net rather than somewhere to leave things.
     *
     * @var array<string, array{one: string, many: string, counted: bool}>
     */
    private const LABELS = [
        'departments' => [
            'one' => 'it has %d department',
            'many' => 'it has %d departments',
            'counted' => true,
        ],
        'teams' => [
            'one' => 'it has %d team',
            'many' => 'it has %d teams',
            'counted' => true,
        ],
        'team_memberships' => [
            'one' => 'membership history exists',
            'many' => 'membership history exists',
            'counted' => false,
        ],
        'business_unit_legal_entity' => [
            'one' => 'it is associated with %d legal entity',
            'many' => 'it is associated with %d legal entities',
            'counted' => true,
        ],
    ];

    /**
     * Business-language reasons this record cannot be purged. Empty means safe.
     *
     * @return list<string>
     */
    public static function blocking(Model $node, bool $locking = false): array
    {
        $blockers = [];

        foreach (self::referencesTo($node->getTable()) as [$table, $column]) {
            $query = DB::table($table)->where($column, $node->getKey());

            /*
             * Inside the write transaction the count must see rows committed
             * after the transaction opened. Under MySQL's REPEATABLE READ a
             * plain SELECT reads the transaction's snapshot and would miss
             * exactly the dependency this second check exists to catch; a
             * locking read sees the latest committed row and holds the gap
             * against a concurrent insert. On SQLite it compiles away, and the
             * writer lock already serialises the same case.
             */
            $count = $locking ? $query->lockForUpdate()->count() : $query->count();

            if ($count > 0) {
                $blockers[] = self::phrase($table, $count);
            }
        }

        return $blockers;
    }

    /**
     * Tables and columns holding a foreign key to $table, read from the schema.
     *
     * @return list<array{0: string, 1: string}>
     */
    public static function referencesTo(string $table): array
    {
        $references = [];

        foreach (Schema::getTables() as $candidate) {
            $name = $candidate['name'];

            foreach (Schema::getForeignKeys($name) as $key) {
                if ($key['foreign_table'] !== $table) {
                    continue;
                }

                // A composite key would need every column to match; P1-01 has
                // none, and one that appeared should be looked at rather than
                // silently half-checked.
                if (count($key['columns']) !== 1) {
                    continue;
                }

                $references[] = [$name, $key['columns'][0]];
            }
        }

        sort($references);

        return $references;
    }

    /**
     * @return array<string, array{one: string, many: string, counted: bool}>
     */
    public static function labels(): array
    {
        return self::LABELS;
    }

    private static function phrase(string $table, int $count): string
    {
        $label = self::LABELS[$table] ?? null;

        if ($label === null) {
            return 'other records still refer to it';
        }

        $template = $count === 1 ? $label['one'] : $label['many'];

        return $label['counted'] ? sprintf($template, $count) : $template;
    }
}
