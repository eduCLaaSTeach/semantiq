<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * D-31's production change, exercised by running the real script.
 *
 * Not assertions about the workflow text: each case builds a throwaway
 * deployment directory with a fixture .env, runs
 * deployment/ensure-session-lifetime.sh against it with a stubbed php, and
 * inspects what happened to the file. A test that grepped the YAML would keep
 * passing while the logic inside it was wrong, which is the failure this
 * project keeps finding.
 *
 * The fixture .env deliberately contains a client secret and an APP_KEY. Those
 * are the values a careless rewrite would lose or leak, so they are the values
 * the assertions watch.
 */
final class SessionLifetimeDeploymentTest extends TestCase
{
    private const SECRET = 'THE-CLIENT-SECRET-THAT-MUST-SURVIVE';

    private const APP_KEY = 'base64:Zm9vYmFyYmF6cXV4MTIzNDU2Nzg5MGFiY2RlZmdoaWo=';

    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/sesslife-'.bin2hex(random_bytes(6));
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
    }

    /** D5. One line changes; every other byte survives. */
    public function test_exactly_one_line_changes(): void
    {
        $before = $this->givenEnv('SESSION_LIFETIME=120');

        $result = $this->runScript();

        $this->assertSame(0, $result['exit'], $result['stderr']);

        $after = $this->env();

        $this->assertStringContainsString("SESSION_LIFETIME=60\n", $after);
        $this->assertStringNotContainsString('SESSION_LIFETIME=120', $after);

        $this->assertSame(
            str_replace('SESSION_LIFETIME=120', 'SESSION_LIFETIME=60', $before),
            $after,
            'Something other than the one line moved. Everything else in .env, including APP_KEY '
            .'and the client secret, must survive byte for byte.'
        );
    }

    /** The values a careless rewrite would lose. */
    public function test_every_other_value_survives(): void
    {
        $this->givenEnv('SESSION_LIFETIME=120');

        $this->runScript();

        $after = $this->env();

        $this->assertStringContainsString(self::SECRET, $after);
        $this->assertStringContainsString(self::APP_KEY, $after);
        $this->assertStringContainsString('# a comment nobody should lose', $after);
        $this->assertStringContainsString('AN_UNRECOGNISED_KEY=keep me', $after);
    }

    /** The key may be absent rather than wrong. Then it is appended, and nothing else moves. */
    public function test_a_missing_key_is_appended(): void
    {
        $before = $this->givenEnv(null);

        $result = $this->runScript();

        $this->assertSame(0, $result['exit'], $result['stderr']);
        $this->assertSame($before."SESSION_LIFETIME=60\n", $this->env());
    }

    /** D6. No value from .env reaches the output, on either stream. */
    public function test_the_script_prints_no_env_value(): void
    {
        $this->givenEnv('SESSION_LIFETIME=120');

        $result = $this->runScript();

        foreach ([$result['stdout'], $result['stderr']] as $stream) {
            $this->assertStringNotContainsString(self::SECRET, $stream);
            $this->assertStringNotContainsString(self::APP_KEY, $stream);
            $this->assertStringNotContainsString('AN_UNRECOGNISED_KEY', $stream);
        }
    }

    /** D7. A second run writes nothing and says so. */
    public function test_the_script_is_idempotent(): void
    {
        $this->givenEnv('SESSION_LIFETIME=120');

        $this->runScript();
        $once = $this->env();

        $result = $this->runScript();

        $this->assertSame(0, $result['exit'], $result['stderr']);
        $this->assertSame($once, $this->env(), 'A second run changed the file again.');
        $this->assertStringContainsString('already matches', $result['stdout']);
    }

    /**
     * D9. The rename swaps the inode, so the mode has to be carried across it.
     *
     * Mutation: drop the chmod before the rename. The replacement then carries
     * the temporary file's 0600 - or, without the umask, 0644 - instead of the
     * original's.
     */
    public function test_the_file_mode_survives_the_replacement(): void
    {
        $this->givenEnv('SESSION_LIFETIME=120');
        chmod($this->dir.'/.env', 0o640);
        clearstatcache();

        $result = $this->runScript();

        $this->assertSame(0, $result['exit'], $result['stderr']);
        clearstatcache();

        $this->assertSame(
            '640',
            decoct(fileperms($this->dir.'/.env') & 0o777),
            'The mode changed. A script written to protect .env must not be the thing that '
            .'widens it.'
        );
    }

    /** D10. Ownership survives too - here trivially, since the test owns the file. */
    public function test_the_owner_survives_the_replacement(): void
    {
        $this->givenEnv('SESSION_LIFETIME=120');
        $before = [fileowner($this->dir.'/.env'), filegroup($this->dir.'/.env')];

        $this->runScript();
        clearstatcache();

        $this->assertSame($before, [fileowner($this->dir.'/.env'), filegroup($this->dir.'/.env')]);
    }

    /**
     * D11. Nothing is left behind - on success, or on a failure part-way.
     *
     * The failure is injected by making the approved value unreadable AFTER the
     * temporary file would have been written, which is the state that matters:
     * a temp file carrying every secret in .env, abandoned on the host.
     */
    public function test_no_temporary_or_backup_file_remains(): void
    {
        $this->givenEnv('SESSION_LIFETIME=120');

        $this->runScript();

        $this->assertSame([], $this->strayFiles(), 'A file was left behind after a successful run.');

        // Now a run that fails before it can finish.
        $this->givenEnv('SESSION_LIFETIME=120');
        $before = $this->env();

        $result = $this->runScript(brokenPhp: true);

        $this->assertNotSame(0, $result['exit']);
        $this->assertSame($before, $this->env(), '.env changed on a failed run.');
        $this->assertSame([], $this->strayFiles(), 'A temporary file carrying every secret survived a failure.');
    }

    /**
     * D11, the case that matters: KILLED part-way through the write.
     *
     * The failure above is injected before the temporary file exists, so it says
     * nothing about the file this guard is for. This one kills the script while
     * it is writing, with a file-size limit of zero, so the temporary file is
     * created and the write then dies.
     *
     * That injection found a real defect. The trap covered EXIT INT TERM HUP,
     * the script was killed by SIGXFSZ, and a complete copy of .env - APP_KEY,
     * client secret and all - was left sitting on the host. The trap now covers
     * more endings, and because SIGKILL can never be trapped at all, the script
     * also sweeps anything a previous run left before writing a new one.
     *
     * Mutation: remove the trap, or the sweep. Either leaves the copy behind.
     */
    public function test_a_killed_run_leaves_no_copy_of_env_behind(): void
    {
        $before = $this->givenEnv('SESSION_LIFETIME=120');

        $result = $this->runScript(fileSizeLimit: true);

        $this->assertNotSame(0, $result['exit'], 'The run was not actually killed, so this proves nothing.');
        $this->assertSame($before, $this->env(), '.env was damaged by a killed run.');
        $this->assertSame(
            [],
            $this->strayFiles(),
            'A killed run left a complete copy of .env - APP_KEY and client secret included - on the host.'
        );
    }

    /** And a copy left by something untrappable is swept by the next run. */
    public function test_a_stale_copy_from_an_earlier_run_is_swept(): void
    {
        $this->givenEnv('SESSION_LIFETIME=120');

        // What a SIGKILL would have left: unsweepable by any trap.
        file_put_contents($this->dir.'/.env.semantiq-session-lifetime.99999', $this->env());

        $result = $this->runScript();

        $this->assertSame(0, $result['exit'], $result['stderr']);
        $this->assertSame([], $this->strayFiles(), 'A stale copy from an earlier run survived.');
    }

    /**
     * D12. A PRESENCE GUARD, and labelled as one.
     *
     * The window in which a temporary file could be world-readable is a race:
     * it cannot be observed reliably from outside the process, and a test that
     * claimed to observe it would be exactly the evidence-shaped output this
     * project keeps catching. So this asserts the ORDERING that makes the race
     * impossible - umask before the file is created, not chmod after - and D11
     * is the behavioural case.
     *
     * Mutation: move the umask below the redirect that creates the file.
     */
    public function test_the_umask_is_set_before_the_temporary_file_is_created(): void
    {
        /*
         * COMMENTS STRIPPED FIRST.
         *
         * The script explains in prose why the umask is set where it is, and the
         * first version of this guard matched that sentence - so a mutation that
         * moved the executable umask below the write sailed through, because the
         * comment above still came first. Found by running the mutation. A guard
         * that reads its own documentation is not a guard.
         */
        $script = (string) preg_replace(
            '/^\s*#.*$/m',
            '',
            (string) file_get_contents(__DIR__.'/../../deployment/ensure-session-lifetime.sh')
        );

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

    /** A missing .env is a hard stop. The script never creates one. */
    public function test_a_missing_env_stops_before_anything_else(): void
    {
        $result = $this->runScript();

        $this->assertNotSame(0, $result['exit']);
        $this->assertFileDoesNotExist($this->dir.'/.env');
        $this->assertStringContainsString('D-05', $result['stderr']);
    }

    /** An unreadable policy is a stop, not a guess. */
    public function test_an_unreadable_policy_refuses_to_touch_env(): void
    {
        $before = $this->givenEnv('SESSION_LIFETIME=120');

        $result = $this->runScript(brokenPhp: true);

        $this->assertNotSame(0, $result['exit']);
        $this->assertSame($before, $this->env());
    }

    private function givenEnv(?string $sessionLine): string
    {
        $lines = [
            'APP_ENV=production',
            'APP_KEY='.self::APP_KEY,
            '# a comment nobody should lose',
            'SESSION_DRIVER=database',
        ];

        if ($sessionLine !== null) {
            $lines[] = $sessionLine;
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
            if ($entry === '.' || $entry === '..' || $entry === '.env' || $entry === 'php-stub') {
                continue;
            }

            $stray[] = $entry;
        }

        return $stray;
    }

    /** @return array{exit: int, stdout: string, stderr: string} */
    private function runScript(bool $brokenPhp = false, bool $fileSizeLimit = false): array
    {
        $stub = $this->dir.'/php-stub';

        file_put_contents($stub, $brokenPhp
            ? "#!/bin/sh\necho 'boom' >&2\nexit 1\n"
            : "#!/bin/sh\nprintf '60'\n");

        chmod($stub, 0o755);

        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];

        $script = __DIR__.'/../../deployment/ensure-session-lifetime.sh';

        $command = $fileSizeLimit
            // A write that dies part-way through, which is the only way to
            // observe the temporary file from outside the process.
            ? ['sh', '-c', 'ulimit -f 0; sh '.escapeshellarg($script).' '.escapeshellarg($this->dir)]
            : ['sh', $script, $this->dir];

        $process = proc_open(
            $command,
            $descriptors,
            $pipes,
            null,
            ['PHP_BIN' => $stub, 'PATH' => getenv('PATH')],
        );

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return ['exit' => proc_close($process), 'stdout' => $stdout, 'stderr' => $stderr];
    }
}
