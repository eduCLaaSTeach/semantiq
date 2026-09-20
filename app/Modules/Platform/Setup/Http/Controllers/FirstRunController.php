<?php

declare(strict_types=1);

namespace App\Modules\Platform\Setup\Http\Controllers;

use App\Modules\Platform\Bootstrap\BootstrapState;
use App\Modules\Platform\Setup\Bootstrap\BootstrapAccess;
use App\Modules\Platform\Setup\Bootstrap\BootstrapAuthenticator;
use App\Modules\Platform\Setup\Bootstrap\BootstrapRecovery;
use App\Modules\Platform\Setup\Bootstrap\FirstAdministratorHandoff;
use App\Modules\Platform\Setup\Identity\IdentityCutover;
use App\Modules\Platform\Setup\IntegrationFamily;
use App\Modules\Platform\Setup\Support\SetupProjection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The First-Run surface.
 *
 * DELIBERATELY NARROWER CHROME THAN THE CONSOLE - no sidebar, no product
 * areas. A setup surface that looks like the console invites the assumption
 * that the console is reachable from it, and then invites somebody to make
 * that true.
 *
 * EVERY WRITE GOES THROUGH THE OWNING SERVICE. Identity through P1-02's
 * writer, the handoff through P1-00's GrantIssuer, the cutover through
 * IdentityCutover. This controller renders and validates; it decides nothing
 * about identity or access.
 */
final class FirstRunController
{
    public function __construct(
        private readonly BootstrapAccess $access,
        private readonly BootstrapAuthenticator $authenticator,
        private readonly BootstrapState $state,
        private readonly SetupProjection $projection,
    ) {}

    /** Step 1. The local sign-in screen. */
    public function signInForm(Request $request): Response|RedirectResponse
    {
        if ($request->session()->has(BootstrapAuthenticator::SESSION_KEY) && $this->access->isOpen($request)) {
            return redirect()->route('first_run.overview');
        }

        if (! $this->access->isOpen($request)) {
            return redirect()->route('first_run.closed');
        }

        return Inertia::render('FirstRun/SignIn')->toResponse($request);
    }

    public function signIn(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $principal = $this->authenticator->attempt($request, $data['email'], $data['password']);

        if ($principal === null) {
            /*
             * ONE MESSAGE FOR EVERY REFUSAL. An unknown address, a wrong
             * password and a closed bootstrap are indistinguishable, so this
             * screen cannot be used to ask whether a deployment has an
             * administrator yet - which is the first thing worth knowing
             * about a target.
             */
            throw ValidationException::withMessages([
                'email' => 'Those sign-in details were not accepted.',
            ]);
        }

        return redirect()->route('first_run.overview');
    }

    public function signOut(Request $request): RedirectResponse
    {
        $this->authenticator->signOut($request);

        return redirect()->route('first_run.sign_in');
    }

    /** Step 2. What is done, what is left, and what is optional. */
    public function overview(Request $request): Response
    {
        return Inertia::render('FirstRun/Overview', [
            'steps' => $this->projection->steps(),
            'canNominate' => $this->projection->identityIsReady(),
            'isConfigured' => $this->state->isConfigured(),
        ])->toResponse($request);
    }

    /** Steps 3 to 6. One screen per family, same shape. */
    public function family(Request $request, string $family): Response|RedirectResponse
    {
        $resolved = IntegrationFamily::tryFrom($family);

        if ($resolved === null) {
            return redirect()->route('first_run.overview');
        }

        return Inertia::render('FirstRun/Integration', [
            'integration' => $this->projection->forFamily($resolved),
            'steps' => $this->projection->steps(),
        ])->toResponse($request);
    }

    /** Step 7. Nominate the first permanent System Administrator. */
    public function nominateForm(Request $request): Response
    {
        return Inertia::render('FirstRun/FirstAdministrator', [
            'steps' => $this->projection->steps(),
            'identityIsReady' => $this->projection->identityIsReady(),
            // THE LINK IS FLASHED, NEVER STORED. It survives exactly one
            // redirect and is gone.
            'handoffLink' => $request->session()->get('handoffLink'),
            'nominated' => $request->session()->get('nominated'),
        ])->toResponse($request);
    }

    public function nominate(
        Request $request,
        FirstAdministratorHandoff $handoff,
        IdentityCutover $cutover,
    ): RedirectResponse {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        /*
         * CORRECTION 4, AT THE MOMENT IT MATTERS.
         *
         * A fresh installation is still reading the (empty) .env at this point.
         * Issuing the grant without moving the authority would produce a link
         * that cannot work: the nominated administrator would open it, reach
         * /auth/microsoft, and find nothing configured.
         *
         * The move is verified-first and atomic, and it is a no-op on a
         * deployment already reading the store.
         */
        $committed = $cutover->commitFreshInstallation();

        if (! $this->projection->identityIsReady()) {
            throw ValidationException::withMessages([
                'email' => $committed['explanation'],
            ]);
        }

        try {
            $link = $handoff->nominate($data['email']);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['email' => $e->getMessage()]);
        }

        return redirect()
            ->route('first_run.first_administrator')
            ->with('handoffLink', $link)
            ->with('nominated', $data['email']);
    }

    /** Step 9. */
    public function complete(Request $request): Response
    {
        return Inertia::render('FirstRun/Complete', [
            'isConfigured' => $this->state->isConfigured(),
        ])->toResponse($request);
    }

    /** Recovery, reachable without a bootstrap session by design. */
    public function recoverForm(Request $request): Response
    {
        return Inertia::render('FirstRun/Recover')->toResponse($request);
    }

    public function recover(Request $request, BootstrapRecovery $recovery): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:16', 'confirmed'],
        ]);

        if (! $recovery->redeem($request, $data['token'], $data['password'])) {
            // Unknown, expired and already-consumed are indistinguishable, for
            // the same reason the sign-in refusal is generic.
            throw ValidationException::withMessages([
                'token' => 'That recovery token was not accepted.',
            ]);
        }

        return redirect()->route('first_run.sign_in');
    }
}
