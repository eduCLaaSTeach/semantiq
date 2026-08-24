<?php

declare(strict_types=1);

/**
 * Generate doc/DATA_DICTIONARY.md and doc/ENTITY_RELATIONSHIP.md.
 *
 * WHY THIS IS GENERATED RATHER THAN WRITTEN.
 *
 * A hand-written data dictionary is correct on the day it is written and wrong
 * by the next migration, and nothing tells you which state it is in. This reads
 * the schema itself, so the documents cannot drift: re-run it after any
 * migration and the diff IS the schema change.
 *
 * WHERE EACH FACT COMES FROM.
 *
 *   Structure, types, lengths     the MIGRATIONS. CLAUDE.md names them the
 *                                 source of truth, and they carry two things
 *                                 SQLite loses - string lengths and
 *                                 unsigned-ness.
 *   Existence, keys, indexes      the LIVE DATABASE, so a migration that was
 *                                 written but never ran cannot appear here as
 *                                 though it had.
 *   Meaning                       tools/data-meanings.php, hand written, the
 *                                 one part no machine can supply.
 *
 * The two structural sources are CROSS-CHECKED and the generator refuses to
 * write anything if they disagree. A dictionary that quietly documents a column
 * the database does not have is worse than no dictionary.
 *
 * Usage:  php tools/generate-data-docs.php
 */
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * The imports sit ABOVE the bootstrap on purpose. A `use` statement is resolved
 * at compile time in the order it appears, so an alias declared after the code
 * that references it does not apply to that code - which is exactly how this
 * script broke once: `Kernel::class` resolved to the literal string "Kernel"
 * and the container refused it.
 */
$root = dirname(__DIR__);

require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$meanings = require $root.'/tools/data-meanings.php';
$columnMeanings = require $root.'/tools/data-column-meanings.php';

/* ------------------------------------------------------------------ */
/* 1. Structure, from the migrations */
/* ------------------------------------------------------------------ */

function mysqlType(string $method, array $args): string
{
    return match ($method) {
        'id' => 'bigint unsigned AUTO_INCREMENT',
        'foreignId' => 'bigint unsigned',
        'bigInteger' => 'bigint',
        'integer' => 'int',
        'unsignedInteger' => 'int unsigned',
        'unsignedSmallInteger' => 'smallint unsigned',
        'boolean' => 'tinyint(1)',
        'text' => 'text',
        'mediumText' => 'mediumtext',
        'longText' => 'longtext',
        'json' => 'json',
        'date' => 'date',
        'timestamp' => 'timestamp',
        'rememberToken' => 'varchar(100)',
        'string' => isset($args[1]) && $args[1] !== '' ? 'varchar('.$args[1].')' : 'varchar(255)',
        default => $method,
    };
}

$files = glob($root.'/database/migrations/*.php');
sort($files);

$tables = [];
$firstSeen = [];

foreach ($files as $file) {
    $src = file_get_contents($file);
    $migration = basename($file, '.php');

    $upStart = strpos($src, 'public function up()');
    $downStart = strpos($src, 'public function down()');
    if ($upStart === false) {
        continue;
    }
    $up = substr($src, $upStart, $downStart === false ? null : $downStart - $upStart);

    preg_match_all("/Schema::(create|table)\(\s*'([a-z_]+)'(.*?)\n        \}\);/s", $up, $blocks, PREG_SET_ORDER);

    foreach ($blocks as [$whole, $kind, $table, $body]) {
        if (! isset($tables[$table])) {
            $tables[$table] = [];
            $firstSeen[$table] = $migration;
        }

        preg_match_all('/\$table->([a-zA-Z]+)\((.*?)\)((?:->[a-zA-Z]+\([^;]*?\))*);/s', $body, $stmts, PREG_SET_ORDER);

        foreach ($stmts as [$all, $method, $argsRaw, $chain]) {
            if (in_array($method, ['index', 'unique', 'dropColumn', 'dropForeign', 'dropIndex', 'dropUnique', 'foreign'], true)) {
                continue;
            }

            if ($method === 'timestamps') {
                foreach (['created_at', 'updated_at'] as $c) {
                    $tables[$table][$c] = ['type' => 'timestamp', 'nullable' => true, 'default' => null, 'migration' => $migration];
                }

                continue;
            }

            preg_match_all("/'([^']*)'|\"([^\"]*)\"|(\d+)/", $argsRaw, $am, PREG_SET_ORDER);
            $args = array_map(static function (array $m): string {
                if (($m[1] ?? '') !== '') {
                    return $m[1];
                }
                if (($m[2] ?? '') !== '') {
                    return $m[2];
                }

                return $m[3] ?? '';
            }, $am);

            $name = match ($method) {
                'id' => $args[0] ?? 'id',
                'rememberToken' => 'remember_token',
                default => $args[0] ?? null,
            };

            if ($name === null || $name === '') {
                continue;
            }

            $default = null;
            if (preg_match('/->default\(([^)]*)\)/', $chain, $dm)) {
                $default = trim($dm[1], " '\"");
                /* MySQL stores a boolean default as 0 or 1, not as the PHP
                 * literal the migration wrote. Show what the column holds. */
                $default = match ($default) {
                    'false' => '0', 'true' => '1', default => $default
                };
            }

            $tables[$table][$name] = [
                'type' => mysqlType($method, $args),
                'nullable' => $method !== 'id' && str_contains($chain, '->nullable()'),
                'default' => $default,
                'migration' => $migration,
            ];
        }
    }
}

