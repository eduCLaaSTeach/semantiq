<?php

declare(strict_types=1);

namespace Tests\Feature\SystemHealth;

use App\Modules\Access\Support\RoleCode;
use App\Modules\Audit\Models\AuditChainHead;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Audit\Services\AuditChainVerifier;
use App\Modules\Identity\Health\IdentityHealthCheck;
use App\Modules\Identity\Health\IdentityHealthReport;
use App\Modules\Platform\Health\HealthInspector;
use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;
use App\Modules\Platform\Models\User;
use App\Modules\Platform\Support\ConfigurationValidator;
use App\Modules\SystemHealth\Checks\BackgroundWorkCheck;
use App\Modules\SystemHealth\Checks\CacheStoreCheck;
use App\Modules\SystemHealth\Checks\SessionStoreCheck;
use App\Modules\SystemHealth\Report\HealthStatus;
use App\Modules\SystemHealth\Report\SystemHealthReport;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\Support\OrganisationFactory;
use Tests\TestCase;

/**
 * P1-09 System Health. H1 to H18.
 *
 * EVERY ONE OF THE SIX STATUSES IS PROVEN, and the three that are not outcomes
 * - Not checked, Not applicable, Not configured - are broken in BOTH
 * directions: forced on when they should not be, and absent when they should.
 * Those three are the ones most likely to pass vacuously, because each is easy
 * to hard-code and each looks correct on the screen.
 *
 * FAILURE IS INDUCED AT THE DEPENDENCY BOUNDARY - a configured table name that
 * does not exist, a cache repository that lies, a stubbed identity result.
 * NEVER WITH DDL: Schema::drop() commits the open transaction on MySQL and cost
 * P1-08 four cases that were green on SQLite and red on the engine production
 * runs. D-128.
 */
final class SystemHealthTest extends TestCase
{
    use RefreshDatabase;

    private OrganisationFactory $make;

    protected function setUp(): void
    {
        parent::setUp();

        $this->make = new OrganisationFactory;
        Cache::flush();
    }

    private function report(): SystemHealthReport
    {
        return app(SystemHealthReport::class);
    }

    /** @return array<string, string> row name => status value */
    private function statuses(): array
    {
        $flat = [];

        foreach ($this->report()->toArray() as $area) {
            foreach ($area['rows'] as $row) {
                $flat[$row['name']] = $row['status'];
            }
        }

        return $flat;
    }

    /**
     * A report whose DATABASE DEPENDENCY THROWS, built at the boundary.
     *
     * Repointing database.default was the first version of this and it was
     * wrong in a way worth recording: RefreshDatabase rolls its transaction
     * back through the DEFAULT connection in tearDown, so the broken name was
     * still in place when it tried, the rollback failed, and the leaked
     * transaction turned every later test in the class red with "cannot start
     * a transaction within a transaction". A green-looking induced failure that
     * breaks the harness is not a test of the check.
     *
     * So the dependency is replaced rather than the configuration: the same
     * HealthInspector, the same SessionStoreCheck, given a manager that cannot
     * hand out a connection. D-128 - no DDL, nothing dropped, nothing global.
     */
    private function reportWithoutADatabase(): SystemHealthReport
    {
        $broken = $this->createMock(DatabaseManager::class);
        $broken->method('connection')->willThrowException(
            new RuntimeException('SQLSTATE[HY000] could not connect to secret-host.internal as secret_user')
        );

        return new SystemHealthReport(
            new HealthInspector(
                $broken,
                app(Migrator::class),
                app(ConfigurationValidator::class),
                app(IdentityHealthCheck::class),
            ),
            app(IdentityHealthCheck::class),
            new SessionStoreCheck($broken),
            app(CacheStoreCheck::class),
            app(BackgroundWorkCheck::class),
            app(AuditChainVerifier::class),
        );
    }

    /** @return array<string, string> row name => status value */
    private function statusesOf(SystemHealthReport $report): array
    {
        $flat = [];

        foreach ($report->toArray() as $area) {
            foreach ($area['rows'] as $row) {
                $flat[$row['name']] = $row['status'];
            }
        }

        return $flat;
    }

    private function actingAsUser(User $user): self
    {
        return $this->withSession([
            EnsureSessionIsCurrent::SESSION_USER_ID => $user->id,
            EnsureSessionIsCurrent::SESSION_AUTHENTICATED_AT => now()->toIso8601String(),
        ]);
    }

