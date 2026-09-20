<?php

declare(strict_types=1);

namespace App\Modules\Platform\Setup\Connections;

use App\Modules\Platform\Setup\IntegrationFamily;

/**
 * One contract, four providers. Each adapter proves REACH AND CREDENTIALS and
 * is structurally incapable of doing more.
 *
 * A CONNECTION IS NOT A CAPABILITY. Passing this test grants nothing: no AI
 * inference, no Fabric business data, no ability to mail an arbitrary
 * recipient. The adapters are written so that those are not options that were
 * declined - they are paths that do not exist in the code.
 */
interface ConnectionTester
{
    public function family(): IntegrationFamily;

    public function test(): ConnectionResult;
}
