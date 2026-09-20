<?php

declare(strict_types=1);

namespace App\Modules\Identity\Configuration;

use App\Modules\Platform\Security\SecurityEventLogger;
use App\Modules\Platform\Setup\Identity\IdentityConfiguration;
use App\Modules\Platform\Setup\Identity\IdentityConfigurationSource;
use App\Modules\Platform\Setup\Identity\ProviderProbe;
use App\Modules\Platform\Setup\IntegrationConfigurationWriter;
use App\Modules\Platform\Setup\IntegrationFamily;
use App\Modules\Platform\Setup\Models\IntegrationConfiguration;
use App\Modules\Platform\Setup\Models\PlatformSetting;
use App\Modules\Platform\Setup\Secrets\IntegrationSecretStore;
use App\Modules\Platform\Setup\Secrets\StagedIntegrationChange;
use App\Modules\SystemHealth\Report\HealthStatus;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * CHANGING MICROSOFT SIGN-IN ON A DEPLOYMENT THAT IS ALREADY USING IT.
 *
 * -------------------------------------------------------------------------
 * WHAT WAS MISSING
 * -------------------------------------------------------------------------
 * P1-02's Entra screen was read-only and said so: "These are set on the
 * server. They cannot be changed from this screen." First-Run could establish
 * a configuration; after installation nobody could change one. A customer whose
 * Entra client secret expired - which they all do - had no way to enter the new
 * one except SSH, which is the thing this whole unit exists to remove.
 *
 * -------------------------------------------------------------------------
 * VERIFY, THEN ACTIVATE. NEVER THE OTHER WAY ROUND
 * -------------------------------------------------------------------------
 * This is the only configuration in SemantIQ whose failure locks everybody out
 * of the deployment, including the person who broke it. So the live
 * configuration is never touched speculatively:
 *
 *   stage the candidate        encrypted, short-lived, single-use
 *   -> Microsoft step-up       prove it is still the administrator
 *   -> VERIFY the candidate    a real discovery round trip, against the values
 *                              being proposed and not the ones in force
 *   -> activate atomically     only if that answered
 *
 * A failure at any step leaves the working configuration exactly where it was.
 * There is no path here that writes first and checks afterwards.
 *
 * THE CANDIDATE IS VERIFIED THROUGH ProviderProbe, which builds its own
 * discovery client and uses its own cache namespace. Both matter: resolving the
 * container's client would test the configuration currently IN FORCE and report
 * success for a candidate nobody checked, and sharing the live cache would let a
 * probe either pass by reading trust a previous sign-in cached, or poison it and
 * break authentication for everyone.
 *
 * -------------------------------------------------------------------------
 * REQUIRED FIELDS CANNOT BE PARTIALLY CLEARED
 * -------------------------------------------------------------------------
 * An administrator may REPLACE the configuration. They may not empty half of
 * it. Blanking the directory on a configured deployment is never a thing
 * somebody meant to do, and the result would be an installation nobody can sign
 * in to - so it is refused before anything is staged, rather than discovered
 * when the probe fails.
 */
final class IdentityReconfiguration
{
    /** The same lifetime as the step-up that authorises it. */
    private const LIFETIME_MINUTES = 10;

    /** Which non-secret fields a reconfiguration may carry. */
    public const FIELDS = ['tenant_id', 'client_id', 'redirect_uri'];

    public function __construct(
        private readonly IdentityConfigurationSource $source,
        private readonly IntegrationSecretStore $secrets,
        private readonly IntegrationConfigurationWriter $writer,
        private readonly ProviderProbe $probe,
        private readonly SecurityEventLogger $events,
    ) {}

    /** Is there a working configuration to protect? */
    public function isConfigured(): bool
    {
        return $this->source->resolve()->isComplete();
    }

