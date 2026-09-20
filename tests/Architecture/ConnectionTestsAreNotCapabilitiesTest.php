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
     * T4. NOTHING HERE TAKES A RECIPIENT FROM A REQUEST.
     *
     * THE RULE USED TO BE "no class in this module may name `->to(` at all",
     * and that was enforceable while nothing was ever sent. D-153 requires ONE
     * message to be sent, so a blanket ban now forbids the approved behaviour -
     * and the thing being protected was never "no addressing". It was "no
     * address somebody outside can choose".
     *
     * SO THE CLAIM SPLITS IN TWO, and both halves are stronger than the ban:
     *
     *   1. NO class may name a request-shaped recipient key. `to`, `recipient`,
     *      `cc`, `bcc` and Mail::to() are forbidden EVERYWHERE, including in
     *      the sender, because that is the open-relay shape.
     *   2. Exactly ONE class may address a message, exactly once, and never to
     *      more than one address.
     *
     * Mutation: add a second `->to(`, a `->cc(`, or read `'to'` from a request.
     */
    public function test_t4_no_test_request_carries_a_recipient(): void
    {
        $requestShaped = ["'to'", '"to"', "'recipient'", "'cc'", "'bcc'", 'Mail::to'];

        $addressers = [];

        foreach ($this->moduleSources() as $path => $source) {
            foreach ($requestShaped as $needle) {
                $this->assertStringNotContainsString(
                    $needle,
                    $source,
                    basename($path)." names [{$needle}]. A recipient that can be named in a "
                    .'request is an open relay wearing a diagnostic\'s clothes: it sends from the '
                    .'deployment\'s own domain, through its own authenticated server, to anywhere.',
                );
            }

            foreach (['->cc(', '->bcc(', '->addTo(', '->addCc(', '->addBcc('] as $extra) {
                $this->assertStringNotContainsString(
                    $extra,
                    $source,
                    basename($path)." names [{$extra}], so one message can reach an address "
                    .'nobody chose deliberately.',
                );
            }

            if (! str_contains($source, '->to(')) {
                continue;
            }

            $addressers[] = basename($path);

            $this->assertSame(
                1,
                substr_count($source, '->to('),
                basename($path).' addresses a message more than once. One test message reaches '
                .'one address - the signed-in principal\'s own.',
            );
        }

        sort($addressers);

        $this->assertSame(
            ['TestEmailSender.php'],
            $addressers,
            'The set of classes that address an email has changed. D-153 permits exactly one, and '
            .'its recipient comes from its own parameter - which TestEmailGoesOnlyToThePrincipal '
            .'pins by reflection and by driving every request shape somebody would try.',
        );
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
