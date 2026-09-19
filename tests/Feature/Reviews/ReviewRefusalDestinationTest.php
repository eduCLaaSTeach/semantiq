<?php

declare(strict_types=1);

namespace Tests\Feature\Reviews;

use App\Modules\Access\Models\RoleAssignment;
use App\Modules\Access\StepUp\PendingStepUp;
use App\Modules\Access\StepUp\StepUpCompletionRegistry;
use App\Modules\Access\StepUp\StepUpVerification;
use App\Modules\Access\Support\AccessViolation;
use App\Modules\Access\Support\RoleCode;
use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;
use App\Modules\Platform\Identity\AuthenticationFailed;
use App\Modules\Platform\Identity\IdentityProvider;
use App\Modules\Platform\Identity\VerifiedIdentity;
use App\Modules\Platform\Models\User;
use App\Modules\Platform\Security\SecurityEventLogger;
use App\Modules\Reviews\Models\AccessReviewItem;
use App\Modules\Reviews\StepUp\ReviewStepUpCompletion;
use App\Modules\Reviews\Support\ReviewKind;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse as HttpRedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;
use ReflectionMethod;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Tests\Support\AccessFactory;
use Tests\Support\OrganisationFactory;
use Tests\Support\RecordingSecurityEventLogger;
use Tests\Support\ReviewFactory;
use Tests\TestCase;

/**
 * GATE D, FINAL DEFECT. A REFUSED REVIEW DECISION BELONGS TO THE REVIEW SCREEN.
 *
 * WHAT THE PRODUCT OWNER SAW. On the only active System Administrator's own
 * privileged review they chose "Remove this access", confirmed at Microsoft,
 * and landed on ROLES & ACCESS reading the administrator-floor refusal - a
 * screen they had not been on, about a page they had not asked for, with no
 * indication of what had become of the review they were doing.
 *
 * WHY IT HAPPENED. The completion rethrew as a step-up failure, so the refusal
 * was handled by P1-05's own StepUpController::refuseToIndex(), whose
 * destination is access.index. That is the RIGHT destination for an action
 * begun in Roles & Access and the wrong one for an action begun in Access
 * Reviews. The destination was never the review's to begin with, so the
 * correction is on P1-07's side of the boundary and P1-05's own refusal
 * destination is unchanged - which is the last case in this file.
 *
 * SEVEN FACTS, ASSERTED SEPARATELY, because one journey producing one
 * assertion would pass on any of them being wrong.
 */
final class ReviewRefusalDestinationTest extends TestCase
{
    use RefreshDatabase;

    private OrganisationFactory $make;

    private AccessFactory $access;

    private ReviewFactory $reviews;

    private ReviewRefusalProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->make = new OrganisationFactory;
        $this->access = new AccessFactory;
        $this->reviews = new ReviewFactory;

