<?php

declare(strict_types=1);

namespace App\Modules\Platform\Setup\Support;

use App\Modules\Platform\Setup\Identity\IdentityConfigurationSource;
use App\Modules\Platform\Setup\IntegrationFamily;
use App\Modules\Platform\Setup\Models\IntegrationConfiguration;
use App\Modules\Platform\Setup\Secrets\IntegrationSecretStore;
use App\Modules\SystemHealth\Report\HealthStatus;

/**
 * The read model for both surfaces - First-Run and Platform Integrations.
 *
 * ONE PROJECTION, TWO SCREENS. The alternative is two read models that agree
 * today: a status shown one way during setup and another way afterwards is a
 * difference nobody designed and nobody would notice until a customer asked
 * why the same integration reads differently in two places.
 *
 * IT READS THE STORE AND NEVER DECRYPTS. Presence comes from
 * IntegrationSecretStore::has(), which answers without touching the key - so a
 * listing cannot fail because APP_KEY changed, and cannot leak because it
 * never held a value.
 */
final class SetupProjection
{
    /**
     * WHICH FAMILIES MUST BE CONFIGURED BEFORE SETUP CAN FINISH - D-167, D-171.
     *
     * Identity alone. Email, AI and Fabric are optional and saying so on every
     * step is the point: an administrator who cannot tell which are required
     * either stops at the first one they lack, or configures all four to be
     * safe. Both waste a setup session.
     */
    private const REQUIRED = [IntegrationFamily::Identity->value];

    public function __construct(
        private readonly IntegrationSecretStore $secrets,
        private readonly IdentityConfigurationSource $identityConfiguration,
    ) {}

    public function forFamily(IntegrationFamily $family): IntegrationView
    {
        $row = IntegrationConfiguration::query()->where('family', $family->value)->first();

        $settings = is_array($row?->settings) ? $row->settings : [];

        /*
         * NOT CONFIGURED IS NOT NOT CHECKED, and the difference is the whole of
         * Gate C correction 4A.
         *
         *   Not configured  nothing has been entered, or a field the provider
         *                   needs is missing. There is nothing to check.
         *   Not checked     a complete configuration exists and nobody has
         *                   tested it yet.
         *
         * The first draft defaulted an absent row to Not checked, which reads
         * as "somebody should press Test" for an integration that has never
         * been set up - and leaves an administrator looking for a test button
         * to explain a state that has nothing to do with testing.
         *
         * IT IS DERIVED, NOT STORED. A stored status would have to be
         * recalculated on every write and would drift the first time somebody
         * forgot; asking whether the configuration is complete cannot drift,
         * because completeness IS the question. It also means a meaningful edit
         * that REMOVES a required field lands on Not configured without
         * anything having to notice that it was a removal.
         *
         * AN INCOMPLETE CONFIGURATION NEVER REPORTS A POSITIVE RESULT, whatever
         * the row says - and that is a correction found by mutation testing
         * rather than by design.
         *
         * The first version only derived when the stored status was already
         * NotChecked, reasoning that a real test result must win. Mutating that
         * guard away changed no test, because it protects a state the writer
         * already prevents: every write invalidates the stored status, so
         * "complete test result" and "incomplete configuration" cannot normally
         * be true together.
         *
         * NORMALLY. If they ever ARE - a direct database edit, a restored
         * backup, or a future write path that forgets to invalidate - the old
         * version displayed a stale "Available" beside a configuration missing
         * a required field, which is the single most misleading thing this
         * screen could say. The mutant was better than the original, so the
         * mutant is now the code.
         *
         * NOT APPLICABLE IS THE ONE EXCEPTION, and it is explicit. It means the
         * check cannot apply to this deployment at all, which is a product
         * statement rather than a report about the configuration - overriding
         * it with "Not configured" would tell an administrator to go and fill
         * in something that is deliberately absent.
         */
        $stored = HealthStatus::tryFrom((string) $row?->status) ?? HealthStatus::NotChecked;

        $status = match (true) {
            $stored === HealthStatus::NotApplicable => $stored,
            ! $this->isConfigured($family) => HealthStatus::NotConfigured,
            default => $stored,
        };

        $fields = [];
        $choices = [];

        // THE ALLOWLIST DECIDES WHAT IS SENT, not whatever the row happens to
        // hold. A field written before an allowlist changed cannot leak out
        // through a projection that simply forwards the column.
        foreach ($family->fields() as $field) {
            $value = $settings[$field] ?? null;
            $fields[$field] = is_scalar($value) ? $value : null;

            $options = $family->choices($field);

            if ($options !== []) {
                $choices[$field] = $options;
            }
        }

        $secrets = [];

        foreach ($family->secrets() as $name) {
            // PRESENCE, NEVER THE VALUE.
            $secrets[$name] = $this->secrets->has($family->value, $name);
        }

        return new IntegrationView(
            family: $family->value,
            name: $family->inWords(),
            describedAs: $family->describedAs(),
            status: $status->value,
            statusInWords: IntegrationView::statusInWords($status),
            explanation: $row?->explanation,
            fields: $fields,
            choices: $choices,
            secrets: $secrets,
            lastTestedAt: $row?->last_tested_at?->toIso8601String(),
            lastChangedAt: $row?->last_changed_at?->toIso8601String(),
            required: in_array($family->value, self::REQUIRED, true),
        );
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return array_map(
            fn (IntegrationFamily $family): array => $this->forFamily($family)->toArray(),
            IntegrationFamily::cases(),
        );
    }

