<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * THE SAFETY CONTRACT OF align-session-driver.yml.
 *
 * Dispatching that workflow changes production and signs every user out. CI
 * never executes it, nothing type-checks it, and by the time anybody reads it
 * carefully they are usually already in a maintenance window - so the
 * properties that make it safe to have in the repository are asserted here.
 *
 * THREE RULES THIS FILE FOLLOWS, because the alternative is a guard that reads
 * its own documentation and passes:
 *
 *   1. COMMENTS ARE STRIPPED before anything is asserted. The workflow explains
 *      at length why it is manual-only; a test that matched the explanation
 *      would sail through a mutation that added a push trigger underneath it.
 *
 *   2. BLOCKS ARE EXTRACTED BY INDENTATION, not matched anywhere in the file.
 *      "There is no push trigger" asserted as a search for the word `push`
 *      across the whole file is satisfied by the word appearing in a step name.
 *      The `on:` block is isolated first and the question is asked of it.
 *
 *   3. THE ORCHESTRATION IS RUN, NOT READ. Round 1 of review found what only
 *      reading it costs: the recovery step ANNOUNCED that production had been
 *      "LEFT IN MAINTENANCE" and ran nothing that would make that true, and the
 *      test asserting the message passed. Round 2 then found two more defects
 *      that a reading missed - recovery clearing caches after a refusal that
 *      had written nothing, and a rollback the workflow advertised but its own
 *      guard would refuse.
 *
 *      So the steps that carry decisions are EXTRACTED AND EXECUTED here,
 *      against a stub `ssh` that records every remote command and holds the
 *      driver and maintenance state as real files. Whole SEQUENCES of steps are
 *      run - gates evaluated, outputs carried between steps, exactly as the
 *      runner would - and the assertions are about what the workflow DID.
 *
 * What none of this can prove is that the workflow works against the real host;
 * only dispatching it could, and that is precisely what must not happen. The
 * script it calls is proven behaviourally in SessionDriverDeploymentTest.
 */
final class SessionDriverAlignmentWorkflowTest extends TestCase
{
    private const WORKFLOW = '.github/workflows/align-session-driver.yml';

    private const SCRIPT = 'deployment/ensure-session-driver.sh';

    private const PLAN_STEP = 'Establish the starting session driver and decide whether anything must change';

    private const MAINTENANCE_STEP = 'Take the maintenance window';

    private const MUTATION_STEP = 'Record that the driver mutation is about to be attempted';

    private const RECOVERY_STEP = 'Establish the exact state after a failure or cancellation';

    private const TAKEOVER_PHRASE = 'TAKE OVER MAINTENANCE FOR SESSION ROLLBACK';

    /** The `artisan down` the recovery step must issue when the driver changed. */
    private const RECOVERY_DOWN = 'remote "php artisan down --retry=60" >/dev/null 2>&1 || true';

    /** The guard that keeps recovery read-only when nothing was mutated. */
    private const MUTATION_GUARD = 'if [ "$mutation_attempted" = "true" ]; then';

    /** Every step the runner could execute, in order, minus the two with no logic. */
    private const SEQUENCE = [
        'Refuse unless this was dispatched from main',
        'Refuse unless the confirmation phrase is exact',
        'Refuse an unrecognised target driver',
        'Verify SSH access before anything is changed',
        self::PLAN_STEP,
        self::MAINTENANCE_STEP,
        self::MUTATION_STEP,
        'Align SESSION_DRIVER',
        'Clear compiled caches',
        'Verify the effective driver is the requested target',
        'Verify application health over SSH',
        'Close the maintenance window',
        'Verify the site over HTTPS',
        'Report what was done',
        'Report that no change was required',
        self::RECOVERY_STEP,
    ];

    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/wfsim-'.bin2hex(random_bytes(6));
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
    // Trigger, inputs, credentials - the contract that was approved.
    // =================================================================

    /** W1. workflow_dispatch and NOTHING else. */
    public function test_the_workflow_is_manual_dispatch_only(): void
    {
        $on = $this->block($this->workflow(), 'on');

        $this->assertStringContainsString('workflow_dispatch:', $on, 'The workflow is not dispatchable at all.');

        foreach (['push:', 'pull_request:', 'schedule:', 'workflow_call:', 'workflow_run:', 'repository_dispatch:'] as $trigger) {
            $this->assertStringNotContainsString(
                $trigger,
                $on,
                "The trigger block carries [{$trigger}]. This workflow mutates production and signs every "
                .'user out; it may only ever run because a person chose to run it.'
            );
        }
    }

    /** W2. And nothing else runs it either. */
    public function test_no_other_workflow_invokes_the_driver_script_or_this_workflow(): void
    {
        $checked = 0;

        foreach (glob($this->root().'/.github/workflows/*.yml') ?: [] as $path) {
            if (basename($path) === basename(self::WORKFLOW)) {
                continue;
            }

            $checked++;
            $source = $this->withoutComments((string) file_get_contents($path));

            $this->assertStringNotContainsString('ensure-session-driver.sh', $source, basename($path).' invokes the session driver script.');
            $this->assertStringNotContainsString(basename(self::WORKFLOW), $source, basename($path).' references the alignment workflow.');
        }

        $this->assertGreaterThan(5, $checked, 'Almost no workflows were scanned. The glob stopped matching.');
    }

    /** W3. deploy.yml in particular - it fires on every push to main. */
    public function test_the_deployment_workflow_does_not_touch_the_session_driver(): void
    {
        $deploy = $this->withoutComments($this->read('.github/workflows/deploy.yml'));

        $this->assertStringNotContainsString('ensure-session-driver', $deploy);
        $this->assertStringNotContainsString('SESSION_DRIVER', $deploy);
        $this->assertStringContainsString('ensure-session-lifetime.sh', $deploy);
    }

    /** W4. The target is a CHOICE of exactly two drivers. */
    public function test_the_target_input_is_a_choice_of_exactly_file_and_database(): void
    {
        $target = $this->block($this->block($this->workflow(), 'on'), 'target', depth: 6);

        $this->assertStringContainsString('type: choice', $target, 'The target driver is not a restricted choice.');

        preg_match_all('/^\s*- (\S+)\s*$/m', $target, $matches);
        sort($matches[1]);

        $this->assertSame(['database', 'file'], $matches[1], 'The selectable drivers must be exactly database and file.');
    }

    /** W5. A TYPED confirmation, checked against an exact phrase. */
    public function test_a_typed_confirmation_phrase_is_required_and_enforced(): void
    {
        $workflow = $this->workflow();

        $this->assertStringContainsString('confirmation:', $this->block($workflow, 'on'));

        $this->assertMatchesRegularExpression(
            '/if \[ "\$\{CONFIRMATION:-\}" != "ALIGN SESSION DRIVER" \]; then\s*\n\s*echo "::error::[^\n]*"\s*\n\s*exit 1/',
            $workflow,
            'The confirmation input is collected but a wrong phrase does not stop the run.'
        );
    }

    /**
     * W6. THREE INPUTS, AND NOWHERE TO PUT A KEY OR A VALUE.
     *
     * The third is the rollback takeover confirmation added in round 3. It is
     * still a fixed phrase, not a setting: there is no key field, no value
     * field and no free-text configuration name anywhere in the block.
     */
    public function test_the_workflow_offers_no_arbitrary_key_or_value_input(): void
    {
        $inputs = $this->block($this->workflow(), 'on');

        preg_match_all('/^      ([a-z_]+):\s*$/m', $inputs, $matches);
        sort($matches[1]);

        $this->assertSame(
            ['confirmation', 'rollback_takeover', 'target'],
            $matches[1],
            'The workflow accepts inputs beyond the driver and the two confirmations: '.implode(', ', $matches[1])
        );

        foreach (['key:', 'value:', 'env_key:', 'setting:'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $inputs);
        }
    }

    /** W7. A HARD REFUSAL UNLESS THE REF IS main. */
    public function test_it_refuses_unless_it_was_dispatched_from_main(): void
    {
        $this->assertMatchesRegularExpression(
            '/if \[ "\$\{\{ github\.ref \}\}" != "refs\/heads\/main" \]; then\s*\n\s*echo "::error::[^\n]*"\s*\n\s*exit 1/',
            $this->workflow(),
            'A dispatch from a branch other than main does not stop the run.'
        );
    }

