<?php

declare(strict_types=1);

namespace App\Modules\Platform\Setup\StepUp;

use App\Modules\Platform\Setup\IntegrationFamily;
use App\Modules\Platform\Setup\Secrets\IntegrationSecretStore;

/**
 * WHICH CREDENTIAL CHANGES NEED RE-AUTHENTICATION - D-159, in one place.
 *
 * The rule is about REPLACEMENT AND REMOVAL, not about writing. Establishing a
 * credential that does not exist yet needs no step-up: there is nothing to take
 * away, the administrator already holds PlatformAdmin, and requiring it would
 * make First-Run unsatisfiable on a deployment that has no Microsoft.
 *
 * Replacing or removing one that DOES exist is the privileged case. Somebody
 * who can silently replace the mail credential can redirect the deployment's
 * outbound mail; somebody who can replace the Fabric secret can point it at a
 * directory they control.
 *
 * ONE CLASS, BOTH SURFACES. The normal console answers this question to decide
 * whether to send the administrator to Microsoft; First-Run answers the same
 * question to decide whether to ask for the local password again. Two copies of
 * the rule would be two chances for one surface to be more generous than the
 * other, and the generous one is the one that gets found.
 *
 * A PLAIN TEST NEEDS NEITHER. It changes nothing.
 */
final class IntegrationChangeAuthority
{
    public function __construct(private readonly IntegrationSecretStore $secrets) {}

    /**
     * Does this submission replace or remove a credential that already exists?
     *
     * @param  array<string, string>  $secrets  name => plaintext, absent means unchanged
     * @param  list<string>  $removals  names explicitly marked for removal
     */
    public function requiresReauthentication(
        IntegrationFamily $family,
        array $secrets,
        array $removals = [],
    ): bool {
        foreach ($family->secrets() as $name) {
            $established = $this->secrets->has($family->value, $name);

            if (! $established) {
                // Nothing to replace or remove.
                continue;
            }

            if (in_array($name, $removals, true)) {
                return true;
            }

            if (array_key_exists($name, $secrets)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The established secrets this submission would replace or remove.
     *
     * Returned so the caller stages exactly those and nothing else - a
     * submission that also establishes a NEW secret alongside a replacement
     * must not have the new one silently swept into the confirmation.
     *
     * @param  array<string, string>  $secrets
     * @param  list<string>  $removals
     * @return array{replace: array<string, string>, remove: list<string>, establish: array<string, string>}
     */
    public function partition(IntegrationFamily $family, array $secrets, array $removals = []): array
    {
        $replace = [];
        $establish = [];
        $remove = [];

        foreach ($secrets as $name => $value) {
            if (! in_array($name, $family->secrets(), true)) {
                continue;
            }

            if ($this->secrets->has($family->value, $name)) {
                $replace[$name] = $value;
            } else {
                $establish[$name] = $value;
            }
        }

        foreach ($removals as $name) {
            if (in_array($name, $family->secrets(), true)
                && $this->secrets->has($family->value, $name)) {
                $remove[] = $name;
            }
        }

        return ['replace' => $replace, 'remove' => $remove, 'establish' => $establish];
    }
}
