<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Audit\Support\AuditLogger;
use App\Modules\Audit\Support\AuditLogQuery;
use App\Modules\Governance\Services\DataProtectionProfiles;
use App\Modules\Governance\Services\PersonalDataCatalogue;
use App\Modules\Governance\Services\RetentionPolicies;
use App\Modules\Governance\Services\SovereigntyExceptions;
use App\Modules\Governance\Services\SovereigntyProfiles;
use App\Modules\Governance\Support\GovernanceStorage;
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
use App\Modules\Security\Support\AuthenticationGuard;
use App\Modules\Security\Support\Reauthentication;
use App\Modules\Security\Support\SecurityCapabilities;
use App\Modules\Security\Support\SecurityPolicies;
use App\Modules\Security\Support\SecurityStorage;
use App\Modules\Security\Support\SessionRegistry;
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

        /*
         * Release 1 gate 3.
         *
         * SINGLETONS FOR A REASON THAT IS NOT ONLY TIDINESS. `SecurityStorage`
         * answers "do the gate 3 tables exist yet" with a schema query, and
         * `SecurityHeaders`, `EnforceSessionPolicy`, `ConfirmIdentity` and the
         * controllers all need the answer on the same request. Registered as a
         * singleton it costs ONE query per request; auto-wired fresh each time
         * it would cost one per consumer, on every response the application
         * sends.
         *
         * `SecurityPolicies` memoises resolved values for the same reason
         * `SystemSettings` does: policy is read many times per request and
         * changes rarely, and two instances could answer the same question
         * differently after a write.
         */
        $this->app->singleton(SecurityStorage::class);
        $this->app->singleton(SecurityCapabilities::class);
        $this->app->singleton(SecurityPolicies::class);
        $this->app->singleton(AuthenticationGuard::class);
        $this->app->singleton(Reauthentication::class);
        $this->app->singleton(SessionRegistry::class);

        /*
         * Release 1 gate 4, batch R1.4a.
         *
         * `GovernanceStorage` is a singleton for the same reason
         * `SecurityStorage` is: three screens and one middleware ask it whether
         * the gate 4 tables exist, and without a singleton each would pay its
         * own schema query on every request.
         *
         * The three services are singletons because each memoises nothing yet
         * but reads the same profile repeatedly within one request - the
         * controller for the form, the view for the badge, and the gap list for
         * the warning. Two instances could answer the same question differently
         * after a write, which for a versioned profile would mean a screen
         * showing a draft it had just superseded.
         */
        $this->app->singleton(GovernanceStorage::class);
        $this->app->singleton(DataProtectionProfiles::class);
        $this->app->singleton(SovereigntyProfiles::class);
        $this->app->singleton(PersonalDataCatalogue::class);

        /*
         * Gate 4 batch R1.4b. Singletons for the same reason as R1.4a's: each
         * is read repeatedly within one request - the screen, the counts and
         * the warnings all ask - and two instances could answer the same
         * question differently after a write.
         *
         * `AuditLogQuery` is a singleton because it resolves the network
         * permission, and the controller asks it twice: once to build the
         * column list and once to tell the view whether to render the column.
         * Two instances could disagree, and the disagreement would be a column
         * header over data that was never selected.
         */
        $this->app->singleton(SovereigntyExceptions::class);
        $this->app->singleton(RetentionPolicies::class);
        $this->app->singleton(AuditLogQuery::class);
    }

    public function boot(): void
    {
        //
    }
}