    private function storeIdentityState(string $state): void
    {
        Cache::put(IdentityHealthCheck::LAST_RESULT_KEY, [
            'state' => $state,
            'at' => now()->subHours(2)->toIso8601String(),
        ], now()->addDays(7));
    }

    // -----------------------------------------------------------------------
    // H1. The reused HealthInspector rows report what the check returned.
    // -----------------------------------------------------------------------

    /** H1. Mutation: hard-code the status. */
    public function test_the_reused_local_checks_report_available_when_healthy(): void
    {
        $statuses = $this->statuses();

        foreach (['Database structure', 'Required settings', 'Database', 'File storage'] as $row) {
            $this->assertSame(
                HealthStatus::Available->value,
                $statuses[$row] ?? null,
                "[{$row}] did not report Available on a healthy deployment."
            );
        }
    }

    /**
     * H1b. A check that STOPS EXISTING is Not checked, not Available.
     *
     * The projection reads HealthInspector by key. If a future change renames
     * one, the row must say nobody looked rather than quietly reporting health
     * for a check that no longer runs - the exact shape of a status nothing
     * measured.
     *
     * Mutation: `$local[$key]['ok'] ?? true`, which is the plausible version of
     * this line and reports Available for a check that has gone.
     */
    public function test_a_local_check_that_is_absent_is_not_checked(): void
    {
        $projection = new \ReflectionMethod(SystemHealthReport::class, 'fromLocal');

        $row = $projection->invoke($this->report(), [], 'database', 'Database', 'fine', 'broken');

        $this->assertSame(HealthStatus::NotChecked, $row->status);
    }

    // -----------------------------------------------------------------------
    // H2, H5. The existing checks go red when their dependency breaks.
    // -----------------------------------------------------------------------

    /**
     * H2. Database unreachable -> Unavailable.
     *
     * Induced at the CONNECTION boundary: the default connection is pointed at
     * a database that cannot be opened. No DDL, nothing dropped.
     *
     * Mutation: return Available on exception.
     */
    public function test_a_database_that_cannot_be_opened_is_unavailable(): void
    {
        $this->assertSame(HealthStatus::Available->value, $this->statuses()['Database']);

        $statuses = $this->statusesOf($this->reportWithoutADatabase());

        $this->assertSame(HealthStatus::Unavailable->value, $statuses['Database']);
    }

    /**
     * H5. File storage unwritable -> Unavailable.
     *
     * Mutation: read the configuration instead of the directory. The check
     * would then be green with the directory gone, which is the hard-coded
     * success this screen exists to prevent.
     */
    public function test_storage_that_is_not_there_is_unavailable(): void
    {
        $this->assertSame(HealthStatus::Available->value, $this->statuses()['File storage']);

        /*
         * The STORAGE PATH is moved, not the permissions.
         *
         * chmod 0500 was the first version and it was vacuous in CI: the
         * container runs as root, which can write to a read-only directory, so
         * the check stayed green and the case would have been skipped on the
         * one machine that runs it. A test that is skipped where it matters is
         * not a guard.
         *
         * Pointing the application at a directory that does not exist breaks
         * the same dependency for every user, root included.
         */
        $original = $this->app->storagePath();
        $this->app->useStoragePath('/nonexistent-directory/storage');

        try {
            $status = $this->statuses()['File storage'] ?? null;
        } finally {
            $this->app->useStoragePath($original);
        }

        $this->assertSame(HealthStatus::Unavailable->value, $status);
    }

    // -----------------------------------------------------------------------
    // H6, H7. Microsoft Entra ID - the stored answer, and absence.
    // -----------------------------------------------------------------------

    /**
     * H6. The three real states survive.
     *
     * Mutation: source the row from forInspector(), which collapses DEGRADED
     * into ok = true. A degraded sign-in would then render as Available - a
     * six-state vocabulary fed by a two-state source.
     */
    public function test_the_stored_identity_state_is_preserved_in_all_three_directions(): void
    {
        foreach ([
            IdentityHealthReport::FAILED => HealthStatus::Unavailable,
            IdentityHealthReport::DEGRADED => HealthStatus::Degraded,
            IdentityHealthReport::HEALTHY => HealthStatus::Available,
        ] as $stored => $expected) {
            Cache::flush();
            $this->storeIdentityState($stored);

            $this->assertSame(
                $expected->value,
                $this->statuses()['Microsoft Entra ID'] ?? null,
                "A stored [{$stored}] did not render as [{$expected->value}]."
            );
        }
    }

