<?php

declare(strict_types=1);

namespace Tests\Architecture;

use App\Modules\Access\Engine\AccessEngine;
use App\Modules\Access\Engine\AccessQuestion;
use App\Modules\Access\Support\ActionClass;
use App\Modules\Access\Support\RoleCatalogue;
use App\Modules\Access\Support\RoleCode;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

/**
 * ONE AUTHORIZATION MODEL, and the architecture that makes it one.
 *
 * These fail at the SHAPE rather than at a behaviour: a second engine, a
 * second definition of "is this person an administrator", or a flag inside the
 * question would each pass every functional test right up until the day the two
 * copies disagreed.
 */
final class AccessBoundaryTest extends TestCase
{
    /**
     * N-B7 and N-B8. EXACTLY ONE ENGINE, and exactly one definition of the
     * administrator question.
     *
     * Mutation: give the simulator its own copy; add a second helper.
     */
    public function test_there_is_exactly_one_access_engine(): void
    {
        $engines = [];
        $holdsRole = [];

        foreach ($this->phpFilesIn(dirname(__DIR__, 2).'/app') as $file) {
            $relative = str_replace(dirname(__DIR__, 2).'/', '', $file);
            $code = $this->codeOnly((string) file_get_contents($file));

            // A class that decides access, other than the engine itself.
            if (preg_match('/class\s+\w*(AccessEngine|AuthorizationEngine|PermissionEngine)\w*/', $code) === 1) {
                $engines[] = $relative;
            }

            // A method that answers "does this person hold this role", other
            // than the engine's own.
            if (str_contains($code, 'function holdsRole')) {
                $holdsRole[] = $relative;
            }
        }

        $this->assertSame(
            ['app/Modules/Access/Engine/AccessEngine.php'],
            $engines,
            'There is more than one access engine. A second gives confident answers that are wrong '
            .'exactly when the two have drifted.'
        );

        $this->assertSame(
            ['app/Modules/Access/Engine/AccessEngine.php'],
            $holdsRole,
            'There is more than one definition of "does this person hold this role".'
        );
    }

    /**
     * N-EN5. EVIDENCE MODE IS NOT A FIELD OF THE QUESTION.
     *
     * A flag inside the question is a flag policy code can read. Keeping it
     * outside makes it structurally incapable of changing the answer - which is
     * why this is an architecture test and not a behavioural one.
     *
     * Mutation: add it there.
     */
    public function test_the_access_question_carries_only_the_security_question(): void
    {
        $properties = array_map(
            static fn (ReflectionProperty $property): string => $property->getName(),
            (new ReflectionClass(AccessQuestion::class))->getProperties(),
        );

        sort($properties);

        $this->assertSame(
            ['action', 'actionClass', 'businessDomainId', 'organisationId', 'resource', 'sensitivity', 'user'],
            $properties,
            'AccessQuestion carries something other than the security question. Whether the caller '
            .'wants detailed evidence must never be able to alter policy semantics.'
        );

        foreach (['explain', 'verbose', 'allPaths', 'evidence', 'mode', 'debug'] as $forbidden) {
            $this->assertNotContains(
                $forbidden,
                $properties,
                "AccessQuestion carries [{$forbidden}]. Evidence mode belongs outside the question."
            );
        }
    }

    /**
     * BOTH ENTRY POINTS GO THROUGH ONE EVALUATOR.
     *
     * decide() and explain() must each call evaluate() and nothing else - two
     * separate implementations would drift.
     */
    public function test_decide_and_explain_share_one_evaluator(): void
    {
        $source = $this->codeOnly(
            (string) file_get_contents(dirname(__DIR__, 2).'/app/Modules/Access/Engine/AccessEngine.php')
        );

        preg_match('/function decide\(.*?\n    \}/s', $source, $decide);
        preg_match('/function explain\(.*?\n    \}/s', $source, $explain);

        $this->assertNotEmpty($decide);
        $this->assertNotEmpty($explain);

        foreach (['decide' => $decide[0], 'explain' => $explain[0]] as $name => $body) {
            $this->assertStringContainsString(
                '$this->evaluate(',
                $body,
                "{$name}() does not go through the shared evaluator."
            );
        }

        // And exactly one evaluator exists.
        $this->assertSame(
            1,
            substr_count($source, 'private function evaluate('),
            'There is more than one evaluator inside the engine.'
        );
    }

