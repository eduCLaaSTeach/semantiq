<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Modules\Access\Engine\AccessEngine;
use App\Modules\Access\Engine\AccessQuestion;
use App\Modules\Access\Engine\ResourceReference;
use App\Modules\Access\Models\DomainEntitlement;
use App\Modules\Access\Models\RoleAssignment;
use App\Modules\Access\Support\DecisionReason;
use App\Modules\Access\Support\RoleCatalogue;
use App\Modules\Access\Support\RoleCode;
use App\Modules\Access\Support\ScopeType;
use App\Modules\Access\Support\Sensitivity;
use App\Modules\Domains\Models\BusinessDomain;
use App\Modules\Domains\Models\DomainOwnership;
use App\Modules\Domains\Models\DomainStatus;
use App\Modules\Organisation\Models\Organisation;
use App\Modules\Platform\Models\User;
use App\Modules\Platform\Models\UserStatus;
use App\Modules\Platform\Security\SecurityEventLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\AccessFactory;
use Tests\Support\OrganisationFactory;
use Tests\Support\RecordingSecurityEventLogger;
use Tests\TestCase;

/**
 * THE BOUNDARY. What a role is worth on its own, and what it is not.
 *
 * Every case here is asserted against the ENGINE's decision rather than
 * against an empty result set. There is no business data in Phase 1, so a test
 * that merely found nothing would pass for the wrong reason and keep passing
 * after the boundary was removed - the failure CLAUDE.md §2 names, and the one
 * this project has produced four times.
 */
final class EngineBoundaryTest extends TestCase
{
    use RefreshDatabase;

    private OrganisationFactory $make;

    private AccessFactory $access;

    private AccessEngine $engine;

    private Organisation $organisation;

    private BusinessDomain $finance;

    protected function setUp(): void
    {
        parent::setUp();

        $this->make = new OrganisationFactory;
        $this->access = new AccessFactory;
        $this->engine = app(AccessEngine::class);

        $this->organisation = $this->make->organisation();
        $this->finance = $this->access->domain($this->organisation);
    }

    /**
     * N-B1. A SYSTEM ADMINISTRATOR RECEIVES NO BUSINESS DATA.
     *
     * The whole unit rests on this. Asserted against a complete question - a
     * real domain, a real resource - so the denial is the engine refusing
     * rather than the question being unanswerable.
     *
     * Mutation: `if (holdsRole(SystemAdministrator)) return allow` in the
     * engine.
     */
    public function test_a_system_administrator_reaches_no_business_data(): void
    {
        $admin = $this->make->user($this->organisation, administrator: true);

        $decision = $this->engine->decide($this->businessQuestion($admin));

        $this->assertFalse($decision->allowed);
        $this->assertSame(DecisionReason::DeniedNoRole, $decision->reason);
    }

    /**
     * N-B10. Nor does an Organisation Administrator, and for the same reason:
     * the four administration classes never reach grant-path evaluation.
     *
     * Mutation: let ORG_ADMIN satisfy a business path.
     */
    public function test_an_organisation_administrator_reaches_no_business_data(): void
    {
        $person = $this->make->user($this->organisation);
        $this->access->assignment($person, RoleCode::OrganisationAdministrator, $this->organisation);

        $decision = $this->engine->decide($this->businessQuestion($person));

        $this->assertFalse($decision->allowed);
        $this->assertSame(DecisionReason::DeniedNoRole, $decision->reason);
    }

    /**
     * N-B3. The Domain Owner ROLE grants nothing beyond a Business User - and
     * N-B11, the P1-04 ownership that must not become one.
     *
     * Both directions in one case, because the defect is a relationship rather
     * than a value: a person who owns Finance and holds domain_owner still
     * reaches nothing without a complete path.
     */
    public function test_the_domain_owner_role_and_domain_ownership_both_grant_nothing(): void
    {
        $person = $this->make->user($this->organisation);

        $this->access->assignment($person, RoleCode::DomainOwner, $this->organisation);

        // And they own the domain, through P1-04's own table.
        DomainOwnership::query()->create([
            'business_domain_id' => $this->finance->id,
            'user_id' => $person->id,
            'assigned_at' => now(),
        ]);

        $decision = $this->engine->decide($this->businessQuestion($person));

        $this->assertFalse($decision->allowed);
        $this->assertSame(DecisionReason::DeniedNoEntitlement, $decision->reason);

        $this->assertSame(
            RoleCatalogue::classesFor(RoleCode::BusinessUser),
            RoleCatalogue::classesFor(RoleCode::DomainOwner),
        );
    }

    /**
     * The other half, and the one that makes every case above non-vacuous: a
     * COMPLETE path DOES authorise.
     *
     * Without this a broken engine that denied everything would pass the whole
     * boundary suite.
     */
    public function test_a_complete_grant_path_authorises(): void
    {
        $person = $this->make->user($this->organisation);
        $this->access->completePath($person, $this->finance);

        $decision = $this->engine->decide($this->businessQuestion($person));

        $this->assertTrue($decision->allowed, 'A complete grant path was refused.');
        $this->assertSame(DecisionReason::AllowedByPath, $decision->reason);
        $this->assertNotNull($decision->authorisingPath);
    }

