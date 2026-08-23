<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Identity\Enums\ReviewDecision;
use App\Modules\Identity\Models\AccessReview;
use App\Modules\Identity\Models\AccessReviewItem;
use App\Modules\Identity\Services\AccessReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Access reviews. Feature ADM-008.
 *
 * SERVER-RENDERED, WHICH DEVIATES FROM PLAN DECISION D4, and the reason is
 * worth stating because D4 was approved.
 *
 * D4 chose React for this screen, to hold multi-item decision state across a
 * long review session before one submit. Building it out, that turns out to be
 * the wrong shape for this particular screen: every decision here is an
 * AUDITABLE EVENT, and batching a session's worth of them into one submit means
 * the trail records who pressed the button rather than who decided what, and a
 * browser closed mid-review loses every decision already made.
 *
 * So each decision posts on its own and is audited as it is made. That is
 * better evidence and it is more robust, and it does not introduce a second
 * runtime in the same change as the authorization core. React remains right for
 * the Connection Test Centre in gate 5, where the state genuinely is transient.
 * Recorded in the plan for review.
 */
class AccessReviewController extends Controller
{
    public function __construct(
        private readonly AccessReviewService $reviews,
    ) {}

    public function index(): View
    {
        $reviews = AccessReview::query()
            ->withCount('items')
            ->with('openedBy:id,name')
            ->orderByDesc('created_at')
            ->get();

        return view('pages.admin.access-reviews', ['reviews' => $reviews]);
    }

    public function show(AccessReview $accessReview): View
    {
        $this->assertInScope($accessReview);

        return view('pages.admin.access-review', [
            'review' => $accessReview,
            'items' => $accessReview->items()
                ->with('user:id,name,email,status')
                ->orderBy('user_id')
                ->orderBy('subject_type')
                ->get(),
            'decisions' => [ReviewDecision::Keep, ReviewDecision::Revoke],
            'undecided' => $accessReview->undecidedCount(),
            'pendingRevocations' => $accessReview->pendingRevocationCount(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'description' => ['nullable', 'string', 'max:500'],
            'due_at' => ['nullable', 'date'],
        ]);

        /** @var User $actor */
        $actor = Auth::user();

        $review = $this->reviews->create(
            $validated['name'],
            $validated['description'] ?? null,
            $validated['due_at'] ?? null,
            $actor,
        );

        return redirect()
            ->route('admin.access-reviews.show', $review)
            ->with('status', 'Review created as a draft. Open it to take the snapshot.');
    }

    /**
     * Take the snapshot and start collecting decisions.
     */
    public function open(AccessReview $accessReview): RedirectResponse
    {
        $this->assertInScope($accessReview);

        /** @var User $actor */
        $actor = Auth::user();

        try {
            $this->reviews->open($accessReview, $actor);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['review' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.access-reviews.show', $accessReview)
            ->with('status', 'Review opened. The access it lists is a snapshot taken just now.');
    }

    /**
     * Record one decision.
     */
    public function decide(Request $request, AccessReview $accessReview, AccessReviewItem $item): RedirectResponse
    {
        $this->assertInScope($accessReview);

        /* The item must belong to the review in the URL. Two bound models mean
         * two ids, and nothing but this check stops a crafted request pairing
         * an item with a review it is not part of. */
        abort_unless($item->access_review_id === $accessReview->getKey(), 404);

        $validated = $request->validate([
            'decision' => ['required', 'string'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $decision = ReviewDecision::tryFrom($validated['decision']);

        if ($decision === null) {
            return back()->withErrors(['review' => 'That is not a decision this application recognises.']);
        }

        /** @var User $actor */
        $actor = Auth::user();

        try {
            $this->reviews->decide($item, $decision, $validated['note'] ?? null, $actor);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['review' => $exception->getMessage()]);
        }

        return back()->with('status', 'Decision recorded.');
    }

    public function complete(AccessReview $accessReview): RedirectResponse
    {
        $this->assertInScope($accessReview);

        /** @var User $actor */
        $actor = Auth::user();

        try {
            $this->reviews->complete($accessReview, $actor);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['review' => $exception->getMessage()]);
        }

        return back()->with('status', 'Review completed. Apply it to carry out the revocations.');
    }

    /**
     * Carry out the revocations the review decided on.
     */
    public function apply(AccessReview $accessReview): RedirectResponse
    {
        $this->assertInScope($accessReview);

        /** @var User $actor */
        $actor = Auth::user();

        try {
            $applied = $this->reviews->apply($accessReview, $actor);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['review' => $exception->getMessage()]);
        }

        return back()->with('status', $applied === 0
            ? 'Nothing left to revoke.'
            : $applied.' grant'.($applied === 1 ? '' : 's').' revoked.');
    }

    public function cancel(Request $request, AccessReview $accessReview): RedirectResponse
    {
        $this->assertInScope($accessReview);

        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:200']]);

        /** @var User $actor */
        $actor = Auth::user();

        try {
            $this->reviews->cancel($accessReview, $actor, $validated['reason'] ?? null);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['review' => $exception->getMessage()]);
        }

        return redirect()->route('admin.access-reviews')->with('status', 'Review cancelled.');
    }

    /**
     * Refuse a review belonging to another organisation.
     *
     * `AccessReview` carries the global organisation scope, so route-model
     * binding already 404s on a foreign id. This is the belt to that braces:
     * the check is cheap and the failure it guards against is another
     * customer's access evidence.
     */
    private function assertInScope(AccessReview $review): void
    {
        if (! AccessReview::query()->whereKey($review->getKey())->exists()) {
            throw new NotFoundHttpException;
        }
    }
}
