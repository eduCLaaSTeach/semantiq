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
 *      across the whole file is satisfied by the word appearing in a step name,
 *      and broken by an unrelated `git push`. The `on:` block is isolated first
 *      and the question is asked of that block only.
 *
 *   3. THE DECISION LOGIC IS RUN, NOT READ. The `plan` step and the failure
 *      recovery step carry the reasoning that matters, and round 1 of tooling
 *      review found the cost of only reading them: the recovery step ANNOUNCED
 *      that production had been "LEFT IN MAINTENANCE" and ran nothing that
 *      would make that true, and a test asserting the message passed. So those
 *      two steps' shell is extracted and EXECUTED here against a stubbed `ssh`,
 *      and the assertions are about what the script did - which commands it
 *      sent to the server - rather than about what it says.
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

    private const RECOVERY_STEP = 'Establish the exact state after a failure';

    /** The `artisan down` the recovery step must issue when the driver changed. */
    private const RECOVERY_DOWN = 'remote "php artisan down --retry=60" >/dev/null 2>&1 || true';

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

    // -----------------------------------------------------------------
    // Trigger: manual, and nothing else.
    // -----------------------------------------------------------------

    /**
     * W1. workflow_dispatch and NOTHING else.
     *
     * Mutation: add `push: {branches: main}` to the trigger block.
     */
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

    /**
     * W2. And nothing else runs it either.
     *
     * A push trigger is the obvious mistake. The subtle one is another workflow
     * calling this one, or deploy.yml running the script directly - which would
     * put a driver switch on every push to main, documentation merges included.
     */
    public function test_no_other_workflow_invokes_the_driver_script_or_this_workflow(): void
    {
        $checked = 0;

        foreach (glob($this->root().'/.github/workflows/*.yml') ?: [] as $path) {
            if (basename($path) === basename(self::WORKFLOW)) {
                continue;
            }

            $checked++;
            $source = $this->withoutComments((string) file_get_contents($path));

            $this->assertStringNotContainsString(
                'ensure-session-driver.sh',
                $source,
                basename($path).' invokes the session driver script. Only the manual, confirmed, '
                .'main-guarded alignment workflow may.'
            );

            $this->assertStringNotContainsString(
                basename(self::WORKFLOW),
                $source,
                basename($path).' references the alignment workflow. It must not be chained to anything.'
            );
        }

        $this->assertGreaterThan(5, $checked, 'Almost no workflows were scanned. The glob stopped matching.');
    }

    /** W3. deploy.yml in particular - it fires on every push to main. */
    public function test_the_deployment_workflow_does_not_touch_the_session_driver(): void
    {
        $deploy = $this->withoutComments($this->read('.github/workflows/deploy.yml'));

        $this->assertStringNotContainsString('ensure-session-driver', $deploy);
        $this->assertStringNotContainsString('SESSION_DRIVER', $deploy);

        // The sibling it must keep doing, so this case cannot pass by the
        // deployment having lost its .env handling entirely.
        $this->assertStringContainsString('ensure-session-lifetime.sh', $deploy);
    }

    // -----------------------------------------------------------------
    // Inputs: two names, a typed phrase, no key and no value.
    // -----------------------------------------------------------------

    /**
     * W4. The target is a CHOICE of exactly two drivers.
     *
     * Mutation: change `type: choice` to `type: string`, or add an option.
     */
    public function test_the_target_input_is_a_choice_of_exactly_file_and_database(): void
    {
        $target = $this->block($this->block($this->workflow(), 'on'), 'target', depth: 6);

        $this->assertStringContainsString('type: choice', $target, 'The target driver is not a restricted choice.');

        preg_match_all('/^\s*- (\S+)\s*$/m', $target, $matches);

        sort($matches[1]);

        $this->assertSame(
            ['database', 'file'],
            $matches[1],
            'The selectable drivers must be exactly database and file.'
        );
    }

    /**
     * W5. A TYPED confirmation, checked against an exact phrase.
     *
     * Mutation: delete the comparison and keep the input. The workflow still
     * looks confirmed and no longer is.
     */
    public function test_a_typed_confirmation_phrase_is_required_and_enforced(): void
    {
        $workflow = $this->workflow();

        $this->assertStringContainsString('confirmation:', $this->block($workflow, 'on'));

        $this->assertMatchesRegularExpression(
            '/if \[ "\$CONFIRMATION" != "ALIGN SESSION DRIVER" \]; then/',
            $workflow,
            'The confirmation input is collected but never compared against the expected phrase.'
        );

        $this->assertMatchesRegularExpression(
            '/!= "ALIGN SESSION DRIVER" \]; then\s*\n\s*echo "::error::[^\n]*"\s*\n\s*exit 1/',
            $workflow,
            'A wrong confirmation phrase does not stop the run.'
        );
    }

    /** W6. THERE IS NO GENERAL ENVIRONMENT EDITOR. */
    public function test_the_workflow_offers_no_arbitrary_key_or_value_input(): void
    {
        $inputs = $this->block($this->workflow(), 'on');

        preg_match_all('/^      ([a-z_]+):\s*$/m', $inputs, $matches);

        sort($matches[1]);

        $this->assertSame(
            ['confirmation', 'target'],
            $matches[1],
            'The workflow accepts inputs beyond the driver and the confirmation: '
            .implode(', ', $matches[1]).'. There is nowhere for an arbitrary .env key to be supplied, '
            .'and it must stay that way.'
        );

        foreach (['key:', 'value:', 'env_key:', 'setting:'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $inputs);
        }
    }

    // -----------------------------------------------------------------
    // The ref guard.
    // -----------------------------------------------------------------

    /** W7. A HARD REFUSAL UNLESS THE REF IS main. */
    public function test_it_refuses_unless_it_was_dispatched_from_main(): void
    {
        $workflow = $this->workflow();

        $this->assertMatchesRegularExpression(
            '/if \[ "\$\{\{ github\.ref \}\}" != "refs\/heads\/main" \]; then/',
            $workflow,
            'There is no exact equality check on the dispatching ref.'
        );

        $this->assertMatchesRegularExpression(
            '/!= "refs\/heads\/main" \]; then\s*\n\s*echo "::error::[^\n]*"\s*\n\s*exit 1/',
            $workflow,
            'A dispatch from a branch other than main does not stop the run.'
        );
    }

    /** W8. And the guard runs before anything reaches the server. */
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

    // -----------------------------------------------------------------
    // Concurrency, permissions, credentials.
    // -----------------------------------------------------------------

    /** W9. THE SAME CONCURRENCY GROUP AS THE DEPLOYMENT, AND NEVER CANCELLED. */
    public function test_it_shares_the_deployment_concurrency_group_and_never_cancels(): void
    {
        $concurrency = $this->block($this->workflow(), 'concurrency');

        $this->assertMatchesRegularExpression('/^\s*group: cpanel-deploy\s*$/m', $concurrency);
        $this->assertMatchesRegularExpression('/^\s*cancel-in-progress: false\s*$/m', $concurrency);

        // The other half of the claim: it is the group the deployment uses.
        $deployConcurrency = $this->block(
            $this->withoutComments($this->read('.github/workflows/deploy.yml')),
            'concurrency'
        );

        $this->assertMatchesRegularExpression(
            '/^\s*group: cpanel-deploy\s*$/m',
            $deployConcurrency,
            'The deployment no longer uses cpanel-deploy, so sharing the name guarantees nothing.'
        );
        $this->assertMatchesRegularExpression('/^\s*cancel-in-progress: false\s*$/m', $deployConcurrency);
    }

    /** W10. Read-only token. It needs the checkout and nothing more. */
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
            $this->assertStringContainsString(
                'secrets.'.$secret,
                $workflow,
                "The workflow does not read the established [{$secret}] secret."
            );
        }

        preg_match_all('/secrets\.([A-Z_]+)/', $workflow, $matches);

        $deploy = $this->read('.github/workflows/deploy.yml');

        foreach (array_unique($matches[1]) as $secret) {
            $this->assertStringContainsString(
                'secrets.'.$secret,
                $deploy,
                "[{$secret}] is a secret the deployment does not use. This workflow must not introduce "
                .'a second credentials model.'
            );
        }
    }

    /** W12. The passphrase-protected key needs ssh-agent and askpass, as the siblings do. */
    public function test_it_uses_the_established_ssh_agent_and_askpass_mechanism(): void
    {
        $workflow = $this->workflow();

        foreach (['ssh-agent -s', 'SSH_ASKPASS', 'SSH_ASKPASS_REQUIRE=force', 'ssh-add', 'ssh-keyscan'] as $fragment) {
            $this->assertStringContainsString(
                $fragment,
                $workflow,
                "The SSH setup omits [{$fragment}]. The deploy key is passphrase-protected; writing the "
                .'key file alone produces "Permission denied (publickey)".'
            );
        }
    }

    // -----------------------------------------------------------------
    // What it runs on the server.
    // -----------------------------------------------------------------

    /** W13. THE SCRIPT IS CALLED WITH A PATH AND A DRIVER. NOTHING ELSE. */
    public function test_the_script_is_piped_over_stdin_with_only_a_path_and_a_target(): void
    {
        $workflow = $this->workflow();

        $this->assertMatchesRegularExpression(
            '/"sh -s -- \\\\"\$CPANEL_DEPLOY_PATH\\\\" \\\\"\$TARGET_DRIVER\\\\""\s*\\\\\s*\n\s*< deployment\/ensure-session-driver\.sh/',
            $workflow,
            'The script is not invoked with exactly the deployment path and the target driver, piped '
            .'over stdin.'
        );

        foreach (['scp ', 'rsync ', 'cat > ', 'install -m'] as $copies) {
            $this->assertStringNotContainsString(
                $copies.'deployment/',
                $workflow,
                'The script must not be deployed to the host.'
            );
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

    /** W15. IT NEVER TOUCHES IDENTITY, Entra OR APP_KEY. */
    public function test_it_never_modifies_identity_configuration_or_the_app_key(): void
    {
        $workflow = $this->workflow();

        foreach ([
            'APP_KEY', 'key:generate', 'ensure-app-key',
            'MICROSOFT_', 'identity_source', 'IDENTITY_SOURCE',
            'identity:', 'entra',
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
            $this->assertStringNotContainsString(
                $forbidden,
                $workflow,
                "The workflow references [{$forbidden}]. Nothing from .env or the session store may reach "
                .'a workflow log.'
            );
        }
    }

    // -----------------------------------------------------------------
    // Ordering.
    // -----------------------------------------------------------------

    /**
     * W17. THE OPERATIONAL SEQUENCE, IN ORDER.
     *
     * Validate, prove access, establish the starting state and decide whether
     * there is anything to do - all before the site goes dark. Then the
     * existing-maintenance refusal, the window, the change, the cache clear,
     * the verification that the APPLICATION is on the requested driver, health,
     * and only then the site comes back.
     *
     * Mutation: move `Close the maintenance window` above the verification. The
     * site then returns before anything has confirmed it works.
     */
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
            'Refuse if production is already in maintenance',
            'Open the maintenance window',
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

        $this->assertStringContainsString(
            'config(\\"session.driver\\")',
            $workflow,
            'Nothing reads the effective driver back out of the application.'
        );

        $this->assertMatchesRegularExpression(
            '/if \[ "\$effective" != "\$TARGET_DRIVER" \]; then\s*\n\s*echo "::error::[^\n]*"\s*\n\s*exit 1/',
            $workflow,
            'The effective driver is read but a mismatch does not fail the run.'
        );
    }

    // =================================================================
    // BLOCKER 1 - failing closed means PUTTING THE SITE DOWN, not saying it
    // is down. These cases RUN the recovery step.
    // =================================================================

    /**
     * W19. A FAILURE AFTER THE DRIVER CHANGED RE-ENTERS MAINTENANCE.
     *
     * THE DEFECT THIS EXISTS FOR. The first version of this workflow announced
     * that production had been "LEFT IN MAINTENANCE" and issued no command that
     * would make that so. The normal close step runs `artisan up` BEFORE the
     * HTTPS verification and the reporting, so a failure in either of those
     * left a changed, unverified deployment SERVING USERS while the log said
     * the opposite. The test that covered it asserted the message.
     *
     * So this one runs the step. The fixture is exactly that scenario - the
     * driver changed, `artisan up` has already run, the site is LIVE - and the
     * assertion is on the commands the step actually sent to the server.
     */
    public function test_a_failure_after_the_site_came_back_up_puts_it_back_into_maintenance(): void
    {
        $result = $this->runRecovery(
            starting: 'file',
            effectiveDriver: 'database',
            maintenanceState: 'LIVE',   // artisan up already ran
            weOpened: 'true',
        );

        $this->assertNotSame(0, $result['exit'], 'A failed run must fail the job.');

        $this->assertContains(
            'php artisan down --retry=60',
            $result['commands'],
            'The driver was changed and the run failed with the site LIVE, and the recovery step sent no '
            .'maintenance command. Production is serving an unverified deployment.'
        );

        $this->assertSame(
            'MAINTENANCE',
            $result['state'],
            'The recovery step ran but production did not end up in maintenance.'
        );

        $this->assertStringContainsString('PUT BACK INTO MAINTENANCE', $result['output']);

        $this->assertNotContains(
            'php artisan up',
            $result['commands'],
            'The recovery step brought the site back up after a failed driver change.'
        );
    }

    /**
     * W20. AND THE COMMAND IS WHAT DOES IT, not the sentence.
     *
     * The same fixture against a copy of the workflow with only the recovery
     * `artisan down` removed. If this run still ended in maintenance, W19 would
     * be proving nothing.
     */
    public function test_without_the_recovery_down_the_same_failure_leaves_production_serving(): void
    {
        $mutant = $this->workflowWithout(self::RECOVERY_DOWN);

        $result = $this->runRecovery(
            starting: 'file',
            effectiveDriver: 'database',
            maintenanceState: 'LIVE',
            weOpened: 'true',
            workflow: $mutant,
        );

        $this->assertSame(
            'LIVE',
            $result['state'],
            'Production ended up in maintenance even with the recovery command removed, so W19 proves '
            .'nothing about that command.'
        );

        $this->assertNotContains('php artisan down --retry=60', $result['commands']);
    }

    /**
     * W21. THE CLAIM CANNOT OUTLIVE THE COMMAND.
     *
     * A structural companion to W19: whatever wording the recovery step uses,
     * it may not assert that production is in maintenance unless it has sent a
     * maintenance command first. This catches the original phrasing too, so
     * reintroducing "LEFT IN MAINTENANCE" without the command fails here.
     */
    public function test_no_maintenance_claim_appears_before_the_maintenance_command(): void
    {
        $recovery = $this->stepBody($this->workflow(), self::RECOVERY_STEP);

        $down = strpos($recovery, 'artisan down');

        $this->assertNotFalse(
            $down,
            'The recovery step never issues a maintenance command, so it cannot honestly claim production '
            .'is in maintenance.'
        );

        foreach (['LEFT IN MAINTENANCE', 'PUT BACK INTO MAINTENANCE', 'left in maintenance'] as $claim) {
            $position = strpos($recovery, $claim);

            if ($position === false) {
                continue;
            }

            $this->assertLessThan(
                $position,
                $down,
                "The recovery step claims [{$claim}] before it has issued any maintenance command."
            );
        }
    }

    /**
     * W22. IF MAINTENANCE CANNOT BE GUARANTEED, IT IS NOT CLAIMED.
     *
     * `artisan down` can fail - the same broken SSH or broken application that
     * failed the run can fail it. Reporting maintenance anyway would be the
     * original defect wearing a command.
     */
    public function test_a_failed_recovery_down_escalates_instead_of_claiming_maintenance(): void
    {
        $result = $this->runRecovery(
            starting: 'file',
            effectiveDriver: 'database',
            maintenanceState: 'LIVE',
            weOpened: 'true',
            downFails: true,
        );

        $this->assertNotSame(0, $result['exit']);
        $this->assertSame('LIVE', $result['state']);

        $this->assertStringNotContainsString(
            'PUT BACK INTO MAINTENANCE',
            $result['output'],
            'The recovery step claimed maintenance after the command to enter it had failed.'
        );

        $this->assertStringContainsString('CRITICAL', $result['output']);
        $this->assertStringContainsString('MAINTENANCE MODE COULD NOT BE GUARANTEED', $result['output']);
        $this->assertStringContainsString('SSH IMMEDIATELY', $result['output']);
    }

    /** W23. An unreadable driver is an unknown state, and is escalated as one. */
    public function test_an_unreadable_driver_after_a_failure_is_escalated_as_unknown(): void
    {
        $result = $this->runRecovery(
            starting: 'file',
            effectiveDriver: '',
            maintenanceState: 'LIVE',
            weOpened: 'true',
        );

        $this->assertNotSame(0, $result['exit']);
        $this->assertStringContainsString('UNKNOWN', $result['output']);
        $this->assertStringContainsString('CRITICAL', $result['output']);

        $this->assertNotContains(
            'php artisan up',
            $result['commands'],
            'The state could not be read and the recovery step brought the site up anyway.'
        );
    }

    /** W24. A failure before the plan step means nothing was touched, and it says so. */
    public function test_a_failure_before_the_driver_was_established_touches_nothing(): void
    {
        $result = $this->runRecovery(
            starting: '',
            effectiveDriver: 'file',
            maintenanceState: 'LIVE',
            weOpened: '',
        );

        $this->assertNotSame(0, $result['exit']);
        $this->assertSame([], $result['commands'], 'Something was sent to the server after an early failure.');
        $this->assertStringContainsString('was not touched', $result['output']);
    }

    // =================================================================
    // BLOCKER 3 - this workflow never closes a window it did not open.
    // =================================================================

    /** W25. Production already in maintenance is a hard refusal, before any mutation. */
    public function test_existing_maintenance_causes_a_hard_refusal_before_any_mutation(): void
    {
        $refusal = $this->stepBody($this->workflow(), 'Refuse if production is already in maintenance');

        // Established from the application, not guessed from an HTTP status: a
        // proxy or a cache can produce a 503 without the application being down.
        $this->assertStringContainsString(
            'isDownForMaintenance',
            $refusal,
            'The maintenance state is not established from the application itself.'
        );

        $this->assertStringNotContainsString('curl', $refusal, 'The maintenance state must not be inferred from HTTP.');
        $this->assertStringNotContainsString('http_code', $refusal);

        $this->assertMatchesRegularExpression(
            '/MAINTENANCE\)\s*\n\s*echo "::error::[^\n]*"\s*\n\s*exit 1/',
            $refusal,
            'Finding production already in maintenance does not stop the run.'
        );

        // An unreadable state is a refusal too, not an assumption that it is live.
        $this->assertMatchesRegularExpression(
            '/\*\)\s*\n\s*echo "::error::Could not establish[^\n]*"\s*\n\s*exit 1/',
            $refusal
        );

        // And it happens before the window is opened.
        $names = $this->stepNames();

        $this->assertLessThan(
            array_search('Open the maintenance window', $names, true),
            array_search('Refuse if production is already in maintenance', $names, true)
        );
    }

    /** W26. Ownership is recorded only once the window has actually been opened. */
    public function test_ownership_of_the_window_is_recorded_only_after_it_opens(): void
    {
        $open = $this->stepBody($this->workflow(), 'Open the maintenance window');

        $down = strpos($open, 'artisan down --retry=60');
        $records = strpos($open, 'opened=true');

        $this->assertNotFalse($down);
        $this->assertNotFalse($records, 'The open step records no ownership, so recovery cannot know who owns the window.');

        $this->assertLessThan(
            $records,
            $down,
            'Ownership is recorded before the window is actually opened, so a failed `artisan down` would '
            .'still leave the run believing it owns the window.'
        );
    }

    /** W27. The close step is gated on OWNERSHIP, not on anything that merely correlates with it. */
    public function test_the_close_step_is_gated_on_ownership(): void
    {
        $this->assertSame(
            "steps.maintenance.outputs.opened == 'true'",
            $this->gate('Close the maintenance window'),
            'The close step is not gated on this run having opened the window.'
        );
    }

    /**
     * W28. RECOVERY DOES NOT RELEASE A WINDOW IT DID NOT OPEN.
     *
     * The scenario: the existing-maintenance refusal fires. The driver is
     * unchanged, so recovery takes the "nothing happened" branch - and must not
     * run `artisan up`, because the window in effect belongs to whatever
     * operation the refusal was protecting.
     */
    public function test_recovery_never_closes_a_maintenance_window_this_run_did_not_open(): void
    {
        $result = $this->runRecovery(
            starting: 'file',
            effectiveDriver: 'file',
            maintenanceState: 'MAINTENANCE',   // somebody else's window
            weOpened: '',
        );

        $this->assertNotSame(0, $result['exit']);

        $this->assertNotContains(
            'php artisan up',
            $result['commands'],
            'The recovery step released a maintenance window that belongs to another operation.'
        );

        $this->assertSame('MAINTENANCE', $result['state'], "Somebody else's maintenance window was ended.");
        $this->assertStringContainsString('did not open a maintenance window', $result['output']);
    }

    /** W29. But it does close its own. */
    public function test_recovery_closes_the_window_when_this_run_opened_it(): void
    {
        $result = $this->runRecovery(
            starting: 'file',
            effectiveDriver: 'file',
            maintenanceState: 'MAINTENANCE',
            weOpened: 'true',
        );

        $this->assertNotSame(0, $result['exit'], 'The run still failed and must still report failure.');

        $this->assertContains(
            'php artisan up',
            $result['commands'],
            'Nothing was changed and the window this run opened was left closed over production.'
        );

        $this->assertSame('LIVE', $result['state']);
        $this->assertStringContainsString('THIS RUN opened', $result['output']);
    }

    // =================================================================
    // BLOCKER 2 - an aligned deployment is a no-op. These cases RUN the
    // plan step.
    // =================================================================

    /**
     * W30. THE PLAN STEP DECIDES, AND THE DECISION IS RUN RATHER THAN READ.
     *
     * The script is idempotent; before round 1 of tooling review the workflow
     * was not, so dispatching `database` at a deployment already on `database`
     * opened a maintenance window and signed everybody out for a change that
     * does not exist.
     */
    public function test_the_plan_step_requires_no_change_when_the_driver_already_matches(): void
    {
        foreach ([['file', 'file'], ['database', 'database']] as [$starting, $target]) {
            $result = $this->runPlan(currentDriver: $starting, target: $target);

            $this->assertSame(0, $result['exit'], $result['output']);
            $this->assertSame($starting, $result['outputs']['starting'] ?? null);
            $this->assertSame(
                'false',
                $result['outputs']['change_required'] ?? null,
                "Starting [{$starting}] with target [{$target}] was planned as a change."
            );
        }
    }

    /** W31. And it does require one when they differ - or the no-op would swallow the change. */
    public function test_the_plan_step_requires_a_change_when_the_driver_differs(): void
    {
        foreach ([['file', 'database'], ['database', 'file']] as [$starting, $target]) {
            $result = $this->runPlan(currentDriver: $starting, target: $target);

            $this->assertSame(0, $result['exit'], $result['output']);
            $this->assertSame($starting, $result['outputs']['starting'] ?? null);
            $this->assertSame(
                'true',
                $result['outputs']['change_required'] ?? null,
                "Starting [{$starting}] with target [{$target}] was planned as a no-op."
            );
        }
    }

    /** W32. An unrecognised production driver stops the run before anything is touched. */
    public function test_the_plan_step_refuses_an_unrecognised_production_driver(): void
    {
        foreach (['redis', 'array', ''] as $driver) {
            $result = $this->runPlan(currentDriver: $driver, target: 'database');

            $this->assertNotSame(0, $result['exit'], "[{$driver}] was accepted as a starting driver.");
            $this->assertArrayNotHasKey('change_required', $result['outputs']);
        }
    }

    /**
     * W33. EVERY MUTATING AND MAINTENANCE STEP IS GATED ON THAT DECISION.
     *
     * The gate expression is asserted verbatim rather than merely "present", so
     * there is no room for a condition that reads like the right one and is
     * not. Combined with W30, this is what establishes that an already-aligned
     * dispatch opens no window, pipes no script and clears no cache.
     *
     * Mutation: delete the `if:` from any of these, or change the comparison.
     */
    public function test_every_mutating_step_is_gated_on_a_change_being_required(): void
    {
        foreach ([
            'Refuse if production is already in maintenance',
            'Open the maintenance window',
            'Align SESSION_DRIVER',
            'Clear compiled caches',
        ] as $step) {
            $this->assertSame(
                "steps.plan.outputs.change_required == 'true'",
                $this->gate($step),
                "[{$step}] is not gated on a change being required. An already-aligned deployment would be "
                .'interrupted for a change that does not exist.'
            );
        }
    }

    /** W34. The no-op path reports honestly, and does not borrow the change path's language. */
    public function test_the_no_op_report_does_not_claim_anyone_was_signed_out(): void
    {
        $noop = $this->stepBody($this->workflow(), 'Report that no change was required');

        $this->assertSame(
            "steps.plan.outputs.change_required != 'true'",
            $this->gate('Report that no change was required')
        );

        $this->assertStringNotContainsString(
            'signed out',
            $noop,
            'The no-op report claims users were signed out. Nothing happened, and saying otherwise would '
            .'make a harmless run look like a production interruption in the record.'
        );

        $this->assertStringContainsString('not opened', $noop);
        $this->assertStringContainsString('not interrupted', $noop);
        $this->assertStringContainsString('not read for modification, not written', $noop);

        // The change path keeps its own, accurate wording.
        $this->assertStringContainsString(
            'signed out',
            $this->stepBody($this->workflow(), 'Report what was done'),
            'The change report no longer states the accepted consequence.'
        );
    }

    // -----------------------------------------------------------------

    /** W35. NO AUTOMATIC ROLLBACK, AND THE EXPLICIT ONE IS NAMED. */
    public function test_it_does_not_roll_back_automatically_and_names_the_explicit_rollback(): void
    {
        $workflow = $this->workflow();

        $invocations = preg_match_all('/< deployment\/ensure-session-driver\.sh/', $workflow);

        $this->assertSame(
            1,
            $invocations,
            'The driver script is invoked more than once. A failure path that mutates production a '
            .'second time is not a rollback, it is a second unattended change.'
        );

        $this->assertStringContainsString(
            'dispatch this workflow from main with target [$starting]',
            $this->stepBody($workflow, self::RECOVERY_STEP),
            'The failure message does not tell the operator how to roll back.'
        );
    }

    /** W36. THE FILE SAYS WHAT DISPATCHING IT COSTS. */
    public function test_the_workflow_states_its_consequence_and_its_authority_at_the_top(): void
    {
        $header = substr($this->read(self::WORKFLOW), 0, 1200);

        $this->assertStringContainsString('SIGNS EVERY USER OUT', $header);
        $this->assertStringContainsString('Product Owner GO', $header);
        $this->assertStringContainsString('PHASE-1-CLOSEOUT-WS-1-SESSION-DRIVER-RUNBOOK.md', $header);
    }

    // =================================================================
    // Running a step's shell for real, against a stubbed ssh.
    // =================================================================

    /**
     * Execute the `plan` step's own shell.
     *
     * @return array{exit: int, output: string, outputs: array<string, string>}
     */
    private function runPlan(string $currentDriver, string $target): array
    {
        $script = $this->stepScript($this->workflow(), self::PLAN_STEP);

        $result = $this->runStep($script, [
            'TARGET_DRIVER' => $target,
            'STUB_DRIVER' => $currentDriver,
            'STUB_STATE_VALUE' => 'LIVE',
        ]);

        return $result + ['outputs' => $this->githubOutputs()];
    }

    /**
     * Execute the failure-recovery step's own shell.
     *
     * The runner substitutes `${{ steps.… }}` before the shell ever sees it, so
     * the two values that come from earlier steps are substituted here the same
     * way. Everything else is the step verbatim.
     *
     * @return array{exit: int, output: string, commands: list<string>, state: string}
     */
    private function runRecovery(
        string $starting,
        string $effectiveDriver,
        string $maintenanceState,
        string $weOpened,
        bool $downFails = false,
        ?string $workflow = null,
    ): array {
        $script = $this->stepScript($workflow ?? $this->workflow(), self::RECOVERY_STEP, [
            '${{ steps.plan.outputs.starting }}' => $starting,
            '${{ steps.maintenance.outputs.opened }}' => $weOpened,
        ]);

        $result = $this->runStep($script, [
            'STUB_DRIVER' => $effectiveDriver,
            'STUB_STATE_VALUE' => $maintenanceState,
            'STUB_DOWN_FAILS' => $downFails ? '1' : '0',
        ]);

        return $result + [
            'commands' => $this->remoteCommands(),
            'state' => trim((string) @file_get_contents($this->dir.'/state')),
        ];
    }

    /**
     * @param  array<string, string>  $env
     * @return array{exit: int, output: string}
     */
    private function runStep(string $script, array $env): array
    {
        $this->writeSshStub();

        file_put_contents($this->dir.'/state', ($env['STUB_STATE_VALUE'] ?? 'LIVE')."\n");
        file_put_contents($this->dir.'/commands', '');
        file_put_contents($this->dir.'/github_output', '');
        file_put_contents($this->dir.'/summary', '');

        $path = $this->dir.'/step.sh';
        file_put_contents($path, $script);

        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];

        // The runner uses bash, so the step is exercised on the shell it will
        // actually run on - `set -o pipefail` is not POSIX.
        $process = proc_open(['bash', $path], $descriptors, $pipes, $this->dir, [
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
        ] + $env);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return ['exit' => proc_close($process), 'output' => $stdout.$stderr];
    }

    /**
     * A stand-in for `ssh` that answers the four things these steps ask the
     * server, and records every remote command it was given.
     *
     * The remote command is always the last argument, which is how both steps
     * invoke ssh.
     */
    private function writeSshStub(): void
    {
        file_put_contents($this->dir.'/bin/ssh', <<<'STUB'
            #!/bin/sh
            for a in "$@"; do last="$a"; done
            printf '%s\n' "$last" >> "$STUB_LOG"

            case "$last" in
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
                printf '%s\n' "$STUB_DRIVER"
                exit 0
                ;;
              *"optimize:clear"*)
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

        chmod($this->dir.'/bin/ssh', 0o755);
    }

    /**
     * The remote commands the step sent, reduced to the artisan call in each.
     *
     * @return list<string>
     */
    private function remoteCommands(): array
    {
        $commands = [];

        foreach (file($this->dir.'/commands', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
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

    /** The workflow with one exact line removed, for the paired mutation cases. */
    private function workflowWithout(string $line): string
    {
        $workflow = $this->workflow();

        $this->assertSame(
            1,
            substr_count($workflow, $line),
            'The mutation target ['.$line.'] does not appear exactly once, so removing it would be a '
            .'no-op and the paired case would pass for the wrong reason.'
        );

        return str_replace($line, ':', $workflow);
    }

    private function withoutComments(string $yaml): string
    {
        return (string) preg_replace('/^\s*#.*$/m', '', $yaml);
    }

    /**
     * Isolate one mapping block by indentation.
     *
     * Asking "is there a push trigger" of the whole file is answered by the
     * word appearing in a step name. Asking it of the `on:` block alone is
     * answered by the trigger block.
     */
    private function block(string $yaml, string $key, int $depth = 0): string
    {
        $indent = str_repeat(' ', $depth);
        $pattern = '/^'.preg_quote($indent, '/').preg_quote($key, '/').':.*$/m';

        $this->assertMatchesRegularExpression($pattern, $yaml, "There is no [{$key}:] block to examine.");

        preg_match($pattern, $yaml, $match, PREG_OFFSET_CAPTURE);

        $start = $match[0][1];
        $lines = explode("\n", substr($yaml, $start));
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

        return $next === false
            ? substr($workflow, $start)
            : substr($workflow, $start, $next - $start);
    }

    /** A step's `if:` expression, verbatim. */
    private function gate(string $name): string
    {
        $body = $this->stepBody($this->workflow(), $name);

        $this->assertSame(
            1,
            preg_match('/^        if: (.+)$/m', $body, $match),
            "[{$name}] has no `if:` condition at all."
        );

        return trim($match[1]);
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