    /**
     * The step list both First-Run screens render.
     *
     * @return list<array<string, mixed>>
     */
    public function steps(): array
    {
        $steps = [];

        foreach (IntegrationFamily::cases() as $family) {
            $view = $this->forFamily($family);

            $steps[] = [
                'family' => $family->value,
                'name' => $family->inWords(),
                'required' => $view->required,
                'status' => $view->status,
                'statusInWords' => $view->statusInWords,
                'configured' => $this->isConfigured($family),
            ];
        }

        return $steps;
    }

    /**
     * Is Microsoft sign-in ready for a nominated administrator to use?
     *
     * CONFIGURED IS NOT THE SAME AS TESTED, AND NEITHER IS "READY". This asks
     * the RESOLVED source whether every value is present - the same source
     * /auth/microsoft reads - rather than whether a row exists. A row with a
     * blank tenant would satisfy "configured" and produce a sign-in that
     * cannot start.
     */
    public function identityIsReady(): bool
    {
        return $this->identityConfiguration->resolve()->isComplete();
    }

    private function isConfigured(IntegrationFamily $family): bool
    {
        /*
         * IDENTITY IS ASKED OF THE AUTHORITY, NOT OF A ROW - AND THIS WAS A
         * DEFECT FOUND BY DEPLOYING.
         *
         * Every other family is configured exactly when its row holds every
         * meaningful field and its secret exists. Identity is not, because
         * identity has TWO possible authorities and the row is only one of
         * them. Until the controlled cutover a deployment reads Microsoft
         * sign-in from the environment, where there is no row at all.
         *
         * So the row test said NOT CONFIGURED for the one deployment whose
         * sign-in demonstrably works - production - while people were signing
         * in through it. A false red on a working system is worse than no
         * status: it sends an administrator to re-enter a configuration that
         * was correct, on the single screen where doing that locks everybody
         * out. ProviderProbe carries the same warning about the same class of
         * mistake.
         *
         * It survived Gate C because the local server used for the browser
         * verification had no Microsoft configuration either, so "Not
         * configured" was the right answer there for the wrong reason - the
         * exact shape CLAUDE.md section 2 names: a test that passes for a
         * reason unrelated to what it claims to check.
         *
         * EITHER AUTHORITY COUNTS, and both are needed:
         *
         *   resolve()          what is IN FORCE. Production, pre-cutover, and
         *                      any deployment after it.
         *   storedCandidate()  what has been TYPED AND SAVED. First-Run, where
         *                      the administrator has just entered Microsoft
         *                      details and the environment is still empty
         *                      because the cutover has not happened yet.
         *
         * Using resolve() alone would tell an administrator mid-setup that the
         * details they just saved are not configured. Using the row alone is
         * the defect above. IdentityConfigurationSource's own docblock draws
         * this distinction; this is the one caller that needs both sides of it.
         *
         * THIS DOES NOT MAKE ANYTHING REPORT A POSITIVE RESULT. It only stops
         * a complete configuration being called incomplete. Whether sign-in
         * actually WORKS is still the stored status - Not checked until
         * somebody checks - and forFamily() still forces Not configured
         * whenever this returns false.
         */
        if ($family === IntegrationFamily::Identity) {
            return $this->identityConfiguration->resolve()->isComplete()
                || $this->identityConfiguration->storedCandidate()->isComplete();
        }

        $row = IntegrationConfiguration::query()->where('family', $family->value)->first();

        if ($row === null) {
            return false;
        }

        $settings = is_array($row->settings) ? $row->settings : [];

        foreach ($family->meaningfulFields() as $field) {
            if (($settings[$field] ?? '') === '' || ($settings[$field] ?? null) === null) {
                return false;
            }
        }

        foreach ($family->secrets() as $name) {
            if (! $this->secrets->has($family->value, $name)) {
                return false;
            }
        }

        return true;
    }
}
