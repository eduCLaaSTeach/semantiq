<?php

declare(strict_types=1);

namespace App\Modules\Platform\Setup\Connections;

use App\Modules\Platform\Setup\IntegrationFamily;
use App\Modules\Platform\Setup\Models\IntegrationConfiguration;
use App\Modules\Platform\Setup\Secrets\IntegrationSecretStore;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * AI. THE CHEAPEST AUTHENTICATED METADATA CALL, AND NOTHING ELSE.
 *
 * NO INFERENCE. NO PROMPT. NO COMPLETION. NO EMBEDDING. NO TOKEN GENERATION.
 * This class names none of those paths, which is what AiTestPerformsNoInference
 * asserts over the source - a runtime test can only show that nothing happened
 * on the paths it exercised, and the guarantee has to survive a refactor no
 * runtime test covers.
 *
 * "Just a tiny test prompt" is always available and always looks harmless.
 * It is inference, it costs money on every press of a Test button, and it is
 * the first step of exactly the capability this unit is defined as not having.
 *
 * A TCP/TLS HANDSHAKE IS NOT "AI AVAILABLE" - D-175. Reaching an endpoint
 * proves the network works. It proves nothing about the key. So a 401 is
 * Unavailable, and a provider whose credentials CANNOT be validated without
 * doing real work is reported as NOT CHECKED - the honest answer, and the one
 * the ruling explicitly names rather than leaving to judgement.
 */
final class AiConnectionTester implements ConnectionTester
{
    private const TIMEOUT_SECONDS = 10;

    /**
     * Providers whose credentials can be checked with a metadata read.
     *
     * A CLOSED LIST. A provider that is not here is Not checked, not
     * "probably fine": the alternative is inventing a probe for an API whose
     * shape nobody has read, which is how a "test" ends up calling something
     * that charges.
     */
    private const METADATA_PATHS = [
        'azure_openai' => '/openai/deployments?api-version=2023-05-15',
        'openai' => '/v1/models',
    ];

    public function __construct(private readonly IntegrationSecretStore $secrets) {}

    public function family(): IntegrationFamily
    {
        return IntegrationFamily::Ai;
    }

    public function test(): ConnectionResult
    {
        $settings = $this->settings();

        $provider = (string) ($settings['provider'] ?? '');
        $endpoint = rtrim((string) ($settings['endpoint'] ?? ''), '/');
        $apiKey = (string) $this->secrets->get(IntegrationFamily::Ai->value, 'api_key');

        if ($endpoint === '' || $apiKey === '') {
            return ConnectionResult::notChecked(
                'The AI service address and key have not both been entered yet.',
            );
        }

        $path = self::METADATA_PATHS[$provider] ?? null;

        if ($path === null) {
            return ConnectionResult::notChecked(
                'This AI service cannot be checked without sending it real work, so SemantIQ does not check it.',
            );
        }

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->withHeaders($this->authHeaders($provider, $apiKey))
                ->get($endpoint.$path);
        } catch (Throwable) {
            // The caught value is discarded entirely. An HTTP client exception
            // carries the full URL, which carries the endpoint and sometimes a
            // key in a query string.
            return ConnectionResult::degraded(
                'The AI service did not answer within ten seconds.',
            );
        }

        if ($response->status() === 401 || $response->status() === 403) {
            return ConnectionResult::unavailable(
                'The AI service refused the key entered here.',
            );
        }

        if (! $response->successful()) {
            return ConnectionResult::unavailable(
                'The AI service did not answer as expected at the address entered here.',
            );
        }

        return ConnectionResult::available(
            'The AI service accepted the key entered here. No content was sent or generated.',
        );
    }

    /** @return array<string, string> */
    private function authHeaders(string $provider, string $apiKey): array
    {
        return $provider === 'azure_openai'
            ? ['api-key' => $apiKey]
            : ['Authorization' => 'Bearer '.$apiKey];
    }

    /** @return array<string, mixed> */
    private function settings(): array
    {
        $row = IntegrationConfiguration::query()
            ->where('family', IntegrationFamily::Ai->value)
            ->first();

        return is_array($row?->settings) ? $row->settings : [];
    }
}
