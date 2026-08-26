<?php

declare(strict_types=1);

namespace App\Modules\Governance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Governance\Enums\CorrectionOutcome;
use App\Modules\Governance\Enums\PrivacyRequestType;
use App\Modules\Governance\Models\PrivacyRequest;
use App\Modules\Governance\Privacy\CollectorCatalogue;
use App\Modules\Governance\Privacy\ExclusionRegister;
use App\Modules\Governance\Services\CorrectionNotes;
use App\Modules\Governance\Services\PrivacyRequests;
use App\Modules\Governance\Support\GovernanceStorage;
use App\Modules\Identity\Support\Authorization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use RuntimeException;

/**
 * PDPA-01 Privacy Requests.
 *
 * IDS ARE PLAIN INTEGERS, RESOLVED HERE, never implicit model bindings.
 * `SubstituteBindings` lives in the `web` middleware GROUP, which runs BEFORE
 * route-level middleware - so an implicit binding would query
 * `privacy_requests` before the storage guard or the permission check could
 * refuse, and a typed URL during a deployment window would return a raw
 * database error instead of an explanation. SEC-DEC-058.
 *
 * A REFUSED LIFECYCLE ACTION RETURNS THE PERSON TO THE SCREEN WITH THE REASON,
 * following the convention every other governance controller already uses. The
 * service refuses an illegal transition by throwing, and the messages it throws
 * are written for a reader rather than as error codes, so showing them is both
 * the kindest and the most accurate thing to do. Letting the exception escape
 * would render a stack trace for what is simply somebody acting on a stale
 * page - two administrators with the same request open, and the second one
 * clicks Close after the first already did. SEC-DEC-088.
 *
 * NO EXPORT, NO DOWNLOAD, NO FILE. There is deliberately no action here that
 * produces one. The response is read on screen and delivered by a person
 * outside SemantIQ, and `evidence_reference` records how. Decision D9.
 */
class PrivacyRequestController extends Controller
{
    public function __construct(
        private readonly PrivacyRequests $requests,
        private readonly CorrectionNotes $notes,
        private readonly GovernanceStorage $storage,
        private readonly Authorization $authorization,
        private readonly CollectorCatalogue $catalogue,
        private readonly ExclusionRegister $exclusions,
    ) {}

    public function index(): View
    {
        /** @var User|null $reader */
        $reader = Auth::user();

        return view('pages.admin.governance.privacy-requests', [
            /*
             * Computed here and passed in, following the convention
             * `roles.blade.php` set. NOT Blade's `@can`: this application
             * registers no Laravel Gates, so `@can` returns FALSE SILENTLY and
             * a form guarded that way would never render for anybody.
             *
             * Hiding a control is convenience. Every route below carries its
             * own `permission:` middleware, so a typed POST meets the same gate.
             */
            'mayManage' => $this->authorization->allows($reader, 'admin.privacy_requests.manage'),
            'mayRelease' => $this->authorization->allows($reader, 'admin.privacy_requests.release'),
            'storageReady' => $this->storage->privacyRequestsAreReady(),
            'storageBlocker' => $this->storage->blocker(),
            'requests' => $this->requests->all(),
            'openCount' => $this->requests->openCount(),
            'types' => PrivacyRequestType::cases(),
            'tablesCovered' => count($this->catalogue->tables()),
            'tablesExcluded' => count($this->exclusions->tables()),
        ]);
    }

    public function show(int $request): View
    {
        $model = $this->find($request);

        /** @var User|null $reader */
        $reader = Auth::user();

        return view('pages.admin.governance.privacy-request', [
            'mayManage' => $this->authorization->allows($reader, 'admin.privacy_requests.manage'),
            'mayRelease' => $this->authorization->allows($reader, 'admin.privacy_requests.release'),
            'request' => $model,
            /*
             * Why release is unavailable, so the screen can SAY SO rather than
             * hide the button. A control that vanishes without explanation
             * reads as a bug, and the person who needs to act cannot tell what
             * to do next - here the answer is usually "somebody else has to do
             * this", which nobody can guess from an absent button.
             */
            'releaseBlocker' => $model->releaseBlockedBecause($reader),
            'records' => $model->records()->orderBy('band')->orderBy('source_table')->get(),
            'notes' => $this->notes->forRequest($model),
            'methods' => $this->requests->verificationMethods(),
            'outcomes' => CorrectionOutcome::cases(),
            'exclusions' => $this->exclusions->all(),
        ]);
    }

