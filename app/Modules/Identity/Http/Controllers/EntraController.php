<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Health\IdentityHealthCheck;
use App\Modules\Identity\Support\IdentityConfigurationReport;
use App\Modules\Platform\Identity\IdentityProvider;
use App\Modules\Platform\Setup\Identity\IdentityConfigurationSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Microsoft Entra ID - read only.
 *
 * Every value comes from the safe read model. The page payload carries the
 * MASKED identifiers and never the full ones, which is what makes the mask real
 * rather than cosmetic: a CSS mask over a value already in the props would be
 * in the page source of every screenshot-adjacent artefact.
 */
final class EntraController
{
    public function __construct(
        private readonly IdentityProvider $provider,
        private readonly IdentityHealthCheck $health,
        private readonly IdentityConfigurationSource $identityConfiguration,
    ) {}

    public function show(): Response
    {
        $report = $this->health->report();

        return Inertia::render('Identity/Entra', [
            'configuration' => IdentityConfigurationReport::build($this->provider, $this->identityConfiguration)->toArray(),
            'healthSummary' => [
                'state' => $report->state(),
                'stateInWords' => $report->stateInWords(),
            ],
        ]);
    }

    /**
     * Reveal ONE identifier, on an explicit action - D-27.
     *
     * POST rather than GET, and therefore CSRF-protected, for the same reason
     * auth.logout is POST: a GET that returns a value is triggerable by any
     * third-party page the administrator happens to be visiting.
     *
     * Exactly two field names are accepted. There is no name that would return
     * the client secret, and no code path that could: the secret never becomes a
     * string in this module.
     */
    public function reveal(Request $request): JsonResponse
    {
        $field = $request->input('field');

        // The RESOLVED source, so a reveal cannot show a directory identifier
        // that sign-in is not using. Revealing .env's value on a store-backed
        // deployment would be an administrator reading, and then trusting, an
        // identifier no longer in play.
        $identity = $this->identityConfiguration->resolve();

        $value = match ($field) {
            'directory' => $identity->tenantId,
            'application' => $identity->clientId,
            default => null,
        };

        if ($value === null) {
            // Names nothing about which fields exist.
            return response()->json(['message' => 'That cannot be revealed.'], 422);
        }

        return response()->json(['value' => $value]);
    }
}
