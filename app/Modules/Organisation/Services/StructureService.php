<?php

declare(strict_types=1);

namespace App\Modules\Organisation\Services;

use App\Modules\Organisation\Models\BusinessUnit;
use App\Modules\Organisation\Models\Department;
use App\Modules\Organisation\Models\LegalEntity;
use App\Modules\Organisation\Models\Organisation;
use App\Modules\Organisation\Models\StructureStatus;
use App\Modules\Organisation\Models\Team;
use App\Modules\Organisation\Support\Jurisdictions;
use App\Modules\Organisation\Support\PurgeDependencies;
use App\Modules\Organisation\Support\StructureViolation;
use App\Modules\Platform\Models\User;
use App\Modules\Platform\Security\SecurityEventLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The structural tree and the legal axis: create, update, move, deactivate.
 *
 * Every rule here is a service-layer invariant with a negative test. None is a
 * UI affordance - the screen may also prevent it, but the screen is not the
 * control.
 *
 * D-24 replaced the blanket "no hard delete anywhere" rule with a guarded
 * permanent delete, on four master types only and only when the record is
 * completely unused. The guard is here, not on the screen: the confirmation
 * dialog is a courtesy to the person, and the refusal is the control.
 */
final class StructureService
{
    public function __construct(private readonly SecurityEventLogger $events) {}

    // -- Legal entities ----------------------------------------------------

    /** @param array<string, string|null> $attributes */
    public function createLegalEntity(Organisation $organisation, array $attributes, User $actor): LegalEntity
    {
        $this->requireActiveOrganisation($organisation);

        $entity = LegalEntity::query()->create($attributes + [
            'organisation_id' => $organisation->id,
            'status' => StructureStatus::Active,
        ]);

        $this->record(SecurityEventLogger::LEGAL_ENTITY_CREATED, $entity, $actor, 'created');

        return $entity;
    }

    // -- Business units ----------------------------------------------------

    /** @param array<string, string|null> $attributes */
    public function createBusinessUnit(Organisation $organisation, array $attributes, User $actor): BusinessUnit
    {
        $this->requireActiveOrganisation($organisation);

        $unit = BusinessUnit::query()->create($attributes + [
            'organisation_id' => $organisation->id,
            'status' => StructureStatus::Active,
        ]);

        $this->record(SecurityEventLogger::BUSINESS_UNIT_CREATED, $unit, $actor, 'created');

        return $unit;
    }

    // -- Departments -------------------------------------------------------

    /** @param array<string, string|null> $attributes */
    public function createDepartment(BusinessUnit $parent, array $attributes, User $actor): Department
    {
        $this->requireActiveParent($parent, 'business_unit');

        $department = Department::query()->create($attributes + [
            'organisation_id' => $parent->organisation_id,
            'business_unit_id' => $parent->id,
            'status' => StructureStatus::Active,
        ]);

        $this->record(SecurityEventLogger::DEPARTMENT_CREATED, $department, $actor, 'created');

        return $department;
    }

    // -- Teams -------------------------------------------------------------

    /**
     * Rule 3: a team's department must belong to the team's business unit.
     *
     * The department is the only parent passed in, so the business unit is read
     * from it rather than accepted separately - a second parameter would be a
     * second chance to disagree with the tree.
     *
     * @param  array<string, string|null>  $attributes
     */
    public function createTeam(Department $parent, array $attributes, User $actor): Team
    {
        $this->requireActiveParent($parent, 'department');

        $team = Team::query()->create($attributes + [
            'organisation_id' => $parent->organisation_id,
            'department_id' => $parent->id,
            'status' => StructureStatus::Active,
        ]);

        $this->record(SecurityEventLogger::TEAM_CREATED, $team, $actor, 'created');

        return $team;
    }

    // -- Moves -------------------------------------------------------------