    /**
     * H7. Nothing trustworthy stored -> Not checked. FOUR SHAPES OF ABSENCE.
     *
     * Each is a real way the cache can be empty or wrong: never written, a
     * non-array value, an array with no state, and a state from an older
     * release. All four must be Not checked, and NONE may be Available.
     *
     * Mutation: `?? 'healthy'`, or a default arm that lands on Available.
     */
    public function test_every_shape_of_absence_is_not_checked(): void
    {
        $absences = [
            'never written' => null,
            'a non-array value' => 'healthy',
            'an array with no state' => ['at' => '2026-01-01T00:00:00+00:00'],
            'an unrecognised state' => ['state' => 'probably_fine', 'at' => '2026-01-01T00:00:00+00:00'],
        ];

        foreach ($absences as $description => $stored) {
            Cache::flush();

            if ($stored !== null) {
                Cache::put(IdentityHealthCheck::LAST_RESULT_KEY, $stored, now()->addDay());
            }

            $this->assertSame(
                HealthStatus::NotChecked->value,
                $this->statuses()['Microsoft Entra ID'] ?? null,
                "With {$description} in the cache, the sign-in row did not read Not checked."
            );
        }
    }

    /**
     * H7 - THE OTHER DIRECTION. Not checked must be ABSENT when a real answer
     * exists.
     *
     * Without this, `return NotChecked` unconditionally would pass the case
     * above. A status that is easy to hard-code needs both halves or neither
     * half is a test.
     */
    public function test_not_checked_disappears_once_something_is_stored(): void
    {
        $this->storeIdentityState(IdentityHealthReport::HEALTHY);

        $this->assertNotSame(
            HealthStatus::NotChecked->value,
            $this->statuses()['Microsoft Entra ID'] ?? null
        );
    }

    /** H7b. The stored answer carries its age; a live row does not. */
    public function test_only_the_stored_row_carries_an_age(): void
    {
        $this->storeIdentityState(IdentityHealthReport::HEALTHY);

        $ages = [];

        foreach ($this->report()->toArray() as $area) {
            foreach ($area['rows'] as $row) {
                $ages[$row['name']] = $row['checkedAt'];
            }
        }

        $this->assertNotNull($ages['Microsoft Entra ID'], 'The stored sign-in answer rendered without its age.');
        $this->assertStringContainsString('ago', (string) $ages['Microsoft Entra ID']);

        foreach ($ages as $name => $age) {
            if ($name === 'Microsoft Entra ID') {
                continue;
            }

            $this->assertNull($age, "[{$name}] is checked live and must not carry an age.");
        }
    }

    // -----------------------------------------------------------------------
    // H8, H9. Jobs - derived, never hard-coded, and broken both ways.
    // -----------------------------------------------------------------------

    /** H8. Not applicable WHILE the driver runs work inline. */
    public function test_the_background_service_is_not_applicable_while_work_runs_inline(): void
    {
        config(['queue.default' => 'sync']);

        $statuses = $this->statuses();

        $this->assertSame(HealthStatus::Available->value, $statuses['Background work']);
        $this->assertSame(HealthStatus::NotApplicable->value, $statuses['Background service']);
    }

    /**
     * H8 - THE OTHER DIRECTION, and the one that catches a hard-coded literal.
     *
     * Change the driver and Not applicable must GO. A `return NotApplicable`
     * passes the case above and fails this one.
     *
     * Mutation: hard-code Not applicable regardless of the driver.
     */
    public function test_not_applicable_goes_when_the_driver_is_not_inline(): void
    {
        config(['queue.default' => 'database']);

        $statuses = $this->statuses();

        $this->assertNotSame(HealthStatus::NotApplicable->value, $statuses['Background service']);
        $this->assertSame(HealthStatus::NotChecked->value, $statuses['Background service']);

        // And it must not claim Available for an arrangement nothing exercised.
        $this->assertNotSame(HealthStatus::Available->value, $statuses['Background work']);
    }

