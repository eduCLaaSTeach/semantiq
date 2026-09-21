<?php

declare(strict_types=1);

namespace Tests\Feature\Administration;

use App\Modules\Access\Support\RoleCode;
use App\Modules\Domains\Models\DomainOwnership;
use App\Modules\Domains\Projection\DomainSummary;
use App\Modules\Domains\Projection\DomainSummaryProjection;
use App\Modules\People\Models\Group;
use App\Modules\People\Models\GroupStatus;
use App\Modules\People\Projection\PeopleSummary;
use App\Modules\People\Projection\PeopleSummaryProjection;
use App\Modules\Platform\Models\UserStatus;
use App\Modules\Reviews\Projection\ReviewSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AccessFactory;
use Tests\Support\OrganisationFactory;
use Tests\TestCase;

/**
 * G5 - THE TWO NEW SEAMS, AT THEIR OWN LEVEL.
 *
 * These are owned by P1-03 and P1-04. They are tested here, with P1-11's other
 * work, only because P1-11 is the unit that added them; the property each one
 * holds belongs to its own module and the boundary tests say so.
 *
 * THE RULE UNDER TEST IS "A MISSING SCOPE NARROWS", and §11.2 names the exact
 * way that test goes wrong: a guard the framework was quietly enforcing. So
 * every null-organisation case below FIRST CREATES REAL DATA that a widened
 * projection would happily count, and only then asserts that nothing is
 * counted. A fixture with an empty database would let "return everything" pass.
 */
final class PeopleAndDomainSummarySeamsTest extends TestCase
{
    use RefreshDatabase;

    private OrganisationFactory $make;

    private AccessFactory $access;

    protected function setUp(): void
    {
        parent::setUp();

        $this->make = new OrganisationFactory;
        $this->access = new AccessFactory;
    }

    private function people(): PeopleSummaryProjection
    {
        return app(PeopleSummaryProjection::class);
    }

    private function domains(): DomainSummaryProjection
    {
        return app(DomainSummaryProjection::class);
    }

    /** The ordinary case: three counts, for one organisation. */
    public function test_people_are_counted_for_the_organisation_that_was_asked_for(): void
    {
        $organisation = $this->make->organisation();
        $viewer = $this->make->user($organisation, administrator: true);

        $this->make->user($organisation);
        $this->make->user($organisation, status: UserStatus::Inactive);
        $this->make->user($organisation, status: UserStatus::Inactive);

        Group::query()->create([
            'organisation_id' => $organisation->id,
            'name' => 'Finance',
            'status' => GroupStatus::Active,
        ]);
        Group::query()->create([
            'organisation_id' => $organisation->id,
            'name' => 'Retired',
            'status' => GroupStatus::Inactive,
        ]);

        $summary = $this->people()->for($viewer, $organisation->id);

        $this->assertTrue($summary->valued);
        // The viewer is active and counted too.
        $this->assertSame(2, $summary->activeUsers);
        $this->assertSame(2, $summary->inactiveUsers);
        $this->assertSame(1, $summary->activeGroups);
    }

    /**
     * G5. A NULL ORGANISATION IS WITHHELD, NOT A GLOBAL COUNT AND NOT ZERO.
     *
     * THE PREMISE IS ESTABLISHED FIRST. Four people and two groups exist in a
     * real organisation before the null call, so a projection that dropped its
     * scope would return 2/2/1 here and this would fail loudly. Against an
     * empty database the mutation would pass.
     *
     * Mutation: make for() skip the where('organisation_id', ...) when the
     * argument is null.
     */
    public function test_a_null_organisation_withholds_people_and_never_counts_globally(): void
    {
        $organisation = $this->make->organisation();
        $viewer = $this->make->user($organisation, administrator: true);

        $this->make->user($organisation);
        $this->make->user($organisation, status: UserStatus::Inactive);

        Group::query()->create([
            'organisation_id' => $organisation->id,
            'name' => 'Finance',
            'status' => GroupStatus::Active,
        ]);

        // The premise, asserted rather than assumed: there IS something to
        // count, so "nothing was counted" cannot be true by accident.
        $this->assertSame(2, $this->people()->for($viewer, $organisation->id)->activeUsers);

        $summary = $this->people()->for($viewer, null);

        $this->assertFalse($summary->valued);
        $this->assertNull($summary->activeUsers, 'A withheld people summary carried a count.');
        $this->assertNull($summary->inactiveUsers);
        $this->assertNull($summary->activeGroups);
    }

    /** An inactive viewer is told nothing, whatever the middleware did. */
    public function test_an_inactive_viewer_is_told_nothing_by_either_seam(): void
    {
        $organisation = $this->make->organisation();
        $viewer = $this->make->user($organisation, administrator: true, status: UserStatus::Inactive);

        $this->make->user($organisation);
        $this->access->domain($organisation, 'finance', 'Finance');

        $this->assertFalse($this->people()->for($viewer, $organisation->id)->valued);
        $this->assertFalse($this->domains()->for($viewer, $organisation->id)->valued);
    }

