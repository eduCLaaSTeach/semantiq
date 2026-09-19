<?php

declare(strict_types=1);

namespace Tests\Feature\SystemHealth;

use App\Modules\SystemHealth\Checks\SessionStoreCheck;
use App\Modules\SystemHealth\Report\HealthStatus;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * THE READ-BACK IS LOOKED AT - and a mutation proved it was not.
 *
 * M-38 kept the SELECT and ignored what came back (`if (false)`), and it
 * survived the whole suite. Nothing anywhere exercised a store that ACCEPTS A
 * WRITE AND RETURNS NOTHING, so "written, read back and discarded" was a
 * sentence on the screen with nothing holding it up. That store is not
 * hypothetical: a view with an INSTEAD OF trigger, a MySQL BLACKHOLE table, or
 * a replica whose writes go somewhere the reads do not, all behave exactly
 * this way - and each one signs nobody in while every other check stays green.
 *
 * WHY THIS FILE IS SEPARATE, AND WHY IT USES NO RefreshDatabase.
 *
 * The failure is induced by giving the check a CONNECTION whose select()
 * returns nothing. A second connection cannot be opened inside
 * RefreshDatabase's transaction without "cannot start a transaction within a
 * transaction" - the failure this project has now hit twice - so this file
 * builds its own throwaway SQLite file, creates the one table it needs there,
 * and deletes it afterwards. Nothing touches the suite's database, and no DDL
 * reaches the connection the harness holds open. D-128.
 */
final class SessionRoundTripReadsBackTest extends TestCase
{
    private string $file = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->file = tempnam(sys_get_temp_dir(), 'semantiq-health-').'.sqlite';
        touch($this->file);

        config(['database.connections.health_probe' => [
            'driver' => 'sqlite',
            'database' => $this->file,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]]);

        // The table this check needs, on a throwaway database of its own.
        Schema::connection('health_probe')->create('sessions', function ($table): void {
            $table->string('id')->primary();
            $table->integer('user_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity');
        });

        config(['session.driver' => 'database', 'session.table' => 'sessions', 'session.connection' => 'health_probe']);
    }

    protected function tearDown(): void
    {
        DB::purge('health_probe');

        if ($this->file !== '' && is_file($this->file)) {
            unlink($this->file);
        }

        parent::tearDown();
    }

    /** The premise: against the honest connection the round trip succeeds. */
    public function test_the_honest_connection_reports_available(): void
    {
        $this->assertSame(HealthStatus::Available, app(SessionStoreCheck::class)->run()['status']);
    }

    /**
     * THE CASE M-38 NEEDED. Writes are accepted; reads come back empty.
     *
     * select() is overridden rather than the table renamed, because a renamed
     * table fails at the INSERT - which the check already catches, and which is
     * why S2 did not kill this mutation. The write has to SUCCEED for the
     * read-back to be the only thing standing between this store and a green
     * row.
     *
     * Mutation: `if (false)` in place of the read-back comparison, or deleting
     * the comparison altogether.
     */
    public function test_a_store_that_accepts_writes_and_returns_nothing_is_unavailable(): void
    {
        $honest = DB::connection('health_probe');

        $blind = new class($honest->getPdo(), $honest->getDatabaseName(), $honest->getTablePrefix(), $honest->getConfig()) extends SQLiteConnection
        {
            /**
             * Every read comes back empty. Writes go through untouched, which
             * is the whole point: this is a store that takes the row and
             * cannot give it back.
             */
            public function select($query, $bindings = [], $useReadPdo = true, array $fetchUsing = []): array
            {
                return [];
            }
        };

        DB::extend('health_probe', fn (): SQLiteConnection => $blind);
        DB::purge('health_probe');

        $result = app(SessionStoreCheck::class)->run();

        $this->assertSame(
            HealthStatus::Unavailable,
            $result['status'],
            'A store that accepted the write and returned nothing was reported as healthy. '
            .'The read-back is not being looked at.'
        );

        $this->assertStringNotContainsStringIgnoringCase('sqlite', $result['explanation']);
    }

    /**
     * And a store that returns a DIFFERENT row is Unavailable too - the case a
     * `$found === null` check alone would miss.
     */
    public function test_a_store_that_returns_the_wrong_row_is_unavailable(): void
    {
        $honest = DB::connection('health_probe');

        $confused = new class($honest->getPdo(), $honest->getDatabaseName(), $honest->getTablePrefix(), $honest->getConfig()) extends SQLiteConnection
        {
            public function select($query, $bindings = [], $useReadPdo = true, array $fetchUsing = []): array
            {
                return [(object) ['id' => 'somebody-elses-session', 'payload' => '', 'last_activity' => 0]];
            }
        };

        DB::extend('health_probe', fn (): SQLiteConnection => $confused);
        DB::purge('health_probe');

        $this->assertSame(
            HealthStatus::Unavailable,
            app(SessionStoreCheck::class)->run()['status'],
            'A store that returned somebody else\'s row was reported as healthy.'
        );
    }
}
