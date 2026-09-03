<?php

declare(strict_types=1);

namespace Tests\Architecture;

use App\Modules\Platform\Models\PlatformRole;
use App\Modules\Platform\Security\SecurityEventLogger;
use Illuminate\Support\Facades\Route;
use ReflectionEnum;
use Tests\TestCase;

/**
 * The boundaries P1-00 must not cross, enforced rather than trusted.
 *
 * Every one of these guards something that would be easy to do by accident and
 * hard to notice afterwards.
 */
final class P1BoundaryTest extends TestCase
{
    /**
     * D-09: one value, and one only.
     *
     * Adding Organisation Administrator, Executive, Manager, Business User or
     * Auditor here would be building P1-05 early - which is exactly the
     * pre-building the phase plan forbids, and would quietly become the
     * authorisation engine nobody designed.
     */
    public function test_the_platform_role_seam_has_exactly_one_case(): void
    {
        $cases = PlatformRole::cases();

        $this->assertCount(
            1,
            $cases,
            'The P1-00 seam has grown extra roles. P1-05 owns the role model; this is not it.'
        );

        $this->assertSame('system_administrator', $cases[0]->value);
    }

    /** The seam must be documented as temporary, or the next unit inherits it as design. */
    public function test_the_platform_role_seam_records_that_p1_05_replaces_it(): void
    {
        $doc = (new ReflectionEnum(PlatformRole::class))->getDocComment() ?: '';

        $this->assertStringContainsString('P1-05', $doc);
    }

    /**
     * The bootstrap grant is a privilege-granting secret. If the deploy workflow
     * ever invoked the command, that secret would be printed into a CI log many
     * people can read.
     */
    public function test_no_workflow_ever_issues_a_bootstrap_grant(): void
    {
        $workflows = glob(__DIR__.'/../../.github/workflows/*.yml') ?: [];

        $this->assertNotEmpty($workflows);

        foreach ($workflows as $workflow) {
            $name = basename($workflow);
            $contents = file_get_contents($workflow);

            // Named workflows are not enough: this guard has to cover every
            // workflow that exists now and every one added later, because the
            // grant is a privilege-granting secret and a CI log is readable by
            // everyone with repository access.
            $this->assertStringNotContainsString(
                'semantiq:bootstrap-grant',
                $contents,
                "{$name} issues a bootstrap grant. The grant would be printed into the run log."
            );

            $this->assertStringNotContainsString(
                'storage:link',
                $contents,
                "{$name} runs storage:link, which under the root layout targets the real storage directory."
            );
        }
    }

    /**
     * No route may begin with a directory the Apache boundary refuses. The
     * bootstrap path is /first-run for exactly this reason: /bootstrap would
     * have returned 403 in production while passing every local test.
     */
    public function test_no_route_uses_a_blocked_prefix(): void
    {
        $blocked = ['app', 'bootstrap', 'config', 'database', 'doc', 'deployment',
            'node_modules', 'public', 'resources', 'routes', 'storage', 'tests', 'vendor'];

        foreach (Route::getRoutes() as $route) {
            $first = explode('/', trim($route->uri(), '/'))[0] ?? '';

            $this->assertNotContains(
                $first,
                $blocked,
                "Route [{$route->uri()}] begins with [{$first}], which Apache refuses in production."
            );
        }
    }

    /** Every event the code emits must be a declared one. */
    public function test_security_events_are_declared(): void
    {
        $declared = SecurityEventLogger::events();

        $this->assertNotEmpty($declared);

        // P1-01 adds the structural event families, P1-02 identity health,
        // P1-03 the user and group families, and P1-04 business_domain.
        // Anything outside this list is an event nobody reviewed.
        $families = 'auth|bootstrap|organisation|legal_entity|business_unit|department|team|management|identity|user|group|business_domain';

        foreach ($declared as $event) {
            $this->assertMatchesRegularExpression('/^('.$families.')\./', $event);
        }
    }

    /**
     * P1-00 owns identity. It does not own roles, scopes or sensitivity, and no
     * migration may create them.
     *
     * `domains` and `business_domains` left this list on 3 September 2026 as a
     * REVIEWED TRANSFER to P1-04, the same way organisations, teams and
     * business_units moved to P1-01 and users to P1-00. What remains belongs to
     * P1-05 and later - and it staying here is the point, because removing one
     * of these to make an implementation pass is how the guard is really lost.
     * NoBusinessSchemaTest asserts the P1-05 names are still forbidden.
     */
    public function test_p1_00_creates_no_later_unit_schema(): void
    {
        $forbidden = ['roles', 'permissions', 'scopes',
            'sensitivity', 'entitlements', 'audit'];

        foreach (glob(__DIR__.'/../../database/migrations/*.php') ?: [] as $file) {
            preg_match_all("/Schema::create\(\s*'([^']+)'/", file_get_contents($file), $created);

            foreach ($created[1] ?? [] as $table) {
                $this->assertNotContains(
                    $table,
                    $forbidden,
                    'Migration '.basename($file)." creates [{$table}], owned by a later unit."
                );
            }
        }
    }

    /**
     * The identity boundary must stay a boundary. One provider is the whole of
     * Release 1; D-13 is explicit that this must not become a generic identity
     * framework.
     */
    public function test_exactly_one_identity_provider_implementation_exists(): void
    {
        $providers = glob(__DIR__.'/../../app/Modules/Platform/Identity/*/*Provider.php') ?: [];

        $this->assertCount(1, $providers, 'A second identity provider appeared. Release 1 ships Microsoft only.');
    }
}
