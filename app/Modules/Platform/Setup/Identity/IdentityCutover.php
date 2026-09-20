<?php

declare(strict_types=1);

namespace App\Modules\Platform\Setup\Identity;

use App\Modules\Platform\Security\SecurityEventLogger;
use App\Modules\Platform\Setup\IntegrationConfigurationWriter;
use App\Modules\Platform\Setup\IntegrationFamily;
use App\Modules\Platform\Setup\Models\PlatformSetting;
use App\Modules\SystemHealth\Report\HealthStatus;
use Illuminate\Support\Facades\DB;

/**
 * MOVING THE IDENTITY AUTHORITY FROM .env TO THE STORE. Two paths, and a
 * deployment is on exactly one of them.
 *
 * -------------------------------------------------------------------------
 * EXISTING DEPLOYMENT - .env already holds a working configuration
 * -------------------------------------------------------------------------
 *   check -> import -> verify -> commit -> a real sign-in -> an operator
 *   retires .env BY HAND, later, deliberately not automated.
 *
 * Driven from SSH by semantiq:identity-cutover, because it reads .env and
 * because the deployment it is changing is already serving people.
 *
 * -------------------------------------------------------------------------
 * FRESH INSTALLATION - there is no .env identity configuration to move
 * -------------------------------------------------------------------------
 * CORRECTION 4. The design had ONE path and treated it as the only one. Apply
 * it to a genuinely fresh installation and the result is absurd:
 *
 *   fresh deployment, identity .env EMPTY
 *     -> the operator creates the Bootstrap administrator
 *     -> First-Run step 3 types the Entra configuration, tests it, saves it
 *     -> identity_source is STILL `env`
 *     -> /auth/microsoft reads .env, finds nothing, and the nominated
 *        administrator cannot sign in. First-Run completes; the installation
 *        is unusable.
 *
 * ...and the only escape would be the SSH command First-Run exists to avoid.
 *
 * So commitFreshInstallation() verifies the typed candidate and, only if that
 * passes, sets identity_source = store ATOMICALLY. No SSH step, and STILL NO
 * RUNTIME FALLBACK: `store` reads no env(), which is a separate guard.
 *
 * THE TRANSITION IS VERIFIED-FIRST, NOT SAVE-AND-HOPE. A failing verification
 * leaves the candidate a candidate and does not move the flag. Moving it on
 * save would hand a deployment an authority nobody has shown to work, at the
 * exact moment nobody can sign in to fix it.
 */
final class IdentityCutover
{
    public function __construct(
        private readonly IdentityConfigurationSource $source,
        private readonly IntegrationConfigurationWriter $writer,
        private readonly ProviderProbe $probe,
        private readonly SecurityEventLogger $events,
    ) {}

    /**
     * Verify the candidate currently in the store, without moving anything.
     *
     * @return array{status: HealthStatus, explanation: string}
     */
    public function verifyCandidate(): array
    {
        return $this->probe->verify($this->candidate());
    }

    /**
     * FRESH INSTALLATION. Verify, then move the authority, atomically.
     *
     * @return array{committed: bool, status: HealthStatus, explanation: string}
     */
    public function commitFreshInstallation(): array
    {
        $settings = PlatformSetting::current();

        if ($settings->identityReadsStore()) {
            // Already on the store. Saying "committed" would be a lie, and
            // re-running the move would increment the revision for nothing.
            return [
                'committed' => false,
                'status' => HealthStatus::Available,
                'explanation' => 'Sign-in already reads the details stored in SemantIQ.',
            ];
        }

        $result = $this->verifyCandidate();

        if ($result['status'] !== HealthStatus::Available) {
            return [
                'committed' => false,
                'status' => $result['status'],
                'explanation' => $result['explanation'],
            ];
        }

        DB::transaction(function (): void {
            $settings = PlatformSetting::forUpdate();
            $settings->identity_source = PlatformSetting::SOURCE_STORE;
            $settings->identity_verified_at = now();
            $settings->identity_committed_at = now();
            $settings->save();

            $this->events->record(SecurityEventLogger::IDENTITY_CONFIGURATION_CUTOVER, [
                'provider' => 'microsoft',
                'result' => 'committed',
                'reason' => 'fresh_installation',
            ]);
        });

        // The memoised store resolution predates the move.
        $this->source->forget();

        $this->writer->recordTestResult(
            IntegrationFamily::Identity,
            HealthStatus::Available,
            $result['explanation'],
        );

        return [
            'committed' => true,
            'status' => HealthStatus::Available,
            'explanation' => 'Sign-in now reads the details stored in SemantIQ.',
        ];
    }

    /**
     * The candidate: what the STORE holds, whether or not it is authoritative
     * yet.
     *
     * NOT source->resolve(). That answers "what is in force", which on a fresh
     * installation is the empty .env - so verifying through it would test
     * nothing and pass or fail for reasons unrelated to what was typed.
     */
    private function candidate(): IdentityConfiguration
    {
        return $this->source->storedCandidate();
    }
}