    /**
     * BUSINESS_DATA IS THE ONLY CLASS THAT REACHES GRANT-PATH EVALUATION.
     *
     * That is what makes "administration authority never implies business-data
     * authority" structural rather than a rule somebody has to remember.
     *
     * Mutation: make an administration class require a grant path, or let a
     * business class skip one.
     */
    public function test_business_data_is_the_only_class_requiring_a_grant_path(): void
    {
        $requiring = array_values(array_filter(
            ActionClass::cases(),
            static fn (ActionClass $class): bool => $class->requiresGrantPath(),
        ));

        $this->assertSame([ActionClass::BusinessData], $requiring);

        // And no administration class is permitted alongside business data on
        // any single role - the four administration roles reach no business
        // decision, and the business roles reach no administration.
        foreach (RoleCatalogue::roles() as $role) {
            $classes = RoleCatalogue::classesFor($role);

            $hasBusiness = in_array(ActionClass::BusinessData, $classes, true);
            $hasAdministration = array_filter(
                $classes,
                static fn (ActionClass $class): bool => $class !== ActionClass::BusinessData,
            );

            if ($hasBusiness) {
                $this->assertSame(
                    [],
                    $hasAdministration,
                    "[{$role->value}] permits both business data and an administration class. A "
                    .'single role that does both is how administration quietly becomes access.'
                );
            }
        }
    }

    /**
     * NO ROLES TABLE - D-51 to D-54.
     *
     * Nothing about a role is manageable at runtime, so nothing about a role is
     * stored in a row somebody can edit.
     *
     * Mutation: add a roles table; back RoleCode with a model.
     */
    public function test_no_roles_table_exists(): void
    {
        foreach (glob(dirname(__DIR__, 2).'/database/migrations/*.php') ?: [] as $file) {
            $source = (string) file_get_contents($file);

            $this->assertStringNotContainsString(
                "Schema::create('roles'",
                $source,
                basename($file).' creates a roles table. A table anybody can edit is a table '
                .'somebody eventually edits, and a role name is security vocabulary.'
            );
        }

        $this->assertTrue(enum_exists(RoleCode::class));
    }

    /**
     * THE ENGINE IS USABLE OUTSIDE AN HTTP REQUEST - D-70, §5.2.
     *
     * No dependency on the session, the request or middleware. Phase 2's
     * propagation is not a web request, and an engine reachable only through
     * middleware would force Phase 2 to build a second one.
     *
     * Mutation: read the session inside the engine.
     */
    public function test_the_engine_does_not_depend_on_the_request_or_the_session(): void
    {
        $source = $this->codeOnly(
            (string) file_get_contents(dirname(__DIR__, 2).'/app/Modules/Access/Engine/AccessEngine.php')
        );

        foreach (['session(', 'request(', 'Auth::', 'auth()', '$_SESSION', 'Cookie::'] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source,
                "AccessEngine reads [{$forbidden}]. The identity is a parameter, so Phase 2 can ask "
                .'the same question outside an HTTP request rather than building a second engine.'
            );
        }

        // The constructor takes the logger and nothing request-shaped.
        $parameters = (new ReflectionClass(AccessEngine::class))
            ->getConstructor()
            ?->getParameters() ?? [];

        $this->assertCount(1, $parameters);
        $this->assertSame('events', $parameters[0]->getName());
    }

    /** @return list<string> */
    private function phpFilesIn(string $directory): array
    {
        $files = [];

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    private function codeOnly(string $source): string
    {
        $kept = '';

        foreach (token_get_all($source) as $token) {
            if (is_array($token)) {
                if (in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }

                $kept .= $token[1];

                continue;
            }

            $kept .= $token;
        }

        return $kept;
    }
}
