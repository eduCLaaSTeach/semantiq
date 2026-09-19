<?php

declare(strict_types=1);

namespace App\Modules\Identity\Health;

use App\Modules\Identity\Support\IdentityConfigurationReport;
use App\Modules\Identity\Support\ProviderInventory;
use App\Modules\Identity\Support\SecretPresence;
use App\Modules\Identity\Support\SessionPolicy;
use App\Modules\Platform\Identity\IdentityProvider;
use App\Modules\Platform\Identity\Microsoft\EntraDiscovery;
use App\Modules\Platform\Support\ConfigurationValidator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * The one source of identity health. Two things present it; neither owns it.
 *
 * The SSO Health screen renders the whole report; HealthInspector collapses it
 * to ok/detail for semantiq:health. If the inspector had its own copy of the
 * logic, the console command and the screen could disagree about the same
 * deployment and an operator would have to pick one to believe.
 *
 * TWO FACTS THIS DELIBERATELY KEEPS APART:
 *
 *   IDENTITY TRUST AVAILABLE   can we get usable metadata and keys at all,
 *                              from cache or provider? A cached answer is a
 *                              perfectly good answer - caching for 24 hours is
 *                              the designed behaviour, not a deficiency.
 *
 *   MICROSOFT REACHABLE        did an explicit live check reach Microsoft just
 *                              now? A response cached this morning says nothing
 *                              about this afternoon.
 *
 * Merging them is how a screen whose purpose is to make outages visible reports
 * Healthy through one for up to a day.
 *
 * WHICH OF THESE REACHES THE NETWORK - stated accurately, because it was not.
 *
 * This comment used to read "Rendering this NEVER touches the network by
 * choice: it reads the cache and the stored probe result." THAT WAS FALSE, and
 * it was false about the method four lines below it. P1-09 was designed
 * against this sentence and inherited the error; the code, not the comment, is
 * what found it.
 *
 *   report()        MAY REACH MICROSOFT. It calls trustAvailability(), which
 *                   reads the cached metadata and keys first and, WHEN EITHER
 *                   IS ABSENT, falls through to EntraDiscovery::metadata() and
 *                   signingKeys(). Both are Cache::remember() around an
 *                   outbound Http::get with a ten-second timeout. On a warm
 *                   cache it contacts nobody; on a cold one - a fresh
 *                   deployment, a cleared cache, or simply 24 hours of quiet,
 *                   since the discovery cache lives for CACHE_HOURS - it makes
 *                   two outbound calls. This is DELIBERATE and unchanged: a
 *                   first look at the SSO Health screen on a healthy
 *                   deployment should not report a fault just because nobody
 *                   has signed in yet.
 *
 *   storedReport()  NEVER REACHES THE NETWORK, and not by choice - by
 *                   construction. It does not reference EntraDiscovery at all,
 *                   so no read-through exists for it to take. This is the
 *                   projection P1-09 System Health renders, and System Health
 *                   renders NOTHING ELSE from this class.
 *
 *   recheck()       is the EXPLICIT live probe, and the only thing here that
 *                   sets out to contact Microsoft. It goes through
 *                   EntraDiscovery::probe(), which holds a provider-wide lock,
 *                   and it runs only when an administrator presses the button.
 *
 * The SSO Health screen renders report(). That is correct for a screen whose
 * subject IS the identity provider and which offers a live re-check beside the
 * result. It would not be correct for a general health page, which is why
 * P1-09 has its own entry point rather than a second probe.
 */
final class IdentityHealthCheck
{
    public const LAST_RESULT_KEY = 'semantiq:identity:health:last';

    public const LAST_PROBE_KEY = 'semantiq:identity:probe:last';

    private const REMEMBER_DAYS = 7;

    public function __construct(
        private readonly IdentityProvider $provider,
        private readonly EntraDiscovery $discovery,
        private readonly ConfigurationValidator $configuration,
        private readonly ProviderInventory $inventory,
        private readonly SessionPolicy $sessionPolicy,
    ) {}

