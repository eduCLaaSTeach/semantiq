<?php

declare(strict_types=1);

namespace App\Modules\Governance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Governance\Enums\DataClassification;
use App\Modules\Governance\Models\PersonalDataCategory;
use App\Modules\Governance\Services\PersonalDataCatalogue;
use App\Modules\Governance\Support\GovernanceStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Personal / Sensitive Data. Feature ADM-014, the category register.
 *
 * What kinds of personal data this application holds about people. The register
 * PDPA-01 answers from in R1.4c.
 *
 * NO CREATE AND NO DELETE IN THIS BATCH, and both absences are deliberate rather
 * than unfinished. The seven categories come from a scan of the live schema, and
 * a category a customer invents needs `source_tables` to point at real tables to
 * be worth anything - which means create belongs with the R1.4c collector that
 * validates those names, not before it. Delete does not belong anywhere: a
 * category is part of the record of how data was treated, so it is retired.
 *
 * THE EDIT ROUTE TAKES A PLAIN INTEGER, NOT AN IMPLICIT MODEL BINDING.
 * SEC-DEC-058. `SubstituteBindings` lives in the `web` middleware GROUP and runs
 * before any route-level middleware, so an implicit binding would query
 * `personal_data_categories` before the storage guard could refuse - a guard
 * that runs after the query it is guarding is not a guard. The lookup happens
 * here instead, which also makes the organisation boundary explicit rather than
 * inherited.
 */
class PersonalDataCategoryController extends Controller
{
    public function __construct(
        private readonly PersonalDataCatalogue $catalogue,
        private readonly GovernanceStorage $storage,
    ) {}

    public function index(): View
    {
        /** @var User|null $actor */
        $actor = Auth::user();

        return view('pages.admin.governance.personal-data', [
            'storageReady' => $this->storage->categoriesAreReady(),
            'storageBlocker' => $this->storage->blocker(),
            'categories' => $this->catalogue->all($actor),
            'classifications' => DataClassification::cases(),
        ]);
    }

    public function edit(int $category): View
    {
        return view('pages.admin.governance.personal-data-edit', [
            'category' => $this->require($category),
            'classifications' => DataClassification::cases(),
        ]);
    }

    public function update(Request $request, int $category): RedirectResponse
    {
        $model = $this->require($category);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'description' => ['required', 'string', 'max:2000'],
            'classification' => ['required', Rule::in(array_column(DataClassification::cases(), 'value'))],
            'status' => ['required', Rule::in(['active', 'retired'])],
            /*
             * A newline-separated list rather than a repeater. These are table
             * names an administrator copies from a schema, and a text area is
             * the honest control for that until R1.4c can validate each name
             * against the live schema and offer a picker.
             */
            'source_tables' => ['nullable', 'string', 'max:2000'],
        ]);

        $validated['contains_sensitive'] = $request->boolean('contains_sensitive');
        $validated['source_tables'] = $this->parseTables($validated['source_tables'] ?? null);

        /** @var User $actor */
        $actor = Auth::user();

        try {
            $this->catalogue->update($model, $validated, $actor);
        } catch (RuntimeException $e) {
            return back()->withErrors(['form' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('admin.governance.personal-data')
            ->with('status', 'Category saved.');
    }

    /**
     * Resolve a category or 404.
     *
     * 404 rather than 403 for a category belonging to another organisation, for
     * the reason SEC-DEC-034 gives: a 403 confirms the row exists, and the ids
     * are sequential integers.
     */
    private function require(int $id): PersonalDataCategory
    {
        $category = $this->catalogue->find($id);

        if ($category === null) {
            throw new NotFoundHttpException;
        }

        return $category;
    }

    /**
     * One table name per line, trimmed, blanks dropped, order preserved.
     *
     * @return list<string>
     */
    private function parseTables(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        $lines = preg_split('/[\r\n,]+/', $raw) ?: [];

        return array_values(array_unique(array_filter(
            array_map(static fn (string $line): string => trim($line), $lines),
            static fn (string $line): bool => $line !== '',
        )));
    }
}
