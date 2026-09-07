<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Modules\Access\Http\Controllers\StepUpController;
use App\Modules\Access\Models\DomainEntitlement;
use App\Modules\Access\Models\EntitlementScope;
use App\Modules\Access\StepUp\PendingStepUp;
use App\Modules\Access\StepUp\StepUpAction;
use App\Modules\Access\StepUp\StepUpService;
use App\Modules\Access\StepUp\StepUpVerification;
use App\Modules\Access\Support\RoleCode;
use App\Modules\Access\Support\ScopeType;
use App\Modules\Organisation\Models\BusinessUnit;
use App\Modules\Organisation\Models\Organisation;
use App\Modules\Organisation\Models\Team;
use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;
use App\Modules\Platform\Identity\AuthenticationFailed;
use App\Modules\Platform\Identity\IdentityProvider;
use App\Modules\Platform\Identity\VerifiedIdentity;
use App\Modules\Platform\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Tests\Support\AccessFactory;
use Tests\Support\OrganisationFactory;
use Tests\TestCase;

/**
 * N-PO1 to N-PO6. THE TWO DEFECTS PRODUCT OWNER TESTING FOUND IN PRODUCTION.
 *
 * Both are the same lesson in different clothes: THE TEST CLIENT IS NOT A
 * BROWSER. A feature test that returns 302 proves the server's intention, not
 * that a browser acts on it; a feature test that posts `['target_id' => 2]`
 * sends an integer where every real form sends "2".
 *
 * Neither defect was reachable by any test in the suite before this file.
 */
final class ProductOwnerTestDefectsTest extends TestCase
{
    use RefreshDatabase;

    private const MICROSOFT = 'https://login.microsoftonline.com/tenant/oauth2/v2.0/authorize?prompt=login';

    private OrganisationFactory $make;

    private AccessFactory $access;

