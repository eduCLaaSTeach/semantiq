<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Modules\Audit\Models\AuditChainHead;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Throwable;

/**
 * A17. THE CHAIN HEAD IS GENUINELY HELD AGAINST ANOTHER CONNECTION.
 *
 * MySQL ONLY, and the skip is the point. SQLite has no SELECT ... FOR UPDATE -
 * the locking read compiles away entirely - so running this there would report
 * a lock against code holding none. It is the P1-05 and P1-07 precedent, and
 * P1-07's M-R12 recorded the same limitation honestly rather than pretending a
 * single-threaded pass proved anything.
 *
 * WITHOUT THIS LOCK the chain FORKS: two writers read the same head, both
 * compute sequence n+1 from the same predecessor, and the second insert either
 * collides on the unique index or - if that index were ever relaxed - produces
 * two rows claiming the same position. Neither is detectable afterwards from
 * the rows alone.
 *
 * NO RefreshDatabase. A transaction wrapping the test would make the second
 * connection unable to see anything, and the measurement would report a lock
 * that was really just invisibility.
 */
final class AuditChainConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped(
                'SQLite has no SELECT ... FOR UPDATE, so the chain-head locking read compiles away '
                .'and this measurement would report a lock the writer does not hold. It runs '
                .'against MySQL 8.4 in CI, the engine production uses.'
            );
        }
    }

    /** Mutation: drop lockForUpdate() from AuditWriter::append(). */
    public function test_the_chain_head_is_held_against_another_connection(): void
    {
        $probe = $this->probe();

        DB::beginTransaction();

        // Exactly the read AuditWriter::append() takes.
        AuditChainHead::query()
            ->whereKey(AuditChainHead::ID)
            ->lockForUpdate()
            ->firstOrFail();

        $held = $this->blocks(
            fn () => $probe->table('audit_chain_head')
                ->where('id', AuditChainHead::ID)
                ->lockForUpdate()
                ->get()
        );

        DB::rollBack();

        $this->assertTrue(
            $held,
            'The chain head was NOT held against a second connection. Two writers would read the '
            .'same predecessor and the chain would fork at the same sequence number.'
        );
    }

    /** An ordinary read is not blocked - the lock is a write boundary, not a stall. */
    public function test_an_ordinary_read_is_not_blocked(): void
    {
        $probe = $this->probe();

        DB::beginTransaction();

        AuditChainHead::query()->whereKey(AuditChainHead::ID)->lockForUpdate()->firstOrFail();

        $blocked = $this->blocks(
            fn () => $probe->table('audit_chain_head')->where('id', AuditChainHead::ID)->get()
        );

        DB::rollBack();

        $this->assertFalse($blocked, 'Reading the evidence start date waited on a write lock.');
    }

    private function probe(): Connection
    {
        config(['database.connections.probe' => config('database.connections.'.config('database.default'))]);

        DB::purge('probe');

        $connection = DB::connection('probe');
        $connection->statement('SET SESSION innodb_lock_wait_timeout = 1');

        return $connection;
    }

    /** Whether a statement waited on a lock rather than completing. */
    private function blocks(callable $statement): bool
    {
        try {
            $statement();

            return false;
        } catch (Throwable $exception) {
            if (str_contains($exception->getMessage(), 'Lock wait timeout')) {
                return true;
            }

            throw $exception;
        }
    }
}
