<?php

declare(strict_types=1);

namespace Tests\Feature\Privacy;

use App\Enums\Role;
use App\Models\User;
use App\Modules\Identity\Support\OrganisationContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SEC-DEC-066. The append-only protection on `privacy_correction_notes`,
 * proved against REAL DATABASE TRIGGERS rather than against the model hooks.
 *
 * WHY THE MODEL HOOKS ARE NOT ENOUGH TO TEST. They throw, and
 * `PrivacyRequestTest` proves they do. But they do not fire on a mass delete,
 * a raw query, or anything that bypasses Eloquent - which is precisely the
 * shape of the operation that would destroy a dispute record. Testing only the
 * hooks would prove the polite path is closed while leaving the dangerous one
 * unexamined.
 *
 * These tests therefore install the real triggers and then attack the table
 * with raw SQL, going nowhere near a model.
 *
 * SQLite is the test driver and supports `CREATE TRIGGER ... RAISE(ABORT)`,
 * which is the same shape as the MySQL `SIGNAL SQLSTATE '45000'` recorded in
 * `doc/execution/R1.4c-PLAN.md` section 1.8. The mechanism differs by dialect;
 * what is proved is identical - that a trigger on this table refuses the
 * statement, and that the row survives.
 *
 * The approval is explicit that production proves the triggers EXIST and that
 * their definitions match the approved SQL, and that the destructive paths are
 * never intentionally fired against production data. This is where firing is
 * proved.
 */
class CorrectionNoteTriggerTest extends TestCase
{
    use RefreshDatabase;

    private function installTriggers(): void
    {
        DB::unprepared(
            'CREATE TRIGGER privacy_correction_notes_no_update '
            .'BEFORE UPDATE ON privacy_correction_notes BEGIN '
            ."SELECT RAISE(ABORT, 'privacy_correction_notes is append-only'); END"
        );

        DB::unprepared(
            'CREATE TRIGGER privacy_correction_notes_no_delete '
            .'BEFORE DELETE ON privacy_correction_notes BEGIN '
            ."SELECT RAISE(ABORT, 'privacy_correction_notes is append-only'); END"
        );
    }

    /**
     * A note written with raw SQL, so nothing about this test depends on the
     * model or its hooks.
     */
    private function aRawNote(): int
    {
        $user = User::query()->create(['name' => 'Test Person', 'email' => uniqid().'@example.test']);
        $organisationId = app(OrganisationContext::class)->require()->getKey();
        $user->forceFill(['role' => Role::Admin, 'organisation_id' => $organisationId])->save();

        $requestId = DB::table('privacy_requests')->insertGetId([
            'organisation_id' => $organisationId,
            'reference' => 'PR-9001',
            'status' => 'received',
            'request_type' => 'correction',
            'subject_name' => 'Dana Subject',
            'subject_email' => 'dana@example.test',
            'received_at' => now()->toDateTimeString(),
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);

        return DB::table('privacy_correction_notes')->insertGetId([
            'organisation_id' => $organisationId,
            'privacy_request_id' => $requestId,
            'subject_assertion' => 'The trail is wrong about me.',
            'outcome' => 'noted',
            'outcome_reason' => 'Recorded beside the entry.',
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);
    }

    #[Test]
    public function both_triggers_exist_once_installed(): void
    {
        $this->installTriggers();

        $names = DB::table('sqlite_master')
            ->where('type', 'trigger')
            ->where('tbl_name', 'privacy_correction_notes')
            ->pluck('name')
            ->sort()
            ->values()
            ->all();

        $this->assertSame(
            ['privacy_correction_notes_no_delete', 'privacy_correction_notes_no_update'],
            $names,
        );
    }

    #[Test]
    public function a_raw_update_is_refused_by_the_database(): void
    {
        $this->installTriggers();
        $id = $this->aRawNote();

        $this->expectException(QueryException::class);

        DB::table('privacy_correction_notes')
            ->where('id', $id)
            ->update(['subject_assertion' => 'Something more convenient.']);
    }

    #[Test]
    public function a_raw_delete_is_refused_by_the_database(): void
    {
        $this->installTriggers();
        $id = $this->aRawNote();

        $this->expectException(QueryException::class);

        DB::table('privacy_correction_notes')->where('id', $id)->delete();
    }

    /**
     * The one that matters most: a MASS delete, which is what model hooks
     * cannot see.
     */
    #[Test]
    public function a_mass_delete_is_refused_and_the_row_survives(): void
    {
        $this->installTriggers();
        $this->aRawNote();

        try {
            DB::table('privacy_correction_notes')->delete();
        } catch (QueryException) {
            // expected
        }

        $this->assertSame(
            1,
            DB::table('privacy_correction_notes')->count(),
            'the note did not survive an unqualified delete',
        );
    }

    /**
     * Refusing everything would also pass the tests above. Writing must work.
     */
    #[Test]
    public function inserting_a_note_still_works_with_the_triggers_in_place(): void
    {
        $this->installTriggers();

        $id = $this->aRawNote();

        $this->assertGreaterThan(0, $id);
        $this->assertSame(1, DB::table('privacy_correction_notes')->where('id', $id)->count());
    }

    /**
     * The approved production SQL must stay in the plan, and must stay legal.
     *
     * A trigger name over 64 characters is exactly what broke the R1.4b
     * production migration. Both of these are 34.
     */
    #[Test]
    public function the_approved_trigger_names_are_legal_on_mysql(): void
    {
        foreach (['privacy_correction_notes_no_update', 'privacy_correction_notes_no_delete'] as $name) {
            $this->assertLessThanOrEqual(64, strlen($name), "`{$name}` would be rejected by MySQL");
        }

        $plan = file_get_contents(base_path('doc/execution/R1.4c-PLAN.md'));

        $this->assertStringContainsString('CREATE TRIGGER privacy_correction_notes_no_update', $plan);
        $this->assertStringContainsString('CREATE TRIGGER privacy_correction_notes_no_delete', $plan);
        $this->assertStringContainsString("SIGNAL SQLSTATE '45000'", $plan);
    }

    /**
     * No migration may install these. SEC-DEC-037: a migration that can create
     * a trigger can also drop it, which would make the protection removable by
     * the same mechanism that installs it.
     */
    #[Test]
    public function no_migration_creates_or_drops_these_triggers(): void
    {
        foreach (glob(database_path('migrations/*.php')) as $file) {
            $source = file_get_contents($file);

            $this->assertStringNotContainsString(
                'privacy_correction_notes_no_',
                $source,
                basename($file).' references the append-only triggers. They are a separately approved '
                .'production step, deliberately outside the migration system.',
            );
        }
    }
}
