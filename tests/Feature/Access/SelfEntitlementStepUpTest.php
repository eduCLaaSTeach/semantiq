<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Modules\Access\Http\Controllers\StepUpController;
use App\Modules\Access\Models\DomainEntitlement;
use App\Modules\Access\Models\RoleAssignment;
use App\Modules\Access\Services\EntitlementService;
use App\Modules\Access\StepUp\PendingStepUp;
use App\Modules\Access\StepUp\StepUpAction;
use App\Modules\Access\StepUp\StepUpVerification;
use App\Modules\Access\Support\RoleCode;
use App\Modules\Domains\Models\BusinessDomain;
use App\Modules\Organisation\Models\Organisation;
use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;
use App\Modules\Platform\Identity\AuthenticationFailed;
use App\Modules\Platform\Identity\IdentityProvider;
use App\Modules\Platform\Identity\VerifiedIdentity;
use App\Modules\Platform\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Tests\Support\AccessFactory;
use Tests\Support\OrganisationFactory;
use Tests\TestCase;

/**
 * N-SE1 to N-SE9. D-73, THE HALF THAT WAS MISSING.
 *
 * The approved rule is that self-granting any role OR entitlement requires
 * step-up. The role half shipped; the entitlement half did not, so an Access
 * Administrator could widen their OWN reach into a business domain with no
 * re-authentication at all - found by the Product Owner at Gate C, not by any
 * test here, which is why every case below exists.
 *
 * SAME ONE OF THE FIVE APPROVED ACTIONS. StepUpAction::SelfGrant covers both
 * halves. There is no sixth action and none is added.
 */
final class SelfEntitlementStepUpTest extends TestCase
{
    use RefreshDatabase;

    private OrganisationFactory $make;

    private AccessFactory $access;

    private Organisation $organisation;

    private User $actor;

    private RoleAssignment $ownAssignment;

    private BusinessDomain $domain;

    private FakeStepUpProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->make = new OrganisationFactory;
        $this->access = new AccessFactory;

        $this->organisation = $this->make->organisation();
        $this->actor = $this->make->user($this->organisation, administrator: true);

        // The administrator's OWN business-user assignment - the thing they
        // must not be able to widen without re-authenticating.
        $this->ownAssignment = $this->access->assignment(
            $this->actor,
            RoleCode::BusinessUser,
            $this->organisation,
        );

        $this->domain = $this->access->domain($this->organisation, 'finance', 'Finance');

