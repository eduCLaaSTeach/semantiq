<?php

declare(strict_types=1);

namespace App\Modules\Platform\Setup\StepUp;

use App\Modules\Platform\Setup\IntegrationFamily;
use App\Modules\Platform\Setup\Models\IntegrationConfiguration;
use App\Modules\Platform\Setup\Secrets\IntegrationSecretStore;

/**
 * WHICH CREDENTIAL CHANGES NEED RE-AUTHENTICATION - D-159, in one place.
 *
 * The rule is about an ESTABLISHED CREDENTIAL, not about writing. Establishing
 * one that does not exist yet needs no step-up: there is nothing to take away,
 * the administrator already holds PlatformAdmin, and requiring it would make
 * First-Run unsatisfiable on a deployment that has no Microsoft.
 *
 * ONCE A CREDENTIAL EXISTS, THREE THINGS ARE PRIVILEGED, not one:
 *
 *   replacing it   somebody who can silently replace the mail credential can
 *                  redirect the deployment's outbound mail;
 *   removing it    the integration stops working, and the evidence of what it
 *                  was is gone;
 *   MOVING ITS DESTINATION - Gate C round 3, and the one the first version
 *                  missed. The credential does not have to move if the
 *                  destination moves to meet it. Change the SMTP host and the
 *                  saved password is handed to the new host at the next test,
 *                  with nothing having asked who was asking.
 *
 * IntegrationFamily::destinationFields() names them, per family, in the type.
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
        array $fields = [],
    ): bool {
        $partition = $this->partition($family, $secrets, $removals, $fields);

        return $partition['replace'] !== []
            || $partition['remove'] !== []
            || $partition['destination'] !== [];
    }

    /**
     * Does this family currently hold any established credential at all?
     *
     * THE QUESTION IS "ANY", NOT "THE ONE BEING CHANGED". A destination change
     * carries no secret name of its own - moving the SMTP host names no
     * credential - so the thing being protected has to be identified by the
     * family holding one.
     */
    public function hasEstablishedSecret(IntegrationFamily $family): bool
    {
        foreach ($family->secrets() as $name) {
            if ($this->secrets->has($family->value, $name)) {
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
     * @param  array<string, scalar|null>  $fields  the submitted non-secret fields
     * @return array{replace: array<string, string>, remove: list<string>, establish: array<string, string>, destination: array<string, scalar|null>, ordinary: array<string, scalar|null>}
     */
    public function partition(
        IntegrationFamily $family,
        array $secrets,
        array $removals = [],
        array $fields = [],
    ): array {
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

        /*
         * THE DESTINATION SPLIT - Gate C round 3.
         *
         * A destination field is privileged only when a credential exists to
         * be redirected, and only when its value is ACTUALLY CHANGING. The
         * second half matters as much as the first: every save posts the whole
         * form, so classifying on submission alone would demand a Microsoft
         * round trip for pressing Save with nothing edited - and a
         * confirmation people meet for no reason is one they learn to click
         * through.
         */
        $destination = [];
        $ordinary = [];

        $established = $this->hasEstablishedSecret($family);
        $current = $this->currentSettings($family);
        $privileged = $family->destinationFields();

        foreach ($fields as $name => $value) {
            if (! in_array($name, $family->fields(), true)) {
                continue;
            }

            if ($established
                && in_array($name, $privileged, true)
                && $this->hasChanged($current[$name] ?? null, $value)) {
                $destination[$name] = $value;

                continue;
            }

            $ordinary[$name] = $value;
        }

        return [
            'replace' => $replace,
            'remove' => $remove,
            'establish' => $establish,
            'destination' => $destination,
            'ordinary' => $ordinary,
        ];
    }

    /**
     * LOOSE COMPARISON ON PURPOSE, and only here.
     *
     * `port` arrives from a form as the string "587" and is stored as the
     * integer 587. A strict comparison would call that a change on every save,
     * which would demand a step-up for editing a sender name - and an
     * administrator who is sent to Microsoft for nothing stops reading what
     * they are confirming.
     *
     * The comparison is on the STRING form of both sides, so "587" and 587
     * agree while "587" and "588" do not, and null and "" both read as absent.
     */
    private function hasChanged(mixed $before, mixed $after): bool
    {
        $normalise = static fn (mixed $v): string => $v === null ? '' : (string) $v;

        return $normalise($before) !== $normalise($after);
    }

    /** @return array<string, mixed> */
    private function currentSettings(IntegrationFamily $family): array
    {
        $row = IntegrationConfiguration::query()->where('family', $family->value)->first();

        return is_array($row?->settings) ? $row->settings : [];
    }
}
