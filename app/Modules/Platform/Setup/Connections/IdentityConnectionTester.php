<?php

declare(strict_types=1);

namespace App\Modules\Platform\Setup\Connections;

use App\Modules\Platform\Setup\Identity\IdentityConfigurationSource;
use App\Modules\Platform\Setup\Identity\ProviderProbe;
use App\Modules\Platform\Setup\IntegrationFamily;
use App\Modules\Platform\Setup\Models\PlatformSetting;

/**
 * Identity, through the SAME probe the cutover uses.
 *
 * NOT A SECOND PROBE. P1-02 owns identity health and P1-10 must not grow a
 * rival answer to the same question - two probes would mean two cache
 * namespaces, two timeout rules and two chances for a screen to disagree with
 * the sign-in path.
 *
 * IT TESTS THE CANDIDATE, NOT WHAT IS IN FORCE. During First-Run the
 * configuration has been typed and saved but the authority has not moved yet,
 * so testing what is in force would test the empty .env and report a correct
 * configuration as broken.
 */
final class IdentityConnectionTester implements ConnectionTester
{
    public function __construct(
        private readonly IdentityConfigurationSource $source,
        private readonly ProviderProbe $probe,
    ) {}

    public function family(): IntegrationFamily
    {
        return IntegrationFamily::Identity;
    }

    public function test(): ConnectionResult
    {
        $settings = PlatformSetting::current();

        // Post-cutover the store IS what is in force, so the two agree. Before
        // it, the candidate is the only thing worth testing.
        $configuration = $settings->identityReadsStore()
            ? $this->source->resolve()
            : $this->source->storedCandidate();

        $result = $this->probe->verify($configuration);

        return new ConnectionResult($result['status'], $result['explanation']);
    }
}
