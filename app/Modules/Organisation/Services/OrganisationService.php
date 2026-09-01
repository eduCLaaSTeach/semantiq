<?php

declare(strict_types=1);

namespace App\Modules\Organisation\Services;

use App\Modules\Organisation\Models\LegalEntity;
use App\Modules\Organisation\Models\Organisation;
use App\Modules\Organisation\Models\StructureStatus;
use App\Modules\Organisation\Support\StructureViolation;
use App\Modules\Platform\Models\User;
use App\Modules\Platform\Security\SecurityEventLogger;
use Illuminate\Support\Facades\DB;

/**
 * The Company Profile, and the single place D-16 associates a user.
 *
 * Release 1 is single-tenant: one organisation, created here rather than seeded,
 * because a row created by migration would be invented business content.
 */
final class OrganisationService
{
    public function __construct(private readonly SecurityEventLogger $events) {}

    public function current(): ?Organisation
    {
        return Organisation::query()->orderBy('id')->first();
    }

    /**
     * Create the organisation and associate its creator, in one transaction.
     *
     * This is D-16's population rule and the ONLY place in P1-01 that writes
     * users.organisation_id. No seed, no backfill, no manual database write, no
     * change to bootstrap: the existing System Administrator carries NULL until
     * they create the profile here.
     *
     * The association is inside the transaction on purpose. An organisation that
     * exists with nobody associated to it would leave the administrator who made
     * it unable to build any structure, and would need a repair path that this
     * unit deliberately does not have.
     *
     * @param  array<string, string|null>  $attributes
     */
    public function createProfile(array $attributes, User $creator): Organisation
    {
        if ($this->current() !== null) {
            throw StructureViolation::because(
                'organisation_already_exists',
                'An organisation already exists. Release 1 is single-tenant.'
            );
        }

        return DB::transaction(function () use ($attributes, $creator): Organisation {
            $organisation = Organisation::query()->create($attributes + ['status' => StructureStatus::Active]);

            $creator->forceFill(['organisation_id' => $organisation->id])->save();

            $this->events->record(SecurityEventLogger::ORGANISATION_CREATED, [
                'user_id' => $creator->id,
                'organisation_id' => $organisation->id,
                'result' => 'created',
            ]);

            return $organisation;
        });
    }

    /**
     * @param  array<string, string|null|int>  $attributes
     */
    public function updateProfile(Organisation $organisation, array $attributes, User $actor): Organisation
    {
        if (array_key_exists('primary_legal_entity_id', $attributes)) {
            $this->requireSelectablePrimary($organisation, $attributes['primary_legal_entity_id']);
        }

        $organisation->fill($attributes)->save();

        $this->events->record(SecurityEventLogger::ORGANISATION_UPDATED, [
            'user_id' => $actor->id,
            'organisation_id' => $organisation->id,
            'result' => 'updated',
        ]);

        return $organisation;
    }

    /**
     * D-25. Set, Change and Clear are one operation: a chosen value or none.
     *
     * The screen offers only this organisation's active legal entities, and
     * that is a convenience, not the control - the value arrives in an HTTP
     * request and anyone can send any id. Both conditions are enforced here.
     *
     * Empty is permitted and is what Clear sends. An organisation with no
     * primary legal entity is a real state, not an error: production has one
     * today and will still have one immediately after the migration, because
     * nothing backfills it.
     */
    private function requireSelectablePrimary(Organisation $organisation, mixed $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $entity = LegalEntity::query()->find($value);

        /*
         * Same organisation. The screen never offers another organisation's
         * entity, but the screen is not the control - route-model binding and a
         * hand-made request both resolve by id alone. Release 1 is
         * single-tenant, which makes this unreachable today and exactly the kind
         * of guard that is missing when it stops being unreachable.
         */
        if ($entity === null || $entity->organisation_id !== $organisation->id) {
            throw StructureViolation::because(
                'organisation_mismatch',
                'That legal entity is not part of this organisation.'
            );
        }

        /*
         * Active only. A retired entity is not who the company currently is, and
         * choosing one would also create a state the deactivation guard is
         * written to prevent - reached from the other direction.
         */
        if (! $entity->isActive()) {
            throw StructureViolation::because(
                'inactive_legal_entity',
                'That legal entity is inactive. Reactivate it first, or choose an active one.'
            );
        }
    }
}