    /**
     * Evaluate everything. MAY CONTACT MICROSOFT - see the class comment.
     *
     * This said "without contacting Microsoft", which is true on a warm cache
     * and false on a cold one: trustAvailability() below falls through to
     * EntraDiscovery::metadata() and signingKeys() when either cached value is
     * absent, and both are read-through HTTP.
     *
     * The BEHAVIOUR is unchanged and remains correct for the SSO Health
     * screen, which is about the identity provider and offers a live re-check
     * beside the result. A caller that must not reach the network wants
     * storedReport() instead.
     */
    public function report(): IdentityHealthReport
    {
        $report = IdentityConfigurationReport::build($this->provider);
        $probe = $this->lastProbe();

        $trust = $this->trustAvailability();

        $checks = [
            $this->providerConfigured($report),
            $this->configurationValid(),
            $this->identityTrustAvailable($trust),
            $this->microsoftReachable($probe, $trust),
            $this->directoryIdentityConsistent($trust),
            $this->returnAddress($report),
            $this->clientSecretPresent($report),
            $this->sessionPolicyCoherent(),
            $this->onlyApprovedProviders(),
        ];

        return new IdentityHealthReport(
            checks: $checks,
            establishedAt: $this->establishedAt(),
            lastProbeAt: is_array($probe) ? ($probe['at'] ?? null) : null,
        );
    }

    /**
     * The explicit administrator action. Probes, then re-evaluates.
     *
     * @return array{report: IdentityHealthReport, ran: bool, changed: bool}
     */
    public function recheck(): array
    {
        $before = $this->rememberedState();

        $probe = $this->discovery->probe();

        if ($probe['ran']) {
            Cache::put(self::LAST_PROBE_KEY, [
                'reachable' => $probe['reachable'],
                'reason' => $probe['reason'],
                'at' => Carbon::now()->toIso8601String(),
            ], now()->addDays(self::REMEMBER_DAYS));
        }

        $report = $this->report();

        Cache::put(self::LAST_RESULT_KEY, [
            'state' => $report->state(),
            'at' => Carbon::now()->toIso8601String(),
        ], now()->addDays(self::REMEMBER_DAYS));

        /*
         * A BOOLEAN, not a nullable "changedFrom".
         *
         * The first version returned the previous state and the caller fired
         * only when it was non-null - so the very first check on a deployment,
         * where there is no previous state, recorded nothing at all. That is the
         * one moment a failing deployment most needs to say so. Found by the
         * test, which expected an event and got none.
         */
        return [
            'report' => $report,
            'ran' => $probe['ran'],
            'changed' => $before !== $report->state(),
        ];
    }