    /**
     * Hold the candidate until Microsoft confirms who is asking.
     *
     * THE SECRET IS ENCRYPTED HERE AND NOWHERE ELSE ON THIS PATH. It does not
     * enter the session - which on this deployment is a database table - the
     * step-up row, whose columns are structural and safe to read, or the URL.
     *
     * @param  array<string, string>  $fields
     */
    public function stage(array $fields, ?string $clientSecret, int $actorId): int
    {
        $clean = [];

        foreach (self::FIELDS as $name) {
            if (array_key_exists($name, $fields)) {
                $clean[$name] = trim((string) $fields[$name]);
            }
        }

        return (int) StagedIntegrationChange::query()->create([
            'family' => IntegrationFamily::Identity->value,
            'secret_name' => $clientSecret === null ? '' : 'client_secret',
            'operation' => StagedIntegrationChange::OPERATION_RECONFIGURE,
            'ciphertext' => $clientSecret === null ? null : Crypt::encryptString($clientSecret),
            'fields' => $clean,
            'requested_by_user_id' => $actorId,
            'expires_at' => now()->addMinutes(self::LIFETIME_MINUTES),
        ])->getKey();
    }

    /**
     * What the deployment WOULD be signing in with, if this candidate were
     * activated.
     *
     * MERGED OVER WHAT IS IN FORCE, so a change that touches only the client
     * secret is verified against the real directory rather than against blanks.
     */
    public function candidateFrom(StagedIntegrationChange $staged): IdentityConfiguration
    {
        $live = $this->source->resolve();

        $fields = is_array($staged->fields) ? $staged->fields : [];

        $secret = $live->clientSecret;

        if (is_string($staged->ciphertext) && $staged->ciphertext !== '') {
            $decrypted = $this->secrets->decryptStaged($staged->ciphertext);

            if ($decrypted === null) {
                // An undecryptable payload is not a candidate. Returning the
                // live secret instead would verify the OLD configuration and
                // report success for a change that cannot be applied.
                return new IdentityConfiguration(
                    tenantId: '', clientId: '', clientSecret: '', redirectUri: '',
                    source: $live->source, revision: $live->revision,
                );
            }

            $secret = $decrypted;
        }

        return new IdentityConfiguration(
            tenantId: (string) ($fields['tenant_id'] ?? $live->tenantId),
            clientId: (string) ($fields['client_id'] ?? $live->clientId),
            clientSecret: $secret,
            redirectUri: (string) ($fields['redirect_uri'] ?? $live->redirectUri),
            source: $live->source,
            revision: $live->revision,
        );
    }

    /**
     * Verify the candidate, then activate it - or leave everything alone.
     *
     * THE PROBE RUNS BEFORE ANY WRITE. A staged change that does not verify is
     * consumed and discarded: the administrator re-enters it. Consuming it is
     * deliberate rather than tidy - a staged credential that survives its own
     * confirmation is a credential sitting in a table with nothing left to
     * authorise it.
     *
     * @return array{activated: bool, status: HealthStatus, explanation: string}
     */
    public function activate(StagedIntegrationChange $staged, int $actorId): array
    {
        $candidate = $this->candidateFrom($staged);

        $result = $this->probe->verify($candidate);

        if ($result['status'] !== HealthStatus::Available) {
            $this->discard($staged, $actorId, $result['status']);

            return [
                'activated' => false,
                'status' => $result['status'],
                'explanation' => $result['explanation'],
            ];
        }

        $applied = $this->commit($staged, $candidate, $actorId);

        if (! $applied) {
            return [
                'activated' => false,
                'status' => HealthStatus::NotChecked,
                'explanation' => 'That change had already been used or had expired. Nothing was changed.',
            ];
        }

        return [
            'activated' => true,
            'status' => HealthStatus::Available,
            'explanation' => $result['explanation'],
        ];
    }

