<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Platform\Security\SecurityEventLogger;
use Illuminate\Support\Facades\DB;

/**
 * Emit an event the way the application emits one.
 *
 * PRODUCTION NEVER RECORDS A STATE CHANGE OUTSIDE A TRANSACTION - D-111, and
 * AuditWriter refuses to. A test that called record() bare would be exercising
 * a shape the application does not have, and would have to be exempted from the
 * guard to work at all. Exempting the tests from the guard is how the guard
 * stops guarding anything.
 */
trait EmitsEvidence
{
    /** @param  array<string, scalar|null>  $context */
    protected function emit(string $event, array $context = []): void
    {
        DB::transaction(fn () => app(SecurityEventLogger::class)->record($event, $context));
    }
}
