<?php

declare(strict_types=1);

namespace Tests\Feature\Domains;

use App\Modules\Domains\Models\BusinessDomain;
use App\Modules\Domains\Models\DomainOwnership;
use App\Modules\Domains\Models\DomainStatus;
use App\Modules\Domains\Services\DomainOwnershipService;
use App\Modules\Domains\Services\DomainService;
use App\Modules\Domains\Support\DomainViolation;
use Illuminate\Database\Connection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\DomainFactory;
use Tests\Support\OrganisationFactory;
use Tests\TestCase;

/**
 * The races the D-42 invariant has to survive - C2 to C7.
 *
 * THESE ARE STALE-INSTANCE TESTS, and that is the whole design.
 *
 * Laravel resolves {domain} BEFORE the transaction opens, so the model a
 * service is handed is a snapshot taken before any lock existed. Every test
 * here passes a deliberately stale instance into a service and asserts the
 * service RE-READS under the lock rather than deciding from what it was given.
 * That is what makes the lock do work: a lock held while the decision is taken
 * from a pre-lock snapshot is decoration.
 *
 * They run on EVERY ENGINE, because they are about WHERE THE DECISION IS TAKEN
 * rather than about what the database does with a lock.
 *
 * WHETHER THE ROW IS ACTUALLY HELD against another connection is a different
 * question and is measured in DomainLockBoundaryTest. It needs COMMITTED data
 * and two real connections, so it cannot live in a class wrapped by
 * RefreshDatabase - the first version of that measurement was in this file and
 * CI caught it giving the right answer for the wrong reason.
 */
