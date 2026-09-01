<?php

declare(strict_types=1);

namespace App\Modules\Organisation\Http\Controllers;

use App\Modules\Organisation\Http\Controllers\Concerns\InteractsWithStructure;
use App\Modules\Organisation\Models\BusinessUnit;
use App\Modules\Organisation\Models\Department;
use App\Modules\Organisation\Services\StructureService;
use App\Modules\Organisation\Support\StructureViolation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class DepartmentController
{
    use InteractsWithStructure;

    public function __construct(private readonly StructureService $structure) {}

    public function index(Request $request): Response
    {
        $organisation = $this->organisation($request);

        return Inertia::render('Organisation/Departments', [
            'departments' => Department::query()
                ->where('organisation_id', $organisation->id)
                ->with('businessUnit:id,name')
                ->orderBy('name')
                ->get()
                ->map(fn (Department $department): array => [
                    'id' => $department->id,
                    'name' => $department->name,
                    'code' => $department->code,
                    'status' => $department->status->value,
                    'businessUnit' => $department->businessUnit?->name,
                    'businessUnitId' => $department->business_unit_id,
                ])
                ->all(),
            'businessUnits' => BusinessUnit::query()
                ->where('organisation_id', $organisation->id)
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'business_unit_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:32'],
        ]);

        $parent = BusinessUnit::query()->find($validated['business_unit_id']);

        if ($parent === null || $parent->organisation_id !== $this->organisation($request)->id) {
            return $this->refuse(StructureViolation::because(
                'organisation_mismatch',
                'That business unit is not part of this organisation.'
            ));
        }

        try {
            $this->structure->createDepartment(
                $parent,
                ['name' => $validated['name'], 'code' => $validated['code'] ?? null],
                $this->actor($request)
            );
        } catch (StructureViolation $violation) {
            return $this->refuse($violation);
        }

        return redirect()->route('organisation.departments');
    }

    /**
     * Name and code. NOT the business unit - that is move(), below, which
     * records a scope-affecting event. Correcting a typo must not look like a
     * restructure in the audit catalogue.
     */
    public function update(Request $request, Department $department): RedirectResponse
    {
        /** @var array<string, string|null> $attributes */
        $attributes = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:32'],
        ]);

        try {
            $this->structure->updateDepartment($department, $attributes, $this->actor($request));
        } catch (StructureViolation $violation) {
            return $this->refuse($violation);
        }

        return redirect()->route('organisation.departments');
    }

    public function move(Request $request, Department $department): RedirectResponse
    {
        $validated = $request->validate(['business_unit_id' => ['required', 'integer']]);

        $target = BusinessUnit::query()->find($validated['business_unit_id']);

        if ($target === null) {
            return $this->refuse(StructureViolation::because('not_found', 'That business unit does not exist.'));
        }

        try {
            $this->structure->moveDepartment($department, $target, $this->actor($request));
        } catch (StructureViolation $violation) {
            return $this->refuse($violation);
        }

        return redirect()->route('organisation.departments');
    }

    public function deactivate(Request $request, Department $department): RedirectResponse
    {
        try {
            $this->structure->deactivateDepartment($department, $this->actor($request));
        } catch (StructureViolation $violation) {
            return $this->refuse($violation);
        }

        return redirect()->route('organisation.departments');
    }

    public function reactivate(Request $request, Department $department): RedirectResponse
    {
        try {
            $this->structure->reactivate($department, $this->actor($request));
        } catch (StructureViolation $violation) {
            return $this->refuse($violation);
        }

        return redirect()->route('organisation.departments');
    }
}
