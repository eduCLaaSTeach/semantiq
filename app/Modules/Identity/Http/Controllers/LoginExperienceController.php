<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Support\ApprovedProviders;
use App\Modules\Platform\Identity\IdentityProvider;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Login Experience - a read-only ownership map, and it says so on the screen.
 *
 * D-29 declined making the support-contact line configurable rather than
 * inventing a setting to justify a tab. So this screen carries no form, no field
 * and no save button: a read-only screen with a disabled Save is worse than one
 * with none, because it implies a capability that does not exist.
 *
 * Its value is that the boundary is currently invisible. An administrator asking
 * "why does the Login page say that" has nowhere to look, and now does.
 */
final class LoginExperienceController
{
    public function __construct(private readonly IdentityProvider $provider) {}

    public function show(): Response
    {
        return Inertia::render('Identity/LoginExperience', [
            'signInOffered' => $this->provider->isConfigured(),
            'providerName' => ApprovedProviders::nameFor($this->provider->key()) ?? 'Unknown provider',
        ]);
    }
}
