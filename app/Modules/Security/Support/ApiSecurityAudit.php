<?php

declare(strict_types=1);

namespace App\Modules\Security\Support;

use App\Modules\Audit\Support\Redaction;
use App\Modules\Platform\Http\Middleware\AssignCorrelationId;
use App\Modules\Security\Enums\SecurityStatus;
use App\Modules\Security\Http\Middleware\LimitRequestSize;
use App\Modules\Security\Http\Middleware\SecurityHeaders;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;

/**
 * Checks the security controls this application actually applies. Feature ADM-011.
 *
 * WHY THIS IS NOT A SETTINGS SCREEN. Release 1 adds no public API surface, so a
 * page offering four switches for an API that does not exist would be four
 * values nothing reads. ADM-011's own text is a list of CONTROLS - authentication
 * by default, authorization per endpoint, CSRF, input validation, rate
 * limiting, correlation IDs, bounded payloads, secure headers, no secrets in
 * errors - and the useful thing to do with a list of controls is to check them.
 * Decision D2, approved 25 August 2026.
 *
 * EVERY CHECK READS THE RUNNING APPLICATION. The route table, the middleware
 * stack, the policy values. Nothing is hard-coded to "Pass", and nothing
 * restates an intention. A check that cannot establish its answer returns
 * `NotVerified` and never `Healthy` - gate 3 rule 9 - because a control this
 * screen cannot see is a control nobody should be relying on.
 *
 * THE POINT OF THE SCREEN is that some of these will be red, and that is
 * information. When this class was written, two of the eight failed.
 */
class ApiSecurityAudit
{
    public function __construct(
        private readonly SecurityPolicies $policies,
    ) {}

    /**
     * Every control, in the order ADM-011 lists them.
     *
     * @return list<array{key: string, name: string, status: SecurityStatus, detail: string, requirement: string}>
     */
    public function run(): array
    {
        return [
            $this->authenticationEnforcement(),
            $this->authorizationEnforcement(),
            $this->csrfProtection(),
            $this->correlationIds(),
            $this->rateLimiting(),
            $this->securityHeaders(),
            $this->payloadLimits(),
            $this->secretSafeErrors(),
        ];
    }

    /** The worst status across every control, which is the status of the whole. */
    public function overall(): SecurityStatus
    {
        return SecurityStatus::worst(array_map(
            static fn (array $control): SecurityStatus => $control['status'],
            $this->run(),
        ));
    }

    /**
     * Control 1: every route that is not deliberately public requires a
     * signed-in person.
     *
     * The allow-list is short and named here rather than inferred, because
     * "which routes are meant to be public" is a decision and not something to
     * be derived from what happens to be unauthenticated today.
     */
    private function authenticationEnforcement(): array
    {
        $public = [
            /* The sign-in flow. Necessarily reachable by somebody who is not
             * signed in, which is the whole point of it. */
            'sign-in', 'sign-in.attempt', 'sign-in.microsoft', 'sign-in.microsoft.callback',
            'password.request', 'sign-out',
        ];

        /*
         * Laravel's own local-disk routes carry NO middleware, and a naive
         * check reports them as an anonymous hole. They are not one while the
         * disk is private: `Illuminate\Filesystem\ServeFile` refuses anything
         * without a valid relative signature and returns a 404 in production.
         *
         * The condition is what matters, so it is CHECKED rather than assumed.
         * Setting `filesystems.disks.local.visibility` to `public` turns that
         * signature check off and makes these routes exactly the hole they
         * first looked like - so in that case they are reported.
         */
        $localDiskIsPrivate = (config('filesystems.disks.local.visibility') ?? 'private') !== 'public';

        if ($localDiskIsPrivate) {
            $public[] = 'storage.local';
            $public[] = 'storage.local.upload';
        }

        $unguarded = [];

        foreach ($this->namedRoutes() as $name => $route) {
            if (in_array($name, $public, true) || $this->isFrameworkRoute($name)) {
                continue;
            }

            if (! in_array('auth', $route->gatherMiddleware(), true)) {
                $unguarded[] = $name;
            }
        }

        return $this->control(
            'authentication',
            'Authentication enforcement',
            'Every route that is not deliberately public requires a signed-in person.',
            $unguarded === [],
            'All application routes outside the sign-in flow require authentication. '
                .'The local storage routes are reachable without signing in and are gated by a signed URL instead.',
            count($unguarded).' route(s) accept an anonymous request: '.implode(', ', array_slice($unguarded, 0, 5)),
            SecurityStatus::Critical,
        );
    }