    /** W8. And the guards run before anything reaches the server. */
    public function test_the_ref_and_confirmation_guards_run_before_any_ssh_step(): void
    {
        $workflow = $this->workflow();

        $ref = strpos($workflow, 'refs/heads/main');
        $confirmation = strpos($workflow, 'ALIGN SESSION DRIVER"');
        $firstSsh = strpos($workflow, 'Configure SSH');

        $this->assertNotFalse($ref);
        $this->assertNotFalse($confirmation);
        $this->assertNotFalse($firstSsh);

        $this->assertLessThan($firstSsh, $ref, 'SSH is configured before the ref is checked.');
        $this->assertLessThan($firstSsh, $confirmation, 'SSH is configured before the confirmation is checked.');
    }

    /** W9. THE SAME CONCURRENCY GROUP AS THE DEPLOYMENT, AND NEVER CANCELLED. */
    public function test_it_shares_the_deployment_concurrency_group_and_never_cancels(): void
    {
        $concurrency = $this->block($this->workflow(), 'concurrency');

        $this->assertMatchesRegularExpression('/^\s*group: cpanel-deploy\s*$/m', $concurrency);
        $this->assertMatchesRegularExpression('/^\s*cancel-in-progress: false\s*$/m', $concurrency);

        $deployConcurrency = $this->block($this->withoutComments($this->read('.github/workflows/deploy.yml')), 'concurrency');

        $this->assertMatchesRegularExpression(
            '/^\s*group: cpanel-deploy\s*$/m',
            $deployConcurrency,
            'The deployment no longer uses cpanel-deploy, so sharing the name guarantees nothing.'
        );
        $this->assertMatchesRegularExpression('/^\s*cancel-in-progress: false\s*$/m', $deployConcurrency);
    }

    /** W10. Read-only token. */
    public function test_the_workflow_token_is_read_only(): void
    {
        $permissions = $this->block($this->workflow(), 'permissions');

        $this->assertMatchesRegularExpression('/^\s*contents: read\s*$/m', $permissions);

        foreach (['write', 'packages', 'id-token', 'actions'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $permissions);
        }
    }

    /** W11. THE ESTABLISHED ENVIRONMENT AND SECRETS, NOT A SECOND SET. */
    public function test_it_reuses_the_established_environment_and_secrets(): void
    {
        $workflow = $this->workflow();

        $this->assertMatchesRegularExpression('/^\s*environment: development\s*$/m', $workflow);

        foreach ([
            'CPANEL_HOST', 'CPANEL_PORT', 'CPANEL_USER', 'CPANEL_DEPLOY_PATH',
            'CPANEL_SSH_PRIVATE_KEY', 'CPANEL_SSH_KEY_PASSPHRASE',
        ] as $secret) {
            $this->assertStringContainsString('secrets.'.$secret, $workflow, "The workflow does not read the established [{$secret}] secret.");
        }

        preg_match_all('/secrets\.([A-Z_]+)/', $workflow, $matches);
        $deploy = $this->read('.github/workflows/deploy.yml');

        foreach (array_unique($matches[1]) as $secret) {
            $this->assertStringContainsString(
                'secrets.'.$secret,
                $deploy,
                "[{$secret}] is a secret the deployment does not use. This workflow must not introduce a second credentials model."
            );
        }
    }

    /** W12. The passphrase-protected key needs ssh-agent and askpass. */
    public function test_it_uses_the_established_ssh_agent_and_askpass_mechanism(): void
    {
        $workflow = $this->workflow();

        foreach (['ssh-agent -s', 'SSH_ASKPASS', 'SSH_ASKPASS_REQUIRE=force', 'ssh-add', 'ssh-keyscan'] as $fragment) {
            $this->assertStringContainsString($fragment, $workflow, "The SSH setup omits [{$fragment}].");
        }
    }

    /** W13. THE SCRIPT IS CALLED WITH A PATH AND A DRIVER. NOTHING ELSE. */
    public function test_the_script_is_piped_over_stdin_with_only_a_path_and_a_target(): void
    {
        $workflow = $this->workflow();

        $this->assertMatchesRegularExpression(
            '/"sh -s -- \\\\"\$CPANEL_DEPLOY_PATH\\\\" \\\\"\$TARGET_DRIVER\\\\""\s*\\\\\s*\n\s*< deployment\/ensure-session-driver\.sh/',
            $workflow,
            'The script is not invoked with exactly the deployment path and the target driver, piped over stdin.'
        );

        foreach (['scp ', 'rsync ', 'cat > ', 'install -m'] as $copies) {
            $this->assertStringNotContainsString($copies.'deployment/', $workflow, 'The script must not be deployed to the host.');
        }

        $this->assertFileExists($this->root().'/'.self::SCRIPT, 'The workflow pipes a script that does not exist.');
    }

    /** W14. IT NEVER RUNS MIGRATIONS. */
    public function test_it_never_runs_migrations(): void
    {
        $workflow = $this->workflow();

        foreach (['artisan migrate', 'migrate --force', 'migrate:fresh', 'db:wipe', 'db:seed'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $workflow);
        }
    }

    /**
     * W15. IT NEVER TOUCHES IDENTITY, SIGN-IN CONFIGURATION OR THE APPLICATION KEY.
     *
     * Case-insensitively. The first version of this guard compared case
     * sensitively, so the word it forbade sat in the workflow's own header in
     * different case and the test passed - a guard that only catches a mutation
     * typed in one particular case is most of the way to no guard.
     */
    public function test_it_never_modifies_identity_configuration_or_the_app_key(): void
    {
        $workflow = strtolower($this->workflow());

        foreach ([
            'app_key', 'key:generate', 'ensure-app-key',
            'microsoft_', 'identity_source', 'identity:', 'entra',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $workflow,
                "The workflow references [{$forbidden}]. It changes one session key and nothing else."
            );
        }
    }

    /** W16. It never reads .env back, and never prints session contents. */
    public function test_it_never_displays_env_or_session_contents(): void
    {
        $workflow = $this->workflow();

        foreach ([
            'cat .env', 'cat "$CPANEL_DEPLOY_PATH/.env"', 'grep .env', 'head .env', 'tail .env',
            'sessions', 'payload', 'ip_address', 'user_agent', 'SELECT',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $workflow, "The workflow references [{$forbidden}].");
        }
    }

    /** W17. THE OPERATIONAL SEQUENCE, IN ORDER. */
    public function test_the_steps_run_in_the_required_order(): void
    {
        $names = $this->stepNames();

        $expected = [
            'Refuse unless this was dispatched from main',
            'Refuse unless the confirmation phrase is exact',
            'Refuse an unrecognised target driver',
            'Configure SSH',
            'Verify SSH access before anything is changed',
            self::PLAN_STEP,
            self::MAINTENANCE_STEP,
            self::MUTATION_STEP,
            'Align SESSION_DRIVER',
            'Clear compiled caches',
            'Verify the effective driver is the requested target',
            'Verify application health over SSH',
            'Close the maintenance window',
        ];

        $positions = [];

        foreach ($expected as $name) {
            $position = array_search($name, $names, true);
            $this->assertNotFalse($position, "The workflow has no step named [{$name}].");
            $positions[] = $position;
        }

        $sorted = $positions;
        sort($sorted);

        $this->assertSame($sorted, $positions, 'The workflow steps are not in the required order.');
    }

    /** W18. THE REPORTED DRIVER IS READ FROM THE APPLICATION, NOT ASSUMED. */
    public function test_it_verifies_the_effective_driver_rather_than_trusting_the_script(): void
    {
        $workflow = $this->workflow();

        $this->assertStringContainsString('config(\\"session.driver\\")', $workflow, 'Nothing reads the effective driver back out of the application.');

        $this->assertMatchesRegularExpression(
            '/if \[ "\$effective" != "\$TARGET_DRIVER" \]; then\s*\n\s*echo "::error::[^\n]*"\s*\n\s*exit 1/',
            $workflow,
            'The effective driver is read but a mismatch does not fail the run.'
        );
    }

    // =================================================================
    // ROUND 1 BLOCKER - failing closed means PUTTING THE SITE DOWN.
    // =================================================================

    /**
     * W19. A FAILURE AFTER THE SITE CAME BACK UP PUTS IT BACK INTO MAINTENANCE.
     *
     * The normal close step runs `artisan up` BEFORE the HTTPS verification and
     * the reporting, so a failure in either left a changed, unverified
     * deployment SERVING USERS while the log said the opposite.
     */
    public function test_a_failure_after_the_site_came_back_up_puts_it_back_into_maintenance(): void
    {
        $result = $this->runRecovery(starting: 'file', driver: 'database', state: 'LIVE', origin: 'opened', mutated: 'true');

        $this->assertNotSame(0, $result['exit'], 'A failed run must fail the job.');

        $this->assertContains(
            'php artisan down --retry=60',
            $result['commands'],
            'The driver was changed and the run failed with the site LIVE, and recovery sent no maintenance command.'
        );

        $this->assertSame('MAINTENANCE', $result['state'], 'Production did not end up in maintenance.');
        $this->assertStringContainsString('PUT BACK INTO MAINTENANCE', $result['output']);
        $this->assertNotContains('php artisan up', $result['commands']);
    }

