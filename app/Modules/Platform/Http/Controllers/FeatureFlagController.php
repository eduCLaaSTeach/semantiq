<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Platform\Support\FeatureFlags;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * The Feature Flags screen. Feature ADM-021, MENU_STRUCTURE.md section 12.15.
 *
 * A flag decides whether a capability is AVAILABLE, never who may use it. That
 * is worth restating on the screen itself as well as here, because a switch
 * labelled "sign-in" invites the reading that it grants or denies access, and
 * it does not: the tier, the permission and the domain entitlement do.
 *
 * Only declared flags can be toggled. The key is validated against the
 * catalogue before anything else happens, so a crafted post cannot create a
 * flag row for a key nobody declared - a row which would then be read by
 * whatever undeclared name it was given.
 */
class FeatureFlagController extends Controller
{
    public function index(FeatureFlags $flags): View
    {
        return view('pages.admin.feature-flags', [
            'flags' => $flags->all(),
        ]);
    }

    public function update(Request $request, FeatureFlags $flags, string $key): RedirectResponse
    {
        /*
         * The key is a route segment, so it is checked against the catalogue
         * before the request body is even looked at.
         */
        if ($flags->declaration($key) === null) {
            return back()->withErrors(['flag' => 'That feature flag does not exist.']);
        }

        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            /* A reason is optional but bounded: it is stored and displayed. */
            'reason' => ['nullable', 'string', 'max:200'],
        ]);

        /** @var User $actor */
        $actor = Auth::user();

        try {
            $changed = $flags->set($key, (bool) $validated['enabled'], $actor, $validated['reason'] ?? null);
        } catch (InvalidArgumentException $exception) {
            /*
             * A refused change, not a fault: the actor lacks the tier, or the
             * catalogue's precondition does not hold - turning off local
             * sign-in with no working Microsoft sign-in, for instance. The
             * denial is already in the audit trail.
             */
            return back()->withErrors(['flag' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.system.feature-flags')
            ->with('status', $changed
                ? 'Feature flag updated.'
                : 'That flag was already in the state you asked for.');
    }
}