    public function store(Request $httpRequest): RedirectResponse
    {
        $validated = $httpRequest->validate([
            'request_type' => ['required', 'string', 'in:access,correction,withdrawal'],
            'subject_name' => ['required', 'string', 'max:190'],
            'subject_email' => ['required', 'email', 'max:190'],
            'subject_reference' => ['nullable', 'string', 'max:190'],
            'subject_user_id' => ['nullable', 'integer'],
            'received_at' => ['required', 'date'],
            'received_channel' => ['nullable', 'string', 'max:32'],
        ]);

        $model = $this->requests->receive([
            'request_type' => $validated['request_type'],
            'subject_name' => $validated['subject_name'],
            'subject_email' => $validated['subject_email'],
            'subject_reference' => $validated['subject_reference'] ?? null,
            'subject_user_id' => $validated['subject_user_id'] ?? null,
            'received_at' => $validated['received_at'],
            'received_channel' => $validated['received_channel'] ?? null,
        ], $this->actor());

        return redirect()
            ->route('admin.governance.privacy-requests.show', $model->getKey())
            ->with('status', 'Request '.$model->reference.' recorded. Nothing is collected until the '
                .'requester has been identified.');
    }

    public function verify(Request $httpRequest, int $request): RedirectResponse
    {
        $model = $this->find($request);

        $validated = $httpRequest->validate([
            'method' => ['required', 'string', 'max:64'],
            'note' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $this->requests->verifyIdentity($model, $this->actor(), $validated['method'], $validated['note']);
        } catch (RuntimeException $e) {
            return back()->withErrors(['form' => $e->getMessage()])->withInput();
        }

        return back()->with('status', 'Identity verified. The response deadline is now fixed and collection '
            .'may run.');
    }

    public function assemble(int $request): RedirectResponse
    {
        $model = $this->find($request);

        try {
            $this->requests->assemble($model, $this->actor());
        } catch (RuntimeException $e) {
            return back()->withErrors(['form' => $e->getMessage()]);
        }

        return back()->with('status', 'Collection complete. Review what may be disclosed before releasing '
            .'anything.');
    }

    public function review(int $request): RedirectResponse
    {
        $model = $this->find($request);

        try {
            $this->requests->markReviewed($model, $this->actor());
        } catch (RuntimeException $e) {
            return back()->withErrors(['form' => $e->getMessage()]);
        }

        return back()->with('status', 'Recorded as reviewed.');
    }

    public function release(Request $httpRequest, int $request): RedirectResponse
    {
        $model = $this->find($request);

        $validated = $httpRequest->validate([
            'evidence_reference' => ['required', 'string', 'max:190'],
        ]);

        try {
            $this->requests->release($model, $this->actor(), $validated['evidence_reference']);
        } catch (RuntimeException $e) {
            return back()->withErrors(['form' => $e->getMessage()])->withInput();
        }

        return back()->with('status', 'Released. SemantIQ sent nothing itself - the reference you recorded '
            .'is the evidence of delivery.');
    }

    public function refuse(Request $httpRequest, int $request): RedirectResponse
    {
        $model = $this->find($request);

        $validated = $httpRequest->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $this->requests->refuse($model, $this->actor(), $validated['reason']);
        } catch (RuntimeException $e) {
            return back()->withErrors(['form' => $e->getMessage()])->withInput();
        }

        return back()->with('status', 'Refused, with the reason recorded.');
    }

    public function close(int $request): RedirectResponse
    {
        $model = $this->find($request);

        try {
            $this->requests->close($model, $this->actor());
        } catch (RuntimeException $e) {
            return back()->withErrors(['form' => $e->getMessage()]);
        }

        return back()->with('status', 'Closed. Reopen by raising a new request.');
    }

    public function note(Request $httpRequest, int $request): RedirectResponse
    {
        $model = $this->find($request);

        $validated = $httpRequest->validate([
            'subject_assertion' => ['required', 'string', 'max:4000'],
            'outcome' => ['required', 'string', 'in:noted,applied,refused'],
            'outcome_reason' => ['required', 'string', 'max:2000'],
            'audit_event_id' => ['nullable', 'integer'],
        ]);

        $this->notes->record(
            $model,
            $this->actor(),
            $validated['subject_assertion'],
            CorrectionOutcome::from($validated['outcome']),
            $validated['outcome_reason'],
            $validated['audit_event_id'] ?? null,
        );

        return back()->with('status', 'Correction note recorded. It cannot be edited or removed.');
    }

    /**
     * Resolve an id, or 404.
     *
     * Explicit rather than an implicit binding, for SEC-DEC-058's reason. This
     * also makes the organisation boundary deliberate rather than inherited:
     * the service applies the global scope, so another organisation's id
     * resolves to nothing and 404s.
     */
    private function find(int $id): PrivacyRequest
    {
        $model = $this->requests->find($id);

        abort_if($model === null, 404);

        return $model;
    }

    private function actor(): User
    {
        /** @var User $actor */
        $actor = Auth::user();

        return $actor;
    }
}
