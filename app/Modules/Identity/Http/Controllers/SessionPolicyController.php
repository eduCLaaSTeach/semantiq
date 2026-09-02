<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Support\SessionPolicy;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Session Policy - read only, D-26.
 *
 * Every value here is the ENFORCED one, read from whatever enforces it. Not one
 * number is written into this screen or into the page it renders, because that
 * is precisely how the policy and the running system came to disagree in the
 * first place: a constant declaring 60 that nothing read, beside a configuration
 * enforcing 120 that nothing checked.
 */
final class SessionPolicyController
{
    public function __construct(private readonly SessionPolicy $policy) {}

    public function show(): Response
    {
        return Inertia::render('Identity/SessionPolicy', [
            'policy' => [
                'idleMinutes' => $this->policy->idleMinutes(),
                'absoluteHours' => $this->policy->absoluteHours(),
                'revalidatesEveryRequest' => $this->policy->revalidatesEveryRequest(),
                'storage' => $this->policy->driverInWords(),
            ],
        ]);
    }
}
