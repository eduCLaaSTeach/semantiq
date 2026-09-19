<?php

declare(strict_types=1);

namespace Tests\Feature\SystemHealth;

use App\Modules\SystemHealth\Checks\SessionStoreCheck;
use App\Modules\SystemHealth\Report\HealthStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * S1 to S4 - correction 2.
 *
 * THE DEPLOYED DATABASE STORE, AND ONLY THAT. A database transaction can only
 * roll back a database write; against `file` this round trip would leave a
 * session file on disk and against `redis` a live key, while the rollback
 * succeeded and rolled back nothing. So an unsupported driver is NOT CHECKED,
 * never Available - and the database check is not allowed to stand in for it.
 *
 * The suite runs with SESSION_DRIVER=array, which is itself one of the
 * unsupported drivers, so every supported-driver case sets `database`
 * explicitly. That is the value production runs.
 */
final class SessionStoreCheckTest extends TestCase
{
    use RefreshDatabase;

    private function check(): SessionStoreCheck
    {
        return app(SessionStoreCheck::class);
    }

    private function useTheDatabaseStore(): void
    {
        config(['session.driver' => 'database', 'session.table' => 'sessions']);
    }

    /** S1. Mutation: hard-code Available. */
    public function test_the_round_trip_succeeds_against_the_database_store(): void
    {
        $this->useTheDatabaseStore();

        $this->assertSame(HealthStatus::Available, $this->check()->run()['status']);
    }

    /**
     * S2. A missing or unusable table is Unavailable.
     *
     * Induced through the CONFIGURED TABLE NAME, at the boundary the check
     * itself reads - NOT with Schema::drop(). MySQL commits the open
     * transaction implicitly on DDL, which ends RefreshDatabase's and cost
     * P1-08 four cases that were green on SQLite and red on the engine
     * production runs. D-128.
     *
     * Mutation: swallow the exception and report Available. The outage this
     * check exists to find - a renamed table that leaves `select 1` green and
     * signs nobody in - would then be invisible.
     */
    public function test_a_session_table_that_is_not_there_is_unavailable(): void
    {
        config(['session.driver' => 'database', 'session.table' => 'a_table_that_is_not_there']);

        $result = $this->check()->run();

        $this->assertSame(HealthStatus::Unavailable, $result['status']);
        $this->assertStringNotContainsString('a_table_that_is_not_there', $result['explanation']);
    }

    /**
     * S3. NOTHING IS LEFT BEHIND - the case correction 2 exists for.
     *
     * Counted before and after, AND the synthetic identifier is looked for
     * directly: a count alone would pass if the check deleted its own row
     * instead of rolling back, and a direct lookup alone would pass if it wrote
     * a second row under another key.
     *
     * Mutation: commit instead of rolling back - swap the rollback signal for a
     * plain return. Both assertions then fail.
     */
    public function test_the_round_trip_leaves_no_session_behind(): void
    {
        $this->useTheDatabaseStore();

        $before = DB::table('sessions')->count();

        $this->assertSame(HealthStatus::Available, $this->check()->run()['status']);

        $this->assertSame($before, DB::table('sessions')->count(), 'The health check left a row behind.');

        $this->assertSame(
            0,
            DB::table('sessions')->where('id', 'like', 'semantiq-health-%')->count(),
            'A synthetic health-check session survived the round trip.'
        );
    }

    /**
     * S3b. It touches no real session.
     *
     * A row that was there before is there afterwards, unchanged. Without this,
     * a check that truncated the table to tidy up after itself would satisfy
     * S3 perfectly.
     */
    public function test_the_round_trip_does_not_disturb_a_real_session(): void
    {
        $this->useTheDatabaseStore();

        DB::table('sessions')->insert([
            'id' => 'a-real-looking-session-identifier',
            'user_id' => null,
            'ip_address' => null,
            'user_agent' => null,
            'payload' => 'the payload',
            'last_activity' => 1_700_000_000,
        ]);

        $this->check()->run();

        $row = DB::table('sessions')->where('id', 'a-real-looking-session-identifier')->first();

        $this->assertNotNull($row, 'The health check removed a real session.');
        $this->assertSame('the payload', $row->payload);
        $this->assertSame(1_700_000_000, (int) $row->last_activity);
    }

    /**
     * S4. AN UNSUPPORTED DRIVER IS NOT CHECKED, AND NEVER AVAILABLE.
     *
     * Every driver Laravel ships that this check does not implement, so a
     * future `SESSION_DRIVER=redis` cannot quietly report health for a store
     * nothing exercised.
     *
     * Mutation: a `default => Available` arm, or falling through to the
     * database check's result.
     */
    public function test_an_unsupported_driver_is_not_checked_and_never_available(): void
    {
        foreach (['file', 'redis', 'dynamodb', 'memcached', 'cookie', 'array', 'null'] as $driver) {
            config(['session.driver' => $driver]);

            $result = $this->check()->run();

            $this->assertSame(
                HealthStatus::NotChecked,
                $result['status'],
                "The [{$driver}] driver did not report Not checked."
            );

            $this->assertNotSame(HealthStatus::Available, $result['status']);

            // And it names no driver on screen.
            $this->assertStringNotContainsStringIgnoringCase($driver, $result['explanation']);
        }
    }

