<?php

declare(strict_types=1);

namespace App\Modules\Platform\Identity;

use App\Modules\Access\StepUp\StepUpVerification;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * The identity-provider boundary required by SYS-011.
 *
 * Release 1 has exactly one implementation, Microsoft Entra ID. The boundary
 * exists so a later approved provider can be added without changing the
 * application's authentication contract - not so that a generic identity
 * framework can grow here. D-13 is explicit about that scope.
 */
interface IdentityProvider
{
    public function key(): string;

    /**
     * False when the provider has no configuration. The Login page offers only
     * configured providers, per blueprint 0.2.
     */
    public function isConfigured(): bool;

    public function beginAuthorization(): RedirectResponse;

    /**
     * D-73 step-up. The SAME provider, the same protocol validation, and two
     * additions: prompt=login (with max_age=0 where the provider honours it) so
     * the IDENTITY PROVIDER performs the re-authentication, and a DISTINCT
     * state store and callback so an ordinary sign-in can never be mistaken for
     * a step-up or the reverse.
     *
     * SemantIQ never handles a credential here. There is no password screen and
     * no dialog pretending to be step-up.
     */
    public function beginStepUpAuthorization(string $returnUri): RedirectResponse;

    /**
     * Completes a step-up return.
     *
     * Passes every validation an ordinary sign-in passes - issuer, tenant,
     * state, nonce, required claims - and ADDS the provider's auth_time. Step-up
     * may only add a check; it may never relax one.
     *
     * @throws AuthenticationFailed on any failure an ordinary sign-in would fail on.
     */
    public function completeStepUpAuthorization(Request $request): StepUpVerification;

    /**
     * @throws AuthenticationFailed on any protocol, signature, issuer,
     *                              audience, nonce, tenant or claim failure.
     */
    public function completeAuthorization(Request $request): VerifiedIdentity;
}
