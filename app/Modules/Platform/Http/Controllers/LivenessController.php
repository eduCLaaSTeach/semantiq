<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers;

use App\Modules\Platform\Health\HealthInspector;
use Illuminate\Http\Response;

/**
 * GET /up - public liveness.
 *
 * Deliberately minimal. The body is one of exactly two words, asserted against
 * an allowlist in the test suite rather than screened with a secret denylist: an
 * allowlist cannot be outgrown by a later edit that adds "just one more field",
 * which is how these endpoints usually start leaking versions and hostnames.
 *
 * It returns 503 rather than 200 when a dependency is down, so a monitor sees
 * the outage. The richer diagnostic is SSH-only (semantiq:health).
 */
final class LivenessController
{
    public function __invoke(HealthInspector $inspector): Response
    {
        $healthy = $inspector->inspect()->isHealthy();

        return new Response(
            $healthy ? 'ok' : 'unhealthy',
            $healthy ? 200 : 503,
            ['Content-Type' => 'text/plain; charset=utf-8'],
        );
    }
}
