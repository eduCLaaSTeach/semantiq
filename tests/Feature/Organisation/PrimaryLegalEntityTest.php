<?php

declare(strict_types=1);

namespace Tests\Feature\Organisation;

use App\Modules\Organisation\Models\LegalEntity;
use App\Modules\Organisation\Models\Organisation;
use App\Modules\Organisation\Models\StructureStatus;
use App\Modules\Organisation\Services\OrganisationService;
use App\Modules\Organisation\Services\StructureService;
use App\Modules\Organisation\Support\StructureViolation;
use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;
use App\Modules\Platform\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Support\OrganisationFactory;
use Tests\TestCase;

/**
 * D-25 — the organisation's primary legal entity.
 *
 * The PLAN listed it among the Organisation's data points and the DESIGN omitted
 * it without recording a decision. These cases close that gap and, just as
 * importantly, pin down what it is NOT: the separation from D-14 is the whole
 * reason the field is safe to add, so it is asserted rather than assumed.
 */
final class PrimaryLegalEntityTest extends TestCase
{
    use RefreshDatabase;

    private OrganisationFactory $make;

    private OrganisationService $organisations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->make = new OrganisationFactory;
        $this->organisations = app(OrganisationService::class);
    }

    // -- The column ---------------------------------------------------------

    /**
     * Nullable, with a foreign key, and RESTRICT rather than a cascade.
     *
     * Mutation: make the column NOT NULL. Production's organisation has no
     * primary legal entity and nothing backfills one, so a non-nullable column
     * could not have been added at all without inventing business content.
     */
    public function test_the_primary_legal_entity_column_is_nullable_and_restricts(): void
    {
        $column = collect(Schema::getColumns('organisations'))
            ->firstWhere('name', 'primary_legal_entity_id');

        $this->assertNotNull($column, 'organisations.primary_legal_entity_id does not exist.');
        $this->assertTrue(
            $column['nullable'],
            'The column is NOT NULL. There is nothing to backfill it with, and an organisation '
            .'without a primary legal entity is a real state.'
        );

        $key = collect(Schema::getForeignKeys('organisations'))
            ->first(fn (array $k): bool => $k['columns'] === ['primary_legal_entity_id']);

        $this->assertNotNull($key, 'The column has no foreign key, so it can point at nothing.');
        $this->assertSame('legal_entities', $key['foreign_table']);
        $this->assertNotSame(
            'cascade',
            strtolower((string) ($key['on_delete'] ?? '')),
            'The key cascades. Destroying a legal entity would silently rewrite the Company Profile.'
        );
    }

    /** A new organisation starts with none, because nothing invents one. */
    public function test_a_new_organisation_has_no_primary_legal_entity(): void
    {
        $this->assertNull($this->make->organisation()->primary_legal_entity_id);
    }

    // -- Set, change, clear -------------------------------------------------

    /** The profile saves perfectly well with no primary legal entity chosen. */
    public function test_the_profile_saves_with_no_primary_legal_entity(): void
    {
        $organisation = $this->make->organisation('Before');
        $actor = $this->make->user($organisation, administrator: true);

        $this->organisations->updateProfile(
            $organisation,
            ['name' => 'After', 'primary_legal_entity_id' => null],
            $actor
        );

        $this->assertSame('After', $organisation->fresh()->name);
        $this->assertNull($organisation->fresh()->primary_legal_entity_id);
    }

    public function test_an_active_legal_entity_of_this_organisation_can_be_selected(): void
    {
        $organisation = $this->make->organisation();
        $entity = $this->make->legalEntity($organisation, 'Lithan Academy Pte Ltd');

        $this->organisations->updateProfile(
            $organisation,
            ['name' => $organisation->name, 'primary_legal_entity_id' => $entity->id],
            $this->make->user($organisation, administrator: true)
        );

        $this->assertSame($entity->id, $organisation->fresh()->primary_legal_entity_id);
        $this->assertSame('Lithan Academy Pte Ltd', $organisation->fresh()->primaryLegalEntity->name);
    }

    /** Change is Set applied again. There is no separate operation. */
    public function test_the_selection_can_be_changed(): void
    {
        $organisation = $this->make->organisation();
        $actor = $this->make->user($organisation, administrator: true);

        $first = $this->make->legalEntity($organisation, 'First Pte Ltd');
        $second = $this->make->legalEntity($organisation, 'Second Pte Ltd');

        $this->organisations->updateProfile(
            $organisation,
            ['name' => $organisation->name, 'primary_legal_entity_id' => $first->id],
            $actor
        );
        $this->organisations->updateProfile(
            $organisation->fresh(),
            ['name' => $organisation->name, 'primary_legal_entity_id' => $second->id],
            $actor
        );

        $this->assertSame($second->id, $organisation->fresh()->primary_legal_entity_id);
        $this->assertNotNull($first->fresh(), 'Changing the selection destroyed the previous entity.');
    }

    public function test_the_selection_can_be_cleared(): void
    {
        $organisation = $this->make->organisation();
        $actor = $this->make->user($organisation, administrator: true);
        $entity = $this->make->legalEntity($organisation);

        $this->organisations->updateProfile(
            $organisation,
            ['name' => $organisation->name, 'primary_legal_entity_id' => $entity->id],
            $actor
        );
        $this->organisations->updateProfile(
            $organisation->fresh(),
            ['name' => $organisation->name, 'primary_legal_entity_id' => null],
            $actor
        );

        $this->assertNull($organisation->fresh()->primary_legal_entity_id);
        $this->assertNotNull($entity->fresh(), 'Clearing the selection destroyed the legal entity.');
    }

    // -- What may not be selected -------------------------------------------

    /**
     * Mutation: drop the organisation comparison from requireSelectablePrimary().
     *
     * The dropdown never offers another organisation's entity, but the dropdown
     * is not the control - the id arrives in an HTTP request.
     */
    public function test_a_legal_entity_of_another_organisation_is_refused(): void
    {
        $ours = $this->make->organisation('Ours');
        $theirs = $this->make->organisation('Theirs');
        $foreign = $this->make->legalEntity($theirs, 'Foreign Pte Ltd');

        $this->expectViolation('organisation_mismatch', fn () => $this->organisations->updateProfile(
            $ours,
            ['name' => $ours->name, 'primary_legal_entity_id' => $foreign->id],
            $this->make->user($ours, administrator: true)
        ));

        $this->assertNull($ours->fresh()->primary_legal_entity_id);
    }

    /** Mutation: drop the isActive() check. */
    public function test_an_inactive_legal_entity_is_refused(): void
    {
        $organisation = $this->make->organisation();
        $entity = $this->make->legalEntity($organisation);
        $entity->forceFill(['status' => StructureStatus::Inactive])->save();

        $this->expectViolation('inactive_legal_entity', fn () => $this->organisations->updateProfile(
            $organisation,
            ['name' => $organisation->name, 'primary_legal_entity_id' => $entity->id],
            $this->make->user($organisation, administrator: true)
        ));

        $this->assertNull($organisation->fresh()->primary_legal_entity_id);
    }

    /** An id that is not a legal entity at all is refused rather than stored. */
    public function test_an_unknown_legal_entity_is_refused(): void
    {
        $organisation = $this->make->organisation();

        $this->expectViolation('organisation_mismatch', fn () => $this->organisations->updateProfile(
            $organisation,
            ['name' => $organisation->name, 'primary_legal_entity_id' => 999999],
            $this->make->user($organisation, administrator: true)
        ));
    }

    /** The same two guards, over HTTP, where the id actually comes from. */
    public function test_the_profile_endpoint_refuses_an_inactive_selection(): void
    {
        $organisation = $this->make->organisation();
        $entity = $this->make->legalEntity($organisation);
        $entity->forceFill(['status' => StructureStatus::Inactive])->save();

        $this->actingAsUser($this->make->user($organisation, administrator: true))
            ->put('/console/organisation', [
                'name' => $organisation->name,
                'primary_legal_entity_id' => $entity->id,
            ]);

        $this->assertNull($organisation->fresh()->primary_legal_entity_id);
    }

    /** The happy path over HTTP, so route, controller and service are joined up. */
    public function test_an_administrator_selects_a_primary_legal_entity_over_http(): void
    {
        $organisation = $this->make->organisation();
        $entity = $this->make->legalEntity($organisation, 'Lithan Academy Pte Ltd');

        $this->actingAsUser($this->make->user($organisation, administrator: true))
            ->put('/console/organisation', [
                'name' => $organisation->name,
                'primary_legal_entity_id' => $entity->id,
            ])
            ->assertRedirect(route('organisation.profile'));

        $this->assertSame($entity->id, $organisation->fresh()->primary_legal_entity_id);
    }

    /**
     * A save that does not mention the field leaves it alone.
     *
     * Mutation: coerce an absent key to null in the controller. A partial save
     * then silently clears the organisation's corporate identity.
     */
    public function test_a_save_that_omits_the_field_does_not_clear_the_selection(): void
    {
        $organisation = $this->make->organisation();
        $entity = $this->make->legalEntity($organisation);
        $session = $this->actingAsUser($this->make->user($organisation, administrator: true));

        $session->put('/console/organisation', [
            'name' => $organisation->name,
            'primary_legal_entity_id' => $entity->id,
        ]);

        $session->put('/console/organisation', ['name' => 'Renamed only']);

        $this->assertSame('Renamed only', $organisation->fresh()->name);
        $this->assertSame(
            $entity->id,
            $organisation->fresh()->primary_legal_entity_id,
            'A save that never mentioned the primary legal entity cleared it.'
        );
    }

    // -- Lifecycle ----------------------------------------------------------

    /**
     * Mutation: delete the primary check from deactivateLegalEntity().
     *
     * The Company Profile would then point at a record the organisation has said
     * is no longer current — readable, and wrong.
     */
    public function test_the_primary_legal_entity_cannot_be_deactivated(): void
    {
        $organisation = $this->make->organisation();
        $actor = $this->make->user($organisation, administrator: true);
        $entity = $this->make->legalEntity($organisation);

        $this->organisations->updateProfile(
            $organisation,
            ['name' => $organisation->name, 'primary_legal_entity_id' => $entity->id],
            $actor
        );

        try {
            app(StructureService::class)->deactivateLegalEntity($entity, $actor);
            $this->fail('The primary legal entity was deactivated.');
        } catch (StructureViolation $violation) {
            $this->assertSame('primary_legal_entity', $violation->reason);
            $this->assertSame(
                "This legal entity is the organisation's primary legal entity. Select another "
                .'primary legal entity or clear the selection before deactivating it.',
                $violation->getMessage()
            );
        }

        $this->assertSame(StructureStatus::Active, $entity->fresh()->status);
        $this->assertSame(
            $entity->id,
            $organisation->fresh()->primary_legal_entity_id,
            'The selection was cleared to let the deactivation through. Nothing is cascaded.'
        );
    }

    /**
     * D-24 picks this up from the schema. Nothing in PurgeDependencies was
     * changed to know about it: the migration added a foreign key and the walk
     * found it.
     */
    public function test_the_primary_legal_entity_cannot_be_purged(): void
    {
        $organisation = $this->make->organisation();
        $actor = $this->make->user($organisation, administrator: true);
        $entity = $this->make->legalEntity($organisation);

        $this->organisations->updateProfile(
            $organisation,
            ['name' => $organisation->name, 'primary_legal_entity_id' => $entity->id],
            $actor
        );

        try {
            app(StructureService::class)->purgeLegalEntity($entity, $actor);
            $this->fail('The primary legal entity was permanently deleted.');
        } catch (StructureViolation $violation) {
            $this->assertSame('in_use', $violation->reason);
            $this->assertStringContainsString('primary legal entity', $violation->getMessage());

            /*
             * NOT "deactivate it instead". Deactivating is refused too while the
             * entity is the primary, so that advice would send the reader in a
             * circle - found in the browser, not by review.
             */
            $this->assertStringNotContainsString('Deactivate it instead', $violation->getMessage());
            $this->assertStringContainsString('Company Profile', $violation->getMessage());
            $this->assertStringContainsString('clear the selection', $violation->getMessage());

            foreach (['organisations', 'foreign key', 'primary_legal_entity_id', 'column'] as $leak) {
                $this->assertStringNotContainsStringIgnoringCase(
                    $leak,
                    $violation->getMessage(),
                    "The refusal exposes [{$leak}]."
                );
            }
        }

        $this->assertNotNull($entity->fresh());
    }

    /** Clearing the selection releases the entity to its normal lifecycle. */
    public function test_clearing_the_selection_restores_the_normal_lifecycle(): void
    {
        $organisation = $this->make->organisation();
        $actor = $this->make->user($organisation, administrator: true);
        $entity = $this->make->legalEntity($organisation);
        $structure = app(StructureService::class);

        $this->organisations->updateProfile(
            $organisation,
            ['name' => $organisation->name, 'primary_legal_entity_id' => $entity->id],
            $actor
        );

        $this->organisations->updateProfile(
            $organisation->fresh(),
            ['name' => $organisation->name, 'primary_legal_entity_id' => null],
            $actor
        );

        $structure->deactivateLegalEntity($entity->fresh(), $actor);
        $this->assertSame(StructureStatus::Inactive, $entity->fresh()->status);

        $structure->reactivate($entity->fresh(), $actor);
        $structure->purgeLegalEntity($entity->fresh(), $actor);

        $this->assertNull(LegalEntity::query()->find($entity->id));
    }

    /** Changing to another entity releases the previous one, in the same way. */
    public function test_changing_the_selection_releases_the_previous_entity(): void
    {
        $organisation = $this->make->organisation();
        $actor = $this->make->user($organisation, administrator: true);

        $first = $this->make->legalEntity($organisation, 'First Pte Ltd');
        $second = $this->make->legalEntity($organisation, 'Second Pte Ltd');

        $this->organisations->updateProfile(
            $organisation,
            ['name' => $organisation->name, 'primary_legal_entity_id' => $first->id],
            $actor
        );
        $this->organisations->updateProfile(
            $organisation->fresh(),
            ['name' => $organisation->name, 'primary_legal_entity_id' => $second->id],
            $actor
        );

        app(StructureService::class)->deactivateLegalEntity($first->fresh(), $actor);

        $this->assertSame(StructureStatus::Inactive, $first->fresh()->status);
    }

    // -- D-14 is unchanged --------------------------------------------------

    /**
     * The claim D-25 rests on, asserted rather than trusted.
     *
     * The junction still carries association and nothing else. If a "primary"
     * column ever appeared there, this fails - and that column is the one a
     * later unit would read as employment or entitlement.
     */
    public function test_the_d14_junction_still_carries_no_primary_flag(): void
    {
        $columns = collect(Schema::getColumns('business_unit_legal_entity'))
            ->pluck('name')
            ->sort()
            ->values()
            ->all();

        $this->assertSame(
            ['business_unit_id', 'created_at', 'id', 'legal_entity_id', 'organisation_id', 'updated_at'],
            $columns,
            'The D-14 junction gained a column. D-25 is an ORGANISATION-level attribute and changes '
            .'nothing about the business unit ↔ legal entity association.'
        );
    }

    /**
     * The primary legal entity is NOT the parent of the business units.
     *
     * D-25 says this in as many words, and it is the inference somebody would
     * make. A business unit may be associated with entities that are not the
     * primary, the primary may be associated with no business unit at all, and
     * neither state is irregular.
     */
    public function test_the_primary_legal_entity_is_not_a_parent_of_business_units(): void
    {
        $organisation = $this->make->organisation();
        $actor = $this->make->user($organisation, administrator: true);
        $structure = app(StructureService::class);

        $primary = $this->make->legalEntity($organisation, 'Corporate Pte Ltd');
        $operating = $this->make->legalEntity($organisation, 'Operating Pte Ltd');
        $unit = $this->make->businessUnit($organisation);

        $this->organisations->updateProfile(
            $organisation,
            ['name' => $organisation->name, 'primary_legal_entity_id' => $primary->id],
            $actor
        );

        // The business unit is associated with the OTHER entity, and that is a
        // perfectly ordinary state that nothing refuses.
        $structure->associate($unit, $operating, $actor);

        $this->assertSame(1, $unit->legalEntities()->count());
        $this->assertSame(
            0,
            $primary->businessUnits()->count(),
            'The primary legal entity acquired a business unit association it was never given.'
        );

        // And the many-to-many still works in both directions afterwards.
        $structure->associate($unit, $primary, $actor);
        $this->assertSame(2, $unit->legalEntities()->count());
    }

    /** Selecting a primary legal entity grants nothing. */
    public function test_selecting_a_primary_legal_entity_grants_no_access(): void
    {
        $organisation = $this->make->organisation();
        $entity = $this->make->legalEntity($organisation);
        $ordinary = $this->make->user($organisation);

        $this->organisations->updateProfile(
            $organisation,
            ['name' => $organisation->name, 'primary_legal_entity_id' => $entity->id],
            $this->make->user($organisation, administrator: true)
        );

        // A non-administrator is refused exactly as before. The Company Profile
        // now naming a legal entity changes nothing about who may read it.
        $this->actingAsUser($ordinary)
            ->get('/console/organisation')
            ->assertRedirect(route('auth.access-denied'));

        $this->assertFalse($ordinary->fresh()->isSystemAdministrator());
    }

    /** Nothing writes this column except the Company Profile screen. */
    public function test_only_the_organisation_service_writes_the_selection(): void
    {
        $writers = [];

        foreach ($this->phpFilesUnder(app_path('Modules')) as $file) {
            $source = file_get_contents($file);

            if (str_contains($source, 'primary_legal_entity_id')
                && ! str_contains($file, 'OrganisationService.php')
                && ! str_contains($file, 'ProfileController.php')
                && ! str_contains($file, 'Organisation.php')
                && ! str_contains($file, 'StructureService.php')
                && ! str_contains($file, 'PurgeDependencies.php')) {
                $writers[] = basename($file);
            }
        }

        $this->assertSame([], $writers, 'The primary legal entity is referenced outside its owners.');
    }

    /**
     * @return list<string>
     */
    private function phpFilesUnder(string $directory): array
    {
        $files = [];

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
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
