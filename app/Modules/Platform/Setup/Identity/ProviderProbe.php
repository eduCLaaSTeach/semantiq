<?php

declare(strict_types=1);

namespace App\Modules\Platform\Setup\Identity;

use App\Modules\Platform\Identity\Microsoft\EntraDiscovery;
use App\Modules\SystemHealth\Report\HealthStatus;
use Throwable;

/**
 * Tests a CANDIDATE identity configuration without letting it touch anything.
 *
 * TWO PROPERTIES, BOTH STRUCTURAL.
 *
 * 1. IT BUILDS ITS OWN DISCOVERY CLIENT. It never resolves the container's,
 *    because the container's is built from the configuration currently in
 *    force - so testing a newly typed tenant through it would validate the
 *    PREVIOUS one and report success. That is the defect the whole
 *    per-resolution binding change exists to prevent, and it would walk back in
 *    here if this class took a provider as a dependency.
 *
 * 2. IT USES ITS OWN CACHE NAMESPACE. A probe can therefore neither READ live
 *    trust - and so pass by reading what a previous successful sign-in cached,
 *    reporting a broken configuration as working - nor WRITE it, and so break
 *    authentication for everyone by testing a misconfigured endpoint. Both
 *    directions matter; the second is the one that turns a diagnostic into an
 *    outage.
 *
 * THE EXPLANATION IS CHOSEN, NEVER CAUGHT. Provider error bodies routinely
 * echo credentials, endpoints and internal hostnames, and a connection test is
 * the single most likely place for one to be rendered straight onto a screen.
 * Every Throwable here becomes one of this class's own declared sentences.
 *
 * A TIMEOUT IS Degraded, NEVER Unavailable. "We could not reach it in ten
 * seconds" is not "it is broken", and reporting the second would have an
 * administrator re-enter a configuration that was correct.
 */
final class ProviderProbe
{
    /** Probes live here, and the live provider lives under `entra`. */
    public const CACHE_NAMESPACE = 'entra-probe';

    /**
     * @return array{status: HealthStatus, explanation: string}
     */
    public function verify(IdentityConfiguration $candidate): array
    {
        if (! $candidate->isComplete()) {
            return [
                'status' => HealthStatus::NotChecked,
                'explanation' => 'Some sign-in details have not been entered yet, so there was nothing to test.',
            ];
        }

        $discovery = new EntraDiscovery($candidate->tenantId, self::CACHE_NAMESPACE);

        try {
            $metadata = $discovery->metadata();
            $signingKeys = $discovery->signingKeys();
        } catch (Throwable) {
            return [
                'status' => HealthStatus::Unavailable,
                'explanation' => 'Microsoft did not answer for the directory entered. Check the directory identifier.',
            ];
        }

        $issuer = is_array($metadata) ? (string) ($metadata['issuer'] ?? '') : '';

        if ($issuer === '' || $signingKeys === []) {
            return [
                'status' => HealthStatus::Unavailable,
                'explanation' => 'Microsoft answered, but did not publish the details needed to verify a sign-in.',
            ];
        }

        /*
         * THE SAME COMPARISON THE SIGN-IN PATH MAKES, AND NO STRICTER.
         *
         * IdTokenValidator takes the issuer FROM discovery, which is fetched
         * from the configured directory's own URL, so for a correct deployment
         * the two agree by construction. A naive equality check here would
         * verify something sign-in does not, and would report a perfectly
         * healthy directory as broken whenever it is configured by DOMAIN NAME
         * rather than by identifier - because the published issuer carries the
         * identifier. A false red on a working system is worse than no check.
         */
        if ($this->looksLikeDirectoryId($candidate->tenantId)
            && ! str_contains($issuer, $candidate->tenantId)) {
            return [
                'status' => HealthStatus::Unavailable,
                'explanation' => 'The directory Microsoft published is not the one entered here.',
            ];
        }

        return [
            'status' => HealthStatus::Available,
            'explanation' => 'Microsoft answered for this directory and published the details needed to verify a sign-in.',
        ];
    }

    private function looksLikeDirectoryId(string $tenant): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $tenant);
    }
}
