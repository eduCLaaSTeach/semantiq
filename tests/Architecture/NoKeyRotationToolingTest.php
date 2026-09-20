<?php

declare(strict_types=1);

namespace Tests\Architecture;

use App\Modules\Platform\Setup\Secrets\IntegrationSecretStore;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;

/**
 * THE APP_KEY RESTRICTION IS OPERATIONAL, AND NOTHING PRETENDS OTHERWISE.
 *
 * P1-10 introduces application-level encryption; nothing in SemantIQ was
 * encrypted before it. The Product Owner's ruling for Release 1:
 *
 *   - rotation is UNSUPPORTED while encrypted integration secrets exist,
 *     unless the old key is available to an approved re-encryption procedure;
 *   - NO ROTATION TOOLING IS BUILT NOW - that would be pre-building;
 *   - `key_version` records WHICH key encrypted a row. IT DOES NOT MAKE
 *     ROTATION SAFE BY ITSELF. It cannot decrypt a row whose key is gone, and
 *     a version column on an undecryptable ciphertext tells you accurately
 *     which key you no longer have.
 *
 * THE LAST POINT IS WHY THIS FILE EXISTS. "We recorded a key version" reads
 * like a mitigation, and the next person to touch this will be tempted to
 * treat it as one - to write a rotation command "since the version is already
 * there". These assertions make that a build failure rather than a judgement
 * call, and say in the message why.
 */
final class NoKeyRotationToolingTest extends TestCase
{
    /** @return array<string, string> path => source, comments stripped */
    private function sources(string $dir): array
    {
        $out = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir)) as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            // Comments stripped: this file's own rationale necessarily names
            // the thing it forbids, and a guard must not be defeatable - or
            // satisfiable - by prose.
            $out[$file->getPathname()] = (string) preg_replace(
                '#/\*.*?\*/#s',
                '',
                (string) file_get_contents($file->getPathname()),
            );
        }

        return $out;
    }

    /**
     * No rotation command, and no re-encryption path, exists.
     *
     * Mutation: add `semantiq:rotate-key`, or a reEncrypt() on the secret
     * store. Both fail here, and both should - they are a Release 2 decision
     * with an approved procedure behind them, not a convenience.
     */
    public function test_no_rotation_or_re_encryption_tooling_was_built(): void
    {
        $forbidden = [
            'rotate-key' => 'a key rotation command',
            'rotateKey' => 'a key rotation method',
            'reEncrypt' => 're-encryption',
            're_encrypt' => 're-encryption',
            'reencrypt' => 're-encryption',
            'APP_KEY_OLD' => 'a second key in the environment',
            'previous_key' => 'a second key',
        ];

        $checked = 0;

        // __DIR__-relative, because this case extends PHPUnit's TestCase
        // rather than Laravel's: there is no booted application, and
        // app_path() needs one.
        foreach ([
            __DIR__.'/../../app',
            __DIR__.'/../../database',
            __DIR__.'/../../routes',
        ] as $dir) {
            foreach ($this->sources($dir) as $path => $source) {
                $checked++;

                foreach ($forbidden as $needle => $why) {
                    $this->assertStringNotContainsString(
                        $needle,
                        $source,
                        basename($path)." names [{$needle}] - {$why}. APP_KEY rotation is "
                        .'UNSUPPORTED in Release 1 while encrypted secrets exist, and tooling for '
                        .'it is a Product Owner decision with an approved procedure behind it.',
                    );
                }
            }
        }

        $this->assertGreaterThan(100, $checked,
            'Almost no files were read, so this guard would pass against an empty tree.');
    }

    /**
     * `key_version` IS RECORDED AND NOTHING READS IT TO DECIDE ANYTHING.
     *
     * If a decrypt path ever branched on the version, that would be the
     * beginning of exactly the tooling that is not supposed to exist - and it
     * would imply a capability (reading an old key) that does not exist
     * either.
     */
    public function test_the_key_version_is_written_and_never_branched_on(): void
    {
        $store = (string) file_get_contents(
            (new ReflectionClass(IntegrationSecretStore::class))->getFileName(),
        );

        $store = (string) preg_replace('#/\*.*?\*/#s', '', $store);

        $this->assertStringContainsString("'key_version' => self::KEY_VERSION", $store,
            'The key version is no longer recorded, so a future re-encryption could not tell which '
            .'rows were encrypted with which key.');

        foreach (['if ($row->key_version', 'match ($row->key_version', '->key_version ==='] as $branch) {
            $this->assertStringNotContainsString($branch, $store,
                "The secret store branches on key_version [{$branch}]. Reading a row encrypted with "
                .'an older key requires that key, which this deployment does not have - so a branch '
                .'here would be promising something it cannot deliver.');
        }
    }

    /** Decryption happens in exactly one place, which is what makes the above checkable. */
    public function test_secrets_are_decrypted_in_exactly_one_place(): void
    {
        $decrypters = [];

        foreach ($this->sources(__DIR__.'/../../app') as $path => $source) {
            if (str_contains($source, 'Crypt::decrypt')) {
                $decrypters[] = basename($path);
            }
        }

        sort($decrypters);

        $this->assertSame(['IntegrationSecretStore.php'], $decrypters,
            'A secret is decrypted somewhere new. Concentrating it in one class is what makes '
            .'"no secret escapes" one claim to check rather than several: '.implode(', ', $decrypters));
    }
}
