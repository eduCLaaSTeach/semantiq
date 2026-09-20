<?php

declare(strict_types=1);

namespace App\Modules\Identity\Providers;

use App\Modules\Access\StepUp\StepUpCompletionRegistry;
use App\Modules\Identity\StepUp\IdentityReconfigurationCompletion;
use Illuminate\Support\ServiceProvider;

/**
 * P1-02's own provider, added at Gate C round 3.
 *
 * WHY THE MODULE GAINS ONE NOW. Until this round P1-02 was read-only and had
 * nothing to register. Changing Microsoft sign-in after installation needs a
 * step-up completion, and StepUpCompletionRegistry's whole design is that
 * "a unit registers its completion in its own service provider" - so that
 * P1-05 never learns what any later unit's privileged action means, and adding
 * a unit adds no line to any accepted P1-05 file.
 *
 * Registering it in PlatformServiceProvider instead would have worked and would
 * have been the shortcut that makes that sentence untrue: Platform would be
 * wiring up Identity's behaviour, and the next module would follow the example.
 */
final class IdentityServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->make(StepUpCompletionRegistry::class)
            ->register($this->app->make(IdentityReconfigurationCompletion::class));
    }
}