    /** W20. AND THE COMMAND IS WHAT DOES IT, not the sentence. */
    public function test_without_the_recovery_down_the_same_failure_leaves_production_serving(): void
    {
        $result = $this->runRecovery(
            starting: 'file', driver: 'database', state: 'LIVE', origin: 'opened', mutated: 'true',
            workflow: $this->workflowWithout(self::RECOVERY_DOWN),
        );

        $this->assertSame('LIVE', $result['state'], 'Production ended in maintenance even with the command removed, so W19 proves nothing.');
        $this->assertNotContains('php artisan down --retry=60', $result['commands']);
    }

    /** W21. THE CLAIM CANNOT OUTLIVE THE COMMAND. */
    public function test_no_maintenance_claim_appears_before_the_maintenance_command(): void
    {
        $recovery = $this->stepBody($this->workflow(), self::RECOVERY_STEP);

        // The command lives in a helper, so its DEFINITION sits above
        // everything. Anchoring on that would make this guard vacuous - which
        // is exactly what happened when the helper was introduced, and a
        // mutation that moved a maintenance claim above the first call sailed
        // through. Anchor on the first CALL.
        $this->assertStringContainsString(
            'php artisan down --retry=60',
            $recovery,
            'The recovery step never issues a maintenance command.'
        );

        $firstCall = strpos($recovery, 'if secure_maintenance; then');

        $this->assertNotFalse($firstCall, 'The recovery step never calls the helper that secures maintenance.');

        foreach ([
            'LEFT IN MAINTENANCE', 'PUT BACK INTO MAINTENANCE', 'left in maintenance', 'LEFT ACTIVE',
        ] as $claim) {
            $position = strpos($recovery, $claim);

            if ($position === false) {
                continue;
            }

            $this->assertLessThan(
                $position,
                $firstCall,
                "The recovery step claims [{$claim}] before it has secured or read the maintenance state."
            );
        }
    }

    /**
     * B5-9b. THE MAINTENANCE STEP GUARDS THE TAKEOVER ON ITS OWN.
     *
     * The planning step decides; this step acts. Both check, independently,
     * because ending somebody else's outage is the one action worth two
     * guards - and because a mutation that removed only this one would
     * otherwise be invisible behind the planning step's refusal.
     */
    public function test_the_maintenance_step_independently_guards_the_takeover(): void
    {
        $step = $this->stepBody($this->workflow(), self::MAINTENANCE_STEP);

        $this->assertMatchesRegularExpression(
            '/if \[ "\$TARGET_DRIVER" != "file" \]; then\s*\n\s*echo "::error::[^\n]*"\s*\n\s*exit 1/',
            $step,
            'The maintenance step does not independently refuse a takeover for a forward target.'
        );

        $this->assertMatchesRegularExpression(
            '/if \[ "\$\{ROLLBACK_TAKEOVER:-\}" != "\$TAKEOVER_PHRASE" \]; then\s*\n\s*echo "::error::[^\n]*"\s*\n\s*exit 1/',
            $step,
            'The maintenance step does not independently require the exact takeover confirmation.'
        );

        // And the planning step carries its own, so neither is the only one.
        $plan = $this->stepBody($this->workflow(), self::PLAN_STEP);

        $this->assertStringContainsString('takeover_supplied', $plan, 'The planning step does not evaluate the takeover confirmation at all.');
        $this->assertSame(2, substr_count($plan, 'if [ "$takeover_supplied" != "true" ]; then'), 'The planning step must require the confirmation on both maintenance paths.');
    }

    /** W22. IF MAINTENANCE CANNOT BE GUARANTEED, IT IS NOT CLAIMED. */
    public function test_a_failed_recovery_down_escalates_instead_of_claiming_maintenance(): void
    {
        $result = $this->runRecovery(
            starting: 'file', driver: 'database', state: 'LIVE', origin: 'opened', mutated: 'true',
            downFails: true,
        );

        $this->assertNotSame(0, $result['exit']);
        $this->assertSame('LIVE', $result['state']);
        $this->assertStringNotContainsString('PUT BACK INTO MAINTENANCE', $result['output']);
        $this->assertStringContainsString('CRITICAL', $result['output']);
        $this->assertStringContainsString('MAINTENANCE MODE COULD NOT BE GUARANTEED', $result['output']);
    }

    /** W23. An unreadable driver is an unknown state, and is escalated as one. */
    public function test_an_unreadable_driver_after_a_failure_is_escalated_as_unknown(): void
    {
        $result = $this->runRecovery(starting: 'file', driver: '', state: 'LIVE', origin: 'opened', mutated: 'true');

        $this->assertNotSame(0, $result['exit']);
        $this->assertStringContainsString('UNKNOWN', $result['output']);
        $this->assertStringContainsString('CRITICAL', $result['output']);
        $this->assertNotContains('php artisan up', $result['commands']);
    }

    /** W24. A failure before the plan step means nothing was touched, and it says so. */
    public function test_a_failure_before_the_driver_was_established_touches_nothing(): void
    {
        $result = $this->runRecovery(starting: '', driver: 'file', state: 'LIVE', origin: '', mutated: '');

        $this->assertNotSame(0, $result['exit']);
        $this->assertSame([], $result['commands'], 'Something was sent to the server after an early failure.');
        $this->assertStringContainsString('nothing on the server was written', $result['output']);
    }

    // =================================================================
    // BLOCKER 4 - recovery must not write after a refusal that did not.
    // =================================================================

    /**
     * B4-1. A REFUSAL THAT WROTE NOTHING LEAVES RECOVERY READ-ONLY.
     *
     * Round 2's recovery ran `optimize:clear` before it knew whether this run
     * had mutated anything. A run that refused because production was already
     * in somebody else's maintenance window would therefore go on to clear
     * compiled caches INSIDE that window - and the workflow's own claim to
     * "refuse before any mutation" was false.
     */
    public function test_recovery_clears_no_caches_when_no_mutation_was_attempted(): void
    {
        $result = $this->runRecovery(starting: 'file', driver: 'file', state: 'MAINTENANCE', origin: '', mutated: '');

        $this->assertNotSame(0, $result['exit']);

        $this->assertNotContains(
            'php artisan optimize:clear',
            $result['commands'],
            'Recovery cleared compiled caches after a run that had written nothing to the server.'
        );

        $this->assertStringContainsString('read-only', $result['output']);
        $this->assertStringContainsString('written nothing to the server', $result['output']);
    }

    /** B4-2. And the guard is what does it: removed, the cache clear happens. */
    public function test_without_the_mutation_guard_recovery_writes_after_a_refusal(): void
    {
        $result = $this->runRecovery(
            starting: 'file', driver: 'file', state: 'MAINTENANCE', origin: '', mutated: '',
            workflow: $this->workflowWithout(self::MUTATION_GUARD, replacement: 'if true; then'),
        );

        $this->assertContains(
            'php artisan optimize:clear',
            $result['commands'],
            'No cache clear happened even without the guard, so B4-1 proves nothing about the guard.'
        );
    }

    /** B4-3. Recovery DOES clear caches when a mutation really was attempted - it has to. */
    public function test_recovery_clears_caches_when_a_mutation_was_attempted(): void
    {
        $result = $this->runRecovery(starting: 'file', driver: 'database', state: 'LIVE', origin: 'opened', mutated: 'true');

        $this->assertContains(
            'php artisan optimize:clear',
            $result['commands'],
            'After a real mutation the effective driver cannot be read truthfully without clearing the cache.'
        );
    }

    /**
     * B4-4. THE WHOLE REFUSAL SEQUENCE, RUN END TO END, WRITES NOTHING.
     *
     * Production is in somebody else's maintenance window and the target is
     * `database`. The maintenance step must refuse, and NOTHING in the entire
     * run - including recovery - may send a write.
     */
    public function test_a_pre_existing_maintenance_refusal_sends_no_write_at_all(): void
    {
        $run = $this->runSequence(
            target: 'database',
            driver: 'file',
            state: 'MAINTENANCE',
        );

        $this->assertContains(self::PLAN_STEP, $run['failed'], 'The planning step did not refuse.');

        $this->assertNoWrites($run);

        $this->assertSame('MAINTENANCE', $run['state'], "Somebody else's maintenance window was disturbed.");
        $this->assertSame('file', $run['driver'], 'The driver moved despite the refusal.');
        $this->assertStringContainsString('will never assume control of an existing window', $run['output']);
    }

