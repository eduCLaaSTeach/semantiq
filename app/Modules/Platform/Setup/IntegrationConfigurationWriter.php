<?php

declare(strict_types=1);

namespace App\Modules\Platform\Setup;

use App\Modules\Identity\Health\IdentityHealthCheck;
use App\Modules\Platform\Security\SecurityEventLogger;
use App\Modules\Platform\Setup\Identity\IdentityConfigurationSource;
use App\Modules\Platform\Setup\Models\IntegrationConfiguration;
use App\Modules\Platform\Setup\Models\PlatformSetting;
use App\Modules\Platform\Setup\Secrets\IntegrationSecretStore;
use App\Modules\SystemHealth\Report\HealthStatus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * THE one place an integration's configuration or secret is written.
 *
 * Everything that makes a stored result trustworthy happens here, in one
 * transaction, or it does not happen:
 *
 *   - the typed fields are validated against the family's closed allowlist;
 *   - a meaningful change moves `status` back to Not checked;
 *   - `last_tested_at` is CLEARED, not kept "for reference";
 *   - for identity, the configuration revision is incremented, which makes the
 *     previous tenant's cached health result unreadable.
 *
 * WHY last_tested_at IS CLEARED RATHER THAN KEPT. A timestamp that outlives
 * the configuration it describes is worse than no timestamp: it is accurate,
 * and the claim it appears to support - that THIS configuration was tested at
 * that time - is false. It is the same failure P1-09 corrected in
 * storedReport(), where a stored state without a usable age could present as
 * current. Keeping it would put the defect back through a different door.
 *
 * ONLY A TEST MAY WRITE A POSITIVE STATUS. Nothing in this class can set
 * Available, Degraded or Unavailable; recordTestResult() is the only method
 * that writes them, and it is called by the connection tests alone. That is
 * what OnlyATestSetsAPositiveStatus asserts, and it is why a save can never
 * leave a card looking green by accident.
 */
final class IntegrationConfigurationWriter
{
    public function __construct(
        private readonly IntegrationSecretStore $secrets,
        private readonly IdentityConfigurationSource $identityConfiguration,
        private readonly SecurityEventLogger $events,
    ) {}

    /**
     * Save typed fields and, optionally, secrets.
     *
     * @param  array<string, scalar|null>  $fields
     * @param  array<string, string>  $secrets  name => plaintext. Absent means unchanged.
     */
    public function save(IntegrationFamily $family, array $fields, array $secrets = [], ?int $actorId = null): void
    {
        /*
         * THE EVIDENCE IS RECORDED IN HERE, NOT BY THE CALLER.
         *
         * The first version left integration.configuration.changed in the
         * controller, one line after this method returned - outside the
         * transaction, so a failed audit write could not roll the configuration
         * change back. P1-08's static atomicity guard caught it, which is
         * precisely the case that guard exists for: nothing about the
         * controller LOOKED wrong.
         *
         * Putting it here also means every caller gets it. A second surface -
         * First-Run alongside Platform Integrations - cannot be the one that
         * forgets, because there is nothing for it to remember.
         */
        $this->assertFieldsAreAllowed($family, $fields);
        $this->assertSecretsAreAllowed($family, $secrets);

        DB::transaction(function () use ($family, $fields, $secrets, $actorId): void {
            $row = IntegrationConfiguration::query()->firstOrCreate(
                ['family' => $family->value],
                ['settings' => [], 'status' => HealthStatus::NotChecked->value],
            );

            $existing = is_array($row->settings) ? $row->settings : [];
            $merged = [...$existing, ...$fields];

            $meaningful = $secrets !== [] || $this->aMeaningfulFieldChanged($family, $existing, $merged);

            $row->settings = $merged;
            $row->last_changed_at = now();
            $row->last_changed_by_user_id = $actorId;

            if ($meaningful) {
                // BOTH, TOGETHER. A status without its timestamp, or a
                // timestamp without its status, is half an answer.
                $row->status = HealthStatus::NotChecked->value;
                $row->explanation = null;
                $row->last_tested_at = null;
            }

            $row->save();

            foreach ($secrets as $name => $value) {
                $this->secrets->put($family->value, $name, $value, $actorId);
            }

            if ($meaningful && $family === IntegrationFamily::Identity) {
                $this->invalidateIdentityHealth();
            }

            $this->events->record(SecurityEventLogger::INTEGRATION_CONFIGURATION_CHANGED, [
                // The FAMILY, which is a configuration choice and not a
                // credential. No host, no endpoint, no identifier, and no key
                // in ALLOWED_KEYS that one could occupy.
                'provider' => $family->value,
                'user_id' => $actorId,
                'result' => 'changed',
            ]);
        });

        // AFTER the transaction commits, because a memoised value discarded
        // inside one that then rolls back would be discarded for nothing.
        $this->identityConfiguration->forget();
    }

