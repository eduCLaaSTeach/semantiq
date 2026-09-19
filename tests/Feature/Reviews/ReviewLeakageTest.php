<?php

declare(strict_types=1);

namespace Tests\Feature\Reviews;

use App\Modules\Access\Support\RoleCode;
use App\Modules\Access\Support\ScopeType;
use App\Modules\Access\Support\Sensitivity;
use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;
use App\Modules\Platform\Models\User;
use App\Modules\Platform\Security\SecurityEventLogger;
use App\Modules\Reviews\Services\ReviewDecisionService;
use App\Modules\Reviews\Services\ReviewerAuthority;
use App\Modules\Reviews\Support\ReviewDecision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\Support\AccessFactory;
use Tests\Support\OrganisationFactory;
use Tests\Support\ReviewFactory;
use Tests\TestCase;

/**
 * N-R15, N-R17, N-R24. WHAT MUST NEVER LEAVE THE SERVER.
 */
final class ReviewLeakageTest extends TestCase
{
    use RefreshDatabase;

    private OrganisationFactory $make;

    private AccessFactory $access;

    private ReviewFactory $reviews;

    protected function setUp(): void
    {
        parent::setUp();
        $this->make = new OrganisationFactory;
        $this->access = new AccessFactory;
        $this->reviews = new ReviewFactory;
    }

    /**
     * N-R15. A System Administrator gains NO business-data visibility by
     * reviewing access.
     *
     * Reviewing a Restricted entitlement must not display a restricted record
     * to prove the access exists - that would be a leak dressed as evidence.
     * The composition is described in WORDS.
     *
     * Mutation: render the entitlement's reachable records on the row.
     */
    public function test_no_business_record_appears_on_a_review_screen(): void
    {
        $organisation = $this->make->organisation();
        $actor = $this->make->user($organisation, administrator: true);
        $subject = $this->make->user($organisation);
        $domain = $this->access->domain($organisation);

        $entitlement = $this->access->completePath(
            $subject, $domain, RoleCode::Manager, ScopeType::Domain, null, Sensitivity::Restricted
        );

        $this->reviews->domainItem($this->reviews->cycle($actor), $entitlement);

        $response = $this->signedInAs($actor)->get('/console/access-reviews/domains');
        $response->assertOk();

        $row = $response->viewData('page')['props']['items']['data'][0];

        // The composition is words, not identifiers.
        $this->assertStringContainsString('Every record in this domain', $row['scopes'][0]);

        // P1-05's implementation caveat about a reserved future partition does
        // not belong on a business screen.
        $this->assertStringNotContainsString('the same as', $row['scopes'][0]);
        $this->assertStringNotContainsString('Reserved for', $row['scopes'][0]);
        $this->assertSame('Restricted', $row['ceiling']);

        // No identifier of the reviewed objects reaches the client at all.
        foreach (['domain_entitlement_id', 'role_assignment_id', 'composition_fingerprint', 'entitlement_scope_id'] as $key) {
            $this->assertArrayNotHasKey($key, $row);
        }

        $body = (string) $response->getContent();
        foreach (['business_user', 'domain_entitlement', 'role_assignment', 'grant path', 'enum'] as $developerTerm) {
            $this->assertStringNotContainsStringIgnoringCase(
                $developerTerm,
                strip_tags($body),
                "The screen exposes developer terminology: {$developerTerm}."
            );
        }
    }

    /**
     * N-R17. NO FREE TEXT REACHES A SECURITY EVENT.
     *
     * Every context key is validated against ALLOWED_KEYS, and `reason` carries
     * a fixed vocabulary rather than a message.
     *
     * Mutation: pass a comment key. The logger throws, which is the point.
     */
    public function test_a_review_event_cannot_carry_free_text(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(SecurityEventLogger::class)->record(SecurityEventLogger::REVIEW_ITEM_RETAINED, [
            'user_id' => 1,
            'comment' => 'looks fine to me',
        ]);
    }

    /**
     * N-R24. Self-review emits its OWN event, not the ordinary one.
     *
     * Evidence that needs a join to spot is evidence nobody spots.
     *
     * Mutation: emit REVIEW_ITEM_RETAINED for a self-review.
     */
    public function test_self_review_emits_a_distinguishable_event(): void
    {
        $organisation = $this->make->organisation();
        $sole = $this->make->user($organisation, administrator: true);
        $assignment = $sole->roleAssignments()->whereNull('ended_at')->firstOrFail();
        $item = $this->reviews->privilegedItem($this->reviews->cycle($sole), $assignment);

        $records = [];
        Log::listen(function ($message) use (&$records): void {
            $records[] = $message->message;
        });

        app(ReviewDecisionService::class)->decide($item, ReviewDecision::Retain, $sole);

        $this->assertContains(SecurityEventLogger::REVIEW_ITEM_SELF_REVIEWED, $records);
        $this->assertNotContains(SecurityEventLogger::REVIEW_ITEM_RETAINED, $records);
        $this->assertTrue($item->fresh()->self_review);
    }

    /**
     * D-88. Self-review is refused while somebody else could do it.
     *
     * Mutation: always permit self-review.
     */
    public function test_self_review_is_refused_when_another_reviewer_exists(): void
    {
        $organisation = $this->make->organisation();
        $first = $this->make->user($organisation, administrator: true);
        $second = $this->make->user($organisation, administrator: true);

        $assignment = $first->roleAssignments()->whereNull('ended_at')->firstOrFail();
        $item = $this->reviews->privilegedItem($this->reviews->cycle($second), $assignment);

        /*
         * ASSERTED ON THE AUTHORITY, NOT ON THE RESULTING STATE.
         *
         * The first version posted the decision and asserted the item was still
         * pending - and SURVIVED the mutation that removes the guard, because a
         * permitted self-review redirects to step-up and ALSO leaves the item
         * pending. It passed for a reason unrelated to what it claimed.
         */
        $this->assertNull(
            app(ReviewerAuthority::class)->basisFor($first, $item),
            'Self-review was permitted although another eligible reviewer exists.'
        );

        $response = $this->signedInAs($first)
            ->post("/console/access-reviews/items/{$item->id}/decide", ['decision' => 'retain']);

        // Refused back to the screen, NOT sent to a step-up confirmation.
        $response->assertRedirect();
        $this->assertStringNotContainsString('step-up', (string) $response->headers->get('Location'));
        $this->assertSame('pending', $item->fresh()->state->value);
    }

    /** Every declared review event is in the catalogue, so none can be emitted unregistered. */
    public function test_the_six_review_events_are_registered(): void
    {
        $events = SecurityEventLogger::events();

        foreach ([
            SecurityEventLogger::REVIEW_CYCLE_STARTED,
            SecurityEventLogger::REVIEW_ITEM_RETAINED,
            SecurityEventLogger::REVIEW_ITEM_REVOKED,
            SecurityEventLogger::REVIEW_ITEM_SUPERSEDED,
            SecurityEventLogger::REVIEW_ITEM_SELF_REVIEWED,
            SecurityEventLogger::REVIEW_REFUSED,
        ] as $event) {
            $this->assertContains($event, $events);
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
