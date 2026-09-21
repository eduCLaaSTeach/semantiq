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

        /*
         * P1-05. The six points at which Roles & Access legitimately names a
         * domain, and no others.
         *
         * A DOMAIN STILL GRANTS NOTHING. What P1-05 added is the opposite: a
         * DISABLED domain DENIES, as a global gate outside every grant path.
         * That is a subtraction, and Guard B below is amended to assert exactly
         * that distinction rather than to exempt these files from it.
         *
         * StepUpController is the sixth, added by the Gate C correction: a
         * self-granted entitlement is performed from the STORED domain id after
         * re-authentication, so the step-up return has to name the domain it
         * was confirmed for. It reads one; it decides nothing.
         */
        'app/Modules/Access/Engine/AccessEngine.php',
        'app/Modules/Access/Models/DomainEntitlement.php',
        'app/Modules/Access/Services/EntitlementService.php',
        'app/Modules/Access/Http/Controllers/AccessController.php',
        'app/Modules/Access/Http/Controllers/SimulatorController.php',
        'app/Modules/Access/Http/Controllers/StepUpController.php',

        /*
         * P1-07. TWO points, both READ-ONLY, and both about ACCOUNTABILITY
         * rather than access.
         *
         * ReviewerAuthority reads business_domain_owners to establish who has
         * standing to ATTEST to a grant in a domain. That is the one place the
         * distinction B-1a rests on has to be made carefully: being an owner
         * confers authority to review and NOTHING ELSE. It writes no ownership,
         * creates no assignment and no entitlement, and Guard B below still
         * applies to it in full - a domain still grants nothing, to its owner
         * or to anybody.
         *
         * AccessReviewItem names the domain so a review row can say which
         * business domain it is about. It reads one; it decides nothing.
         *
         * OwnershipGrantsNoAccessTest is the behavioural half of this: it
         * reviews AS an owner and asserts the owner ends with no role and no
         * entitlement.
         */
        'app/Modules/Reviews/Services/ReviewerAuthority.php',
        'app/Modules/Reviews/Models/AccessReviewItem.php',

        /*
         * P1-06. ONE point, and it READS ONLY.
         *
         * DomainAdapter reports each domain's posture - enabled or disabled,
         * whether somebody is accountable for it, and counts of what has been
         * granted into it. It decides nothing, writes nothing, and adds no way
         * for a domain to confer access: Guard B below still applies to it in
         * full, so a domain remains something that grants nothing and a
         * DISABLED one still only denies.
         *
         * It is one file rather than several deliberately - the whole of
         * P1-06's dependency on Domains passes through it, so this line is the
         * complete statement of that coupling.
         */
        'app/Modules/Security/Posture/Adapters/DomainAdapter.php',
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
     * P1-11 WIDENED THIS GUARD WITHOUT WEAKENING IT, and the distinction is the
     * point. D-181 gave P1-04 a READ SEAM -
     * App\Modules\Domains\Projection\DomainSummaryProjection - which exists so
     * that a consumer can ask P1-04 a question instead of writing P1-04's
     * queries somewhere else.
     *
     * The obvious way to admit its one consumer was to add
     * AdministrationHomeProjection to ALLOWED_OUTSIDE_THE_MODULE. That would
     * have been WRONG: the allowlist exempts a FILE from the whole guard, so
     * the same file could then have reached past the seam for BusinessDomain,
     * DomainOwnership or business_domains and nothing would have failed. The
     * seam's entire purpose would have been unguarded in its only consumer.
     *
     * So the SEAM's fully-qualified names are removed from the source before
     * the scan, and nothing else is. Naming the seam is free for any file;
     * naming a model or a table is a finding for every file that is not on the
     * allowlist, the seam's consumers included.
     *
     * Mutation: add a BusinessDomain reference to an unrelated service. The
     * second one, which is how a boundary is really lost: add the reference AND
     * widen ALLOWED_OUTSIDE_THE_MODULE to admit it. And the third, which is the
     * one this widening created: add BusinessDomain::query() to
     * AdministrationHomeProjection - the file that legitimately names the seam.
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

            $source = $this->withoutTheReadSeam((string) file_get_contents($file));

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
     * The D-181 read seam, removed so a consumer may NAME it and still be
     * caught reaching past it.
     *
     * Deliberately matched as a FULLY-QUALIFIED NAME under
     * App\Modules\Domains\Projection. A looser pattern - say, anything
     * containing "Projection" - would have let `Modules\Domains\Models`
     * through, which is the whole thing being guarded.
     */
    private function withoutTheReadSeam(string $source): string
    {
        return (string) preg_replace(
            '/App\\\\Modules\\\\Domains\\\\Projection\\\\\w+/',
            '',
            $source,
        );
    }

    /**
     * The seam exemption is NARROW: naming the projection is free, naming a
     * model is not.
     *
     * This is the half that stops withoutTheReadSeam() from quietly becoming a
     * hole. A stripper that removed too much would pass Guard A for a file that
     * queries business_domains directly, and Guard A's own "no offenders"
     * assertion could never tell.
     *
     * Mutation: widen the pattern to /App\\Modules\\Domains\\\w+/ - the seam
     * still strips, and this fails because the models strip too.
     */
    public function test_the_seam_exemption_does_not_hide_a_model_or_a_table(): void
    {
        $consumer = <<<'PHP'
        <?php
        use App\Modules\Domains\Projection\DomainSummaryProjection;
        use App\Modules\Domains\Projection\DomainSummary;
        PHP;

        $this->assertStringNotContainsString(
            'Modules\Domains',
            $this->withoutTheReadSeam($consumer),
            'A file that names only the read seam is still reported as depending on Domains, so '
            .'the seam cannot be consumed at all.'
        );

        /*
         * THE MODEL NAMESPACE MUST SURVIVE THE STRIPPER INTACT, and that is
         * asserted directly rather than as an OR over four needles.
         *
         * THIS CASE WAS REWRITTEN BECAUSE A MUTATION SURVIVED IT. Widening the
         * pattern to App\Modules\Domains\\w+ - which strips the MODEL
         * namespace as well as the seam's - passed the earlier version,
         * because `use App\Modules\Domains\Models\BusinessDomain;` still left
         * the bare word "BusinessDomain" behind and the OR was satisfied by
         * that. The assertion was true and said nothing about the pattern.
         *
         * Mutation: widen the pattern to /App\\Modules\\Domains\\\w+/.
         */
        foreach ([
            'use App\Modules\Domains\Models\BusinessDomain;',
            'use App\Modules\Domains\Models\DomainOwnership;',
            '\App\Modules\Domains\Models\BusinessDomain::query()->count();',
        ] as $reachingPast) {
            $this->assertStringContainsString(
                'Modules\Domains\Models',
                $this->withoutTheReadSeam($consumer."\n".$reachingPast),
                "[{$reachingPast}] has its MODEL namespace stripped by the seam exemption. The "
                .'exemption must remove the Projection namespace and nothing else, or a consumer '
                .'could name the seam and then query the tables directly - which is exactly what '
                .'the seam exists to prevent.'
            );
        }

        // ...and the three needles Guard A scans for survive too, so a file
        // reaching past the seam is caught by name as well as by namespace.
        foreach (['BusinessDomain', 'DomainOwnership', 'business_domains'] as $needle) {
            $this->assertStringContainsString(
                $needle,
                $this->withoutTheReadSeam($consumer."\nBusinessDomain DomainOwnership business_domains"),
                "[{$needle}] is stripped by the seam exemption."
            );
        }
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
     * GUARD B. A DOMAIN NEVER GRANTS. AMENDED BY P1-05.
     *
     * As written for P1-04 this asserted that NOTHING anywhere reads domain
     * state to authorize. P1-05 makes that literally false and deliberately so:
     * a DISABLED domain is a global DENY gate, checked before any grant path.
     *
     * The distinction that survives, and that this guard now asserts, is the
     * one that always mattered: domain state may only SUBTRACT. Reading
     * DomainStatus to deny is the carried P1-04 gate working. Reading domain
     * OWNERSHIP or its ACCESS EXPECTATION to decide anything would be a grant
     * derived from a domain, and that is still forbidden everywhere - including
     * inside the Access module, which is where somebody would now put it.
     *
     * Mutation: have RequireActionClass consult a domain's owner; have the
     * engine read access_expectation; have a React component hide a menu entry
     * on DomainStatus.
     */
    public function test_no_authorization_path_reads_domain_state(): void
    {
        $authorizationPaths = [
            base_path('app/Modules/Platform/Http/Middleware'),
            base_path('app/Modules/Organisation/Http/Middleware'),
            base_path('app/Modules/Platform/Security'),
            base_path('app/Shared/Navigation'),
            base_path('app/Http/Middleware'),
            base_path('app/Modules/Access/Http/Middleware'),
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
     * GUARD B, THE P1-05 HALF - N-B16 and N-B17.
     *
     * The Access module may read a domain's STATUS, because a disabled domain
     * denies. It may read NOTHING ELSE about a domain.
     *
     * DomainOwnership is the one that matters. P1-04's business_domain_owners
     * remains the SOLE source of domain accountability, and the P1-05
     * domain_owner role is a security role only - neither may be derived from
     * the other. The convenience a well-meaning developer adds is "they own it,
     * so give them the role", and every functional test would still pass.
     *
     * access_expectation is D-61: CONTEXT ONLY. P1-04 shipped it as a label,
     * and reading it here would quietly make it authorization.
     *
     * This fails at the DEPENDENCY, not at a behaviour, which is why it is an
     * architecture test.
     */
    public function test_the_access_module_never_reads_domain_ownership_or_expectation(): void
    {
        $scanned = 0;

        foreach ($this->phpFilesIn(base_path('app/Modules/Access')) as $file) {
            $scanned++;

            // COMMENTS STRIPPED FIRST. This file's own docblocks explain the
            // separation and name the table while doing so; a guard that
            // matched prose would fail on the documentation that exists to
            // prevent the defect. The P1-04 lesson, applied the other way
            // round: there a docblock must not SATISFY an assertion, here it
            // must not TRIGGER one. Only executable code counts.
            $source = $this->codeOnly((string) file_get_contents($file));

            foreach (['DomainOwnership', 'business_domain_owners', 'access_expectation', 'AccessExpectation'] as $needle) {
                $this->assertStringNotContainsString(
                    $needle,
                    $source,
                    basename($file)." reads [{$needle}]. Owning a domain grants no role, holding the "
                    .'domain_owner role confers no ownership, and access_expectation is context only '
                    .'(D-61). Neither relationship may be derived from the other.'
                );
            }
        }

        $this->assertGreaterThan(10, $scanned, 'Almost no Access files were scanned.');
    }

    /**
     * And the mirror: the Domains module never reads a role assignment to
     * decide ownership.
     *
     * Both directions, because a developer closing one would not necessarily
     * close the other.
     */
    public function test_the_domains_module_never_reads_role_assignments(): void
    {
        $scanned = 0;

        foreach ($this->phpFilesIn(base_path('app/Modules/Domains')) as $file) {
            $scanned++;

            $source = $this->codeOnly((string) file_get_contents($file));

            foreach (['RoleAssignment', 'role_assignments', 'RoleCode', 'AccessEngine'] as $needle) {
                $this->assertStringNotContainsString(
                    $needle,
                    $source,
                    basename($file)." reads [{$needle}]. Domain accountability is decided by "
                    .'business_domain_owners alone, never by what role somebody holds.'
                );
            }
        }

        $this->assertGreaterThan(5, $scanned, 'Almost no Domains files were scanned.');
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

    /**
     * PHP source with every comment removed, so a guard reads what the code
     * DOES rather than what it says about itself.
     *
     * token_get_all rather than a regular expression: a regex over PHP source
     * gets strings containing slashes wrong, and a guard that is wrong in a
     * corner is a guard nobody trusts.
     */
    private function codeOnly(string $source): string
    {
        $kept = '';

        foreach (token_get_all($source) as $token) {
            if (is_array($token)) {
                if (in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }

                $kept .= $token[1];

                continue;
            }

            $kept .= $token;
        }

        return $kept;
    }
}