    /**
     * A move is the change most likely to alter someone's future scope, which is
     * why it is recorded as scope-affecting rather than as an ordinary update.
     */
    public function moveDepartment(Department $department, BusinessUnit $target, User $actor): Department
    {
        $this->requireSameOrganisation($department->organisation_id, $target->organisation_id);
        $this->requireActiveParent($target, 'business_unit');

        return DB::transaction(function () use ($department, $target, $actor): Department {
            $department->business_unit_id = $target->id;
            $department->save();

            // Teams follow their department. Their organisation is unchanged -
            // a cross-organisation move is refused above - so this only keeps
            // the denormalised parent chain honest.
            $this->record(SecurityEventLogger::DEPARTMENT_MOVED, $department, $actor, 'moved');

            return $department;
        });
    }

    public function moveTeam(Team $team, Department $target, User $actor): Team
    {
        $this->requireSameOrganisation($team->organisation_id, $target->organisation_id);
        $this->requireActiveParent($target, 'department');

        $team->department_id = $target->id;
        $team->save();

        $this->record(SecurityEventLogger::TEAM_MOVED, $team, $actor, 'moved');

        return $team;
    }

    // -- Lifecycle ---------------------------------------------------------

    /**
     * Refusing the cascade is the deliberate choice.
     *
     * The source document warns that restructuring must not silently broaden
     * access, and a silent cascade is precisely how structure changes underneath
     * someone's scope. The refusal names the blocking children so the
     * administrator can act, rather than leaving them to guess.
     */
    public function deactivateBusinessUnit(BusinessUnit $unit, User $actor): BusinessUnit
    {
        $blocking = $unit->departments()
            ->where('status', StructureStatus::Active->value)
            ->pluck('name')
            ->all();

        if ($blocking !== []) {
            throw StructureViolation::blockedByChildren(
                'active_children',
                'This business unit still has active departments.',
                $blocking
            );
        }

        return $this->setStatus($unit, StructureStatus::Inactive, SecurityEventLogger::BUSINESS_UNIT_DEACTIVATED, $actor);
    }

    public function deactivateDepartment(Department $department, User $actor): Department
    {
        $blocking = $department->teams()
            ->where('status', StructureStatus::Active->value)
            ->pluck('name')
            ->all();

        if ($blocking !== []) {
            throw StructureViolation::blockedByChildren(
                'active_children',
                'This department still has active teams.',
                $blocking
            );
        }

        return $this->setStatus($department, StructureStatus::Inactive, SecurityEventLogger::DEPARTMENT_DEACTIVATED, $actor);
    }

    /**
     * A team has no structural children, so the block is its active membership.
     */
    public function deactivateTeam(Team $team, User $actor): Team
    {
        $members = $team->memberships()->whereNull('left_at')->count();

        if ($members > 0) {
            throw StructureViolation::because(
                'active_memberships',
                'This team still has active members. Remove them before deactivating it.'
            );
        }

        return $this->setStatus($team, StructureStatus::Inactive, SecurityEventLogger::TEAM_DEACTIVATED, $actor);
    }

    /**
     * D-25: the organisation's primary legal entity cannot be retired while it
     * still holds that role.
     *
     * The refusal is not a technicality. The primary legal entity is who the
     * company is on paper, and an inactive record in that position would leave
     * the Company Profile pointing at something the organisation has said is no
     * longer current - readable, and wrong.
     *
     * Clearing or changing the selection first is the way through, and the
     * message says so. Nothing is cascaded: the selection is never cleared for
     * the caller to make the deactivation succeed.
     */
    public function deactivateLegalEntity(LegalEntity $entity, User $actor): LegalEntity
    {
        $isPrimary = Organisation::query()
            ->where('primary_legal_entity_id', $entity->id)
            ->exists();

        if ($isPrimary) {
            throw StructureViolation::because(
                'primary_legal_entity',
                "This legal entity is the organisation's primary legal entity. Select another "
                .'primary legal entity or clear the selection before deactivating it.'
            );
        }

        return $this->setStatus($entity, StructureStatus::Inactive, SecurityEventLogger::LEGAL_ENTITY_DEACTIVATED, $actor);
    }

