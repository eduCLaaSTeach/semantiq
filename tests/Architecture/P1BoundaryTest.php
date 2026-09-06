<?php

declare(strict_types=1);

namespace Tests\Architecture;

use App\Modules\Access\Support\RoleCatalogue;
use App\Modules\Access\Support\RoleCode;
use App\Modules\Platform\Security\SecurityEventLogger;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
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
     * D-09 IS GONE, AND THAT IS THE ASSERTION.
     *
     * The seam this file used to guard - PlatformRole, one case, documented as
     * temporary - was replaced by P1-05's role model. What replaced it must not
     * leave the old one behind: a readable users.platform_role column or a
     * surviving PlatformRole enum would be a SECOND authority that can disagree
     * with role_assignments, and the disagreement would appear the first time
     * somebody updated one of them.
     *
     * Mutation: leave the column, or the enum, in place. Both are caught here.
     */
    public function test_the_platform_role_seam_is_gone(): void
    {
        $this->assertFalse(
            class_exists('App\\Modules\\Platform\\Models\\PlatformRole'),
            'The P1-00 platform_role seam still exists. D-49 replaced it with role assignments, '
            .'and leaving it readable is the second authorization model P1-05 exists to prevent.'
        );

        $this->assertFalse(
            Schema::hasColumn('users', 'platform_role'),
            'users.platform_role still exists. It can disagree with role_assignments, and a column '
            .'that can disagree with a history table eventually does.'
        );
    }

    /**
     * The catalogue that replaced it: SEVEN roles, fixed codes, nothing
     * manageable at runtime.
     *
     * Mutation: add an eighth role; make a label mutable - "Super Admin".
     */
    public function test_the_role_catalogue_has_exactly_seven_immutable_roles(): void
    {
        $roles = RoleCatalogue::roles();

        $this->assertCount(
            7,
            $roles,
            'The role catalogue has changed size. D-51 to D-54 all answered "no": there is no roles '
            .'table, no custom role and no runtime change, so a new role is a Product Owner decision.'
        );

        $this->assertSame(
            [
                'system_administrator',
                'organisation_administrator',
                'executive',
                'domain_owner',
                'manager',
                'business_user',
                'auditor',
            ],
            array_map(static fn (RoleCode $role): string => $role->value, $roles),
        );

        // An enum has no setter, which is what makes "immutable" structural
        // rather than a promise. Asserted so that replacing it with a model
        // backed by a table fails here.
        $this->assertTrue(
            (new ReflectionEnum(RoleCode::class))->isEnum(),
            'RoleCode stopped being an enum. A role backed by a row is a role somebody can rename.'
        );
    }

    /**
     * EXACTLY ONE role may be held without an organisation.
     *
     * system_administrator is platform-scoped because bootstrap must create one
     * before a Company Profile exists. A second platform-scoped role would be a
     * role that escapes its tenancy boundary.
     */
    public function test_only_system_administrator_is_platform_scoped(): void
    {
        $platformScoped = array_values(array_filter(
            RoleCatalogue::roles(),
            static fn (RoleCode $role): bool => $role->isPlatformScoped(),
        ));

        $this->assertSame([RoleCode::SystemAdministrator], $platformScoped);
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
        // P1-03 the user and group families, P1-04 business_domain and P1-05
        // access. Anything outside this list is an event nobody reviewed.
        $families = 'auth|bootstrap|organisation|legal_entity|business_unit|department|team|management|identity|user|group|business_domain|access';

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
