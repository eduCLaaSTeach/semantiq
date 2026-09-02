<?php

declare(strict_types=1);

namespace App\Modules\Organisation\Http\Controllers;

use App\Modules\Organisation\Http\Controllers\Concerns\InteractsWithStructure;
use App\Modules\Organisation\Models\LegalEntity;
use App\Modules\Organisation\Services\OrganisationService;
use App\Modules\Organisation\Support\StructureViolation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Company Profile: the first screen, and the one that creates the
 * organisation.
 *
 * Under D-16 it is also the single place users.organisation_id is written - the
 * administrator who creates the profile is associated with it in the same
 * transaction.
 */
final class ProfileController
{
    use InteractsWithStructure;

    public function __construct(private readonly OrganisationService $organisations) {}

    public function show(Request $request): Response
    {
        $organisation = $this->organisations->current();
        $actor = $this->actor($request);

        return Inertia::render('Organisation/Profile', [
            'organisation' => $organisation?->only([
                'id', 'name', 'legal_name', 'primary_legal_entity_id', 'country', 'timezone', 'status',
            ]),
            'associated' => $actor->belongsToOrganisation(),
            // D-25. Active only, and only this organisation's - the same two
            // conditions the service enforces, so the dropdown and the guard
            // cannot disagree about what is selectable.
            'legalEntities' => $organisation === null ? [] : LegalEntity::query()
                ->where('organisation_id', $organisation->id)
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $attributes = $this->validated($request);

        try {
            $this->organisations->createProfile($attributes, $this->actor($request));
        } catch (StructureViolation $violation) {
            return $this->refuse($violation);
        }

        return $this->confirm('organisation.profile', 'Company Profile created. You are now associated with this organisation.');
    }

    public function update(Request $request): RedirectResponse
    {
        $organisation = $this->organisations->current();

        // Nothing to update, so nothing is confirmed. Saying "saved" here would
        // be the confirmation lying, which is worse than the silence it
        // replaces.
        if ($organisation === null) {
            return redirect()->route('organisation.profile');
        }

        try {
            $this->organisations->updateProfile(
                $organisation,
                $this->validated($request),
                $this->actor($request)
            );
        } catch (StructureViolation $violation) {
            return $this->refuse($violation);
        }

        return $this->confirm('organisation.profile', 'Company Profile saved.');
    }

    /** @return array<string, string|int|null> */
    private function validated(Request $request): array
    {
        /** @var array<string, string|int|null> $attributes */
        $attributes = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            // D-25. Nullable: an empty selection is Clear, and an organisation
            // with no primary legal entity is a real state. Whether the id is a
            // legal entity of THIS organisation, and active, is decided in the
            // service - not here, and not by the dropdown.
            'primary_legal_entity_id' => ['nullable', 'integer'],
            'country' => ['nullable', 'string', 'size:2'],
            'timezone' => ['nullable', 'string', 'max:64'],
        ]);

        /*
         * The empty option posts "", which is Clear. It is coerced ONLY when the
         * field was actually sent: a request that omits it leaves the selection
         * alone, so a partial save can never silently clear the organisation's
         * corporate identity.
         */
        if (array_key_exists('primary_legal_entity_id', $attributes)
            && ($attributes['primary_legal_entity_id'] === '' || $attributes['primary_legal_entity_id'] === null)) {
            $attributes['primary_legal_entity_id'] = null;
        }

        return $attributes;
    }
}