    /** H9. Not configured WHILE nothing is registered. */
    public function test_scheduled_tasks_are_not_configured_while_none_is_registered(): void
    {
        $this->assertSame(HealthStatus::NotConfigured->value, $this->statuses()['Scheduled tasks']);
    }

    /**
     * H9 - THE OTHER DIRECTION. Register one and Not configured must GO.
     *
     * Mutation: hard-code Not configured.
     */
    public function test_not_configured_goes_once_a_task_is_registered(): void
    {
        app(Schedule::class)->command('inspire')->daily();

        $this->assertNotSame(HealthStatus::NotConfigured->value, $this->statuses()['Scheduled tasks']);
    }

    // -----------------------------------------------------------------------
    // H10. Audit.
    // -----------------------------------------------------------------------

    /**
     * H10. A broken chain is Unavailable.
     *
     * The chain head row is REMOVED with ordinary DML, not DDL - P1-08 learnt
     * that Schema::drop() commits the open transaction on MySQL and ends
     * RefreshDatabase's, turning four green-on-SQLite cases red on the engine
     * production uses.
     *
     * Mutation: report the chain without verifying it.
     */
    public function test_a_chain_that_does_not_verify_is_unavailable(): void
    {
        $this->assertSame(HealthStatus::Available->value, $this->statuses()['Record integrity']);

        AuditChainHead::query()->where('id', AuditChainHead::ID)->delete();

        $statuses = $this->statuses();

        $this->assertSame(HealthStatus::Unavailable->value, $statuses['Record integrity']);
        $this->assertSame(HealthStatus::Unavailable->value, $statuses['Record keeping']);
    }

    // -----------------------------------------------------------------------
    // H11, H12. The payload.
    // -----------------------------------------------------------------------

    /**
     * H11. FOUR KEYS PER ROW, asserted as an EQUALITY on the rendered props.
     *
     * On the props rather than on the class, because the class having four
     * properties does not prove the controller sends four - an earlier unit in
     * this project shipped a prop the server never shared and the suite stayed
     * green.
     *
     * Mutation: add a `detail` passthrough.
     */
    public function test_every_rendered_row_carries_exactly_the_four_allowlisted_keys(): void
    {
        $admin = $this->make->user(administrator: true);

        $response = $this->actingAsUser($admin)->get('/console/system-health');
        $response->assertOk();

        $areas = $response->viewData('page')['props']['areas'];

        $this->assertNotSame([], $areas);

        foreach ($areas as $area) {
            $this->assertSame(['name', 'description', 'rows'], array_keys($area));

            foreach ($area['rows'] as $row) {
                $this->assertSame(
                    ['name', 'status', 'explanation', 'checkedAt'],
                    array_keys($row),
                    'A rendered row carries a key that is not on the allowlist.'
                );
            }
        }
    }

    /**
     * H12. No hostname, path, driver, version, tenant id, table name, SQL or
     * exception text anywhere in the payload - INCLUDING WHEN EVERY CHECK HAS
     * FAILED, which is when the leak would actually happen.
     *
     * Mutation: put the caught exception in `explanation`.
     */
    public function test_the_payload_leaks_nothing_even_when_everything_is_broken(): void
    {
        // The injected failure's message deliberately carries a host, a user
        // and a SQLSTATE, so "nothing leaked" is a real claim rather than a
        // claim about a message that had nothing in it.
        config(['session.table' => 'a_table_that_is_not_there']);

        $payload = (string) json_encode($this->reportWithoutADatabase()->toArray());

        $forbidden = [
            'secret-host.internal', 'secret_user', 'SQLSTATE', 'HY000',
            'a_table_that_is_not_there', 'sqlite', 'mysql', 'select ', 'PDO',
            'Exception', 'RuntimeException', 'localhost', '127.0.0.1',
            'session.driver', 'queue.default',
            base_path(), storage_path(),
        ];

        $forbidden[] = 'a_table_that_is_not_there';

        $tenant = (string) config('identity.microsoft.tenant_id');

        if ($tenant !== '') {
            $forbidden[] = $tenant;
        }

        foreach ($forbidden as $needle) {
            $this->assertStringNotContainsStringIgnoringCase(
                (string) $needle,
                $payload,
                "The payload carries [{$needle}], which the four-field row has no place for."
            );
        }
    }

