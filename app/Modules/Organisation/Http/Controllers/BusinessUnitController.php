<?php

declare(strict_types=1);

namespace App\Modules\Organisation\Http\Controllers;

use App\Modules\Organisation\Http\Controllers\Concerns\InteractsWithStructure;
use App\Modules\Organisation\Models\BusinessUnit;
use App\Modules\Organisation\Models\LegalEntity;
use App\Modules\Organisation\Services\StructureService;
use App\Modules\Organisation\Support\StructureViolation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class BusinessUnitController
{
    use InteractsWithStructure;

    public function __construct(private readonly StructureService $structure) {}

    public function index(Request $request): Response
    {
        $organisation = $this->organisation($request);

        return Inertia::render('Organisation/BusinessUnits', [
            'businessUnits' => BusinessUnit::query()
                ->where('organisation_id', $organisation->id)
                ->withCount('departments')
                ->orderBy('name')
                ->get()
                ->map(fn (BusinessUnit $unit): array => [
                    'id' => $unit->id,
                    'name' => $unit->name,
                    'code' => $unit->code,
                    'status' => $unit->status->value,
                    'departments' => $unit->departments_count,
                ])
                ->all(),
        ]);
    }

    /**
     * The D-14 shape, visible: this unit's legal entities, and every legal
     * entity available to associate. Both directions may be many.
     */
    public function show(Request $request, BusinessUnit $businessUnit): Response
    {
        $organisation = $this->organisation($request);

        return Inertia::render('Organisation/BusinessUnit', [
            'businessUnit' => [
                'id' => $businessUnit->id,
                'name' => $businessUnit->name,
                'code' => $businessUnit->code,
                'status' => $businessUnit->status->value,
            ],
            'associated' => $businessUnit->legalEntities()
                ->orderBy('name')
                ->get()
                ->map(fn (LegalEntity $entity): array => ['id' => $entity->id, 'name' => $entity->name])
                ->all(),
            'available' => LegalEntity::query()
                ->where('organisation_id', $organisation->id)
                ->whereNotIn('id', $businessUnit->legalEntities()->pluck('legal_entities.id'))
                ->orderBy('name')
                ->get()
                ->map(fn (LegalEntity $entity): array => ['id' => $entity->id, 'name' => $entity->name])
                ->all(),
            'departments' => $businessUnit->departments()
                ->orderBy('name')
                ->get()
                ->map(fn ($department): array => [
                    'id' => $department->id,
                    'name' => $department->name,
                    'status' => $department->status->value,
                ])
                ->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var array<string, string|null> $attributes */
        $attributes = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:32'],
        ]);

        try {
            $this->structure->createBusinessUnit($this->organisation($request), $attributes, $this->actor($request));
        } catch (StructureViolation $violation) {
            return $this->refuse($violation);
        }

        return redirect()->route('organisation.business-units');
    }

    public function update(Request $request, BusinessUnit $businessUnit): RedirectResponse
    {
        /** @var array<string, string|null> $attributes */
        $attributes = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:32'],
        ]);

        try {
            $this->structure->updateBusinessUnit($businessUnit, $attributes, $this->actor($request));
        } catch (StructureViolation $violation) {
            return $this->refuse($violation);
        }

        return redirect()->route('organisation.business-units');
    }

    /**
     * D-24 guarded permanent delete.
     *
     * The confirmation happened in the browser and proves only that a person
     * clicked twice. The dependency check that decides this is in the service,
     * and it runs again inside the write transaction.
     */
    public function purge(Request $request, BusinessUnit $businessUnit): RedirectResponse
    {
        try {
            $this->structure->purgeBusinessUnit($businessUnit, $this->actor($request));
        } catch (StructureViolation $violation) {
            return $this->refuse($violation);
        }

        return redirect()->route('organisation.business-units');
    }

    public function deactivate(Request $request, BusinessUnit $businessUnit): RedirectResponse
    {
        try {
            $this->structure->deactivateBusinessUnit($businessUnit, $this->actor($request));
        } catch (StructureViolation $violation) {
            return $this->refuse($violation);
        }

        return redirect()->route('organisation.business-units');
    }

    public function reactivate(Request $request, BusinessUnit $businessUnit): RedirectResponse
    {
        try {
            $this->structure->reactivate($businessUnit, $this->actor($request));
        } catch (StructureViolation $violation) {
            return $this->refuse($violation);
        }

        return redirect()->route('organisation.business-units');
    }

    public function associate(Request $request, BusinessUnit $businessUnit): RedirectResponse
    {
        $validated = $request->validate(['legal_entity_id' => ['required', 'integer']]);

        $entity = LegalEntity::query()->find($validated['legal_entity_id']);

        if ($entity === null) {
            return $this->refuse(StructureViolation::because('not_found', 'That legal entity does not exist.'));
        }

        try {
            $this->structure->associate($businessUnit, $entity, $this->actor($request));
        } catch (StructureViolation $violation) {
            return $this->refuse($violation);
        }

        return redirect()->route('organisation.business-unit', $businessUnit);
    }

    public function dissociate(Request $request, BusinessUnit $businessUnit, LegalEntity $legalEntity): RedirectResponse
    {
        $this->structure->dissociate($businessUnit, $legalEntity, $this->actor($request));

        return redirect()->route('organisation.business-unit', $businessUnit);
    }
}
