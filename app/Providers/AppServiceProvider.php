<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Audit\Support\AuditLogger;
use App\Modules\Identity\Support\OrganisationContext;
use App\Modules\Platform\Support\FeatureFlags;
use App\Modules\Platform\Support\HealthProbe;
use App\Modules\Platform\Support\SystemSettings;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * All five are singletons, and for each the singleton is a correctness
     * requirement rather than a performance one.
     *
     * `OrganisationContext` must give ONE answer per request. Two instances
     * could disagree about whose data is being read halfway through a
     * transaction, which is the exact failure the class exists to prevent.
     *
     * `SystemSettings` and `FeatureFlags` memoise. A second instance would
     * hold a stale copy and would not be invalidated when the first one wrote,
     * so a screen could show the value it just replaced.
     *
     * `AuditLogger` and `HealthProbe` are stateless, and are registered here so
     * that the organisation context and the flags they depend on are the same
     * instances everything else is using.
     */
    public function register(): void
    {
        $this->app->singleton(OrganisationContext::class);
        $this->app->singleton(AuditLogger::class);
        $this->app->singleton(SystemSettings::class);
        $this->app->singleton(FeatureFlags::class);
        $this->app->singleton(HealthProbe::class);
    }

    public function boot(): void
    {
        //
    }
}
