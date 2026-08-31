<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\FirstRun;

use App\Modules\Platform\Bootstrap\BootstrapState;
use App\Modules\Platform\Models\BootstrapGrant;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * The first-run entry.
 *
 * NOT mounted at /bootstrap: that is one of the directories the Apache boundary
 * refuses, so a route there would 403 in production while passing every local
 * test - the same failure that moved the authenticated area off /app.
 *
 * Opening this grants nothing. It records intent and offers Sign in with
 * Microsoft; the administrator is created only after Entra verifies the
 * identity and the grant matches it.
 *
 * Every failure - unknown, expired, already consumed, system already
 * configured - lands on the same closed state. Distinguishing them would make
 * this endpoint probeable.
 */
final class BeginController
{
    public const SESSION_GRANT = 'bootstrap.grant';

    public function __construct(private readonly BootstrapState $state) {}

    public function __invoke(Request $request, string $grant): Response
    {
        if ($this->state->isConfigured() || ! $this->grantIsRedeemable($grant)) {
            return redirect()->route('first_run.closed');
        }

        $request->session()->put(self::SESSION_GRANT, $grant);

        return Inertia::render('Auth/FirstRun')->toResponse($request);
    }

    private function grantIsRedeemable(string $grant): bool
    {
        return BootstrapGrant::query()
            ->where('token_hash', BootstrapGrant::hashFor($grant))
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->exists();
    }
}
