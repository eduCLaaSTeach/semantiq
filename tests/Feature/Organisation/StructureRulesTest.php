<?php

declare(strict_types=1);

namespace Tests\Feature\Organisation;

use App\Modules\Organisation\Models\StructureStatus;
use App\Modules\Organisation\Services\MembershipService;
use App\Modules\Organisation\Services\StructureService;
use App\Modules\Organisation\Support\StructureViolation;
use App\Modules\Platform\Security\SecurityEventLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\Support\OrganisationFactory;
use Tests\TestCase;

/**
 * Negative cases 5, 6, 7, 11, 12, 14 and 16.
 */
final class StructureRulesTest extends TestCase
{
    use RefreshDatabase;

    private OrganisationFactory $make;

    private StructureService $structure;

    protected function setUp(): void
    {
        parent::setUp();

        $this->make = new OrganisationFactory;
        $this->structure = app(StructureService::class);
    }

    /**
     * Case 5. Mutation: make organisation_id nullable.
     *
     * Asserted at the database, because that is the guarantee the application
     * rules rest on: if the column accepted NULL, every same-organisation check
     * above it would be comparing against nothing.
     */
    public function test_a_structural_record_cannot_exist_without_an_organisation(): void
    {
        foreach (['legal_entities', 'business_units', 'departments', 'teams',
            'team_memberships', 'management_relationships', 'business_unit_legal_entity'] as $table) {
            $column = collect(Schema::getColumns($table))
                ->firstWhere('name', 'organisation_id');

            $this->assertNotNull($column, "[{$table}] has no organisation_id column.");
            $this->assertFalse(
                $column['nullable'],
                "[{$table}].organisation_id is nullable, so a record can exist outside every organisation."
            );
        }
    }

    /** Case 6. Mutation: drop the same-organisation check. */
    public function test_a_department_cannot_move_to_a_business_unit_in_another_organisation(): void
    {
        $ours = $this->make->organisation('Ours');
        $theirs = $this->make->organisation('Theirs');

        $department = $this->make->department($this->make->businessUnit($ours));
        $foreign = $this->make->businessUnit($theirs, 'Foreign');

        $this->expectViolation('organisation_mismatch', fn () => $this->structure->moveDepartment(
            $department,
            $foreign,
            $this->make->user($ours, administrator: true)
        ));
    }

    /** Case 7. Mutation: drop rule 3. */
    public function test_a_team_cannot_be_created_under_a_department_of_another_organisation(): void
    {
        $ours = $this->make->organisation('Ours');
        $theirs = $this->make->organisation('Theirs');

        $foreignDepartment = $this->make->department($this->make->businessUnit($theirs));
        $team = $this->make->team($this->make->department($this->make->businessUnit($ours)));

        $this->expectViolation('organisation_mismatch', fn () => $this->structure->moveTeam(
            $team,
            $foreignDepartment,
            $this->make->user($ours, administrator: true)
        ));
    }

    /** Case 11. Mutation: cascade instead of refusing. */
    public function test_deactivating_a_business_unit_with_active_departments_is_refused_and_names_them(): void
    {
        $organisation = $this->make->organisation();
        $unit = $this->make->businessUnit($organisation);
        $this->make->department($unit, 'Engineering');
        $this->make->department($unit, 'Finance');

        try {
            $this->structure->deactivateBusinessUnit($unit, $this->make->user($organisation, administrator: true));
            $this->fail('Deactivating a business unit with active departments was permitted.');
        } catch (StructureViolation $violation) {
            $this->assertSame('active_children', $violation->reason);
            $this->assertEqualsCanonicalizing(['Engineering', 'Finance'], $violation->blockedBy);
        }

        // The cascade did not happen quietly instead.
        $this->assertSame(StructureStatus::Active, $unit->fresh()->status);
        $this->assertSame(2, $unit->departments()->where('status', 'active')->count());
    }

    /** Case 12. Mutation: drop the membership check. */
    public function test_deactivating_a_team_with_active_members_is_refused(): void
    {
        $organisation = $this->make->organisation();
        $team = $this->make->team($this->make->department($this->make->businessUnit($organisation)));
        $admin = $this->make->user($organisation, administrator: true);

        app(MembershipService::class)
            ->add($team, $this->make->user($organisation), $admin);

        $this->expectViolation('active_memberships', fn () => $this->structure->deactivateTeam($team, $admin));

        $this->assertSame(StructureStatus::Active, $team->fresh()->status);
    }