    /**
     * H12b. EVERY EXPLANATION IS ONE THIS UNIT DECLARED - not one it was handed.
     *
     * M-28 replaced the declared sentences with HealthInspector's own details
     * and SURVIVED. Those details are OPERATOR-facing: "3 migration(s)
     * pending", "Build manifest present", "2 configuration problem(s); see the
     * log". None leaks a secret, so the leak guard stayed green - and every one
     * of them would have put developer terminology, a raw count and an
     * instruction to read a log file onto a screen a Product Owner reads.
     * That is the professional-polish gate in CLAUDE.md section 4, and no test
     * was holding it.
     *
     * Asserted in BOTH states, because the healthy sentences and the failure
     * sentences are different strings and a guard on one proves nothing about
     * the other.
     *
     * Mutation: pass $local[$key]['detail'] through to the row.
     */
    public function test_every_explanation_is_business_language_this_unit_declared(): void
    {
        $inspectorDetails = [
            'Connection opened and query executed.', 'Could not open a database connection.',
            'Migration repository does not exist.', 'No pending migrations.',
            'Could not determine migration state.', 'All required configuration present.',
            'Runtime directories writable.', 'A required runtime directory is not writable.',
            'Build manifest present.', 'Build manifest missing; assets were not built or not deployed.',
            'Could not determine identity health.',
        ];

        foreach ([$this->report(), $this->reportWithoutADatabase()] as $report) {
            foreach ($report->toArray() as $area) {
                foreach ($area['rows'] as $row) {
                    $where = "[{$row['name']}]";
                    $explanation = (string) $row['explanation'];

                    $this->assertNotContains($explanation, $inspectorDetails,
                        "{$where} renders HealthInspector's operator detail. The explanation is chosen by this unit, never passed through.");

                    $this->assertDoesNotMatchRegularExpression('/\d/', $explanation,
                        "{$where} carries a figure. A count is an operator's answer, not a reader's.");

                    foreach ([
                        'migration', 'manifest', 'repository', 'config(', 'driver', 'see the log',
                        'null', 'exception', 'query', 'connection string', 'directory', 'runtime',
                    ] as $jargon) {
                        $this->assertStringNotContainsStringIgnoringCase($jargon, $explanation,
                            "{$where} uses the implementation word [{$jargon}] on a user-facing surface.");
                    }

                    $this->assertMatchesRegularExpression('/^[A-Z].*[.]$/s', $explanation,
                        "{$where} is not a finished sentence.");
                }
            }
        }
    }

    /**
     * THE FIVE AREA HEADINGS ARE THE PHASE 1 AUTHORITY'S, EXACTLY.
     *
     * Application, Integrations, Jobs, Connections, Service Health - in that
     * order, with that spelling and that capitalisation.
     *
     * This guard exists because the headings DID drift. "Integrations" became
     * "Sign-in" and "Jobs" became "Tasks and timetables", each for a defensible
     * local reason - Microsoft Entra ID is the only integration today, and an
     * area called "Background work" collided with a row of the same name - and
     * neither reason is this unit's to act on. A heading in the approved scope
     * is a decision already taken; improving it is a change to the product,
     * not a polish fix, and it belongs in a separate conversation.
     *
     * Asserted separately from the row shape so a rename fails with a message
     * that says what happened rather than as a large array diff.
     *
     * Mutation: rename any area. Both directions - a "better" name and a typo.
     */
    public function test_the_five_area_headings_are_the_authoritative_ones(): void
    {
        $this->assertSame(
            ['Application', 'Integrations', 'Jobs', 'Connections', 'Service Health'],
            array_map(fn ($area): string => $area->name, $this->report()->areas()),
            'A System Health area heading is not the Phase 1 authority\'s. These five names are '
            .'approved scope, not presentation, and this unit does not get to improve them.'
        );
    }

    /**
     * NO AREA IS NAMED AFTER A ROW INSIDE IT.
     *
     * Found by looking at the rendered screen, not by a test: the area was
     * called "Background work" and its first row was called "Background work",
     * so the same words appeared twice, three lines apart, meaning the heading
     * and one of the things under it. The UI standard forbids naming a group
     * after its cluster; this is the same mistake one level down.
     *
     * The first fix renamed the AREA, which drifted from approved scope. The
     * authority's own heading - "Jobs" - resolves both: it is not any row's
     * name, so this guard still holds, and nothing was renamed to make it.
     */
    public function test_no_area_shares_its_name_with_a_row(): void
    {
        foreach ($this->report()->areas() as $area) {
            foreach ($area->rows as $row) {
                $this->assertNotSame(
                    strtolower($area->name),
                    strtolower($row->name),
                    "Area [{$area->name}] contains a row with the same name."
                );
            }
        }
    }

