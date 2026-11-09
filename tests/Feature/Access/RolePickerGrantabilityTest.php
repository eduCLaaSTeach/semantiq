<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Modules\Access\Http\Controllers\AccessController;
use App\Modules\Access\Models\RoleAssignment;
use App\Modules\Access\Support\RoleCatalogue;
use App\Modules\Access\Support\RoleCode;
use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;
use App\Modules\Platform\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Support\AccessFactory;
use Tests\Support\OrganisationFactory;
use Tests\TestCase;

/**
 * THE ROLE PICKER OFFERS ONLY WHAT THE ADMINISTRATOR CAN ACTUALLY GRANT.
 *
 * The final CL-12 correction. After the amended ruling gave the Organisation
 * Administrator the full administration surface, the Roles & Access picker
 * still listed every role in the catalogue - including System Administrator,
 * which they can never grant. The backend refused the submit correctly, so
 * nothing was ever escalated; what was wrong was the offer.
 *
 * A CONTROL THAT PRESENTS AN OPTION IT WILL NOT HONOUR teaches administrators
 * that refusals are arbitrary, and it contradicted the role summary added in
 * the same unit, which states "Cannot grant System Administrator" in writing.
 *
 * WHAT THIS FILE IS CAREFUL NOT TO CLAIM. Filtering a dropdown enforces
 * nothing. The picker is a disclosure, not a gate, and the crafted POST that
 * never touches the screen is still refused - asserted here, and again by the
 * service under its own locks in RouteAuthorizationMatrixTest. If the backend
 * refusal were ever removed, a filtered picker would hide the hole rather than
 * close it, so the refusal is tested independently of the list.
 *
 * ONE CALCULATION, TWO CALLERS. The picker and the pre-flight refusal both
 * read AccessController::grantableRolesFor(), so they cannot disagree about
 * who may grant what. The mutation that hard-codes a picker list is caught
 * because the two stop agreeing.
 */
final class RolePickerGrantabilityTest extends TestCase
{
    use RefreshDatabase;

    /** What an Organisation Administrator may offer. No system_administrator. */
    private const ORGANISATION_ADMINISTRATOR_PICKER = [
        'organisation_administrator',
        'executive',
        'domain_owner',
        'manager',
        'business_user',
        'auditor',
    ];

    private OrganisationFactory $make;

    private AccessFactory $access;

    protected function setUp(): void
    {
        parent::setUp();

        $this->make = new OrganisationFactory;
        $this->access = new AccessFactory;
    }

    // =================================================================
    // G1 - G4. What each administrator is offered.
    // =================================================================

    /** G1. The Organisation Administrator picker, as an EQUALITY. */
    public function test_an_organisation_administrator_is_offered_exactly_the_grantable_roles(): void
    {
        $this->assertSame(
            self::ORGANISATION_ADMINISTRATOR_PICKER,
            $this->pickerFor(RoleCode::OrganisationAdministrator),
            'The Organisation Administrator role picker is not exactly the roles they may grant.'
        );
    }

    /**
     * G2. SYSTEM ADMINISTRATOR IS ABSENT, stated on its own.
     *
     * G1 covers it by equality. This exists because it is the specific defect
     * being corrected, and a failed array comparison does not say so in words.
     */
    public function test_system_administrator_is_not_offered_to_an_organisation_administrator(): void
    {
        $this->assertNotContains(
            RoleCode::SystemAdministrator->value,
            $this->pickerFor(RoleCode::OrganisationAdministrator),
            'The picker offers an Organisation Administrator the System Administrator role, '
            .'which the backend will refuse and the role summary says they cannot grant.'
        );
    }

    /**
     * G3. AND A SYSTEM ADMINISTRATOR STILL SEES ALL SEVEN.
     *
     * The half that stops G1 and G2 passing against a picker that filters
     * everything, or renders nothing at all.
     */
    public function test_a_system_administrator_is_offered_every_role(): void
    {
        $picker = $this->pickerFor(RoleCode::SystemAdministrator);

        $this->assertSame(
            array_map(static fn (RoleCode $role): string => $role->value, RoleCatalogue::roles()),
            $picker,
            'The System Administrator picker is not the whole catalogue, in catalogue order.'
        );

        $this->assertContains(
            RoleCode::SystemAdministrator->value,
            $picker,
            'A System Administrator can no longer grant their own role.'
        );

        $this->assertCount(7, $picker, 'The catalogue is no longer seven roles.');
    }