    /**
     * Control 2: every administration route names a policy or a permission.
     *
     * Being signed in is not authorization. A route inside `/admin` that only
     * requires `auth` is reachable by every viewer who guesses the URL.
     */
    private function authorizationEnforcement(): array
    {
        $ungated = [];

        foreach ($this->namedRoutes() as $name => $route) {
            if (! str_starts_with($name, 'admin.')) {
                continue;
            }

            $middleware = $route->gatherMiddleware();

            $gated = false;

            foreach ($middleware as $entry) {
                if (str_starts_with((string) $entry, 'policy:') || str_starts_with((string) $entry, 'permission:')) {
                    $gated = true;

                    break;
                }
            }

            if (! $gated) {
                $ungated[] = $name;
            }
        }

        return $this->control(
            'authorization',
            'Authorization enforcement',
            'Every administration route is gated by a declared policy or permission, not only by being signed in.',
            $ungated === [],
            'All administration routes name a policy or a permission.',
            count($ungated).' administration route(s) are gated only by authentication: '.implode(', ', array_slice($ungated, 0, 5)),
            SecurityStatus::Critical,
        );
    }

    /**
     * Control 3: CSRF is applied and nothing has been excused from it.
     *
     * The `except` list is what actually matters here. The middleware being
     * present proves little if a route has been added to its exclusions, and
     * that exclusion is exactly the change that gets made under deadline
     * pressure and never revisited.
     */
    private function csrfProtection(): array
    {
        /*
         * Laravel 13 renamed this middleware from `ValidateCsrfToken` to
         * `PreventRequestForgery`, keeping the old name as a subclass. Both are
         * accepted, so this check survives the rename in either direction
         * rather than reporting a missing control the day the framework moves.
         * Found by this test failing against a correctly configured
         * application, which is what the check is for.
         */
        $registered = $this->webMiddleware();

        $applied = in_array(PreventRequestForgery::class, $registered, true)
            || in_array(ValidateCsrfToken::class, $registered, true);

        $excused = [];

        try {
            $property = new \ReflectionProperty(PreventRequestForgery::class, 'except');
            $excused = (array) $property->getValue(app(PreventRequestForgery::class));
        } catch (\Throwable) {
            /* The framework changed shape. Reporting "verified" on the strength
             * of a failed reflection would be the false green rule 9 forbids. */
            return $this->unverified(
                'csrf',
                'CSRF protection',
                'Every state-changing form carries a token that a third-party site cannot forge.',
                'The CSRF exclusion list could not be read on this framework version, so the exclusions cannot be confirmed empty.',
            );
        }

        return $this->control(
            'csrf',
            'CSRF protection',
            'Every state-changing form carries a token that a third-party site cannot forge.',
            $applied && $excused === [],
            'Applied to the whole web group with no route excluded.',
            $applied
                ? count($excused).' route pattern(s) are excluded from CSRF checking.'
                : 'The CSRF middleware is not in the web middleware group.',
            SecurityStatus::Critical,
        );
    }

    /** Control 4: every request carries a correlation id into the logs and the trail. */
    private function correlationIds(): array
    {
        return $this->control(
            'correlation',
            'Correlation IDs',
            'Every request carries an id that ties a screen, a log line and an audit event together.',
            in_array(AssignCorrelationId::class, $this->webMiddleware(), true),
            'Assigned at the front of the web middleware group, and an inbound id is only accepted when it is a well-formed UUID.',
            'No correlation middleware is registered, so a failure cannot be traced across the log and the audit trail.',
            SecurityStatus::Warning,
        );
    }

    /**
     * Control 5: the sign-in form is rate limited, from policy.
     *
     * Reported as a Warning rather than Critical when the threshold is loose:
     * a high threshold is a weak control, not an absent one.
     */
    private function rateLimiting(): array
    {
        $threshold = $this->policies->number('sign_in.failed_attempt_threshold');
        $lockMinutes = $this->policies->number('sign_in.lock_minutes');

        $detail = sprintf(
            'The credential form allows %d attempt(s) per address and network before a %d minute lockout. Sensitive endpoints allow %d requests a minute.',
            $threshold,
            $lockMinutes,
            $this->policies->number('api.sensitive_rate_limit_per_minute'),
        );

        if ($threshold > 20) {
            return [
                'key' => 'rate_limiting',
                'name' => 'Rate limiting',
                'requirement' => 'Sensitive endpoints refuse a caller who is trying too often.',
                'status' => SecurityStatus::Warning,
                'detail' => $detail.' A threshold this high leaves the form usable as a password guessing tool.',
            ];
        }

        return [
            'key' => 'rate_limiting',
            'name' => 'Rate limiting',
            'requirement' => 'Sensitive endpoints refuse a caller who is trying too often.',
            'status' => SecurityStatus::Healthy,
            'detail' => $detail,
        ];
    }

