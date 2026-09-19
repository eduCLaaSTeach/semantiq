<?php

declare(strict_types=1);

namespace Tests\Feature\Reviews;

use App\Modules\Access\Support\RoleCode;
use App\Modules\Access\Support\ScopeType;
use App\Modules\Access\Support\Sensitivity;
use App\Modules\Organisation\Models\Organisation;
use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;
use App\Modules\Platform\Models\User;
use App\Modules\Reviews\Models\AccessReviewCycle;
use App\Modules\Reviews\Models\AccessReviewItem;
use App\Modules\Reviews\Services\ReviewCycleGenerator;
use App\Modules\Reviews\Services\ReviewDecisionService;
use App\Modules\Reviews\Support\ReviewDecision;
use App\Modules\Reviews\Support\ReviewKind;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AccessFactory;
use Tests\Support\OrganisationFactory;
use Tests\TestCase;

/**
 * GATE D DEFECTS 1 AND 2.
 *
 * P1-07 IS THE OPERATIONAL SCREEN, NOT THE HISTORY BROWSER. Every completed
 * cycle used to appear alongside the live one, so a screen whose whole job is
 * "what still needs deciding" filled with rows already decided. History is not
 * deleted - it stays permanently, and P1-08 owns the experience for reading it.
 */
final class ReviewCycleProjectionTest extends TestCase
{
    use RefreshDatabase;

    private OrganisationFactory $make;

    private AccessFactory $access;

