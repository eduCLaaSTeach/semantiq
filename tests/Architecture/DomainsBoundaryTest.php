<?php

declare(strict_types=1);

namespace Tests\Architecture;

use App\Modules\Domains\Models\AccessExpectation;
use App\Modules\Domains\Models\DomainStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * P1-04 delivers business domains AND NO ACCESS MODEL. This is the file that
 * says so against the code rather than in a comment.
 *
 * The risk here is sharper than P1-03's. A group at least SOUNDS inert;
 * "Finance domain, owner Salil, enabled" reads like a grant to almost every
 * reader, and the pressure to make it one will be constant. P1-05 owns roles,
 * domain entitlements, scopes, sensitivity ceilings and effective access, and
 * the way it arrives early is not a deliberate decision - it is a column called
 * `visible_to` added because it seemed useful, or a middleware that reads
 * `status` "just for now".
 *
 * TWO GUARDS, WITH DIFFERENT SCOPES, and the difference is the whole point.
 *
 *   GUARD A - module dependency. Scope: app/, excluding the module. Three
 *   wiring exceptions and one approved integration. Migrations, tests and
 *   resources/js are OUT OF SCOPE, because a migration defines the schema, a
 *   test that could not name the thing it tests would test nothing, and the
 *   screens legitimately render domain props.
 *
 *   GUARD B - authorization. Scope: app/ AND resources/js, the whole
 *   application, THE DOMAINS MODULE INCLUDED. No exceptions at all.
 *
 * An earlier draft of this guard said "nothing outside the module may reference
 * a domain, except three files". That could not hold, and a rule that cannot
 * hold gets weakened the first time it fails.
 */
