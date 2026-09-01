<?php

declare(strict_types=1);

namespace Tests\Feature\Organisation;

use App\Modules\Organisation\Models\BusinessUnit;
use App\Modules\Organisation\Models\Department;
use App\Modules\Organisation\Models\LegalEntity;
use App\Modules\Organisation\Models\ManagementRelationship;
use App\Modules\Organisation\Models\StructureStatus;
use App\Modules\Organisation\Models\Team;
use App\Modules\Organisation\Models\TeamMembership;
use App\Modules\Organisation\Services\ManagementService;
use App\Modules\Organisation\Services\MembershipService;
use App\Modules\Organisation\Services\OrganisationService;
use App\Modules\Organisation\Services\StructureService;
use App\Modules\Organisation\Support\PurgeDependencies;
use App\Modules\Organisation\Support\StructureViolation;
use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;
use App\Modules\Platform\Models\User;
use App\Modules\Platform\Security\SecurityEventLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\Support\OrganisationFactory;
use Tests\TestCase;

/**
 * D-24 — guarded permanent delete.
 *
 * D-24 supersedes the blanket "no hard delete anywhere" rule for four master
 * types. Everything here exists to make the word GUARDED true: a purge that
 * fires when a dependency exists is worse than no purge at all, because the
 * unit's whole retention promise rests on it.
 *
 * Two rules shape almost every case below:
 *
 *  - **An inactive child still counts.** Status is never consulted. A
 *    deactivated department blocks its business unit exactly as an active one
 *    does, and an ended membership blocks its team exactly as a current one
 *    does. This is the mistake somebody who half-understood the rule would
 *    make, so it is asserted for every type in both states.
 *  - **Nothing is ever cascaded.** A refusal names the blocker; it never
 *    removes it. Every refusal case asserts that the dependency is still there
 *    afterwards, because a cascade would also produce a "successful" purge.
 */
