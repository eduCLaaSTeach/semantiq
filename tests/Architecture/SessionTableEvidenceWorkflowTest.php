<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;

/**
 * THE SAFETY CONTRACT OF verify-session-table-evidence.yml.
 *
 * That workflow exists to close the two WS-1 evidence gaps the go/no-go pack
 * could not close: P-5, where "the sessions table exists in production" was
 * inferred rather than observed, and Fact B, the pre-change baseline nothing
 * could collect. It closes them by doing the one thing the existing read-only
 * session-store verification refuses to do - looking at the configured session
 * table while the effective driver is `file`.
 *
 * THAT IS ALSO WHAT MAKES IT WORTH TESTING THIS HARD. It reads a table that
 * holds session identifiers, payloads, user ids, IP addresses and user agents,
 * against production, over a connection whose exception messages name the
 * database host, name and user. It is approved as EVIDENCE TOOLING ONLY: it is
 * not a driver change, and nothing here may become one by accident.
 *
 * THREE RULES THIS FILE FOLLOWS, for the same reason the alignment workflow's
 * tests follow them - a guard that reads its own documentation passes:
 *
 *   1. COMMENTS ARE STRIPPED before anything is asserted. The workflow states
 *      at length that it never mutates production; a test satisfied by that
 *      sentence would sail past a mutation that added `artisan optimize:clear`
 *      directly underneath it.
 *
 *   2. BLOCKS ARE EXTRACTED BY INDENTATION. "There is no push trigger" asked as
 *      a search for `push` across the file is answered by any step name that
 *      happens to contain the word. The `on:` block is isolated first.
 *
 *   3. THE STEPS ARE RUN, NOT READ. The refusal step, the whole evidence step
 *      and its output sweep are extracted and EXECUTED against a stub `ssh`
 *      that returns a report this test chose, so the assertions are about what
 *      the workflow DID with it.
 *
 * Every guard below that can be broken is broken: the paired cases mutate the
 * workflow and assert the guard then fails. A guard with no paired case is one
 * nobody has shown can fail.
 */
final class SessionTableEvidenceWorkflowTest extends TestCase
{
    private const WORKFLOW = '.github/workflows/verify-session-table-evidence.yml';

    private const REFUSAL_STEP = 'Refuse unless this was dispatched from main';

    private const EVIDENCE_STEP = 'Collect the read-only session table evidence';

    private const REACHABILITY_STEP = 'Verify SSH access before anything is read';

    /** The five fields the Product Owner approved, and no sixth. */
    private const APPROVED_FIELDS = [
        'effective_session_driver',
        'configuration_is_cached',
        'session_table_exists',
        'session_row_count',
        'max_last_activity',
    ];