final class DomainsBoundaryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The three wiring/integration points, and nothing else in app/.
     *
     * @var list<string>
     */
    private const ALLOWED_OUTSIDE_THE_MODULE = [
        'app/Modules/Organisation/Services/OrganisationService.php',
        'app/Shared/Navigation/ApprovedMenu.php',
    ];

    /**
     * N3b, the PHYSICAL schema - Schema::getColumnListing, not the migration
     * source. Reading the migration would prove what the file says, not what
     * the database has: a later migration adding a column would leave it
     * passing.
     *
     * Mutation: add `$table->string('visible_to')` in a second migration.
     */
    public function test_the_domain_tables_have_exactly_their_declared_columns(): void
    {
        $this->assertSame(
            ['id', 'organisation_id', 'code', 'name', 'description', 'kind', 'status',
                'access_expectation', 'created_at', 'updated_at'],
            Schema::getColumnListing('business_domains'),
            'The physical business_domains table is not the declared one. A domain is a name and an '
            .'accountability; a column that could be read as a grant is P1-05 arriving early.'
        );

        $this->assertSame(
            ['id', 'business_domain_id', 'user_id', 'assigned_at', 'ended_at', 'created_at', 'updated_at'],
            Schema::getColumnListing('business_domain_owners'),
            'The physical business_domain_owners table is not the declared one.'
        );
    }

    /**
     * N3b, second half - and the half that survives somebody updating the list
     * above without thinking about why it is there.
     *
     * Mutation: add `grantee_role` to the table AND to the expected list. The
     * equality above then passes; this fails.
     */
    public function test_no_domain_column_can_be_read_as_a_grant(): void
    {
        $forbidden = ['role', 'permission', 'scope', 'sensitivity', 'entitlement',
            'ceiling', 'grant', 'allow', 'deny', 'visible', 'admin'];

        $checked = 0;

        foreach (['business_domains', 'business_domain_owners'] as $table) {
            foreach (Schema::getColumnListing($table) as $column) {
                foreach ($forbidden as $word) {
                    $this->assertStringNotContainsString(
                        $word,
                        strtolower($column),
                        "[{$table}.{$column}] contains [{$word}]. P1-04 delivers no access model."
                    );
                }

                $checked++;
            }
        }

        $this->assertGreaterThan(10, $checked, 'Almost nothing was checked.');
    }

    /**
     * N3d. NO COLUMN ANYWHERE IN P1-04 IS ABOUT SENSITIVITY.
     *
     * D-47 defers the whole dimension - Standard, Confidential, Restricted and
     * the enforced ceilings - to P1-05. Not the ceiling, not an inert
     * statement, not the vocabulary.
     *
     * Called out separately from the word list above because it has its own
     * decision behind it, and because the first DESIGN draft proposed exactly
     * this column while asserting it must not exist.
     *
     * Mutation: add sensitivity_expectation back.
     */
    public function test_nothing_in_p1_04_models_sensitivity(): void
    {
        foreach (['business_domains', 'business_domain_owners'] as $table) {
            foreach (Schema::getColumnListing($table) as $column) {
                $this->assertStringNotContainsString('sensitivity', strtolower($column));
            }
        }

        // And the vocabulary is not borrowed either: "confidential" and
        // "restricted" belong to P1-05's ENFORCED dimension, and reusing them
        // for an ADVISORY field would put two concepts behind one word.
        foreach (AccessExpectation::cases() as $case) {
            $this->assertNotContains(
                $case->value,
                ['confidential', 'restricted', 'standard'],
                "[{$case->value}] is a P1-05 sensitivity word being used for an advisory field."
            );
        }
    }

    /**
     * N23, at the SCHEMA. Ownership timing is DATETIME, and there is no
     * uniqueness involving assigned_at.
     *
     * P1-01 keyed team membership on (team_id, user_id, joined_at) over DATE
     * values. Hand a domain over and take it back in one day and the second
     * period carries the same three key values as the first, so the database
     * refuses it with an integrity error about something the administrator did
     * nothing to cause. P1-03 paid for that with a correction, and then
     * PRODUCTION PRODUCED EXACTLY THAT CASE for group membership on its first
     * day of use.
     *
     * Asserted against the declared column type rather than against two written
     * values, because SQLite has type affinity rather than types: a DATE column
     * there happily stores and returns a full timestamp, so a behavioural test
     * passes against the very mutation this exists to catch.
     *
     * Mutation: make assigned_at a date(); add UNIQUE(business_domain_id,
     * assigned_at).
     */
    public function test_ownership_timing_is_datetime_and_carries_no_uniqueness(): void
    {
        $types = [];

        foreach (Schema::getColumns('business_domain_owners') as $column) {
            $types[$column['name']] = strtolower((string) $column['type']);
        }

        foreach (['assigned_at', 'ended_at'] as $column) {
            $this->assertArrayHasKey($column, $types);

            $this->assertStringContainsString(
                'datetime',
                $types[$column],
                "[business_domain_owners.{$column}] is not a DATETIME. Two ownership periods on one "
                .'calendar day must be distinguishable - the P1-01 collision.'
            );
        }

        foreach (Schema::getIndexes('business_domain_owners') as $index) {
            if (! ($index['unique'] ?? false)) {
                continue;
            }

            $this->assertNotContains(
                'assigned_at',
                $index['columns'],
                'A unique key involves assigned_at. That is the P1-01 collision: the invariant worth '
                .'enforcing is "at most one CURRENT owner", not "no two periods share a start".'
            );
        }
    }

    /**
     * N3c. There is no owner column - the ownership table is the only source of
     * truth for who owns a domain.
     *
     * Fails on the column's EXISTENCE, not on two values disagreeing: a second
     * writable source of truth is wrong even during the period it agrees.
     */
    public function test_the_domain_carries_no_owner_column(): void
    {
        $this->assertNotContains('owner_user_id', Schema::getColumnListing('business_domains'));
    }

    /**
     * GUARD A. Within app/, only the declared points depend on Domains.
     *
     * MIGRATIONS, TESTS AND resources/js ARE OUT OF SCOPE, and the test says so
     * in its own name and message so nobody later reads a passing run as a
     * claim it did not make.
     *
     * Mutation: add a BusinessDomain reference to an unrelated service. And the
     * second one, which is how a boundary is really lost: add the reference AND
     * widen ALLOWED_OUTSIDE_THE_MODULE to admit it.
     */
    public function test_only_the_declared_integration_points_depend_on_domains(): void
    {
        $offenders = [];
        $scanned = 0;

        foreach ($this->phpFilesIn(base_path('app')) as $file) {
            $relative = str_replace(base_path().'/', '', $file);

            if (str_starts_with($relative, 'app/Modules/Domains/')) {
                continue;
            }

            $scanned++;

            $source = (string) file_get_contents($file);

            $mentions = str_contains($source, 'Modules\\Domains')
                || str_contains($source, 'business_domains')
                || str_contains($source, 'BusinessDomain')
                || str_contains($source, 'DomainOwnership');

            if ($mentions && ! in_array($relative, self::ALLOWED_OUTSIDE_THE_MODULE, true)) {
                $offenders[] = $relative;
            }
        }

        $this->assertGreaterThan(
            40,
            $scanned,
            'Almost no files were scanned, so this guard would pass against an empty directory.'
        );

        $this->assertSame(
            [],
            $offenders,
            'Code outside the Domains module depends on it. Only the declared wiring and the one '
            .'approved Company Profile integration may. (Migrations, tests and resources/js are '
            .'deliberately outside this guard - see Guard B for what they may not do.)'
        );
    }

    /**
     * GUARD A is not vacuous: it finds a violation when one exists.
     *
     * A scanner pointed at the wrong directory passes by scanning nothing,
     * which is the failure mode a "no offenders" assertion cannot detect on its
     * own.
     */
    public function test_the_dependency_scan_would_actually_catch_a_violation(): void
    {
        $found = [];

        foreach ($this->phpFilesIn(base_path('app')) as $file) {
            $source = (string) file_get_contents($file);

            if (str_contains($source, 'BusinessDomain')) {
                $found[] = str_replace(base_path().'/', '', $file);
            }
        }

        $this->assertNotEmpty(
            $found,
            'The scanner found no reference to BusinessDomain anywhere in app/, including inside the '
            .'module itself. It is looking in the wrong place, and every other assertion it makes is '
            .'worthless.'
        );
    }

    /**
     * GUARD B. NOTHING ANYWHERE AUTHORIZES FROM DOMAIN STATE.
     *
     * Scope: app/ and resources/js, the Domains module included. No exceptions.
     * A domain's status, its owner and its access expectation are never read to
     * decide what somebody may see or do.
     *
     * Mutation: have RequireSystemAdministrator consult a domain; have a React
     * component hide a menu entry on DomainStatus.
     */
    public function test_no_authorization_path_reads_domain_state(): void
    {
        $authorizationPaths = [
            base_path('app/Modules/Platform/Http/Middleware'),
            base_path('app/Modules/Organisation/Http/Middleware'),
            base_path('app/Modules/Platform/Security'),
            base_path('app/Shared/Navigation'),
            base_path('app/Http/Middleware'),
        ];

        $scanned = 0;

        foreach ($authorizationPaths as $path) {
            if (! is_dir($path)) {
                continue;
            }

            foreach ($this->phpFilesIn($path) as $file) {
                $scanned++;

                $source = (string) file_get_contents($file);

                foreach (['business_domains', 'BusinessDomain', 'DomainStatus', 'access_expectation', 'DomainOwnership'] as $needle) {
                    // ApprovedMenu names the ROUTE, which is wiring, not a
                    // decision about a domain. Everything else is a finding.
                    if (str_contains($source, $needle)) {
                        $this->fail(
                            basename($file)." reads [{$needle}]. Nothing may use a domain's status, "
                            .'owner or access expectation to decide what somebody may see.'
                        );
                    }
                }
            }
        }

        $this->assertGreaterThan(5, $scanned, 'Almost no authorization files were scanned.');
    }

    /**
     * GUARD B, the frontend half. The screens may RENDER domain state and must
     * never ENFORCE with it.
     *
     * Hiding "Enable" on an already-enabled domain is presentation. Hiding a
     * menu entry, a route or another unit's data would be enforcement - so
     * DomainStatus and access_expectation appear in exactly two files, and
     * nowhere near navigation.
     *
     * Mutation: have AppShell or the navigation component branch on a domain.
     */
    public function test_no_screen_outside_domains_reads_domain_state(): void
    {
        $offenders = [];
        $scanned = 0;

        foreach ($this->filesIn(base_path('resources/js'), 'jsx') as $file) {
            $relative = str_replace(base_path().'/', '', $file);

            if (str_contains($relative, 'Pages/Domains/') || str_contains($relative, 'DomainsPage.jsx')) {
                continue;
            }

            $scanned++;

            $source = (string) file_get_contents($file);

            foreach (['access_expectation', 'expectationLabel', 'needsAttention', 'business_domains'] as $needle) {
                if (str_contains($source, $needle)) {
                    $offenders[] = "{$relative} reads {$needle}";
                }
            }
        }

        $this->assertGreaterThan(10, $scanned, 'Almost no screens were scanned.');

        $this->assertSame([], $offenders, 'A screen outside Business Domains reads domain state.');
    }

    /**
     * N34 and N35, source half. Neither `status` nor `access_expectation` is
     * read to make a decision anywhere in the application - including inside
     * the Domains module, where the only legitimate uses are storing them,
     * rendering them, and the D-42 enable rule.
     *
     * The behavioural half is DomainAccessBoundaryTest: an owner and a
     * non-owner get identical answers from every route.
     */
    public function test_the_expectation_is_never_branched_on(): void
    {
        $scanned = 0;

        foreach ($this->phpFilesIn(base_path('app')) as $file) {
            $source = (string) file_get_contents($file);
            $scanned++;

            foreach (AccessExpectation::cases() as $case) {
                if ($case === AccessExpectation::Undecided) {
                    // The default, written at creation. Not a decision.
                    continue;
                }

                $this->assertStringNotContainsString(
                    "AccessExpectation::{$case->name} =>",
                    $source,
                    basename($file).' branches on an access expectation. It is advisory: nothing '
                    .'may read it to decide anything.'
                );
            }

            $this->assertStringNotContainsString(
                'DomainStatus::Enabled ?',
                $source,
                basename($file).' branches on a domain status to produce a value.'
            );
        }

        $this->assertGreaterThan(40, $scanned);
    }

    /** The two enums stay small, and stay the approved words. */
    public function test_the_domain_enums_are_the_approved_ones(): void
    {
        $this->assertSame(['enabled', 'disabled'], array_column(DomainStatus::cases(), 'value'));

        $this->assertSame(
            ['undecided', 'broad', 'limited', 'exceptional'],
            array_column(AccessExpectation::cases(), 'value'),
            'The access-expectation vocabulary is not D-48\'s.'
        );
    }

    /** @return list<string> */
    private function phpFilesIn(string $directory): array
    {
        return $this->filesIn($directory, 'php');
    }

    /** @return list<string> */
    private function filesIn(string $directory, string $extension): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $files = [];

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === $extension) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }
}
