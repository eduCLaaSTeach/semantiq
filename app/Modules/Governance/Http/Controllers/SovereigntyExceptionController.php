<?php

declare(strict_types=1);

namespace App\Modules\Governance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Governance\Models\SovereigntyException;
use App\Modules\Governance\Services\SovereigntyExceptions;
use App\Modules\Governance\Services\SovereigntyProfiles;
use App\Modules\Governance\Support\GovernanceStorage;
use App\Modules\Identity\Support\Authorization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Sovereignty Exceptions. Feature ADM-016.
 *
 * Request, approve, reject, revoke. No edit and no delete: an exception is a
 * record of something asked for and answered, and editing the question after
 * the answer would make the trail meaningless.
 *
 * THE APPROVE AND REJECT ROUTES SHARE ONE FORM AND ONE PERMISSION. They are two
 * outcomes of one decision, and splitting them into two permissions would let
 * somebody hold the power to say yes without the power to say no - which is not
 * a reviewer.
 *
 * `{exception}` is a plain integer resolved here, NOT an implicit model
 * binding, for the reason SEC-DEC-058 records: `SubstituteBindings` runs in the
 * `web` middleware GROUP, ahead of route middleware, so a binding would query
 * the table before the storage guard could refuse it.
 */
class SovereigntyExceptionController extends Controller
{
    public function __construct(
        private readonly SovereigntyExceptions $exceptions,
        private readonly SovereigntyProfiles $profiles,
        private readonly GovernanceStorage $storage,
        private readonly Authorization $authorization,
    ) {}

    public function index(): View
    {
        /** @var User|null $reader */
        $reader = Auth::user();

        return view('pages.admin.governance.exceptions', [
            /*
             * Computed here and passed in, following the convention
             * `roles.blade.php` set. NOT Blade's `@can`: this application uses
             * `Authorization`, registers no Laravel Gates, and `@can` against
             * an undefined gate returns FALSE SILENTLY - so a form guarded that
             * way would simply never render, for anybody, with nothing to
             * suggest why.
             *
             * Hiding a control is convenience only. Both routes carry their own
             * `permission:` middleware, so a typed POST meets the same gate.
             */
            'mayRequest' => $this->authorization->allows($reader, 'admin.sovereignty_exceptions.request'),
            'mayDecide' => $this->authorization->allows($reader, 'admin.sovereignty_exceptions.approve'),
            'storageReady' => $this->storage->exceptionsAreReady(),
            'storageBlocker' => $this->storage->blocker(),
            'exceptions' => $this->exceptions->all(),
            'inForceCount' => $this->exceptions->inForce()->count(),
            'awaitingCount' => $this->exceptions->awaitingDecision(),
            'expiringSoon' => $this->exceptions->expiringWithin(30),
            /* The position being departed from, so a reader sees both together
             * rather than having to hold one in their head. */
            'inForceProfile' => $this->profiles->inForce(),
            'aspects' => (array) config('governance.exception_aspects', []),
            'geographies' => (array) config('governance.geographies', []),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:190'],
            'justification' => ['required', 'string', 'min:20', 'max:2000'],
            'aspect' => ['required', Rule::in(array_keys((array) config('governance.exception_aspects', [])))],
            'requested_geography' => ['nullable', Rule::in(array_keys((array) config('governance.geographies', [])))],
            'scope_note' => ['nullable', 'string', 'max:2000'],
            'starts_on' => ['nullable', 'date'],
            /*
             * An end date is REQUIRED, and must be after the start. An exception
             * with no end is a permanent change to the sovereignty position
             * wearing the word "exception", which is exactly the thing this
             * feature exists to prevent.
             */
            'ends_on' => ['required', 'date', 'after_or_equal:today', 'after_or_equal:starts_on'],
        ], [
            'justification.min' => 'Give a justification somebody deciding this can actually weigh. '
                .'It is the whole basis on which data is allowed to leave its geography.',
            'ends_on.required' => 'An exception must have an end date. Without one it is a permanent '
                .'change to the sovereignty position rather than an exception to it.',
            'ends_on.after_or_equal' => 'The end date cannot be in the past or before the start date.',
        ]);

        /** @var User $actor */
        $actor = Auth::user();

        try {
            $this->exceptions->request($validated, $actor);
        } catch (RuntimeException $e) {
            return back()->withErrors(['form' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('admin.governance.exceptions')
            ->with('status', 'Exception requested. It permits nothing until somebody approves it.');
    }

    public function decide(Request $request, int $exception): RedirectResponse
    {
        $model = $this->require($exception);

        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'note' => ['required', 'string', 'min:10', 'max:1000'],
        ], [
            'note.required' => 'Deciding an exception requires a stated reason.',
            'note.min' => 'Give a reason somebody reviewing this decision later can use.',
        ]);

        /** @var User $actor */
        $actor = Auth::user();

        try {
            $validated['decision'] === 'approve'
                ? $this->exceptions->approve($model, $actor, $validated['note'])
                : $this->exceptions->reject($model, $actor, $validated['note']);
        } catch (RuntimeException $e) {
            return back()->withErrors(['form' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('admin.governance.exceptions')
            ->with('status', 'Exception '.($validated['decision'] === 'approve' ? 'approved' : 'rejected').'.');
    }

    public function revoke(Request $request, int $exception): RedirectResponse
    {
        $model = $this->require($exception);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ], [
            'reason.required' => 'Revoking an exception requires a stated reason.',
        ]);

        /** @var User $actor */
        $actor = Auth::user();

        try {
            $this->exceptions->revoke($model, $actor, $validated['reason']);
        } catch (RuntimeException $e) {
            return back()->withErrors(['form' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('admin.governance.exceptions')
            ->with('status', 'Exception revoked. It stopped applying immediately.');
    }

    /**
     * Resolve or 404.
     *
     * 404 rather than 403 for another organisation's id, per SEC-DEC-034: a 403
     * confirms the row exists, and the ids are sequential integers.
     */
    private function require(int $id): SovereigntyException
    {
        $model = $this->exceptions->find($id);

        if ($model === null) {
            throw new NotFoundHttpException;
        }

        return $model;
    }
}