    /**
     * S4 - THE OTHER DIRECTION. Not checked must GO when the driver IS
     * supported, or `return NotChecked` would pass the case above.
     */
    public function test_not_checked_goes_when_the_driver_is_supported(): void
    {
        $this->useTheDatabaseStore();

        $this->assertNotSame(HealthStatus::NotChecked, $this->check()->run()['status']);
    }

    /**
     * NO DDL IS EVER ISSUED, asserted on the statements the connection
     * actually ran rather than on the source.
     *
     * A source-level guard would miss a DDL issued through a helper; this
     * watches the wire. D-128, and the P1-08 lesson that a case green on SQLite
     * can be red on MySQL precisely because of DDL.
     */
    public function test_the_check_issues_no_ddl_and_no_delete(): void
    {
        $this->useTheDatabaseStore();

        $statements = [];
        DB::listen(function ($query) use (&$statements): void {
            $statements[] = strtolower($query->sql);
        });

        $this->check()->run();

        $this->assertNotSame([], $statements, 'No statement was recorded, so this guard proved nothing.');

        /*
         * IT IS A ROUND TRIP, NOT A WRITE - asserted on the statements, because
         * nothing else distinguishes the two.
         *
         * M-37 deleted the read-back entirely and SURVIVED: the success case
         * still reported Available, and the missing-table case still failed at
         * the insert, so "written, read back and discarded" was a sentence on
         * the screen with nothing holding it up. A check that writes and does
         * not read cannot tell a table that accepts writes and returns nothing
         * from a working one.
         */
        $joined = implode(' | ', $statements);

        $this->assertStringContainsString('insert into', $joined, 'The check wrote nothing.');
        $this->assertStringContainsString('select', $joined, 'The check never read its write back.');

        $inserted = array_values(array_filter($statements, fn (string $sql): bool => str_contains($sql, 'insert into')));
        $selected = array_values(array_filter($statements, fn (string $sql): bool => str_starts_with($sql, 'select')));

        $this->assertCount(1, $inserted, 'The check issued more than one write.');
        $this->assertCount(1, $selected, 'The check did not read back exactly once.');

        foreach ([$inserted[0], $selected[0]] as $sql) {
            $this->assertStringContainsString('sessions', $sql, 'A statement did not address the session table.');
        }

        // The read is BY THE SYNTHETIC IDENTIFIER, so it cannot be satisfied by
        // any other row the table happens to hold.
        $this->assertStringContainsString('where', $selected[0], 'The read-back was not keyed on the row just written.');

        foreach ($statements as $sql) {
            foreach (['drop ', 'truncate', 'alter ', 'create ', 'delete '] as $forbidden) {
                $this->assertStringNotContainsString(
                    $forbidden,
                    $sql,
                    "The session check issued [{$forbidden}], which D-128 forbids: [{$sql}]"
                );
            }
        }
    }

    /**
     * A FRESH IDENTIFIER EVERY RUN.
     *
     * M-40 replaced random_bytes with a fixed string and SURVIVED. A constant
     * identifier is not a cosmetic difference: two administrators refreshing at
     * the same moment collide on the primary key, so one of them sees
     * Unavailable for a session store that is working perfectly - a false red
     * on a healthy system, which is the failure this codebase refuses
     * elsewhere. It also stops being collision-resistant against a real
     * session id, which is the property the check depends on for never
     * touching one.
     *
     * Mutation: $id = self::SYNTHETIC_PREFIX.'fixed';
     */
    public function test_each_run_uses_a_different_synthetic_identifier(): void
    {
        $this->useTheDatabaseStore();

        $ids = [];

        DB::listen(function ($query) use (&$ids): void {
            if (! str_contains(strtolower($query->sql), 'insert into')) {
                return;
            }

            foreach ($query->bindings as $binding) {
                if (is_string($binding) && str_starts_with($binding, 'semantiq-health-')) {
                    $ids[] = $binding;
                }
            }
        });

        $this->check()->run();
        $this->check()->run();

        $this->assertCount(2, $ids, 'The synthetic identifier was not observed on both runs.');
        $this->assertNotSame($ids[0], $ids[1], 'The same synthetic identifier is used every run.');

        foreach ($ids as $id) {
            $this->assertMatchesRegularExpression('/^semantiq-health-[0-9a-f]{32}$/', $id,
                'The synthetic identifier is not 32 hex characters behind the fixed prefix.');
        }
    }

    /** The transaction is closed either way. */
    public function test_the_transaction_is_not_left_open(): void
    {
        $this->useTheDatabaseStore();
        $before = DB::transactionLevel();

        $this->check()->run();
        $this->assertSame($before, DB::transactionLevel(), 'A successful round trip left a transaction open.');

        config(['session.table' => 'a_table_that_is_not_there']);

        $this->check()->run();
        $this->assertSame($before, DB::transactionLevel(), 'A failed round trip left a transaction open.');
    }
}
