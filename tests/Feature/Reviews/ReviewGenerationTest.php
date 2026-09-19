<?php

declare(strict_types=1);

namespace Tests\Feature\Reviews;

use App\Modules\Access\Support\RoleCatalogue;
use App\Modules\Access\Support\RoleCode;
use App\Modules\Access\Support\ScopeType;
use App\Modules\Access\Support\Sensitivity;
use App\Modules\Platform\Models\UserStatus;
use App\Modules\Reviews\Models\AccessReviewItem;
use App\Modules\Reviews\Services\ReviewCycleGenerator;
use App\Modules\Reviews\Support\ReviewKind;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AccessFactory;
use Tests\Support\OrganisationFactory;
use Tests\TestCase;

/**
 * N-R13, N-R21, N-R26, N-R27. WHO GETS REVIEWED, AND HOW OFTEN.
 */
final class ReviewGenerationTest extends TestCase
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
     * N-R26. The privileged population is DERIVED from the catalogue.
     *
     * A role added later with an administration class is included by
     * construction. A literal list of three role codes would silently omit it,
     * and nobody would notice until an audit asked why.
     *
     * Mutation: hard-code ['system_administrator', 'organisation_administrator',
     * 'auditor'].
     */
    public function test_the_privileged_population_is_every_role_holding_an_administration_class(): void
    {
        $expected = [];

        foreach (RoleCode::cases() as $role) {
            foreach (RoleCatalogue::administrationClasses() as $class) {
                if (RoleCatalogue::permits($role, $class)) {
                    $expected[$role->value] = true;
                    break;
                }
            }
        }

        $actual = $this->generator->privilegedRoleCodes();
        sort($actual);
        $expectedKeys = array_keys($expected);
        sort($expectedKeys);

        $this->assertSame($expectedKeys, $actual);

        // And the business roles are NOT privileged, whatever their names.
        $this->assertNotContains('domain_owner', $actual);
        $this->assertNotContains('manager', $actual);
    }

    /** D-85. Auditor is in the population. */
    public function test_an_auditor_assignment_is_reviewed(): void
    {
        $organisation = $this->make->organisation();
        $starter = $this->make->user($organisation, administrator: true);
        $auditor = $this->make->user($organisation);
        $this->access->assignment($auditor, RoleCode::Auditor, $organisation);

        $this->generator->start($starter, now()->addDays(30), $organisation->id);

        $this->assertTrue(
            AccessReviewItem::query()
                ->where('kind', ReviewKind::Privileged->value)
                ->where('subject_user_id', $auditor->id)
                ->exists()
        );
    }

    /**
     * N-R27 and D-86. Sensitive by DEPTH or by BREADTH, both derived.
     *
     * Mutation: hard-code ('domain','organisation'), or drop the scope half.
     */
    public function test_the_domain_population_is_sensitive_by_depth_or_by_breadth(): void
    {
        $organisation = $this->make->organisation();
        $starter = $this->make->user($organisation, administrator: true);
        $domain = $this->access->domain($organisation);

        $ordinary = $this->make->user($organisation);
        $this->access->completePath($ordinary, $domain, RoleCode::BusinessUser, ScopeType::Team, null, Sensitivity::Standard);

        $deep = $this->make->user($organisation);
        $this->access->completePath($deep, $domain, RoleCode::Manager, ScopeType::Team, null, Sensitivity::Confidential);

        $broad = $this->make->user($organisation);
        $this->access->completePath($broad, $domain, RoleCode::Executive, ScopeType::Domain, null, Sensitivity::Standard);

        $this->generator->start($starter, now()->addDays(30), $organisation->id);

        $reviewed = AccessReviewItem::query()
            ->where('kind', ReviewKind::Domain->value)
            ->pluck('subject_user_id')
            ->all();

        $this->assertContains($deep->id, $reviewed, 'A confidential grant was not reviewed.');
        $this->assertContains($broad->id, $reviewed, 'A whole-domain grant was not reviewed.');
        $this->assertNotContains(
            $ordinary->id,
            $reviewed,
            'An ordinary standard, team-scoped grant was reviewed. That population is unbounded and the screen becomes unreadable.'
        );
    }

    /**
     * N-R21. Generation is idempotent, twice over.
     *
     * Mutation: remove the already-pending check. A second cycle then raises a
     * second open question about one grant.
     */
    public function test_generation_never_raises_two_open_questions_about_one_grant(): void
    {
        $organisation = $this->make->organisation();
        $starter = $this->make->user($organisation, administrator: true);
        $subject = $this->make->user($organisation);
        $domain = $this->access->domain($organisation);
        $this->access->completePath($subject, $domain, RoleCode::Manager, ScopeType::Team, null, Sensitivity::Restricted);

        $this->generator->start($starter, now()->addDays(30), $organisation->id);
        $first = AccessReviewItem::query()->count();

        $this->generator->start($starter, now()->addDays(60), $organisation->id);

        $this->assertSame($first, AccessReviewItem::query()->count(), 'A second cycle duplicated open items.');
    }

    /**
     * N-R13. Overdue is deterministic - an INSTANT comparison, not a local
     * date.
     *
     * Mutation: compare dates. The item due later today then reads overdue in
     * a timezone behind UTC.
     */
    public function test_overdue_is_an_instant_comparison(): void
    {
        $organisation = $this->make->organisation();
        $starter = $this->make->user($organisation, administrator: true);
        $this->generator->start($starter, now()->addHours(2), $organisation->id);

        $this->assertSame(0, AccessReviewItem::query()->overdue()->count());

        $this->travel(3)->hours();

        $this->assertGreaterThan(0, AccessReviewItem::query()->overdue()->count());
        $this->travelBack();
    }

    /** Inactive people are not put up for review - their access already grants nothing. */
    public function test_an_inactive_persons_access_is_not_generated(): void
    {
        $organisation = $this->make->organisation();
        $starter = $this->make->user($organisation, administrator: true);
        $inactive = $this->make->user($organisation, status: UserStatus::Inactive);
        $this->access->assignment($inactive, RoleCode::Auditor, $organisation);

        $this->generator->start($starter, now()->addDays(30), $organisation->id);

        $this->assertFalse(
            AccessReviewItem::query()->where('subject_user_id', $inactive->id)->exists()
        );
    }
}
