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
     * Correction 4. P1-BASE must not refuse to boot because P1-00 Microsoft
     * configuration is absent, and no fake value may be invented to satisfy it.
     */
    public function test_absent_microsoft_configuration_is_not_a_p1_base_problem(): void
    {
        config([
            'semantiq.identity.microsoft.tenant_id' => null,
            'semantiq.identity.microsoft.client_id' => null,
            'semantiq.identity.microsoft.client_secret' => null,
            'semantiq.identity.microsoft.redirect_uri' => null,
        ]);

        $this->assertTrue(
            app(ConfigurationValidator::class)->isValid(),
            'P1-BASE refused to validate without P1-00 identity configuration.'
        );
    }

    public function test_the_active_connection_is_validated_not_a_hardcoded_driver(): void
    {
        config(['database.connections.sqlite.database' => '']);

        $problems = implode(' ', app(ConfigurationValidator::class)->problems());

        $this->assertStringContainsString('database.connections.sqlite.database', $problems);
    }

    public function test_microsoft_keys_are_declared_and_owned_by_p1_00(): void
    {
        foreach (ConfigurationRequirements::declared() as $key => $owner) {
            $this->assertSame('P1-00', $owner, "Declared key [{$key}] should be owned by P1-00.");
            $this->assertNotContains($key, ConfigurationRequirements::required());
        }
    }
}