    /** G4. The summaries are still RoleCode::summary(), not a second copy. */
    public function test_the_picker_summaries_come_from_the_role_summary(): void
    {
        $roles = $this->rolesPropFor(RoleCode::SystemAdministrator);

        $this->assertNotEmpty($roles, 'The picker sent nothing, so this guard asserts nothing.');

        foreach ($roles as $role) {
            $this->assertSame(
                RoleCode::from($role['value'])->summary(),
                $role['summary'],
                "The picker is sending a summary for [{$role['value']}] that is not "
                .'RoleCode::summary().'
            );

            $this->assertSame(RoleCode::from($role['value'])->label(), $role['label']);
        }
    }

    // =================================================================
    // G5 - G7. The backend is still the authority.
    // =================================================================

    /**
     * G5. THE CRAFTED REQUEST IS STILL REFUSED, AND NO ROW IS WRITTEN.
     *
     * The assertion that stops the filtered picker being mistaken for a
     * control. This request never touches the screen, so the dropdown's
     * contents are irrelevant to it.
     */
    public function test_a_crafted_request_for_system_administrator_is_still_refused(): void
    {
        $organisation = $this->make->organisation();
        $actor = $this->make->user($organisation);
        $target = $this->make->user($organisation);

        $this->access->assignment($actor, RoleCode::OrganisationAdministrator, $organisation);

        $this->signedInAs($actor)->post('/console/access/assignments', [
            'user_id' => $target->id,
            'role_code' => RoleCode::SystemAdministrator->value,
        ]);

        $this->assertSame(
            0,
            RoleAssignment::query()
                ->where('user_id', $target->id)
                ->where('role_code', RoleCode::SystemAdministrator->value)
                ->count(),
            'An Organisation Administrator created a System Administrator assignment. The '
            .'picker is a disclosure; the refusal is the control, and it did not hold.'
        );
    }

    /**
     * G6. AND IT IS REFUSED BEFORE STEP-UP, not after.
     *
     * Sending somebody to Microsoft to re-authenticate and only then refusing
     * them is a trap. A redirect into the step-up flow here would mean the
     * escalation guard had moved behind it.
     */
    public function test_the_refusal_happens_before_step_up_is_offered(): void
    {
        $organisation = $this->make->organisation();
        $actor = $this->make->user($organisation);
        $target = $this->make->user($organisation);

        $this->access->assignment($actor, RoleCode::OrganisationAdministrator, $organisation);

        $response = $this->signedInAs($actor)->post('/console/access/assignments', [
            'user_id' => $target->id,
            'role_code' => RoleCode::SystemAdministrator->value,
        ]);

        $this->assertStringNotContainsString(
            'step-up',
            (string) $response->headers->get('Location'),
            'An Organisation Administrator asking for System Administrator was sent to step-up '
            .'and would only then be refused.'
        );
    }

    /**
     * G7. ORGANISATION ADMINISTRATOR -> ORGANISATION ADMINISTRATOR STILL WORKS,
     * and still goes through step-up.
     *
     * The Product Owner preserved this deliberately. The correction must narrow
     * the picker without closing a grant that is allowed - so this asserts the
     * request reaches the step-up flow rather than a refusal.
     */
    public function test_an_organisation_administrator_may_still_grant_that_role_through_step_up(): void
    {
        $organisation = $this->make->organisation();
        $actor = $this->make->user($organisation);
        $target = $this->make->user($organisation);

        $this->access->assignment($actor, RoleCode::OrganisationAdministrator, $organisation);

        $response = $this->signedInAs($actor)->post('/console/access/assignments', [
            'user_id' => $target->id,
            'role_code' => RoleCode::OrganisationAdministrator->value,
        ]);

        $this->assertStringContainsString(
            'step-up',
            (string) $response->headers->get('Location'),
            'An Organisation Administrator can no longer begin granting that role. The picker '
            .'correction has closed a grant the Product Owner preserved.'
        );

        $this->assertContains(
            RoleCode::OrganisationAdministrator->value,
            $this->pickerFor(RoleCode::OrganisationAdministrator),
            'The role is grantable but is not offered, so it could only be granted by crafting '
            .'a request.'
        );
    }