    /**
     * Reactivation is permitted only if the parent is active. Reactivating a
     * child under an inactive parent would produce a live node hanging off a
     * dead one - visible in a list, unreachable through the tree.
     */
    public function reactivate(Model $node, User $actor): Model
    {
        $parent = match (true) {
            $node instanceof Department => $node->businessUnit,
            $node instanceof Team => $node->department,
            default => null,
        };

        if ($parent !== null && ! $parent->isActive()) {
            throw StructureViolation::because(
                'inactive_parent',
                'The parent is inactive. Reactivate it first.'
            );
        }

        $event = match (true) {
            $node instanceof BusinessUnit => SecurityEventLogger::BUSINESS_UNIT_UPDATED,
            $node instanceof Department => SecurityEventLogger::DEPARTMENT_UPDATED,
            $node instanceof Team => SecurityEventLogger::TEAM_UPDATED,
            default => SecurityEventLogger::LEGAL_ENTITY_UPDATED,
        };

        return $this->setStatus($node, StructureStatus::Active, $event, $actor);
    }

    // -- Update ------------------------------------------------------------

    /*
     * UPDATE was in the approved scope from the start.
     *
     * PHASE-1-PLAN §"Definition of Done" 1: structures "can be created, UPDATED
     * and deactivated", and the event catalogue names legal_entity.updated,
     * business_unit.updated, department.updated and team.updated. The events
     * were declared and the operations were not, so those constants were only
     * ever emitted by reactivate - a status change - and a typed name could
     * never be corrected. A Department called "Singapore Retai Sales" had no
     * route back to "Singapore Retail Sales" except the database.
     *
     * EDIT IS NOT MOVE. These methods change a record's OWN attributes and
     * never its parent. Re-parenting stays in moveDepartment / moveTeam, which
     * emit *.moved and are flagged scope-affecting for the audit catalogue. If
     * a spelling correction emitted a move, the catalogue would record a
     * structural change that never happened.
     */

    /** @param array<string, string|null> $attributes */
    public function updateLegalEntity(LegalEntity $entity, array $attributes, User $actor): LegalEntity
    {
        return $this->applyUpdate(
            $entity,
            $this->only($attributes, ['name', 'registration_number', 'jurisdiction', 'registered_address']),
            SecurityEventLogger::LEGAL_ENTITY_UPDATED,
            $actor
        );
    }

    /** @param array<string, string|null> $attributes */
    public function updateBusinessUnit(BusinessUnit $unit, array $attributes, User $actor): BusinessUnit
    {
        return $this->applyUpdate(
            $unit,
            $this->only($attributes, ['name', 'code']),
            SecurityEventLogger::BUSINESS_UNIT_UPDATED,
            $actor
        );
    }

    /**
     * Name and code only.
     *
     * business_unit_id is deliberately NOT accepted here. Moving a department
     * is moveDepartment(), which records a scope-affecting event.
     *
     * @param  array<string, string|null>  $attributes
     */
    public function updateDepartment(Department $department, array $attributes, User $actor): Department
    {
        return $this->applyUpdate(
            $department,
            $this->only($attributes, ['name', 'code']),
            SecurityEventLogger::DEPARTMENT_UPDATED,
            $actor
        );
    }

    /**
     * Name and code only. department_id belongs to moveTeam(), for the same
     * reason business_unit_id belongs to moveDepartment().
     *
     * @param  array<string, string|null>  $attributes
     */
    public function updateTeam(Team $team, array $attributes, User $actor): Team
    {
        return $this->applyUpdate(
            $team,
            $this->only($attributes, ['name', 'code']),
            SecurityEventLogger::TEAM_UPDATED,
            $actor
        );
    }