    /**
     * P1-09. The STORED answer, for a reader that must not contact Microsoft.
     *
     * NO NETWORK PATH EXISTS IN THIS METHOD, and that is a stronger claim than
     * "it does not normally make a request". It does not touch EntraDiscovery
     * at all - not metadata(), not signingKeys(), not probe(), and not even the
     * cached accessors - so there is no read-through to fall through to. It
     * reads the two cache keys THIS CLASS already writes and reports what they
     * say.
     *
     * WHY report() WOULD NOT DO, which is the defect this method was added for:
     * report() calls trustAvailability(), and when either cached value is
     * absent that method asks Microsoft. On a cold cache - a fresh deployment,
     * a cleared cache, or 24 hours of quiet - rendering a health screen off
     * report() makes two outbound HTTPS calls with a ten-second timeout each,
     * to the dependency the person opening that screen is trying to diagnose.
     *
     * ABSENCE IS REPORTED AS ABSENCE, in both directions. There is no default
     * arm that lands on HEALTHY, and equally no evaluation that would land on
     * FAILED because nothing is cached - inventing an outage from "nobody
     * looked" is the same defect as inventing health from it, and a false red
     * on a working sign-in is the one this codebase already refuses elsewhere.
     *
     * A STATE WITHOUT A USABLE TIME IS NOT A RESULT, and that is a correction.
     *
     * The first version validated the state and took the instant on trust. So
     * a cache entry of ['state' => 'healthy'] - no 'at' at all, or an 'at' that
     * will not parse - rendered as:
     *
     *     Microsoft Entra ID     Available     (and no age beneath it)
     *
     * which reads as a measurement taken now. It is the exact failure this
     * whole method exists to prevent, arriving through the other field: the
     * screen's only defence against a stale cached answer is the age printed
     * next to it, so an answer whose age is unknown must not be shown as an
     * answer. D-114.
     *
     * Both halves are therefore required. Either one missing or malformed
     * returns NOT_CHECKED, and the caller gets no age to render because there
     * is none to invent.
     */
    public function storedReport(): StoredIdentityHealth
    {
        $stored = Cache::get(self::LAST_RESULT_KEY);
        $probe = $this->lastProbe();
        $probeAt = is_array($probe) ? ($probe['at'] ?? null) : null;

        $state = is_array($stored) ? ($stored['state'] ?? null) : null;
        $at = is_array($stored) ? ($stored['at'] ?? null) : null;

        // An unrecognised value is not trustworthy, whatever it is. A cache
        // entry written by an older release, or half-written, must not be
        // rendered as a status; it is exactly as unknown as no entry at all.
        $recognised = in_array($state, [
            IdentityHealthReport::HEALTHY,
            IdentityHealthReport::DEGRADED,
            IdentityHealthReport::FAILED,
        ], true);

        $measuredAt = $this->parsedInstant($at);

        if (! $recognised || $measuredAt === null) {
            return new StoredIdentityHealth(
                state: IdentityHealthReport::NOT_CHECKED,
                checkedAt: null,
                lastProbeAt: $this->parsedInstant($probeAt),
            );
        }

        return new StoredIdentityHealth(
            state: (string) $state,
            checkedAt: $measuredAt,
            lastProbeAt: $this->parsedInstant($probeAt),
        );
    }

    /**
     * The instant, or null - and PARSING is the test, not is_string().
     *
     * "recently", "", "0000-00-00", an array, an integer: each is a string or a
     * value that looks stored, and none of them is a time. Carbon is asked to
     * parse it here rather than at render, so a value that would have produced
     * no age produces no RESULT instead.
     *
     * Still no network: Carbon parses a string.
     */
    private function parsedInstant(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }

