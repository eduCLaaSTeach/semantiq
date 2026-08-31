<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers;

use App\Modules\Platform\Identity\IdentityProvider;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Login page.
 *
 * The blueprint requires that an unauthenticated browser receives no protected
 * shell, menu or business metadata. This page therefore carries none: brand,
 * product name, and one action.
 *
 * The provider is offered only when it is configured, per blueprint 0.2. Note
 * what is passed to the browser - a boolean, not the client id, not the tenant,
 * and certainly not the secret. The view has no way to render what it never
 * receives.
 */
final class EntryController
{
    public function __construct(private readonly IdentityProvider $provider) {}

    public function __invoke(): Response
    {
        return Inertia::render('Entry', [
            'microsoftEnabled' => $this->provider->isConfigured(),
        ]);
    }
}