    /** B4-5. An unreadable maintenance state refuses, and writes nothing either. */
    public function test_an_unreadable_maintenance_state_refuses_and_writes_nothing(): void
    {
        $run = $this->runSequence(target: 'database', driver: 'file', state: 'UNREADABLE-GARBAGE');

        $this->assertContains(self::PLAN_STEP, $run['failed']);
        $this->assertNoWrites($run);
        $this->assertStringContainsString('Could not establish whether production is in maintenance', $run['output']);
    }

    /** B4-6. A failure before the maintenance step writes nothing either. */
    public function test_a_failure_before_the_maintenance_step_sends_no_write(): void
    {
        // An unrecognised production driver stops the plan step.
        $run = $this->runSequence(target: 'database', driver: 'redis', state: 'LIVE');

        $this->assertContains(self::PLAN_STEP, $run['failed']);
        $this->assertNoWrites($run);
        $this->assertNotContains(self::MAINTENANCE_STEP, $run['ran']);
    }

    /** B4-7. The mutation flag is recorded immediately before the script, and nowhere else. */
    public function test_the_mutation_flag_is_recorded_only_immediately_before_the_script(): void
    {
        $workflow = $this->workflow();

        $this->assertSame(
            1,
            substr_count($workflow, 'attempted=true'),
            'The mutation-attempted flag is written in more than one place, so it no longer means "the '
            .'driver script is about to run".'
        );

        $names = $this->stepNames();

        $this->assertSame(
            array_search('Align SESSION_DRIVER', $names, true) - 1,
            array_search(self::MUTATION_STEP, $names, true),
            'The mutation-attempted flag is not recorded in the step immediately before the script runs.'
        );
    }

    // =================================================================
    // BLOCKER 5 - the advertised rollback must actually be runnable.
    // =================================================================

    /**
     * B5-1. THE RECOVERY DEADLOCK, AND ITS WAY OUT - RUN END TO END.
     *
     * Round 2 advertised "dispatch this workflow with target [file]" as the
     * rollback, deliberately left production in maintenance on a failed forward
     * change, and then refused any run that found production in maintenance.
     * The documented way out could not run.
     *
     * This case runs BOTH dispatches against one stubbed server:
     *
     *   Run 1  file -> database, HTTPS verification fails after the site is
     *          back up. Recovery must leave production DOWN on `database`.
     *
     *   Run 2  the rollback: database -> file, production already in
     *          maintenance, takeover confirmation supplied. It must be
     *          accepted, run the same script, verify `file`, pass health, and
     *          close the window it assumed control of.
     *
     * Final state must be `file` and LIVE - a deployment an operator could
     * actually recover with this tooling.
     */
    public function test_a_failed_forward_run_can_be_rolled_back_by_the_same_workflow(): void
    {
        $this->givenServer(driver: 'file', state: 'LIVE');

        // ---- Run 1: forward, failing after the site came back up. ----------
        $forward = $this->runSequence(target: 'database', httpCode: '500', fresh: false);

        $this->assertContains('Verify the site over HTTPS', $forward['failed'], 'Run 1 did not fail where this case needs it to.');
        $this->assertContains('Close the maintenance window', $forward['ran'], 'Run 1 never brought the site back up, so the deadlock is not reproduced.');

        $this->assertSame('database', $forward['driver'], 'Run 1 did not change the driver.');
        $this->assertSame('MAINTENANCE', $forward['state'], 'Run 1 left a changed, unverified deployment serving users.');
        $this->assertStringContainsString('rollback takeover confirmation', $forward['output'], 'Run 1 does not tell the operator how to get out.');

        // ---- Run 2: the rollback the failure message named. ----------------
        $rollback = $this->runSequence(target: 'file', takeover: self::TAKEOVER_PHRASE, fresh: false);

        $this->assertSame([], $rollback['failed'], 'The advertised rollback was refused: '.$rollback['output']);

        $this->assertContains(self::MAINTENANCE_STEP, $rollback['ran']);
        $this->assertSame('takeover', $rollback['outputs']['maintenance']['origin'] ?? null, 'The rollback did not assume control of the window.');

        $this->assertContains('Align SESSION_DRIVER', $rollback['ran']);
        $this->assertContains('Verify the effective driver is the requested target', $rollback['ran']);
        $this->assertContains('Verify application health over SSH', $rollback['ran']);
        $this->assertContains('Close the maintenance window', $rollback['ran']);

        $this->assertSame('file', $rollback['driver'], 'The rollback did not restore the file driver.');
        $this->assertSame('LIVE', $rollback['state'], 'The rollback left production down.');
    }

    /** B5-2. A forward alignment can NEVER take over a window, whatever is typed. */
    public function test_a_forward_alignment_can_never_take_over_an_existing_window(): void
    {
        // Without the phrase.
        $plain = $this->runSequence(target: 'database', driver: 'file', state: 'MAINTENANCE');
        $this->assertContains(self::PLAN_STEP, $plain['failed']);
        $this->assertNoWrites($plain);

        // And with it - it is refused before anything reaches the server.
        $withPhrase = $this->runSequence(target: 'database', driver: 'file', state: 'MAINTENANCE', takeover: self::TAKEOVER_PHRASE);
        $this->assertContains('Refuse an unrecognised target driver', $withPhrase['failed'], 'The takeover phrase was accepted alongside a forward target.');
        $this->assertNoWrites($withPhrase);
        $this->assertStringContainsString('can never authorise a forward alignment', $withPhrase['output']);
    }

    /** B5-3. A rollback without the takeover confirmation cannot take over either. */
    public function test_a_rollback_without_the_takeover_confirmation_is_refused(): void
    {
        $run = $this->runSequence(target: 'file', driver: 'database', state: 'MAINTENANCE');

        $this->assertContains(self::PLAN_STEP, $run['failed']);
        $this->assertNoWrites($run);
        $this->assertSame('MAINTENANCE', $run['state']);
        $this->assertStringContainsString('only when the exact takeover confirmation is supplied', $run['output']);
    }

    /** B5-4. And a WRONG takeover phrase is refused - before anything reaches the server. */
    public function test_a_wrong_takeover_phrase_is_refused(): void
    {
        foreach (['take over maintenance for session rollback', 'TAKE OVER MAINTENANCE', 'TAKE OVER MAINTENANCE FOR SESSION ROLLBACK '] as $phrase) {
            $run = $this->runSequence(target: 'file', driver: 'database', state: 'MAINTENANCE', takeover: $phrase);

            $this->assertNotSame([], $run['failed'], "[{$phrase}] was accepted as the takeover confirmation.");
            $this->assertNoWrites($run);
            $this->assertSame('MAINTENANCE', $run['state']);
        }
    }

    /**
     * B5-5. A FAILED ROLLBACK KEEPS PRODUCTION IN MAINTENANCE.
     *
     * The window it holds was opened by the failed forward change it exists to
     * undo. Releasing it would put exactly that state in front of users, so
     * `takeover` is the one origin that must NOT close on failure.
     */
    public function test_a_failed_rollback_leaves_the_inherited_maintenance_window_active(): void
    {
        $result = $this->runRecovery(starting: 'database', driver: 'database', state: 'MAINTENANCE', origin: 'takeover', mutated: 'true');

        $this->assertNotSame(0, $result['exit']);

        $this->assertNotContains(
            'php artisan up',
            $result['commands'],
            'A failed rollback released the window opened by the failure it was undoing.'
        );

        $this->assertSame('MAINTENANCE', $result['state']);
        $this->assertStringContainsString('deliberately LEFT ACTIVE', $result['output']);
        $this->assertStringContainsString('target [file]', $result['output']);
    }

    /** B5-6. Whereas a window this run opened over a LIVE deployment is restored. */
    public function test_a_failed_forward_run_that_changed_nothing_restores_the_live_state(): void
    {
        $result = $this->runRecovery(starting: 'file', driver: 'file', state: 'MAINTENANCE', origin: 'opened', mutated: 'true');

        $this->assertNotSame(0, $result['exit'], 'The run still failed and must still report failure.');
        $this->assertContains('php artisan up', $result['commands']);
        $this->assertSame('LIVE', $result['state']);
        $this->assertStringContainsString('THIS RUN opened', $result['output']);
    }

    /** B5-7. And a run holding nothing never issues `up`. */
    public function test_recovery_never_closes_a_maintenance_window_this_run_did_not_open(): void
    {
        $result = $this->runRecovery(starting: 'file', driver: 'file', state: 'MAINTENANCE', origin: '', mutated: '');

        $this->assertNotSame(0, $result['exit']);
        $this->assertNotContains('php artisan up', $result['commands'], 'Recovery released a window belonging to another operation.');
        $this->assertSame('MAINTENANCE', $result['state']);
        $this->assertStringContainsString('holds no maintenance window', $result['output']);
    }

