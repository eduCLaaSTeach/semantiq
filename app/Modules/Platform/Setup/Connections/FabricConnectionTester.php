<?php

declare(strict_types=1);

namespace App\Modules\Platform\Setup\Connections;

use App\Modules\Platform\Setup\IntegrationFamily;
use App\Modules\Platform\Setup\Models\IntegrationConfiguration;
use App\Modules\Platform\Setup\Secrets\IntegrationSecretStore;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * FABRIC. A client-credentials token, then METADATA FOR THE ONE CONFIGURED
 * WORKSPACE.
 *
 * NO BUSINESS DATA IS READ AND NONE CAN BE - D-177. No DAX, no SQL, no table,
 * no dataset, no semantic model, no lakehouse, no item enumeration, no
 * ingestion, and no create, update or delete. FabricTestReadsNoBusinessData
 * asserts those names appear nowhere in this module.
 *
 * ONE WORKSPACE, NAMED IN ADVANCE, AND NEVER A LIST. Enumerating workspaces is
 * the tempting version - it "proves more" - and it is a directory read of the
 * customer's entire analytics estate performed by a setup screen. The
 * configured workspace id goes into the URL path; there is no code here that
 * could request the collection.
 *
 * WHY THIS PROVES ENOUGH. A 200 for the configured workspace proves the token
 * was issued for the right directory, the application has been granted access,
 * and the workspace exists. That is precisely what a person needs to know
 * before configuring anything on top of it.
 */
final class FabricConnectionTester implements ConnectionTester
{
    private const TIMEOUT_SECONDS = 10;

    private const SCOPE = 'https://api.fabric.microsoft.com/.default';

    public function __construct(private readonly IntegrationSecretStore $secrets) {}

    public function family(): IntegrationFamily
    {
        return IntegrationFamily::Fabric;
    }

    public function test(): ConnectionResult
    {
        $settings = $this->settings();

        $tenant = (string) ($settings['tenant_id'] ?? '');
        $client = (string) ($settings['client_id'] ?? '');
        $workspace = (string) ($settings['workspace_id'] ?? '');
        $secret = (string) $this->secrets->get(IntegrationFamily::Fabric->value, 'client_secret');

        if ($tenant === '' || $client === '' || $workspace === '' || $secret === '') {
            return ConnectionResult::notChecked(
                'The Fabric directory, application, workspace and secret have not all been entered yet.',
            );
        }

        try {
            $token = Http::timeout(self::TIMEOUT_SECONDS)->asForm()->post(
                "https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token",
                [
                    'grant_type' => 'client_credentials',
                    'client_id' => $client,
                    'client_secret' => $secret,
                    'scope' => self::SCOPE,
                ],
            );
        } catch (Throwable) {
            return ConnectionResult::degraded('Microsoft did not answer within ten seconds.');
        }

        if (! $token->successful()) {
            // The body of a failed token request names the application and
            // sometimes the reason in terms that identify the directory. None
            // of it is returned.
            return ConnectionResult::unavailable(
                'Microsoft refused the directory, application and secret entered here.',
            );
        }

        $accessToken = (string) ($token->json()['access_token'] ?? '');

        if ($accessToken === '') {
            return ConnectionResult::unavailable('Microsoft did not return a usable token.');
        }

        try {
            // ONE WORKSPACE, BY ID. There is no request in this class that
            // could return a list.
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->withToken($accessToken)
                ->get("https://api.fabric.microsoft.com/v1/workspaces/{$workspace}");
        } catch (Throwable) {
            return ConnectionResult::degraded('Fabric did not answer within ten seconds.');
        }

        if ($response->status() === 401 || $response->status() === 403) {
            return ConnectionResult::unavailable(
                'The application has not been given access to the workspace entered here.',
            );
        }

        if ($response->status() === 404) {
            return ConnectionResult::unavailable(
                'No workspace with the identifier entered here could be found.',
            );
        }

        if (! $response->successful()) {
            return ConnectionResult::unavailable('Fabric did not answer as expected for this workspace.');
        }

        return ConnectionResult::available(
            'Fabric confirmed the workspace entered here. No business data was read.',
        );
    }

    /** @return array<string, mixed> */
    private function settings(): array
    {
        $row = IntegrationConfiguration::query()
            ->where('family', IntegrationFamily::Fabric->value)
            ->first();

        return is_array($row?->settings) ? $row->settings : [];
    }
}