    /**
     * The atomic activation.
     *
     * ONE TRANSACTION. The staged row is consumed by a conditional UPDATE whose
     * guard is in the WHERE clause, and the configuration is written in the same
     * transaction - so a lost race writes nothing, and a write that fails takes
     * the consumption with it.
     */
    private function commit(
        StagedIntegrationChange $staged,
        IdentityConfiguration $candidate,
        int $actorId,
    ): bool {
        return (bool) DB::transaction(function () use ($staged, $actorId): bool {
            $consumed = StagedIntegrationChange::query()
                ->whereKey($staged->getKey())
                ->whereNull('consumed_at')
                ->where('expires_at', '>', now())
                ->update(['consumed_at' => now()]);

            if ($consumed !== 1) {
                return false;
            }

            $fields = is_array($staged->fields) ? $staged->fields : [];

            if ($fields !== []) {
                $this->writer->applyFields(IntegrationFamily::Identity, $fields, $actorId);
            }

            if (is_string($staged->ciphertext) && $staged->ciphertext !== '') {
                $adopted = $this->secrets->adoptStaged(
                    IntegrationFamily::Identity->value,
                    'client_secret',
                    $staged->ciphertext,
                    $actorId,
                );

                if (! $adopted) {
                    // Unreachable: candidateFrom() already decrypted it to
                    // build the thing that was verified. Refusing rather than
                    // continuing is still the correct direction.
                    throw new \RuntimeException('The staged identity secret could not be applied.');
                }

                $this->writer->recordSecretChanged(IntegrationFamily::Identity, $actorId);
            }

            // THE PAYLOAD IS DESTROYED THE MOMENT IT HAS BEEN USED.
            $staged->ciphertext = null;
            $staged->save();

            /*
             * THE DEPLOYMENT NOW READS THE STORE, whether or not it did before.
             * A deployment still on `env` that reconfigures here has just said
             * the store is authoritative, and leaving the flag alone would save
             * a configuration that sign-in does not read - the P1-10 correction
             * 4 defect, arriving through a screen that did not exist then.
             */
            $settings = PlatformSetting::forUpdate();
            $settings->identity_source = PlatformSetting::SOURCE_STORE;
            $settings->identity_verified_at = now();
            $settings->identity_committed_at = now();
            $settings->save();

            $this->events->record(SecurityEventLogger::IDENTITY_CONFIGURATION_CUTOVER, [
                // The provider and the outcome. Never a directory identifier,
                // an application identifier or a secret.
                'provider' => 'microsoft',
                'user_id' => $actorId,
                'result' => 'committed',
                'reason' => 'reconfigured',
            ]);

            return true;
        });
    }

    /** A candidate that did not verify. Consume it; change nothing. */
    private function discard(StagedIntegrationChange $staged, int $actorId, HealthStatus $status): void
    {
        DB::transaction(function () use ($staged, $actorId, $status): void {
            StagedIntegrationChange::query()
                ->whereKey($staged->getKey())
                ->whereNull('consumed_at')
                ->update(['consumed_at' => now(), 'ciphertext' => null]);

            $this->events->record(SecurityEventLogger::IDENTITY_CONFIGURATION_CUTOVER, [
                'provider' => 'microsoft',
                'user_id' => $actorId,
                'result' => $status->value,
                'reason' => 'candidate_rejected',
            ]);
        });
    }

    /**
     * The live non-secret values, for pre-filling the form.
     *
     * THE CLIENT SECRET IS NOT HERE AND CANNOT BE. There is no property on this
     * return for it to occupy - the same absence that makes the read-only
     * screen's mask real.
     *
     * @return array<string, string>
     */
    public function currentFields(): array
    {
        $row = IntegrationConfiguration::query()
            ->where('family', IntegrationFamily::Identity->value)
            ->first();

        $stored = is_array($row?->settings) ? $row->settings : [];
        $live = $this->source->resolve();

        return [
            'tenant_id' => (string) ($stored['tenant_id'] ?? $live->tenantId),
            'client_id' => (string) ($stored['client_id'] ?? $live->clientId),
            'redirect_uri' => (string) ($stored['redirect_uri'] ?? $live->redirectUri),
        ];
    }
}
