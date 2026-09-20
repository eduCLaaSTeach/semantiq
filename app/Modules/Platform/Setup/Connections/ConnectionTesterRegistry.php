<?php

declare(strict_types=1);

namespace App\Modules\Platform\Setup\Connections;

use App\Modules\Platform\Setup\IntegrationFamily;

/**
 * Maps a family to its tester. A match on the enum, so a fifth family added
 * without a tester fails to compile rather than silently reporting nothing.
 */
final class ConnectionTesterRegistry
{
    public function __construct(
        private readonly EmailConnectionTester $email,
        private readonly AiConnectionTester $ai,
        private readonly FabricConnectionTester $fabric,
        private readonly IdentityConnectionTester $identity,
    ) {}

    public function for(IntegrationFamily $family): ConnectionTester
    {
        return match ($family) {
            IntegrationFamily::Email => $this->email,
            IntegrationFamily::Ai => $this->ai,
            IntegrationFamily::Fabric => $this->fabric,
            IntegrationFamily::Identity => $this->identity,
        };
    }
}