    // =================================================================
    // G8 - G10. Properties of the calculation itself.
    // =================================================================

    /**
     * G8. MULTIPLE CURRENT ROLES GIVE THE UNION, WITHOUT DUPLICATES.
     *
     * Both administrator roles grant Executive, Domain Owner, Manager,
     * Business User, Auditor and Organisation Administrator, so a naive
     * array_merge lists each of those TWICE. Holding both is the only
     * combination in the catalogue that exercises deduplication at all, which
     * is why this case holds both rather than something easier to set up.
     *
     * The order is the catalogue's, not the order the assignment rows happen
     * to come back in.
     */
    public function test_multiple_held_roles_give_the_union_with_no_duplicates(): void
    {
        $organisation = $this->make->organisation();
        $actor = $this->make->user($organisation);

        $this->access->assignment($actor, RoleCode::SystemAdministrator, $organisation);
        $this->access->assignment($actor, RoleCode::OrganisationAdministrator, $organisation);

        $picker = $this->pickerFrom($this->signedInAs($actor)->get('/console/access'));

        $this->assertSame(
            array_values(array_unique($picker)),
            $picker,
            'The picker lists a role twice for somebody holding two roles whose grantable sets '
            .'overlap. The merged list is not being deduplicated.'
        );

        $this->assertSame(
            array_map(static fn (RoleCode $role): string => $role->value, RoleCatalogue::roles()),
            $picker,
            'Holding both administrator roles did not produce the union of what each may grant.'
        );
    }

    /**
     * G9. AN ENDED ASSIGNMENT CONFERS NOTHING.
     *
     * Revoked authority must stop granting immediately; a stale row keeping a
     * picker open would be a privilege that outlived its revocation.
     *
     * THE ACTOR ALSO HOLDS A CURRENT ORGANISATION ADMINISTRATOR ROLE, and that
     * is not incidental. Somebody whose only assignment has ended holds no
     * AccessAdmin and cannot reach this screen at all, so the picker could not
     * be read - an empty list obtained that way would be a page that never
     * rendered. Giving them a current role they genuinely hold means the
     * screen renders, and the ENDED System Administrator row is the only thing
     * that could add system_administrator to it.
     */
    public function test_an_ended_assignment_does_not_confer_picker_authority(): void
    {
        $organisation = $this->make->organisation();
        $actor = $this->make->user($organisation);

        $this->access->assignment($actor, RoleCode::OrganisationAdministrator, $organisation);
        $this->access->assignment(
            $actor,
            RoleCode::SystemAdministrator,
            $organisation,
            now()->subDay()->toDateTimeString(),
        );

        $picker = $this->pickerFrom($this->signedInAs($actor)->get('/console/access'));

        $this->assertNotContains(
            RoleCode::SystemAdministrator->value,
            $picker,
            'An ENDED System Administrator assignment still lets this administrator offer the '
            .'System Administrator role. Revoked authority is still granting.'
        );

        $this->assertSame(
            self::ORGANISATION_ADMINISTRATOR_PICKER,
            $picker,
            'The ended assignment changed the picker.'
        );
    }

