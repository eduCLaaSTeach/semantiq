<?php

declare(strict_types=1);

namespace Tests\Architecture;

use Tests\TestCase;

/**
 * THE READ-ONLY VERIFICATION WORKFLOWS MUST NAME CODE THAT EXISTS.
 *
 * Those workflows run PHP on the production server through `artisan tinker`.
 * Nothing compiles them, nothing type-checks them, and CI never executes them -
 * so a rename in the application is invisible until somebody dispatches one
 * against production and it aborts.
 *
 * P1-05 PRODUCED EXACTLY THAT. It deleted `users.platform_role` and the
 * `User::activeSystemAdministrators()` scope that read it. Four verification
 * workflows still named one or the other, and `verify-bootstrap` failed on its
 * first dispatch after the deployment - during the validation window, which is
 * the one moment those reports are load-bearing. The application was fine; the
 * instruments were broken, which is worse than it sounds, because a broken
 * instrument reads as a broken system.
 *
 * This guard asserts the workflows still refer to real code. It cannot prove
 * the QUERIES are right - only that the symbols exist.
 */
final class VerificationWorkflowTest extends TestCase
{
    /**
     * Every `\App\…` class named in a verification workflow must exist.
     *
     * Mutation: rename any class one of them reads.
     */
    public function test_every_application_class_named_in_a_verification_workflow_exists(): void
    {
        $checked = 0;

        foreach ($this->workflows() as $path => $source) {
            preg_match_all('/\\\\{1,2}(App(?:\\\\{1,2}[A-Za-z_][A-Za-z0-9_]*)+)/', $source, $matches);

            foreach (array_unique($matches[1]) as $raw) {
                // The workflows escape backslashes for the shell; PHP wants one.
                $class = preg_replace('/\\\\{2,}/', '\\', $raw);

                // Namespace prefixes appear too (App\Modules\Access). Only test
                // what is meant to be a class - the last segment starts upper
                // case and the symbol resolves or it does not.
                if (! str_contains($class, '\\')) {
                    continue;
                }

                $checked++;

                $this->assertTrue(
                    class_exists($class) || interface_exists($class) || $this->isNamespacePrefix($class),
                    basename($path)." names [{$class}], which does not exist. That workflow will abort "
                    .'when it is dispatched against production.'
                );
            }
        }

        $this->assertGreaterThan(10, $checked, 'Almost nothing was scanned. The pattern stopped matching.');
    }

    /**
     * Every method a workflow calls on a container-resolved service must exist.
     *
     * This is the shape that broke: `app(\Some\Service::class)->aMethod()`.
     *
     * Mutation: rename the method on the service.
     */
    public function test_every_service_method_called_by_a_verification_workflow_exists(): void
    {
        $checked = 0;

        foreach ($this->workflows() as $path => $source) {
            preg_match_all(
                '/app\(\\\\{1,2}(App(?:\\\\{1,2}[A-Za-z_][A-Za-z0-9_]*)+)::class\)->([A-Za-z_][A-Za-z0-9_]*)\(/',
                $source,
                $matches,
                PREG_SET_ORDER,
            );

            foreach ($matches as [, $raw, $method]) {
                $class = preg_replace('/\\\\{2,}/', '\\', $raw);
                $checked++;

                $this->assertTrue(
                    class_exists($class) && method_exists($class, $method),
                    basename($path)." calls [{$class}::{$method}()], which does not exist."
                );
            }
        }

        $this->assertGreaterThan(0, $checked, 'No container-resolved service call was found to check.');
    }

    /**
     * No verification workflow may call a model query scope that is gone.
     *
     * `User::query()->activeSystemAdministrators()` is not a method on the
     * model - it is a scope, so `method_exists` says nothing and the call fails
     * only at runtime. This resolves it the way Eloquent would.
     *
     * Mutation: put the removed scope back into a workflow.
     */
    public function test_every_model_scope_called_by_a_verification_workflow_resolves(): void
    {
        $checked = 0;

        foreach ($this->workflows() as $path => $source) {
            preg_match_all(
                '/\\\\{1,2}(App(?:\\\\{1,2}[A-Za-z_][A-Za-z0-9_]*)+)::query\(\)->([A-Za-z_][A-Za-z0-9_]*)\(/',
                $source,
                $matches,
                PREG_SET_ORDER,
            );

            foreach ($matches as [, $raw, $method]) {
                $class = preg_replace('/\\\\{2,}/', '\\', $raw);

                if (! class_exists($class)) {
                    continue; // Reported by the first case, with a better message.
                }

                $checked++;

                $builder = $class::query();

                /*
                 * EVERY WAY ELOQUENT ACTUALLY RESOLVES A CALL, not just the
                 * obvious one. `orderBy` is not a method on the Eloquent
                 * builder at all - it is forwarded to the underlying query
                 * builder by __call - so a naive method_exists check fails on
                 * perfectly ordinary code and would have to be silenced, which
                 * is how a guard turns into noise and then into nothing.
                 */
                $resolves = method_exists($builder, $method)
                    || method_exists($builder->getQuery(), $method)
                    || $builder->hasNamedScope($method)
                    || $builder->hasMacro($method);

                $this->assertTrue(
                    $resolves,
                    basename($path)." calls [{$method}()] on a ".class_basename($class)
                    .' query, and nothing by that name resolves - not a builder method, not a scope, '
                    .'not a macro. This is exactly how P1-05 broke four of these workflows.'
                );
            }
        }

        $this->assertGreaterThan(5, $checked, 'Almost no model query call was scanned.');
    }

    /**
     * A namespace prefix such as App\Modules\Access is matched by the pattern
     * but is not a class. Anything a real class lives under is acceptable.
     */
    private function isNamespacePrefix(string $candidate): bool
    {
        $prefix = $candidate.'\\';

        foreach (get_declared_classes() as $declared) {
            if (str_starts_with($declared, $prefix)) {
                return true;
            }
        }

        return is_dir(base_path(str_replace(['App\\', '\\'], ['app/', '/'], $candidate)));
    }

    /** @return array<string, string> */
    private function workflows(): array
    {
        $found = [];

        foreach (glob(base_path('.github/workflows/verify-*.yml')) ?: [] as $path) {
            $found[$path] = (string) file_get_contents($path);
        }

        $this->assertNotEmpty($found, 'No verification workflows were found to check.');

        return $found;
    }
}
