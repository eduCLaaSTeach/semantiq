<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Providers;

use App\Modules\Access\StepUp\StepUpCompletionRegistry;
use App\Modules\Reviews\StepUp\ReviewStepUpCompletion;
use Illuminate\Support\ServiceProvider;

/**
 * P1-07 registers ITSELF with P1-05. P1-05 names nothing.
 *
 * This is the whole of the dependency direction: Reviews knows about Access,
 * Access knows nothing about Reviews, and the one line that joins them lives
 * here rather than in an accepted file.
 */
final class ReviewsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(StepUpCompletionRegistry::class);
    }

    public function boot(): void
    {
        $this->app->make(StepUpCompletionRegistry::class)
            ->register($this->app->make(ReviewStepUpCompletion::class));
    }
}
