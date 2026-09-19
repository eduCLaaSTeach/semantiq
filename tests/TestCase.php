<?php

namespace Tests;

use App\Modules\Audit\Services\AuditWriter;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    /**
     * THE ATOMICITY GUARD'S BASELINE, and the only reason this method exists.
     *
     * AuditWriter refuses to record a state change outside a transaction,
     * because a change already committed cannot be rolled back when its
     * evidence fails - D-111. RefreshDatabase wraps every test in a transaction
     * of its own, so inside a test the baseline is 1 rather than 0. Without
     * this the harness would satisfy the guard on every emitter's behalf and it
     * would catch nothing, which is exactly the vacuous guard CLAUDE.md §2
     * warns about.
     *
     * Read AFTER parent::setUp(), because that is where RefreshDatabase opens
     * its transaction. Tests that do not use it are at 0 and get 0.
     */
    protected function setUp(): void
    {
        parent::setUp();

        AuditWriter::$baselineTransactionLevel = DB::transactionLevel();
    }

    protected function tearDown(): void
    {
        AuditWriter::$baselineTransactionLevel = 0;

        parent::tearDown();
    }
}