    /**
     * THE SIX STATUS WORDS THE PRODUCT OWNER WILL READ, pinned.
     *
     * The React layer maps each backing value to a phrase, and the Product
     * Owner test script quotes those phrases - "queue worker says Not
     * applicable", "scheduler says Not configured". The scheduler word had
     * drifted to "Not set up", which is friendlier and is not what the approved
     * decision says, so a script written against D-121 would have failed on
     * wording alone.
     *
     * Asserted against the SOURCE of the component that renders them, since
     * there is no JavaScript test runner in this project and no test anywhere
     * renders the DOM.
     */
    public function test_the_status_words_on_screen_match_the_approved_decisions(): void
    {
        $source = (string) file_get_contents(base_path('resources/js/Components/HealthStatusBadge.jsx'));

        foreach ([
            'available' => 'Available',
            'degraded' => 'Needs attention',
            'unavailable' => 'Unavailable',
            'not_configured' => 'Not configured',
            'not_applicable' => 'Not applicable',
            'not_checked' => 'Not checked',
        ] as $value => $word) {
            $this->assertStringContainsString(
                "{$value}: '{$word}'",
                $source,
                "The status [{$value}] no longer reads [{$word}] on screen."
            );
        }

        // Every backing value has a word. A status with no phrase would render
        // the fallback, which reads "Not checked" for something that was.
        foreach (HealthStatus::cases() as $case) {
            $this->assertStringContainsString("{$case->value}: '", $source,
                "The status [{$case->value}] has no phrase and would fall back to Not checked.");
        }
    }

    /** And the same rule for the names and the area headings. */
    public function test_no_name_or_heading_exposes_an_implementation_term(): void
    {
        foreach ($this->report()->toArray() as $area) {
            foreach ([$area['name'], $area['description']] as $text) {
                $this->assertDoesNotMatchRegularExpression('/[a-z]+\.[a-z_]+/', (string) $text,
                    'An area heading carries a dotted configuration key.');
            }

            foreach ($area['rows'] as $row) {
                $this->assertDoesNotMatchRegularExpression('/[a-z]+\.[a-z_]+/', (string) $row['name'],
                    "Row name [{$row['name']}] is a dotted key rather than business words.");
                $this->assertDoesNotMatchRegularExpression('/^[a-z_]+$/', (string) $row['name'],
                    "Row name [{$row['name']}] looks like an internal identifier.");
            }
        }
    }

    // -----------------------------------------------------------------------
    // H13. Authorisation.
    // -----------------------------------------------------------------------

    /**
     * H13. PlatformAdmin only.
     *
     * An Auditor and an Organisation Administrator hold EvidenceRead and are
     * refused here, because infrastructure visibility is a different authority
     * from audit-evidence access.
     *
     * Mutation: lower the route to EvidenceRead. The Auditor and the
     * Organisation Administrator would then be admitted.
     */
    public function test_only_a_platform_administrator_may_read_system_health(): void
    {
        $organisation = $this->make->organisation();

        foreach ([
            RoleCode::OrganisationAdministrator,
            RoleCode::Auditor,
            RoleCode::Executive,
            RoleCode::DomainOwner,
            RoleCode::Manager,
            RoleCode::BusinessUser,
        ] as $role) {
            $user = $this->make->user($organisation);
            $this->make->roleAssignment($user, $role, $organisation);

            $response = $this->actingAsUser($user)->get('/console/system-health');

            $this->assertNotSame(
                200,
                $response->getStatusCode(),
                "[{$role->value}] reached System Health, which is PlatformAdmin only."
            );
        }

        $admin = $this->make->user(administrator: true);
        $this->actingAsUser($admin)->get('/console/system-health')->assertOk();
    }

    /** H13b. An anonymous visitor reaches nothing. */
    public function test_an_anonymous_visitor_is_refused(): void
    {
        $this->get('/console/system-health')->assertStatus(302);
    }

