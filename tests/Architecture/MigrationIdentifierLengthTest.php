<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * MySQL rejects an identifier longer than 64 characters; SQLite does not.
 *
 * A previous release shipped a migration that passed the SQLite suite and then
 * failed on production MySQL. The CI MySQL service now catches that class of
 * problem outright, and this test catches it earlier still, with a message that
 * says what to do about it.
 */
final class MigrationIdentifierLengthTest extends TestCase
{
    private const MYSQL_MAX_IDENTIFIER = 64;

    public function test_no_migration_declares_an_identifier_mysql_would_reject(): void
    {
        foreach (glob(__DIR__.'/../../database/migrations/*.php') ?: [] as $file) {
            $source = file_get_contents($file);

            preg_match_all("/->(?:index|unique|primary|foreign)\(\s*\[?[^]]*?\]?\s*,\s*'([^']+)'/", $source, $named);

            foreach ($named[1] ?? [] as $identifier) {
                $this->assertLessThanOrEqual(
                    self::MYSQL_MAX_IDENTIFIER,
                    strlen($identifier),
                    "Identifier [{$identifier}] in ".basename($file).' exceeds MySQL\'s 64-character '
                    .'limit. Give the index a shorter explicit name.'
                );
            }
        }

        $this->assertTrue(true);
    }
}
