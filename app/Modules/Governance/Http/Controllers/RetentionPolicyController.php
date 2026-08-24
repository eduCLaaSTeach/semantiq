<?php

declare(strict_types=1);

namespace App\Modules\Governance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Governance\Models\PersonalDataCategory;
use App\Modules\Governance\Services\PersonalDataCatalogue;
use App\Modules\Governance\Services\RetentionPolicies;
use App\Modules\Governance\Support\GovernanceStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Retention. Feature PDPA-03.
 *
 * **NO DELETE ROUTE, AND NO ROUTE THAT EXECUTES ANYTHING.** SEC-DEC-038. This
 * screen writes policy down. It runs no sweep, schedules nothing and removes no
 * data, and it says so on the page - because a table full of retention periods
 * reads as protection, and reading it that way is how an organisation believes
 * it is compliant while nothing at all happens.
 *
 * THE LIST IS OF CATEGORIES, NOT POLICIES. A category with no policy is the
 * state that matters most: it is personal data nobody has decided a period for.
 * Listing only the policies that exist would hide exactly that, so the index
 * walks the active categories and pairs each with its policy or with nothing.
 */
class RetentionPolicyController extends Controller
{
    public function __construct(
        private readonly RetentionPolicies $policies,
        private readonly PersonalDataCatalogue $catalogue,
        private readonly GovernanceStorage $storage,
    ) {}

    public function index(): View
    {
        /** @var User|null $actor */
        $actor = Auth::user();

        return view('pages.admin.governance.retention', [
            'storageReady' => $this->storage->retentionIsReady() && $this->storage->categoriesAreReady(),
            'storageBlocker' => $this->storage->blocker(),
            /* Seeds the categories if this is the first visit anywhere in
             * governance, so retention is not empty purely because somebody
             * reached it before the register. */
            'seeded' => $this->catalogue->all($actor)->count(),
            'rows' => $this->policies->forEveryCategory(),
            'withoutAPeriod' => $this->policies->categoriesWithoutAPeriod(),
            'overdueReviews' => $this->policies->overdueReviews(),
        ]);
    }

    public function edit(int $category): View
    {
        $model = $this->requireCategory($category);

        return view('pages.admin.governance.retention-edit', [
            'category' => $model,
            'policy' => $this->policies->findForCategory((int) $model->getKey()),
            'startEvents' => (array) config('governance.retention_start_events', []),
            'disposalActions' => (array) config('governance.retention_disposal_actions', []),
        ]);
    }

    public function update(Request $request, int $category): RedirectResponse
    {
        $model = $this->requireCategory($category);

        $validated = $request->validate([
            /*
             * Nullable on purpose. A blank period is Not Configured, which is a
             * true and useful state - far better than a plausible default
             * SemantIQ invented.
             */
            'retention_months' => ['nullable', 'integer', 'min:1', 'max:1200'],
            'basis' => ['nullable', 'string', 'max:2000'],
            'lawful_basis' => ['nullable', 'string', 'max:190'],
            'start_event' => ['nullable', Rule::in(array_keys((array) config('governance.retention_start_events', [])))],
            'disposal_action' => ['nullable', Rule::in(array_keys((array) config('governance.retention_disposal_actions', [])))],
            'owner' => ['nullable', 'string', 'max:190'],
            'exception_rule' => ['nullable', 'string', 'max:2000'],
            'next_review_on' => ['nullable', 'date'],
        ], [
            'retention_months.max' => 'A hundred years is not a retention period. Check the figure.',
        ]);

        /** @var User $actor */
        $actor = Auth::user();

        try {
            $this->policies->save($model, $validated, $actor);
        } catch (RuntimeException $e) {
            return back()->withErrors(['form' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('admin.governance.retention')
            ->with('status', 'Retention policy saved. Nothing is deleted by SemantIQ as a result.');
    }

    public function approve(Request $request, int $category): RedirectResponse
    {
        $model = $this->requireCategory($category);
        $policy = $this->policies->findForCategory((int) $model->getKey());

        if ($policy === null) {
            return back()->withErrors(['form' => 'There is no policy to approve for this category yet.']);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ], [
            'reason.required' => 'Approving a retention policy requires a stated reason.',
        ]);

        /** @var User $actor */
        $actor = Auth::user();

        try {
            $this->policies->approve($policy, $actor, $validated['reason']);
        } catch (RuntimeException $e) {
            return back()->withErrors(['form' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('admin.governance.retention')
            ->with('status', 'Retention policy approved. This records a decision; it does not delete anything.');
    }

    private function requireCategory(int $id): PersonalDataCategory
    {
        $category = $this->catalogue->find($id);

        if ($category === null) {
            throw new NotFoundHttpException;
        }

        return $category;
    }
}