/* ------------------------------------------------------------------ */
/* 2. Existence, keys and indexes, from the live database */
/* ------------------------------------------------------------------ */

$driver = DB::connection()->getDriverName();
$live = [];
$foreignKeys = [];

$liveTables = collect(Schema::getTables())->pluck('name')->sort()->values()->all();

foreach ($liveTables as $t) {
    $live[$t] = collect(Schema::getColumns($t))->pluck('name')->all();

    foreach (Schema::getForeignKeys($t) as $fk) {
        $foreignKeys[] = [
            'table' => $t,
            'columns' => $fk['columns'],
            'foreign_table' => $fk['foreign_table'],
            'foreign_columns' => $fk['foreign_columns'],
            'on_delete' => $fk['on_delete'] ?? null,
        ];
    }
}

/* ------------------------------------------------------------------ */
/* 3. Cross-check. Refuse to write anything if the two disagree. */
/* ------------------------------------------------------------------ */

$problems = [];

foreach ($live as $t => $cols) {
    if ($t === 'migrations') {
        continue;
    }
    if (! isset($tables[$t])) {
        $problems[] = "`{$t}` exists in the database but no migration was parsed for it";

        continue;
    }
    foreach (array_diff($cols, array_keys($tables[$t])) as $c) {
        $problems[] = "`{$t}.{$c}` exists in the database but was not parsed from a migration";
    }
    foreach (array_diff(array_keys($tables[$t]), $cols) as $c) {
        $problems[] = "`{$t}.{$c}` was parsed from a migration but does not exist in the database";
    }
}

foreach (array_diff(array_keys($tables), array_keys($live)) as $t) {
    $problems[] = "`{$t}` was parsed from a migration but does not exist in the database";
}

if ($problems !== []) {
    fwrite(STDERR, "REFUSING TO WRITE. The migrations and the database disagree:\n\n");
    fwrite(STDERR, '  - '.implode("\n  - ", $problems)."\n\n");
    fwrite(STDERR, "Run `php artisan migrate` and try again, or fix the discrepancy.\n");
    exit(1);
}

/* ------------------------------------------------------------------ */
/* 4. Write the documents */
/* ------------------------------------------------------------------ */

/*
 * Which columns are ACTUALLY foreign keys, from the database.
 *
 * Built here rather than in a renderer because both documents need it, and
 * because guessing from a name would be wrong: `users.entra_object_id` and
 * `users.external_reference_id` end in `_id` and are not foreign keys at all.
 * The ER document states that nothing in it is inferred from a column name, so
 * this has to be the real list or that sentence is a lie.
 */
$fkByColumn = [];
foreach ($foreignKeys as $fk) {
    foreach ($fk['columns'] as $c) {
        $fkByColumn[$fk['table'].'.'.$c] = $fk['foreign_table'].'.'.($fk['foreign_columns'][0] ?? 'id')
            .($fk['on_delete'] ? ' ('.str_replace('_', ' ', strtolower($fk['on_delete'])).')' : '');
    }
}

/** What a column means: the specific entry, else the shared shape, else nothing. */
function meaningFor(string $table, string $column, array $columnMeanings, array $shared): string
{
    if (isset($columnMeanings[$table.'.'.$column])) {
        return $columnMeanings[$table.'.'.$column];
    }
    if (isset($shared['*'.$column])) {
        return $shared['*'.$column];
    }
    if (str_ends_with($column, '_by_user_id')) {
        return 'Who did this. Kept as `nullOnDelete` so the record survives the person leaving.';
    }
    if (str_ends_with($column, '_user_id')) {
        return 'Which person this points at.';
    }
    if (str_ends_with($column, '_id')) {
        return 'Foreign key. See the relationships table below.';
    }

    return '';
}

$generatedFrom = count($liveTables).' tables, '
    .collect($live)->flatten()->count().' columns, '
    .count($foreignKeys).' foreign keys';

$frameworkTables = ['cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'sessions', 'password_reset_tokens', 'migrations'];

$moduleOf = static function (string $t) use ($meanings, $frameworkTables): string {
    if (in_array($t, $frameworkTables, true)) {
        return 'Laravel';
    }

    return $meanings['tables'][$t]['module'] ?? 'Unassigned';
};

$byModule = [];
foreach ($liveTables as $t) {
    if ($t === 'migrations') {
        continue;
    }
    $byModule[$moduleOf($t)][] = $t;
}
$moduleOrder = ['Identity', 'Audit', 'Platform', 'Security', 'Governance', 'Laravel'];
uksort($byModule, static fn ($a, $b) => array_search($a, $moduleOrder, true) <=> array_search($b, $moduleOrder, true));

require $root.'/tools/render-data-dictionary.php';
require $root.'/tools/render-entity-relationship.php';

echo "Wrote doc/DATA_DICTIONARY.md and doc/ENTITY_RELATIONSHIP.md\n";
echo "  from {$generatedFrom}\n";
echo "  driver: {$driver}\n";
