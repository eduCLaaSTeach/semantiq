<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * The APP_KEY bootstrap, exercised by running the real script.
 *
 * These are not structural assertions about the workflow text. Each case builds
 * a throwaway deployment directory, runs deployment/ensure-app-key.sh against
 * it with a stubbed php binary, and inspects what happened to .env. A test that
 * only grepped the YAML would keep passing if the logic inside it were wrong.
 *
 * The behaviour under test exists because of a deadlock: the server .env is
 * written by hand during D-05 provisioning, a Laravel key cannot be written by
 * hand, and before the first deployment there is no remote artisan to make one.
 * Generating it once, on the INITIAL path, after the application has landed, is
 * the only point at which all three constraints hold.
 */
final class AppKeyBootstrapTest extends TestCase
{
    private const VALID_KEY = 'base64:Zm9vYmFyYmF6cXV4MTIzNDU2Nzg5MGFiY2RlZmdoaWo=';

    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/appkey-'.bin2hex(random_bytes(6));
        mkdir($this->dir.'/vendor', 0o777, true);
    }

    protected function tearDown(): void
    {
        foreach (['vendor/autoload.php', 'artisan', '.env', 'php-stub', 'stub-was-called'] as $f) {
            @unlink($this->dir.'/'.$f);
        }
        @rmdir($this->dir.'/vendor');
        @rmdir($this->dir);
    }

    public function test_initial_with_an_empty_key_generates_one(): void
    {
        $this->givenLaravelIsPresent();
        $this->givenEnv("APP_KEY=\n");

        $result = $this->runScript('INITIAL');

        $this->assertSame(0, $result['exit'], $result['stderr']);
        $this->assertMatchesRegularExpression('/^APP_KEY=base64:.+$/m', $this->env());
        $this->assertFileExists($this->dir.'/stub-was-called', 'key:generate was not invoked.');
    }

    public function test_initial_with_a_valid_key_never_regenerates_it(): void
    {
        $this->givenLaravelIsPresent();
        $this->givenEnv('APP_KEY='.self::VALID_KEY."\n");

        $result = $this->runScript('INITIAL');

        $this->assertSame(0, $result['exit'], $result['stderr']);
        $this->assertStringContainsString(self::VALID_KEY, $this->env(), 'An existing key was altered.');
        $this->assertFileDoesNotExist($this->dir.'/stub-was-called');
    }

    public function test_update_with_a_valid_key_leaves_it_untouched(): void
    {
        $this->givenLaravelIsPresent();
        $this->givenEnv('APP_KEY='.self::VALID_KEY."\n");

        $result = $this->runScript('EXISTING');

        $this->assertSame(0, $result['exit'], $result['stderr']);
        $this->assertStringContainsString(self::VALID_KEY, $this->env());
        $this->assertFileDoesNotExist(
            $this->dir.'/stub-was-called',
            'An UPDATE deployment invoked key:generate. Rotating APP_KEY invalidates every session.'
        );
    }

    public function test_update_with_a_missing_key_fails_the_deployment(): void
    {
        $this->givenLaravelIsPresent();
        $this->givenEnv("APP_KEY=\n");

        $result = $this->runScript('EXISTING');

        $this->assertNotSame(0, $result['exit'], 'An UPDATE deployment continued without an APP_KEY.');
        $this->assertStringContainsString('never generated here', $result['stderr']);
        $this->assertFileDoesNotExist($this->dir.'/stub-was-called');
    }

    public function test_a_malformed_key_is_treated_as_missing_on_update(): void
    {
        $this->givenLaravelIsPresent();
        $this->givenEnv("APP_KEY=base64:\n");

        $this->assertNotSame(0, $this->runScript('EXISTING')['exit']);
    }

    public function test_a_missing_env_stops_before_anything_else(): void
    {
        $this->givenLaravelIsPresent();

        $result = $this->runScript('INITIAL');

        $this->assertNotSame(0, $result['exit']);
        $this->assertStringContainsString('D-05', $result['stderr']);
    }

    public function test_initial_without_laravel_transferred_refuses_to_generate(): void
    {
        $this->givenEnv("APP_KEY=\n");

        $result = $this->runScript('INITIAL');

        $this->assertNotSame(0, $result['exit']);
        $this->assertStringContainsString('Laravel is not present', $result['stderr']);
    }

    /**
     * The key must never reach the workflow log. Suppressing artisan's output is
     * what enforces that, so the stub prints a key deliberately: if the script
     * ever stopped discarding that stream, this test goes red.
     */
    public function test_the_generated_key_never_appears_in_the_output(): void
    {
        $this->givenLaravelIsPresent();
        $this->givenEnv("APP_KEY=\n");

        $result = $this->runScript('INITIAL');

        $generated = [];
        preg_match('/^APP_KEY=(base64:.+)$/m', $this->env(), $generated);
        $this->assertNotEmpty($generated, 'No key was generated, so this test proves nothing.');

        $this->assertStringNotContainsString($generated[1], $result['stdout']);
        $this->assertStringNotContainsString($generated[1], $result['stderr']);
        $this->assertStringNotContainsString('SUPER-SECRET-KEY-MATERIAL', $result['stdout']);
    }

    private function givenLaravelIsPresent(): void
    {
        touch($this->dir.'/artisan');
        touch($this->dir.'/vendor/autoload.php');
    }

    private function givenEnv(string $contents): void
    {
        file_put_contents($this->dir.'/.env', "APP_ENV=production\n".$contents);
    }

    private function env(): string
    {
        return (string) file_get_contents($this->dir.'/.env');
    }

    /** @return array{exit: int, stdout: string, stderr: string} */
    private function runScript(string $state): array
    {
        // Stands in for the server's php. It writes a key the way key:generate
        // does, records that it ran, and prints key-shaped material to both
        // streams so output suppression is genuinely under test.
        $stub = $this->dir.'/php-stub';
        file_put_contents($stub, <<<'STUB'
#!/bin/sh
touch "$(dirname "$0")/stub-was-called"
env_file="$(dirname "$0")/.env"
key="base64:U1VQRVItU0VDUkVULUtFWS1NQVRFUklBTC0xMjM0NTY3OA=="
if grep -q '^APP_KEY=' "$env_file"; then
  sed -i "s|^APP_KEY=.*|APP_KEY=$key|" "$env_file"
else
  printf 'APP_KEY=%s\n' "$key" >> "$env_file"
fi
echo "Application key set successfully. SUPER-SECRET-KEY-MATERIAL $key"
echo "SUPER-SECRET-KEY-MATERIAL $key" >&2
exit 0
STUB);
        chmod($stub, 0o755);

        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open(
            ['sh', __DIR__.'/../../deployment/ensure-app-key.sh', $this->dir, $state],
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
