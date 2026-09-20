<?php

declare(strict_types=1);

namespace App\Modules\Platform\Setup\Console;

use App\Modules\Platform\Bootstrap\BootstrapState;
use App\Modules\Platform\Security\SecurityEventLogger;
use App\Modules\Platform\Setup\Bootstrap\Models\BootstrapAdministrator;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

use function Laravel\Prompts\password;

/**
 * Creates the ONE local Bootstrap Administrator.
 *
 * SSH ONLY, like the grant command beside it, and for the same reason: this
 * creates the pre-SSO credential that can complete First-Run, so it must not be
 * reachable from a screen, an endpoint or a workflow.
 *
 * THE PASSWORD IS READ INTERACTIVELY AND HIDDEN - D-164, D-165. There is NO
 * --password option, and passing one is a usage error rather than a shortcut.
 * A plaintext password on a command line lands in the shell history, in `ps`
 * output while the process runs, and in any process accounting the host keeps.
 * The absence of the option is the control; a warning would not be.
 *
 * IT IS NEVER ECHOED BACK. Not on success, not in a confirmation, not in the
 * audit trail - ALLOWED_KEYS has no key that could hold it.
 *
 * NO VENDOR DEFAULT. There is no fallback password, no "changeme", and no
 * generated one printed to the terminal. A default that works before anybody
 * changes it is a credential every deployment shares.
 *
 * EXACTLY ONE, ENFORCED BY THE DATABASE. The unique constraint decides, not a
 * count-then-insert in PHP, which is a race.
 */
final class CreateBootstrapAdministratorCommand extends Command
{
    protected $signature = 'semantiq:bootstrap-administrator {--email= : The setup administrator sign-in address}';

    protected $description = 'Create the one local setup administrator for First-Run';

    public function handle(BootstrapState $state, SecurityEventLogger $events): int
    {
        $email = mb_strtolower(trim((string) $this->option('email')));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('--email is required and must be an email address.');

            return self::FAILURE;
        }

        if ($state->isConfigured()) {
            // Creating a pre-SSO credential on a deployment that already has a
            // working administrator is not setup - it is adding a second way in.
            $this->error('This deployment already has a System Administrator. First-Run is closed.');

            return self::FAILURE;
        }

        if (BootstrapAdministrator::current() !== null) {
            $this->error('A setup administrator already exists. There is exactly one.');

            return self::FAILURE;
        }

        // HIDDEN, INTERACTIVE, AND TYPED TWICE. The confirmation is not
        // ceremony: nobody can see what they typed, and a typo in a credential
        // that cannot be recovered without an SSH recovery token is expensive.
        $password = password('Choose a password for the setup administrator', required: true);
        $again = password('Type it again', required: true);

        if (! hash_equals($password, $again)) {
            $this->error('The two passwords do not match. Nothing was created.');

            return self::FAILURE;
        }

        if (mb_strlen($password) < 16) {
            // A length floor rather than a composition rule. Composition rules
            // push people toward P@ssw0rd!; length is what actually costs an
            // attacker, and this credential is never rotated by a policy.
            $this->error('The password must be at least 16 characters. Nothing was created.');

            return self::FAILURE;
        }

        try {
            DB::transaction(function () use ($email, $password, $events): void {
                BootstrapAdministrator::query()->create([
                    'singleton' => BootstrapAdministrator::SINGLETON,
                    'email' => $email,
                    // Hash::make, the configured adaptive hash. NEVER SHA-256:
                    // that is right for a generated 64-character token and
                    // wrong for anything a person chose.
                    'password_hash' => Hash::make($password),
                ]);

                $events->record(SecurityEventLogger::BOOTSTRAP_ADMINISTRATOR_CREATED, [
                    'result' => 'created',
                ]);
            });
        } catch (QueryException) {
            // The unique constraint. Another process won; the message names no
            // column and no value.
            $this->error('A setup administrator already exists. There is exactly one.');

            return self::FAILURE;
        }

        $this->info('The setup administrator was created.');
        $this->line('Sign in at '.route('first_run.sign_in').' to complete setup.');

        return self::SUCCESS;
    }
}
