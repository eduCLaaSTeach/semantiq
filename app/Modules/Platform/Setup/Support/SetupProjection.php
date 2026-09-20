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
        $status = HealthStatus::tryFrom((string) $row?->status) ?? HealthStatus::NotChecked;

        $fields = [];

        // THE ALLOWLIST DECIDES WHAT IS SENT, not whatever the row happens to
        // hold. A field written before an allowlist changed cannot leak out
        // through a projection that simply forwards the column.
        foreach ($family->fields() as $field) {
            $value = $settings[$field] ?? null;
            $fields[$field] = is_scalar($value) ? $value : null;
        }

        $secrets = [];

        foreach ($family->secrets() as $name) {
            // PRESENCE, NEVER THE VALUE.
            $secrets[$name] = $this->secrets->has($family->value, $name);
        }

        return new IntegrationView(
            family: $family->value,
            name: $family->inWords(),
            status: $status->value,
            statusInWords: IntegrationView::statusInWords($status),
            explanation: $row?->explanation,
            fields: $fields,
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
