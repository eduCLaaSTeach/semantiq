<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response as InertiaResponse;

/**
 * Federated sign-in against Microsoft Entra ID.
 *
 * SemantIQ holds no local credential. Identity is delegated entirely to Entra
 * ID, so this controller only ever starts the federated flow or explains why it
 * cannot start.
 *
 * The token exchange itself is deliberately NOT implemented in this change. The
 * provider library is an unresolved decision, and shipping a half-built
 * authorization-code handler into the live path would be worse than shipping
 * none: it is the one code path where a partial implementation is a security
 * problem rather than a missing feature. The callback route is therefore not
 * registered at all, per the integration-safety rule in
 * .claude/rules/production-readiness.md.
 */
class MicrosoftSignInController extends Controller
{
    /**
     * Render the sign-in screen.
     *
     * Reads the one-shot status message left by a previous attempt, if any, so a
     * failed sign-in explains itself on the page the user lands back on.
     */
    public function create(Request $request): InertiaResponse
    {
        return inertia('auth/sign-in', [
            'status' => $request->session()->get('status'),
            'supportEmail' => config('services.microsoft.support_email'),
        ]);
    }

    /**
     * Begin the federated sign-in.
     *
     * Posted rather than linked so the request carries a CSRF token; a GET entry
     * point would let a third party initiate sign-in on a user's behalf.
     *
     * Fails closed: with the tenant or client identifier absent, the user is
     * returned to the sign-in screen with an explanation rather than being sent
     * to a malformed Microsoft URL that would fail confusingly at their end.
     */
    public function redirect(): RedirectResponse
    {
        if (! $this->isConfigured()) {
            return back()->with('status', [
                'level' => 'warning',
                'title' => 'Microsoft sign-in is not configured yet',
                'body' => 'This environment has no Entra ID application registered against it, '
                    .'so sign-in cannot start. An administrator needs to add the tenant and '
                    .'client identifiers to the server configuration.',
            ]);
        }

        // Reached only once the provider decision is made and the authorization-code
        // exchange lands in its own change. Until then this branch is unreachable,
        // which is why the callback route does not exist.
        return back()->with('status', [
            'level' => 'info',
            'title' => 'Sign-in is not available yet',
            'body' => 'The Entra ID application is configured, but the sign-in exchange has not '
                .'been released to this environment yet.',
        ]);
    }

    /**
     * Whether this environment has enough Entra ID configuration to start a sign-in.
     *
     * Presence only. No secret is read, logged, or reported here, and the client
     * secret is never consulted to answer this question.
     */
    private function isConfigured(): bool
    {
        return filled(config('services.microsoft.tenant_id'))
            && filled(config('services.microsoft.client_id'));
    }
}