final class DomainConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private OrganisationFactory $make;

    private DomainFactory $domains;

    protected function setUp(): void
    {
        parent::setUp();

        $this->make = new OrganisationFactory;
        $this->domains = new DomainFactory;
    }

    /**
     * C7. EVERY OPERATION TAKES THE LOCKS IN THE SAME ORDER: domain, then
     * ownership. One order everywhere, so two services cannot deadlock by
     * approaching the same two tables from opposite ends.
     *
     * Asserted from the query log rather than by reading the source, because a
     * future service could take them in a different order without changing a
     * line of the code this test could scan.
     *
     * Mutation: reverse the two locking reads in any one operation.
     */
    public function test_every_operation_locks_the_domain_before_the_ownership(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);
        $owner = $this->make->user($organisation);

        $operations = [
            'set owner' => function () use ($organisation, $owner, $admin): void {
                $domain = $this->domains->domain($organisation, 'A', 'a');
                app(DomainOwnershipService::class)->set($domain, $owner, $admin);
            },
            'clear owner' => function () use ($organisation, $owner, $admin): void {
                $domain = $this->domains->domain($organisation, 'B', 'b');
                $this->domains->ownership($domain, $owner);
                app(DomainOwnershipService::class)->clear($domain, $admin);
            },
            'enable' => function () use ($organisation, $owner, $admin): void {
                $domain = $this->domains->domain($organisation, 'C', 'c');
                $this->domains->ownership($domain, $owner);
                app(DomainService::class)->enable($domain, $admin);
            },
            'disable' => function () use ($organisation, $owner, $admin): void {
                $domain = $this->domains->enabledWithOwner($organisation, $owner, 'D', 'd');
                app(DomainService::class)->disable($domain, $admin);
            },
            'purge' => function () use ($organisation, $admin): void {
                $domain = $this->domains->domain($organisation, 'E', 'e');
                app(DomainService::class)->purge($domain, $admin);
            },
        ];

        foreach ($operations as $label => $operation) {
            $order = $this->orderOfTablesRead($operation);

            $this->assertNotEmpty($order, "[{$label}] read neither table, so this proves nothing.");

            $this->assertSame(
                'business_domains',
                $order[0],
                "[{$label}] read the ownership table before it locked the domain row. The lock order "
                .'is domain, then ownership, then dependency checks - in every operation.'
            );
        }
    }

    /**
     * C3. ENABLE RE-READS THE DOMAIN AND ITS OWNERSHIP UNDER THE LOCK.
     *
     * The instance the route hands the service is a snapshot from before the
     * transaction opened. Here it says "this domain has an owner"; by the time
     * enable() runs, that owner has been cleared. Enable must refuse.
     *
     * Deciding from the handed-in instance would commit ENABLED WITH NO OWNER,
     * which is the one state D-42 exists to make impossible.
     *
     * Mutation: decide from $domain rather than from the re-read $locked.
     */
    public function test_enable_refuses_when_the_owner_was_cleared_after_the_domain_was_loaded(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);
        $owner = $this->make->user($organisation);

        $domain = $this->domains->domain($organisation);
        $period = $this->domains->ownership($domain, $owner);

        // The stale snapshot: loaded WITH its ownership relation, while it had one.
        $stale = BusinessDomain::query()->with('currentOwnership')->whereKey($domain->id)->first();

        $this->assertNotNull($stale->currentOwnership, 'The snapshot was not taken while an owner existed.');

        // The world moves on.
        $period->forceFill(['ended_at' => now()])->save();

        $this->expectException(DomainViolation::class);

        app(DomainService::class)->enable($stale, $admin);
    }

    /** The same, for an owner who was deactivated after the load. */
    public function test_enable_refuses_when_the_owner_went_inactive_after_the_domain_was_loaded(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);
        $owner = $this->make->user($organisation);

        $domain = $this->domains->domain($organisation);
        $this->domains->ownership($domain, $owner);

        $stale = BusinessDomain::query()->with('currentOwnership.user')->whereKey($domain->id)->first();

        $this->assertTrue($stale->currentOwnership->user->isActive());

        $owner->forceFill(['status' => 'inactive'])->save();

        try {
            app(DomainService::class)->enable($stale, $admin);
            $this->fail('A domain was enabled with an inactive owner.');
        } catch (DomainViolation $violation) {
            $this->assertSame('owner_inactive', $violation->reason);
        }

        $this->assertSame(DomainStatus::Disabled, $domain->fresh()->status);
    }

    /**
     * C2. CLEAR OWNER RE-READS THE STATUS UNDER THE LOCK.
     *
     * The snapshot says "disabled", so clearing would be permitted. By the time
     * clear() runs the domain has been enabled, and it must refuse - otherwise
     * an enable racing a clear commits an enabled domain with nobody
     * accountable.
     *
     * Mutation: test $domain->isEnabled() instead of $locked->isEnabled().
     */
    public function test_clearing_refuses_when_the_domain_was_enabled_after_it_was_loaded(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);
        $owner = $this->make->user($organisation);

        $domain = $this->domains->domain($organisation);
        $this->domains->ownership($domain, $owner);

        $stale = BusinessDomain::query()->whereKey($domain->id)->first();

        $this->assertFalse($stale->isEnabled(), 'The snapshot was not taken while the domain was disabled.');

        $domain->forceFill(['status' => DomainStatus::Enabled->value])->save();

        try {
            app(DomainOwnershipService::class)->clear($stale, $admin);
            $this->fail('The owner of an enabled domain was cleared.');
        } catch (DomainViolation $violation) {
            $this->assertSame('owner_required_while_enabled', $violation->reason);
        }

        $this->assertSame(1, DomainOwnership::query()->whereNull('ended_at')->count());
    }

    /**
     * C4. Owner replacement stays atomic under a stale snapshot: exactly one
     * open period, and no period ended twice.
     *
     * Mutation: end the period using the stale $domain->currentOwnership
     * instead of the re-read locked row.
     */
    public function test_owner_replacement_is_atomic_against_a_stale_snapshot(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);
        $first = $this->make->user($organisation);
        $second = $this->make->user($organisation);
        $third = $this->make->user($organisation);

        $domain = $this->domains->domain($organisation);
        $this->domains->ownership($domain, $first);

        $stale = BusinessDomain::query()->with('currentOwnership')->whereKey($domain->id)->first();

        // Somebody else replaced the owner while this request was in flight.
        app(DomainOwnershipService::class)->set($domain, $second, $admin);

        // Now the in-flight request lands. It must end the CURRENT period - the
        // second one - not the one its snapshot remembers.
        app(DomainOwnershipService::class)->set($stale, $third, $admin);

        $periods = DomainOwnership::query()->orderBy('id')->get();

        $this->assertCount(3, $periods);

        $this->assertSame(
            1,
            $periods->whereNull('ended_at')->count(),
            'More than one ownership period is open, or none is.'
        );

        $this->assertSame($third->id, $periods->firstWhere('ended_at', null)->user_id);

        foreach ($periods->where('ended_at', '!=', null) as $ended) {
            $this->assertNotNull($ended->ended_at);
        }
    }

    /**
     * C5. Purge re-checks its dependencies under the lock.
     *
     * The snapshot was taken while the domain had never had an owner, so it
     * looked purgeable. By the time purge() runs somebody has been made
     * accountable for it, and it must refuse rather than destroy that history
     * or trip a foreign key.
     *
     * Mutation: run the dependency walk only before the transaction.
     */
    public function test_purge_refuses_when_an_owner_was_assigned_after_the_check(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);
        $owner = $this->make->user($organisation);

        $domain = $this->domains->domain($organisation, 'Mistake', 'mistake');

        $this->assertTrue(
            app(DomainService::class)->isPurgeable($domain),
            'The domain was not purgeable to begin with, so this proves nothing.'
        );

        $this->domains->ownership($domain, $owner);

        try {
            app(DomainService::class)->purge($domain, $admin);
            $this->fail('A domain with ownership history was destroyed.');
        } catch (DomainViolation $violation) {
            $this->assertSame('in_use', $violation->reason);
        }

        $this->assertDatabaseHas('business_domains', ['id' => $domain->id]);
        $this->assertSame(1, DomainOwnership::query()->count(), 'Ownership history was destroyed.');
    }

    /**
     * C6. THE LOSER OF EVERY RACE IS REFUSED IN A BUSINESS SENTENCE.
     *
     * Winning the race is not enough; losing it has to be readable. A
     * serialised transaction that then trips a constraint has produced a
     * database integrity error for an administrator who did nothing wrong -
     * the exact defect P1-03 shipped and had to correct.
     *
     * Mutation: remove the in-transaction re-check and let the database refuse.
     */
    public function test_the_loser_of_a_race_gets_a_sentence_and_never_a_database_error(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);
        $owner = $this->make->user($organisation);

        $losers = [];

        // Enable, having lost the owner.
        $one = $this->domains->domain($organisation, 'One', 'one');
        $period = $this->domains->ownership($one, $owner);
        $staleOne = BusinessDomain::query()->whereKey($one->id)->first();
        $period->forceFill(['ended_at' => now()])->save();
        $losers[] = fn () => app(DomainService::class)->enable($staleOne, $admin);

        // Clear, having lost the disabled status.
        $two = $this->domains->domain($organisation, 'Two', 'two');
        $this->domains->ownership($two, $owner);
        $staleTwo = BusinessDomain::query()->whereKey($two->id)->first();
        $two->forceFill(['status' => DomainStatus::Enabled->value])->save();
        $losers[] = fn () => app(DomainOwnershipService::class)->clear($staleTwo, $admin);

        // Purge, having lost its emptiness.
        $three = $this->domains->domain($organisation, 'Three', 'three');
        $staleThree = BusinessDomain::query()->whereKey($three->id)->first();
        $this->domains->ownership($three, $owner);
        $losers[] = fn () => app(DomainService::class)->purge($staleThree, $admin);

        foreach ($losers as $index => $loser) {
            try {
                $loser();
                $this->fail("Loser {$index} was not refused at all.");
            } catch (DomainViolation $violation) {
                $message = $violation->getMessage();

                $this->assertNotSame('', $message, "Loser {$index} was refused with no message.");

                foreach (['SQLSTATE', 'Integrity constraint', 'foreign key', 'FOREIGN KEY', 'PDO', 'violation'] as $leak) {
                    $this->assertStringNotContainsString(
                        $leak,
                        $message,
                        "Loser {$index} received a database error rather than a sentence."
                    );
                }

                // A sentence, not a code: it ends in a full stop and reads as English.
                $this->assertStringEndsWith('.', trim($message));
            }
        }
    }

    /**
     * The tables read by an operation, in the order they were first touched.
     *
     * @return list<string>
     */
    private function orderOfTablesRead(callable $operation): array
    {
        $order = [];

        DB::flushQueryLog();
        DB::enableQueryLog();

        $operation();

        foreach (DB::getQueryLog() as $entry) {
            $sql = strtolower($entry['query']);

            if (! str_starts_with($sql, 'select')) {
                continue;
            }

            foreach (['business_domains', 'business_domain_owners'] as $table) {
                if (str_contains($sql, "from `{$table}`") || str_contains($sql, "from \"{$table}\"")) {
                    if (! in_array($table, $order, true)) {
                        $order[] = $table;
                    }
                }
            }
        }

        DB::disableQueryLog();

        return $order;
    }
}
