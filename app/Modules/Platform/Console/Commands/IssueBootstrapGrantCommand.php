<?php

declare(strict_types=1);

namespace App\Modules\Platform\Console\Commands;

use App\Modules\Platform\Bootstrap\GrantIssuer;
use App\Modules\Platform\Setup\Identity\IdentityConfigurationSource;
use Illuminate\Console\Command;
use Throwable;

/**
 * Issues the first-administrator bootstrap grant.
 *
 * Reachable only by an operator with SSH. It is deliberately not an application
 * screen, not an HTTP endpoint and not an admin menu action, and the deploy
 * workflow never invokes it - BootstrapCommandIsNotAutomatedTest asserts that,
 * because a grant printed into a CI log would be a privilege-granting secret in
 * a place many people can read.
 */
final class IssueBootstrapGrantCommand extends Command
{
    protected $signature = 'semantiq:bootstrap-grant {--subject= : UPN or email of the nominated first System Administrator}';

    protected $description = 'Issue a single-use grant for the first System Administrator';

    public function handle(GrantIssuer $issuer, IdentityConfigurationSource $identityConfiguration): int
    {
        $subject = (string) $this->option('subject');

        if ($subject === '') {
            $this->error('--subject is required: the UPN or email of the nominated first System Administrator.');

            return self::FAILURE;
        }

        // The RESOLVED tenant. A grant is bound to the tenant it was issued
        // for and checked against that binding at redemption, so issuing
        // against .env on a deployment that signs in from the store would
        // produce a grant that can never be redeemed.
        $identity = $identityConfiguration->resolve();

        if ($identity->tenantId === '') {
            $this->error('No Microsoft directory is configured on this server.');

            return self::FAILURE;
        }

        $tenant = $identity->tenantId;

        try {
            $grant = $issuer->issue($subject, $tenant, 'ssh-operator');
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $url = rtrim((string) config('app.url'), '/').'/first-run/'.$grant;

        $this->newLine();
        $this->line('Bootstrap grant issued. Valid for '.GrantIssuer::TTL_MINUTES.' minutes, single use.');
        $this->newLine();
        $this->line($url);
        $this->newLine();
        $this->warn('Shown once. Send it to the nominated administrator over a trusted channel.');
        $this->warn('It grants nothing on its own - they must still sign in with Microsoft, and');
        $this->warn('the verified identity must match '.mb_strtolower(trim($subject)).'.');

        return self::SUCCESS;
    }
}
