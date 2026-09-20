<?php

declare(strict_types=1);

namespace Tests\Architecture;

use App\Modules\Platform\Setup\Identity\IdentityConfigurationSource;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;

/**
 * THE TWO GUARDS THE P1-10 DESIGN NAMES BY NAME.
 *
 * `NoDirectIdentityConfigRead` and `EnvIsNotIdentityAuthorityAfterCutover`
 * were referenced in three separate code comments before either existed - the
 * comments asserted a coverage that was not there, which is worse than no
 * comment, because the next reader would have believed them.
 *
 * Between them they hold the whole of Correction 5's first half and the
 * no-fallback rule:
 *
 *   ONE SOURCE      every identity value in the application resolves through
 *                   IdentityConfigurationSource, so the health screens cannot
 *                   describe .env while sign-in uses the store.
 *   NO FALLBACK     the `store` branch reads no environment at all, and
 *                   nothing couples the two branches - so the store being
 *                   empty means empty, not "look at .env".
 */
final class IdentityHasOneSourceTest extends TestCase
{
    private function sourcePath(): string
    {
        return (new ReflectionClass(IdentityConfigurationSource::class))->getFileName() ?: '';
    }

    /** @return array<string, string> path => source, comments stripped */
    private function applicationSources(): array
    {
        $out = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__.'/../../app')) as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            /*
             * COMMENTS STRIPPED. Several classes explain in prose WHY they no
             * longer read the configuration directly, and naming the key is
             * the only way to say it. A guard that read those would be failed
             * by the explanations of its own rule, and the fix would be to
             * delete them.
             */
            $out[$file->getPathname()] = (string) preg_replace(
                '#/\*.*?\*/#s',
                '',
                (string) preg_replace(
                    '#^\s*//.*$#m',
                    '',
                    (string) file_get_contents($file->getPathname()),
                ),
            );
        }

        return $out;
    }

    /**
     * NoDirectIdentityConfigRead. ONE READER, IN THE WHOLE APPLICATION.
     *
     * THE MATCH IS THE DOTTED KEY, NOT THE config('… CALL. Four of the eleven
     * reads this replaced lived inside an array in
     * IdentityConfigurationReport::missingKeys() and were read as
     * `config($key)` - a shape no call-shape match would ever have seen. The
     * guard has to contain the string the mistake contains.
     *
     * Mutation: restore any one of the eleven, including the array one.
     */
    public function test_only_the_configuration_source_names_the_identity_keys(): void
    {
        $readers = [];
        $checked = 0;

        foreach ($this->applicationSources() as $path => $source) {
            $checked++;

            // realpath BOTH SIDES. The iterator yields a path built from
            // __DIR__.'/../..' and reflection yields an absolute one, so a
            // string comparison excluded nothing and the guard failed against
            // the one file it is supposed to permit.
            if (realpath($path) === realpath($this->sourcePath())) {
                continue;
            }

            if (str_contains($source, 'identity.microsoft.')) {
                $readers[] = basename($path);
            }
        }

        $this->assertGreaterThan(100, $checked,
            'Almost no files were read, so this guard would pass against an empty tree.');

        sort($readers);

        $this->assertSame(
            [],
            $readers,
            'These read the identity configuration directly instead of through '
            .'IdentityConfigurationSource: '.implode(', ', $readers).'. A deployment whose '
            .'authority is the store would have them describing .env - confidently wrong at '
            .'exactly the moment somebody is debugging a sign-in.',
        );
    }

    /** ...and the one permitted reader really does name them, so the above is not vacuous. */
    public function test_the_configuration_source_is_the_one_that_names_them(): void
    {
        $source = (string) file_get_contents($this->sourcePath());

        $this->assertStringContainsString('identity.microsoft.tenant_id', $source,
            'IdentityConfigurationSource no longer reads the environment at all, so the guard '
            .'above is asserting the absence of something nothing does.');
    }

    /**
     * EnvIsNotIdentityAuthorityAfterCutover. THE `store` BRANCH READS NO
     * ENVIRONMENT, AND NOTHING COUPLES THE TWO.
     *
     * A silent fallback is how two credential authorities come to coexist, and
     * it fails in the worst possible way: the store is edited, the old .env
     * value keeps working, everything looks correct, and nobody finds out
     * until the .env secret expires - at which point the deployment breaks for
     * a reason that has not been true for months.
     *
     * Mutation: add `?: config('identity.microsoft.tenant_id')` to the store
     * branch, which is exactly how somebody would "make it more robust".
     */
    public function test_the_store_branch_reads_no_environment(): void
    {
        $source = (string) preg_replace(
            '#/\*.*?\*/#s',
            '',
            (string) preg_replace('#^\s*//.*$#m', '', (string) file_get_contents($this->sourcePath())),
        );

        $start = strpos($source, 'private function fromStore(');

        $this->assertNotFalse($start, 'fromStore() has gone, so the two branches are no longer two.');

        $body = $this->methodBody($source, $start);

        foreach (['config(', 'env(', '$_ENV', 'getenv('] as $needle) {
            $this->assertStringNotContainsString(
                $needle,
                $body,
                "The `store` branch names [{$needle}]. After cutover .env is not an identity "
                .'authority, and a branch that can read it is one edit away from being one.',
            );
        }

        // ...and it really is the branch that reads the store, so the absence
        // above is about the right method.
        $this->assertStringContainsString('IntegrationConfiguration::query()', $body);
        $this->assertStringContainsString('$this->secrets->get(', $body);
    }

    /** Nothing null-coalesces one authority into the other. */
    public function test_no_fallback_couples_the_two_authorities(): void
    {
        $source = (string) preg_replace(
            '#/\*.*?\*/#s',
            '',
            (string) preg_replace('#^\s*//.*$#m', '', (string) file_get_contents($this->sourcePath())),
        );

        $this->assertDoesNotMatchRegularExpression(
            '/fromStore\([^)]*\)\s*(\?\?|\?:)/',
            $source,
            'The store resolution is coupled to a fallback. There are two states and no third.',
        );

        $this->assertDoesNotMatchRegularExpression(
            '/(\?\?|\?:)\s*\$this->fromEnvironment/',
            $source,
            'Something falls back to the environment. A silent fallback is how two credential '
            .'authorities coexist.',
        );
    }

    /** The method body from its opening brace, by brace matching. */
    private function methodBody(string $source, int $start): string
    {
        $open = strpos($source, '{', $start);
        $this->assertNotFalse($open);

        $depth = 0;

        for ($i = $open; $i < strlen($source); $i++) {
            if ($source[$i] === '{') {
                $depth++;
            }

            if ($source[$i] === '}') {
                $depth--;

                if ($depth === 0) {
                    return substr($source, $open, $i - $open + 1);
                }
            }
        }

        $this->fail('fromStore() is not closed.');
    }
}
