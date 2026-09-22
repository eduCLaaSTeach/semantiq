<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * CL-10's production mechanism, exercised by RUNNING THE REAL SCRIPT.
 *
 * Not assertions about the script's text, and not assertions about the
 * workflow that calls it: each case builds a throwaway deployment directory
 * with a fixture .env, runs deployment/ensure-session-driver.sh against it, and
 * inspects what actually happened to the file. A test that grepped the script
 * would keep passing while the logic inside it was wrong, which is the failure
 * this project keeps finding.
 *
 * SEVERAL CASES MUTATE A COPY OF THE SCRIPT ON PURPOSE. A guard that cannot be
 * shown to fail is not evidence of anything, so the cases that matter most run
 * twice: once against a copy whose rewrite step has been deliberately broken -
 * the guard must refuse - and once against a copy that ALSO has the guard
 * removed, where the damage must actually land. The second half is what proves
 * the first half was the guard doing the work rather than something incidental.
 *
 * The fixture .env deliberately contains a client secret and an APP_KEY. Those
 * are the values a careless rewrite would lose or leak, so they are the values
 * the assertions watch.
 */
final class SessionDriverDeploymentTest extends TestCase
{
    private const SECRET = 'THE-CLIENT-SECRET-THAT-MUST-SURVIVE';

    private const APP_KEY = 'base64:Zm9vYmFyYmF6cXV4MTIzNDU2Nzg5MGFiY2RlZmdoaWo=';

    private string $dir;

    /** Copies of the script mutated by a case; removed afterwards. */
    private array $mutants = [];

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/sessdriver-'.bin2hex(random_bytes(6));
        mkdir($this->dir, 0o777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir.'/{,.}*', GLOB_BRACE) ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        @rmdir($this->dir);