    /** B5-8. Only a held window is ever closed on success. */
    public function test_the_close_step_is_gated_on_holding_the_window(): void
    {
        $this->assertSame(
            "steps.maintenance.outputs.origin == 'opened' || steps.maintenance.outputs.origin == 'takeover'",
            $this->gate('Close the maintenance window'),
            'The close step is not gated on this run holding the window.'
        );
    }

    /** B5-9. Ownership is recorded only once the authority is real. */
    public function test_ownership_is_recorded_only_after_the_authority_is_real(): void
    {
        $step = $this->stepBody($this->workflow(), self::MAINTENANCE_STEP);

        $down = strpos($step, 'artisan down --retry=60');
        $opened = strpos($step, 'origin=opened');
        $takeover = strpos($step, 'origin=takeover');

        $this->assertNotFalse($down);
        $this->assertNotFalse($opened, 'The step records no ownership, so recovery cannot know who holds the window.');
        $this->assertNotFalse($takeover);

        $this->assertLessThan($opened, $down, 'Ownership is recorded before the window is actually opened.');

        // The step re-reads the state itself rather than trusting the plan.
        $this->assertStringContainsString('state="$(read_state)"', $step, 'The step trusts the planning step\'s reading instead of taking its own.');
        $this->assertLessThan($opened, strpos($step, 'state="$(read_state)"'));

        // And the takeover branch refuses a window that is no longer in effect
        // BEFORE claiming it.
        $refusal = strpos($step, 'if [ "$state" != "MAINTENANCE" ]; then');
        $this->assertNotFalse($refusal, 'The takeover branch does not confirm the window is still in effect.');
        $this->assertLessThan($takeover, $refusal);
    }

    /** B5-10. The maintenance state is taken from the application, never from HTTP. */
    public function test_the_maintenance_state_is_established_from_the_application(): void
    {
        $step = $this->stepBody($this->workflow(), self::MAINTENANCE_STEP);

        $this->assertStringContainsString('isDownForMaintenance', $step);
        $this->assertStringNotContainsString('curl', $step, 'The maintenance state must not be inferred from HTTP.');
        $this->assertStringNotContainsString('http_code', $step);
    }

    // =================================================================
    // BLOCKER 6 - cancellation, and unknown ownership.
    // =================================================================

    /** B6-1. Recovery runs on cancellation as well as failure. */
    public function test_the_recovery_step_covers_cancellation_as_well_as_failure(): void
    {
        $this->assertSame(
            'failure() || cancelled()',
            $this->gate(self::RECOVERY_STEP),
            'A cancelled run would bypass every piece of recovery reasoning in this workflow.'
        );
    }

    /**
     * B6-2. AND THE LIMIT OF THAT IS STATED RATHER THAN GLOSSED.
     *
     * GitHub can terminate a runner before any step executes. The workflow says
     * so, and says what an operator must establish if that happens - rather
     * than implying cancellation recovery is guaranteed.
     */
    public function test_the_workflow_states_the_limit_of_cancellation_recovery(): void
    {
        $header = substr($this->read(self::WORKFLOW), 0, 2000);

        $this->assertStringContainsString('DO NOT CANCEL', $header);
        $this->assertStringContainsString('CANNOT promise recovery', $header);
        $this->assertStringContainsString('the effective session driver', $header);
        $this->assertStringContainsString('maintenance state', $header);
    }

    /**
     * B6-3. UNKNOWN OWNERSHIP FAILS CLOSED.
     *
     * Not "assume we own it", and not "assume we do not and say nothing": read
     * the maintenance state, report it as uncertain, issue no `up`, fail.
     */
    public function test_unknown_ownership_issues_no_up_and_reports_uncertainty(): void
    {
        $result = $this->runRecovery(starting: 'file', driver: 'file', state: 'MAINTENANCE', origin: 'something-else', mutated: 'true');

        $this->assertNotSame(0, $result['exit']);
        $this->assertNotContains('php artisan up', $result['commands']);
        $this->assertSame('MAINTENANCE', $result['state']);
        $this->assertStringContainsString('could not establish whether it holds the maintenance window', $result['output']);
        $this->assertStringContainsString('CRITICAL', $result['output']);

        // It read the state rather than guessing it.
        $this->assertContains('php artisan tinker', $result['commands']);
    }

    // =================================================================
    // The no-op path.
    // =================================================================

    // =================================================================
    // THE STATE MACHINE - change / rollback_completion / noop.
    // =================================================================

    /**
     * W30. A NO-OP REQUIRES BOTH: the driver already right AND production live.
     *
     * Round 3 decided this on the driver alone, and that is what stranded a
     * half-finished rollback: `file` while production was still down planned a
     * no-op, reported "nothing to do", and left the deployment dark.
     */
    public function test_a_no_op_requires_the_driver_to_match_and_production_to_be_live(): void
    {
        foreach (['file', 'database'] as $driver) {
            $run = $this->runSequence(target: $driver, driver: $driver, state: 'LIVE');

            $this->assertSame([], $run['failed'], $run['output']);
            $this->assertSame('noop', $run['outputs']['plan']['operation'] ?? null);
        }
    }

    /** W31. A differing driver is a change, in either direction. */
    public function test_a_differing_driver_is_planned_as_a_change(): void
    {
        $forward = $this->runSequence(target: 'database', driver: 'file', state: 'LIVE');
        $this->assertSame('change', $forward['outputs']['plan']['operation'] ?? null);

        $rollback = $this->runSequence(target: 'file', driver: 'database', state: 'MAINTENANCE', takeover: self::TAKEOVER_PHRASE);
        $this->assertSame('change', $rollback['outputs']['plan']['operation'] ?? null);
    }

    /**
     * W32. `file` + MAINTENANCE + the confirmation is a ROLLBACK COMPLETION.
     *
     * Not a no-op, and not a change: the .env is already correct, so nothing
     * is rewritten - but production is down and this run exists to bring it
     * back.
     */
    public function test_file_in_maintenance_with_the_confirmation_is_a_rollback_completion(): void
    {
        $run = $this->runSequence(target: 'file', driver: 'file', state: 'MAINTENANCE', takeover: self::TAKEOVER_PHRASE);

        $this->assertSame([], $run['failed'], $run['output']);
        $this->assertSame('rollback_completion', $run['outputs']['plan']['operation'] ?? null);
        $this->assertSame('takeover', $run['outputs']['maintenance']['origin'] ?? null);
    }

    /** W33. An unrecognised production driver stops the run before anything is touched. */
    public function test_the_plan_step_refuses_an_unrecognised_production_driver(): void
    {
        foreach (['redis', 'array', ''] as $driver) {
            $run = $this->runSequence(target: 'database', driver: $driver, state: 'LIVE');

            $this->assertContains(self::PLAN_STEP, $run['failed'], "[{$driver}] was accepted as a starting driver.");
            $this->assertNoWrites($run);
        }
    }

    /** W34. And so does an unreadable maintenance state, before anything is touched. */
    public function test_the_plan_step_refuses_an_unreadable_maintenance_state(): void
    {
        $run = $this->runSequence(target: 'database', driver: 'file', state: 'UNREADABLE-GARBAGE');

        $this->assertContains(self::PLAN_STEP, $run['failed']);
        $this->assertNoWrites($run);
        $this->assertStringContainsString('Could not establish whether production is in maintenance', $run['output']);
    }

    /**
     * W35. AN ALIGNED, SERVING DEPLOYMENT IS NEVER TAKEN DOWN - RUN END TO END.
     *
     * Not the gates read off the file: the whole sequence executed, with the
     * gates evaluated, asserting that nothing reached the server.
     */
    public function test_an_already_aligned_dispatch_takes_no_window_and_writes_nothing(): void
    {
        foreach (['file', 'database'] as $driver) {
            $run = $this->runSequence(target: $driver, driver: $driver, state: 'LIVE');

            $this->assertSame([], $run['failed'], $run['output']);

            $this->assertNotContains(self::MAINTENANCE_STEP, $run['ran'], 'An aligned, serving deployment was taken down.');
            $this->assertNotContains('Align SESSION_DRIVER', $run['ran']);
            $this->assertNotContains('Clear compiled caches', $run['ran']);
            $this->assertContains('Report that no change was required', $run['ran']);

            $this->assertNoWrites($run);
            $this->assertSame('LIVE', $run['state']);
        }
    }