        return $value;
    }

    /**
     * Collapsed for HealthInspector.
     *
     * Failed fails the deployment; Degraded does not. A return-address nuance,
     * or a live check that could not reach Microsoft while a valid trust set is
     * still cached and sign-in is still being served, must not fail a
     * deployment. A condition that deterministically prevents authentication
     * must.
     *
     * @return array{ok: bool, detail: string}
     */
    public function forInspector(): array
    {
        if ($this->exemptOutsideProduction()) {
            return [
                'ok' => true,
                'detail' => 'Identity is not configured in this environment, which is expected outside production.',
            ];
        }

        $report = $this->report();

        return match ($report->state()) {
            IdentityHealthReport::FAILED => ['ok' => false, 'detail' => 'Sign-in unavailable: '.($report->firstConcern() ?? 'unknown').'.'],
            IdentityHealthReport::DEGRADED => ['ok' => true, 'detail' => 'Needs attention: '.($report->firstConcern() ?? 'unknown').'.'],
            default => ['ok' => true, 'detail' => 'No issue was detected by the available identity checks.'],
        };
    }

    /**
     * CI and developer machines have no Entra tenant, deliberately - the
     * identity keys are required in production only, and inventing placeholder
     * values to satisfy a validator moves the failure from boot, where it is
     * obvious, to the identity provider, where it is not.
     *
     * This exemption is a vacuity risk and is treated as one: a check that is
     * trivially green everywhere except the one environment nobody runs tests in
     * is not a check. IdentityHealthTest forces app.env to production with the
     * keys absent and asserts Failed.
     */
    private function exemptOutsideProduction(): bool
    {
        return config('app.env') !== 'production' && ! $this->provider->isConfigured();
    }

    /** @return array{metadata: array<string, mixed>|null, keys: array<int, mixed>|null} */
    private function trustAvailability(): array
    {
        $metadata = $this->discovery->cachedMetadata();
        $keys = $this->discovery->cachedSigningKeys();

        if ($metadata !== null && $keys !== null) {
            return ['metadata' => $metadata, 'keys' => $keys];
        }

        /*
         * NOTHING CACHED, SO ASK - AND THIS IS THE OUTBOUND CALL.
         *
         * metadata() and signingKeys() are Cache::remember() around an
         * Http::get with a ten-second timeout, so this branch reaches
         * Microsoft. It is deliberate: a first look on a healthy deployment
         * should not report a fault just because nobody has signed in yet, and
         * a failure here is a real failure, since EntraDiscovery caches no
         * failed response.
         *
         * It is also the reason storedReport() exists. Every caller of
         * report() inherits this branch, so a caller that must contact nobody
         * cannot use report() at all - no amount of warm cache makes the path
         * absent, and a guarantee that depends on cache state is not a
         * guarantee.
         */
        try {
            $metadata ??= $this->discovery->metadata();
            $keys ??= $this->discovery->signingKeys();
        } catch (Throwable) {
            return ['metadata' => $metadata, 'keys' => $keys];
        }

        return ['metadata' => $metadata, 'keys' => $keys];
    }

    /** @return array{key: string, label: string, state: string, finding: string, action: string|null} */
    private function providerConfigured(IdentityConfigurationReport $report): array
    {
        if ($report->configured) {
            return $this->row('provider_configured', 'Provider configured', IdentityHealthReport::HEALTHY,
                'Microsoft Entra ID is configured on this deployment.');
        }

        return $this->row('provider_configured', 'Provider configured', IdentityHealthReport::FAILED,
            'Microsoft Entra ID is not fully configured, so nobody can sign in.',
            'Set the missing settings on the server through the controlled deployment process. '
            .'The Microsoft Entra ID screen lists the ones that are missing.');
    }

    /** @return array{key: string, label: string, state: string, finding: string, action: string|null} */
    private function configurationValid(): array
    {
        $problems = array_values(array_filter(
            $this->configuration->problems(),
            fn (string $problem): bool => str_contains($problem, 'identity.microsoft'),
        ));

        if ($problems === []) {
            return $this->row('configuration_valid', 'Configuration valid', IdentityHealthReport::HEALTHY,
                'The identity settings this deployment requires are all present.');
        }

        return $this->row('configuration_valid', 'Configuration valid', IdentityHealthReport::FAILED,
            'The deployment is missing identity settings it requires.',
            'Set them on the server through the controlled deployment process.');
    }

    /**
     * @param  array{metadata: array<string, mixed>|null, keys: array<int, mixed>|null}  $trust
     * @return array{key: string, label: string, state: string, finding: string, action: string|null}
     */
    private function identityTrustAvailable(array $trust): array
    {
        if ($this->hasUsableTrust($trust)) {
            return $this->row('identity_trust', 'Identity trust available', IdentityHealthReport::HEALTHY,
                "Microsoft's sign-in settings and signing keys are available to this deployment.");
        }

        return $this->row('identity_trust', 'Identity trust available', IdentityHealthReport::FAILED,
            "Microsoft's sign-in settings could not be obtained, so people cannot sign in.",
            'Check that the server can make outbound connections to Microsoft, then run the live check again.');
    }

    /**
     * @param  array<string, mixed>|null  $probe
     * @param  array{metadata: array<string, mixed>|null, keys: array<int, mixed>|null}  $trust
     * @return array{key: string, label: string, state: string, finding: string, action: string|null}
     */
    private function microsoftReachable(?array $probe, array $trust): array
    {
        if ($probe === null) {
            return $this->row('microsoft_reachable', 'Microsoft reachable (live check)', IdentityHealthReport::NOT_CHECKED,
                'No live check has been run on this deployment yet.',
                'Run a live check to confirm Microsoft is reachable right now.');
        }

        $at = $this->inWords($probe['at'] ?? null);

        if (($probe['reachable'] ?? false) === true) {
            return $this->row('microsoft_reachable', 'Microsoft reachable (live check)', IdentityHealthReport::HEALTHY,
                "Microsoft was reached by the live check {$at}.");
        }

        if ($this->hasUsableTrust($trust)) {
            return $this->row('microsoft_reachable', 'Microsoft reachable (live check)', IdentityHealthReport::DEGRADED,
                "Microsoft could not be reached during the latest live check, {$at}. Cached identity "
                .'trust remains available, but sign-in may be affected.',
                'Check that the server can make outbound connections to Microsoft, then run the live check again.');
        }

        return $this->row('microsoft_reachable', 'Microsoft reachable (live check)', IdentityHealthReport::FAILED,
            "Microsoft could not be reached during the latest live check, {$at}, and no usable "
            .'sign-in settings are held locally.',
            'Check that the server can make outbound connections to Microsoft, then run the live check again.');
    }

    /**
     * Only meaningful when the directory is configured by id.
     *
     * The sign-in path does not compare the configured tenant against the
     * published issuer - IdTokenValidator takes the issuer FROM discovery, which
     * is fetched from the configured tenant's own URL, so for a correct
     * deployment the two agree by construction. A naive equality check would
     * verify something sign-in does not, and would report a perfectly healthy
     * tenant as broken whenever the directory is configured by domain name,
     * because the published issuer carries the directory's id and not its
     * domain. A false red on a working system is worse than no check.
     *
     * @param  array{metadata: array<string, mixed>|null, keys: array<int, mixed>|null}  $trust
     * @return array{key: string, label: string, state: string, finding: string, action: string|null}
     */
    private function directoryIdentityConsistent(array $trust): array
    {
        $tenant = (string) config('identity.microsoft.tenant_id');
        $issuer = is_array($trust['metadata']) ? (string) ($trust['metadata']['issuer'] ?? '') : '';

        if ($tenant === '' || $issuer === '') {
            return $this->row('directory_identity', 'Directory identity consistent', IdentityHealthReport::NOT_CHECKED,
                'The published directory identity has not been read on this deployment yet.',
                'Run a live check.');
        }

        if (! $this->looksLikeDirectoryId($tenant)) {
            return $this->row('directory_identity', 'Directory identity consistent', IdentityHealthReport::HEALTHY,
                'The directory is configured by domain name, so there is no identifier to compare.');
        }

        if (str_contains($issuer, $tenant)) {
            return $this->row('directory_identity', 'Directory identity consistent', IdentityHealthReport::HEALTHY,
                'The directory Microsoft publishes is the one configured here.');
        }

        return $this->row('directory_identity', 'Directory identity consistent', IdentityHealthReport::DEGRADED,
            'The directory Microsoft publishes is not the one configured here.',
            'Compare the directory configured on this deployment with the one in Microsoft Entra.');
    }

    /** @return array{key: string, label: string, state: string, finding: string, action: string|null} */
    private function returnAddress(IdentityConfigurationReport $report): array
    {
        if ($report->redirectUriMatchesDeployment) {
            return $this->row('return_address', 'Sign-in return address', IdentityHealthReport::HEALTHY,
                "The return address configured for Microsoft is this deployment's own.");
        }

        return $this->row('return_address', 'Sign-in return address', IdentityHealthReport::DEGRADED,
            'The sign-in return address configured for Microsoft is not this deployment\'s return '
            .'address. If they differ, people will be returned to the wrong place after signing in.',
            'Compare it with the redirect address registered in Microsoft Entra.');
    }

    /** @return array{key: string, label: string, state: string, finding: string, action: string|null} */
    private function clientSecretPresent(IdentityConfigurationReport $report): array
    {
        if ($report->secret === SecretPresence::Present) {
            return $this->row('client_secret', 'Client secret present', IdentityHealthReport::HEALTHY,
                'A client secret is configured.');
        }

        return $this->row('client_secret', 'Client secret present', IdentityHealthReport::FAILED,
            'No client secret is configured, so sign-in cannot complete.',
            'Set it on the server through the controlled deployment process.');
    }

    /** @return array{key: string, label: string, state: string, finding: string, action: string|null} */
    private function sessionPolicyCoherent(): array
    {
        $idle = $this->sessionPolicy->idleMinutes();

        if (! $this->sessionPolicy->idleIsShorterThanAbsolute()) {
            return $this->row('session_policy', 'Session policy coherent', IdentityHealthReport::DEGRADED,
                'The idle timeout is not shorter than the absolute session lifetime, so the '
                .'absolute one can never take effect.',
                'Correct the session settings on the server through the controlled deployment process.');
        }

        if ($this->sessionPolicy->matchesApprovedPolicy()) {
            return $this->row('session_policy', 'Session policy coherent', IdentityHealthReport::HEALTHY,
                'The session policy in force is the approved one.');
        }

        return $this->row('session_policy', 'Session policy coherent', IdentityHealthReport::DEGRADED,
            "The idle timeout in force is {$idle} minutes; the approved policy is "
            .SessionPolicy::APPROVED_IDLE_MINUTES.' minutes.',
            'Correct SESSION_LIFETIME on the server through the controlled deployment process.');
    }

    /** @return array{key: string, label: string, state: string, finding: string, action: string|null} */
    private function onlyApprovedProviders(): array
    {
        if ($this->inventory->unapprovedKeys() === []) {
            return $this->row('approved_providers', 'Only approved providers registered', IdentityHealthReport::HEALTHY,
                'Every identity provider in this build has been approved.');
        }

        return $this->row('approved_providers', 'Only approved providers registered', IdentityHealthReport::FAILED,
            'An identity provider is present in this build that has not been approved.',
            'Sign-in providers require Product Owner approval. Remove it, or record the approval.');
    }

    /**
     * @param  array{metadata: array<string, mixed>|null, keys: array<int, mixed>|null}  $trust
     */
    private function hasUsableTrust(array $trust): bool
    {
        return is_array($trust['metadata'])
            && isset($trust['metadata']['issuer'])
            && is_array($trust['keys'])
            && $trust['keys'] !== [];
    }

    private function looksLikeDirectoryId(string $tenant): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $tenant);
    }

    /** @return array<string, mixed>|null */
    private function lastProbe(): ?array
    {
        $stored = Cache::get(self::LAST_PROBE_KEY);

        return is_array($stored) ? $stored : null;
    }

    private function rememberedState(): ?string
    {
        $stored = Cache::get(self::LAST_RESULT_KEY);

        return is_array($stored) ? ($stored['state'] ?? null) : null;
    }

    private function establishedAt(): ?string
    {
        $stored = Cache::get(self::LAST_RESULT_KEY);

        return is_array($stored) ? ($stored['at'] ?? null) : null;
    }

    private function inWords(?string $iso): string
    {
        if (! is_string($iso) || $iso === '') {
            return 'recently';
        }

        try {
            return Carbon::parse($iso)->diffForHumans();
        } catch (Throwable) {
            return 'recently';
        }
    }

    /** @return array{key: string, label: string, state: string, finding: string, action: string|null} */
    private function row(string $key, string $label, string $state, string $finding, ?string $action = null): array
    {
        return ['key' => $key, 'label' => $label, 'state' => $state, 'finding' => $finding, 'action' => $action];
    }
}