final class PurgeGuardTest extends TestCase
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

    // -- Legal entity ------------------------------------------------------

    /** The case D-24 exists for: a master record entered by mistake, used by nothing. */
    public function test_an_unused_legal_entity_can_be_purged(): void
    {
        $organisation = $this->make->organisation();
        $entity = $this->make->legalEntity($organisation, 'Typo Pte Ltd');

        $this->structure->purgeLegalEntity($entity, $this->make->user($organisation, administrator: true));

        $this->assertNull(LegalEntity::query()->find($entity->id));
    }

    /** Mutation: drop the junction from the reference walk. */
    public function test_an_associated_legal_entity_cannot_be_purged(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);

        $entity = $this->make->legalEntity($organisation);
        $unit = $this->make->businessUnit($organisation);
        $this->structure->associate($unit, $entity, $admin);

        $this->expectViolation('in_use', fn () => $this->structure->purgeLegalEntity($entity, $admin));

        $this->assertNotNull($entity->fresh(), 'The legal entity was destroyed despite an association.');
        $this->assertSame(1, $unit->legalEntities()->count(), 'The association was cascaded away.');
    }

    // -- Business unit -----------------------------------------------------

    public function test_an_unused_business_unit_can_be_purged(): void
    {
        $organisation = $this->make->organisation();
        $unit = $this->make->businessUnit($organisation, 'Entered by mistake');

        $this->structure->purgeBusinessUnit($unit, $this->make->user($organisation, administrator: true));

        $this->assertNull(BusinessUnit::query()->find($unit->id));
    }

    /**
     * Both states, in one case, because the pair is the point.
     *
     * Mutation: filter the department count to active rows only - the change
     * somebody would make by pattern-matching on deactivateBusinessUnit(), which
     * legitimately does exactly that. The active half keeps passing and only the
     * inactive half fails.
     */
    public function test_a_business_unit_with_a_department_cannot_be_purged_in_either_state(): void
    {
        foreach ([StructureStatus::Active, StructureStatus::Inactive] as $status) {
            $organisation = $this->make->organisation('Org '.$status->value);
            $admin = $this->make->user($organisation, administrator: true);

            $unit = $this->make->businessUnit($organisation);
            $department = $this->make->department($unit);
            $department->forceFill(['status' => $status])->save();

            $this->expectViolation('in_use', fn () => $this->structure->purgeBusinessUnit($unit, $admin));

            $this->assertNotNull(
                $unit->fresh(),
                "A business unit with a {$status->value} department was destroyed."
            );
            $this->assertNotNull($department->fresh(), 'The department was cascaded away.');
        }
    }

    public function test_a_business_unit_with_a_legal_entity_association_cannot_be_purged(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);

        $unit = $this->make->businessUnit($organisation);
        $this->structure->associate($unit, $this->make->legalEntity($organisation), $admin);

        $this->expectViolation('in_use', fn () => $this->structure->purgeBusinessUnit($unit, $admin));

        $this->assertNotNull($unit->fresh());
    }

    // -- Department --------------------------------------------------------

    public function test_an_unused_department_can_be_purged(): void
    {
        $organisation = $this->make->organisation();
        $department = $this->make->department($this->make->businessUnit($organisation), 'Mistake');

        $this->structure->purgeDepartment($department, $this->make->user($organisation, administrator: true));

        $this->assertNull(Department::query()->find($department->id));
    }

    /** Mutation: filter the team count to active rows only. */
    public function test_a_department_with_a_team_cannot_be_purged_in_either_state(): void
    {
        foreach ([StructureStatus::Active, StructureStatus::Inactive] as $status) {
            $organisation = $this->make->organisation('Org '.$status->value);
            $admin = $this->make->user($organisation, administrator: true);

            $department = $this->make->department($this->make->businessUnit($organisation));
            $team = $this->make->team($department);
            $team->forceFill(['status' => $status])->save();

            $this->expectViolation('in_use', fn () => $this->structure->purgeDepartment($department, $admin));

            $this->assertNotNull($department->fresh(), "A department with a {$status->value} team was destroyed.");
            $this->assertNotNull($team->fresh(), 'The team was cascaded away.');
        }
    }

    // -- Team --------------------------------------------------------------

    public function test_an_unused_team_can_be_purged(): void
    {
        $organisation = $this->make->organisation();
        $team = $this->make->team($this->make->department($this->make->businessUnit($organisation)), 'Mistake');

        $this->structure->purgeTeam($team, $this->make->user($organisation, administrator: true));

        $this->assertNull(Team::query()->find($team->id));
    }

    public function test_a_team_with_a_current_member_cannot_be_purged(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);

        $team = $this->make->team($this->make->department($this->make->businessUnit($organisation)));
        app(MembershipService::class)->add($team, $this->make->user($organisation), $admin);

        $this->expectViolation('in_use', fn () => $this->structure->purgeTeam($team, $admin));

        $this->assertNotNull($team->fresh());
    }

    /**
     * The case most likely to be got wrong, and the one D-24 names explicitly:
     * "a historical membership still counts as usage".
     *
     * Removing a member sets left_at and keeps the row. If purge ignored ended
     * memberships, a team could be destroyed while rows in team_memberships
     * still pointed at it - and the answer to "who was in this team in March"
     * would be a foreign key to nothing.
     *
     * Mutation: count only memberships with a null left_at. CAUGHT here and
     * nowhere else.
     */
    public function test_a_team_with_only_ended_membership_cannot_be_purged(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);

        $team = $this->make->team($this->make->department($this->make->businessUnit($organisation)));
        $memberships = app(MembershipService::class);

        $membership = $memberships->add($team, $this->make->user($organisation), $admin);
        $memberships->remove($membership, $admin);

        $this->assertSame(
            0,
            TeamMembership::query()->where('team_id', $team->id)->whereNull('left_at')->count(),
            'The membership was not actually ended, so this case would prove nothing.'
        );

        $this->expectViolation('in_use', fn () => $this->structure->purgeTeam($team, $admin));

        $this->assertNotNull($team->fresh(), 'A team with membership history was destroyed.');
        $this->assertSame(1, TeamMembership::query()->where('team_id', $team->id)->count());
    }

    // -- Shared purge guards -----------------------------------------------

    /** Mutation: drop requireSameOrganisation() from applyPurge(). */
    public function test_an_object_in_another_organisation_cannot_be_purged(): void
    {
        $ours = $this->make->organisation('Ours');
        $theirs = $this->make->organisation('Theirs');

        $foreign = $this->make->businessUnit($theirs, 'Foreign');

        $this->expectViolation('organisation_mismatch', fn () => $this->structure->purgeBusinessUnit(
            $foreign,
            $this->make->user($ours, administrator: true)
        ));

        $this->assertNotNull($foreign->fresh());
    }

    /** D-24 grants purge to the System Administrator. Mutation: drop the gate. */
    public function test_a_non_administrator_cannot_purge(): void
    {
        $organisation = $this->make->organisation();
        $unit = $this->make->businessUnit($organisation, 'Untouched');

        $this->actingAsUser($this->make->user($organisation))
            ->delete("/console/organisation/business-units/{$unit->id}")
            ->assertRedirect(route('auth.access-denied'));

        $this->assertNotNull($unit->fresh(), 'A non-administrator destroyed a business unit.');
    }

    /** The happy path over HTTP, so the route, controller and service are proven joined up. */
    public function test_an_administrator_purges_over_http(): void
    {
        $organisation = $this->make->organisation();
        $unit = $this->make->businessUnit($organisation, 'Entered by mistake');

        $this->actingAsUser($this->make->user($organisation, administrator: true))
            ->delete("/console/organisation/business-units/{$unit->id}")
            ->assertRedirect(route('organisation.business-units'));

        $this->assertNull($unit->fresh());
    }

    /** A refused purge over HTTP explains itself in business language. */
    public function test_a_refused_purge_explains_itself_without_database_terminology(): void
    {
        $organisation = $this->make->organisation();
        $unit = $this->make->businessUnit($organisation);
        $this->make->department($unit, 'Engineering');
        $this->make->department($unit, 'Finance');

        try {
            $this->structure->purgeBusinessUnit($unit, $this->make->user($organisation, administrator: true));
            $this->fail('A business unit with two departments was purged.');
        } catch (StructureViolation $violation) {
            $this->assertSame(
                'This business unit cannot be permanently deleted because it has 2 departments. '
                .'Deactivate it instead.',
                $violation->getMessage()
            );

            foreach (['business_unit', 'foreign key', 'constraint', 'business_unit_id', 'row', 'table'] as $leak) {
                $this->assertStringNotContainsStringIgnoringCase(
                    $leak,
                    $violation->getMessage(),
                    "The refusal exposes [{$leak}]. D-24 §5 requires business language."
                );
            }
        }
    }

    /** Mutation: drop the record() call from applyPurge(). */
    public function test_a_successful_purge_emits_a_security_event(): void
    {
        $organisation = $this->make->organisation();
        $team = $this->make->team($this->make->department($this->make->businessUnit($organisation)));

        $recorded = [];
        Log::listen(function ($message) use (&$recorded): void {
            $recorded[] = ['event' => $message->message, 'context' => $message->context];
        });

        $this->structure->purgeTeam($team, $this->make->user($organisation, administrator: true));

        $purge = collect($recorded)->firstWhere('event', SecurityEventLogger::TEAM_PURGED);

        $this->assertNotNull($purge, 'A purge destroyed a record and left no trace.');
        $this->assertSame($team->id, $purge['context']['entity_id']);
        $this->assertSame('teams', $purge['context']['entity_type']);
        $this->assertSame('purged', $purge['context']['result']);
        $this->assertArrayHasKey('at', $purge['context']);

        // The name is business content and is deliberately absent - the record
        // is gone, and the log is not where it gets kept instead.
        $this->assertNotContains($team->name, $purge['context']);
    }

    /** A refusal writes nothing at all: not a status change, not a partial delete. */
    public function test_a_refused_purge_leaves_every_record_unchanged(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);

        $unit = $this->make->businessUnit($organisation, 'Delivery');
        $department = $this->make->department($unit, 'Engineering');
        $team = $this->make->team($department, 'Platform');

        $before = [
            'unit' => $unit->only(['name', 'status']),
            'department' => $department->only(['name', 'status', 'business_unit_id']),
            'team' => $team->only(['name', 'status', 'department_id']),
        ];

        $this->expectViolation('in_use', fn () => $this->structure->purgeBusinessUnit($unit, $admin));
        $this->expectViolation('in_use', fn () => $this->structure->purgeDepartment($department, $admin));

        $this->assertSame($before['unit'], $unit->fresh()->only(['name', 'status']));
        $this->assertSame($before['department'], $department->fresh()->only(['name', 'status', 'business_unit_id']));
        $this->assertSame($before['team'], $team->fresh()->only(['name', 'status', 'department_id']));
    }

    // -- What may never be purged ------------------------------------------

    /**
     * D-24 excludes the organisation, membership history and management history.
     *
     * Asserted as the absence of any method that could destroy them, not only as
     * the absence of a route: a route is one line away, and a service method
     * that already destroys the record is what makes adding that line dangerous.
     */
    public function test_no_service_can_destroy_the_organisation_or_the_retained_history(): void
    {
        $forbidden = [
            OrganisationService::class,
            MembershipService::class,
            ManagementService::class,
        ];

        foreach ($forbidden as $class) {
            $source = file_get_contents((new \ReflectionClass($class))->getFileName());

            foreach (['->delete()', '->forceDelete()', '::destroy(', '->truncate('] as $destructive) {
                $this->assertStringNotContainsString(
                    $destructive,
                    $source,
                    "[{$class}] contains [{$destructive}]. The organisation, team memberships and "
                    .'management relationships are never destroyed: membership ends with left_at and '
                    .'a management link ends with effective_to, and both keep their row.'
                );
            }
        }
    }

    /** The same claim from the outside: no route can be dispatched to destroy them. */
    public function test_no_route_can_destroy_the_organisation_or_the_retained_history(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);
        $session = $this->actingAsUser($admin);

        $team = $this->make->team($this->make->department($this->make->businessUnit($organisation)));
        $membership = app(MembershipService::class)->add($team, $this->make->user($organisation), $admin);

        $subject = $this->make->user($organisation);
        app(ManagementService::class)->setManager($subject, $admin, $admin);

        $targets = [
            '/console/organisation',
            "/console/organisation/teams/{$team->id}/members/{$membership->id}",
            "/console/organisation/hierarchy/{$subject->id}",
        ];

        foreach ($targets as $uri) {
            $response = $session->delete($uri);

            $this->assertContains(
                $response->getStatusCode(),
                [404, 405],
                "DELETE [{$uri}] was dispatched. D-24 permits permanent deletion of four master types only."
            );
        }

        $this->assertNotNull($organisation->fresh());
        $this->assertNotNull($membership->fresh());
        $this->assertSame(1, ManagementRelationship::query()->where('user_id', $subject->id)->count());
    }

    // -- The reference walk itself -----------------------------------------

    /**
     * The guard reads the schema, so it cannot be wrong about today - but it
     * can be wrong about the WORDS. This is the case that keeps the fallback
     * phrase from becoming somewhere to leave things.
     *
     * Mutation: add a table with a foreign key to teams and no label. The purge
     * is still refused, correctly; this fails until the refusal can say why in
     * business language.
     */
    public function test_every_referencing_table_has_business_language_for_its_refusal(): void
    {
        $labelled = array_keys(PurgeDependencies::labels());
        $seen = [];

        foreach (['legal_entities', 'business_units', 'departments', 'teams'] as $table) {
            foreach (PurgeDependencies::referencesTo($table) as [$referencing]) {
                $seen[] = $referencing;

                $this->assertContains(
                    $referencing,
                    $labelled,
                    "[{$referencing}] can block a purge but has no business-language phrase, so the "
                    .'refusal would fall back to a generic sentence that tells the reader nothing.'
                );
            }
        }

        $this->assertNotEmpty($seen, 'No references were found, so this test proves nothing.');
    }

    /**
     * The walk finds the references D-24 names, and finds them from the schema.
     *
     * `organisations.primary_legal_entity_id` is in this list because D-25 added
     * it, and that is the point worth noticing: PurgeDependencies was not
     * changed to know about the primary legal entity. The migration added a
     * foreign key, the walk found it, and this case started failing until it was
     * updated - which is the guard behaving exactly as it was written to.
     */
    public function test_the_reference_walk_finds_every_dependency_d24_names(): void
    {
        $this->assertSame(
            [
                ['business_unit_legal_entity', 'legal_entity_id'],
                ['organisations', 'primary_legal_entity_id'],
            ],
            PurgeDependencies::referencesTo('legal_entities')
        );

        $this->assertSame(
            [['business_unit_legal_entity', 'business_unit_id'], ['departments', 'business_unit_id']],
            PurgeDependencies::referencesTo('business_units')
        );

        $this->assertSame([['teams', 'department_id']], PurgeDependencies::referencesTo('departments'));
        $this->assertSame([['team_memberships', 'team_id']], PurgeDependencies::referencesTo('teams'));
    }

    /**
     * The database refuses too.
     *
     * Every foreign key into these tables is RESTRICT, so if the service guard
     * were ever wrong the delete would still fail rather than orphan a row. The
     * guard exists to turn that into a sentence a person can read; this asserts
     * that the backstop under it is real.
     *
     * Mutation: change a foreign key to ON DELETE CASCADE.
     */
    public function test_no_foreign_key_into_a_purgeable_table_cascades(): void
    {
        foreach (['departments', 'teams', 'team_memberships', 'business_unit_legal_entity'] as $table) {
            foreach (Schema::getForeignKeys($table) as $key) {
                $this->assertNotSame(
                    'cascade',
                    strtolower((string) ($key['on_delete'] ?? '')),
                    "[{$table}] cascades on delete. A purge would then quietly destroy the very "
                    .'records the guard refuses to destroy.'
                );
            }
        }
    }

    /**
     * The second check is not decoration.
     *
     * D-24 §4 requires the dependency state to be re-read inside the write
     * transaction, because a dependency can appear between the confirmation and
     * the delete. Asserted structurally: applyPurge() must call the check twice,
     * once outside the transaction and once inside it.
     *
     * Mutation: delete the second call. The behaviour is identical in every
     * single-threaded test, which is exactly why this is asserted on the shape.
     */
    public function test_the_dependency_state_is_rechecked_inside_the_write_transaction(): void
    {
        $source = file_get_contents(
            (new \ReflectionClass(StructureService::class))->getFileName()
        );

        $body = substr($source, strpos($source, 'private function applyPurge'));
        $body = substr($body, 0, strpos($body, 'private function refuseIfInUse'));

        $this->assertSame(
            2,
            substr_count($body, '$this->refuseIfInUse('),
            'applyPurge() no longer checks dependencies twice. The check inside the transaction is '
            .'the one that catches a dependency created after the confirmation was shown.'
        );

        $inside = substr($body, strpos($body, 'DB::transaction'));

        $this->assertStringContainsString(
            'locking: true',
            $inside,
            'The check inside the transaction is not a locking read. Under REPEATABLE READ it would '
            .'read the transaction snapshot and miss the row it exists to catch.'
        );
    }

    /** The purge routes exist and are DELETE, from the outside. */
    public function test_the_four_purge_routes_are_registered(): void
    {
        $named = [
            'organisation.legal-entities.purge',
            'organisation.business-units.purge',
            'organisation.departments.purge',
            'organisation.teams.purge',
        ];

        foreach ($named as $name) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertNotNull($route, "Route [{$name}] is missing, so that type has no purge.");
            $this->assertContains('DELETE', $route->methods());
        }
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
