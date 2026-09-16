<?php

declare(strict_types=1);

namespace App\Modules\Security\Providers;

use App\Modules\Security\Posture\Adapters\AdministratorAdapter;
use App\Modules\Security\Posture\Adapters\CarriedGateAdapter;
use App\Modules\Security\Posture\Adapters\DomainAdapter;
use App\Modules\Security\Posture\Adapters\EngineGateAdapter;
use App\Modules\Security\Posture\Adapters\GrantPathAdapter;
use App\Modules\Security\Posture\Adapters\HostingAdapter;
use App\Modules\Security\Posture\Adapters\IdentityAdapter;
use App\Modules\Security\Posture\Adapters\RouteCoverageAdapter;
use App\Modules\Security\Posture\Adapters\SessionAdapter;
use App\Modules\Security\Posture\Adapters\StepUpAdapter;
use App\Modules\Security\Posture\PostureEvaluator;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the ONE evaluator to its adapters.
 *
 * The adapter list lives here rather than inside PostureEvaluator so that a
 * test can substitute a failing or fixed adapter without touching the
 * evaluator - which is what makes "a throwing source yields Unverified and the
 * row still renders" testable rather than asserted.
 *
 * NOT a singleton: posture is recomputed from current authoritative source
 * state on every request (D-81), and holding the evaluator would be the first
 * step towards holding its answer.
 *
 * P1-06 IS NOT A NAVIGATION PROVIDER. Its menu node is already in ApprovedMenu
 * and simply stops being locked; there is no second registry.
 */
final class SecurityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PostureEvaluator::class, static function (Container $app): PostureEvaluator {
            /** @var DomainAdapter $domains */
            $domains = $app->make(DomainAdapter::class);

            return new PostureEvaluator(
                $domains,
                $app->make(IdentityAdapter::class),
                $app->make(SessionAdapter::class),
                $app->make(RouteCoverageAdapter::class),
                $app->make(EngineGateAdapter::class),
                $app->make(AdministratorAdapter::class),
                $app->make(StepUpAdapter::class),
                $app->make(GrantPathAdapter::class),
                $app->make(HostingAdapter::class),
                $domains,
                $app->make(CarriedGateAdapter::class),
            );
        });
    }
}
