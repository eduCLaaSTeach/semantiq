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
 * TWO RULES THIS FILE FOLLOWS, because the alternative is a guard that reads
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
 * What this cannot prove is that the workflow WORKS - only CI that dispatched
 * it against production could, and that is precisely what must not happen. The
 * script it calls is proven behaviourally in SessionDriverDeploymentTest.
 */
final class SessionDriverAlignmentWorkflowTest extends TestCase
{
    private const WORKFLOW = '.github/workflows/align-session-driver.yml';

    private const SCRIPT = 'deployment/ensure-session-driver.sh';

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
     *
     * Mutation: add a step to deploy.yml that pipes the script over SSH.
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
     * Free text here would make this an arbitrary-value tool. A third option
     * would make it a driver the deployment has never run.
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
     * The dropdown alone would let a repeat dispatch sign every user out on a
     * stray click. This asserts both halves: the input exists, and a step
     * actually compares it and exits non-zero.
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

    /**
     * W6. THERE IS NO GENERAL ENVIRONMENT EDITOR.
     *
     * No key input, no value input, no key=value pair. The only thing a
     * dispatcher can choose is which of two drivers to run.
     */
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

    /**
     * W7. A HARD REFUSAL UNLESS THE REF IS main.
     *
     * workflow_dispatch can be pointed at any branch. Without this, an
     * unreviewed script on a branch could be piped straight onto the
     * production server.
     *
     * Mutation: delete the comparison, or soften it to a `startsWith`.
     */
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

    /**
     * W9. THE SAME CONCURRENCY GROUP AS THE DEPLOYMENT, AND NEVER CANCELLED.
     *
     * A deployment rsyncing the application while this rewrites .env - or the
     * reverse - produces a state nobody can reason about afterwards. Sharing
     * `cpanel-deploy` makes them queue instead of racing, and
     * cancel-in-progress false means neither is killed mid-mutation.
     *
     * Mutation: give this workflow its own group, or set cancel-in-progress
     * true. Either one reintroduces the race.
     */
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

    /**
     * W11. THE ESTABLISHED ENVIRONMENT AND SECRETS, NOT A SECOND SET.
     *
     * A parallel credentials model is a second thing to rotate, a second thing
     * to audit, and a second thing to forget.
     */
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

        // Every secret it reads must be one the deployment already uses.
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

    /**
     * W13. THE SCRIPT IS CALLED WITH A PATH AND A DRIVER. NOTHING ELSE.
     *
     * And it is piped over stdin rather than copied to the host: deployment/ is
     * excluded from rsync and a permanent copy on the server is one more thing
     * that can be run by something that is not this workflow.
     *
     * Mutation: add a third argument, or scp the script to the host first.
     */
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

    /**
     * W14. IT NEVER RUNS MIGRATIONS.
     *
     * CL-10 is a configuration change. The sessions table migration is already
     * applied and the most recent deployment reports nothing to migrate; a
     * migrate here would turn a reversible one-key change into a schema event
     * inside a maintenance window.
     */
    public function test_it_never_runs_migrations(): void
    {
        $workflow = $this->workflow();

        foreach (['artisan migrate', 'migrate --force', 'migrate:fresh', 'db:wipe', 'db:seed'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $workflow);
        }
    }

    /**
     * W15. IT NEVER TOUCHES IDENTITY, Entra OR APP_KEY.
     *
     * CL-08 is deferred. APP_KEY rotation invalidates every encrypted cookie
     * and session. Neither belongs anywhere near a session driver change.
     */
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
    // Ordering, and what happens when it goes wrong.
    // -----------------------------------------------------------------

    /**
     * W17. THE OPERATIONAL SEQUENCE, IN ORDER.
     *
     * Validate, prove access, establish the starting state - all before the
     * site goes dark. Then maintenance, the change, the cache clear, the
     * verification that the APPLICATION is on the requested driver, health,
     * and only then the site comes back.
     *
     * Mutation: move `Close the maintenance window` above the verification.
     * The site then returns before anything has confirmed it works.
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
            'Establish the starting session driver',
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

    /**
     * W18. THE REPORTED DRIVER IS READ FROM THE APPLICATION, NOT ASSUMED.
     *
     * The script exiting zero says the FILE was written. That the APPLICATION
     * is running the requested driver is a different claim, and it is the one
     * that matters - the same distinction as runbook proof 5 versus proof 6.
     */
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

    /**
     * W19. FAILURE IS HANDLED BY READING THE STATE, NOT BY GUESSING IT.
     *
     * Two different failures need two different responses, and which one
     * happened cannot be inferred from which step failed: the script can
     * replace .env and then fail its own post-rename check. So the recovery
     * step reads the effective driver and compares it against the starting
     * value.
     *
     * Mutation: replace the comparison with an unconditional `artisan up`.
     * A half-finished change would then be exposed to users.
     */
    public function test_the_failure_path_establishes_the_state_before_deciding(): void
    {
        $workflow = $this->workflow();

        $recovery = $this->stepBody($workflow, 'Establish the exact state after a failure');

        $this->assertStringContainsString(
            'if: failure()',
            $recovery,
            'The recovery step is not conditional on failure, so it runs on a successful run too.'
        );

        $this->assertStringContainsString(
            'if [ "$effective" = "$starting" ]; then',
            $recovery,
            'The recovery step does not compare the effective driver against the starting one, so it '
            .'cannot know which side of the mutation the failure landed on.'
        );

        $this->assertStringContainsString(
            'artisan up',
            $recovery,
            'A failure that changed nothing must not leave production dark.'
        );

        $this->assertStringContainsString(
            'LEFT IN MAINTENANCE',
            $recovery,
            'A failure AFTER the driver changed must say that the site was deliberately left in '
            .'maintenance rather than exposed unverified.'
        );
    }

    /**
     * W20. NO AUTOMATIC ROLLBACK, AND THE EXPLICIT ONE IS NAMED.
     *
     * Rolling back automatically would be a second unattended production
     * mutation on a path already known to be misbehaving. The runbook's
     * rollback is this same workflow with target `file`, and the failure
     * message has to say so - the operator reading it is the recovery path.
     *
     * Mutation: add a step that re-runs the script with the starting driver on
     * failure.
     */
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
            $this->stepBody($workflow, 'Establish the exact state after a failure'),
            'The failure message does not tell the operator how to roll back.'
        );
    }

    /**
     * W21. THE FILE SAYS WHAT DISPATCHING IT COSTS.
     *
     * Somebody will open this file in a hurry. The first thing they read must
     * be that it changes production, signs everybody out, and needs a GO.
     */
    public function test_the_workflow_states_its_consequence_and_its_authority_at_the_top(): void
    {
        $header = substr($this->read(self::WORKFLOW), 0, 1200);

        $this->assertStringContainsString('SIGNS EVERY USER OUT', $header);
        $this->assertStringContainsString('Product Owner GO', $header);
        $this->assertStringContainsString('PHASE-1-CLOSEOUT-WS-1-SESSION-DRIVER-RUNBOOK.md', $header);
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
}