    private Organisation $organisation;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->make = new OrganisationFactory;
        $this->access = new AccessFactory;
        $this->organisation = $this->make->organisation();
        $this->admin = $this->make->user($this->organisation, administrator: true);
    }

    // ------------------------------------------------ DEFECT 2: the 500

    /**
     * N-PO1. A TEAM SCOPE ASSIGNED THE WAY A BROWSER ASSIGNS ONE.
     *
     * THE PRODUCTION 500, EXACTLY:
     *
     *   EntitlementService::assignScope(): Argument #3 ($targetId) must be of
     *   type ?int, string given ... ScopeType::Team, '2'
     *
     * Laravel's `integer` rule VALIDATES; it does not convert. The value stayed
     * a string, assignScope declares ?int, the file is strict_types=1, and the
     * request died in front of the Product Owner on the first scope of test D.
     *
     * The payload here is stringified deliberately. Written as an int - which
     * is what every other test in this suite does, because the test client
     * passes PHP values straight through - this case passes against the defect.
     *
     * Mutation: remove the (int) cast in AccessController::assignScope.
     */
    public function test_a_team_scope_can_be_assigned_with_a_string_target_as_a_browser_sends_it(): void
    {
        [$entitlement, $team] = $this->entitlementWithStructure();

        $response = $this->signedInAs($this->admin)
            ->post("/console/access/entitlements/{$entitlement->id}/scopes", [
                // STRINGS. Every one of them, the way a form body arrives.
                'scope_type' => ScopeType::Team->value,
                'target_id' => (string) $team->id,
            ]);

        $response->assertStatus(302);
        $response->assertSessionHasNoErrors();

        $scope = EntitlementScope::query()->sole();

        $this->assertSame(ScopeType::Team, $scope->scope_type);
        $this->assertSame($team->id, $scope->team_id, 'The team scope did not land on the team that was chosen.');
        $this->assertNull($scope->business_unit_id);
    }

    /**
     * N-PO2. The same, for a business unit - the other scope type that carries
     * a target, and the one a developer fixing only the reported case forgets.
     *
     * Mutation: cast only when the scope type is Team.
     */
    public function test_a_business_unit_scope_can_be_assigned_with_a_string_target(): void
    {
        [$entitlement, , $unit] = $this->entitlementWithStructure();

        $this->signedInAs($this->admin)
            ->post("/console/access/entitlements/{$entitlement->id}/scopes", [
                'scope_type' => ScopeType::BusinessUnit->value,
                'target_id' => (string) $unit->id,
            ])
            ->assertStatus(302)
            ->assertSessionHasNoErrors();

        $scope = EntitlementScope::query()->sole();

        $this->assertSame(ScopeType::BusinessUnit, $scope->scope_type);
        $this->assertSame($unit->id, $scope->business_unit_id);
    }

    /**
     * N-PO3. A scope type that takes NO target still refuses one, and an empty
     * string is not a target.
     *
     * A browser submits "" for an untouched field, and `(int) ""` is 0 - which
     * would become a target id of zero, matching nothing, stored as a
     * contradiction nobody granted.
     *
     * Mutation: cast without the null check, so "" becomes 0.
     */
    public function test_an_empty_target_is_treated_as_no_target(): void
    {
        [$entitlement] = $this->entitlementWithStructure();

        $this->signedInAs($this->admin)
            ->post("/console/access/entitlements/{$entitlement->id}/scopes", [
                'scope_type' => ScopeType::Organisation->value,
                'target_id' => '',
            ])
            ->assertStatus(302)
            ->assertSessionHasNoErrors();

        $scope = EntitlementScope::query()->sole();

        $this->assertSame(ScopeType::Organisation, $scope->scope_type);
        $this->assertNull($scope->team_id, 'An empty target became a real team id.');
        $this->assertNull($scope->business_unit_id, 'An empty target became a real business unit id.');
    }

    /**
     * N-PO4. THE WHOLE RECORD PAGE STILL RENDERS, through every state the
     * Product Owner walks in C and D.
     *
     * The 500 was reported against this URL, so the page is exercised in each
     * shape rather than only in the one that failed.
     */
    public function test_the_record_page_renders_through_every_state_of_the_test_script(): void
    {
        $person = $this->make->user($this->organisation);
        $assignment = $this->access->assignment($person, RoleCode::Executive, $this->organisation);

        $unit = $this->make->businessUnit($this->organisation);
        $team = $this->make->team($this->make->department($unit));
        $enabled = $this->access->domain($this->organisation, 'finance', 'Finance');
        $disabled = $this->access->domain($this->organisation, 'sales', 'Sales', 'disabled');

        $url = "/console/access/assignments/{$assignment->id}";

        // 1. No entitlement at all.
        $this->signedInAs($this->admin)->get($url)->assertOk();

        // 2. An entitlement with no scope - the incomplete grant path.
        $entitlement = $this->access->entitlement($assignment, $enabled);
        $this->signedInAs($this->admin)->get($url)->assertOk();

        // 3. A team scope, assigned through the route the way a browser does.
        $this->signedInAs($this->admin)->post("/console/access/entitlements/{$entitlement->id}/scopes", [
            'scope_type' => ScopeType::Team->value,
            'target_id' => (string) $team->id,
        ])->assertSessionHasNoErrors();
        $this->signedInAs($this->admin)->get($url)->assertOk();

        // 4. Several scopes at once - they union.
        $this->signedInAs($this->admin)->post("/console/access/entitlements/{$entitlement->id}/scopes", [
            'scope_type' => ScopeType::BusinessUnit->value,
            'target_id' => (string) $unit->id,
        ])->assertSessionHasNoErrors();
        $this->signedInAs($this->admin)->get($url)->assertOk();

        // 5. A disabled domain alongside them.
        $this->access->entitlement($assignment, $disabled);
        $this->signedInAs($this->admin)->get($url)->assertOk();

        $this->assertSame(2, EntitlementScope::query()->whereNull('ended_at')->count());
    }

    // ------------------------------- DEFECT 1: the departure for Microsoft

    /**
     * N-PO5. AN INERTIA DEPARTURE TELLS THE BROWSER TO LEAVE, rather than
     * redirecting an XHR that cannot.
     *
     * THE PRODUCTION DEFECT: the card posts over XHR, an XHR FOLLOWS a 302
     * itself, so the browser fetched Microsoft's sign-in page in the background,
     * Inertia discarded it as a non-Inertia payload, and the page sat there.
     * No error, no navigation - "clicking Continue to Microsoft does nothing".
     *
     * A browser trace confirmed it and is the real evidence; this is the part
     * that can fail in CI. 409 + X-Inertia-Location is what makes the Inertia
     * client perform a top-level navigation.
     *
     * Mutation: return the redirect unconditionally.
     */
    public function test_an_inertia_departure_returns_a_location_the_browser_must_follow(): void
    {
        $reference = $this->beginAStepUp();
        $this->fakeProvider();

        $response = $this->inTheStepUpSession($reference)
            ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => ''])
            ->post("/console/access/step-up/{$reference}");

        $response->assertStatus(409);
        $response->assertHeader('X-Inertia-Location', self::MICROSOFT);

        // And the return trip is still armed: the reference was stored in the
        // session, and nothing has been consumed.
        $this->assertSame($reference, session(StepUpController::SESSION_REFERENCE));
        $this->assertNull(PendingStepUp::query()->sole()->consumed_at);
    }

    /**
     * ...and a NON-Inertia caller still gets the ordinary redirect.
     *
     * The fix must not break a plain form post, and this is the half that stops
     * somebody "simplifying" the branch away in the other direction.
     *
     * Mutation: return Inertia::location unconditionally.
     */
    public function test_a_plain_form_post_still_receives_an_ordinary_redirect(): void
    {
        $reference = $this->beginAStepUp();
        $this->fakeProvider();

        $this->inTheStepUpSession($reference)
            ->post("/console/access/step-up/{$reference}")
            ->assertStatus(302)
            ->assertRedirect(self::MICROSOFT);
    }

    /**
     * N-PO6. THE DEPARTURE IS STILL GUARDED. D-73 is not relaxed by any of this.
     *
     * A reference belonging to somebody else, or to another session, is refused
     * before the provider is ever asked - so the fix changed how the browser is
     * told to leave, and nothing about who may leave.
     *
     * Mutation: resolve after building the departure.
     */
    public function test_the_departure_still_refuses_a_reference_that_is_not_yours(): void
    {
        $reference = $this->beginAStepUp();
        $this->fakeProvider();

        $intruder = $this->make->user($this->organisation, administrator: true);

        $this->withSession([
            EnsureSessionIsCurrent::SESSION_USER_ID => $intruder->id,
            EnsureSessionIsCurrent::SESSION_AUTHENTICATED_AT => now()->toIso8601String(),
        ])
            ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => ''])
            ->post("/console/access/step-up/{$reference}")
            ->assertHeaderMissing('X-Inertia-Location');

        $this->assertNull(
            session(StepUpController::SESSION_REFERENCE),
            'A reference belonging to somebody else was armed for the return trip.'
        );
    }

    // ---------------------------------------------------------------- helpers

    /** @return array{0: DomainEntitlement, 1: Team, 2: BusinessUnit} */
    private function entitlementWithStructure(): array
    {
        $person = $this->make->user($this->organisation);
        $assignment = $this->access->assignment($person, RoleCode::Executive, $this->organisation);
        $domain = $this->access->domain($this->organisation, 'finance', 'Finance');
        $entitlement = $this->access->entitlement($assignment, $domain);

        $unit = $this->make->businessUnit($this->organisation);
        $team = $this->make->team($this->make->department($unit));

        return [$entitlement, $team, $unit];
    }

    private function beginAStepUp(): string
    {
        return app(StepUpService::class)->begin(
            $this->admin,
            session()->getId(),
            StepUpAction::GrantSystemAdministrator,
            ['subject_user_id' => $this->make->user($this->organisation)->id, 'role_code' => RoleCode::SystemAdministrator->value],
        );
    }

    private function inTheStepUpSession(string $reference): self
    {
        $bound = PendingStepUp::query()
            ->where('reference_hash', PendingStepUp::hashFor($reference))
            ->sole()
            ->session_id;

        return $this->withCookie((string) config('session.cookie'), $bound)
            ->withSession([
                EnsureSessionIsCurrent::SESSION_USER_ID => $this->admin->id,
                EnsureSessionIsCurrent::SESSION_AUTHENTICATED_AT => now()->toIso8601String(),
            ]);
    }

    /** A provider that hands back a departure URL without leaving the test. */
    private function fakeProvider(): void
    {
        $this->app->bind(IdentityProvider::class, fn (): IdentityProvider => new class implements IdentityProvider
        {
            public function key(): string
            {
                return 'microsoft';
            }

            public function isConfigured(): bool
            {
                return true;
            }

            public function beginAuthorization(): RedirectResponse
            {
                return new RedirectResponse('/');
            }

            public function beginStepUpAuthorization(string $returnUri): RedirectResponse
            {
                return new RedirectResponse(ProductOwnerTestDefectsTest::microsoftUrl());
            }

            public function completeStepUpAuthorization(Request $request): StepUpVerification
            {
                throw AuthenticationFailed::protocol('not_used');
            }

            public function completeAuthorization(Request $request): VerifiedIdentity
            {
                throw AuthenticationFailed::protocol('not_used');
            }
        });
    }

    public static function microsoftUrl(): string
    {
        return self::MICROSOFT;
    }

    private function signedInAs(User $user): self
    {
        return $this->withSession([
            EnsureSessionIsCurrent::SESSION_USER_ID => $user->id,
            EnsureSessionIsCurrent::SESSION_AUTHENTICATED_AT => now()->toIso8601String(),
        ]);
    }
}
