<?php

declare(strict_types=1);

namespace App\Modules\Domains\Console;

use App\Modules\Domains\Services\BaselineDomainInitialiser;
use App\Modules\Domains\Support\BaselineDomains;
use App\Modules\Organisation\Services\OrganisationService;
use Illuminate\Console\Command;

/**
 * The explicit, one-time initialisation for a deployment that already has an
 * organisation - D-46.
 *
 * Production was created before P1-04 existed and will never run Company
 * Profile creation again, so the integration point in OrganisationService can
 * never fire for it. This command is the other path, and it is deliberately a
 * COMMAND rather than a migration or a boot hook: somebody has to decide to run
 * it, and the deploy does not decide for them.
 *
 * IT WRITES. Every other operational entry point in this project so far has
 * been a read-only report, and this one is not; the workflow that dispatches it
 * says so in its name, its header and its summary, so nobody mistakes it for
 * one of those.
 *
 * IT IS SAFE TO RUN TWICE. Keyed on code: missing baseline domains are created,
 * present ones are left exactly as they are. It is not a reset - a domain the
 * administrator has renamed, enabled or assigned an owner to is untouched.
 *
 * IT REPORTS WHAT IT DID. A command that prints nothing cannot be verified, and
 * this one's output is evidence recorded in the verification document.
 */
final class InitialiseBusinessDomains extends Command
{
    protected $signature = 'domains:initialise';

    protected $description = 'Create any missing baseline business domains for the organisation (idempotent, writes)';

    public function handle(OrganisationService $organisations, BaselineDomainInitialiser $initialiser): int
    {
        $organisation = $organisations->current();

        if ($organisation === null) {
            // Refuse rather than create orphan rows. organisation_id is not
            // nullable and could not be invented here even if it were.
            $this->error('There is no organisation yet. Create the Company Profile first.');

            return self::FAILURE;
        }

        $this->line('Baseline business domains - idempotent initialisation. THIS COMMAND WRITES.');

        $created = $initialiser->initialise($organisation);

        $present = array_values(array_diff(BaselineDomains::codes(), $created));

        $this->line('Created:       '.($created === [] ? '(none - all seven were already present)' : implode(', ', $created)));
        $this->line('Already there: '.($present === [] ? '(none)' : implode(', ', $present)));
        $this->line('Every domain created is Disabled, has no owner, and its access expectation is "Not yet determined".');

        return self::SUCCESS;
    }
}