    // -----------------------------------------------------------------------
    // H14. Rendering records nothing.
    // -----------------------------------------------------------------------

    /**
     * H14. Opening System Health writes no audit row and logs no security
     * event.
     *
     * Reading a health page changes no state, reveals no evidence and names
     * nobody. An event here would write a row into the chain on every refresh.
     *
     * Mutation: add a `system.health.checked` key and record it.
     */
    public function test_rendering_records_no_event(): void
    {
        $admin = $this->make->user(administrator: true);

        $before = AuditEvent::query()->count();

        $logged = [];
        Log::listen(function ($message) use (&$logged): void {
            $logged[] = $message->message;
        });

        $this->actingAsUser($admin)->get('/console/system-health')->assertOk();

        $this->assertSame($before, AuditEvent::query()->count(), 'Rendering System Health wrote an audit row.');

        foreach ($logged as $message) {
            $this->assertStringNotContainsString('system.health', (string) $message);
            $this->assertStringNotContainsString('system_health', (string) $message);
        }
    }

    // -----------------------------------------------------------------------
    // H17. Every row has a check behind it.
    // -----------------------------------------------------------------------

    /**
     * H17. FOURTEEN ROWS, FIVE AREAS, asserted as an equality.
     *
     * 9 projected from existing authoritative sources, 2 new round trips, 3
     * derived from the deployment. The DESIGN said "eleven" while its own
     * tables listed fourteen; this equality is why the document and the
     * implementation cannot drift again.
     *
     * A row added without a check behind it fails here, and so does a row
     * silently lost. The names are the business words a Product Owner reads,
     * which is also what the test script refers to.
     */
    public function test_the_five_areas_carry_exactly_the_fourteen_declared_rows(): void
    {
        $shape = [];

        foreach ($this->report()->areas() as $area) {
            $shape[$area->name] = array_map(fn ($row) => $row->name, $area->rows);
        }

        $this->assertSame([
            'Application' => ['Database structure', 'Required settings', 'Screens and styling'],
            'Integrations' => ['Microsoft Entra ID'],
            'Jobs' => ['Background work', 'Background service', 'Scheduled tasks'],
            'Connections' => ['Database', 'Staying signed in', 'Temporary storage', 'File storage'],
            'Service Health' => ['Local service health', 'Record integrity', 'Record keeping'],
        ], $shape);
    }

    /**
     * Every rendered status is one of the six. Nothing invents a seventh, and
     * nothing renders a raw PHP identifier.
     */
    public function test_every_rendered_status_is_one_of_the_declared_six(): void
    {
        $declared = array_map(fn (HealthStatus $s): string => $s->value, HealthStatus::cases());

        foreach ($this->statuses() as $name => $status) {
            $this->assertContains($status, $declared, "[{$name}] rendered [{$status}], which is not a declared status.");
        }
    }

    // -----------------------------------------------------------------------
    // Local service health is NOT the /up verdict.
    // -----------------------------------------------------------------------

    /**
     * The roll-up covers the local checks and EXCLUDES sign-in, which is the
     * whole reason it is not called "overall health".
     *
     * With sign-in stored as FAILED, Local service health must still be
     * Available - and the screen must say so on its own row rather than through
     * a verdict that silently merges the two.
     *
     * Mutation: roll identity into this row, or source it from
     * HealthInspector::inspect(). Either makes this assertion fail, and
     * inspect() would also reach the network.
     */
    public function test_local_service_health_excludes_sign_in(): void
    {
        $this->storeIdentityState(IdentityHealthReport::FAILED);

        $statuses = $this->statuses();

        $this->assertSame(HealthStatus::Unavailable->value, $statuses['Microsoft Entra ID']);
        $this->assertSame(HealthStatus::Available->value, $statuses['Local service health']);
    }

    /** And it goes red when a local check does. */
    public function test_local_service_health_fails_when_a_local_check_fails(): void
    {
        $statuses = $this->statusesOf($this->reportWithoutADatabase());

        $this->assertSame(HealthStatus::Unavailable->value, $statuses['Local service health']);
    }

    /** The transaction guard: rendering opens none that it leaves open. */
    public function test_rendering_leaves_no_transaction_open(): void
    {
        $before = DB::transactionLevel();

        $this->report()->toArray();

        $this->assertSame($before, DB::transactionLevel(), 'System Health left a transaction open.');
    }
}
