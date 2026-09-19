<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Modules\Audit\Services\AuditWriter;
use App\Modules\Platform\Security\SecurityEventLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\Support\OrganisationFactory;
use Tests\TestCase;

/**
 * THE GUARD THAT GUARDS THE GUARD.
 *
 * Two mutations survived the first time this was measured, and both were about
 * the runtime atomicity check having nothing pointed at it:
 *
 *   - disabling the check entirely changed no result;
 *   - setting the test baseline to 0 made it vacuous - every test runs inside
 *     RefreshDatabase's transaction, so level > 0 would be satisfied by the
 *     HARNESS on every emitter's behalf and the check would pass for code that
 *     never opens a transaction of its own.
 *
 * The second is the more dangerous: a guard that is present, runs, and proves
 * nothing. That is CLAUDE.md §2 in its purest form, and it is why the baseline
 * exists at all.
 */
final class AuditAtomicityGuardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Mutation: replace the atomicity check in AuditWriter::record() with
     * `if (false)`. Nothing else in the suite notices.
     */
    public function test_a_state_change_recorded_outside_a_transaction_is_refused(): void
    {
        $organisation = (new OrganisationFactory)->organisation();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('not inside a database transaction');

        // Deliberately bare - exactly the shape the five defective emitters had.
        app(SecurityEventLogger::class)->record(SecurityEventLogger::ORGANISATION_UPDATED, [
            'user_id' => 1,
            'organisation_id' => $organisation->id,
            'result' => 'updated',
        ]);
    }

    /**
     * Mutation: set the baseline to a literal 0 in TestCase::setUp(). The check
     * then passes on every emitter, because RefreshDatabase already holds a
     * transaction open, and the case above is the only thing that fails.
     */
    public function test_the_baseline_tracks_the_harness_rather_than_assuming_zero(): void
    {
        $this->assertGreaterThan(
            0,
            DB::transactionLevel(),
            'RefreshDatabase is expected to hold a transaction; without one this suite is not isolated.'
        );

        $this->assertSame(
            DB::transactionLevel(),
            AuditWriter::$baselineTransactionLevel,
            'The atomicity baseline does not match the harness, so the guard is either vacuous '
            .'(baseline too low) or refuses correct code (baseline too high).'
        );
    }

    /** And the same call inside a transaction is accepted. The non-vacuous half. */
    public function test_a_state_change_inside_a_transaction_is_accepted(): void
    {
        $organisation = (new OrganisationFactory)->organisation();

        DB::transaction(function () use ($organisation): void {
            app(SecurityEventLogger::class)->record(SecurityEventLogger::ORGANISATION_UPDATED, [
                'user_id' => 1,
                'organisation_id' => $organisation->id,
                'result' => 'updated',
            ]);
        });

        $this->assertDatabaseHas('audit_events', ['event' => 'organisation.updated']);
    }

    /**
     * A REFUSAL IS NOT SUBJECT TO THE CHECK. It is written outside the caller's
     * transaction on purpose, and demanding one would be demanding the opposite
     * of what D-111 says about refusals.
     *
     * Mutation: apply the atomicity check to every outcome class.
     */
    public function test_a_refusal_needs_no_transaction(): void
    {
        app(SecurityEventLogger::class)->record(SecurityEventLogger::LOGIN_REFUSED_UNKNOWN, [
            'provider' => 'microsoft',
            'subject' => 'outsider',
            'tenant' => 'elsewhere',
            'result' => 'refused',
            'reason' => 'unknown_identity',
        ]);

        $this->assertDatabaseHas('audit_events', ['event' => 'auth.login.refused.unknown_identity']);
    }
}
