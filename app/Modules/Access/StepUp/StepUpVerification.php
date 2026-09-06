<?php

declare(strict_types=1);

namespace App\Modules\Access\StepUp;

use App\Modules\Platform\Identity\VerifiedIdentity;
use Illuminate\Support\Carbon;

/**
 * What a step-up return carries back: the verified identity AND the provider's
 * auth_time claim.
 *
 * auth_time is nullable because a provider may omit it. An absent claim where
 * one is required FAILS CLOSED - it is not treated as "probably fine", because
 * the whole point of reading it is that this application does not get to decide
 * when somebody last authenticated.
 */
final class StepUpVerification
{
    public function __construct(
        public readonly VerifiedIdentity $identity,
        public readonly ?Carbon $authenticatedAt,
    ) {}
}
