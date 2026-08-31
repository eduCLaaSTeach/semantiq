<?php

declare(strict_types=1);

namespace App\Modules\Platform\Bootstrap;

use App\Modules\Platform\Models\BootstrapGrant;
use App\Modules\Platform\Security\SecurityEventLogger;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Issues a single-use bootstrap grant. Called only by the Artisan command, so
 * it is reachable only by an operator with SSH - never as an application screen
 * or an HTTP endpoint.
 */
final class GrantIssuer
{
    public const TTL_MINUTES = 30;

    public function __construct(
        private readonly BootstrapState $state,
        private readonly SecurityEventLogger $events,
    ) {}

    /**
     * @return string the plaintext grant - returned once, to the caller, and
     *                never stored, logged or transmitted anywhere else
     */
    public function issue(string $expectedSubject, string $expectedTenant, ?string $issuedBy = null): string
    {
        if ($this->state->isConfigured()) {
            throw new RuntimeException(
                'A System Administrator already exists. Bootstrap is closed. It reopens only if '
                .'no active System Administrator remains.'
            );
        }

        $grant = Str::random(64);

        BootstrapGrant::query()->create([
            'token_hash' => BootstrapGrant::hashFor($grant),
            'expected_subject' => mb_strtolower(trim($expectedSubject)),
            'expected_tenant' => $expectedTenant,
            'issued_by' => $issuedBy,
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
        ]);

        $this->events->record(SecurityEventLogger::BOOTSTRAP_GRANT_ISSUED, [
            'subject' => mb_strtolower(trim($expectedSubject)),
            'tenant' => $expectedTenant,
            'expires_at' => now()->addMinutes(self::TTL_MINUTES)->toIso8601String(),
            'result' => 'issued',
        ]);

        return $grant;
    }
}