        $this->provider = new ReviewRefusalProvider;
        $this->app->bind(IdentityProvider::class, fn (): IdentityProvider => $this->provider);
    }

    /**
     * THE DEFECT ITSELF. Back to Access Reviews, not to Roles & Access.
     *
     * Mutation: rethrow AccessViolation::stepUpInvalid() from the completion,
     * as the shipped version did. The redirect becomes access.index and this
     * fails on the first assertion.
     */
    public function test_a_refused_sole_administrator_revoke_returns_to_privileged_reviews(): void
    {
        [$actor, $item] = $this->soleAdministratorReview();

        $response = $this->refuseARevokeFromTheReview($actor, $item);

        $response->assertRedirect(route('access-reviews.privileged'));

        // Named explicitly. assertRedirect above would also be satisfied if
        // access.index and access-reviews.privileged were ever the same route,
        // and this says which screen is wrong.
        $this->assertNotSame(
            route('access.index'),
            (string) $response->headers->get('Location'),
            'A review-originated refusal sent the reviewer to Roles & Access.'
        );
    }

    /**
     * THE BUSINESS REFUSAL, in P1-05's own words, on the review screen.
     *
     * Mutation: flash the generic "That confirmation is no longer valid"
     * message instead. The reviewer would be told their confirmation failed
     * rather than why the removal was refused.
     */
    public function test_the_administrator_floor_refusal_is_shown_on_the_review_screen(): void
    {
        [$actor, $item] = $this->soleAdministratorReview();

        $this->refuseARevokeFromTheReview($actor, $item)->assertSessionHas(
            'refusal',
            AccessViolation::soleAdministrator()->getMessage(),
        );

        // The review screens render `refusal`; `errors` is Roles & Access's
        // channel and nothing on this screen reads it.
        $this->assertSame(
            'This is the only active System Administrator. Add or retain another before removing this one.',
            session('refusal'),
        );
    }

    /**
     * THE ROLE IS UNTOUCHED. The floor held.
     *
     * Mutation: make AdministratorSetGuard::refuseIfLast() stop refusing. The
     * role ends and this fails. STATED PLAINLY: what this case proves is that
     * the review path runs THROUGH the floor, not that P1-07 implements one -
     * P1-07 contains no access-ending logic of its own, and a second
     * administrator floor is exactly what this unit must not grow.
     */
    public function test_a_refused_revoke_leaves_the_system_administrator_role_active(): void
    {
        [$actor, $item, $assignment] = $this->soleAdministratorReview();

        $this->refuseARevokeFromTheReview($actor, $item);

        $this->assertNull(
            $assignment->fresh()->ended_at,
            'The only active System Administrator was removed by a refused review decision.'
        );
    }

    /**
     * THE REVIEW IS STILL AWAITING A DECISION. Not retained, not revoked, not
     * superseded - none of those happened.
     *
     * Mutation: close the item in the refusal handler - the "tidy it away"
     * change. The review then reads as decided for access that is still there.
     *
     * NOT proved by this: the ORDER of the writes inside ReviewDecisionService.
     * Moving its state write above the revoke call leaves this passing, because
     * the whole inner transaction rolls back on the refusal. That was run and
     * it SURVIVED; the transaction is what holds the item, and saying otherwise
     * would credit this case with a guarantee it does not provide.
     */
    public function test_a_refused_revoke_leaves_the_review_pending(): void
    {
        [$actor, $item] = $this->soleAdministratorReview();

        $this->refuseARevokeFromTheReview($actor, $item);

        $fresh = $item->fresh();

        $this->assertSame('pending', $fresh->state->value, 'A refused review decision was recorded as decided.');
        $this->assertNull($fresh->decided_at);
        $this->assertNull($fresh->decided_by_user_id);
        $this->assertNull($fresh->superseded_reason);
    }

    /**
     * ONE CONFIRMATION, ONE ATTEMPT - AND A REFUSED ATTEMPT WAS STILL THE
     * ATTEMPT.
     *
     * Returning the refusal rather than throwing it lets the transaction that
     * consumed the reference commit. Rethrowing would roll the consumption back
     * and hand the reviewer a live reference, which is the "let them try again"
     * change D-73 exists to refuse.
     *
     * Mutation: rethrow instead of returning. consumed_at is then null and the
     * replay below is accepted rather than refused.
     */
    public function test_the_refused_confirmation_is_consumed_and_cannot_be_replayed(): void
    {
        [$actor, $item] = $this->soleAdministratorReview();

        $reference = $this->beginARevoke($actor, $item);
        $this->returnFromMicrosoft($actor, $reference);

        $pending = PendingStepUp::query()->sole();

        $this->assertNotNull($pending->consumed_at, 'A refused review confirmation was left reusable.');
        $this->assertSame('completed', $pending->outcome);

        // And the replay is refused where every other spent reference is - by
        // resolve(), before any completion runs.
        $replay = $this->returnFromMicrosoft($actor, $reference);

        $replay->assertRedirect(route('access.index'));
        $this->assertSame('pending', $item->fresh()->state->value);
    }

    /**
     * P1-05'S OWN REFUSAL DESTINATION IS UNCHANGED.
     *
     * The same rule, refused on the same person, reached from Roles & Access -
     * and it must still answer on Roles & Access. Without this the correction
     * could have been made by pointing refuseToIndex() at the review screen,
     * which would break the screen the refusal actually belongs to.
     *
     * Mutation: change refuseToIndex() to redirect to access-reviews.privileged.
     */
    public function test_a_roles_and_access_revoke_refusal_still_returns_to_roles_and_access(): void
    {
        $organisation = $this->make->organisation();
        $actor = $this->make->user($organisation, administrator: true);
        $assignment = $this->administratorAssignment($actor);

        $reference = $this->referenceFrom(
            $this->signedInAs($actor)->patch("/console/access/assignments/{$assignment->id}/revoke")
        );

        $response = $this->returnFromMicrosoft($actor, $reference);

        $response->assertRedirect(route('access.index'));
        $response->assertSessionHasErrors(['access' => AccessViolation::soleAdministrator()->getMessage()]);
        $this->assertNull($assignment->fresh()->ended_at);
    }

    /**
     * THE SUCCESSFUL PATH IS UNCHANGED, both kinds.
     *
     * The non-vacuous half: a completion that redirected to Access Reviews
     * unconditionally - including when nothing was refused - would satisfy
     * every case above.
     *
     * Mutation: flash the refusal message on the success path too. The
     * confirmation assertion below fails.
     */
    public function test_a_successful_review_revoke_still_returns_to_the_right_review_tab(): void
    {
        // Privileged: an Organisation Administrator, which needs the same
        // confirmation and is refused by nothing.
        $organisation = $this->make->organisation();
        $actor = $this->make->user($organisation, administrator: true);
        $subject = $this->make->user($organisation);
        $assignment = $this->access->assignment($subject, RoleCode::OrganisationAdministrator, $organisation);
        $item = $this->reviews->privilegedItem($this->reviews->cycle($actor), $assignment);

        $reference = $this->beginARevoke($actor, $item);
        $response = $this->returnFromMicrosoft($actor, $reference);

        $response->assertRedirect(route('access-reviews.privileged'));
        $response->assertSessionHas('confirmation', 'Access removed. It ended at that moment.');
        $this->assertNotNull($assignment->fresh()->ended_at, 'A permitted review revoke did not remove the access.');
        $this->assertSame('revoked', $item->fresh()->state->value);
    }

    /**
     * THE REFUSAL IS EVIDENCE, UNDER THE KEY THAT ALREADY EXISTS.
     *
     * REVIEW_REFUSED is P1-07's one refusal key and it stays that way. A
     * second key for "refused by P1-05" would split one question - what was
     * refused, and why - across two places, and evidence that needs a join to
     * assemble is evidence nobody assembles. The REASON carries which rule
     * refused; that is what the reason field is for.
     *
     * Mutation: drop the refuse() call from the completion. The refusal then
     * leaves no trace at all, which is the shipped behaviour.
     */
    public function test_the_refusal_is_recorded_under_the_existing_review_refusal_key(): void
    {
        $events = new RecordingSecurityEventLogger;
        $this->app->instance(SecurityEventLogger::class, $events);

        /*
         * THE COMPLETION IS BUILT AT BOOT and held in a singleton registry, so
         * it carries the logger the container had THEN. Binding a recorder and
         * leaving it at that records the step-up events - which come from a
         * per-request service - and none of the review ones, which is exactly
         * what the first version of this case observed: a recorder that looked
         * bound and was reading a different object.
         */
        $this->app->forgetInstance(StepUpCompletionRegistry::class);
        $this->app->make(StepUpCompletionRegistry::class)
            ->register($this->app->make(ReviewStepUpCompletion::class));

        [$actor, $item] = $this->soleAdministratorReview();

        $this->refuseARevokeFromTheReview($actor, $item);

        $refusals = $events->contextsFor(SecurityEventLogger::REVIEW_REFUSED);

        $this->assertCount(1, $refusals, 'The refused review decision left no evidence.');
        $this->assertSame('sole_administrator', $refusals[0]['reason']);
        $this->assertSame($item->id, $refusals[0]['entity_id']);
        $this->assertSame($actor->id, $refusals[0]['related_id']);
        $this->assertSame('refused', $refusals[0]['result']);

        // And nothing claims the access was decided.
        $this->assertNotContains(SecurityEventLogger::REVIEW_ITEM_REVOKED, $events->recordedEvents());
        $this->assertNotContains(SecurityEventLogger::REVIEW_ITEM_RETAINED, $events->recordedEvents());
        $this->assertNotContains(SecurityEventLogger::REVIEW_ITEM_SELF_REVIEWED, $events->recordedEvents());
    }

    /**
     * AND IT REACHES THE SCREEN.
     *
     * A message in the session is not a message on the page. `refusal` was
     * flashed by every review refusal since P1-07 shipped and was never among
     * the shared Inertia props, so ReviewPage read `props.refusal`, found
     * nothing, and rendered nothing - while assertSessionHas('refusal') passed
     * throughout. Found by opening the screen, not by running the suite.
     *
     * Mutation: remove 'refusal' from HandleInertiaRequests::share(). The
     * session assertion above still passes; this one does not.
     */
    public function test_the_refusal_is_among_the_props_the_review_screen_renders(): void
    {
        [$actor, $item] = $this->soleAdministratorReview();

        $props = $this->signedInAs($actor)
            ->withSession(['refusal' => AccessViolation::soleAdministrator()->getMessage()])
            ->get('/console/access-reviews')
            ->viewData('page')['props'];

        $this->assertSame(
            'This is the only active System Administrator. Add or retain another before removing this one.',
            $props['refusal'] ?? null,
            'The refusal never reaches the screen, so the reviewer is returned to a page that says nothing.'
        );

        // The success channel is separate and must stay empty here.
        $this->assertNull($props['confirmation'] ?? null);
    }

    /**
     * A DOMAIN REVIEW ANSWERS ON DOMAIN REVIEWS - refused or not.
     *
     * HONESTLY STATED: P1-05 has no refusal REACHABLE on the domain revoke path
     * today. EntitlementService::revoke refuses only an entitlement that is no
     * longer current, and an entitlement that is no longer current is caught
     * one step earlier as a SUPERSEDED review. Producing one would mean forcing
     * a state the product cannot reach, which is a fixture more helpful than
     * reality in the opposite direction.
     *
     * So this asserts the destination selection itself, on a REAL domain review
     * item, rather than staging a refusal that cannot happen. The refusal path
     * and the confirmation path share this one method, and the privileged cases
     * above prove the refusal path uses it.
     *
     * Mutation: return the privileged route unconditionally. This fails, and so
     * does the domain half of the successful-path case.
     */
    public function test_a_domain_review_answers_on_domain_reviews(): void
    {
        $organisation = $this->make->organisation();
        $actor = $this->make->user($organisation, administrator: true);
        $subject = $this->make->user($organisation);
        $assignment = $this->access->assignment($subject, RoleCode::BusinessUser, $organisation);
        $domain = $this->access->domain($organisation, 'finance', 'Finance');
        $entitlement = $this->access->entitlement($assignment, $domain);
        $this->access->scope($entitlement);
        $this->access->ceiling($entitlement);

        $item = $this->reviews->domainItem(
            $this->reviews->cycle($actor),
            $entitlement->fresh()->load(['assignment', 'scopes', 'ceilings']),
        );

        $this->assertSame(ReviewKind::Domain, $item->kind);

        $screen = new ReflectionMethod(ReviewStepUpCompletion::class, 'originatingScreen');

        /** @var HttpRedirectResponse $redirect */
        $redirect = $screen->invoke(app(ReviewStepUpCompletion::class), $item);

        $this->assertSame(route('access-reviews.domains'), $redirect->getTargetUrl());
    }

    /**
     * The production shape: ONE active System Administrator, reviewing their
     * own privileged access because nobody else can (D-88).
     *
     * NOTHING IS MANUFACTURED TO MAKE THE REFUSAL HAPPEN. A second
     * administrator is not created and none is deactivated - the deployment
     * simply has one, which is the state the Product Owner tested from.
     *
     * @return array{0: User, 1: AccessReviewItem, 2: RoleAssignment}
     */
    private function soleAdministratorReview(): array
    {
        $organisation = $this->make->organisation();
        $actor = $this->make->user($organisation, administrator: true);
        $assignment = $this->administratorAssignment($actor);
        $item = $this->reviews->privilegedItem($this->reviews->cycle($actor), $assignment);

        return [$actor, $item, $assignment];
    }

    private function administratorAssignment(User $actor): RoleAssignment
    {
        return RoleAssignment::query()
            ->where('user_id', $actor->getKey())
            ->where('role_code', RoleCode::SystemAdministrator->value)
            ->whereNull('ended_at')
            ->sole();
    }

    /** The whole journey, through the real routes, ending in the refusal. */
    private function refuseARevokeFromTheReview(User $actor, AccessReviewItem $item): TestResponse
    {
        return $this->returnFromMicrosoft($actor, $this->beginARevoke($actor, $item));
    }

    private function beginARevoke(User $actor, AccessReviewItem $item): string
    {
        return $this->referenceFrom(
            $this->signedInAs($actor)->post("/console/access-reviews/items/{$item->id}/decide", ['decision' => 'revoke'])
        );
    }

    /**
     * The real second and third steps. The departure is what stores the
     * reference for the return, exactly as production does - planting it by
     * hand would keep passing if the departure stopped storing it at all.
     */
    private function returnFromMicrosoft(User $actor, string $reference): TestResponse
    {
        $this->answerAs($actor);

        $this->inTheStepUpSession($actor, $reference)->post("/console/access/step-up/{$reference}");

        return $this->inTheStepUpSession($actor, $reference)->get('/auth/microsoft/step-up');
    }

    private function answerAs(User $actor): void
    {
        $this->provider->answer(new StepUpVerification(
            new VerifiedIdentity(
                'microsoft',
                $actor->external_subject,
                $actor->tenant_id,
                $actor->email,
                $actor->display_name,
            ),
            now(),
        ));
    }

    /**
     * Signed in, IN THE SESSION THE PENDING ROW IS BOUND TO. The test client
     * starts a new session per request unless the cookie is carried; without it
     * every case here would be refused as a session mismatch - the right
     * refusal for the wrong reason.
     */
    private function inTheStepUpSession(User $actor, string $reference): self
    {
        $bound = PendingStepUp::query()
            ->where('reference_hash', PendingStepUp::hashFor($reference))
            ->sole()
            ->session_id;

        return $this->withCookie((string) config('session.cookie'), $bound)
            ->withSession([
                EnsureSessionIsCurrent::SESSION_USER_ID => $actor->id,
                EnsureSessionIsCurrent::SESSION_AUTHENTICATED_AT => now()->toIso8601String(),
            ]);
    }

    /** The plaintext reference exists only in the redirect the route issued. */
    private function referenceFrom(TestResponse $response): string
    {
        $location = (string) $response->headers->get('Location');

        $this->assertStringContainsString(
            '/console/access/step-up/',
            $location,
            'The revoke did not ask for a fresh sign-in.'
        );

        return basename((string) parse_url($location, PHP_URL_PATH));
    }

    private function signedInAs(User $user): self
    {
        return $this->withSession([
            EnsureSessionIsCurrent::SESSION_USER_ID => $user->id,
            EnsureSessionIsCurrent::SESSION_AUTHENTICATED_AT => now()->toIso8601String(),
        ]);
    }
}

/** Microsoft's one step of the journey, so the two either side can be real. */
final class ReviewRefusalProvider implements IdentityProvider
{
    private ?StepUpVerification $verification = null;

    public function answer(StepUpVerification $verification): void
    {
        $this->verification = $verification;
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
        return $this->verification ?? throw AuthenticationFailed::protocol('not_used');
    }

    public function completeAuthorization(Request $request): VerifiedIdentity
    {
        return $this->verification?->identity ?? throw AuthenticationFailed::protocol('not_used');
    }
}