    /** A complete, healthy report. */
    private const BASELINE = [
        'effective_session_driver' => 'file',
        'configuration_is_cached' => true,
        'session_table_exists' => true,
        'session_row_count' => 41,
        'max_last_activity' => 1759012345,
    ];

    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/evsim-'.bin2hex(random_bytes(6));
        mkdir($this->dir.'/bin', 0o777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir.'/{,bin/}*', GLOB_BRACE) ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        @rmdir($this->dir.'/bin');
        @rmdir($this->dir);
    }

    // =================================================================
    // E1-E8. Trigger, permissions, environment, concurrency.
    // =================================================================

    /** E1. workflow_dispatch and nothing else. */
    public function test_the_workflow_is_manual_dispatch_only(): void
    {
        $this->assertManualDispatchOnly($this->workflow());
    }

    /** E1m. Adding any automatic trigger is caught. */
    public function test_an_added_automatic_trigger_is_caught(): void
    {
        foreach ([
            "on:\n  workflow_dispatch:\n  push:\n    branches: [main]",
            "on:\n  workflow_dispatch:\n  schedule:\n    - cron: '0 3 * * *'",
            "on:\n  workflow_dispatch:\n  workflow_call:",
        ] as $mutant) {
            $this->assertGuardFails(
                fn () => $this->assertManualDispatchOnly(
                    str_replace("on:\n  workflow_dispatch:", $mutant, $this->workflow())
                ),
                'An automatic trigger was added and the dispatch-only guard still passed.'
            );
        }
    }

    /** E2. Nothing else in the repository runs it, and it runs nothing else. */
    public function test_no_other_workflow_invokes_it_and_it_invokes_no_production_tooling(): void
    {
        $checked = 0;

        foreach (glob($this->root().'/.github/workflows/*.yml') ?: [] as $path) {
            if (basename($path) === basename(self::WORKFLOW)) {
                continue;
            }

            $checked++;
            $source = $this->withoutComments((string) file_get_contents($path));

            $this->assertStringNotContainsString(
                basename(self::WORKFLOW),
                $source,
                basename($path).' references the evidence workflow. It is dispatched by a person or not at all.'
            );
        }

        $this->assertGreaterThan(5, $checked, 'Almost no workflows were scanned. The glob stopped matching.');

        // And the converse: it is evidence tooling, so it may not reach for the
        // tooling that changes the driver. Running it must never be a step
        // towards a driver change that nobody authorised.
        $workflow = $this->workflow();

        $this->assertStringNotContainsString('ensure-session-driver.sh', $workflow, 'The evidence workflow invokes the driver script.');
        $this->assertStringNotContainsString('align-session-driver.yml', $workflow, 'The evidence workflow invokes the alignment workflow.');
        $this->assertStringNotContainsString('SESSION_DRIVER=', $workflow, 'The evidence workflow writes a driver value.');
        $this->assertStringNotContainsString('workflow_call', $workflow, 'The evidence workflow is callable by another workflow.');
        $this->assertStringNotContainsString('gh workflow run', $workflow, 'The evidence workflow dispatches another workflow.');
    }

    /** E3. The token is read-only, and that is the whole permissions block. */
    public function test_the_workflow_token_is_read_only(): void
    {
        $permissions = $this->block($this->workflow(), 'permissions');

        $this->assertStringContainsString('contents: read', $permissions);

        foreach (['write', 'packages', 'id-token', 'actions'] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $permissions,
                "The permissions block carries [{$forbidden}]. This workflow reads production and reports five numbers."
            );
        }
    }

    /** E4. The established Environment, so the same protection rules apply. */
    public function test_it_reuses_the_established_environment_and_secrets(): void
    {
        $job = $this->block($this->workflow(), 'evidence', 2);

        $this->assertStringContainsString('environment: development', $job, 'The workflow does not use the development Environment the other production workflows use.');

        foreach ([
            'CPANEL_HOST: ${{ secrets.CPANEL_HOST }}',
            'CPANEL_USER: ${{ secrets.CPANEL_USER }}',
            'CPANEL_DEPLOY_PATH: ${{ secrets.CPANEL_DEPLOY_PATH }}',
        ] as $binding) {
            $this->assertStringContainsString($binding, $job, "The job does not bind [{$binding}] from secrets.");
        }

        // No literal host, user or path. A secret read from a secret is a
        // secret; one typed into the file is in every clone of the repository.
        $this->assertDoesNotMatchRegularExpression(
            '/CPANEL_(HOST|USER|DEPLOY_PATH):\s*(?!\$\{\{)\S/',
            $job,
            'A cPanel connection value is hard-coded rather than read from secrets.'
        );
    }

    /** E5. The SAME coordination group as the deployment, and it never cancels. */
    public function test_it_shares_the_deployment_concurrency_group_and_never_cancels(): void
    {
        $this->assertSharesTheDeploymentGroup($this->workflow());

        // Proof it is genuinely the same group, rather than a string that
        // happens to look like it today.
        $deploy = $this->withoutComments($this->read('.github/workflows/deploy.yml'));

        $this->assertSame(
            trim(preg_replace('/\s+/', ' ', $this->block($deploy, 'concurrency')) ?? ''),
            trim(preg_replace('/\s+/', ' ', $this->block($this->workflow(), 'concurrency')) ?? ''),
            'The evidence workflow and the deployment no longer share a concurrency block. A row count '
            .'sampled halfway through an rsync is a number nobody can say what it was a count of.'
        );
    }

    /** E5m. Dropping the shared group, or letting it cancel, is caught. */
    public function test_losing_the_shared_concurrency_group_is_caught(): void
    {
        foreach ([
            "concurrency:\n  group: session-table-evidence\n  cancel-in-progress: false",
            "concurrency:\n  group: cpanel-deploy\n  cancel-in-progress: true",
        ] as $mutant) {
            $this->assertGuardFails(
                fn () => $this->assertSharesTheDeploymentGroup(
                    str_replace(
                        "concurrency:\n  group: cpanel-deploy\n  cancel-in-progress: false",
                        $mutant,
                        $this->workflow()
                    )
                ),
                'The concurrency guard passed for ['.str_replace("\n", ' ', $mutant).'].'
            );
        }
    }

    /** E6. The established ssh-agent and askpass mechanism, unchanged. */
    public function test_it_reuses_the_established_ssh_agent_and_askpass_mechanism(): void
    {
        $step = $this->stepScript($this->workflow(), 'Configure SSH');

        foreach ([
            'ssh-agent -s', 'SSH_ASKPASS', 'SSH_ASKPASS_REQUIRE=force', 'ssh-add', 'ssh-keyscan', 'umask 077',
        ] as $required) {
            $this->assertStringContainsString($required, $step, "Configure SSH no longer uses [{$required}].");
        }

        // The strongest available statement that the pattern is REUSED rather
        // than re-derived: it is byte-identical to the one the existing
        // read-only verification workflow already uses against this host.
        $this->assertSame(
            $this->stepScript($this->withoutComments($this->read('.github/workflows/verify-session-store.yml')), 'Configure SSH'),
            $step,
            'The SSH setup has diverged from the established read-only verification pattern.'
        );
    }

    /** E7. Every remote invocation is non-interactive and pinned to the deploy key. */
    public function test_every_remote_invocation_uses_the_deploy_key_non_interactively(): void
    {
        $workflow = $this->workflow();
        $invocations = preg_match_all('/^\s*(?:out="\$\()?(?:if ! )?ssh -i /m', $workflow);

        $this->assertGreaterThanOrEqual(2, $invocations, 'The remote invocations could not be found, so this guard is vacuous.');

        foreach ([self::REACHABILITY_STEP, self::EVIDENCE_STEP] as $name) {
            $step = $this->stepScript($workflow, $name);

            foreach (['ssh -i ~/.ssh/cpanel_deploy_key', '-o IdentitiesOnly=yes', '-o BatchMode=yes'] as $required) {
                $this->assertStringContainsString($required, $step, "[{$name}] does not use [{$required}].");
            }
        }
    }

    /** E8. The steps, in the order that makes the refusal meaningful. */
    public function test_the_ref_guard_runs_before_any_connection_is_made(): void
    {
        $names = $this->stepNames();

        $this->assertSame(
            [self::REFUSAL_STEP, 'Configure SSH', self::REACHABILITY_STEP, self::EVIDENCE_STEP],
            $names,
            'The steps are not the four approved steps in the approved order. The ref refusal must come '
            .'first: after Configure SSH the deploy key is already on the runner.'
        );
    }

    // =================================================================
    // E9-E11. main only, proven by running the refusal.
    // =================================================================

    /** E9. Dispatched from main: it proceeds. */
    public function test_a_dispatch_from_main_is_allowed(): void
    {
        $run = $this->runRefusal('refs/heads/main');

        $this->assertSame(0, $run['exit'], 'A dispatch from main was refused.');
        $this->assertStringContainsString('Dispatched from main.', $run['output']);
    }

    /** E10. Dispatched from anywhere else: it stops. */
    public function test_a_dispatch_from_any_other_ref_is_refused(): void
    {
        foreach (['refs/heads/feature/x', 'refs/heads/mainline', 'refs/tags/v1', 'refs/pull/9/merge'] as $ref) {
            $run = $this->runRefusal($ref);

            $this->assertSame(1, $run['exit'], "A dispatch from [{$ref}] was not refused.");
            $this->assertStringContainsString('::error::', $run['output']);
            $this->assertStringContainsString('may only be dispatched from main', $run['output']);
        }
    }

    /**
     * E11m. Remove the guard and a branch dispatch runs. The mutation is the
     * plausible one: someone "simplifying" the refusal into a notice.
     */
    public function test_without_the_ref_guard_a_branch_dispatch_would_proceed(): void
    {
        $mutant = $this->workflowWithout('          if [ "${{ github.ref }}" != "refs/heads/main" ]; then');
        $mutant = str_replace(
            [
                '            echo "::error::This workflow may only be dispatched from main. It was dispatched from ${{ github.ref }}."',
                '            exit 1',
                '          fi',
            ],
            ['', '', ''],
            $mutant
        );

        $run = $this->runScript($this->stepScript($mutant, self::REFUSAL_STEP, ['${{ github.ref }}' => 'refs/heads/feature/x']));

        $this->assertSame(
            0,
            $run['exit'],
            'The mutation that removes the ref guard did not make a branch dispatch succeed, so E10 is not '
            .'proving what it claims: it would pass with no guard present.'
        );
    }

    // =================================================================
    // E12-E17. The evidence step, executed.
    // =================================================================

    /** E12. A healthy production report: the five fields, and a pass. */
    public function test_a_complete_report_passes_and_prints_only_the_approved_facts(): void
    {
        $run = $this->runEvidence(self::BASELINE);

        $this->assertSame(0, $run['exit'], "The baseline report failed.\n".$run['output']);

        $this->assertStringContainsString('Effective session driver : file', $run['output']);
        $this->assertStringContainsString('Session table exists     : True', $run['output']);
        $this->assertStringContainsString('Session row count        : 41', $run['output']);
        $this->assertStringContainsString('Most recent activity     : 1759012345', $run['output']);
        $this->assertStringContainsString('not a driver change and it is not a GO', $run['output']);
    }

    /**
     * E13. THE POINT OF THE WHOLE WORKFLOW.
     *
     * The table is read while the driver is `file`. The existing read-only
     * session-store verification gates its table branch on the driver being
     * `database`, which is exactly why P-5 was never observed. If this
     * workflow ever grows the same gate, it stops answering the question it
     * was built for, and it does so silently - the run would go green having
     * reported nothing.
     */
    public function test_the_table_is_inspected_even_though_the_driver_is_file(): void
    {
        $run = $this->runEvidence(self::BASELINE);

        $this->assertSame(0, $run['exit']);
        $this->assertStringContainsString('Session table exists     : True', $run['output']);
        $this->assertStringContainsString('Session row count        : 41', $run['output']);

        // And the remote statement itself carries no driver condition around
        // the table work. `hasTable` must not sit behind a driver comparison.
        $remote = $this->remoteEvidenceStatement();

        $this->assertStringContainsString('hasTable', $remote, 'The remote statement no longer checks for the table.');

        /*
         * THE DRIVER IS READ EXACTLY ONCE, AND ONLY TO REPORT IT.
         *
         * Asserted this way because the first version of this guard searched
         * for `=== "database"` - a form that cannot occur, because the
         * statement is embedded in a double-quoted shell argument and every
         * quote in it is backslash-escaped. It could not fail, and the
         * mutation that puts the driver gate back sailed through it. So the
         * property is now stated positively: one read, before the try block,
         * feeding the reported field - and the word `database` does not appear
         * in the executable statement at all.
         */
        $reads = substr_count($remote, 'config(\\"session.driver\\")');
        $this->assertSame(1, $reads, "The remote statement reads the session driver {$reads} times. It needs exactly one, to report it.");

        $beforeTry = substr($remote, 0, (int) strpos($remote, 'try {'));

        $this->assertStringContainsString(
            'config(\\"session.driver\\")',
            $beforeTry,
            'The only read of the session driver is inside the table-inspection block, which means the '
            .'inspection is conditional on it - the gap this workflow exists to close.'
        );

        $this->assertStringNotContainsString(
            'database',
            $remote,
            'The executable remote statement mentions `database`. The table is inspected unconditionally; '
            .'a comparison against the driver here would report nothing under `file`.'
        );

        // The existing workflow's gate is the mutation. Proof the guard above
        // is the one that catches it.
        $this->assertStringContainsString(
            'isDatabase',
            $this->withoutComments($this->read('.github/workflows/verify-session-store.yml')),
            'The workflow this guard contrasts itself with no longer gates on the driver, so the contrast '
            .'is stale and the guard is asserting nothing about a real difference.'
        );
    }

    /** E14. An empty table is a legitimate baseline: zero rows, no timestamp. */
    public function test_an_empty_table_reports_a_null_timestamp_and_passes(): void
    {
        $run = $this->runEvidence([
            'effective_session_driver' => 'file',
            'configuration_is_cached' => true,
            'session_table_exists' => true,
            'session_row_count' => 0,
            'max_last_activity' => null,
        ]);

        $this->assertSame(0, $run['exit'], "An empty table was rejected.\n".$run['output']);
        $this->assertStringContainsString('Session row count        : 0', $run['output']);
        $this->assertStringContainsString('the table is empty', $run['output']);
    }

    /** E15. No table: false, no aggregates, and the evidence gate FAILS. */
    public function test_an_absent_table_reports_false_and_fails_the_evidence_gate(): void
    {
        $run = $this->runEvidence([
            'effective_session_driver' => 'file',
            'configuration_is_cached' => true,
            'session_table_exists' => false,
        ]);

        $this->assertSame(1, $run['exit'], 'An absent session table produced a green run.');
        $this->assertStringContainsString('Session table exists     : False', $run['output']);
        $this->assertStringContainsString('P-5 is NOT satisfied', $run['output']);
        $this->assertStringNotContainsString('Session row count', $run['output']);
    }

    /**
     * E16. A FAILED CHECK IS NOT A FALSE.
     *
     * "the check blew up" and "the table is not there" are different facts,
     * and only one of them is the one the go/no-go pack needs. The exception
     * never leaves the server: it names the database host, name and user.
     */
    public function test_a_failed_check_is_reported_as_unavailable_and_never_as_absent(): void
    {
        $run = $this->runEvidenceRaw(
            "SESSION_EVIDENCE_UNAVAILABLE\n",
            exit: 1
        );

        $this->assertSame(1, $run['exit'], 'A failed evidence check produced a green run.');
        $this->assertStringContainsString('could not be established', $run['output']);
        $this->assertStringContainsString('do NOT record this as the table being absent', $run['output']);
        $this->assertStringNotContainsString('does not exist in this deployment', $run['output']);

        // Statically too, because the stub cannot execute the remote PHP: the
        // catch block reports unavailability and stops. It must never reach
        // for session_table_exists, which is where "the check failed" would
        // quietly become "the table is absent" - and an absent table is a
        // NO-GO finding somebody would then act on.
        $remote = $this->remoteEvidenceStatement();
        $catch = substr($remote, (int) strpos($remote, 'catch (\\Throwable'));

        $this->assertStringContainsString('SESSION_EVIDENCE_UNAVAILABLE', $catch, 'The catch block no longer reports that the evidence is unavailable.');
        $this->assertStringContainsString('exit(1)', $catch, 'The catch block no longer stops the run.');
        $this->assertStringNotContainsString('session_table_exists', $catch, 'The catch block reports the table as absent when the check merely failed.');
        $this->assertStringNotContainsString('session_row_count', $catch);
    }

    /**
     * E16b. THE FAILURE PATH IS REACHABLE AT ALL.
     *
     * Written because it was not. The first version assigned the remote output
     * under `set -e`, so a non-zero remote exit aborted the step on the
     * assignment and every refusal below it - including the one that
     * distinguishes a failed check from an absent table - was unreachable
     * code. The run went red with an empty log and no statement whatsoever.
     */
    public function test_a_remote_failure_still_produces_a_stated_reason(): void
    {
        foreach ([
            ['', 1, 'did not complete on the server'],
            ["INFO Goodbye.\n", 1, 'did not complete on the server'],
            ["INFO Goodbye.\n", 0, 'returned no report'],
        ] as [$output, $exit, $expected]) {
            $run = $this->runEvidenceRaw($output, exit: $exit);

            $this->assertSame(1, $run['exit']);
            $this->assertStringContainsString('::error::', $run['output'], 'A remote failure produced no statement at all.');
            $this->assertStringContainsString($expected, $run['output']);
        }
    }

    /** E17. The two aggregates must agree with each other. */
    public function test_aggregates_that_contradict_each_other_are_refused(): void
    {
        $contradictions = [
            ['session_row_count' => 0, 'max_last_activity' => 1759012345],
            ['session_row_count' => 9, 'max_last_activity' => null],
        ];

        foreach ($contradictions as $override) {
            $run = $this->runEvidence(array_merge(self::BASELINE, $override));

            $this->assertSame(1, $run['exit'], 'Contradictory aggregates were accepted as a baseline.');
            $this->assertStringContainsString('aggregates disagree', $run['output']);
        }

        /*
         * And a malformed type is refused BY NAME.
         *
         * The first version of these three cases asserted the exit status
         * alone. Removing the row-count type check then left Python comparing
         * a string with an integer, which raises, which exits non-zero - so
         * the case still passed, on a traceback, with the guard gone. An
         * assertion satisfied by any failure is satisfied by the wrong one.
         */
        foreach ([
            [['session_table_exists' => 'true'], 'was not reported as a boolean'],
            [['max_last_activity' => '1759012345'], 'neither an integer timestamp nor null'],
            [['session_row_count' => '41'], 'no row count was reported'],
        ] as [$override, $expected]) {
            $run = $this->runEvidence(array_merge(self::BASELINE, $override));

            $this->assertSame(1, $run['exit'], 'A string was accepted where a typed value was required: '.json_encode($override));
            $this->assertStringContainsString($expected, $run['output'], 'The malformed value was rejected, but not by the check that is supposed to reject it.');
            $this->assertStringNotContainsString('Traceback', $run['output'], 'The sweep crashed rather than refusing. A crash is not a stated refusal.');
        }
    }

    // =================================================================
    // E18-E22. The output allow-list and the leak sweep, executed.
    // =================================================================

    /** E18. The permitted set is EXACTLY the five approved fields. */
    public function test_the_permitted_set_is_exactly_the_five_approved_fields(): void
    {
        $sweep = $this->stepScript($this->workflow(), self::EVIDENCE_STEP);

        $this->assertSame(
            1,
            preg_match('/permitted = \{(.+?)\}/s', $sweep, $match),
            'The output allow-list could not be found, so nothing below is asserting anything about it.'
        );

        preg_match_all("/'([a-z_]+)'/", $match[1], $fields);

        sort($fields[1]);
        $expected = self::APPROVED_FIELDS;
        sort($expected);

        $this->assertSame($expected, $fields[1], 'The output allow-list is not exactly the five approved fields.');
    }

    /**
     * E18b. AND THE REMOTE STATEMENT BUILDS EXACTLY THOSE FIVE KEYS.
     *
     * The allow-list is a runtime gate: a sixth field added to the remote
     * report fails the dispatched run, which is the right outcome but arrives
     * only when somebody is standing in a change window waiting for evidence.
     * A field added to the report is visible right here, in CI, the day it is
     * written.
     */
    public function test_the_remote_report_is_built_from_exactly_the_five_approved_fields(): void
    {
        $remote = $this->remoteEvidenceStatement();

        preg_match_all('/\\\\"([a-z_]+)\\\\"\\s*=>/', $remote, $literal);
        preg_match_all('/\\$report\\[\\\\"([a-z_]+)\\\\"\\]/', $remote, $assigned);

        $keys = array_values(array_unique(array_merge($literal[1], $assigned[1])));
        sort($keys);

        $expected = self::APPROVED_FIELDS;
        sort($expected);

        $this->assertSame(
            $expected,
            $keys,
            'The remote statement builds a report that is not exactly the five approved fields.'
        );
    }

    /** E19. A sixth field fails the run rather than being printed. */
    public function test_any_field_outside_the_allow_list_fails_the_run(): void
    {
        foreach ([
            'payload' => 'a|b|c',
            'user_id' => 7,
            'session_id' => 'HGkq2mR9',
            'ip_address' => '203.0.113.9',
            'user_agent' => 'Mozilla/5.0',
            'session_table_name' => 'sessions',
            'database_host' => 'db.internal',
            'system_health_row_status' => 'ok',
        ] as $field => $value) {
            $run = $this->runEvidence(array_merge(self::BASELINE, [$field => $value]));

            $this->assertSame(1, $run['exit'], "The report carried [{$field}] and the run still passed.");
            $this->assertStringContainsString('unapproved fields', $run['output']);
            $this->assertStringNotContainsString((string) $value, $run['output'], "The value of [{$field}] was printed before it was rejected.");
        }
    }

    /**
     * E20. THE VALUE IS SWEPT, NOT ONLY THE KEY.
     *
     * An approved field carrying a credential or a path is the leak the
     * allow-list alone cannot catch.
     */
    public function test_a_credential_or_a_path_inside_an_approved_field_fails_the_run(): void
    {
        foreach ([
            '/home/semantiq/public_html',
            'mysql://semantiq:hunter2@db.internal/semantiq',
            'DB_PASSWORD=hunter2',
            'base64:AAAABBBBCCCCDDDD',
            'the secret is out',
            '127.0.0.1',
        ] as $value) {
            $run = $this->runEvidence(array_merge(self::BASELINE, ['effective_session_driver' => $value]));

            $this->assertSame(1, $run['exit'], "An approved field carried [{$value}] and the run still passed.");
            $this->assertStringContainsString('The report contains', $run['output']);
        }
    }

    /** E21. The leak markers the Product Owner named are all present. */
    public function test_the_leak_sweep_carries_every_required_marker(): void
    {
        $sweep = $this->stepScript($this->workflow(), self::EVIDENCE_STEP);

        $this->assertSame(1, preg_match('/for marker in \[(.+?)\]:/s', $sweep, $match), 'The marker list could not be found.');

        foreach ([
            'APP_KEY', 'DB_PASSWORD', 'DB_USERNAME', 'DB_HOST', 'password', 'secret', 'mysql://', '/home/',
            'payload', 'session_id', 'user_id', 'ip_address', 'user_agent', 'cookie',
        ] as $marker) {
            $this->assertStringContainsString("'{$marker}'", $match[1], "The leak sweep does not reject [{$marker}].");
        }

        // A marker that is a substring of an approved KEY would make every run
        // fail - which is a broken gate, not a strict one. The sweep runs over
        // the keys as well as the values, so this has to hold.
        preg_match_all("/'([^']+)'/", $match[1], $markers);

        foreach ($markers[1] as $marker) {
            foreach (self::APPROVED_FIELDS as $field) {
                $this->assertStringNotContainsString(
                    strtolower($marker),
                    strtolower($field),
                    "The marker [{$marker}] is a substring of the approved field [{$field}]: every run would fail."
                );
            }
        }
    }

    /** E21m. Removing a marker lets the value it guarded through. */
    public function test_removing_a_leak_marker_lets_that_value_through(): void
    {
        $mutant = $this->workflowWithout("              '/home/', 'localhost', '127.0.0.1',", "              'localhost',");

        $run = $this->runEvidence(
            array_merge(self::BASELINE, ['effective_session_driver' => '/home/semantiq/public_html']),
            $mutant
        );

        $this->assertSame(
            0,
            $run['exit'],
            'Removing the path marker did not let a path through, so E20 is not proving the marker does the work.'
        );
    }

    /** E22. The raw remote output is never echoed before the sweep has passed. */
    public function test_the_raw_remote_output_is_never_printed_before_it_is_swept(): void
    {
        $step = $this->stepScript($this->workflow(), self::EVIDENCE_STEP);
        $sweepStart = strpos($step, 'python3 - "$out"');

        $this->assertNotFalse($sweepStart, 'The sweep could not be located.');

        $this->assertDoesNotMatchRegularExpression(
            '/^\s*(echo|printf|cat)\s+"?\$(\{)?out\b/m',
            substr($step, 0, $sweepStart),
            'The step prints the raw remote output before the allow-list has run, so a value the sweep '
            .'would reject is already in a log by the time it is rejected.'
        );

        // Demonstrated, not only asserted: an unapproved field's value does
        // not appear anywhere in the output of a real run.
        $run = $this->runEvidence(array_merge(self::BASELINE, ['payload' => 'eyJ1c2VyIjoxfQ']));

        $this->assertSame(1, $run['exit']);
        $this->assertStringNotContainsString('eyJ1c2VyIjoxfQ', $run['output']);
    }

    // =================================================================
    // E23-E27. It never writes. Anything. Anywhere.
    // =================================================================

    /** E23. No maintenance, no cache command, no artisan mutation of any kind. */
    public function test_it_runs_no_production_mutation_command(): void
    {
        $this->assertNoMutationCommands($this->workflow());
    }

    /** E23m. Each forbidden command, inserted, is caught. */
    public function test_an_inserted_mutation_command_is_caught(): void
    {
        foreach ([
            'php artisan optimize:clear',
            'php artisan down --retry=60',
            'php artisan up',
            'php artisan config:cache',
            'php artisan cache:clear',
            'php artisan migrate --force',
            'php artisan session:table',
        ] as $command) {
            $this->assertGuardFails(
                fn () => $this->assertNoMutationCommands($this->injectRemoteCommand($command)),
                "[{$command}] was inserted into the evidence step and the guard still passed."
            );
        }
    }

    /** E24. No migration, in any form. */
    public function test_it_never_runs_or_inspects_migrations_destructively(): void
    {
        $workflow = strtolower($this->workflow());

        foreach (['artisan migrate', 'migrate --force', 'migrate:fresh', 'migrate:rollback', 'db:wipe', 'db:seed', 'schema:dump'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $workflow, "The workflow references [{$forbidden}].");
        }
    }

    /** E25. No DML and no DDL, written as SQL or built by the query builder. */
    public function test_it_issues_no_data_or_schema_modification(): void
    {
        $this->assertNoDataModification($this->workflow());
    }

    /** E25m. A single inserted statement of each kind is caught. */
    public function test_an_inserted_data_modification_is_caught(): void
    {
        foreach ([
            'DB::connection($connection)->table($table)->delete()',
            'DB::connection($connection)->table($table)->insert([])',
            'DB::connection($connection)->table($table)->update([])',
            'DB::connection($connection)->table($table)->truncate()',
            'DB::statement("DELETE FROM sessions")',
            'DB::statement("TRUNCATE TABLE sessions")',
            'DB::statement("UPDATE sessions SET payload = 1")',
            'Schema::connection($connection)->drop($table)',
        ] as $statement) {
            $this->assertGuardFails(
                fn () => $this->assertNoDataModification($this->injectRemoteStatement($statement)),
                "[{$statement}] was inserted and the guard still passed."
            );
        }
    }

    /** E26. It never touches .env, reads it back, or writes a file on the server. */
    public function test_it_never_reads_or_writes_the_environment_file(): void
    {
        $this->assertNoEnvironmentFileAccess($this->workflow());
    }

    /** E26m. Each plausible .env access, inserted, is caught. */
    public function test_an_inserted_environment_file_access_is_caught(): void
    {
        foreach ([
            'cat .env',
            'grep SESSION_DRIVER .env',
            'sed -i "s/a/b/" .env',
            'cp .env .env.backup',
            'tee -a .env',
        ] as $command) {
            $this->assertGuardFails(
                fn () => $this->assertNoEnvironmentFileAccess($this->injectRemoteCommand($command)),
                "[{$command}] was inserted and the guard still passed."
            );
        }
    }

    /** E27. It never selects, or names for selection, a sensitive session column. */
    public function test_it_selects_no_sensitive_session_column(): void
    {
        $remote = $this->remoteEvidenceStatement();

        foreach (['payload', 'user_id', 'ip_address', 'user_agent', 'session_id'] as $column) {
            $this->assertStringNotContainsString(
                $column,
                $remote,
                "The remote statement names the session column [{$column}]. The evidence is two aggregates; "
                .'no row content may be read at all.'
            );
        }

        // Positively: the only column named anywhere is the one aggregate
        // timestamp, and the only two reads are count and max.
        $this->assertStringContainsString('->count()', $remote);
        $this->assertStringContainsString('->max(\\"last_activity\\")', $remote);

        foreach (['->get(', '->first(', '->pluck(', '->select(', '->value(', '->chunk(', '->cursor('] as $reader) {
            $this->assertStringNotContainsString($reader, $remote, "The remote statement uses [{$reader}], which returns row content.");
        }
    }

    /** E27b. And it never touches identity, sign-in configuration or the application key. */
    public function test_it_never_touches_identity_configuration_or_the_application_key(): void
    {
        // The leak sweep has to NAME the application key in order to reject a
        // report carrying one, so the marker list is excluded here - otherwise
        // this guard would forbid the very thing that protects the key.
        $workflow = strtolower($this->workflowOutsideTheLeakMarkers());

        foreach (['app_key', 'key:generate', 'microsoft_', 'entra', 'identity_source', 'auth.user_id'] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $workflow,
                "The workflow references [{$forbidden}]. It reads a table and reports five numbers."
            );
        }
    }

    /**
     * E28. Nothing is printed that could be a path, a host or a credential,
     * from the remote side, on any branch - including the branches that exist
     * for things going wrong.
     */
    public function test_no_remote_output_is_relayed_on_any_failure_path(): void
    {
        foreach ([self::REACHABILITY_STEP, self::EVIDENCE_STEP] as $name) {
            $step = $this->stepScript($this->workflow(), $name);

            $this->assertStringContainsString(
                '2>/dev/null',
                $step,
                "[{$name}] relays the remote stderr. A failed `cd` reports the deploy path and an uncaught "
                .'error reports a stack trace full of them.'
            );
        }

        // A run in which everything fails prints no path, host or credential.
        foreach ([
            $this->runEvidenceRaw("SESSION_EVIDENCE_UNAVAILABLE\n", exit: 1),
            $this->runEvidenceRaw('', exit: 255),
            $this->runEvidence(array_merge(self::BASELINE, ['session_table_exists' => false])),
        ] as $run) {
            foreach (['/home/', 'mysql://', 'DB_PASSWORD', 'base64:'] as $marker) {
                $this->assertStringNotContainsString($marker, $run['output'], "A failure path printed [{$marker}].");
            }
        }
    }

    /**
     * E29. THE WORKFLOW SAYS WHAT IT IS, IN THE PART A RUNNER EXECUTES.
     *
     * A reader who only sees the run log, not the file, must not come away
     * believing a driver change happened or was authorised.
     */
    public function test_a_run_never_claims_the_driver_changed_or_that_anything_is_authorised(): void
    {
        $run = $this->runEvidence(self::BASELINE);

        $this->assertStringContainsString('READ-ONLY baseline', $run['output']);
        $this->assertStringContainsString('not a driver change and it is not a GO', $run['output']);

        $workflow = $this->workflow();

        $this->assertStringNotContainsString('ALIGN SESSION DRIVER', $workflow, 'The workflow carries the alignment confirmation phrase.');
        $this->assertStringNotContainsString('inputs.', $workflow, 'The workflow accepts an input. It takes no decisions and needs none.');
    }

    // =================================================================
    // The guards, extracted so a mutation can be run through the same one.
    // =================================================================

    /** The workflow with the leak sweep's own marker list removed. */
    private function workflowOutsideTheLeakMarkers(): string
    {
        $workflow = $this->workflow();

        $this->assertSame(
            1,
            preg_match('/for marker in \[.+?\]:/s', $workflow, $match),
            'The marker list could not be located, so this guard would be examining the wrong text.'
        );

        return str_replace($match[0], '', $workflow);
    }

    private function assertManualDispatchOnly(string $workflow): void
    {
        $on = $this->block($workflow, 'on');

        $this->assertStringContainsString('workflow_dispatch:', $on, 'The workflow is not dispatchable at all.');

        foreach (['push:', 'pull_request:', 'schedule:', 'workflow_call:', 'workflow_run:', 'repository_dispatch:'] as $trigger) {
            $this->assertStringNotContainsString(
                $trigger,
                $on,
                "The trigger block carries [{$trigger}]. This workflow reads production; it runs because a "
                .'person chose to run it.'
            );
        }
    }

    private function assertSharesTheDeploymentGroup(string $workflow): void
    {
        $concurrency = $this->block($workflow, 'concurrency');

        $this->assertStringContainsString('group: cpanel-deploy', $concurrency, 'The workflow does not share the production coordination group.');
        $this->assertStringContainsString('cancel-in-progress: false', $concurrency, 'The workflow may be cancelled by a later run.');
        $this->assertStringNotContainsString('cancel-in-progress: true', $concurrency);
    }

    private function assertNoMutationCommands(string $workflow): void
    {
        foreach ([
            'artisan down', 'artisan up', 'optimize:clear', 'config:cache', 'config:clear',
            'cache:clear', 'route:cache', 'view:cache', 'queue:restart', 'session:table',
            'artisan migrate', 'storage:link',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $workflow,
                "The workflow runs [{$forbidden}]. It is read-only evidence tooling; it changes nothing."
            );
        }
    }

    private function assertNoDataModification(string $workflow): void
    {
        foreach ([
            'INSERT', 'UPDATE ', 'DELETE', 'TRUNCATE', 'DROP ', 'ALTER ', 'CREATE TABLE', 'REPLACE INTO',
            '->insert(', '->update(', '->delete(', '->truncate(', '->upsert(', '->insertGetId(',
            '->increment(', '->decrement(', 'DB::statement', 'DB::unprepared', '->dropIfExists(', '->drop(',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $workflow,
                "The workflow contains [{$forbidden}]. This deployment's session table is production data."
            );
        }
    }

    private function assertNoEnvironmentFileAccess(string $workflow): void
    {
        foreach ([
            'cat .env', 'grep .env', 'head .env', 'tail .env', 'sed -i', 'cp .env', 'mv .env',
            '.env.backup', '> .env', '>> .env', 'tee .env', 'tee -a .env', '.env"', "'.env'", ' .env ',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $workflow,
                "The workflow touches the environment file via [{$forbidden}]."
            );
        }
    }

    /** Assert that a guard, run against a mutant, actually fails. */
    private function assertGuardFails(callable $guard, string $message): void
    {
        try {
            $guard();
        } catch (AssertionFailedError) {
            $this->addToAssertionCount(1);

            return;
        }

        $this->fail($message);
    }

    // =================================================================
    // Running the workflow.
    // =================================================================

    /** @return array{exit: int, output: string} */
    private function runRefusal(string $ref, ?string $workflow = null): array
    {
        return $this->runScript(
            $this->stepScript($workflow ?? $this->workflow(), self::REFUSAL_STEP, ['${{ github.ref }}' => $ref])
        );
    }

    /**
     * Run the evidence step against a stub server that returns this report.
     *
     * @param  array<string, mixed>  $report
     * @return array{exit: int, output: string}
     */
    private function runEvidence(array $report, ?string $workflow = null): array
    {
        return $this->runEvidenceRaw(
            json_encode($report, JSON_PRETTY_PRINT)."\n INFO  Goodbye.\n",
            exit: 0,
            workflow: $workflow
        );
    }

    /** @return array{exit: int, output: string} */
    private function runEvidenceRaw(string $remoteOutput, int $exit, ?string $workflow = null): array
    {
        file_put_contents($this->dir.'/evidence', $remoteOutput);
        file_put_contents($this->dir.'/evidence_exit', (string) $exit);

        return $this->runScript($this->stepScript($workflow ?? $this->workflow(), self::EVIDENCE_STEP));
    }

    /** @return array{exit: int, output: string} */
    private function runScript(string $script): array
    {
        $this->writeStubs();

        foreach (['commands', 'evidence', 'evidence_exit'] as $file) {
            if (! file_exists($this->dir.'/'.$file)) {
                file_put_contents($this->dir.'/'.$file, $file === 'evidence_exit' ? '0' : '');
            }
        }

        $path = $this->dir.'/step.sh';
        file_put_contents($path, $script);

        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];

        $process = proc_open(['bash', $path], $descriptors, $pipes, $this->root(), [
            'PATH' => $this->dir.'/bin:'.getenv('PATH'),
            'HOME' => $this->dir,
            'CPANEL_HOST' => 'host.invalid',
            'CPANEL_PORT' => '22',
            'CPANEL_USER' => 'deployer',
            'CPANEL_DEPLOY_PATH' => '/srv/semantiq',
            'GITHUB_OUTPUT' => $this->dir.'/github_output',
            'GITHUB_STEP_SUMMARY' => $this->dir.'/summary',
            'STUB_LOG' => $this->dir.'/commands',
            'STUB_EVIDENCE' => $this->dir.'/evidence',
            'STUB_EVIDENCE_EXIT' => $this->dir.'/evidence_exit',
        ]);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return ['exit' => proc_close($process), 'output' => $stdout.$stderr];
    }

    /**
     * A stand-in for `ssh`.
     *
     * It answers the reachability probe, returns the report this test chose
     * with the exit status this test chose, and records every remote command
     * so a case can assert on what was actually sent.
     */
    private function writeStubs(): void
    {
        file_put_contents($this->dir.'/bin/ssh', <<<'STUB'
            #!/bin/sh
            for a in "$@"; do last="$a"; done
            printf '%s\n' "$last" >> "$STUB_LOG"

            case "$last" in
              *REACHABLE*) echo REACHABLE; exit 0 ;;
            esac

            cat "$STUB_EVIDENCE"
            exit "$(cat "$STUB_EVIDENCE_EXIT")"
            STUB);

        chmod($this->dir.'/bin/ssh', 0o755);
    }

    // =================================================================
    // Reading the workflow.
    // =================================================================

    /** The remote statement the evidence step sends, isolated from the shell around it. */
    private function remoteEvidenceStatement(): string
    {
        $step = $this->stepScript($this->workflow(), self::EVIDENCE_STEP);

        $start = strpos($step, "tinker --execute='");
        $this->assertNotFalse($start, 'The remote statement could not be located.');

        $end = strpos($step, "' 2>/dev/null\")", $start);
        $this->assertNotFalse($end, 'The remote statement is not terminated where this test expects.');

        $statement = substr($step, $start, $end - $start);

        $this->assertStringContainsString('config(\\"session.table\\"', $statement, 'The remote statement no longer reads the configured session table.');
        $this->assertStringContainsString('config(\\"session.connection\\")', $statement, 'The remote statement no longer reads the configured session connection.');

        return $this->withoutComments(preg_replace('#/\*.*?\*/#s', '', $statement) ?? '');
    }

    /** The workflow with one extra command inside the evidence step's remote call. */
    private function injectRemoteCommand(string $command): string
    {
        return str_replace(
            'cd \"$CPANEL_DEPLOY_PATH\" && php artisan tinker',
            'cd \"$CPANEL_DEPLOY_PATH\" && '.$command.' && php artisan tinker',
            $this->workflow()
        );
    }

    /** The workflow with one extra statement inside the remote PHP. */
    private function injectRemoteStatement(string $statement): string
    {
        $anchor = '                  \$report[\"session_row_count\"] = (int) \$rows;';
        $workflow = $this->workflow();

        $this->assertStringContainsString($anchor, $workflow, 'The injection anchor has moved, so the mutation cases are not mutating anything.');

        return str_replace($anchor, '                  \\'.$statement.';'."\n".$anchor, $workflow);
    }

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    private function read(string $relative): string
    {
        $path = $this->root().'/'.$relative;

        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    /** The workflow with its prose removed, so no assertion can be satisfied by a comment. */
    private function workflow(): string
    {
        return $this->withoutComments($this->read(self::WORKFLOW));
    }

    /** The workflow with one exact line replaced, for the paired mutation cases. */
    private function workflowWithout(string $line, string $replacement = ':'): string
    {
        $workflow = $this->workflow();

        $this->assertSame(
            1,
            substr_count($workflow, $line),
            'The mutation target ['.$line.'] does not appear exactly once, so removing it would be a '
            .'no-op and the paired case would pass for the wrong reason.'
        );

        return str_replace($line, $replacement, $workflow);
    }

    private function withoutComments(string $yaml): string
    {
        return (string) preg_replace('/^\s*#.*$/m', '', $yaml);
    }

    /** Isolate one mapping block by indentation. */
    private function block(string $yaml, string $key, int $depth = 0): string
    {
        $indent = str_repeat(' ', $depth);
        $pattern = '/^'.preg_quote($indent, '/').preg_quote($key, '/').':.*$/m';

        $this->assertMatchesRegularExpression($pattern, $yaml, "There is no [{$key}:] block to examine.");

        preg_match($pattern, $yaml, $match, PREG_OFFSET_CAPTURE);

        $lines = explode("\n", substr($yaml, $match[0][1]));
        $collected = [array_shift($lines)];

        foreach ($lines as $line) {
            if (trim($line) === '') {
                $collected[] = $line;

                continue;
            }

            if (strlen($line) - strlen(ltrim($line)) <= $depth) {
                break;
            }

            $collected[] = $line;
        }

        return implode("\n", $collected);
    }

    /** @return list<string> */
    private function stepNames(): array
    {
        preg_match_all('/^      - name: (.+)$/m', $this->workflow(), $matches);

        $this->assertNotEmpty($matches[1], 'No steps were found, so any ordering assertion is vacuous.');

        return array_map(trim(...), $matches[1]);
    }

    /** One step, from its `- name:` to the next one. */
    private function stepBody(string $workflow, string $name): string
    {
        $start = strpos($workflow, '- name: '.$name);

        $this->assertNotFalse($start, "The workflow has no step named [{$name}].");

        $next = strpos($workflow, '      - name: ', $start + 1);

        return $next === false ? substr($workflow, $start) : substr($workflow, $start, $next - $start);
    }

    /**
     * A step's `run:` block, dedented and ready to execute.
     *
     * @param  array<string, string>  $substitutions  what the runner would have interpolated
     */
    private function stepScript(string $workflow, string $name, array $substitutions = []): string
    {
        $body = $this->stepBody($workflow, $name);

        $marker = "        run: |\n";
        $start = strpos($body, $marker);

        $this->assertNotFalse($start, "[{$name}] has no `run:` block to execute.");

        $lines = explode("\n", substr($body, $start + strlen($marker)));
        $script = [];

        foreach ($lines as $line) {
            if (trim($line) === '') {
                $script[] = '';

                continue;
            }

            if (strlen($line) - strlen(ltrim($line)) < 10) {
                break;
            }

            $script[] = substr($line, 10);
        }

        $source = implode("\n", $script)."\n";

        foreach ($substitutions as $expression => $value) {
            $source = str_replace($expression, $value, $source);
        }

        $this->assertStringNotContainsString(
            '${{',
            $source,
            "[{$name}] still carries an uninterpolated runner expression, so running it would not be "
            .'running what the runner runs.'
        );

        return $source;
    }
}
