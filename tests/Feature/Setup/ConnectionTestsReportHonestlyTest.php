<?php

declare(strict_types=1);

namespace Tests\Feature\Setup;

use App\Modules\Platform\Setup\Connections\AiConnectionTester;
use App\Modules\Platform\Setup\Connections\FabricConnectionTester;
use App\Modules\Platform\Setup\IntegrationConfigurationWriter;
use App\Modules\Platform\Setup\IntegrationFamily;
use App\Modules\SystemHealth\Report\HealthStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * T2 and T5. WHAT A TEST IS ALLOWED TO CLAIM.
 *
 * The failure these guard against is a health surface whose worst honest
 * answer is Available - the failure CLAUDE.md section 2 describes, and the one
 * a connection test is most prone to, because "it answered" is so nearly
 * "it works".
 */
final class ConnectionTestsReportHonestlyTest extends TestCase
{
    use RefreshDatabase;

    private function givenAiIsConfigured(): void
    {
        app(IntegrationConfigurationWriter::class)->save(
            IntegrationFamily::Ai,
            ['provider' => 'azure_openai', 'endpoint' => 'https://ai.example.test', 'deployment' => 'gpt'],
            ['api_key' => 'the-key'],
        );
    }

    private function givenFabricIsConfigured(): void
    {
        app(IntegrationConfigurationWriter::class)->save(
            IntegrationFamily::Fabric,
            [
                'tenant_id' => '11111111-2222-3333-4444-555555555555',
                'client_id' => '99999999-8888-7777-6666-555555555555',
                'workspace_id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
            ],
            ['client_secret' => 'the-secret'],
        );
    }

    /**
     * T2. A REFUSED KEY IS Unavailable. Reaching the endpoint proves the
     * network works and nothing about the credential.
     *
     * Mutation: report Available on any response that arrived.
     */
    public function test_t2_a_refused_key_is_unavailable_not_available(): void
    {
        $this->givenAiIsConfigured();

        Http::fake(['*' => Http::response(['error' => 'invalid key sk-abcdef123456'], 401)]);

        $result = app(AiConnectionTester::class)->test();

        $this->assertSame(HealthStatus::Unavailable, $result->status,
            'A 401 was reported as anything other than Unavailable. A TCP/TLS handshake is not '
            .'"AI Available".');

        // ...and the provider's body, which carried a key, reached nothing.
        $this->assertStringNotContainsString('sk-abcdef', $result->explanation);
        $this->assertStringNotContainsString('invalid key', $result->explanation);
    }

    /**
     * T2. A provider whose credentials cannot be validated without real work is
     * NOT CHECKED - never Available.
     *
     * Mutation: fall through to Available for an unrecognised provider.
     */
    public function test_t2_an_unverifiable_provider_is_not_checked(): void
    {
        app(IntegrationConfigurationWriter::class)->save(
            IntegrationFamily::Ai,
            ['provider' => 'some_other_service', 'endpoint' => 'https://ai.example.test'],
            ['api_key' => 'the-key'],
        );

        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        $result = app(AiConnectionTester::class)->test();

        $this->assertSame(HealthStatus::NotChecked, $result->status,
            'A provider SemantIQ cannot check without sending it real work was reported as checked.');

        Http::assertNothingSent();
    }

    /** An incomplete configuration is Not checked, and contacts nobody. */
    public function test_an_incomplete_configuration_contacts_nobody(): void
    {
        app(IntegrationConfigurationWriter::class)->save(
            IntegrationFamily::Ai,
            ['provider' => 'azure_openai'],
        );

        Http::fake();

        $this->assertSame(HealthStatus::NotChecked, app(AiConnectionTester::class)->test()->status);

        Http::assertNothingSent();
    }

    /**
     * T5. A TIMEOUT IS Degraded, NEVER Unavailable - D-156.
     *
     * Mutation: swap the status. "We could not reach it in ten seconds" would
     * then read as "it is broken", and an administrator would re-enter a
     * configuration that was correct.
     */
    public function test_t5_a_timeout_is_degraded_not_unavailable(): void
    {
        $this->givenAiIsConfigured();

        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

        $result = app(AiConnectionTester::class)->test();

        $this->assertSame(HealthStatus::Degraded, $result->status,
            'A timeout was reported as a definitive failure.');
    }

    /** The same rule for Fabric. */
    public function test_t5_a_fabric_timeout_is_degraded(): void
    {
        $this->givenFabricIsConfigured();

        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

        $this->assertSame(HealthStatus::Degraded, app(FabricConnectionTester::class)->test()->status);
    }

    /** T3, at runtime: the only Fabric data request is for the configured workspace. */
    public function test_t3_the_only_fabric_request_is_the_configured_workspace(): void
    {
        $this->givenFabricIsConfigured();

        Http::fake([
            '*oauth2/v2.0/token' => Http::response(['access_token' => 'a-token'], 200),
            '*' => Http::response(['id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee'], 200),
        ]);

        $result = app(FabricConnectionTester::class)->test();

        $this->assertSame(HealthStatus::Available, $result->status);

        $fabricCalls = [];

        Http::recorded(function ($request) use (&$fabricCalls) {
            if (str_contains($request->url(), 'api.fabric.microsoft.com')) {
                $fabricCalls[] = $request->url();
            }

            return true;
        });

        $this->assertSame(
            ['https://api.fabric.microsoft.com/v1/workspaces/aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee'],
            $fabricCalls,
            'The Fabric test made a request other than the one configured workspace.',
        );
    }

    /** A refused token names no directory, application or secret. */
    public function test_a_refused_fabric_token_leaks_nothing(): void
    {
        $this->givenFabricIsConfigured();

        Http::fake([
            '*oauth2/v2.0/token' => Http::response([
                'error' => 'invalid_client',
                'error_description' => 'AADSTS7000215: Invalid client secret provided for app 99999999-8888-7777-6666-555555555555.',
            ], 401),
        ]);

        $result = app(FabricConnectionTester::class)->test();

        $this->assertSame(HealthStatus::Unavailable, $result->status);

        foreach (['AADSTS', '99999999', 'the-secret', 'invalid_client'] as $leak) {
            $this->assertStringNotContainsString($leak, $result->explanation,
                "The explanation carries [{$leak}] from the provider's own error body.");
        }
    }

    /** No explanation from any adapter reads like an internal message. */
    public function test_no_explanation_is_developer_language(): void
    {
        $this->givenAiIsConfigured();
        $this->givenFabricIsConfigured();

        Http::fake(['*' => Http::response('', 500)]);

        foreach ([AiConnectionTester::class, FabricConnectionTester::class] as $tester) {
            $explanation = app($tester)->test()->explanation;

            foreach (['Exception', 'cURL', 'HTTP 5', 'null', 'stack', 'Illuminate\\'] as $leak) {
                $this->assertStringNotContainsString($leak, $explanation,
                    "[{$tester}] returned developer language: {$explanation}");
            }

            $this->assertMatchesRegularExpression('/^[A-Z].*\.$/', $explanation,
                "[{$tester}] returned a sentence that is not written as one: {$explanation}");
        }
    }
}