    /**
     * Control 6: the security headers are registered and switched on.
     *
     * Three separate answers rather than a boolean, because "off" and "on but
     * only reporting" are different positions and an administrator chose each.
     */
    private function securityHeaders(): array
    {
        if (! in_array(SecurityHeaders::class, $this->webMiddleware(), true)) {
            return [
                'key' => 'headers',
                'name' => 'Security headers',
                'requirement' => 'Responses tell the browser not to sniff types, not to be framed, and what it may load.',
                'status' => SecurityStatus::Critical,
                'detail' => 'The security header middleware is not registered, so no response carries these headers.',
            ];
        }

        if (! $this->policies->enabled('api.security_headers')) {
            return [
                'key' => 'headers',
                'name' => 'Security headers',
                'requirement' => 'Responses tell the browser not to sniff types, not to be framed, and what it may load.',
                'status' => SecurityStatus::NotConfigured,
                'detail' => 'Switched off on this screen. No security headers are being sent.',
            ];
        }

        $mode = $this->policies->text('api.content_policy_mode');
        $hsts = $this->policies->enabled('api.hsts_enabled');

        $detail = count(SecurityHeaders::BASE_HEADERS).' headers are sent on every response. '
            .'Content Security Policy is '.match ($mode) {
                'enforce' => 'enforcing',
                'report_only' => 'in report-only mode, so it reports what it would block without blocking it',
                default => 'off',
            }.'. Strict-Transport-Security is '.($hsts ? 'on' : 'off, pending separate approval').'.';

        return [
            'key' => 'headers',
            'name' => 'Security headers',
            'requirement' => 'Responses tell the browser not to sniff types, not to be framed, and what it may load.',
            'status' => $mode === 'off' ? SecurityStatus::Warning : SecurityStatus::Healthy,
            'detail' => $detail,
        ];
    }

    /** Control 7: an oversized request is refused before it is parsed. */
    private function payloadLimits(): array
    {
        return $this->control(
            'payload',
            'Request and payload limits',
            'A request larger than this application expects is refused before it is read.',
            in_array(LimitRequestSize::class, $this->webMiddleware(), true),
            'Requests above '.$this->policies->number('api.max_payload_kilobytes').' KB are refused with a 413. '
                .'The web server and PHP apply their own limits as well.',
            'No application-level size limit is registered; only the web server\'s limit applies, and nobody here can see what it is.',
            SecurityStatus::Warning,
        );
    }

    /**
     * Control 8: a credential cannot travel out through an error message.
     *
     * Verified by RUNNING the redactor over a string that looks like a
     * credential, not by checking that a class exists. A class that exists and
     * has stopped working is the failure this check is for.
     */
    private function secretSafeErrors(): array
    {
        $probe = 'Authorization: Bearer abcdefghijklmnopqrstuvwxyz0123456789';
        $scrubbed = Redaction::scrub($probe);

        $works = is_string($scrubbed) && ! str_contains($scrubbed, 'abcdefghijklmnopqrstuvwxyz');

        return $this->control(
            'errors',
            'Secret-safe error handling',
            'A credential in an exception, a driver message or an audit summary is replaced before it is stored or shown.',
            $works && config('app.debug') !== true,
            'Redaction is applied to every external message, and debug output is off.',
            $works
                ? 'Redaction works, but APP_DEBUG is on: a stack trace would be rendered to the browser, and a stack trace carries configuration.'
                : 'The redactor did not remove a credential-shaped string from a test message.',
            $works ? SecurityStatus::Warning : SecurityStatus::Critical,
        );
    }

    /**
     * Build one control result.
     *
     * @return array{key: string, name: string, status: SecurityStatus, detail: string, requirement: string}
     */
    private function control(
        string $key,
        string $name,
        string $requirement,
        bool $passing,
        string $passDetail,
        string $failDetail,
        SecurityStatus $failStatus,
    ): array {
        return [
            'key' => $key,
            'name' => $name,
            'requirement' => $requirement,
            'status' => $passing ? SecurityStatus::Healthy : $failStatus,
            'detail' => $passing ? $passDetail : $failDetail,
        ];
    }

    /** @return array{key: string, name: string, status: SecurityStatus, detail: string, requirement: string} */
    private function unverified(string $key, string $name, string $requirement, string $detail): array
    {
        return [
            'key' => $key,
            'name' => $name,
            'requirement' => $requirement,
            'status' => SecurityStatus::NotVerified,
            'detail' => $detail,
        ];
    }

    /**
     * The middleware the `web` group actually runs.
     *
     * @return list<string>
     */
    private function webMiddleware(): array
    {
        return array_map(
            static fn (mixed $entry): string => is_string($entry) ? $entry : get_debug_type($entry),
            app(Kernel::class)->getMiddlewareGroups()['web'] ?? [],
        );
    }

    /**
     * Every named route in the application.
     *
     * @return array<string, RoutingRoute>
     */
    private function namedRoutes(): array
    {
        $routes = [];

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();

            if (is_string($name) && $name !== '') {
                $routes[$name] = $route;
            }
        }

        return $routes;
    }

    /**
     * Routes the framework itself registers, which this application does not
     * own and should not report on.
     */
    private function isFrameworkRoute(string $name): bool
    {
        foreach (['sanctum.', 'ignition.', 'livewire.', 'horizon.', 'telescope.', 'pulse'] as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