    /** Case 14. Mutation: drop the junction organisation check. */
    public function test_a_junction_row_may_not_cross_organisations(): void
    {
        $ours = $this->make->organisation('Ours');
        $theirs = $this->make->organisation('Theirs');

        $unit = $this->make->businessUnit($ours);
        $foreignEntity = $this->make->legalEntity($theirs, 'Foreign Pte Ltd');

        $this->expectViolation('organisation_mismatch', fn () => $this->structure->associate(
            $unit,
            $foreignEntity,
            $this->make->user($ours, administrator: true)
        ));

        $this->assertSame(0, $unit->legalEntities()->count());
    }

    /**
     * D-14, both directions. This is the check that a single-parent model would
     * fail, and the reason the many-to-one proposal was rejected.
     */
    public function test_the_association_is_many_to_many_in_both_directions(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);

        $unitA = $this->make->businessUnit($organisation, 'Delivery');
        $unitB = $this->make->businessUnit($organisation, 'Research');
        $entityA = $this->make->legalEntity($organisation, 'Acme SG');
        $entityB = $this->make->legalEntity($organisation, 'Acme MY');

        $this->structure->associate($unitA, $entityA, $admin);
        $this->structure->associate($unitA, $entityB, $admin);
        $this->structure->associate($unitB, $entityA, $admin);

        $this->assertSame(2, $unitA->legalEntities()->count(), 'One business unit must span several legal entities.');
        $this->assertSame(2, $entityA->businessUnits()->count(), 'One legal entity must span several business units.');
    }

    /** The junction carries association and nothing that could be read as entitlement. */
    public function test_the_junction_carries_no_attributes_beyond_the_association(): void
    {
        $columns = collect(Schema::getColumns('business_unit_legal_entity'))
            ->pluck('name')
            ->sort()
            ->values()
            ->all();

        $this->assertSame(
            ['business_unit_id', 'created_at', 'id', 'legal_entity_id', 'organisation_id', 'updated_at'],
            $columns,
            'The D-14 junction has grown an attribute. An attribute here is the first thing a later '
            .'unit reads as employment or entitlement, and the association grants nothing.'
        );
    }

    /** Case 16. Mutation: stop emitting on move. */
    public function test_a_move_is_recorded_as_scope_affecting(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);

        $from = $this->make->businessUnit($organisation, 'From');
        $to = $this->make->businessUnit($organisation, 'To');
        $department = $this->make->department($from);

        $recorded = [];
        Log::listen(function ($message) use (&$recorded): void {
            $recorded[] = $message->message;
        });

        $this->structure->moveDepartment($department, $to, $admin);

        $this->assertContains(
            SecurityEventLogger::DEPARTMENT_MOVED,
            $recorded,
            'A move emitted no scope-affecting event. A move is the change most likely to alter '
            ."someone's future scope, which is why it is not recorded as an ordinary update."
        );
    }

    /** Reactivating under an inactive parent would produce a live node hanging off a dead one. */
    public function test_a_child_cannot_be_reactivated_under_an_inactive_parent(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);

        $unit = $this->make->businessUnit($organisation);
        $department = $this->make->department($unit);

        $this->structure->deactivateDepartment($department, $admin);
        $this->structure->deactivateBusinessUnit($unit->fresh(), $admin);

        $this->expectViolation('inactive_parent', fn () => $this->structure->reactivate($department->fresh(), $admin));
    }

    /** Nothing may be created under an inactive parent either. */
    public function test_nothing_may_be_created_under_an_inactive_parent(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);

        $unit = $this->make->businessUnit($organisation);
        $this->structure->deactivateBusinessUnit($unit, $admin);

        $this->expectViolation('inactive_parent', fn () => $this->structure->createDepartment(
            $unit->fresh(),
            ['name' => 'Engineering'],
            $admin
        ));
    }

    private function expectViolation(string $reason, callable $action): void
    {
        try {
            $action();
            $this->fail("Expected a StructureViolation with reason [{$reason}], but the action was permitted.");
        } catch (StructureViolation $violation) {
            $this->assertSame(
                $reason,
                $violation->reason,
                "The action was refused, but for [{$violation->reason}] rather than [{$reason}] - so this "
                .'test would pass even with the guard it is meant to prove removed.'
            );
        }
    }
}
