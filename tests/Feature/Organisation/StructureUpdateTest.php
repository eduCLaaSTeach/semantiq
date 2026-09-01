<?php

declare(strict_types=1);

namespace Tests\Feature\Organisation;

use App\Modules\Organisation\Models\ManagementRelationship;
use App\Modules\Organisation\Services\ManagementService;
use App\Modules\Organisation\Services\StructureService;
use App\Modules\Organisation\Support\Jurisdictions;
use App\Modules\Organisation\Support\StructureViolation;
use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;
use App\Modules\Platform\Models\User;
use App\Modules\Platform\Security\SecurityEventLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\Support\OrganisationFactory;
use Tests\TestCase;

/**
 * The P1-01 scope completion: the Update half of the lifecycle.
 *
 * P1-01 was accepted with Create, Read, Deactivate and Reactivate present and
 * Update missing on four of the five scoped entities. These cases exist so that
 * the gap cannot reopen silently.
 *
 * The cases that matter most are the ones separating Edit from Move. A rename is
 * a correction; a re-parent is a restructure that P1-05 will read to resolve
 * scope. If a rename could quietly change a parent, the audit catalogue would
 * record a restructure that never happened - and, worse, one that did happen
 * without being recorded as such.
 */
final class StructureUpdateTest extends TestCase
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

    // -- Legal entities ----------------------------------------------------

    /**
     * Every field the design lists, through the service.
     *
     * Mutation: drop 'registered_address' from the allow-list in
     * updateLegalEntity(). The address stops persisting and this fails - which
     * is exactly the defect that shipped, in miniature.
     */
    public function test_a_legal_entity_updates_every_field_the_design_lists(): void
    {
        $organisation = $this->make->organisation();
        $entity = $this->make->legalEntity($organisation, 'Acme Pte Ltd');

        $this->structure->updateLegalEntity($entity, [
            'name' => 'Acme Singapore Pte Ltd',
            'registration_number' => '201812345K',
            'jurisdiction' => 'Singapore',
            'registered_address' => '1 Raffles Place, Singapore 048616',
        ], $this->make->user($organisation, administrator: true));

        $fresh = $entity->fresh();

        $this->assertSame('Acme Singapore Pte Ltd', $fresh->name);
        $this->assertSame('201812345K', $fresh->registration_number);
        $this->assertSame('Singapore', $fresh->jurisdiction);
        $this->assertSame('1 Raffles Place, Singapore 048616', $fresh->registered_address);
    }

    /** Mutation: drop the jurisdiction check from applyUpdate(). */
    public function test_a_jurisdiction_outside_the_approved_list_is_refused_by_the_service(): void
    {
        $organisation = $this->make->organisation();
        $entity = $this->make->legalEntity($organisation);

        $this->expectViolation('unknown_jurisdiction', fn () => $this->structure->updateLegalEntity(
            $entity,
            ['jurisdiction' => 'Wakanda'],
            $this->make->user($organisation, administrator: true)
        ));

        $this->assertNotSame('Wakanda', $entity->fresh()->jurisdiction);
    }

    /**
     * The dropdown constrains the browser. This constrains everybody else.
     *
     * Mutation: remove ApprovedJurisdiction from the controller's rules. The
     * request is accepted, the value lands in the column, and this fails.
     */
    public function test_a_jurisdiction_outside_the_approved_list_is_refused_over_http(): void
    {
        $organisation = $this->make->organisation();
        $entity = $this->make->legalEntity($organisation);

        $this->actingAsUser($this->make->user($organisation, administrator: true))
            ->put("/console/organisation/legal-entities/{$entity->id}", [
                'name' => $entity->name,
                'jurisdiction' => 'Wakanda',
            ])
            ->assertSessionHasErrors('jurisdiction');

        $this->assertNotSame('Wakanda', $entity->fresh()->jurisdiction);
    }

    /** Not recorded is a real state, so the empty choice must survive the same guard. */
    public function test_an_unrecorded_jurisdiction_is_permitted(): void
    {
        $organisation = $this->make->organisation();
        $entity = $this->make->legalEntity($organisation);

        $this->structure->updateLegalEntity(
            $entity,
            ['jurisdiction' => ''],
            $this->make->user($organisation, administrator: true)
        );

        $this->assertSame('', $entity->fresh()->jurisdiction);
    }

    // -- The packaged list -------------------------------------------------

    /**
     * The list is packaged, not fetched. A runtime dependency on an external
     * service would make a form field fail when somebody else's site is down.
     *
     * Mutation: make Jurisdictions::all() call an HTTP client. The guard below
     * catches it; the count and ordering cases would not.
     */
    public function test_the_jurisdiction_list_is_packaged_and_makes_no_runtime_call(): void
    {
        $source = file_get_contents(
            (new \ReflectionClass(Jurisdictions::class))->getFileName()
        );

        foreach (['Http::', 'file_get_contents', 'curl_', 'fopen', 'Guzzle'] as $call) {
            $this->assertStringNotContainsString(
                $call,
                $source,
                "The jurisdiction list reaches out at runtime via {$call}. It must be packaged."
            );
        }

        $this->assertGreaterThan(200, Jurisdictions::count(), 'The packaged list is implausibly short.');
    }

    /** Alphabetical by the name the Product Owner reads, not by ISO code. */
    public function test_the_jurisdiction_list_is_alphabetical_and_contains_singapore(): void
    {
        $all = Jurisdictions::all();

        $this->assertContains('Singapore', $all);

        $sorted = $all;
        sort($sorted, SORT_NATURAL | SORT_FLAG_CASE);

        $this->assertSame($sorted, $all, 'The jurisdiction list is not in alphabetical order.');
    }

    /**
     * Preserving existing valid data is a requirement, not a hope: the column
     * already holds display names, and Singapore is the one in production.
     *
     * Mutation: store ISO codes instead of names. 'Singapore' stops being a
     * permitted value and every existing row becomes unselectable.
     */
    public function test_an_existing_jurisdiction_value_remains_valid(): void
    {
        $this->assertTrue(
            Jurisdictions::permits('Singapore'),
            'The recorded production jurisdiction is no longer on the approved list.'
        );
    }

    // -- Business units ----------------------------------------------------

    /** Mutation: delete updateBusinessUnit(). */
    public function test_a_business_unit_updates_its_name_and_code(): void
    {
        $organisation = $this->make->organisation();
        $unit = $this->make->businessUnit($organisation, 'Retai Sales');

        $this->structure->updateBusinessUnit(
            $unit,
            ['name' => 'Retail Sales', 'code' => 'RS'],
            $this->make->user($organisation, administrator: true)
        );

        $this->assertSame('Retail Sales', $unit->fresh()->name);
        $this->assertSame('RS', $unit->fresh()->code);
    }

    // -- Departments: edit is not move -------------------------------------

    /** The correction the Product Owner asked for, made through the application. */
    public function test_a_department_spelling_correction_is_applied(): void
    {
        $organisation = $this->make->organisation();
        $department = $this->make->department($this->make->businessUnit($organisation), 'Singapore Retai Sales');

        $this->structure->updateDepartment(
            $department,
            ['name' => 'Singapore Retail Sales'],
            $this->make->user($organisation, administrator: true)
        );

        $this->assertSame('Singapore Retail Sales', $department->fresh()->name);
    }

    /**
     * Mutation: add 'business_unit_id' to the allow-list in updateDepartment().
     *
     * The parent then changes, no *.moved event is emitted, and the audit
     * catalogue records a rename where a restructure happened.
     */
    public function test_updating_a_department_cannot_re_parent_it(): void
    {
        $organisation = $this->make->organisation();
        $from = $this->make->businessUnit($organisation, 'From');
        $to = $this->make->businessUnit($organisation, 'To');
        $department = $this->make->department($from);

        $this->structure->updateDepartment(
            $department,
            ['name' => 'Renamed', 'business_unit_id' => $to->id],
            $this->make->user($organisation, administrator: true)
        );

        $this->assertSame('Renamed', $department->fresh()->name);
        $this->assertSame(
            $from->id,
            $department->fresh()->business_unit_id,
            'A rename re-parented the department. Edit and Move must remain different operations.'
        );
    }

    /**
     * The same separation, over HTTP, where the attacker actually is.
     *
     * The rename is asserted FIRST, and that is not decoration. Written without
     * it, this case passed while the parent-key mutation was live: a request
     * refused for any unrelated reason changes nothing, so "the parent did not
     * change" was satisfied by the request never having worked. Proving the
     * update landed is what makes the second assertion mean anything.
     *
     * There are two independent barriers on this path - the controller's
     * validated-key set and the service's allow-list - so breaking either one
     * alone leaves the other holding, and both single mutations SURVIVE. That is
     * not a weak test; it is what defence in depth looks like from the outside.
     * The mutation that matters is the one somebody who misunderstood the rule
     * would actually write: "Edit should let you change the business unit too",
     * applied to both places at once. CAUGHT.
     */
    public function test_the_department_update_endpoint_ignores_a_submitted_parent(): void
    {
        $organisation = $this->make->organisation();
        $from = $this->make->businessUnit($organisation, 'From');
        $to = $this->make->businessUnit($organisation, 'To');
        $department = $this->make->department($from, 'Original');

        $this->actingAsUser($this->make->user($organisation, administrator: true))
            ->put("/console/organisation/departments/{$department->id}", [
                'name' => 'Renamed',
                'business_unit_id' => $to->id,
            ])
            ->assertRedirect(route('organisation.departments'));

        $this->assertSame(
            'Renamed',
            $department->fresh()->name,
            'The update never took effect, so this case would prove nothing about the parent.'
        );

        $this->assertSame(
            $from->id,
            $department->fresh()->business_unit_id,
            'A submitted parent key re-parented the department through the update endpoint.'
        );
    }

    /** Mutation: drop the *_UPDATED record from applyUpdate(). */
    public function test_an_update_is_recorded_but_not_as_a_move(): void
    {
        $organisation = $this->make->organisation();
        $department = $this->make->department($this->make->businessUnit($organisation));

        $recorded = [];
        Log::listen(function ($message) use (&$recorded): void {
            $recorded[] = $message->message;
        });

        $this->structure->updateDepartment(
            $department,
            ['name' => 'Renamed'],
            $this->make->user($organisation, administrator: true)
        );

        $this->assertContains(SecurityEventLogger::DEPARTMENT_UPDATED, $recorded);
        $this->assertNotContains(
            SecurityEventLogger::DEPARTMENT_MOVED,
            $recorded,
            'A rename emitted a move event. The catalogue would then record a restructure that never happened.'
        );
    }

    // -- Teams -------------------------------------------------------------

    /** Mutation: delete updateTeam(). */
    public function test_a_team_updates_its_name_and_code(): void
    {
        $organisation = $this->make->organisation();
        $team = $this->make->team($this->make->department($this->make->businessUnit($organisation)));

        $this->structure->updateTeam(
            $team,
            ['name' => 'Platform Engineering', 'code' => 'PE'],
            $this->make->user($organisation, administrator: true)
        );

        $this->assertSame('Platform Engineering', $team->fresh()->name);
        $this->assertSame('PE', $team->fresh()->code);
    }

    /** Mutation: add 'department_id' to the allow-list in updateTeam(). */
    public function test_updating_a_team_cannot_re_parent_it(): void
    {
        $organisation = $this->make->organisation();
        $from = $this->make->department($this->make->businessUnit($organisation, 'A'), 'From');
        $to = $this->make->department($this->make->businessUnit($organisation, 'B'), 'To');
        $team = $this->make->team($from);

        $this->structure->updateTeam(
            $team,
            ['name' => 'Renamed', 'department_id' => $to->id],
            $this->make->user($organisation, administrator: true)
        );

        $this->assertSame($from->id, $team->fresh()->department_id);
    }

    // -- Shared update guards ----------------------------------------------

    /**
     * Mutation: drop requireSameOrganisation() from applyUpdate().
     *
     * Update was added after the rest of the unit, so it is the operation most
     * likely to have been written without the boundary the others carry.
     */
    public function test_an_update_may_not_cross_organisations(): void
    {
        $ours = $this->make->organisation('Ours');
        $theirs = $this->make->organisation('Theirs');

        $foreign = $this->make->businessUnit($theirs, 'Foreign');

        $this->expectViolation('organisation_mismatch', fn () => $this->structure->updateBusinessUnit(
            $foreign,
            ['name' => 'Taken'],
            $this->make->user($ours, administrator: true)
        ));

        $this->assertSame('Foreign', $foreign->fresh()->name);
    }

    /** Mutation: drop the blank-name check. A whitespace name renders as an empty row. */
    public function test_an_update_may_not_blank_a_name(): void
    {
        $organisation = $this->make->organisation();
        $unit = $this->make->businessUnit($organisation, 'Delivery');

        $this->expectViolation('invalid_name', fn () => $this->structure->updateBusinessUnit(
            $unit,
            ['name' => '   '],
            $this->make->user($organisation, administrator: true)
        ));

        $this->assertSame('Delivery', $unit->fresh()->name);
    }

    /** Update is an administrator operation, like every other write in the unit. */
    public function test_a_non_administrator_may_not_update_structure(): void
    {
        $organisation = $this->make->organisation();
        $unit = $this->make->businessUnit($organisation, 'Delivery');

        $this->actingAsUser($this->make->user($organisation))
            ->put("/console/organisation/business-units/{$unit->id}", ['name' => 'Taken'])
            ->assertRedirect(route('auth.access-denied'));

        $this->assertSame('Delivery', $unit->fresh()->name);
    }

    // -- Management hierarchy ----------------------------------------------

    /**
     * Change is Set applied to a user who already has a manager. The previous
     * link is ended, not deleted.
     *
     * Mutation: replace the effective_to update with a delete. The current
     * manager is still right and only this assertion fails - which is the whole
     * reason it is asserted separately.
     */
    public function test_changing_a_manager_ends_the_previous_link_and_keeps_it(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);

        $subject = $this->make->user($organisation);
        $first = $this->make->user($organisation);
        $second = $this->make->user($organisation);

        $management = app(ManagementService::class);

        // Yesterday, so the change is history rather than a same-day correction.
        $management->setManager($subject, $first, $admin);
        ManagementRelationship::query()
            ->where('user_id', $subject->id)
            ->update(['effective_from' => now()->subDay()->toDateString()]);

        $management->setManager($subject->fresh(), $second, $admin);

        $links = ManagementRelationship::query()->where('user_id', $subject->id)->get();

        $this->assertCount(2, $links, 'The previous management link was destroyed rather than ended.');

        $current = $links->firstWhere('effective_to', null);
        $this->assertNotNull($current);
        $this->assertSame($second->id, $current->manager_id);

        $ended = $links->first(fn (ManagementRelationship $link): bool => $link->effective_to !== null);
        $this->assertNotNull($ended, 'No ended link remains, so the history cannot be answered.');
        $this->assertSame($first->id, $ended->manager_id);
    }

    private function expectViolation(string $reason, callable $action): void
    {
        try {
            $action();
            $this->fail("Expected a StructureViolation with reason [{$reason}]; none was thrown.");
        } catch (StructureViolation $violation) {
            $this->assertSame($reason, $violation->reason);
        }
    }

    private function actingAsUser(User $user): self
    {
        return $this->withSession([
            EnsureSessionIsCurrent::SESSION_USER_ID => $user->id,
            EnsureSessionIsCurrent::SESSION_AUTHENTICATED_AT => now()->toIso8601String(),
        ]);
    }
}