        foreach ($this->mutants as $mutant) {
            @unlink($mutant);
        }
    }

    // -----------------------------------------------------------------
    // The change itself, in both directions.
    // -----------------------------------------------------------------

    /** S1. file -> database. */
    public function test_it_changes_the_driver_from_file_to_database(): void
    {
        $before = $this->givenEnv('file');

        $result = $this->runScript('database');

        $this->assertSame(0, $result['exit'], $result['stderr']);
        $this->assertStringContainsString("SESSION_DRIVER=database\n", $this->env());
        $this->assertSame(
            str_replace('SESSION_DRIVER=file', 'SESSION_DRIVER=database', $before),
            $this->env(),
            'Something other than the one line moved.'
        );
        $this->assertStringContainsString('updated to database', $result['stdout']);
    }

    /**
     * S2. database -> file, USING THE SAME SCRIPT.
     *
     * This is the whole point of the argument shape. A rollback made of
     * different code is a path nobody has ever run, exercised for the first
     * time under pressure.
     */
    public function test_the_same_script_rolls_the_driver_back_to_file(): void
    {
        $before = $this->givenEnv('database');

        $result = $this->runScript('file');

        $this->assertSame(0, $result['exit'], $result['stderr']);
        $this->assertSame(
            str_replace('SESSION_DRIVER=database', 'SESSION_DRIVER=file', $before),
            $this->env()
        );
        $this->assertStringContainsString('updated to file', $result['stdout']);
    }

    /** S3. Forward then back returns the file to exactly its original bytes. */
    public function test_forward_then_rollback_restores_the_original_file_exactly(): void
    {
        $before = $this->givenEnv('file');

        $this->runScript('database');
        $this->runScript('file');

        $this->assertSame($before, $this->env());
    }

    /** S4. The values a careless rewrite would lose. */
    public function test_every_other_value_and_comment_survives(): void
    {
        $this->givenEnv('file');

        $this->runScript('database');

        $after = $this->env();

        $this->assertStringContainsString(self::SECRET, $after);
        $this->assertStringContainsString(self::APP_KEY, $after);
        $this->assertStringContainsString('# a comment nobody should lose', $after);
        $this->assertStringContainsString('AN_UNRECOGNISED_KEY=keep me', $after);
        $this->assertStringContainsString('SESSION_LIFETIME=60', $after);
        $this->assertStringContainsString('DB_PASSWORD=not-the-one-being-changed', $after);
    }

    /** S5. No value from .env reaches either output stream. */
    public function test_the_script_prints_no_env_value(): void
    {
        $this->givenEnv('file');

        $result = $this->runScript('database');

        foreach ([$result['stdout'], $result['stderr']] as $stream) {
            $this->assertStringNotContainsString(self::SECRET, $stream);
            $this->assertStringNotContainsString(self::APP_KEY, $stream);
            $this->assertStringNotContainsString('AN_UNRECOGNISED_KEY', $stream);
            $this->assertStringNotContainsString('DB_PASSWORD', $stream);
        }
    }

    /** S6. And nothing leaks on a refusal either, which is when messages get chatty. */
    public function test_a_refusal_prints_no_env_value(): void
    {
        $this->givenEnv('redis');

        $result = $this->runScript('database');

        $this->assertNotSame(0, $result['exit']);

        foreach ([$result['stdout'], $result['stderr']] as $stream) {
            $this->assertStringNotContainsString(self::SECRET, $stream);
            $this->assertStringNotContainsString(self::APP_KEY, $stream);
            $this->assertStringNotContainsString('redis', $stream, 'The refusal echoed the value it refused.');
        }
    }

    // -----------------------------------------------------------------
    // R-7. Idempotence, in both directions.
    // -----------------------------------------------------------------

    /** S7. Already `file`, asked for `file`: nothing is written. */
    public function test_it_is_idempotent_when_already_on_file(): void
    {
        $before = $this->givenEnv('file');
        $mtime = filemtime($this->dir.'/.env');

        $result = $this->runScript('file');

        clearstatcache();

        $this->assertSame(0, $result['exit'], $result['stderr']);
        $this->assertSame($before, $this->env());
        $this->assertSame($mtime, filemtime($this->dir.'/.env'), 'The file was rewritten to the same value.');
        $this->assertStringContainsString('already file', $result['stdout']);
    }

    /** S8. Already `database`, asked for `database`: nothing is written. */
    public function test_it_is_idempotent_when_already_on_database(): void
    {
        $before = $this->givenEnv('database');
        $mtime = filemtime($this->dir.'/.env');

        $result = $this->runScript('database');

        clearstatcache();

        $this->assertSame(0, $result['exit'], $result['stderr']);
        $this->assertSame($before, $this->env());
        $this->assertSame($mtime, filemtime($this->dir.'/.env'));
        $this->assertStringContainsString('already database', $result['stdout']);
    }

    // -----------------------------------------------------------------
    // R-1 .. R-6. Refusals. Every one leaves .env exactly as it was.
    // -----------------------------------------------------------------

    /** S9. R-1. A missing .env is a hard stop, and the script never creates one. */
    public function test_a_missing_env_is_refused_and_never_created(): void
    {
        $result = $this->runScript('database');

        $this->assertNotSame(0, $result['exit']);
        $this->assertFileDoesNotExist($this->dir.'/.env');
        $this->assertStringContainsString('D-05', $result['stderr']);
    }

    /**
     * S10. R-3. A MISSING key is refused, NOT appended.
     *
     * ensure-session-lifetime.sh appends its key when absent, which is right
     * for a policy value with a known correct answer. It is wrong here: an
     * .env with no SESSION_DRIVER is a deployment this script does not
     * understand, and writing one would be inventing production configuration
     * from a command-line argument.
     */
    public function test_a_missing_session_driver_entry_is_refused_rather_than_appended(): void
    {
        $before = $this->givenEnv(null);

        $result = $this->runScript('database');

        $this->assertNotSame(0, $result['exit']);
        $this->assertSame($before, $this->env(), 'The script appended a key it should have refused.');
        $this->assertStringNotContainsString('SESSION_DRIVER', $this->env());
        $this->assertStringContainsString('Refusing to invent', $result['stderr']);
    }

    /** S11. R-2/R-4. Two entries: which one is live is not a thing to guess. */
    public function test_a_duplicate_session_driver_entry_is_refused(): void
    {
        $before = $this->givenEnv('file', duplicate: true);

        $result = $this->runScript('database');

        $this->assertNotSame(0, $result['exit']);
        $this->assertSame($before, $this->env());
        $this->assertStringContainsString('more than one', $result['stderr']);
    }

    /** S12. R-5. An unrecognised CURRENT value is refused, not overwritten. */
    public function test_an_unexpected_current_value_is_refused(): void
    {
        $before = $this->givenEnv('memcached');

        $result = $this->runScript('database');

        $this->assertNotSame(0, $result['exit']);
        $this->assertSame($before, $this->env(), 'The script overwrote a state nobody described.');
        $this->assertStringContainsString('Refusing to overwrite an unexpected state', $result['stderr']);
    }

    /** S13. R-6. An unrecognised TARGET is refused. */
    public function test_an_unrecognised_target_is_refused(): void
    {
        $before = $this->givenEnv('file');

        foreach (['redis', 'cookie', '', 'FILE', 'file ', 'database;rm -rf /'] as $target) {
            $result = $this->runScript($target);

            $this->assertNotSame(0, $result['exit'], "[{$target}] was accepted as a session driver.");
            $this->assertSame($before, $this->env());
        }
    }

    /**
     * S14. THIS IS NOT AN .env EDITOR.
     *
     * The argument shape is the guarantee: a deployment path and a driver name.
     * There is nowhere to put a key, so there is nothing to abuse. Anything
     * that is not exactly two arguments is refused before the file is opened.
     */
    public function test_it_takes_exactly_two_arguments_and_no_key_name(): void
    {
        $before = $this->givenEnv('file');

        $oneArgument = $this->execute([$this->dir]);
        $this->assertNotSame(0, $oneArgument['exit']);
        $this->assertStringContainsString('usage:', $oneArgument['stderr']);

        $threeArguments = $this->execute([$this->dir, 'database', 'APP_KEY']);
        $this->assertNotSame(0, $threeArguments['exit'], 'A third argument was accepted.');
        $this->assertStringContainsString('usage:', $threeArguments['stderr']);

        // The shape somebody would reach for if they thought this were an editor.
        $asAnEditor = $this->execute([$this->dir, 'SESSION_DRIVER=database']);
        $this->assertNotSame(0, $asAnEditor['exit']);

        $this->assertSame($before, $this->env());
    }

    /** S15. The one key it writes is the only key named in it. */
    public function test_the_script_names_no_env_key_other_than_session_driver(): void
    {
        $script = $this->withoutComments($this->scriptSource());

        preg_match_all('/\b([A-Z][A-Z0-9_]{2,})=/', $script, $matches);

        $keys = array_values(array_unique(array_diff($matches[1], ['SESSION_DRIVER'])));

        $this->assertSame(
            [],
            $keys,
            'The executable part of the script names another .env key: '.implode(', ', $keys).
            '. This is a SESSION_DRIVER script, not an .env editor.'
        );
    }

    // -----------------------------------------------------------------
    // V-1 .. V-4, each proven by breaking it.
    // -----------------------------------------------------------------

    /**
     * S16. V-3. A REWRITE THAT ALSO MANGLES AN UNRELATED LINE IS CAUGHT.
     *
     * The line count is unchanged, so V-2 cannot see this. Only the normalised
     * comparison can, and this is exactly the case the existing
     * ensure-session-lifetime.sh does NOT cover: its single pre-rename check is
     * that the target line exists, which a rewrite that destroyed the client
     * secret would pass.
     *
     * Mutation, run below: remove V-3 and the secret is actually destroyed.
     */
    public function test_a_rewrite_that_changes_unrelated_content_is_refused_before_the_rename(): void
    {
        $before = $this->givenEnv('file');

        $result = $this->execute([$this->dir, 'database'], script: $this->mutantThatMangesTheSecret());

        $this->assertNotSame(0, $result['exit']);
        $this->assertSame($before, $this->env(), '.env was replaced despite unrelated content having changed.');
        $this->assertStringContainsString(self::SECRET, $this->env());
        $this->assertStringContainsString('content other than SESSION_DRIVER', $result['stderr']);
        $this->assertSame([], $this->strayFiles());
    }

    /** S17. And V-3 is what catches it: without that check, the secret is lost. */
    public function test_without_v3_the_same_rewrite_destroys_the_client_secret(): void
    {
        $this->givenEnv('file');

        $result = $this->execute([$this->dir, 'database'], script: $this->mutantThatMangesTheSecret(withoutV3: true));

        $this->assertSame(0, $result['exit'], 'The mutation was caught by something else, so S16 proves nothing about V-3.');
        $this->assertStringNotContainsString(
            self::SECRET,
            $this->env(),
            'The secret survived without V-3, so V-3 is not the check doing the work.'
        );
    }

    /**
     * S18. V-2. A rewrite that changes the LINE COUNT is caught.
     *
     * V-3 would also catch this one, so the mutant has V-3 removed: what is
     * being proven here is that V-2 alone is sufficient, which matters because
     * V-2 is the check ensure-session-lifetime.sh's comment promises and its
     * code never performs.
     */
    public function test_a_rewrite_that_changes_the_line_count_is_refused_before_the_rename(): void
    {
        $before = $this->givenEnv('file');

        $result = $this->execute([$this->dir, 'database'], script: $this->mutantThatAppendsALine(withoutV3: true));

        $this->assertNotSame(0, $result['exit']);
        $this->assertSame($before, $this->env());
        $this->assertStringContainsString('number of lines', $result['stderr']);
    }

    /** S19. And V-2 is what catches it: without it, the extra line lands. */
    public function test_without_v2_the_same_rewrite_adds_a_line_to_env(): void
    {
        $this->givenEnv('file');

        $result = $this->execute(
            [$this->dir, 'database'],
            script: $this->mutantThatAppendsALine(withoutV2: true, withoutV3: true)
        );

        $this->assertSame(0, $result['exit'], 'Something else caught it, so S18 proves nothing about V-2.');
        $this->assertStringContainsString('AN_APPENDED_LINE=1', $this->env());
    }

    /**
     * S20. V-4. A MODE MISMATCH ABORTS BEFORE .env IS REPLACED.
     *
     * This is the correction that matters most, and the one place this script
     * deliberately differs from ensure-session-lifetime.sh. That script chowns
     * with `|| true`, renames, and only then compares - so a failed restoration
     * is found once .env has already been replaced. Recovery, not prevention.
     *
     * The mutant makes the temporary file 0644 instead of the original's 0600.
     * The pre-rename check must abort with .env untouched AND still 0600.
     */
    public function test_a_mode_mismatch_aborts_before_env_is_replaced(): void
    {
        $before = $this->givenEnv('file');

        $result = $this->execute([$this->dir, 'database'], script: $this->mutantThatWidensTheMode());

        clearstatcache();

        $this->assertNotSame(0, $result['exit']);
        $this->assertSame($before, $this->env(), '.env was replaced despite the mode mismatch.');
        $this->assertSame(
            '600',
            decoct(fileperms($this->dir.'/.env') & 0o777),
            'The original .env mode changed even though the run aborted.'
        );
        $this->assertStringContainsString('does not carry the original', $result['stderr']);
        $this->assertSame([], $this->strayFiles());
    }

    /**
     * S21. WITHOUT the pre-rename check, .env ends up 0644 - the exact weakness
     * recorded against ensure-session-lifetime.sh in the runbook §5.2(c).
     *
     * The run still fails, because the after-the-fact check fires. But it fails
     * having ALREADY widened the permissions on the file holding every secret
     * the application has, and telling the operator to go and fix it. That
     * difference is the whole reason V-4 exists.
     */
    public function test_without_the_pre_rename_check_the_replacement_widens_the_mode(): void
    {
        $this->givenEnv('file');

        $result = $this->execute([$this->dir, 'database'], script: $this->mutantThatWidensTheMode(withoutV4: true));

        clearstatcache();

        $this->assertNotSame(0, $result['exit'], 'Neither check fired, which would be worse than either.');
        $this->assertSame(
            '644',
            decoct(fileperms($this->dir.'/.env') & 0o777),
            'The mode did not widen, so S20 proves nothing about the PRE-rename check.'
        );
        $this->assertStringContainsString('changed during the update', $result['stderr']);
    }

    /** S22. V-1. The target line must actually be what was asked for. */
    public function test_a_rewrite_that_writes_the_wrong_value_is_refused(): void
    {
        $before = $this->givenEnv('file');

        $result = $this->execute([$this->dir, 'database'], script: $this->mutantThatWritesTheWrongValue());

        $this->assertNotSame(0, $result['exit']);
        $this->assertSame($before, $this->env());
        $this->assertStringContainsString('exactly one correct SESSION_DRIVER line', $result['stderr']);
    }

    // -----------------------------------------------------------------
    // Mode, ownership, and what is left behind.
    // -----------------------------------------------------------------

    /** S23. The rename swaps the inode, so the mode has to be carried across it. */
    public function test_the_file_mode_survives_the_replacement(): void
    {
        $this->givenEnv('file');
        chmod($this->dir.'/.env', 0o640);
        clearstatcache();

        $result = $this->runScript('database');

        clearstatcache();

        $this->assertSame(0, $result['exit'], $result['stderr']);
        $this->assertSame('640', decoct(fileperms($this->dir.'/.env') & 0o777));
    }

    /** S24. Ownership survives too - here trivially, since the test owns the file. */
    public function test_the_owner_and_group_survive_the_replacement(): void
    {
        $this->givenEnv('file');
        $before = [fileowner($this->dir.'/.env'), filegroup($this->dir.'/.env')];

        $this->runScript('database');
        clearstatcache();

        $this->assertSame($before, [fileowner($this->dir.'/.env'), filegroup($this->dir.'/.env')]);
    }

    /** S25. Nothing is left behind on success, and no backup is ever written. */
    public function test_a_successful_run_leaves_no_temporary_and_no_backup(): void
    {
        $this->givenEnv('file');

        $this->runScript('database');

        $this->assertSame([], $this->strayFiles(), 'A file was left behind after a successful run.');

        foreach (['.env.bak', '.env.backup', '.env~', '.env.orig', '.env.save'] as $backup) {
            $this->assertFileDoesNotExist(
                $this->dir.'/'.$backup,
                'A backup of .env is a second copy of every secret in it.'
            );
        }
    }

    /** S26. A catchable failure after the temporary file exists removes it. */
    public function test_a_catchable_failure_removes_the_temporary_file(): void
    {
        $before = $this->givenEnv('file');

        // Fails at V-3, which is after the temporary file has been written.
        $result = $this->execute([$this->dir, 'database'], script: $this->mutantThatMangesTheSecret());

        $this->assertNotSame(0, $result['exit']);
        $this->assertSame($before, $this->env());
        $this->assertSame(
            [],
            $this->strayFiles(),
            'A temporary file carrying every secret in .env survived a failure.'
        );
    }

    /**
     * S27. KILLED part-way through the write.
     *
     * A file-size limit of zero creates the temporary file and then kills the
     * write, which is the only way to observe that file from outside the
     * process. This is the injection that found a real defect in the sibling
     * script: a complete copy of .env - APP_KEY, client secret and all - left
     * sitting on the host after a SIGXFSZ the trap did not cover.
     *
     * Mutation: remove the trap. The copy is then left behind.
     */
    public function test_a_killed_run_leaves_no_copy_of_env_behind(): void
    {
        $before = $this->givenEnv('file');

        $result = $this->execute([$this->dir, 'database'], fileSizeLimit: true);

        $this->assertNotSame(0, $result['exit'], 'The run was not actually killed, so this proves nothing.');
        $this->assertSame($before, $this->env(), '.env was damaged by a killed run.');
        $this->assertSame(
            [],
            $this->strayFiles(),
            'A killed run left a complete copy of .env - APP_KEY and client secret included - on the host.'
        );
    }

    /** S28. And the trap is what does it: without it, the copy survives. */
    public function test_without_the_trap_a_killed_run_leaves_a_copy_behind(): void
    {
        $this->givenEnv('file');

        $this->execute([$this->dir, 'database'], script: $this->mutantWithoutTheTrap(), fileSizeLimit: true);

        $this->assertNotSame(
            [],
            $this->strayFiles(),
            'Nothing was left behind even without the trap, so S27 proves nothing about the trap.'
        );
    }

    /**
     * S29. A copy left by something UNTRAPPABLE is swept by the next run.
     *
     * SIGKILL can never be trapped, so the trap alone bounds nothing. The sweep
     * is what turns "a copy of every secret, forever" into "a copy until the
     * next run".
     */
    public function test_a_stale_copy_from_an_earlier_run_is_swept(): void
    {
        $this->givenEnv('file');

        // What a SIGKILL would have left: unsweepable by any trap.
        file_put_contents($this->dir.'/.env.semantiq-session-driver.99999', $this->env());

        $result = $this->runScript('database');

        $this->assertSame(0, $result['exit'], $result['stderr']);
        $this->assertSame([], $this->strayFiles(), 'A stale copy from an earlier run survived.');
    }

    /** S30. And the sweep is what does it. */
    public function test_without_the_sweep_a_stale_copy_survives(): void
    {
        $this->givenEnv('file');
        file_put_contents($this->dir.'/.env.semantiq-session-driver.99999', $this->env());

        $this->execute([$this->dir, 'database'], script: $this->mutantWithoutTheSweep());

        $this->assertNotSame(
            [],
            $this->strayFiles(),
            'The stale copy went away without the sweep, so S29 proves nothing about the sweep.'
        );
    }

    /**
     * S31. A PRESENCE GUARD, and labelled as one.
     *
     * The window in which a temporary file could be world-readable is a race:
     * it cannot be observed reliably from outside the process, and a test that
     * claimed to observe it would be exactly the evidence-shaped output this
     * project keeps catching. So this asserts the ORDERING that makes the race
     * impossible - umask before the file is created, not chmod after - and S27
     * is the behavioural case.
     *
     * COMMENTS ARE STRIPPED FIRST. The script explains in prose why the umask
     * is where it is; a guard that matched the prose would sail through a
     * mutation that moved the executable line below the write.
     */
    public function test_the_umask_is_set_before_the_temporary_file_is_created(): void
    {
        $script = $this->withoutComments($this->scriptSource());

        $umask = strpos($script, 'umask 077');
        $creates = strpos($script, '> "$tmp"');

        $this->assertNotFalse($umask, 'The script never restricts the temporary file mode.');
        $this->assertNotFalse($creates, 'The script does not create a temporary file, so this proves nothing.');

        $this->assertLessThan(
            $creates,
            $umask,
            'The temporary file is created before the umask is set, so it exists - holding every '
            .'secret in .env - at the default mode first.'
        );
    }

    /** S32. The temporary file is a sibling of .env: a rename across filesystems is not atomic. */
    public function test_the_temporary_file_is_created_in_the_same_directory(): void
    {
        $script = $this->withoutComments($this->scriptSource());

        $this->assertMatchesRegularExpression(
            '/tmp="\.env\.semantiq-session-driver\.\$\$"/',
            $script,
            'The temporary file must be a sibling of .env in the deployment directory.'
        );

        $this->assertStringNotContainsString(
            'mktemp',
            $script,
            'mktemp defaults to a temporary filesystem, where the rename onto .env would not be atomic.'
        );
    }

    // -----------------------------------------------------------------
    // Fixtures and the runner.
    // -----------------------------------------------------------------

    private function givenEnv(?string $driver, bool $duplicate = false): string
    {
        $lines = [
            'APP_ENV=production',
            'APP_KEY='.self::APP_KEY,
            '# a comment nobody should lose',
            'DB_PASSWORD=not-the-one-being-changed',
            'SESSION_LIFETIME=60',
        ];

        if ($driver !== null) {
            $lines[] = 'SESSION_DRIVER='.$driver;

            if ($duplicate) {
                $lines[] = 'SESSION_DRIVER='.$driver;
            }
        }

        $lines[] = 'MICROSOFT_CLIENT_SECRET='.self::SECRET;
        $lines[] = 'AN_UNRECOGNISED_KEY=keep me';

        $contents = implode("\n", $lines)."\n";

        file_put_contents($this->dir.'/.env', $contents);
        chmod($this->dir.'/.env', 0o600);

        return $contents;
    }

    private function env(): string
    {
        return (string) file_get_contents($this->dir.'/.env');
    }

    /** @return list<string> */
    private function strayFiles(): array
    {
        $stray = [];

        foreach (scandir($this->dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === '.env') {
                continue;
            }

            $stray[] = $entry;
        }

        return $stray;
    }

    private function scriptPath(): string
    {
        return dirname(__DIR__, 2).'/deployment/ensure-session-driver.sh';
    }

    private function scriptSource(): string
    {
        return (string) file_get_contents($this->scriptPath());
    }

    private function withoutComments(string $script): string
    {
        return (string) preg_replace('/^\s*#.*$/m', '', $script);
    }

    // -----------------------------------------------------------------
    // The mutants. Each one breaks exactly one thing, on a COPY.
    // -----------------------------------------------------------------

    private const REWRITE = 'sed "s|^SESSION_DRIVER=.*|SESSION_DRIVER=${target}|" .env > "$tmp"';

    private const V2_CHECK = 'if [ "$after_lines" -ne "$before_lines" ]; then';

    private const V3_CHECK = 'if [ "$(normalised_hash "$tmp")" != "$before_hash" ]; then';

    private const V4_CHECK = 'if [ "$tmp_mode" != "$mode" ] || [ "$tmp_owner" != "$owner" ]; then';

    private function mutantThatMangesTheSecret(bool $withoutV3 = false): string
    {
        $script = $this->replaceRewrite(
            'sed -e "s|^SESSION_DRIVER=.*|SESSION_DRIVER=${target}|" '
            .'-e "s|^MICROSOFT_CLIENT_SECRET=.*|MICROSOFT_CLIENT_SECRET=MANGLED|" .env > "$tmp"'
        );

        if ($withoutV3) {
            $script = $this->disable($script, self::V3_CHECK);
        }

        return $this->writeMutant($script);
    }

    private function mutantThatAppendsALine(bool $withoutV2 = false, bool $withoutV3 = false): string
    {
        $script = $this->replaceRewrite(
            '{ sed "s|^SESSION_DRIVER=.*|SESSION_DRIVER=${target}|" .env; '
            .'echo "AN_APPENDED_LINE=1"; } > "$tmp"'
        );

        if ($withoutV2) {
            $script = $this->disable($script, self::V2_CHECK);
        }

        if ($withoutV3) {
            $script = $this->disable($script, self::V3_CHECK);
        }

        return $this->writeMutant($script);
    }

    private function mutantThatWritesTheWrongValue(): string
    {
        return $this->writeMutant($this->replaceRewrite(
            'sed "s|^SESSION_DRIVER=.*|SESSION_DRIVER=array|" .env > "$tmp"'
        ));
    }

    private function mutantThatWidensTheMode(bool $withoutV4 = false): string
    {
        $script = $this->requireOnce($this->scriptSource(), 'chmod "$mode" "$tmp"');
        $script = str_replace('chmod "$mode" "$tmp"', 'chmod 644 "$tmp"', $script);

        if ($withoutV4) {
            $script = $this->disable($script, self::V4_CHECK);
        }

        return $this->writeMutant($script);
    }

    private function mutantWithoutTheTrap(): string
    {
        $trap = 'trap \'rm -f "$tmp"\' EXIT INT TERM HUP QUIT PIPE XFSZ';

        return $this->writeMutant(
            str_replace($trap, ':', $this->requireOnce($this->scriptSource(), $trap))
        );
    }

    private function mutantWithoutTheSweep(): string
    {
        $sweep = 'rm -f .env.semantiq-session-driver.*';

        return $this->writeMutant(
            str_replace($sweep, ':', $this->requireOnce($this->scriptSource(), $sweep))
        );
    }

    private function replaceRewrite(string $replacement): string
    {
        $script = $this->requireOnce($this->scriptSource(), self::REWRITE);

        return str_replace(self::REWRITE, $replacement, $script);
    }

    /**
     * Turn a guard's `if` into one that can never be true.
     *
     * The body is left in place so the mutant stays syntactically whole; only
     * the condition is neutralised.
     */
    private function disable(string $script, string $condition): string
    {
        $this->requireOnce($script, $condition);

        return str_replace($condition, 'if false; then', $script);
    }

    /** A mutation that silently matched nothing would prove nothing. */
    private function requireOnce(string $script, string $needle): string
    {
        $this->assertSame(
            1,
            substr_count($script, $needle),
            'The mutation target ['.$needle.'] does not appear exactly once in the script, so the '
            .'mutation would be a no-op and the case would pass for the wrong reason.'
        );

        return $script;
    }

    private function writeMutant(string $source): string
    {
        $path = $this->dir.'/../mutant-'.bin2hex(random_bytes(6)).'.sh';

        file_put_contents($path, $source);
        chmod($path, 0o700);

        $this->mutants[] = $path;

        return $path;
    }

    // -----------------------------------------------------------------

    /** @return array{exit: int, stdout: string, stderr: string} */
    private function runScript(string $target): array
    {
        return $this->execute([$this->dir, $target]);
    }

    /**
     * @param  list<string>  $arguments
     * @return array{exit: int, stdout: string, stderr: string}
     */
    private function execute(array $arguments, ?string $script = null, bool $fileSizeLimit = false): array
    {
        $script ??= $this->scriptPath();

        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];

        $quoted = implode(' ', array_map(escapeshellarg(...), [$script, ...$arguments]));

        $command = $fileSizeLimit
            // A write that dies part-way through, which is the only way to
            // observe the temporary file from outside the process.
            ? ['sh', '-c', 'ulimit -f 0; sh '.$quoted]
            : ['sh', $script, ...$arguments];

        $process = proc_open($command, $descriptors, $pipes, null, ['PATH' => getenv('PATH')]);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return ['exit' => proc_close($process), 'stdout' => $stdout, 'stderr' => $stderr];
    }
}
