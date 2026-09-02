<?php

declare(strict_types=1);

namespace App\Modules\Platform\Console;

use App\Modules\Identity\Support\SessionPolicy;
use Illuminate\Console\Command;

/**
 * Prints the approved session policy, for the deployment to read.
 *
 * D-31 requires the production SESSION_LIFETIME to be corrected through the
 * controlled deployment process. The value could have been typed into the
 * workflow, and then the deployment and the application would each hold their
 * own copy of the policy - which is the shape of the defect D-31 exists to fix,
 * moved one layer out.
 *
 * So the deployment asks the application. One number, on stdout, nothing else:
 * the script parses it, and anything else it prints would have to be parsed
 * around.
 */
final class SessionPolicyCommand extends Command
{
    protected $signature = 'semantiq:session-policy {--idle-minutes : Print the approved idle timeout in minutes}';

    protected $description = 'Print the approved session policy';

    public function handle(): int
    {
        if ($this->option('idle-minutes')) {
            $this->output->write((string) SessionPolicy::APPROVED_IDLE_MINUTES);

            return self::SUCCESS;
        }

        $this->line('Approved idle timeout: '.SessionPolicy::APPROVED_IDLE_MINUTES.' minutes');
        $this->line('Approved absolute lifetime: '.SessionPolicy::APPROVED_ABSOLUTE_HOURS.' hours');

        return self::SUCCESS;
    }
}
