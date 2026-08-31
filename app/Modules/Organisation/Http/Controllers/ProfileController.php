<?php

declare(strict_types=1);

namespace App\Modules\Organisation\Http\Controllers;

use App\Modules\Organisation\Http\Controllers\Concerns\InteractsWithStructure;
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
            'organisation' => $organisation?->only(['id', 'name', 'legal_name', 'country', 'timezone', 'status']),
            'associated' => $actor->belongsToOrganisation(),
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

        return redirect()->route('organisation.profile');
    }

    public function update(Request $request): RedirectResponse
    {
        $organisation = $this->organisations->current();

        if ($organisation === null) {
            return redirect()->route('organisation.profile');
        }

        $this->organisations->updateProfile($organisation, $this->validated($request), $this->actor($request));

        return redirect()->route('organisation.profile');
    }

    /** @return array<string, string|null> */
    private function validated(Request $request): array
    {
        /** @var array<string, string|null> $attributes */
        $attributes = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'size:2'],
            'timezone' => ['nullable', 'string', 'max:64'],
        ]);

        return $attributes;
    }
}