        $this->provider = new FakeStepUpProvider;
        $this->app->bind(IdentityProvider::class, fn (): IdentityProvider => $this->provider);
    }

    /**
     * N-SE1. GRANTING SOMEBODY ELSE AN ENTITLEMENT IS ORDINARY WORK.
     *
     * The non-vacuous half. Without it, a controller that sent EVERY
     * entitlement grant to step-up would pass every other case in this file
     * while making the screen unusable.
     *
     * Mutation: send every grant to step-up regardless of who it is for.
     */
    public function test_granting_another_person_an_entitlement_does_not_require_step_up(): void
    {
        $other = $this->make->user($this->organisation);
        $theirs = $this->access->assignment($other, RoleCode::BusinessUser, $this->organisation);

        $this->signedInAs($this->actor)
            ->post("/console/access/assignments/{$theirs->id}/entitlements", [
                'business_domain_id' => $this->domain->id,
            ])
            ->assertRedirect(route('access.show', $theirs->id));

        $this->assertSame(
            1,
            DomainEntitlement::query()->where('role_assignment_id', $theirs->id)->count(),
            'An ordinary grant to another person was blocked.'
        );

        $this->assertSame(
            0,
            PendingStepUp::query()->count(),
            'A step-up was demanded for a grant that is not a self-grant.'
        );
    }

    /**
     * N-SE2 and N-SE3. A SELF-GRANT GOES TO STEP-UP, AND NOTHING IS WRITTEN
     * BEFORE IT.
     *
     * The blocker itself. The entitlement must not exist at any point between
     * the request and a proven fresh authentication.
     *
     * Mutation: call EntitlementService::grant directly, the way the first
     * version did.
     */
    public function test_a_self_grant_redirects_to_step_up_and_creates_nothing_yet(): void
    {
        $response = $this->signedInAs($this->actor)
            ->post("/console/access/assignments/{$this->ownAssignment->id}/entitlements", [
                'business_domain_id' => $this->domain->id,
            ]);

        $pending = PendingStepUp::query()->sole();
        $reference = $this->referenceFrom($response);

        $response->assertRedirect(route('access.step-up.begin', ['reference' => $reference]));

        // The plaintext never reached the table - only its hash did.
        $this->assertSame(PendingStepUp::hashFor($reference), $pending->reference_hash);

        $this->assertSame(
            0,
            DomainEntitlement::query()->count(),
            'A self-granted domain entitlement was created BEFORE the administrator re-authenticated.'
        );

        // The confirmed action, and the EXACT target, held server-side.
        $this->assertSame(StepUpAction::SelfGrant, $pending->action);
        $this->assertSame($this->ownAssignment->id, $pending->role_assignment_id);
        $this->assertSame($this->domain->id, $pending->business_domain_id);
        $this->assertSame($this->actor->id, $pending->user_id);
    }

    /**
     * N-SE4. A COMPLETED STEP-UP CREATES EXACTLY THE INTENDED ENTITLEMENT.
     *
     * The other non-vacuous half: without it, a controller that refused every
     * self-grant outright would satisfy every negative case here.
     */
    public function test_a_completed_step_up_creates_exactly_the_confirmed_entitlement(): void
    {
        $this->completeAJourney();

        $entitlement = DomainEntitlement::query()->sole();

        $this->assertSame($this->ownAssignment->id, $entitlement->role_assignment_id);
        $this->assertSame($this->domain->id, $entitlement->business_domain_id);
        $this->assertNull($entitlement->ended_at);
        $this->assertSame($this->actor->id, $entitlement->granted_by_user_id);

        // And the reference was consumed in the same breath.
        $this->assertNotNull(PendingStepUp::query()->sole()->consumed_at);
    }

    /**
     * N-SE5. A CANCELLED, FAILED OR EXPIRED STEP-UP CREATES NO ENTITLEMENT.
     *
     * Three shapes, each proved on its own, because a developer closing one
     * would not necessarily close the others.
     *
     * Mutation: perform the action anyway on the way out; extend an expired
     * reference on return.
     */
    public function test_no_entitlement_survives_a_cancelled_failed_or_expired_step_up(): void
    {
        // 1. The administrator cancelled at Microsoft, or the provider errored -
        //    the provider reports both the same way.
        $reference = $this->beginASelfGrant();
        $this->bindProvider(fails: true);

        $this->returnFromMicrosoft($reference);

        $this->assertSame(0, DomainEntitlement::query()->count(), 'A cancelled step-up granted a domain.');
        $this->assertSame('provider_error', PendingStepUp::query()->sole()->outcome);

        // 2. The provider returned, but its auth_time is not fresh - the
        //    replay-shaped case.
        PendingStepUp::query()->delete();
        $reference = $this->beginASelfGrant();
        $this->bindProvider(authenticatedAt: now()->subHours(3));

        $this->returnFromMicrosoft($reference);

        $this->assertSame(0, DomainEntitlement::query()->count(), 'A stale authentication granted a domain.');
        $this->assertSame('stale_freshness', PendingStepUp::query()->sole()->outcome);

        // 3. The confirmation was left sitting and expired.
        PendingStepUp::query()->delete();
        $reference = $this->beginASelfGrant();
        $this->bindProvider();

        Carbon::setTestNow(now()->addMinutes(PendingStepUp::LIFETIME_MINUTES + 1));

        try {
            $this->returnFromMicrosoft($reference);
        } finally {
            Carbon::setTestNow();
        }

        $this->assertSame(0, DomainEntitlement::query()->count(), 'An expired step-up granted a domain.');
        $this->assertSame('expired', PendingStepUp::query()->sole()->outcome);
    }

    /**
     * N-SE6. NOTHING IN THE BROWSER CAN SWITCH THE ASSIGNMENT OR THE DOMAIN
     * AFTER THE CONFIRMATION HAS BEGUN.
     *
     * The whole reason the target is stored server-side. The return trip goes
     * through the browser, so everything in it is attacker-controlled.
     *
     * Mutation: read business_domain_id (or role_assignment_id) from the
     * request in StepUpController::perform.
     */
    public function test_no_request_parameter_can_substitute_the_assignment_or_the_domain(): void
    {
        $otherPerson = $this->make->user($this->organisation);
        $otherAssignment = $this->access->assignment($otherPerson, RoleCode::BusinessUser, $this->organisation);
        $otherDomain = $this->access->domain($this->organisation, 'people', 'People');

        $reference = $this->beginASelfGrant();
        $this->bindProvider();

        // Every field the stored row holds, offered again in the return URL
        // with a different answer.
        $this->returnFromMicrosoft($reference, [
            'role_assignment_id' => $otherAssignment->id,
            'business_domain_id' => $otherDomain->id,
            'subject_user_id' => $otherPerson->id,
            'organisation_id' => $this->make->organisation()->id,
            'action' => StepUpAction::GrantSystemAdministrator->value,
            'role_code' => RoleCode::SystemAdministrator->value,
        ]);

        $entitlement = DomainEntitlement::query()->sole();

        $this->assertSame(
            $this->ownAssignment->id,
            $entitlement->role_assignment_id,
            'A request parameter substituted the role assignment after the confirmation began.'
        );
        $this->assertSame(
            $this->domain->id,
            $entitlement->business_domain_id,
            'A request parameter substituted the business domain after the confirmation began.'
        );

        // And nothing else happened either.
        $this->assertSame(
            0,
            RoleAssignment::query()
                ->where('user_id', $otherPerson->id)
                ->where('role_code', RoleCode::SystemAdministrator->value)
                ->count(),
            'A request parameter turned a self-entitlement step-up into a role grant.'
        );
    }

    /**
     * N-SE7. ONE COMPLETED SELF-GRANT CANNOT CREATE A SECOND ENTITLEMENT.
     *
     * One step-up authorises one action, once. A replay of the same reference
     * is refused, and the successful action is not repeated.
     *
     * Mutation: leave the reference reusable after a successful grant.
     */
    public function test_one_completed_self_grant_cannot_create_a_second_entitlement(): void
    {
        $reference = $this->completeAJourney();

        $this->assertSame(1, DomainEntitlement::query()->count());

        // The same reference, returned a second time.
        $this->returnFromMicrosoft($reference)->assertRedirect(route('access.index'));

        $this->assertSame(
            1,
            DomainEntitlement::query()->count(),
            'A replayed step-up reference created a second domain entitlement.'
        );
    }

    /**
     * N-SE8. THE ROUTE CANNOT BE TALKED OUT OF D-73.
     *
     * No field a browser can send makes the route skip the step-up: not a
     * flag that looks like one, not a duplicate id, not an attempt to name
     * somebody else's assignment in the body while addressing your own in the
     * path.
     *
     * Mutation: trust any request field when deciding whether this is a
     * self-grant.
     */
    public function test_no_crafted_request_makes_the_route_skip_step_up(): void
    {
        $other = $this->make->user($this->organisation);
        $theirs = $this->access->assignment($other, RoleCode::BusinessUser, $this->organisation);

        $crafted = [
            ['business_domain_id' => $this->domain->id, 'step_up' => 1],
            ['business_domain_id' => $this->domain->id, 'stepped_up' => 'true'],
            ['business_domain_id' => $this->domain->id, 'confirmed' => 'yes'],
            ['business_domain_id' => $this->domain->id, 'user_id' => $other->id],
            ['business_domain_id' => $this->domain->id, 'role_assignment_id' => $theirs->id],
            ['business_domain_id' => $this->domain->id, 'reference' => 'anything'],
        ];

        foreach ($crafted as $index => $payload) {
            PendingStepUp::query()->delete();

            $this->signedInAs($this->actor)
                ->post("/console/access/assignments/{$this->ownAssignment->id}/entitlements", $payload);

            $this->assertSame(
                0,
                DomainEntitlement::query()->count(),
                "Crafted payload #{$index} created a self-granted entitlement with no step-up."
            );

            $this->assertSame(
                StepUpAction::SelfGrant,
                PendingStepUp::query()->sole()->action,
                "Crafted payload #{$index} avoided the self-grant step-up."
            );
        }
    }

    /**
     * ...and the OTHER bypass: the service is not the D-73 boundary and does
     * not pretend to be.
     *
     * EntitlementService::grant writes when it is called - that is its job, and
     * it is what the step-up return calls. The guarantee is that the ROUTE is
     * the only way in from a browser, so this states plainly where the boundary
     * is rather than leaving a reader to assume the service holds it.
     */
    public function test_the_route_is_the_step_up_boundary_and_the_service_says_so(): void
    {
        // Called directly - as the step-up return itself calls it - it writes.
        app(EntitlementService::class)->grant($this->ownAssignment, $this->domain, $this->actor);

        $this->assertSame(1, DomainEntitlement::query()->count());

        // And that is exactly why the route must not call it for a self-grant
        // before the confirmation: nothing downstream will stop it.
        DomainEntitlement::query()->delete();

        $this->signedInAs($this->actor)
            ->post("/console/access/assignments/{$this->ownAssignment->id}/entitlements", [
                'business_domain_id' => $this->domain->id,
            ]);

        $this->assertSame(0, DomainEntitlement::query()->count());
    }

    /**
     * N-SE9. A SELF-GRANT THAT WOULD BE REFUSED IS REFUSED NOW, NOT AFTER A
     * TRIP TO MICROSOFT.
     *
     * The N-B9 lesson applied rather than learned again: a confirmation
     * somebody can never complete is a trap.
     *
     * Mutation: begin the step-up first and let the service refuse afterwards.
     */
    public function test_a_self_grant_that_cannot_succeed_is_refused_before_step_up(): void
    {
        // Already held.
        $this->access->entitlement($this->ownAssignment, $this->domain);

        $this->signedInAs($this->actor)
            ->post("/console/access/assignments/{$this->ownAssignment->id}/entitlements", [
                'business_domain_id' => $this->domain->id,
            ])
            ->assertSessionHasErrors('access');

        $this->assertSame(
            0,
            PendingStepUp::query()->count(),
            'A step-up was offered for an entitlement that is already held. The administrator '
            .'would re-authenticate with Microsoft and be refused afterwards.'
        );

        // A revoked assignment cannot be widened either.
        DomainEntitlement::query()->delete();
        $this->ownAssignment->forceFill(['ended_at' => now()])->save();

        $this->signedInAs($this->actor)
            ->post("/console/access/assignments/{$this->ownAssignment->id}/entitlements", [
                'business_domain_id' => $this->domain->id,
            ])
            ->assertSessionHasErrors('access');

        $this->assertSame(0, PendingStepUp::query()->count());
    }

    /**
     * AND THE AUTHORITY IS RE-EVALUATED ON RETURN.
     *
     * The administrator has been away at Microsoft. If the assignment was
     * revoked while they were gone, the confirmed action no longer has anything
     * to hang from and must not be performed.
     *
     * Mutation: trust the confirmation and skip the re-check.
     */
    public function test_authority_is_revalidated_when_the_step_up_returns(): void
    {
        $reference = $this->beginASelfGrant();
        $this->bindProvider();

        // Revoked while the administrator was at Microsoft.
        $this->ownAssignment->forceFill(['ended_at' => now()])->save();

        $this->returnFromMicrosoft($reference)->assertRedirect(route('access.index'));

        $this->assertSame(
            0,
            DomainEntitlement::query()->count(),
            'A step-up confirmed before the role was revoked still granted a domain afterwards.'
        );
    }

    /**
     * AND IT MUST STILL BE A SELF-GRANT WHEN IT RETURNS.
     *
     * SelfGrant is the action that was confirmed. If the assignment is no
     * longer the confirming administrator's own, the confirmation they gave is
     * not the action being performed - and every other check would let it
     * through, because the assignment is current, the domain is in the right
     * organisation and nothing is held twice.
     *
     * Mutation: drop the actor re-check in performSelfEntitlementGrant. The
     * entitlement is then created for SOMEBODY ELSE off a step-up the
     * administrator confirmed for themselves.
     */
    public function test_an_assignment_that_stopped_being_the_actors_own_is_not_granted(): void
    {
        $other = $this->make->user($this->organisation);

        $reference = $this->beginASelfGrant();
        $this->bindProvider();

        // The assignment moved to somebody else while the administrator was at
        // Microsoft. Everything else about it is still perfectly valid.
        $this->ownAssignment->forceFill(['user_id' => $other->id])->save();

        $this->returnFromMicrosoft($reference)->assertRedirect(route('access.index'));

        $this->assertSame(
            0,
            DomainEntitlement::query()->count(),
            'A self-grant confirmation was spent on an assignment that is no longer the '
            .'administrator\'s own.'
        );
    }

    /**
     * AND IT MUST STILL BE THE ORGANISATION IT WAS CONFIRMED IN.
     *
     * The state that only this check can reject: the assignment AND the domain
     * both moved together, so they still agree with each other and the service
     * has nothing to object to - but neither is in the organisation the
     * administrator was working in when they confirmed.
     *
     * Mutation: drop the organisation re-check. The tenancy test inside
     * EntitlementService does not catch this, because it compares the
     * assignment with the domain rather than either with the confirmation.
     */
    public function test_a_confirmation_cannot_be_spent_in_another_organisation(): void
    {
        $elsewhere = $this->make->organisation();

        $reference = $this->beginASelfGrant();
        $this->bindProvider();

        $this->ownAssignment->forceFill(['organisation_id' => $elsewhere->id])->save();
        $this->domain->forceFill(['organisation_id' => $elsewhere->id])->save();

        $this->returnFromMicrosoft($reference)->assertRedirect(route('access.index'));

        $this->assertSame(
            0,
            DomainEntitlement::query()->count(),
            'A confirmation begun in one organisation was spent in another.'
        );
    }

    /**
     * Both halves of D-73 self-granting, stated together so neither can be
     * removed while the other keeps the rule looking implemented.
     */
    public function test_self_granting_a_role_and_a_domain_entitlement_both_require_step_up(): void
    {
        // The role half.
        $this->signedInAs($this->actor)->post('/console/access/assignments', [
            'user_id' => $this->actor->id,
            'role_code' => RoleCode::DomainOwner->value,
        ]);

        $roleStepUp = PendingStepUp::query()->sole();

        $this->assertSame(StepUpAction::SelfGrant, $roleStepUp->action);
        $this->assertSame(
            0,
            RoleAssignment::query()
                ->where('user_id', $this->actor->id)
                ->where('role_code', RoleCode::DomainOwner->value)
                ->count(),
            'A self-granted ROLE was created before step-up.'
        );

        PendingStepUp::query()->delete();

        // The entitlement half.
        $this->signedInAs($this->actor)
            ->post("/console/access/assignments/{$this->ownAssignment->id}/entitlements", [
                'business_domain_id' => $this->domain->id,
            ]);

        $this->assertSame(StepUpAction::SelfGrant, PendingStepUp::query()->sole()->action);
        $this->assertSame(
            0,
            DomainEntitlement::query()->count(),
            'A self-granted ENTITLEMENT was created before step-up.'
        );
    }

    // ---------------------------------------------------------------- helpers

    /** Begin a self-grant through the real route, and return the reference. */
    private function beginASelfGrant(): string
    {
        $response = $this->signedInAs($this->actor)
            ->post("/console/access/assignments/{$this->ownAssignment->id}/entitlements", [
                'business_domain_id' => $this->domain->id,
            ]);

        return $this->referenceFrom($response);
    }

    /** The whole journey, end to end. Returns the reference that was used. */
    private function completeAJourney(): string
    {
        $reference = $this->beginASelfGrant();
        $this->bindProvider();
        $this->returnFromMicrosoft($reference);

        return $reference;
    }

    /**
     * The session a step-up return arrives in: signed in, and carrying the
     * reference the redirect stored - the same session id the pending row was
     * bound to.
     */
    /**
     * The real second and third steps of the journey.
     *
     * THE REFERENCE REACHES THE RETURN THE WAY PRODUCTION PUTS IT THERE -
     * StepUpController::redirect writes it into the session on the way out, and
     * the callback pulls it on the way back. An earlier version planted it in
     * the session by hand, which is a fixture more helpful than reality: it
     * would have kept passing if the departure stopped storing it at all.
     *
     * @param  array<string, int|string>  $tampered  extra query the browser sends back
     */
    private function returnFromMicrosoft(string $reference, array $tampered = []): TestResponse
    {
        // Depart. This is what stores the reference and binds the return.
        $this->inTheStepUpSession($reference)->post("/console/access/step-up/{$reference}");

        $query = $tampered === [] ? '' : '?'.http_build_query($tampered);

        return $this->inTheStepUpSession($reference)->get('/auth/microsoft/step-up'.$query);
    }

    /**
     * Signed in, in the SAME session the pending row is bound to.
     *
     * The test client starts a new session per request unless the session
     * cookie is carried, so this carries it. Without that the return would be
     * refused as a session mismatch - the right refusal for the wrong reason,
     * and every case here would pass while proving nothing about the grant.
     */
    private function inTheStepUpSession(string $reference): self
    {
        $bound = PendingStepUp::query()
            ->where('reference_hash', PendingStepUp::hashFor($reference))
            ->sole()
            ->session_id;

        return $this->withCookie((string) config('session.cookie'), $bound)
            ->withSession([
                EnsureSessionIsCurrent::SESSION_USER_ID => $this->actor->id,
                EnsureSessionIsCurrent::SESSION_AUTHENTICATED_AT => now()->toIso8601String(),
            ]);
    }

    /**
     * The provider, faked.
     *
     * The real middle step is Microsoft's and this suite is about what SemantIQ
     * does with the answer - never a claim that the provider itself was
     * exercised.
     *
     * MUTATED IN PLACE, and resolved through a closure binding, because the
     * container hands the request whatever instance it resolved first: an
     * earlier version re-bound a new stub per case and the request went on
     * using the previous one, so the "stale authentication" case was silently
     * testing the cancellation case instead. A fixture more helpful than
     * reality, exactly as CLAUDE.md §2 warns.
     */
    private function bindProvider(?Carbon $authenticatedAt = null, bool $fails = false): void
    {
        $identity = new VerifiedIdentity(
            'microsoft',
            $this->actor->external_subject,
            $this->actor->tenant_id,
            $this->actor->email,
            $this->actor->display_name,
        );

        $this->provider->answer(new StepUpVerification($identity, $authenticatedAt ?? now()), $fails);
    }

    /**
     * The plaintext reference is never stored anywhere, so it is read from the
     * redirect the route issued - which is the only place a copy exists.
     */
    private function referenceFrom(TestResponse $response): string
    {
        $location = (string) $response->headers->get('Location');

        $this->assertStringContainsString(
            '/console/access/step-up/',
            $location,
            'The grant did not redirect to the step-up confirmation.'
        );

        return basename(parse_url($location, PHP_URL_PATH) ?: '');
    }

    private function signedInAs(User $user): self
    {
        return $this->withSession([
            EnsureSessionIsCurrent::SESSION_USER_ID => $user->id,
            EnsureSessionIsCurrent::SESSION_AUTHENTICATED_AT => now()->toIso8601String(),
        ]);
    }
}

/**
 * A Microsoft that answers exactly what a case needs it to.
 *
 * It never pretends to BE the provider: it stands in for the one step of the
 * journey that belongs to Microsoft, so that the two steps either side of it -
 * what SemantIQ stored before, and what it does with the answer after - can be
 * exercised for real.
 */
final class FakeStepUpProvider implements IdentityProvider
{
    private ?StepUpVerification $verification = null;

    private bool $fails = false;

    public function answer(StepUpVerification $verification, bool $fails): void
    {
        $this->verification = $verification;
        $this->fails = $fails;
    }

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
        return new RedirectResponse($returnUri);
    }

    public function completeStepUpAuthorization(Request $request): StepUpVerification
    {
        if ($this->fails || $this->verification === null) {
            throw AuthenticationFailed::protocol('cancelled_at_the_provider');
        }

        return $this->verification;
    }

    public function completeAuthorization(Request $request): VerifiedIdentity
    {
        if ($this->verification === null) {
            throw AuthenticationFailed::protocol('not_used');
        }

        return $this->verification->identity;
    }
}
