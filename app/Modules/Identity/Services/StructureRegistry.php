<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Models\User;
use App\Modules\Audit\Support\AuditLogger;
use App\Modules\Identity\Models\BusinessUnit;
use App\Modules\Identity\Models\Team;
use App\Modules\Identity\Support\OrganisationContext;
use App\Modules\Platform\Enums\LifecycleStatus;
use InvalidArgumentException;
use RuntimeException;

/**
 * Business units and teams. Features ADM-003 and ADM-004.
 *
 * Both are SCOPES and never permissions. Nothing this class writes grants
 * access to anything; a business unit narrows what an entitlement covers, and
 * an entitlement is granted separately by `UserRegistry`.
 *
 * The rule that needs code rather than a constraint is VAL-BU-LOOP-001: no
 * relational constraint can express "not an ancestor of itself", so the check
 * lives here and every write path goes through it.
 */
class StructureRegistry
{
    /**
     * How deep a hierarchy may go before this class calls it a mistake.
     *
     * Not a business rule - it is a guard against a cycle created by a direct
     * database edit turning every traversal into a hang. Thirty-two levels is
     * far beyond any real organisation chart.
     */
    private const MAX_DEPTH = 32;

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly OrganisationContext $organisations,
    ) {}

    /* ---- Business units --------------------------------------------- */

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createBusinessUnit(array $attributes, User $actor): BusinessUnit
    {
        $organisationId = $this->organisations->require()->id;
        $code = $this->normaliseCode($attributes['code']);

        /* VAL-BU-CODE-001: unique within the organisation. */
        if (BusinessUnit::query()->where('code', $code)->exists()) {
            throw new InvalidArgumentException('A business unit with the code "'.$code.'" already exists.');
        }

        $parent = $this->resolveParent($attributes['parent_id'] ?? null);

        $unit = new BusinessUnit;
        $unit->forceFill([
            'organisation_id' => $organisationId,
            'code' => $code,
            'name' => $attributes['name'],
            'parent_id' => $parent?->getKey(),
            'manager_user_id' => $attributes['manager_user_id'] ?? null,
            'cost_centre' => $attributes['cost_centre'] ?? null,
            'country' => $attributes['country'] ?? null,
            'effective_from' => $attributes['effective_from'] ?? null,
            'effective_to' => $attributes['effective_to'] ?? null,
            'status' => LifecycleStatus::Active,
            'created_by_user_id' => $actor->getKey(),
            'updated_by_user_id' => $actor->getKey(),
        ])->save();

        $this->audit->record(
            action: 'business_unit.created',
            module: 'Identity',
            resourceType: 'business_unit',
            resourceId: $unit->getKey(),
            after: ['code' => $code, 'name' => $unit->name, 'parent_id' => $unit->parent_id],
        );

        return $unit;
    }

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws RuntimeException when the change would create a hierarchy loop.
     */
    public function updateBusinessUnit(BusinessUnit $unit, array $attributes, User $actor): BusinessUnit
    {
        $parent = $this->resolveParent($attributes['parent_id'] ?? null);

        /* VAL-BU-LOOP-001. Checked before anything is written, because a loop
         * that reaches the database makes every later traversal hang. */
        $this->assertNoLoop($unit, $parent);

        $status = $attributes['status'] ?? $unit->status;

        if (! LifecycleStatus::isWithin($status->value, LifecycleStatus::forStructure())) {
            throw new InvalidArgumentException('"'.$status->value.'" is not a state a business unit can hold.');
        }

        $before = [
            'name' => $unit->name,
            'parent_id' => $unit->parent_id,
            'status' => $unit->status->value,
        ];

        $unit->forceFill([
            'name' => $attributes['name'],
            'parent_id' => $parent?->getKey(),
            'manager_user_id' => $attributes['manager_user_id'] ?? null,
            'cost_centre' => $attributes['cost_centre'] ?? null,
            'country' => $attributes['country'] ?? null,
            'effective_from' => $attributes['effective_from'] ?? null,
            'effective_to' => $attributes['effective_to'] ?? null,
            'status' => $status,
            'updated_by_user_id' => $actor->getKey(),
        ])->save();

        $this->audit->record(
            action: $status === LifecycleStatus::Disabled && $before['status'] !== 'disabled'
                ? 'business_unit.disabled'
                : 'business_unit.updated',
            module: 'Identity',
            resourceType: 'business_unit',
            resourceId: $unit->getKey(),
            before: $before,
            after: ['name' => $unit->name, 'parent_id' => $unit->parent_id, 'status' => $unit->status->value],
        );

        return $unit;
    }

    /* ---- Teams -------------------------------------------------------- */

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createTeam(array $attributes, User $actor): Team
    {
        $organisationId = $this->organisations->require()->id;
        $code = $this->normaliseCode($attributes['code']);

        if (Team::query()->where('code', $code)->exists()) {
            throw new InvalidArgumentException('A team with the code "'.$code.'" already exists.');
        }

        /* VAL-TEAM-BU-001: exactly one business unit, and it must be one this
         * organisation can see. The scope on the lookup is what stops a crafted
         * id attaching a team to another customer's unit. */
        $unit = BusinessUnit::query()->findOrFail($attributes['business_unit_id']);

        /* VAL-BU-INACTIVE-001. */
        if (! $unit->acceptsAssignment()) {
            throw new RuntimeException('"'.$unit->name.'" is disabled and cannot take a new team.');
        }

        $team = new Team;
        $team->forceFill([
            'organisation_id' => $organisationId,
            'business_unit_id' => $unit->getKey(),
            'code' => $code,
            'name' => $attributes['name'],
            'description' => $attributes['description'] ?? null,
            'lead_user_id' => $attributes['lead_user_id'] ?? null,
            'status' => LifecycleStatus::Active,
            'created_by_user_id' => $actor->getKey(),
            'updated_by_user_id' => $actor->getKey(),
        ])->save();

        $this->audit->record(
            action: 'team.created',
            module: 'Identity',
            resourceType: 'team',
            resourceId: $team->getKey(),
            after: ['code' => $code, 'name' => $team->name, 'business_unit_id' => $unit->getKey()],
        );

        return $team;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateTeam(Team $team, array $attributes, User $actor): Team
    {
        $unit = BusinessUnit::query()->findOrFail($attributes['business_unit_id']);

        $movingUnit = $unit->getKey() !== $team->business_unit_id;

        if ($movingUnit && ! $unit->acceptsAssignment()) {
            throw new RuntimeException('"'.$unit->name.'" is disabled and cannot take a team.');
        }

        $status = $attributes['status'] ?? $team->status;

        $before = [
            'name' => $team->name,
            'business_unit_id' => $team->business_unit_id,
            'status' => $team->status->value,
        ];

        $team->forceFill([
            'name' => $attributes['name'],
            'description' => $attributes['description'] ?? null,
            'business_unit_id' => $unit->getKey(),
            'lead_user_id' => $attributes['lead_user_id'] ?? null,
            'status' => $status,
            'updated_by_user_id' => $actor->getKey(),
        ])->save();

        $this->audit->record(
            /* ADM-004: reassignment is audited as its own event, because
             * moving a team between units changes who reports where. */
            action: $movingUnit ? 'team.reassigned' : 'team.updated',
            module: 'Identity',
            resourceType: 'team',
            resourceId: $team->getKey(),
            before: $before,
            after: ['name' => $team->name, 'business_unit_id' => $unit->getKey(), 'status' => $team->status->value],
        );

        return $team;
    }

    /* ---- Shared ------------------------------------------------------- */

    /**
     * Refuse a parent that would make a unit its own ancestor.
     *
     * VAL-BU-LOOP-001. Two cases: a unit set as its own parent, and a unit set
     * under one of its own descendants. The walk is bounded, so a cycle that
     * somehow already exists produces an exception rather than a hung request.
     *
     * @throws RuntimeException
     */
    private function assertNoLoop(BusinessUnit $unit, ?BusinessUnit $parent): void
    {
        if ($parent === null) {
            return;
        }

        if ($parent->getKey() === $unit->getKey()) {
            throw new RuntimeException('A business unit cannot be its own parent.');
        }

        $current = $parent;
        $steps = 0;

        while ($current !== null) {
            if ($current->getKey() === $unit->getKey()) {
                throw new RuntimeException(
                    'That would put "'.$unit->name.'" underneath one of its own sub-units, which would make the hierarchy a loop.'
                );
            }

            if (++$steps > self::MAX_DEPTH) {
                throw new RuntimeException(
                    'The business unit hierarchy is deeper than '.self::MAX_DEPTH.' levels, which usually means it already contains a loop.'
                );
            }

            $current = $current->parent;
        }
    }

    /**
     * A parent unit from a posted id, scoped to this organisation.
     */
    private function resolveParent(int|string|null $parentId): ?BusinessUnit
    {
        if ($parentId === null || $parentId === '') {
            return null;
        }

        return BusinessUnit::query()->findOrFail($parentId);
    }

    /**
     * A structure code: uppercase, alphanumeric and dashes.
     *
     * Uppercased rather than merely validated, so `sales` and `SALES` cannot
     * both exist and make "the Sales unit" ambiguous.
     */
    private function normaliseCode(string $code): string
    {
        $code = strtoupper(trim($code));
        $code = (string) preg_replace('/[^A-Z0-9]+/', '-', $code);
        $code = trim($code, '-');

        if ($code === '') {
            throw new InvalidArgumentException('A code must contain at least one letter or number.');
        }

        return $code;
    }
}
