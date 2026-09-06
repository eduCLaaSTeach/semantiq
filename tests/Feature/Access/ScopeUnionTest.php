<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Modules\Access\Engine\AccessEngine;
use App\Modules\Access\Engine\AccessQuestion;
use App\Modules\Access\Engine\ResourceReference;
use App\Modules\Access\Services\EntitlementService;
use App\Modules\Access\Support\AccessViolation;
use App\Modules\Access\Support\DecisionReason;
use App\Modules\Access\Support\RoleCode;
use App\Modules\Access\Support\ScopeType;
use App\Modules\Access\Support\Sensitivity;
use App\Modules\Domains\Models\BusinessDomain;
use App\Modules\Organisation\Models\BusinessUnit;
use App\Modules\Organisation\Models\Organisation;
use App\Modules\Organisation\Models\Team;
use App\Modules\Platform\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AccessFactory;
use Tests\Support\OrganisationFactory;
use Tests\TestCase;

/**
 * N-SC1 to N-SC12. SEVERAL CURRENT SCOPES ON ONE ENTITLEMENT UNION.
 *
 * A record is in scope when ANY current row covers it. One scope never reduces
 * or cancels another, and a broader row beside a narrower one makes the
 * narrower redundant rather than restrictive.
 */
final class ScopeUnionTest extends TestCase
{
    use RefreshDatabase;

    private OrganisationFactory $make;

    private AccessFactory $access;

    private AccessEngine $engine;

    private Organisation $organisation;

    private BusinessDomain $finance;

    private User $person;

    private User $actor;

    private Team $north;

    private Team $south;

    private BusinessUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->make = new OrganisationFactory;
        $this->access = new AccessFactory;
        $this->engine = app(AccessEngine::class);

        $this->organisation = $this->make->organisation();
        $this->finance = $this->access->domain($this->organisation);

        $this->unit = $this->make->businessUnit($this->organisation);
        $department = $this->make->department($this->unit);
        $this->north = $this->make->team($department, 'Team North');
        $this->south = $this->make->team($department, 'Team South');