    /**
     * N-D3 to N-D7. THE P1-04 CARRIED GATE, all five cases.
     *
     * Case 3 is the one the gate exists for and is stated as its own
     * assertion: NO ENABLED DOMAINS MUST NEVER BECOME ALLOW-ALL.
     *
     * Mutation: `if (domains.isEmpty()) return allow` - the gate, as one line.
     */
    public function test_the_p1_04_domain_gate_holds_in_all_five_cases(): void
    {
        $person = $this->make->user($this->organisation);
        $sales = $this->access->domain($this->organisation, 'sales', 'Sales');

        $this->access->completePath($person, $this->finance);
        $this->access->completePath($person, $sales);

        // 1. Both enabled: both reachable, so the cases below are not passing
        //    against a person who could never see anything.
        $this->assertTrue($this->engine->decide($this->businessQuestion($person, $this->finance))->allowed);
        $this->assertTrue($this->engine->decide($this->businessQuestion($person, $sales))->allowed);

        // 2. ONE disabled: only that one is lost.
        $sales->forceFill(['status' => DomainStatus::Disabled])->save();

        $this->assertTrue(
            $this->engine->decide($this->businessQuestion($person, $this->finance))->allowed,
            'Disabling one domain removed access to another.'
        );

        $denied = $this->engine->decide($this->businessQuestion($person, $sales));
        $this->assertFalse($denied->allowed);
        $this->assertSame(DecisionReason::DeniedDomainDisabled, $denied->reason);

        // 3. ALL disabled - and NO ENABLED DOMAINS AT ALL. Nothing is granted,
        //    never everything.
        $this->finance->forceFill(['status' => DomainStatus::Disabled])->save();

        $this->assertSame(
            0,
            BusinessDomain::query()->where('status', DomainStatus::Enabled->value)->count(),
            'The deployment still has an enabled domain, so case 3 is not being tested.'
        );

        foreach ([$this->finance, $sales] as $domain) {
            $decision = $this->engine->decide($this->businessQuestion($person, $domain));

            $this->assertFalse(
                $decision->allowed,
                'With no enabled domains, access was granted. This is the empty-set defect the '
                .'P1-04 gate exists for: no enabled domains means NO access, never all access.'
            );
            $this->assertSame(DecisionReason::DeniedDomainDisabled, $decision->reason);
        }

        // 4. The entitlement is RETAINED while the domain is disabled - disable
        //    is a state change, not a revocation.
        $this->assertSame(
            2,
            DomainEntitlement::query()->whereNull('ended_at')->count(),
            'Disabling a domain revoked an entitlement. Disable is not a revocation.'
        );

        // 5. RE-ENABLE restores exactly the prior access, with no new grant.
        $this->finance->forceFill(['status' => DomainStatus::Enabled])->save();

        $this->assertTrue(
            $this->engine->decide($this->businessQuestion($person, $this->finance))->allowed,
            'Re-enabling a domain did not restore the access that existed before it was disabled.'
        );

        $this->assertFalse(
            $this->engine->decide($this->businessQuestion($person, $sales))->allowed,
            'Re-enabling one domain restored access to another that is still disabled.'
        );
    }

    /**
     * N-D10. An inactive user is a DENIED REQUEST, not merely an absent row.
     *
     * Mutation: filter listings only. A direct route would stay open.
     */
    public function test_an_inactive_user_is_denied_rather_than_merely_filtered(): void
    {
        $person = $this->make->user($this->organisation);
        $this->access->completePath($person, $this->finance);

        $this->assertTrue($this->engine->decide($this->businessQuestion($person))->allowed);

        $person->forceFill(['status' => UserStatus::Inactive])->save();

        $decision = $this->engine->decide($this->businessQuestion($person->fresh()));

        $this->assertFalse($decision->allowed);
        $this->assertSame(DecisionReason::DeniedInactiveUser, $decision->reason);
    }

    /**
     * N-L11. Deactivate then reactivate returns access EXACTLY.
     *
     * Deactivation is a state change, not a revocation - D-36 - so nothing is
     * ended and there is nothing to re-grant.
     *
     * Mutation: end assignments on deactivation.
     */
    public function test_deactivation_preserves_grants_and_reactivation_returns_them(): void
    {
        $person = $this->make->user($this->organisation);
        $this->access->completePath($person, $this->finance);

        $person->forceFill(['status' => UserStatus::Inactive])->save();

        $this->assertSame(
            1,
            RoleAssignment::query()->whereNull('ended_at')->where('user_id', $person->id)->count(),
            'Deactivation ended a role assignment. A person leaving is exactly when they have the '
            .'most relationships, and destroying them makes the safe action impossible.'
        );

        $person->forceFill(['status' => UserStatus::Active])->save();

        $this->assertTrue(
            $this->engine->decide($this->businessQuestion($person->fresh()))->allowed,
            'Reactivation did not return access exactly.'
        );
    }

