<?php

declare(strict_types=1);

namespace App\Modules\Governance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Governance\Services\DataProtectionProfiles;
use App\Modules\Governance\Support\GovernanceStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use RuntimeException;

/**
 * Data Protection Profile. Feature ADM-014.
 *
 * Three actions and no more: read, save the draft, approve the draft. There is
 * no delete and no edit-in-place, which is decision D4 expressed as a route
 * table rather than as a rule somebody has to remember.
 *
 * THE SCREEN SHOWS TWO THINGS AT ONCE and the distinction is the whole point:
 * the version IN FORCE, and the DRAFT being written. Collapsing them into one
 * "current profile" would make a half-finished edit look like the organisation's
 * position, which is the failure SEC-DEC-068 names for the sovereignty seed and
 * which applies just as much here.
 *
 * WHEN THE MIGRATION HAS NOT RUN, the screen renders and says so. It does not
 * 500 and it does not show an empty profile as though nothing had been
 * configured - "we cannot tell you" and "nothing is configured" are different
 * facts, and gate 3 shipped the second one as the first before somebody caught
 * it on the live site. SEC-DEC-057, SEC-DEC-072.
 */
class DataProtectionProfileController extends Controller
{
    public function __construct(
        private readonly DataProtectionProfiles $profiles,
        private readonly GovernanceStorage $storage,
    ) {}

    public function show(): View
    {
        $inForce = $this->profiles->inForce();
        $draft = $this->profiles->draft();

        return view('pages.admin.governance.data-protection', [
            'storageReady' => $this->storage->dataProtectionIsReady(),
            'storageBlocker' => $this->storage->blocker(),
            'inForce' => $inForce,
            'draft' => $draft,
            'history' => $this->profiles->history(),
            'regimes' => (array) config('governance.regime.choices', []),
            /*
             * Passed so the screen can say what is still missing rather than
             * showing an unexplained warning. Read from whichever version the
             * reader is looking at - the draft when there is one, because that
             * is what they would be fixing.
             */
            /*
             * What the FORM shows. The draft when there is one, and otherwise
             * the version in force.
             *
             * This is not cosmetic. With no draft open the form used to render
             * blank, so somebody changing one field posted six empty ones on
             * top of an approved profile - the new version silently lost every
             * value the old one had. `saveDraft()` copies the approved version
             * before filling, but the copy was immediately overwritten by the
             * blanks the form had just posted. Found by driving the screen in a
             * browser; the service test passed because it called `saveDraft()`
             * with one key rather than through a form.
             */
            'editing' => $draft ?? $inForce,
            'gaps' => ($draft ?? $inForce)?->gaps() ?? [],
            'defaultDueDays' => config('governance.breach_notification.due_days_default'),
            'deadlineUnit' => config('governance.breach_notification.unit'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'applicable_regime' => ['nullable', 'string', 'max:64'],
            'regime_basis' => ['nullable', 'string', 'max:2000'],
            'privacy_officer_designated' => ['nullable', 'boolean'],
            'breach_notification_due_days' => array_merge(
                ['nullable'],
                (array) config('governance.breach_notification.due_days_rules', ['integer']),
            ),
            'breach_notification_basis' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ], [
            'breach_notification_due_days.min' => 'A notification deadline of less than one day cannot '
                .'be met. Enter the number of days the regulator actually allows.',
        ]);

        /*
         * An unchecked checkbox is absent from the request, not false, so the
         * flag has to be coerced here. Left to the validator it would silently
         * keep whatever the draft already said - which for a designation flag
         * would mean unchecking it never took effect.
         */
        $validated['privacy_officer_designated'] = $request->boolean('privacy_officer_designated');

        /** @var User $actor */
        $actor = Auth::user();

        try {
            $this->profiles->saveDraft($validated, $actor);
        } catch (RuntimeException $e) {
            return back()->withErrors(['form' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('admin.governance.data-protection')
            ->with('status', 'Draft saved. It is not in force until it is approved.');
    }

    public function approve(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ], [
            'reason.required' => 'Approving a data protection profile requires a stated reason.',
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
            ->route('admin.governance.data-protection')
            ->with('status', 'Version '.$profile->version.' approved. It is now the version in force.');
    }
}
