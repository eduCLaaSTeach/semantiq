<?php

declare(strict_types=1);

namespace Tests\Feature\Reviews;

use App\Modules\Access\Support\RoleCode;
use App\Modules\Access\Support\ScopeType;
use App\Modules\Access\Support\Sensitivity;
use App\Modules\Platform\Security\SecurityEventLogger;
use App\Modules\Reviews\Models\AccessReviewItem;
use App\Modules\Reviews\Services\ReviewDecisionService;
use App\Modules\Reviews\Support\ReviewDecision;
use App\Modules\Reviews\Support\ReviewViolation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\Support\AccessFactory;
use Tests\Support\OrganisationFactory;
use Tests\Support\ReviewFactory;
use Tests\TestCase;

/**
 * N-R12. TWO DECISIONS, ONE OUTCOME.
 *
 * UI state is never authoritative. Every decision re-validates on the server
 * inside the transaction, against the item locked the way P1-05 locks the rows
 * beneath it.
 *
 * CI runs this against MYSQL as well as SQLite, because lockForUpdate
 * behaviour differs between them - the P1-05 precedent.
 */
final class ReviewConcurrencyTest extends TestCase
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
     * A second decision on the same item is refused, and emits no second
     * decision event.
     *
     * Mutation: remove the terminal-state check, or drop lockForUpdate.
     */
    public function test_a_second_decision_is_refused_and_emits_no_second_event(): void
    {
        $organisation = $this->make->organisation();
        $first = $this->make->user($organisation, administrator: true);
        $second = $this->make->user($organisation, administrator: true);
        $subject = $this->make->user($organisation);
        $domain = $this->access->domain($organisation);

        $entitlement = $this->access->completePath(
            $subject, $domain, RoleCode::Manager, ScopeType::Team, null, Sensitivity::Confidential
        );

        $item = $this->reviews->domainItem($this->reviews->cycle($first), $entitlement);
        $service = app(ReviewDecisionService::class);

        $events = [];
        Log::listen(function ($message) use (&$events): void {
            $events[] = $message->message;
        });

        $service->decide($item, ReviewDecision::Retain, $first);

        try {
            $service->decide($item->fresh(), ReviewDecision::Revoke, $second);
            $this->fail('The second reviewer was allowed to decide an item that was already decided.');
        } catch (ReviewViolation $violation) {
            $this->assertSame('already_decided', $violation->reason);
        }

        $this->assertSame('retained', $item->fresh()->state->value);
        $this->assertNull($entitlement->fresh()->ended_at, 'The losing decision still changed the access.');

        // ONE decision event. The refusal is recorded separately, as a refusal.
        $decisionEvents = array_values(array_filter(
            $events,
            static fn (string $e): bool => in_array($e, [
                SecurityEventLogger::REVIEW_ITEM_RETAINED,
                SecurityEventLogger::REVIEW_ITEM_REVOKED,
            ], true),
        ));

        $this->assertCount(1, $decisionEvents);
        $this->assertContains(SecurityEventLogger::REVIEW_REFUSED, $events);
    }

    /**
     * A duplicate revoke - the double submit, or the retry - ends the access
     * ONCE.
     *
     * Mutation: allow a terminal item to be revoked again. ended_at is then
     * rewritten, and the history says it ended at the wrong moment.
     */
    public function test_a_duplicate_revoke_ends_the_access_once(): void
    {
        $organisation = $this->make->organisation();
        $actor = $this->make->user($organisation, administrator: true);
        $subject = $this->make->user($organisation);
        $domain = $this->access->domain($organisation);

        $entitlement = $this->access->completePath(
            $subject, $domain, RoleCode::Manager, ScopeType::Team, null, Sensitivity::Confidential
        );

        $item = $this->reviews->domainItem($this->reviews->cycle($actor), $entitlement);
        $service = app(ReviewDecisionService::class);

        $service->decide($item, ReviewDecision::Revoke, $actor);
        $endedAt = $entitlement->fresh()->ended_at;

        $this->travel(1)->minutes();

        try {
            $service->decide($item->fresh(), ReviewDecision::Revoke, $actor);
        } catch (ReviewViolation) {
            // expected
        }

        $this->assertEquals($endedAt, $entitlement->fresh()->ended_at, 'The access ended twice.');
        $this->travelBack();
    }

    /**
     * N-R10. An item in a state the machine does not recognise FAILS CLOSED.
     *
     * Mutation: add a permissive default branch to the state handling.
     */
    public function test_an_unrecognised_item_state_is_not_decidable(): void
    {
        $organisation = $this->make->organisation();
        $actor = $this->make->user($organisation, administrator: true);
        $subject = $this->make->user($organisation);
        $domain = $this->access->domain($organisation);

        $entitlement = $this->access->completePath(
            $subject, $domain, RoleCode::Manager, ScopeType::Team, null, Sensitivity::Confidential
        );

        $item = $this->reviews->domainItem($this->reviews->cycle($actor), $entitlement);

        // A state no enum case covers - the shape a bad migration or a manual
        // repair leaves behind.
        AccessReviewItem::query()->whereKey($item->getKey())->update(['state' => 'in_progress']);

        $this->expectException(\ValueError::class);
        AccessReviewItem::query()->findOrFail($item->getKey())->state;

        $this->assertNull($entitlement->fresh()->ended_at);
    }
}
