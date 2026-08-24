<?php

declare(strict_types=1);

namespace Tests\Feature\Schema;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Every index, unique and foreign key name a migration generates must fit in a
 * MySQL identifier.
 *
 * WHY THIS TEST EXISTS. The R1.4b retention migration passed the whole local
 * suite, passed CI, deployed green, and then failed on the live database with
 * MySQL error 1059: the unique key Laravel named
 * `retention_policies_organisation_id_personal_data_category_id_unique` is 67
 * characters and the limit is 64. Nothing local could have caught it. The suite
 * runs on SQLite, which imposes no identifier limit at all, so a name that is
 * illegal on the production engine is silently legal in every test.
 *
 * That is the whole hazard: the test database is not the production database,
 * and the difference is invisible until a release is already deployed. This
 * test closes it by computing the names statically, from the migration source,
 * rather than by asking the database - so it gives the same answer on SQLite as
 * it would on MySQL.
 *
 * The failure mode it prevents is not cosmetic. A migration that fails halfway
 * through leaves the table created, the index missing and the migration row
 * unrecorded, because MySQL does not roll DDL back. Recovering from that needs
 * a human with server access and a destructive drop.
 *
 * When this test fails, do not shorten a column name. Pass an explicit short
 * index name as the second argument to `unique()` or `index()`.
 */
class MigrationIdentifierLengthTest extends TestCase
{
    /**
     * MySQL's hard limit on the length of any identifier, in characters.
     * See MySQL error 1059, "Identifier name is too long".
     */
    private const MYSQL_IDENTIFIER_LIMIT = 64;

    #[Test]
    public function no_migration_generates_an_identifier_mysql_would_reject(): void
    {
        $offenders = [];
        $checked = 0;

        foreach ($this->identifiers() as $identifier) {
            $checked++;

            if (strlen($identifier['name']) > self::MYSQL_IDENTIFIER_LIMIT) {
                $offenders[] = sprintf(
                    '%s (%d chars) - %s on `%s` in %s',
                    $identifier['name'],
                    strlen($identifier['name']),
                    $identifier['type'],
                    $identifier['table'],
                    $identifier['file']
                );
            }
        }

        $this->assertGreaterThan(
            50,
            $checked,
            'The scanner found almost nothing, which means it stopped parsing the '
            .'migrations rather than that the migrations are clean. Fix the scanner.'
        );

        $this->assertSame([], $offenders, sprintf(
            "%d migration identifier(s) exceed MySQL's %d character limit.\n"
            ."These pass on SQLite and fail on the live database.\n"
            ."Give each one an explicit short name, for example\n"
            ."  \$table->unique(['a_id', 'b_id'], 'short_name_unique');\n\n%s",
            count($offenders),
            self::MYSQL_IDENTIFIER_LIMIT,
            implode("\n", $offenders)
        ));
    }

    /**
     * Every identifier the migrations would ask the database to create.
     *
     * Read statically from the source rather than from a live schema, so the
     * answer does not depend on which driver the suite happens to run on.
     *
     * @return list<array{name: string, type: string, table: string, file: string}>
     */
    private function identifiers(): array
    {
        $found = [];

        foreach (glob(database_path('migrations/*.php')) as $file) {
            $source = file_get_contents($file);
            $shortName = basename($file);

            preg_match_all(
                '/Schema::(?:create|table)\(\s*[\'"]([a-z0-9_]+)[\'"]\s*,\s*function\s*\(Blueprint\s+\$table\)\s*\{(.*?)\n        \}\)/s',
                $source,
                $blocks,
                PREG_SET_ORDER
            );

            foreach ($blocks as [, $table, $body]) {
                $found = array_merge(
                    $found,
                    $this->composites($table, $body, $shortName),
                    $this->chained($table, $body, $shortName),
                    $this->foreignKeys($table, $body, $shortName),
                );
            }
        }

        return $found;
    }

    /**
     * `$table->index([...])`, `unique([...])` and `primary([...])`. An explicit
     * second argument wins, which is exactly the fix this test asks for.
     *
     * @return list<array{name: string, type: string, table: string, file: string}>
     */
    private function composites(string $table, string $body, string $file): array
    {
        preg_match_all(
            '/\$table->(index|unique|primary)\(\s*\[(.*?)\]\s*(?:,\s*[\'"]([a-z0-9_]+)[\'"])?\s*\)/s',
            $body,
            $matches,
            PREG_SET_ORDER
        );

        $found = [];

        foreach ($matches as $match) {
            $type = $match[1];
            $explicit = $match[3] ?? '';

            preg_match_all('/[\'"]([a-z0-9_]+)[\'"]/', $match[2], $columns);

            $found[] = [
                'name' => $explicit !== ''
                    ? $explicit
                    : $table.'_'.implode('_', $columns[1]).'_'.$type,
                'type' => $type,
                'table' => $table,
                'file' => $file,
            ];
        }

        return $found;
    }

    /**
     * `->index()` or `->unique()` chained onto a single column definition.
     *
     * @return list<array{name: string, type: string, table: string, file: string}>
     */
    private function chained(string $table, string $body, string $file): array
    {
        preg_match_all(
            '/\$table->[a-zA-Z]+\(\s*[\'"]([a-z0-9_]+)[\'"].*?\)((?:->[a-zA-Z]+\([^;]*?\))*)\s*;/s',
            $body,
            $matches,
            PREG_SET_ORDER
        );

        $found = [];

        foreach ($matches as [, $column, $chain]) {
            foreach (['index', 'unique'] as $type) {
                if (preg_match('/->'.$type.'\(\s*\)/', $chain)) {
                    $found[] = [
                        'name' => $table.'_'.$column.'_'.$type,
                        'type' => $type,
                        'table' => $table,
                        'file' => $file,
                    ];
                }
            }
        }

        return $found;
    }

    /**
     * `foreignId('x')->constrained()` generates `{table}_x_foreign`.
     *
     * @return list<array{name: string, type: string, table: string, file: string}>
     */
    private function foreignKeys(string $table, string $body, string $file): array
    {
        preg_match_all('/\$table->foreignId\(\s*[\'"]([a-z0-9_]+)[\'"]\s*\)/', $body, $matches);

        return array_map(fn (string $column): array => [
            'name' => $table.'_'.$column.'_foreign',
            'type' => 'foreign key',
            'table' => $table,
            'file' => $file,
        ], $matches[1]);
    }
}
