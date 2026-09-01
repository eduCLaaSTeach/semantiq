<?php

declare(strict_types=1);

namespace App\Modules\Organisation\Http\Controllers;

use App\Modules\Organisation\Http\Controllers\Concerns\InteractsWithStructure;
use App\Modules\Organisation\Models\LegalEntity;
use App\Modules\Organisation\Rules\ApprovedJurisdiction;
use App\Modules\Organisation\Services\StructureService;
use App\Modules\Organisation\Support\Jurisdictions;
use App\Modules\Organisation\Support\StructureViolation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class LegalEntityController
{
    use InteractsWithStructure;

    public function __construct(private readonly StructureService $structure) {}

    public function index(Request $request): Response
    {
        $organisation = $this->organisation($request);

        return Inertia::render('Organisation/LegalEntities', [
            // The approved jurisdiction list travels with the page, so the
            // dropdown needs no request of its own and no external call.
            'jurisdictions' => Jurisdictions::all(),
            'legalEntities' => LegalEntity::query()
                ->where('organisation_id', $organisation->id)
                ->orderBy('name')
                ->get()
                ->map(fn (LegalEntity $entity): array => [
                    'id' => $entity->id,
                    'name' => $entity->name,
                    'registration_number' => $entity->registration_number,
                    'jurisdiction' => $entity->jurisdiction,
                    'registered_address' => $entity->registered_address,
                    'status' => $entity->status->value,
                ])
                ->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var array<string, string|null> $attributes */
        $attributes = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'registration_number' => ['nullable', 'string', 'max:64'],
            'jurisdiction' => ['nullable', 'string', 'max:64', new ApprovedJurisdiction],
            'registered_address' => ['nullable', 'string'],
        ]);

        try {
            $this->structure->createLegalEntity($this->organisation($request), $attributes, $this->actor($request));
        } catch (StructureViolation $violation) {
            return $this->refuse($violation);
        }

        return redirect()->route('organisation.legal-entities');
    }

    public function update(Request $request, LegalEntity $legalEntity): RedirectResponse
    {
        /** @var array<string, string|null> $attributes */
        $attributes = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'registration_number' => ['nullable', 'string', 'max:64'],
            'jurisdiction' => ['nullable', 'string', 'max:64', new ApprovedJurisdiction],
            'registered_address' => ['nullable', 'string'],
        ]);

        try {
            $this->structure->updateLegalEntity($legalEntity, $attributes, $this->actor($request));
        } catch (StructureViolation $violation) {
            return $this->refuse($violation);
        }

        return redirect()->route('organisation.legal-entities');
    }

    public function deactivate(Request $request, LegalEntity $legalEntity): RedirectResponse
    {
        try {
            $this->structure->deactivateLegalEntity($legalEntity, $this->actor($request));
        } catch (StructureViolation $violation) {
            return $this->refuse($violation);
        }

        return redirect()->route('organisation.legal-entities');
    }

    public function reactivate(Request $request, LegalEntity $legalEntity): RedirectResponse
    {
        try {
            $this->structure->reactivate($legalEntity, $this->actor($request));
        } catch (StructureViolation $violation) {
            return $this->refuse($violation);
        }

        return redirect()->route('organisation.legal-entities');
    }
}
