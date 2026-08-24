<?php

declare(strict_types=1);

namespace App\Modules\Governance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Governance\Services\SovereigntyProfiles;
use App\Modules\Governance\Support\GovernanceStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/**
 * Data Sovereignty Profile. Feature ADM-015.
 *
 * The same three actions as ADM-014, and one difference: on a fresh install the
 * read seeds a DRAFT from the confirmed production facts (decision D12,
 * SEC-DEC-068).
 *
 * THE SEED IS SEEDED ON READ AND THAT IS DELIBERATE, but it is only a seed when
 * there is genuinely nothing - no draft, no approved version, no superseded one.
 * `SovereigntyProfiles::ensureDraft()` holds that condition, not this
 * controller, so a console command reaching the same method behaves the same
 * way.
 *
 * THE SCREEN MUST NEVER SHOW THE SEED AS APPROVED. It carries the draft badge,
 * its `source_note` says where the values came from, and the "in force" panel
 * reads Not Configured until a person approves it. A green tick over a
 * sovereignty position nobody approved would be the same false healthy gate 3
 * shipped over an untracked credential estate.
 *
 * WHY THE GEOGRAPHY VALUES ARE VALIDATED AGAINST THE CATALOGUE. A free-text
 * geography cannot be compared, cannot be reported on and cannot be checked
 * against an approved list. CLAUDE.md's schema rules call for codified
 * reference lists rather than uncontrolled free text, and this is one.
 */
class SovereigntyProfileController extends Controller
{
    public function __construct(
        private readonly SovereigntyProfiles $profiles,
        private readonly GovernanceStorage $storage,
    ) {}

    public function show(): View
    {
        /** @var User|null $actor */
        $actor = Auth::user();

        $inForce = $this->profiles->inForce();
        $draft = $this->profiles->ensureDraft($actor);

        return view('pages.admin.governance.sovereignty', [
            'storageReady' => $this->storage->sovereigntyIsReady(),
            'storageBlocker' => $this->storage->blocker(),
            'inForce' => $inForce,
            'draft' => $draft,
            'history' => $this->profiles->history(),
            'geographies' => (array) config('governance.geographies', []),
            'replicationChoices' => (array) config('governance.external_replication', []),
            /*
             * What the form shows - the draft when there is one, otherwise the
             * version in force. See the note on ADM-014's controller: a blank
             * form over an approved profile turns "change one field" into
             * "erase the other six".
             *
             * It is also what lets a profile be revised AFTER approval. The
             * form is rendered whenever there is something to edit, and saving
             * it opens version 2 through `saveDraft()`. Without this the screen
             * became permanently read-only the moment somebody approved the
             * first version, because the seeded draft was gone and nothing
             * opened another.
             */
            'editing' => $draft ?? $inForce,
            'gaps' => ($draft ?? $inForce)?->gaps() ?? [],
            /*
             * Passed separately from the profile so the warning can be shown
             * above the form as well as beside the switches. A position that
             * crosses a border is the thing a reader most needs to notice.
             */
            'crossesABorder' => ($draft ?? $inForce)?->crossesABorder() ?? false,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $geographies = array_keys((array) config('governance.geographies', []));
        $replication = array_keys((array) config('governance.external_replication', []));

        $validated = $request->validate([
            'storage_geography' => ['nullable', Rule::in($geographies)],
            'processing_geography' => ['nullable', Rule::in($geographies)],
            'ai_processing_geography' => ['nullable', Rule::in($geographies)],
            'backup_geography' => ['nullable', Rule::in($geographies)],
            'external_replication' => ['nullable', Rule::in($replication)],
            'evidence_reference' => ['nullable', 'string', 'max:190'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ], [
            'storage_geography.in' => 'Choose a geography from the list. A typed-in country cannot be '
                .'compared against an approved list.',
        ]);

        /* Four checkboxes, four absences to coerce. See ADM-014's note. */
        foreach ([
            'cross_geo_storage',
            'cross_geo_processing',
            'cross_geo_ai',
            'cross_geo_conversation_history',
        ] as $switch) {
            $validated[$switch] = $request->boolean($switch);
        }

        /** @var User $actor */
        $actor = Auth::user();

        try {
            $this->profiles->saveDraft($validated, $actor);
        } catch (RuntimeException $e) {
            return back()->withErrors(['form' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('admin.governance.sovereignty')
            ->with('status', 'Draft saved. It is not in force until it is approved.');
    }

    public function approve(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ], [
            'reason.required' => 'Approving a sovereignty profile requires a stated reason.',
            'reason.min' => 'Give a reason somebody reviewing this later can actually use.',
        ]);

        /** @var User $actor */
        $actor = Auth::user();

        try {
            $profile = $this->profiles->approve($actor, $validated['reason']);
        } catch (RuntimeException $e) {
            return back()->withErrors(['form' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('admin.governance.sovereignty')
            ->with('status', 'Version '.$profile->version.' approved. It is now the version in force.');
    }
}
