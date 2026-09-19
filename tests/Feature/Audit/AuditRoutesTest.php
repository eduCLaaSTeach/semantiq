<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Modules\Access\Support\RoleCode;
use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;
use App\Modules\Platform\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Support\AccessFactory;
use Tests\Support\OrganisationFactory;
use Tests\TestCase;

/**
 * A7. NO APPLICATION PATH UPDATES OR DELETES AN AUDIT ROW.
 *
 * Asserted as an EQUALITY over the route set, the way P1-01 asserts its four
 * DELETE routes. A test that merely looked for a delete route would pass on a
 * PATCH somebody added to "correct a typo in an audit entry" - which is exactly
 * the helpful change this forbids.
 */
final class AuditRoutesTest extends TestCase
{
    use RefreshDatabase;

    private OrganisationFactory $make;

    private AccessFactory $access;

    protected function setUp(): void
    {
        parent::setUp();
        $this->make = new OrganisationFactory;
        $this->access = new AccessFactory;
    }

    /** Mutation: add any write route under the audit prefix. */
    public function test_audit_exposes_exactly_four_reads_and_nothing_else(): void
    {
        $routes = [];

        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'console/audit')) {
                continue;
            }

            foreach ($route->methods() as $method) {
                if (in_array($method, ['HEAD', 'OPTIONS'], true)) {
                    continue;
                }

                $routes[] = $method.' '.$route->uri();
            }
        }

        sort($routes);

        $this->assertSame([
            'GET console/audit',
            'GET console/audit/admin-changes',
            'GET console/audit/configuration',
            'GET console/audit/security-events',
        ], $routes, 'A write path appeared under Audit.');
    }

    /**
     * READING IS NOT DECIDING. An Auditor holds EvidenceRead and reaches the
     * screen; P1-06 once filtered such a listing by who may ACT and showed them
     * nothing at all.
     *
     * Mutation: raise the route's action class to AccessAdmin. Auditor and
     * Organisation Administrator are then refused.
     */
    public function test_evidence_readers_reach_audit(): void
    {
        $organisation = $this->make->organisation();

        foreach ([RoleCode::SystemAdministrator, RoleCode::OrganisationAdministrator, RoleCode::Auditor] as $role) {
            $viewer = $this->make->user($organisation);
            $this->access->assignment($viewer, $role, $organisation);

            $this->signedInAs($viewer->fresh())
                ->get('/console/audit')
                ->assertOk();
        }
    }

    /**
     * And a business role does NOT. Administration authority never follows from
     * business access, and the reverse is the leak this asserts against.
     *
     * Mutation: drop RequireActionClass from the audit group.
     */
    public function test_a_business_role_cannot_reach_audit(): void
    {
        $organisation = $this->make->organisation();

        foreach ([RoleCode::BusinessUser, RoleCode::Manager, RoleCode::Executive, RoleCode::DomainOwner] as $role) {
            $viewer = $this->make->user($organisation);
            $this->access->assignment($viewer, $role, $organisation);

            $response = $this->signedInAs($viewer->fresh())->get('/console/audit');

            $this->assertNotSame(200, $response->status(), $role->value.' reached Audit.');
        }
    }

    private function signedInAs(User $user): self
    {
        return $this->withSession([
            EnsureSessionIsCurrent::SESSION_USER_ID => $user->id,
            EnsureSessionIsCurrent::SESSION_AUTHENTICATED_AT => now()->toIso8601String(),
        ]);
    }
}