    /**
     * The one update path.
     *
     * A rejected update leaves the record untouched: the guards run before
     * anything is written, and the write is a single save inside a transaction.
     *
     * @template T of Model
     *
     * @param  T  $node
     * @param  array<string, string|null>  $attributes
     * @return T
     */
    private function applyUpdate(Model $node, array $attributes, string $event, User $actor): Model
    {
        /*
         * The record must be in the ACTOR'S organisation.
         *
         * Route-model binding resolves a record by id alone, so without this an
         * administrator could edit another organisation's structure by URL. The
         * screen never offers it, but the screen is not the control. Release 1
         * is single-tenant, which makes this unreachable today and exactly the
         * kind of guard that is missing when it stops being unreachable.
         *
         * D-16: the comparison is organisation_id on both sides. The Entra
         * tenant is a directory boundary and is never substituted for it.
         */
        $this->requireSameOrganisation(
            $node->getAttribute('organisation_id'),
            $actor->organisation_id
        );

        if (array_key_exists('name', $attributes) && trim((string) $attributes['name']) === '') {
            throw StructureViolation::because('invalid_name', 'A name is required.');
        }

        if (array_key_exists('jurisdiction', $attributes)
            && ! Jurisdictions::permits($attributes['jurisdiction'])) {
            throw StructureViolation::because(
                'unknown_jurisdiction',
                'That jurisdiction is not on the approved list.'
            );
        }

        return DB::transaction(function () use ($node, $attributes, $event, $actor): Model {
            foreach ($attributes as $key => $value) {
                $node->setAttribute($key, $value);
            }

            $node->save();

            $this->record($event, $node, $actor, 'updated');

            return $node;
        });
    }

    /**
     * The attributes this operation is allowed to touch, and no others.
     *
     * Whitelisted rather than blacklisted: a field added to the model later is
     * not silently updatable, and a parent key posted by a caller is ignored
     * instead of quietly re-parenting the record.
     *
     * @param  array<string, string|null>  $attributes
     * @param  list<string>  $allowed
     * @return array<string, string|null>
     */
    private function only(array $attributes, array $allowed): array
    {
        return array_intersect_key($attributes, array_flip($allowed));
    }

    // -- D-24 guarded permanent delete -------------------------------------

    /*
     * Purge is not delete-with-a-confirmation, and it is not a better
     * Deactivate. It exists for one case the Product Owner named: a master
     * record entered by mistake, which nothing uses and which would otherwise
     * be permanent garbage. Everything else keeps its record.
     *
     * There is no purge for the Organisation, for team memberships or for
     * management relationships. The first is the tenancy root; the other two are
     * the history whose retention the rest of the unit is built around, and a
     * way to destroy them would make `left_at` and `effective_to` decorative.
     */

    public function purgeLegalEntity(LegalEntity $entity, User $actor): void
    {
        $this->applyPurge($entity, 'legal entity', SecurityEventLogger::LEGAL_ENTITY_PURGED, $actor);
    }

    public function purgeBusinessUnit(BusinessUnit $unit, User $actor): void
    {
        $this->applyPurge($unit, 'business unit', SecurityEventLogger::BUSINESS_UNIT_PURGED, $actor);
    }

    public function purgeDepartment(Department $department, User $actor): void
    {
        $this->applyPurge($department, 'department', SecurityEventLogger::DEPARTMENT_PURGED, $actor);
    }

    public function purgeTeam(Team $team, User $actor): void
    {
        $this->applyPurge($team, 'team', SecurityEventLogger::TEAM_PURGED, $actor);
    }

    /**
     * The one purge path.
     *
     * The dependency check runs TWICE, and the second run is the one that
     * matters. D-24 §4 requires it: between the moment the screen offered the
     * confirmation and the moment the row is destroyed, somebody else can add
     * the first department to that business unit. The first check answers the
     * screen; only the check inside the write transaction answers the delete.
     *
     * Nothing is cascaded, ever. If a dependency exists the answer is a refusal
     * naming it, never a wider delete that makes the refusal go away - and the
     * foreign keys are RESTRICT beneath this, so the database refuses too if
     * this guard is ever wrong.
     */
    private function applyPurge(Model $node, string $noun, string $event, User $actor): void
    {
        $this->requireSameOrganisation(
            $node->getAttribute('organisation_id'),
            $actor->organisation_id
        );

        $this->refuseIfInUse($node, $noun);

        DB::transaction(function () use ($node, $noun, $event, $actor): void {
            $this->refuseIfInUse($node, $noun, locking: true);

            $node->delete();

            // Recorded from the attributes still in memory. The row is gone, so
            // this event is the only remaining trace that it existed - which is
            // why a purge is logged even though nothing else in P1-01 destroys
            // anything worth tracing.
            $this->record($event, $node, $actor, 'purged');
        });
    }

