<?php

declare(strict_types=1);

namespace Tests\Architecture;

use App\Modules\Platform\Setup\Connections\ConnectionResult;
use App\Modules\Platform\Setup\IntegrationFamily;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;

/**
 * T1, T3, T4 and T6, ASSERTED OVER THE SOURCE.
 *
 * A CONNECTION IS NOT A CAPABILITY, and these say so structurally. A runtime
 * test can only show that nothing bad happened on the paths it exercised; these
 * show there is no path. That matters most for exactly the three things this
 * unit is defined as not having - AI inference, Fabric business data, and an
 * arbitrary mail recipient - because each is one helpful commit away, and each
 * would be indistinguishable from a feature request in review.
 */
final class ConnectionTestsAreNotCapabilitiesTest extends TestCase
{
    private const MODULE = __DIR__.'/../../app/Modules/Platform/Setup';

    /** @return array<string, string> path => source, comments stripped */
    private function moduleSources(): array
    {
        $sources = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::MODULE)) as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $sources[$file->getPathname()] = $this->withoutComments(
                    (string) file_get_contents($file->getPathname()),
                );
            }
        }

        $this->assertNotSame([], $sources, 'The Setup module has no source files to check.');

        return $sources;
    }

    /** A guard must not be satisfied by prose. Every one of these names appears in a comment somewhere. */
    private function withoutComments(string $source): string
    {
        $source = (string) preg_replace('#/\*.*?\*/#s', '', $source);

        return (string) preg_replace('#^\s*//.*$#m', '', $source);
    }

    /**
     * T1. THE AI TEST PERFORMS NO INFERENCE.
     *
     * Mutation: add a one-token completion to AiConnectionTester "just to be
     * sure the key works".
     */
    public function test_t1_the_module_names_no_inference_path(): void
    {
        $forbidden = [
            'completions' => 'a completion endpoint',
            'chat/completions' => 'a chat endpoint',
            'embeddings' => 'an embedding endpoint',
            '/generate' => 'a generation endpoint',
            'max_tokens' => 'a generation parameter',
            'temperature' => 'a generation parameter',
            "'prompt'" => 'a prompt',
            "'messages'" => 'a chat payload',
        ];

        foreach ($this->moduleSources() as $path => $source) {
            foreach ($forbidden as $needle => $why) {
                $this->assertStringNotContainsString(
                    $needle,
                    $source,
                    basename($path)." names [{$needle}] - {$why}. A connection test proves reach and "
                    .'credentials. Inference costs money on every press of a Test button and is the '
                    .'first step of a capability this unit is defined as not having.',
                );
            }
        }
    }

    /**
     * T3. THE FABRIC TEST READS NO BUSINESS DATA AND ENUMERATES NOTHING.
     *
     * Mutation: list workspaces instead of fetching the configured one, because
     * it "proves more".
     */
    public function test_t3_the_module_names_no_business_data_path(): void
    {
        $forbidden = [
            'executeQueries' => 'a DAX query',
            'datasets' => 'a dataset read',
            'lakehouses' => 'a lakehouse read',
            'semanticModels' => 'a semantic model read',
            'tables' => 'a table read',
            '/items' => 'an item enumeration',
            'SELECT ' => 'a SQL query',
        ];

        foreach ($this->moduleSources() as $path => $source) {
            foreach ($forbidden as $needle => $why) {
                $this->assertStringNotContainsString(
                    $needle,
                    $source,
                    basename($path)." names [{$needle}] - {$why}.",
                );
            }
        }
    }

    /** ...and the workspace request is for ONE workspace, by id, never the collection. */
    public function test_t3_the_workspace_request_cannot_return_a_list(): void
    {
        $source = $this->withoutComments((string) file_get_contents(
            self::MODULE.'/Connections/FabricConnectionTester.php',
        ));

        $this->assertStringContainsString('/v1/workspaces/{$workspace}', $source,
            'The Fabric test no longer requests one workspace by identifier.');

        $this->assertDoesNotMatchRegularExpression(
            '#workspaces[\'"]#',
            $source,
            'The Fabric test can request the workspace COLLECTION, which is a directory read of the '
            ."customer's entire analytics estate performed by a setup screen.",
        );
    }

    /**
     * T4. THE TEST MESSAGE HAS NOWHERE TO PUT A RECIPIENT.
     *
     * Mutation: accept a `to` field so somebody can "check it arrives". A test
     * that can be pointed at an address is an open relay wearing a diagnostic's
     * clothes, and it sends from the deployment's own domain.
     */
    public function test_t4_no_test_request_carries_a_recipient(): void
    {
        $forbidden = ["'to'", '"to"', "'recipient'", '->to(', 'Mail::to'];

        foreach ($this->moduleSources() as $path => $source) {
            foreach ($forbidden as $needle) {
                $this->assertStringNotContainsString(
                    $needle,
                    $source,
                    basename($path)." names [{$needle}]. There is deliberately no recipient field: "
                    .'the optional test message goes to the signed-in principal, resolved '
                    .'server-side, and no parameter changes that.',
                );
            }
        }
    }

    /**
     * T6. SUCCESS ENABLES NO BUSINESS ACCESS.
     *
     * The module names no part of the access model, so a passing test cannot
     * grant anything - there is nothing here to grant it with.
     */
    public function test_t6_the_module_touches_no_part_of_the_access_model(): void
    {
        $forbidden = ['AccessEngine', 'RoleAssignment', 'DomainEntitlement', 'ActionClass', 'RoleCatalogue'];

        foreach ($this->moduleSources() as $path => $source) {
            foreach ($forbidden as $needle) {
                $this->assertStringNotContainsString(
                    $needle,
                    $source,
                    basename($path)." names [{$needle}]. Configuring an integration is not an "
                    .'entitlement, and a Test button must not be able to become one.',
                );
            }
        }
    }

    /** The result type has three fields and no room for a caught message. */
    public function test_the_result_carries_only_a_chosen_sentence(): void
    {
        $properties = array_map(
            fn ($p): string => $p->getName(),
            (new ReflectionClass(ConnectionResult::class))->getProperties(),
        );

        sort($properties);

        $this->assertSame(['explanation', 'status'], $properties,
            'ConnectionResult has grown a field. A caught provider message has nowhere to go only '
            .'while there is nowhere to put it.');

        $this->assertTrue((new ReflectionClass(ConnectionResult::class))->isReadOnly());
    }

    /** The four families are closed, and each declares its own allowlists. */
    public function test_the_family_set_is_closed(): void
    {
        $cases = array_map(
            static fn (IntegrationFamily $f): string => $f->value,
            IntegrationFamily::cases(),
        );

        $this->assertSame(['identity', 'email', 'ai', 'fabric'], $cases,
            'The integration family set changed. A fifth family is a Product Owner decision.');

        foreach (IntegrationFamily::cases() as $family) {
            $this->assertNotSame([], $family->fields(), "[{$family->value}] declares no field allowlist.");
            $this->assertNotSame('', $family->inWords());

            // Every meaningful field must be a real field. A typo here would
            // silently mean "nothing about this family invalidates a result".
            $this->assertSame(
                [],
                array_diff($family->meaningfulFields(), $family->fields()),
                "[{$family->value}] names a meaningful field that is not in its field allowlist, so "
                .'a change to it can never be detected.',
            );
        }
    }
}