    /** W35b. Every step that rewrites anything is gated on `change`, and only `change`. */
    public function test_every_mutating_step_is_gated_on_the_change_operation(): void
    {
        foreach ([self::MUTATION_STEP, 'Align SESSION_DRIVER', 'Clear compiled caches'] as $step) {
            $this->assertSame(
                "steps.plan.outputs.operation == 'change'",
                $this->gate($step),
                "[{$step}] is not gated on the change operation. A rollback completion rewrites nothing, "
                .'so it must not reach any of these.'
            );
        }

        // The window and the verification run for a completion too - it has a
        // window to take and a driver to confirm, it simply rewrites nothing.
        foreach ([
            self::MAINTENANCE_STEP,
            'Verify the effective driver is the requested target',
            'Verify application health over SSH',
            'Verify the site over HTTPS',
        ] as $step) {
            $this->assertSame(
                "steps.plan.outputs.operation != 'noop'",
                $this->gate($step),
                "[{$step}] must run for a rollback completion as well as a change."
            );
        }
    }

    /** W35c. The no-op report does not borrow the change path's language. */
    public function test_the_no_op_report_does_not_claim_anyone_was_signed_out(): void
    {
        $noop = $this->stepBody($this->workflow(), 'Report that no change was required');

        $this->assertSame("steps.plan.outputs.operation == 'noop'", $this->gate('Report that no change was required'));

        $this->assertStringNotContainsString('signed out', $noop, 'The no-op report claims users were signed out.');
        $this->assertStringContainsString('not taken', $noop);
        $this->assertStringContainsString('not interrupted', $noop);
        $this->assertStringContainsString('not read for modification, not written', $noop);

        $this->assertStringContainsString(
            'signed out',
            $this->stepBody($this->workflow(), 'Report what was done'),
            'The change report no longer states the accepted consequence.'
        );
    }

    // =================================================================
    // BLOCKER 7 - the rollback-completion state, run end to end.
    // =================================================================

    /**
     * B7-1. THE THREE-RUN RECOVERY SEQUENCE, AGAINST ONE STUBBED SERVER.
     *
     * Round 3 could get production to `file` + MAINTENANCE and no further: the
     * driver already matched the target, so the next dispatch planned a no-op
     * and left the deployment dark. This runs the whole way out.
     *
     *   A  forward `file` -> `database`, failing late. Ends `database` + DOWN.
     *   B  rollback takeover `database` -> `file`, the rewrite lands, health
     *      then fails. Ends `file` + DOWN - the state round 3 could not leave.
     *   C  dispatch `file` with the confirmation. Recognised as a ROLLBACK
     *      COMPLETION: no script, no cache clear, verify, health, window
     *      closed. Ends `file` + LIVE.
     */
    public function test_the_three_run_recovery_sequence_ends_live_on_file(): void
    {
        $this->givenServer(driver: 'file', state: 'LIVE');

        // ---- Run A: forward, failing after the site came back up. ----------
        $a = $this->runSequence(target: 'database', httpCode: '500', fresh: false);

        $this->assertContains('Verify the site over HTTPS', $a['failed'], 'Run A did not fail where this case needs it to.');
        $this->assertSame('change', $a['outputs']['plan']['operation'] ?? null);
        $this->assertSame('database', $a['driver'], 'Run A did not change the driver.');
        $this->assertSame('MAINTENANCE', $a['state'], 'Run A left a changed, unverified deployment serving users.');

        // ---- Run B: the rollback, whose rewrite lands but health fails. -----
        $b = $this->runSequence(target: 'file', takeover: self::TAKEOVER_PHRASE, healthFails: true, fresh: false);

        $this->assertContains('Verify application health over SSH', $b['failed']);
        $this->assertSame('change', $b['outputs']['plan']['operation'] ?? null);
        $this->assertSame('takeover', $b['outputs']['maintenance']['origin'] ?? null);
        $this->assertSame('file', $b['driver'], 'Run B did not roll the driver back.');
        $this->assertSame('MAINTENANCE', $b['state'], 'Run B left production serving after a failed rollback.');

        // THE STATE ROUND 3 COULD NOT ESCAPE: file, and still down.
        $this->assertStringContainsString('ROLLBACK COMPLETION', $b['output'], 'Run B does not tell the operator how to finish.');

        // ---- Run C: the completion. ----------------------------------------
        $c = $this->runSequence(target: 'file', takeover: self::TAKEOVER_PHRASE, fresh: false);

        $this->assertSame([], $c['failed'], 'The completion was refused: '.$c['output']);
        $this->assertSame('rollback_completion', $c['outputs']['plan']['operation'] ?? null);
        $this->assertSame('takeover', $c['outputs']['maintenance']['origin'] ?? null);

        // NOTHING WAS REWRITTEN.
        $this->assertNotContains(self::MUTATION_STEP, $c['ran']);
        $this->assertNotContains('Align SESSION_DRIVER', $c['ran'], 'The completion invoked the mutation script.');
        $this->assertNotContains('Clear compiled caches', $c['ran'], 'The completion cleared caches.');
        $this->assertNotContains('sh -s --', $c['commands'], 'The completion piped the driver script to the server.');
        $this->assertNotContains('php artisan optimize:clear', $c['commands'], 'The completion cleared compiled caches.');

        // But it DID verify, check health and bring production back.
        $this->assertContains('Verify the effective driver is the requested target', $c['ran']);
        $this->assertContains('Verify application health over SSH', $c['ran']);
        $this->assertContains('Close the maintenance window', $c['ran']);
        $this->assertContains('Verify the site over HTTPS', $c['ran']);

        $this->assertSame('file', $c['driver']);
        $this->assertSame('LIVE', $c['state'], 'The completion left production down.');
    }

    /** B7-2. A completion never touches .env, in isolation as well as in sequence. */
    public function test_a_rollback_completion_rewrites_nothing(): void
    {
        $run = $this->runSequence(target: 'file', driver: 'file', state: 'MAINTENANCE', takeover: self::TAKEOVER_PHRASE);

        $this->assertSame([], $run['failed'], $run['output']);
        $this->assertNotContains('sh -s --', $run['commands']);
        $this->assertNotContains('php artisan optimize:clear', $run['commands']);
        $this->assertSame('file', $run['driver']);
        $this->assertSame('LIVE', $run['state']);
    }

    /** B7-3. Without the confirmation, `file` + MAINTENANCE is refused and stays down. */
    public function test_a_completion_without_the_takeover_confirmation_is_refused(): void
    {
        $run = $this->runSequence(target: 'file', driver: 'file', state: 'MAINTENANCE');

        $this->assertContains(self::PLAN_STEP, $run['failed']);
        $this->assertNoWrites($run);
        $this->assertSame('MAINTENANCE', $run['state'], 'The refusal released the window anyway.');
        $this->assertStringContainsString('still in maintenance, which is where a partially completed rollback stops', $run['output']);
    }

    /** B7-4. A wrong confirmation is refused too, before anything reaches the server. */
    public function test_a_completion_with_a_wrong_takeover_phrase_is_refused(): void
    {
        foreach (['take over maintenance for session rollback', 'TAKE OVER MAINTENANCE', self::TAKEOVER_PHRASE.' '] as $phrase) {
            $run = $this->runSequence(target: 'file', driver: 'file', state: 'MAINTENANCE', takeover: $phrase);

            $this->assertNotSame([], $run['failed'], "[{$phrase}] was accepted.");
            $this->assertNoWrites($run);
            $this->assertSame('MAINTENANCE', $run['state']);
        }
    }

    /**
     * B7-5. `database` + MAINTENANCE with target `database` is NOT a completion.
     *
     * The recovery direction is always `file`. Treating this as a completion
     * would mean assuming control of a maintenance window in order to leave
     * production on the driver the failed change was moving it to.
     */
    public function test_database_in_maintenance_can_never_become_a_completion(): void
    {
        $run = $this->runSequence(target: 'database', driver: 'database', state: 'MAINTENANCE', takeover: self::TAKEOVER_PHRASE);

        $this->assertNotSame([], $run['failed']);
        $this->assertNoWrites($run);
        $this->assertSame('MAINTENANCE', $run['state']);
        $this->assertSame('database', $run['driver']);

        // And it is refused for being a forward target, not silently planned.
        $this->assertStringContainsString('can never authorise a forward alignment', $run['output']);
    }

    /** B7-6. Even without the phrase, it is refused rather than planned as a no-op. */
    public function test_database_in_maintenance_without_the_phrase_is_refused_not_a_no_op(): void
    {
        $run = $this->runSequence(target: 'database', driver: 'database', state: 'MAINTENANCE');

        $this->assertContains(self::PLAN_STEP, $run['failed']);
        $this->assertNoWrites($run);
        $this->assertStringContainsString('the recovery target is [file]', $run['output']);
        $this->assertStringNotContainsString('noop', $run['outputs']['plan']['operation'] ?? '');
    }

