<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Audit\Support\AuditLogger;
use App\Modules\Identity\Policies\SystemAdministratorGuard;
use App\Modules\Identity\Services\AccessReviewService;
use App\Modules\Identity\Services\RoleRegistry;
use App\Modules\Identity\Services\StructureRegistry;
use App\Modules\Identity\Services\UserRegistry;
use App\Modules\Identity\Support\Authorization;
use App\Modules\Identity\Support\OrganisationContext;
use App\Modules\Identity\Support\PermissionRegistry;
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

        /*
         * Release 1 gate 2.
         *
         * `PermissionRegistry` and `Authorization` are singletons because both
         * memoise, and a second instance would answer an authorization
         * question from a stale set - a screen showing access that the check
         * would refuse, or worse, the other way round.
         *
         * The services and the guard are registered so they resolve the same
         * organisation context, authorization and audit logger as everything
         * else. A second audit logger would write to the same table but with a
         * different correlation id.
         */
        $this->app->singleton(PermissionRegistry::class);
        $this->app->singleton(Authorization::class);
        $this->app->singleton(SystemAdministratorGuard::class);
        $this->app->singleton(UserRegistry::class);
        $this->app->singleton(RoleRegistry::class);
        $this->app->singleton(StructureRegistry::class);
        $this->app->singleton(AccessReviewService::class);
    }

    public function boot(): void
    {
        //
    }
}