    /**
     * G10. SOMEBODY HOLDING NO CURRENT ROLE MAY GRANT NOTHING.
     *
     * ASKED OF THE CALCULATION DIRECTLY, and that needs explaining rather than
     * hiding. A user with no role holds no AccessAdmin, so /console/access
     * refuses them before any picker is built - there is no rendered screen to
     * read, and asserting an empty list from a refused request would prove
     * only that the route works.
     *
     * So this reaches grantableRolesFor() by reflection. It is the one case in
     * this file that does not go through the screen, and it is here because
     * "no role grants nothing" is a property of the calculation that should
     * not go unasserted merely because the HTTP path cannot express it.
     */
    public function test_a_user_holding_no_role_may_grant_nothing(): void
    {
        $organisation = $this->make->organisation();

        $holder = $this->make->user($organisation);
        $this->access->assignment($holder, RoleCode::OrganisationAdministrator, $organisation);

        $nobody = $this->make->user($organisation);

        $method = (new \ReflectionClass(AccessController::class))
            ->getMethod('grantableRolesFor');

        $controller = app(AccessController::class);

        $this->assertNotEmpty(
            $method->invoke($controller, $holder),
            'The calculation returned nothing for somebody who genuinely may grant roles, so '
            .'the empty result below would prove nothing.'
        );

        $this->assertSame(
            [],
            $method->invoke($controller, $nobody),
            'Somebody holding no role was found able to grant roles.'
        );
    }

    /**
     * G11. THE CALCULATION AND THE CATALOGUE AGREE, FOR EVERY ROLE.
     *
     * THE GUARD THAT CATCHES A HARD-CODED SECOND LIST. G1 and G3 pass against
     * any list somebody types that happens to match those two cases; this one
     * holds grantableRolesFor() to RoleCatalogue::grantableBy() for all seven
     * roles, so the controller cannot grow its own opinion about who may grant
     * what.
     *
     * ASKED OF THE CALCULATION, NOT THE SCREEN, and deliberately. Only the two
     * administrator roles hold AccessAdmin, so the other five cannot reach
     * /console/access at all and have no rendered picker to compare. Looping
     * over the screen would silently cover two roles while appearing to cover
     * seven - the shape CLAUDE.md section 2 names. The rendered equalities
     * stay in G1 and G3, where they can actually be read.
     */
    public function test_the_calculation_matches_the_catalogue_for_every_role(): void
    {
        $organisation = $this->make->organisation();

        $method = (new \ReflectionClass(AccessController::class))->getMethod('grantableRolesFor');
        $controller = app(AccessController::class);

        foreach (RoleCatalogue::roles() as $role) {
            $actor = $this->make->user($organisation);
            $this->access->assignment($actor, $role, $organisation);

            $expected = array_values(array_filter(
                RoleCatalogue::roles(),
                static fn (RoleCode $candidate): bool => in_array(
                    $candidate,
                    RoleCatalogue::grantableBy($role),
                    true
                ),
            ));

            $this->assertSame(
                $expected,
                $method->invoke($controller, $actor),
                "What [{$role->value}] may grant does not match "
                ."RoleCatalogue::grantableBy({$role->value}). The controller has its own list."
            );
        }
    }

    // =================================================================
    // Helpers.
    // =================================================================

    /** @return list<string> */
    private function pickerFor(RoleCode $role): array
    {
        $organisation = $this->make->organisation();
        $actor = $this->make->user($organisation);
        $this->access->assignment($actor, $role, $organisation);

        return $this->pickerFrom($this->signedInAs($actor)->get('/console/access'));
    }

    /** @return list<array<string, string>> */
    private function rolesPropFor(RoleCode $role): array
    {
        $organisation = $this->make->organisation();
        $actor = $this->make->user($organisation);
        $this->access->assignment($actor, $role, $organisation);

        $response = $this->signedInAs($actor)->get('/console/access');
        $response->assertOk();

        return $response->viewData('page')['props']['roles'] ?? [];
    }

    /** @return list<string> */
    private function pickerFrom(TestResponse $response): array
    {
        $response->assertOk();

        return array_values(array_map(
            static fn (array $role): string => $role['value'],
            $response->viewData('page')['props']['roles'] ?? []
        ));
    }

    private function signedInAs(User $user): self
    {
        return $this->withSession([
            EnsureSessionIsCurrent::SESSION_USER_ID => $user->id,
            EnsureSessionIsCurrent::SESSION_AUTHENTICATED_AT => now()->toIso8601String(),
        ]);
    }
}