        $this->person = $this->make->user($this->organisation);
        $this->actor = $this->make->user($this->organisation, administrator: true);
    }

    /**
     * N-SC1. THREE TEAM SCOPES UNION.
     *
     * Mutation: intersect them. The entitlement then authorises nothing, and
     * the mutation looks like a tightening rather than a break.
     */
    public function test_three_team_scopes_union(): void
    {
        $department = $this->make->department($this->unit, 'Second');
        $east = $this->make->team($department, 'Team East');

        $assignment = $this->access->assignment($this->person, RoleCode::Manager, $this->organisation);
        $entitlement = $this->access->entitlement($assignment, $this->finance);
        $this->access->ceiling($entitlement, Sensitivity::Standard);

        foreach ([$this->north, $this->south, $east] as $team) {
            $this->access->scope($entitlement, ScopeType::Team, $team->id);
        }

        foreach ([$this->north, $this->south, $east] as $team) {
            $this->assertTrue(
                $this->engine->decide($this->questionForTeam($team->id))->allowed,
                "Team [{$team->name}] was not reachable, so the three scopes did not union."
            );
        }
    }

    /**
     * N-SC2. MIXED TYPES union too.
     *
     * Mutation: require one scope type per entitlement.
     */
    public function test_a_team_scope_and_a_business_unit_scope_union(): void
    {
        $other = $this->make->businessUnit($this->organisation, 'Other');

        $assignment = $this->access->assignment($this->person, RoleCode::Manager, $this->organisation);
        $entitlement = $this->access->entitlement($assignment, $this->finance);
        $this->access->ceiling($entitlement, Sensitivity::Standard);

        $this->access->scope($entitlement, ScopeType::Team, $this->north->id);
        $this->access->scope($entitlement, ScopeType::BusinessUnit, $other->id);

        $this->assertTrue($this->engine->decide($this->questionForTeam($this->north->id))->allowed);
        $this->assertTrue($this->engine->decide($this->questionForBusinessUnit($other->id))->allowed);

        // And a record in neither is still refused, which is what makes the two
        // assertions above mean something.
        $this->assertFalse($this->engine->decide($this->questionForTeam($this->south->id))->allowed);
    }

    /**
     * N-SC3. ROW ORDER CANNOT ALTER THE DECISION.
     *
     * Mutation: return on the first row instead of testing all. It passes
     * whenever the fixture happens to order the covering row first, which is
     * why the covering row is deliberately placed LAST here.
     */
    public function test_the_order_of_scope_rows_cannot_alter_the_decision(): void
    {
        $assignment = $this->access->assignment($this->person, RoleCode::Manager, $this->organisation);
        $entitlement = $this->access->entitlement($assignment, $this->finance);
        $this->access->ceiling($entitlement, Sensitivity::Standard);

        // Non-covering first, covering last.
        $this->access->scope($entitlement, ScopeType::Team, $this->south->id);
        $this->access->scope($entitlement, ScopeType::Team, $this->north->id);

        $this->assertTrue(
            $this->engine->decide($this->questionForTeam($this->north->id))->allowed,
            'The covering scope was not found because it was not the first row.'
        );
    }

    /**
     * N-SC4. A NARROWER SCOPE NEVER RESTRICTS A BROADER ONE.
     *
     * Organisation plus Team A: the Team row is REDUNDANT, not restrictive. A
     * record in Team B is still reachable through the Organisation row.
     *
     * Mutation: let team cap organisation - an invisible deny, which D-64
     * rejects.
     */
    public function test_a_narrow_scope_does_not_restrict_a_broader_one(): void
    {
        $assignment = $this->access->assignment($this->person, RoleCode::Manager, $this->organisation);
        $entitlement = $this->access->entitlement($assignment, $this->finance);
        $this->access->ceiling($entitlement, Sensitivity::Standard);

        $this->access->scope($entitlement, ScopeType::Organisation);
        $this->access->scope($entitlement, ScopeType::Team, $this->north->id);

        $this->assertTrue(
            $this->engine->decide($this->questionForTeam($this->south->id))->allowed,
            'A team scope reduced an organisation scope. Scopes add; they never subtract.'
        );
    }

    /**
     * N-SC6 and N-SC7. Revoking ONE of several preserves the others; revoking
     * the LAST produces zero access and leaves the entitlement CURRENT.
     */
    public function test_revoking_scopes_one_at_a_time(): void
    {
        $service = app(EntitlementService::class);

        $assignment = $this->access->assignment($this->person, RoleCode::Manager, $this->organisation);
        $entitlement = $this->access->entitlement($assignment, $this->finance);
        $this->access->ceiling($entitlement, Sensitivity::Standard);

        $northScope = $this->access->scope($entitlement, ScopeType::Team, $this->north->id);
        $southScope = $this->access->scope($entitlement, ScopeType::Team, $this->south->id);

        $service->revokeScope($northScope, $this->actor);

        $this->assertFalse($this->engine->decide($this->questionForTeam($this->north->id))->allowed);
        $this->assertTrue(
            $this->engine->decide($this->questionForTeam($this->south->id))->allowed,
            'Revoking one scope removed access through another.'
        );

        $service->revokeScope($southScope, $this->actor);

        $decision = $this->engine->decide($this->questionForTeam($this->south->id));

        $this->assertFalse($decision->allowed);
        $this->assertSame(DecisionReason::DeniedScope, $decision->reason);

        // AND THE ENTITLEMENT IS STILL CURRENT. Removing a child must never
        // silently mean the parent was revoked.
        $this->assertTrue(
            $entitlement->fresh()->isCurrent(),
            'Revoking the last scope revoked the entitlement. The entitlement stays current and '
            .'becomes an incomplete, non-authorising grant path.'
        );
    }

    /**
     * N-SC8. A DUPLICATE CURRENT SCOPE FOR THE SAME TARGET IS REFUSED.
     *
     * It grants nothing the first does not, makes revocation ambiguous and
     * makes the screen wrong.
     */
    public function test_a_duplicate_current_scope_is_refused(): void
    {
        $service = app(EntitlementService::class);

        $assignment = $this->access->assignment($this->person, RoleCode::Manager, $this->organisation);
        $entitlement = $this->access->entitlement($assignment, $this->finance);

        $service->assignScope($entitlement, ScopeType::Team, $this->north->id, $this->actor);

        $this->expectException(AccessViolation::class);

        $service->assignScope($entitlement, ScopeType::Team, $this->north->id, $this->actor);
    }

    /**
     * ...but an ENDED row does not block a new period. That is an ordinary
     * re-grant, and it creates a NEW period rather than reviving the old one.
     *
     * N-SC4 and N-C5 together.
     */
    public function test_an_ended_scope_does_not_block_a_new_period(): void
    {
        $service = app(EntitlementService::class);

        $assignment = $this->access->assignment($this->person, RoleCode::Manager, $this->organisation);
        $entitlement = $this->access->entitlement($assignment, $this->finance);

        $first = $service->assignScope($entitlement, ScopeType::Team, $this->north->id, $this->actor);
        $service->revokeScope($first, $this->actor);

        $second = $service->assignScope($entitlement, ScopeType::Team, $this->north->id, $this->actor);

        $this->assertNotSame($first->id, $second->id, 'Re-scoping revived the ended row instead of creating a new period.');
        $this->assertNotNull($first->fresh()->ended_at, 'The old period was reopened.');
        $this->assertNull($second->ended_at);
    }

    /**
     * N-SC9. ENDED SCOPES DO NOT PARTICIPATE.
     *
     * Mutation: drop the `ended_at IS NULL` filter - the change that silently
     * restores revoked access.
     */
    public function test_an_ended_scope_authorises_nothing(): void
    {
        $assignment = $this->access->assignment($this->person, RoleCode::Manager, $this->organisation);
        $entitlement = $this->access->entitlement($assignment, $this->finance);
        $this->access->ceiling($entitlement, Sensitivity::Standard);

        $this->access->scope($entitlement, ScopeType::Team, $this->north->id, endedAt: now()->toDateTimeString());

        $this->assertFalse(
            $this->engine->decide($this->questionForTeam($this->north->id))->allowed,
            'A revoked scope still authorised a request.'
        );
    }

    /**
     * N-SC5 and N-P5. ORGANISATION SCOPE NEVER ESCAPES THE ENTITLED DOMAIN.
     *
     * "Organisation" on a Finance entitlement means all FINANCE records, not
     * all records.
     */
    public function test_organisation_scope_never_reaches_another_domain(): void
    {
        $sales = $this->access->domain($this->organisation, 'sales', 'Sales');

        $this->access->completePath($this->person, $this->finance, RoleCode::Manager, ScopeType::Organisation);

        $this->assertTrue($this->engine->decide($this->questionForDomain($this->finance))->allowed);

        $decision = $this->engine->decide($this->questionForDomain($sales));

        $this->assertFalse(
            $decision->allowed,
            'Organisation scope on a Finance entitlement reached Sales. Scope selects records '
            .'WITHIN the entitled domain and never reaches outside it.'
        );
        $this->assertSame(DecisionReason::DeniedNoEntitlement, $decision->reason);
    }

    /**
     * N-SC10. DOMAIN AND ORGANISATION RESOLVE IDENTICALLY - D-74 - and through
     * the SAME resolution.
     *
     * The equivalence is asserted as behaviour rather than as a source rule,
     * because two paths that are equal today drift silently the day a
     * partition arrives and a source scan would not see it.
     */
    public function test_domain_and_organisation_scope_resolve_identically(): void
    {
        $other = $this->make->user($this->organisation);

        $this->access->completePath($this->person, $this->finance, RoleCode::Manager, ScopeType::Domain);
        $this->access->completePath($other, $this->finance, RoleCode::Manager, ScopeType::Organisation);

        foreach ([$this->person, $other] as $subject) {
            foreach ([$this->north, $this->south] as $team) {
                $this->assertTrue(
                    $this->engine->decide($this->questionForTeam($team->id, $subject))->allowed,
                    'Domain and Organisation scope did not resolve to the same record set.'
                );
            }
        }
    }

    /**
     * N-SC11 and N-SC12. A structural target is REQUIRED where the scope type
     * says so, and REFUSED where it does not apply.
     *
     * Both directions, because a team_id on an `own` scope is a stored
     * contradiction that would match everything or nothing - neither of which
     * anybody granted.
     */
    public function test_a_scope_target_is_required_and_refused_in_the_right_places(): void
    {
        $service = app(EntitlementService::class);

        $assignment = $this->access->assignment($this->person, RoleCode::Manager, $this->organisation);
        $entitlement = $this->access->entitlement($assignment, $this->finance);

        // Required, and missing.
        try {
            $service->assignScope($entitlement, ScopeType::Team, null, $this->actor);
            $this->fail('A team scope was accepted with no team.');
        } catch (AccessViolation $violation) {
            $this->assertSame('scope_target_required', $violation->reason);
        }

        // Not applicable, and present.
        try {
            $service->assignScope($entitlement, ScopeType::Organisation, $this->north->id, $this->actor);
            $this->fail('An organisation scope was accepted with a team.');
        } catch (AccessViolation $violation) {
            $this->assertSame('scope_target_not_applicable', $violation->reason);
        }
    }

    private function questionForTeam(int $teamId, ?User $subject = null): AccessQuestion
    {
        return AccessQuestion::businessData(
            $subject ?? $this->person,
            'read',
            $this->finance->id,
            new ResourceReference(teamIds: [$teamId], organisationId: $this->organisation->id),
            Sensitivity::Standard,
        );
    }

    private function questionForBusinessUnit(int $unitId): AccessQuestion
    {
        return AccessQuestion::businessData(
            $this->person,
            'read',
            $this->finance->id,
            new ResourceReference(businessUnitIds: [$unitId], organisationId: $this->organisation->id),
            Sensitivity::Standard,
        );
    }

    private function questionForDomain(BusinessDomain $domain): AccessQuestion
    {
        return AccessQuestion::businessData(
            $this->person,
            'read',
            $domain->id,
            new ResourceReference(organisationId: $this->organisation->id),
            Sensitivity::Standard,
        );
    }
}
