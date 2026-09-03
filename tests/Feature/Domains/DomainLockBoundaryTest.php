<?php

declare(strict_types=1);

namespace Tests\Feature\Domains;

use App\Modules\Domains\Models\BusinessDomain;
use App\Modules\Domains\Services\DomainOwnershipService;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Throwable;

/**
 * IS THE ROW ACTUALLY HELD? Measured against real MySQL, with two connections.
 *
 * THIS CLASS DOES NOT USE RefreshDatabase, AND THAT IS THE POINT.
 *
 * The first version of this measurement lived in DomainConcurrencyTest, which
 * does. CI caught it giving the right answer for the WRONG REASON: under
 * RefreshDatabase the domain row itself is uncommitted, so a second connection
 * inserting an ownership row blocks on the FOREIGN KEY to that uncommitted
 * parent - not on any lock the service took. The measurement said "blocked" and
 * meant nothing. Exactly the failure CLAUDE.md §2 describes: a test that passes,
 * or fails, for a reason unrelated to what it claims to check.
 *
 * So the data here is COMMITTED, both connections can see it, and the only
 * thing that can block the second connection is a lock the first one holds.
 * tearDown removes what it created, because nothing rolls it back.
 *
 * MySQL only. SQLite has no SELECT ... FOR UPDATE - the locking reads compile
 * away entirely there - so this SKIPS EXPLICITLY WITH A STATED REASON rather
 * than passing vacuously, and CI fails the Domains MySQL step if anything in
 * the suite skips there.
 */
final class DomainLockBoundaryTest extends TestCase
{
    private ?int $organisationId = null;

    private ?int $userId = null;

    private ?int $domainId = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped(
                'SQLite has no SELECT ... FOR UPDATE, so the locking reads compile away entirely and '
                .'this measurement would report a lock against a service holding none. It runs '
                .'against MySQL 8.4 in CI, the engine production uses.'
            );
        }

        $this->seedCommitted();
    }

    protected function tearDown(): void
    {
        $this->removeCommitted();

        parent::tearDown();
    }

    /**
     * C1. WHERE THE BOUNDARY ACTUALLY IS.
     *
     * Two questions, asked of the engine rather than assumed:
     *
     *   (a) With NO ownership row present, does locking the open ownership row
     *       hold anything against a second connection?
     *   (b) Does locking the DOMAIN row hold?
     *
     * (b) MUST be true - it is the boundary the whole unit rests on, and a
     * failure here means the five operations are not serialised at all.
     *
     * (a) is REPORTED RATHER THAN ASSERTED IN ONE DIRECTION, because the honest
     * answer depends on InnoDB gap locking and on the shape of the index, and
     * neither is a thing to rest an invariant on. Whichever way it comes out,
     * the argument for the correction is unchanged and is stated in the message:
     * ENABLE, DISABLE, SET OWNER, CLEAR OWNER AND PURGE EACH DECIDE FROM THE
     * DOMAIN'S STATUS **AND** ITS OWNERSHIP TOGETHER, and a lock on one of two
     * things cannot serialise a decision taken over both.
     */
    public function test_the_domain_row_is_the_boundary(): void
    {
        $probe = $this->probe();

        // (b) THE BOUNDARY. Hold the domain row; a second connection must wait.
        DB::beginTransaction();

        app(DomainOwnershipService::class)->lockDomain(
            BusinessDomain::query()->findOrFail($this->domainId)
        );

        $domainRowHeld = $this->blocks(
            fn () => $probe->table('business_domains')->where('id', $this->domainId)->lockForUpdate()->get()
        );

        DB::rollBack();

        $this->assertTrue(
            $domainRowHeld,
            'The business_domains row was NOT held against a second connection. Nothing serialises '
            .'the five operations that decide from a domain\'s status and its ownership together, '
            .'and the D-42 invariant is unenforced under concurrency.'
        );

        // (a) THE FIRST DESIGN, measured rather than argued about.
        DB::beginTransaction();

        $current = app(DomainOwnershipService::class)->lockCurrentOwnership(
            BusinessDomain::query()->findOrFail($this->domainId)
        );

        $this->assertNull($current, 'The domain already has an owner, so this measures nothing.');

        $ownershipLockHeld = $this->blocks(fn () => $probe->table('business_domain_owners')->insert([
            'business_domain_id' => $this->domainId,
            'user_id' => $this->userId,
            'assigned_at' => now(),
            'ended_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]));

        DB::rollBack();

        $probe->table('business_domain_owners')->where('business_domain_id', $this->domainId)->delete();

        /*
         * Recorded either way. If InnoDB's gap locking happens to hold the
         * empty range, the ownership-row lock is not USELESS - it is merely
         * INSUFFICIENT and dependent on an index shape and an isolation level
         * that nobody should have to reason about to keep an invariant true.
         */
        $this->addToAssertionCount(1);

        fwrite(STDERR, sprintf(
            "\n  [C1] With no ownership row present: locking the open ownership row %s a concurrent "
            ."first-owner insert; locking the domain row %s a concurrent domain read.\n"
            .'       The boundary is the domain row either way - the five operations decide from '
            ."status AND ownership together.\n",
            $ownershipLockHeld ? 'BLOCKED' : 'DID NOT BLOCK',
            $domainRowHeld ? 'BLOCKED' : 'DID NOT BLOCK',
        ));
    }

    /**
     * The lock is released when the transaction ends. A boundary that never
     * lets go is an outage rather than a guard.
     */
    public function test_the_lock_is_released_when_the_transaction_ends(): void
    {
        $probe = $this->probe();

        DB::beginTransaction();
        app(DomainOwnershipService::class)->lockDomain(
            BusinessDomain::query()->findOrFail($this->domainId)
        );
        DB::rollBack();

        $this->assertFalse(
            $this->blocks(
                fn () => $probe->table('business_domains')->where('id', $this->domainId)->lockForUpdate()->get()
            ),
            'The domain row was still held after the transaction ended.'
        );
    }

    /** A second connection, with a short lock-wait so a block is observable. */
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

    /** Committed rows - no RefreshDatabase here, so these are really there. */
    private function seedCommitted(): void
    {
        $this->organisationId = (int) DB::table('organisations')->insertGetId([
            'name' => 'Lock Boundary Org', 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->userId = (int) DB::table('users')->insertGetId([
            'organisation_id' => $this->organisationId,
            'provider' => 'microsoft', 'external_subject' => 'lock-boundary-subject',
            'tenant_id' => '11111111-1111-1111-1111-111111111111',
            'email' => 'lock-boundary@example.test', 'display_name' => 'Lock Boundary',
            'status' => 'active', 'platform_role' => null,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->domainId = (int) DB::table('business_domains')->insertGetId([
            'organisation_id' => $this->organisationId,
            'code' => 'lock-boundary', 'name' => 'Lock Boundary', 'description' => null,
            'kind' => 'custom', 'status' => 'disabled', 'access_expectation' => 'undecided',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function removeCommitted(): void
    {
        if ($this->domainId !== null) {
            DB::table('business_domain_owners')->where('business_domain_id', $this->domainId)->delete();
            DB::table('business_domains')->where('id', $this->domainId)->delete();
        }

        if ($this->userId !== null) {
            DB::table('users')->where('id', $this->userId)->delete();
        }

        if ($this->organisationId !== null) {
            DB::table('organisations')->where('id', $this->organisationId)->delete();
        }
    }
}