    /**
     * A SECRET CHANGED OUTSIDE save() - the step-up completion path.
     *
     * Replacing or removing a credential through D-159's two-stage flow does
     * not go through save(): the value was staged before the administrator left
     * for Microsoft, and is applied by StagedChangeStore when they come back.
     * The invalidation still has to happen, and it has to happen HERE rather
     * than in the completion handler, so there is one place that knows what a
     * meaningful change does to a stored result.
     *
     * Without this, a credential replaced through step-up would leave the
     * previous Available and its timestamp standing - Correction 5's defect
     * reappearing through a door Correction 5 did not know about.
     *
     * MUST run inside the caller's transaction, which is the one consuming the
     * step-up reference.
     */
    public function recordSecretChanged(IntegrationFamily $family, ?int $actorId = null): void
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException(
                'recordSecretChanged() must run inside the transaction that applies the change.',
            );
        }

        $row = IntegrationConfiguration::query()->firstOrCreate(
            ['family' => $family->value],
            ['settings' => [], 'status' => HealthStatus::NotChecked->value],
        );

        $row->status = HealthStatus::NotChecked->value;
        $row->explanation = null;
        $row->last_tested_at = null;
        $row->last_changed_at = now();
        $row->last_changed_by_user_id = $actorId;
        $row->save();

        if ($family === IntegrationFamily::Identity) {
            $this->invalidateIdentityHealth();
        }
    }

    /**
     * FIELDS CONFIRMED THROUGH STEP-UP - Gate C round 3.
     *
     * The destination half of a staged reconfiguration. It is separate from
     * save() for the same reason recordSecretChanged() is: save() opens its own
     * transaction, and this must run INSIDE the one consuming the step-up, so
     * that a lost race takes the destination change back with the confirmation
     * that authorised it.
     *
     * It does not re-derive "was this meaningful". A change that reached here
     * came through a confirmation, which is only ever demanded for a change
     * that moves where a credential is sent - so the stored result is withdrawn
     * unconditionally.
     *
     * @param  array<string, scalar|null>  $fields
     */
    public function applyFields(IntegrationFamily $family, array $fields, ?int $actorId = null): void
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException(
                'applyFields() must run inside the transaction that applies the change.',
            );
        }

        $this->assertFieldsAreAllowed($family, $fields);

        $row = IntegrationConfiguration::query()->firstOrCreate(
            ['family' => $family->value],
            ['settings' => [], 'status' => HealthStatus::NotChecked->value],
        );

        $existing = is_array($row->settings) ? $row->settings : [];

        $row->settings = [...$existing, ...$fields];
        $row->status = HealthStatus::NotChecked->value;
        $row->explanation = null;
        $row->last_tested_at = null;
        $row->last_changed_at = now();
        $row->last_changed_by_user_id = $actorId;
        $row->save();

        if ($family === IntegrationFamily::Identity) {
            $this->invalidateIdentityHealth();
        }

        $this->identityConfiguration->forget();
    }

    /**
     * The ONLY writer of a positive status.
     *
     * It takes a HealthStatus and a CHOSEN sentence from the adapter that ran
     * the test. Nothing here catches a provider error: provider error bodies
     * routinely echo credentials, so what reaches this method has already been
     * turned into one of the adapter's own declared sentences.
     */
    public function recordTestResult(IntegrationFamily $family, HealthStatus $status, string $explanation): void
    {
        $row = IntegrationConfiguration::query()->firstOrCreate(
            ['family' => $family->value],
            ['settings' => [], 'status' => HealthStatus::NotChecked->value],
        );

        $row->status = $status->value;
        $row->explanation = $explanation;
        $row->last_tested_at = now();
        $row->save();
    }

    /**
     * Increment the revision, and forget the old keys as housekeeping only.
     *
     * THE INCREMENT IS THE MECHANISM. The forget() below is a courtesy that
     * keeps a file cache from accumulating dead entries; nothing depends on it
     * succeeding, which is the whole point. A cache that silently ignores
     * forget() - or fails to unlink - still cannot serve the previous tenant's
     * result, because after the increment nobody asks for that key again.
     */
    private function invalidateIdentityHealth(): void
    {
        $settings = PlatformSetting::forUpdate();
        $previous = $settings->identity_config_revision;

        $settings->identity_config_revision = $previous + 1;
        $settings->save();

        Cache::forget(IdentityHealthCheck::LAST_RESULT_KEY_PREFIX.':'.$previous);
        Cache::forget(IdentityHealthCheck::LAST_PROBE_KEY_PREFIX.':'.$previous);
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    private function aMeaningfulFieldChanged(IntegrationFamily $family, array $before, array $after): bool
    {
        foreach ($family->meaningfulFields() as $field) {
            if (($before[$field] ?? null) !== ($after[$field] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, scalar|null>  $fields
     */
    private function assertFieldsAreAllowed(IntegrationFamily $family, array $fields): void
    {
        $unknown = array_diff(array_keys($fields), $family->fields());

        if ($unknown !== []) {
            // The KEY NAMES, which the operator chose. Never a value: a
            // refusal that echoes what was submitted is a refusal that can be
            // made to echo a secret.
            throw new InvalidArgumentException(
                sprintf('[%s] has no field [%s].', $family->value, implode(', ', $unknown)),
            );
        }
    }

    /**
     * @param  array<string, string>  $secrets
     */
    private function assertSecretsAreAllowed(IntegrationFamily $family, array $secrets): void
    {
        $unknown = array_diff(array_keys($secrets), $family->secrets());

        if ($unknown !== []) {
            throw new InvalidArgumentException(
                sprintf('[%s] has no secret named [%s].', $family->value, implode(', ', $unknown)),
            );
        }
    }
}
