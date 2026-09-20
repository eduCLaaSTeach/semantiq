<?php

declare(strict_types=1);

namespace App\Modules\Platform\Setup\Console;

use App\Modules\Platform\Bootstrap\BootstrapState;
use App\Modules\Platform\Setup\Bootstrap\BootstrapRecovery;
use App\Modules\Platform\Setup\Bootstrap\Models\BootstrapAdministrator;
use Illuminate\Console\Command;

/**
 * Issues the single-use token that reopens local password login.
 *
 * SSH ONLY, and this one most of all: once bootstrap has closed, this command
 * is the ONLY thing that can reopen it. Correction 3 made UNCONFIGURED
 * necessary and not sufficient precisely so that this deliberate operator
 * action - and nothing about the administrator count - is what reopens the
 * door.
 *
 * THE TOKEN IS PRINTED ONCE AND NEVER STORED. Only its SHA-256 is kept, so it
 * cannot be recovered from the database, a log or the audit trail. Losing it
 * means issuing another, which is the correct trade.
 *
 * IT REFUSES WHILE THE DEPLOYMENT STILL HAS AN ADMINISTRATOR. A recovery token
 * issued into a working deployment is a second way in that nobody needs, and
 * it would sit unconsumed for thirty minutes.
 */
final class IssueBootstrapRecoveryCommand extends Command
{
    protected $signature = 'semantiq:bootstrap-recovery {--issued-by= : Who asked for this, and why}';

    protected $description = 'Issue a single-use token that reopens local setup sign-in';

    public function handle(BootstrapState $state, BootstrapRecovery $recovery): int
    {
        if ($state->isConfigured()) {
            $this->error(
                'This deployment has an active System Administrator. Recovery is for a deployment '
                .'that has lost every one of them.',
            );

            return self::FAILURE;
        }

        if (BootstrapAdministrator::current() === null) {
            $this->error('This deployment has no setup administrator to recover.');

            return self::FAILURE;
        }

        $token = $recovery->issue((string) $this->option('issued-by') ?: null);

        $this->newLine();
        $this->info('Recovery token, valid for 30 minutes and usable once:');
        $this->line($token);
        $this->newLine();
        $this->warn('It is not stored anywhere and cannot be shown again.');
        $this->line('Use it at '.route('first_run.recover'));

        return self::SUCCESS;
    }
}