    private function refuseIfInUse(Model $node, string $noun, bool $locking = false): void
    {
        $blocking = PurgeDependencies::blocking($node, $locking);

        if ($blocking === []) {
            return;
        }

        $phrases = array_column($blocking, 'phrase');

        /*
         * "Deactivate it instead" is the usual way forward, but not always.
         * A legal entity that is the organisation's primary refuses the
         * deactivation as well, so that advice would send the reader in a
         * circle - observed in the browser, and the reason a blocker may carry
         * its own closing sentence.
         */
        $advice = collect($blocking)->pluck('advice')->filter()->first()
            ?? 'Deactivate it instead.';

        throw StructureViolation::blockedByChildren(
            'in_use',
            sprintf(
                'This %s cannot be permanently deleted because %s. %s',
                $noun,
                $this->readAsList($phrases),
                $advice
            ),
            $phrases
        );
    }

    /**
     * @param  list<string>  $clauses
     */
    private function readAsList(array $clauses): string
    {
        if (count($clauses) === 1) {
            return $clauses[0];
        }

        $last = array_pop($clauses);

        return implode(', ', $clauses).' and '.$last;
    }

    // -- D-14 associations -------------------------------------------------

    /**
     * Rule 5: both sides must be in the same organisation.
     *
     * The association carries nothing else. An attribute here would be the first
     * thing a later unit reads as employment or entitlement, and D-14 states the
     * association grants nothing.
     */
    public function associate(BusinessUnit $unit, LegalEntity $entity, User $actor): void
    {
        $this->requireSameOrganisation($unit->organisation_id, $entity->organisation_id);

        $unit->legalEntities()->syncWithoutDetaching([$entity->id => [
            'organisation_id' => $unit->organisation_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]]);

        $this->events->record(SecurityEventLogger::BUSINESS_UNIT_LEGAL_ENTITY_ASSOCIATED, [
            'user_id' => $actor->id,
            'organisation_id' => $unit->organisation_id,
            'entity_type' => 'business_unit',
            'entity_id' => $unit->id,
            'related_id' => $entity->id,
            'result' => 'associated',
        ]);
    }

    public function dissociate(BusinessUnit $unit, LegalEntity $entity, User $actor): void
    {
        $unit->legalEntities()->detach($entity->id);

        $this->events->record(SecurityEventLogger::BUSINESS_UNIT_LEGAL_ENTITY_DISSOCIATED, [
            'user_id' => $actor->id,
            'organisation_id' => $unit->organisation_id,
            'entity_type' => 'business_unit',
            'entity_id' => $unit->id,
            'related_id' => $entity->id,
            'result' => 'dissociated',
        ]);
    }

    // -- Shared guards -----------------------------------------------------

    private function requireActiveOrganisation(Organisation $organisation): void
    {
        if (! $organisation->isActive()) {
            throw StructureViolation::because('inactive_parent', 'The organisation is inactive.');
        }
    }

    private function requireActiveParent(BusinessUnit|Department $parent, string $type): void
    {
        if (! $parent->isActive()) {
            throw StructureViolation::because(
                'inactive_parent',
                "The {$type} is inactive, so nothing may be created under it."
            );
        }
    }

    /**
     * Rules 1, 5 and every move: nothing crosses an organisation boundary.
     */
    private function requireSameOrganisation(?int $left, ?int $right): void
    {
        if ($left === null || $right === null || $left !== $right) {
            throw StructureViolation::because(
                'organisation_mismatch',
                'Both records must belong to the same organisation.'
            );
        }
    }

    private function setStatus(Model $node, StructureStatus $status, string $event, User $actor): Model
    {
        $node->forceFill(['status' => $status])->save();

        $this->record($event, $node, $actor, $status->value);

        return $node;
    }

    private function record(string $event, Model $node, User $actor, string $result): void
    {
        $this->events->record($event, [
            'user_id' => $actor->id,
            'organisation_id' => $node->getAttribute('organisation_id'),
            'entity_type' => $node->getTable(),
            'entity_id' => $node->getKey(),
            'result' => $result,
        ]);
    }
}
