<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Modules\Platform\Support\ConfigurationRequirements;
use App\Modules\Platform\Support\ConfigurationValidator;
use Tests\TestCase;

final class ConfigurationValidatorTest extends TestCase
{
    public function test_a_complete_configuration_is_valid(): void
    {
        $this->assertTrue(app(ConfigurationValidator::class)->isValid());
    }

    public function test_a_missing_required_key_is_a_problem(): void
    {
        config(['app.key' => '']);

        $problems = app(ConfigurationValidator::class)->problems();

        $this->assertNotEmpty($problems);
        $this->assertStringContainsString('app.key', implode(' ', $problems));
    }

    public function test_a_problem_names_the_key_but_never_its_value(): void
    {
        config(['app.url' => '']);

        $problems = implode(' ', app(ConfigurationValidator::class)->problems());

        $this->assertStringContainsString('app.url', $problems);
        $this->assertStringNotContainsString((string) config('app.key'), $problems);
    }

    public function test_debug_is_rejected_in_production(): void
    {
        config(['app.env' => 'production', 'app.debug' => true]);

        $problems = implode(' ', app(ConfigurationValidator::class)->problems());

        $this->assertStringContainsString('APP_DEBUG', $problems);
    }

    /**
     * Correction 4, still true after P1-00 activated these keys: absent
     * Microsoft configuration must not stop a non-production environment
     * booting, and no fake value may be invented to satisfy the validator.
     *
     * The keys moved from config/semantiq.php to config/identity.php when
     * P1-00 activated them - one file, one owner.
     */
    public function test_absent_microsoft_configuration_is_not_a_problem_outside_production(): void
    {
        config([
            'app.env' => 'testing',
            'identity.microsoft.tenant_id' => null,
            'identity.microsoft.client_id' => null,
            'identity.microsoft.client_secret' => null,
            'identity.microsoft.redirect_uri' => null,
        ]);

        $this->assertTrue(
            app(ConfigurationValidator::class)->isValid(),
            'Validation failed without identity configuration outside production.'
        );
    }

    public function test_the_active_connection_is_validated_not_a_hardcoded_driver(): void
    {
        config(['database.connections.sqlite.database' => '']);

        $problems = implode(' ', app(ConfigurationValidator::class)->problems());

        $this->assertStringContainsString('database.connections.sqlite.database', $problems);
    }

    /**
     * P1-00 promoted the Microsoft keys from declared to required-in-production.
     * A production deployment missing them must fail at boot, not at a user's
     * first sign-in attempt.
     */
    public function test_identity_keys_are_required_in_production(): void
    {
        config([
            'app.env' => 'production',
            'app.debug' => false,
            'identity.microsoft.tenant_id' => '',
            'identity.microsoft.client_id' => '',
            'identity.microsoft.client_secret' => '',
            'identity.microsoft.redirect_uri' => '',
        ]);

        $problems = implode(' ', app(ConfigurationValidator::class)->problems());

        foreach (ConfigurationRequirements::requiredInProduction() as $key) {
            $this->assertStringContainsString($key, $problems, "[{$key}] must be required in production.");
        }
    }

    /**
     * And NOT required elsewhere. CI and developer machines have no Entra
     * tenant, and inventing placeholder values to satisfy the validator would
     * move the failure from boot, where it is obvious, to the identity
     * provider, where it is not.
     */
    public function test_identity_keys_are_not_required_outside_production(): void
    {
        config([
            'app.env' => 'testing',
            'identity.microsoft.tenant_id' => '',
            'identity.microsoft.client_id' => '',
            'identity.microsoft.client_secret' => '',
            'identity.microsoft.redirect_uri' => '',
        ]);

        $problems = implode(' ', app(ConfigurationValidator::class)->problems());

        $this->assertStringNotContainsString('identity.microsoft', $problems);
    }
}