    /** B7-7. A completion whose health check fails leaves the window ACTIVE. */
    public function test_a_failed_completion_leaves_production_in_maintenance(): void
    {
        $run = $this->runSequence(
            target: 'file', driver: 'file', state: 'MAINTENANCE',
            takeover: self::TAKEOVER_PHRASE, healthFails: true,
        );

        $this->assertContains('Verify application health over SSH', $run['failed']);
        $this->assertNotContains('Close the maintenance window', $run['ran'], 'A failed completion released the window.');
        $this->assertNotContains('php artisan up', $run['commands']);
        $this->assertSame('MAINTENANCE', $run['state']);
        $this->assertStringContainsString('deliberately LEFT ACTIVE', $run['output']);
    }

    /**
     * B7-8. A completion that fails AFTER `artisan up` is put back down.
     *
     * This is the case the round-3 recovery would have got wrong: the driver
     * never changed, so its "nothing changed" branch would have reported the
     * window as left active while production was in fact SERVING, unverified.
     * Recovery now reads the maintenance state rather than inferring it from
     * the driver.
     */
    public function test_a_completion_that_fails_after_the_site_came_back_up_is_put_back_down(): void
    {
        $run = $this->runSequence(
            target: 'file', driver: 'file', state: 'MAINTENANCE',
            takeover: self::TAKEOVER_PHRASE, httpCode: '500',
        );

        $this->assertContains('Verify the site over HTTPS', $run['failed']);
        $this->assertContains('Close the maintenance window', $run['ran'], 'The site never came back up, so this proves nothing.');

        $this->assertContains('php artisan down --retry=60', $run['commands'], 'Recovery sent no maintenance command.');
        $this->assertSame('MAINTENANCE', $run['state'], 'Production was left serving a state this run never verified.');
        $this->assertStringContainsString('RELEASED it, and then ended', $run['output']);

        // And it still wrote nothing: a completion has no mutation point.
        $this->assertNotContains('sh -s --', $run['commands']);
        $this->assertNotContains('php artisan optimize:clear', $run['commands']);
    }

    /**
     * B7-9. NO RECOVERY MESSAGE EVER SENDS THE OPERATOR BACK TO `database`.
     *
     * A failed rollback leaves the driver on `file`; telling the operator to
     * take over the window with target `database` would undo the recovery and
     * re-apply the change that failed.
     */
    public function test_no_recovery_message_directs_a_takeover_back_to_database(): void
    {
        $recovery = $this->stepBody($this->workflow(), self::RECOVERY_STEP);

        $this->assertStringNotContainsString(
            'target [database]',
            $recovery,
            'A recovery message names [database] as the dispatch target. The recovery direction is always file.'
        );

        $this->assertStringNotContainsString(
            'target [$starting]',
            $recovery,
            'A recovery message names the STARTING driver as the dispatch target. After a failed rollback '
            .'that is [database], which would re-apply the change that failed.'
        );

        $this->assertSame(
            2,
            substr_count($recovery, 'with target [file]'),
            'Both recovery directions must name target [file] explicitly.'
        );
    }

    /** B7-10. And the two directions are distinguished, not collapsed. */
    public function test_the_recovery_message_distinguishes_rollback_from_completion(): void
    {
        $onDatabase = $this->runRecovery(starting: 'file', driver: 'database', state: 'LIVE', origin: 'opened', mutated: 'true');
        $this->assertStringContainsString('ROLL BACK to file using the same script', $onDatabase['output']);
        $this->assertStringNotContainsString('ROLLBACK COMPLETION', $onDatabase['output']);

        $onFile = $this->runRecovery(starting: 'database', driver: 'file', state: 'MAINTENANCE', origin: 'takeover', mutated: 'true');
        $this->assertStringContainsString('ROLLBACK COMPLETION', $onFile['output']);
        $this->assertStringContainsString('rewrite nothing', $onFile['output']);
    }

    /** W36. NO AUTOMATIC ROLLBACK, AND THE EXPLICIT ONE IS NAMED. */
    public function test_it_does_not_roll_back_automatically_and_names_the_explicit_rollback(): void
    {
        $workflow = $this->workflow();

        $this->assertSame(
            1,
            preg_match_all('/< deployment\/ensure-session-driver\.sh/', $workflow),
            'The driver script is invoked more than once. A failure path that mutates production a second '
            .'time is not a rollback, it is a second unattended change.'
        );

        $this->assertStringContainsString(
            'dispatch this workflow from main with target [file]',
            $this->stepBody($workflow, self::RECOVERY_STEP),
            'The failure message does not tell the operator how to recover.'
        );
    }

    /** W37. THE FILE SAYS WHAT DISPATCHING IT COSTS. */
    public function test_the_workflow_states_its_consequence_and_its_authority_at_the_top(): void
    {
        $header = substr($this->read(self::WORKFLOW), 0, 1200);

        $this->assertStringContainsString('SIGNS EVERY USER OUT', $header);
        $this->assertStringContainsString('Product Owner GO', $header);
        $this->assertStringContainsString('PHASE-1-CLOSEOUT-WS-1-SESSION-DRIVER-RUNBOOK.md', $header);
    }

    // =================================================================
    // Running the workflow's own steps against a stubbed server.
    // =================================================================

    /** Nothing in this run may have written to the server. */
    private function assertNoWrites(array $run): void
    {
        foreach (['php artisan down', 'php artisan up', 'php artisan optimize:clear', 'sh -s --'] as $write) {
            $this->assertNotContains(
                $write,
                $run['commands'],
                "A run that refused before the mutation point sent [{$write}] to the server."
            );
        }
    }

    /** Set the stubbed server's starting condition. */
    private function givenServer(string $driver, string $state): void
    {
        file_put_contents($this->dir.'/driver', $driver."\n");
        file_put_contents($this->dir.'/state', $state."\n");
        file_put_contents($this->dir.'/commands', '');
    }

    /**
     * Execute the failure-recovery step on its own.
     *
     * @return array{exit: int, output: string, commands: list<string>, state: string}
     */
    private function runRecovery(
        string $starting,
        string $driver,
        string $state,
        string $origin,
        string $mutated,
        string $operation = 'change',
        bool $downFails = false,
        ?string $workflow = null,
    ): array {
        $this->givenServer($driver, $state);

        $script = $this->stepScript($workflow ?? $this->workflow(), self::RECOVERY_STEP, [
            '${{ steps.plan.outputs.starting }}' => $starting,
            '${{ steps.plan.outputs.operation }}' => $operation,
            '${{ steps.maintenance.outputs.origin }}' => $origin,
            '${{ steps.mutation.outputs.attempted }}' => $mutated,
        ]);

        $result = $this->runScript($script, ['STUB_DOWN_FAILS' => $downFails ? '1' : '0']);

        return $result + [
            'commands' => $this->remoteCommands(),
            'state' => trim((string) file_get_contents($this->dir.'/state')),
        ];
    }

    /**
     * Execute the whole step sequence, evaluating each gate as the runner would.
     *
     * Checkout and Configure SSH are excluded: they carry no decision and
     * nothing in them can be simulated honestly.
     *
     * @return array{ran: list<string>, skipped: list<string>, failed: list<string>, outputs: array<string, array<string, string>>, commands: list<string>, output: string, driver: string, state: string}
     */
    private function runSequence(
        string $target,
        ?string $driver = null,
        ?string $state = null,
        string $takeover = '',
        string $confirmation = 'ALIGN SESSION DRIVER',
        string $httpCode = '200',
        bool $healthFails = false,
        bool $fresh = true,
    ): array {
        if ($fresh) {
            $this->givenServer($driver ?? 'file', $state ?? 'LIVE');
        } else {
            file_put_contents($this->dir.'/commands', '');
        }

        $workflow = $this->workflow();
        $outputs = [];
        $ran = $skipped = $failed = [];
        $output = '';
        $runFailed = false;

        foreach (self::SEQUENCE as $name) {
            $gate = $this->optionalGate($workflow, $name);

            if (! $this->gatePasses($gate, $outputs, $runFailed)) {
                $skipped[] = $name;

                continue;
            }

            $id = $this->stepId($workflow, $name);

            file_put_contents($this->dir.'/github_output', '');

            $script = $this->stepScript($workflow, $name, $this->substitutions($outputs));

            $result = $this->runScript($script, [
                'TARGET_DRIVER' => $target,
                'ROLLBACK_TAKEOVER' => $takeover,
                'CONFIRMATION' => $confirmation,
                'STUB_HTTP_CODE' => $httpCode,
                'STUB_HEALTH_FAILS' => $healthFails ? '1' : '0',
            ]);

            $ran[] = $name;
            $output .= $result['output'];

            if ($id !== null) {
                $outputs[$id] = $this->githubOutputs();
            }

            if ($result['exit'] !== 0) {
                $failed[] = $name;
                $runFailed = true;
            }
        }

        return [
            'ran' => $ran,
            'skipped' => $skipped,
            'failed' => $failed,
            'outputs' => $outputs,
            'commands' => $this->remoteCommands(),
            'output' => $output,
            'driver' => trim((string) file_get_contents($this->dir.'/driver')),
            'state' => trim((string) file_get_contents($this->dir.'/state')),
        ];
    }

