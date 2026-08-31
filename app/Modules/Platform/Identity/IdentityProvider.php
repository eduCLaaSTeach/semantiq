<?php

declare(strict_types=1);

namespace App\Modules\Platform\Identity;

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
     * @throws AuthenticationFailed on any protocol, signature, issuer,
     *                              audience, nonce, tenant or claim failure.
     */
    public function completeAuthorization(Request $request): VerifiedIdentity;
}
