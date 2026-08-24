<?php

declare(strict_types=1);

namespace Tests\Feature\Governance;

use App\Modules\Audit\Support\AuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The `audit_events` index migration must not disturb the append-only
 * protection.
 *
 * WHY THIS FILE EXISTS. `audit_events` carries two database triggers, BEFORE
 * UPDATE and BEFORE DELETE, that raise SQLSTATE 45000. They are deliberately
 * NOT in any migration, because a migration that creates a trigger can also
 * drop one (SEC-DEC-037), and they are the only thing standing between the
 * audit trail and a mass delete - model hooks do not fire on one, and MySQL has
 * no DENY.
 *
 * SEC-DEC-039 records the constraint: if the table is ever REBUILT the triggers
 * go with it. So a migration touching this table has to be checked, not
 * trusted, and the approved R1.4b scope asked for exactly that.
 *
 * WHAT IS PROVED HERE. The migration adds two indexes and does nothing else:
 * no rebuild, no row touched, no trigger dropped or recreated. The triggers are
 * CREATED IN THE TEST first, because the test database has none - they live on
 * production, applied by hand - and then asserted to survive a full down-and-up
 * of the migration.
 *
 * SQLite is the test driver and supports the same `CREATE TRIGGER ... RAISE`
 * shape, so the behaviour being proved is real rather than mocked.
 */
class AuditIndexMigrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Recreate the production protection on the test database.
     *
     * Mirrors what was applied to production by hand and recorded in the gate 3
     * verification document.
     */
    private function installTheAppendOnlyTriggers(): void
    {
        DB::statement(
            'CREATE TRIGGER audit_events_no_update BEFORE UPDATE ON audit_events BEGIN '
            ."SELECT RAISE(ABORT, 'audit_events is append only: rows cannot be updated'); END"
        );

        DB::statement(
            'CREATE TRIGGER audit_events_no_delete BEFORE DELETE ON audit_events BEGIN '
            ."SELECT RAISE(ABORT, 'audit_events is append only: rows cannot be deleted'); END"
        );
    }

    /** @return list<string> */
    private function triggerNames(): array
    {
        return DB::table('sqlite_master')
            ->where('type', 'trigger')
            ->where('tbl_name', 'audit_events')
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }

    /** @return list<string> */
    private function indexNames(): array
    {
        return collect(Schema::getIndexes('audit_events'))
            ->pluck('name')
            ->sort()
            ->values()
            ->all();
    }

    #[Test]
    public function the_migration_added_both_indexes(): void
    {
        $indexes = $this->indexNames();

        $this->assertContains('audit_events_org_module_occurred_index', $indexes);
        $this->assertContains('audit_events_org_outcome_occurred_index', $indexes);
    }

    #[Test]
    public function it_adds_no_index_already_covered_by_an_existing_one(): void
    {
        /*
         * The plan called for "audit log filter indexes" without saying which.
         * Four already existed, covering tenancy, time, action, actor and
         * resource. Adding those again would be dead weight on the largest and
         * fastest-growing table in the schema, so R1.4b added exactly the two
         * columns nothing covered.
         */
        $indexes = $this->indexNames();

        $leadingColumns = collect(Schema::getIndexes('audit_events'))
            ->mapWithKeys(static fn (array $i): array => [$i['name'] => implode(',', $i['columns'])])
            ->all();

        $this->assertSame(
            'organisation_id,module,occurred_at',
            $leadingColumns['audit_events_org_module_occurred_index'] ?? null
        );
        $this->assertSame(
            'organisation_id,outcome,occurred_at',
            $leadingColumns['audit_events_org_outcome_occurred_index'] ?? null
        );

        /* And the four that were already there are untouched. */
        foreach ([
            'audit_events_organisation_id_occurred_at_index',
            'audit_events_action_occurred_at_index',
            'audit_events_actor_user_id_occurred_at_index',
            'audit_events_resource_type_resource_id_index',
        ] as $existing) {
            $this->assertContains($existing, $indexes, "The pre-existing index `{$existing}` is gone.");
        }
    }

    #[Test]
    public function both_triggers_survive_the_migration_being_rolled_back_and_re_run(): void
    {
        /*
         * THE ASSERTION THE APPROVED SCOPE ASKED FOR. Not a comment claiming
         * the migration is safe - a check that it is.
         */
        $this->installTheAppendOnlyTriggers();

        $this->assertSame(
            ['audit_events_no_delete', 'audit_events_no_update'],
            $this->triggerNames(),
            'The test could not install the triggers, so nothing below proves anything.'
        );

        /* Down. */
        Artisan::call('migrate:rollback', ['--step' => 1, '--force' => true]);

        $this->assertSame(
            ['audit_events_no_delete', 'audit_events_no_update'],
            $this->triggerNames(),
            'Rolling back the index migration removed an append-only trigger.'
        );

        /* And up again. */
        Artisan::call('migrate', ['--force' => true]);

        $this->assertSame(
            ['audit_events_no_delete', 'audit_events_no_update'],
            $this->triggerNames(),
            'Re-running the index migration removed an append-only trigger.'
        );

        $this->assertContains('audit_events_org_module_occurred_index', $this->indexNames());
    }

    #[Test]
    public function the_protection_still_works_after_the_migration(): void
    {
        /*
         * Surviving as a name in `sqlite_master` is not the same as still
         * firing. This writes a row and then tries to change it.
         */
        /* Written through the real path. `AuditEvent` is deliberately not
         * mass-assignable - `AuditLogger` is the only writer, which is part of
         * what keeps the trail trustworthy. */
        $event = app(AuditLogger::class)->record(action: 'test.event', module: 'Platform');

        $this->assertNotNull($event);

        $this->installTheAppendOnlyTriggers();

        try {
            DB::table('audit_events')->where('id', $event->getKey())->update(['action' => 'tampered']);
            $this->fail('An audit row was updated. The append-only protection is not working.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('append only', $e->getMessage());
        }

        try {
            DB::table('audit_events')->where('id', $event->getKey())->delete();
            $this->fail('An audit row was deleted. The append-only protection is not working.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('append only', $e->getMessage());
        }

        /* And the row is still there, unchanged. */
        $this->assertSame('test.event', DB::table('audit_events')->where('id', $event->getKey())->value('action'));
    }

    #[Test]
    public function the_migration_modifies_no_audit_row(): void
    {
        /*
         * An index migration must not touch data. Proved by writing rows,
         * rolling the migration back, re-running it, and comparing.
         */
        for ($i = 1; $i <= 3; $i++) {
            app(AuditLogger::class)->record(action: 'test.event.'.$i, module: 'Platform');
        }

        $this->assertSame(3, DB::table('audit_events')->count());

        $before = DB::table('audit_events')->orderBy('id')->get()->toArray();

        Artisan::call('migrate:rollback', ['--step' => 1, '--force' => true]);
        Artisan::call('migrate', ['--force' => true]);

        $after = DB::table('audit_events')->orderBy('id')->get()->toArray();

        $this->assertEquals($before, $after, 'The index migration changed audit data.');
    }
}