    /** @param array<string, array<string, string>> $outputs */
    private function substitutions(array $outputs): array
    {
        $substitutions = ['${{ github.ref }}' => 'refs/heads/main'];

        foreach ([
            'plan' => ['starting', 'operation', 'maintenance_plan'],
            'maintenance' => ['origin'],
            'mutation' => ['attempted'],
        ] as $id => $keys) {
            foreach ($keys as $key) {
                $substitutions['${{ steps.'.$id.'.outputs.'.$key.' }}'] = $outputs[$id][$key] ?? '';
            }
        }

        return $substitutions;
    }

    /**
     * Evaluate a step condition.
     *
     * Deliberately tiny, and deliberately strict: it understands the exact
     * forms this workflow uses and refuses anything else, so a condition that
     * changed shape could never be silently treated as "true".
     *
     * @param  array<string, array<string, string>>  $outputs
     */
    private function gatePasses(?string $gate, array $outputs, bool $runFailed): bool
    {
        if ($gate === null || $gate === '') {
            return ! $runFailed;
        }

        // The recovery conditions. `cancelled()` cannot be simulated here - a
        // cancelled run is a terminated runner, not a shell - so both forms are
        // evaluated as "something went wrong". That a CHANGED condition is a
        // defect is asserted directly, in B6-1, rather than inferred from this
        // evaluator failing to parse it.
        if ($gate === 'failure() || cancelled()' || $gate === 'failure()') {
            return $runFailed;
        }

        if ($runFailed) {
            return false;
        }

        foreach (explode(' || ', $gate) as $clause) {
            $this->assertSame(
                1,
                preg_match('/^steps\.([a-z_]+)\.outputs\.([a-z_]+) (==|!=) \'([a-z_]*)\'$/', trim($clause), $match),
                "The condition [{$gate}] is not a form this evaluator understands, so the sequence cases "
                .'would be reasoning about a gate they cannot read.'
            );

            [, $id, $key, $operator, $value] = $match;
            $actual = $outputs[$id][$key] ?? '';

            if (($operator === '==' && $actual === $value) || ($operator === '!=' && $actual !== $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, string>  $env
     * @return array{exit: int, output: string}
     */
    private function runScript(string $script, array $env = []): array
    {
        $this->writeStubs();

        foreach (['driver', 'state', 'commands', 'github_output', 'summary'] as $file) {
            if (! file_exists($this->dir.'/'.$file)) {
                file_put_contents($this->dir.'/'.$file, '');
            }
        }

        $path = $this->dir.'/step.sh';
        file_put_contents($path, $script);

        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];

        // The runner uses bash, and the repository root is the working
        // directory, so `< deployment/ensure-session-driver.sh` resolves.
        $process = proc_open(['bash', $path], $descriptors, $pipes, $this->root(), $env + [
            'PATH' => $this->dir.'/bin:'.getenv('PATH'),
            'HOME' => $this->dir,
            'CPANEL_HOST' => 'host.invalid',
            'CPANEL_PORT' => '22',
            'CPANEL_USER' => 'deployer',
            'CPANEL_DEPLOY_PATH' => '/srv/semantiq',
            'APP_URL' => 'https://example.invalid',
            'GITHUB_OUTPUT' => $this->dir.'/github_output',
            'GITHUB_STEP_SUMMARY' => $this->dir.'/summary',
            'GITHUB_RUN_ID' => '1',
            'STUB_LOG' => $this->dir.'/commands',
            'STUB_STATE' => $this->dir.'/state',
            'STUB_DRIVER' => $this->dir.'/driver',
            'TAKEOVER_PHRASE' => self::TAKEOVER_PHRASE,
            'TARGET_DRIVER' => 'file',
            'ROLLBACK_TAKEOVER' => '',
            'CONFIRMATION' => 'ALIGN SESSION DRIVER',
        ]);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return ['exit' => proc_close($process), 'output' => $stdout.$stderr];
    }

    /**
     * Stand-ins for `ssh` and `curl`.
     *
     * The ssh stub answers what these steps ask the server, records every
     * remote command, and - for the driver script piped over stdin - actually
     * moves the stubbed driver, so a sequence behaves like one.
     */
    private function writeStubs(): void
    {
        file_put_contents($this->dir.'/bin/ssh', <<<'STUB'
            #!/bin/sh
            for a in "$@"; do last="$a"; done
            printf '%s\n' "$last" >> "$STUB_LOG"

            case "$last" in
              "sh -s -- "*)
                # The driver script, piped over stdin. Consume it and apply the
                # requested target, so a sequence moves like the real thing.
                cat > /dev/null
                target="$(printf '%s' "$last" | sed 's/.*"\([a-z]*\)"[[:space:]]*$/\1/')"
                printf '%s\n' "$target" > "$STUB_DRIVER"
                printf 'SESSION_DRIVER updated to %s.\n' "$target"
                exit 0
                ;;
              *"artisan down"*)
                [ "${STUB_DOWN_FAILS:-0}" = "1" ] && exit 1
                printf 'MAINTENANCE\n' > "$STUB_STATE"
                exit 0
                ;;
              *"artisan up"*)
                printf 'LIVE\n' > "$STUB_STATE"
                exit 0
                ;;
              *isDownForMaintenance*)
                cat "$STUB_STATE"
                exit 0
                ;;
              *"session.driver"*)
                cat "$STUB_DRIVER"
                exit 0
                ;;
              *"optimize:clear"*)
                exit 0
                ;;
              *"semantiq:health"*)
                [ "${STUB_HEALTH_FAILS:-0}" = "1" ] && exit 1
                printf 'Healthy.\n'
                exit 0
                ;;
              *REACHABLE*)
                printf 'REACHABLE\n'
                exit 0
                ;;
              *)
                exit 0
                ;;
            esac
            STUB);

        file_put_contents($this->dir.'/bin/curl', <<<'STUB'
            #!/bin/sh
            for a in "$@"; do last="$a"; done
            case "$last" in
              *"/up?"*) printf '%s' "${STUB_UP_BODY:-ok}"; exit 0 ;;
            esac
            printf '%s' "${STUB_HTTP_CODE:-200}"
            exit 0
            STUB);

        chmod($this->dir.'/bin/ssh', 0o755);
        chmod($this->dir.'/bin/curl', 0o755);
    }

    /** @return list<string> */
    private function remoteCommands(): array
    {
        $commands = [];

        foreach (file($this->dir.'/commands', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            if (str_starts_with($line, 'sh -s -- ')) {
                $commands[] = 'sh -s --';

                continue;
            }

            if (preg_match('/(php artisan [a-z:]+(?: --retry=\d+)?)/', $line, $match) === 1) {
                $commands[] = $match[1];
            }
        }

        return $commands;
    }

    /** @return array<string, string> */
    private function githubOutputs(): array
    {
        $outputs = [];

        foreach (file($this->dir.'/github_output', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
            $outputs[$key] = $value;
        }

        return $outputs;
    }

    // -----------------------------------------------------------------
    // Reading the workflow.
    // -----------------------------------------------------------------

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

    /** A step's `if:` expression, verbatim. It must have one. */
    private function gate(string $name): string
    {
        $gate = $this->optionalGate($this->workflow(), $name);

        $this->assertNotNull($gate, "[{$name}] has no `if:` condition at all.");

        return $gate;
    }

    private function optionalGate(string $workflow, string $name): ?string
    {
        $body = $this->stepBody($workflow, $name);

        return preg_match('/^        if: (.+)$/m', $body, $match) === 1 ? trim($match[1]) : null;
    }

    private function stepId(string $workflow, string $name): ?string
    {
        $body = $this->stepBody($workflow, $name);

        return preg_match('/^        id: (.+)$/m', $body, $match) === 1 ? trim($match[1]) : null;
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