    /**
     * N-D8 and N-C3. A MISSING SCOPE GRANTS NOTHING, and never defaults.
     *
     * The two mutations are at two different places - `scope ?? Organisation`
     * in the engine and `scope ?? Domain` in the resolver - so both are named.
     */
    public function test_an_entitlement_with_no_scope_authorises_nothing(): void
    {
        $person = $this->make->user($this->organisation);

        $assignment = $this->access->assignment($person, RoleCode::BusinessUser, $this->organisation);
        $entitlement = $this->access->entitlement($assignment, $this->finance);
        $this->access->ceiling($entitlement, Sensitivity::Restricted);

        $decision = $this->engine->decide($this->businessQuestion($person));

        $this->assertFalse(
            $decision->allowed,
            'An entitlement with no scope authorised a request. A missing scope must never fall '
            .'back to domain or organisation - the widest possible mistake.'
        );
        $this->assertSame(DecisionReason::DeniedScope, $decision->reason);
    }

    /**
     * N-C7. NO CURRENT CEILING FAILS CLOSED. It is never assumed to be
     * standard.
     *
     * A cleared ceiling is a CURRENT standard row; absence is a malformed
     * state, and an assumed default is an invisible permissive default by
     * another name.
     *
     * Mutation: `ceiling ?? Sensitivity::Standard`.
     */
    public function test_an_entitlement_with_no_ceiling_fails_closed(): void
    {
        $person = $this->make->user($this->organisation);

        $assignment = $this->access->assignment($person, RoleCode::BusinessUser, $this->organisation);
        $entitlement = $this->access->entitlement($assignment, $this->finance);
        $this->access->scope($entitlement, ScopeType::Organisation);
        // No ceiling at all.

        $decision = $this->engine->decide($this->businessQuestion($person, null, Sensitivity::Standard));

        $this->assertFalse($decision->allowed);
        $this->assertSame(DecisionReason::DeniedCeilingMissing, $decision->reason);
    }

    /**
     * N-D2. ENGINE FAILURE IS A DENIAL, never an exception some later change
     * turns into a pass.
     *
     * Provoked by making a table the engine must read unreadable, which is the
     * closest this suite can get to an unreachable dependency.
     */
    public function test_an_engine_failure_denies(): void
    {
        $person = $this->make->user($this->organisation);
        $this->access->completePath($person, $this->finance);

        /*
         * N-EN3. THE OPERATIONAL SIGNAL, not only the reason code.
         *
         * A failing engine must not look like an ordinary lack of entitlement.
         *
         * A RECORDING LOGGER, NOT Log::spy(). Mockery reports
         * shouldHaveReceived('info')->with(...) as `info(<Any Arguments>)` -
         * the argument constraint is not applied - so an assertion written that
         * way passes whenever ANY line was logged, which is true in every case
         * the engine denies. It looked like it checked which event fired.
         *
         * The engine is a singleton, so it is rebuilt here AFTER the recording
         * logger is bound; resolving it earlier would hand back the instance
         * built at boot with the real one.
         */
        $events = new RecordingSecurityEventLogger;
        $this->app->instance(SecurityEventLogger::class, $events);
        $this->app->forgetInstance(AccessEngine::class);

        $engine = $this->app->make(AccessEngine::class);

        Schema::drop('entitlement_scopes');

        $decision = $engine->decide($this->businessQuestion($person));

        $this->assertFalse($decision->allowed);
        $this->assertSame(DecisionReason::DeniedEngineFailure, $decision->reason);

        $this->assertContains(
            SecurityEventLogger::ACCESS_ENGINE_FAILED,
            $events->recordedEvents(),
            'An engine failure raised no operational signal, so a broken deployment would look '
            .'exactly like one correctly refusing.'
        );
    }

    /**
     * N-B4. THE ENGINE NEVER READS group_memberships - D-58.
     *
     * Observed from the emitted SQL rather than from the source, because a
     * query built through a variable would not appear in a source scan.
     *
     * Mutation: read the table.
     */
    public function test_the_engine_never_reads_group_memberships(): void
    {
        $person = $this->make->user($this->organisation);
        $this->access->completePath($person, $this->finance);

        $statements = [];
        DB::listen(function ($query) use (&$statements): void {
            $statements[] = strtolower($query->sql);
        });

        $this->engine->decide($this->businessQuestion($person));

        $this->assertNotEmpty($statements, 'The engine ran no queries, so this test proves nothing.');

        foreach ($statements as $sql) {
            $this->assertStringNotContainsString('group_memberships', $sql);
            $this->assertStringNotContainsString('business_domain_owners', $sql);
            $this->assertStringNotContainsString('access_expectation', $sql);
        }
    }

    private function businessQuestion(
        User $user,
        ?BusinessDomain $domain = null,
        Sensitivity $sensitivity = Sensitivity::Standard,
    ): AccessQuestion {
        return AccessQuestion::businessData(
            $user,
            'read',
            ($domain ?? $this->finance)->id,
            new ResourceReference(organisationId: $this->organisation->id),
            $sensitivity,
        );
    }
}
