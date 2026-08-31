<?php

declare(strict_types=1);

namespace App\Modules\Organisation\Services;

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
     * @param  array<string, string|null>  $attributes
     */
    public function updateProfile(Organisation $organisation, array $attributes, User $actor): Organisation
    {
        $organisation->fill($attributes)->save();

        $this->events->record(SecurityEventLogger::ORGANISATION_UPDATED, [
            'user_id' => $actor->id,
            'organisation_id' => $organisation->id,
            'result' => 'updated',
        ]);

        return $organisation;
    }
}