    /**
     * A viewer belonging to another organisation is told nothing about this
     * one. Release 1 is single-tenant so this is unreachable through the
     * screens, which is exactly why it is asserted.
     */
    public function test_a_viewer_from_another_organisation_is_told_nothing(): void
    {
        $mine = $this->make->organisation('Acme');
        $theirs = $this->make->organisation('Other');

        $outsider = $this->make->user($theirs);
        $this->access->assignment($outsider, RoleCode::OrganisationAdministrator, $theirs);

        $this->make->user($mine);
        $this->access->domain($mine, 'finance', 'Finance');

        $this->assertFalse($this->people()->for($outsider, $mine->id)->valued);
        $this->assertFalse($this->domains()->for($outsider, $mine->id)->valued);
    }

    /**
     * DOMAINS RETURN FACTS: enabled, and enabled without a current owner.
     *
     * A DISABLED DOMAIN WITH NO OWNER IS IN NEITHER FIGURE, and the fixture
     * contains one so that the exclusion is exercised rather than described.
     */
    public function test_domains_count_enabled_and_enabled_without_a_current_owner(): void
    {
        $organisation = $this->make->organisation();
        $viewer = $this->make->user($organisation, administrator: true);

        $owned = $this->access->domain($organisation, 'finance', 'Finance');
        $this->access->domain($organisation, 'sales', 'Sales');
        $this->access->domain($organisation, 'legacy', 'Legacy', status: 'disabled');

        DomainOwnership::query()->create([
            'business_domain_id' => $owned->id,
            'user_id' => $viewer->id,
            'assigned_at' => now(),
            'ended_at' => null,
        ]);

        $summary = $this->domains()->for($viewer, $organisation->id);

        $this->assertTrue($summary->valued);
        $this->assertSame(2, $summary->enabled, 'The disabled domain was counted as enabled.');
        $this->assertSame(1, $summary->enabledUnowned);
    }

    /**
     * AN ENDED OWNERSHIP IS NOT A CURRENT ONE, and this is what reusing
     * currentOwnership() buys.
     *
     * Mutation: count against ownerships() rather than currentOwnership(). The
     * domain below has a closed ownership row and no open one, so a projection
     * asking the wrong relation reports it owned.
     */
    public function test_an_ended_ownership_leaves_a_domain_unowned(): void
    {
        $organisation = $this->make->organisation();
        $viewer = $this->make->user($organisation, administrator: true);

        $domain = $this->access->domain($organisation, 'finance', 'Finance');

        DomainOwnership::query()->create([
            'business_domain_id' => $domain->id,
            'user_id' => $viewer->id,
            'assigned_at' => now()->subYear(),
            'ended_at' => now()->subDay(),
        ]);

        $summary = $this->domains()->for($viewer, $organisation->id);

        $this->assertSame(1, $summary->enabled);
        $this->assertSame(
            1,
            $summary->enabledUnowned,
            'A domain whose only ownership period has ended was counted as owned.'
        );
    }

    /**
     * G5, the Domains half. Same shape, same reason, same established premise.
     *
     * Mutation: drop the organisation scope on the null path.
     */
    public function test_a_null_organisation_withholds_domains_and_never_counts_globally(): void
    {
        $organisation = $this->make->organisation();
        $viewer = $this->make->user($organisation, administrator: true);

        $this->access->domain($organisation, 'finance', 'Finance');
        $this->access->domain($organisation, 'sales', 'Sales');

        $this->assertSame(2, $this->domains()->for($viewer, $organisation->id)->enabled);

        $summary = $this->domains()->for($viewer, null);

        $this->assertFalse($summary->valued);
        $this->assertNull($summary->enabled, 'A withheld domain summary carried a count.');
        $this->assertNull($summary->enabledUnowned);
    }

    /**
     * G6. THE TWO DTOs CARRY EXACTLY THEIR DECLARED PROPERTIES.
     *
     * Mutation: add `public array $userNames` to PeopleSummary. A name, an
     * email or a membership list arriving through a summary object is the leak
     * these shapes exist to make unrepresentable.
     */
    public function test_the_two_summaries_carry_exactly_their_declared_fields(): void
    {
        $shape = static function (string $class): array {
            $names = array_map(
                static fn (\ReflectionProperty $property): string => $property->getName(),
                (new \ReflectionClass($class))->getProperties(),
            );

            sort($names);

            return $names;
        };

        $this->assertSame(
            ['activeGroups', 'activeUsers', 'inactiveUsers', 'valued'],
            $shape(PeopleSummary::class),
        );

        $this->assertSame(
            ['enabled', 'enabledUnowned', 'valued'],
            $shape(DomainSummary::class),
        );

        $this->assertSame(
            ['outstanding', 'overdue', 'valued'],
            $shape(ReviewSummary::class),
        );
    }
}