    private ReviewCycleGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->make = new OrganisationFactory;
        $this->access = new AccessFactory;
        $this->generator = app(ReviewCycleGenerator::class);
    }

    /**
     * 1 and 3. A previous completed cycle is not shown as current work, and the
     * current cycle's rows are.
     *
     * Mutation: drop the cycle filter from the listing.
     */
    public function test_only_the_current_cycle_is_shown_as_work(): void
    {
        [$admin, $organisation, $subject] = $this->fixture();

        $this->generator->start($admin, now()->addDays(30), $organisation->id);
        $first = AccessReviewItem::query()->where('subject_user_id', $subject->id)->firstOrFail();

        // The whole of cycle one is decided.
        foreach (AccessReviewItem::query()->pending()->get() as $item) {
            app(ReviewDecisionService::class)->decide($item, ReviewDecision::Retain, $admin);
        }

        // A legitimate later cycle raises a fresh review of the same access.
        $this->generator->start($admin, now()->addDays(60), $organisation->id);
        $second = AccessReviewItem::query()
            ->where('subject_user_id', $subject->id)
            ->where('id', '!=', $first->id)
            ->firstOrFail();

        $rows = $this->rowsOn($admin, '/console/access-reviews');
        $ids = array_column($rows, 'id');

        $this->assertContains($second->id, $ids, "The current cycle's review is missing.");
        $this->assertNotContains($first->id, $ids, 'A completed cycle is cluttering the operational screen.');
    }

    /**
     * 2. THE HISTORY IS STILL THERE. Not shown is not deleted.
     *
     * Mutation: delete superseded cycles when a new one starts.
     */
    public function test_historical_review_records_still_exist(): void
    {
        [$admin, $organisation] = $this->fixture();

        $this->generator->start($admin, now()->addDays(30), $organisation->id);
        $firstCycleIds = AccessReviewItem::query()->pluck('id')->all();

        foreach (AccessReviewItem::query()->pending()->get() as $item) {
            app(ReviewDecisionService::class)->decide($item, ReviewDecision::Retain, $admin);
        }

        $this->generator->start($admin, now()->addDays(60), $organisation->id);

        foreach ($firstCycleIds as $id) {
            $this->assertNotNull(
                AccessReviewItem::query()->find($id),
                'A historical review record was removed. Nothing in this system deletes evidence.'
            );
            $this->assertSame('retained', AccessReviewItem::query()->findOrFail($id)->state->value);
        }
    }

    /**
     * 4. ONE CYCLE COVERS BOTH POPULATIONS. It is not a cycle per tab.
     *
     * Mutation: generate only the privileged half.
     */
    public function test_one_cycle_populates_both_privileged_and_domain_reviews(): void
    {
        [$admin, $organisation, $subject] = $this->fixture();
        $domain = $this->access->domain($organisation);
        $this->access->completePath($subject, $domain, RoleCode::Manager, ScopeType::Team, null, Sensitivity::Restricted);

        $this->generator->start($admin, now()->addDays(30), $organisation->id);

        $cycleIds = AccessReviewItem::query()->distinct()->pluck('access_review_cycle_id');

        $this->assertCount(1, $cycleIds, 'More than one cycle was created.');
        $this->assertTrue(AccessReviewItem::query()->where('kind', ReviewKind::Privileged->value)->exists());
        $this->assertTrue(AccessReviewItem::query()->where('kind', ReviewKind::Domain->value)->exists());

        // And both tabs read the same cycle.
        $this->assertNotEmpty($this->rowsOn($admin, '/console/access-reviews'));
        $this->assertNotEmpty($this->rowsOn($admin, '/console/access-reviews/domains'));
    }

    /**
     * 5. An overlapping cycle cannot be started by accident.
     *
     * Mutation: remove the outstanding-reviews check. The second cycle is then
     * created - empty, because generation still refuses duplicates - and the
     * button appears to do nothing at all.
     */
    public function test_an_overlapping_cycle_is_refused_with_a_business_message(): void
    {
        [$admin, $organisation] = $this->fixture();

        $this->generator->start($admin, now()->addDays(30), $organisation->id);
        $cyclesBefore = AccessReviewCycle::query()->count();

        $response = $this->signedInAs($admin)->post('/console/access-reviews/cycles');

        $this->assertSame($cyclesBefore, AccessReviewCycle::query()->count());
        $response->assertSessionHas(
            'refusal',
            'A review cycle is already in progress. Complete the outstanding reviews before starting another cycle.'
        );

        // Once the work is done, a new cycle is permitted again.
        foreach (AccessReviewItem::query()->pending()->get() as $item) {
            app(ReviewDecisionService::class)->decide($item, ReviewDecision::Retain, $admin);
        }

        $this->signedInAs($admin)->post('/console/access-reviews/cycles');

        $this->assertSame($cyclesBefore + 1, AccessReviewCycle::query()->count());
    }

    /**
     * DEFECT 1. The start control belongs to Privileged Reviews only.
     *
     * Mutation: pass offersStart on the other two screens.
     */
    public function test_only_privileged_reviews_offers_the_start_control(): void
    {
        [$admin] = $this->fixture();

        $this->assertTrue($this->propsOn($admin, '/console/access-reviews')['canStartCycle']);
        $this->assertFalse($this->propsOn($admin, '/console/access-reviews/domains')['canStartCycle']);
        $this->assertFalse($this->propsOn($admin, '/console/access-reviews/overdue')['canStartCycle']);
    }

    /**
     * The empty states the Product Owner confirmed are correct stay empty.
     *
     * ASSERTED ON WHAT THE SERVER CONTROLS. The sentences themselves are
     * rendered by React - this project has no server-side rendering and no
     * JavaScript test runner - so assertSee cannot reach them and a test that
     * claimed to check the wording would be checking nothing. The wording is a
     * browser check, recorded in the verification record.
     */
    public function test_the_screens_the_product_owner_confirmed_empty_stay_empty(): void
    {
        [$admin] = $this->fixture();
        $this->generator->start($admin, now()->addDays(30), $admin->organisation_id);

        $this->assertSame([], $this->rowsOn($admin, '/console/access-reviews/domains'));
        $this->assertSame([], $this->rowsOn($admin, '/console/access-reviews/overdue'));

        // And the privileged screen is NOT empty, so the two are distinguishable.
        $this->assertNotEmpty($this->rowsOn($admin, '/console/access-reviews'));
    }

    /** @return array{0: User, 1: Organisation, 2: User} */
    private function fixture(): array
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);
        $subject = $this->make->user($organisation);
        $this->access->assignment($subject, RoleCode::Auditor, $organisation);

        return [$admin, $organisation, $subject];
    }

    /** @return array<string, mixed> */
    private function propsOn(User $actor, string $path): array
    {
        return $this->signedInAs($actor)->get($path)->viewData('page')['props'];
    }

    /** @return list<array<string, mixed>> */
    private function rowsOn(User $actor, string $path): array
    {
        return $this->propsOn($actor, $path)['items']['data'];
    }

    private function signedInAs(User $user): self
    {
        return $this->withSession([
            EnsureSessionIsCurrent::SESSION_USER_ID => $user->id,
            EnsureSessionIsCurrent::SESSION_AUTHENTICATED_AT => now()->toIso8601String(),
        ]);
    }
}
