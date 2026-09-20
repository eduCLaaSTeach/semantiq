<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Access\StepUp\StepUpAction;
use App\Modules\Access\StepUp\StepUpService;
use App\Modules\Identity\Configuration\IdentityReconfiguration;
use App\Modules\Identity\Health\IdentityHealthCheck;
use App\Modules\Identity\StepUp\IdentityReconfigurationCompletion;
use App\Modules\Identity\Support\IdentityConfigurationReport;
use App\Modules\Platform\Identity\IdentityProvider;
use App\Modules\Platform\Models\User;
use App\Modules\Platform\Setup\Identity\IdentityConfigurationSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Microsoft Entra ID - readable always, changeable deliberately.
 *
 * READING IS STILL MASKED. Every value comes from the safe read model. The page
 * payload carries the MASKED identifiers and never the full ones, which is what
 * makes the mask real rather than cosmetic: a CSS mask over a value already in
 * the props would be in the page source of every screenshot-adjacent artefact.
 *
 * -------------------------------------------------------------------------
 * GATE C ROUND 3 - THIS SCREEN CAN NOW CHANGE THE CONFIGURATION
 * -------------------------------------------------------------------------
 * It used to say "These are set on the server. They cannot be changed from this
 * screen", and that was true and was the gap: First-Run could establish a
 * configuration and nobody could ever change one. A customer whose Entra client
 * secret expired - which they all do - had no route back except SSH.
 *
 * P1-02 REMAINS THE SOLE OWNER. Platform Integrations still has no identity
 * write path; it shows a summary and links here. What changed is that here now
 * has somewhere to link TO.
 *
 * THE WRITE PATH IS VERIFY-THEN-ACTIVATE AND IS NEVER DIRECT:
 *
 *   change -> Microsoft step-up -> verify the candidate -> activate atomically
 *
 * IdentityReconfiguration owns every step. This controller validates, refuses
 * the one thing that is never meant - partially clearing a configured
 * deployment - and hands over.
 */
final class EntraController
{
    public function __construct(
        private readonly IdentityProvider $provider,
        private readonly IdentityHealthCheck $health,
        private readonly IdentityConfigurationSource $identityConfiguration,
        private readonly IdentityReconfiguration $reconfiguration,
        private readonly StepUpService $stepUp,
    ) {}

    public function show(): Response
    {
        $report = $this->health->report();

        return Inertia::render('Identity/Entra', [
            'configuration' => IdentityConfigurationReport::build($this->provider, $this->identityConfiguration)->toArray(),
            'healthSummary' => [
                'state' => $report->state(),
                'stateInWords' => $report->stateInWords(),
            ],
            // WHETHER a configuration exists, which decides the wording and the
            // partial-clear rule. Never the configuration itself.
            'isConfigured' => $this->reconfiguration->isConfigured(),
        ]);
    }

    /**
     * The change form.
     *
     * IT PRE-FILLS THE NON-SECRET FIELDS AND NEVER THE SECRET. An administrator
     * correcting one character of a directory identifier should not have to
     * re-type the other thirty-five - but a client secret that arrived
     * pre-filled would be a client secret in the page source, which is the
     * exact property the masked read screen exists to preserve.
     */
    public function edit(): Response
    {
        return Inertia::render('Identity/EntraChange', [
            'fields' => $this->reconfiguration->currentFields(),
            'isConfigured' => $this->reconfiguration->isConfigured(),
            'secretIsSet' => IdentityConfigurationReport::build(
                $this->provider,
                $this->identityConfiguration,
            )->secret->isPresent(),
        ]);
    }

    /**
     * Stage the candidate and send the administrator to Microsoft.
     *
     * NOTHING IS WRITTEN HERE. Not the fields, not the secret, not the flag.
     * The live configuration is the only way anybody signs in, so it is not
     * touched until a confirmation has come back AND the candidate has answered
     * a real discovery round trip.
     */
    public function update(Request $request): RedirectResponse
    {
        $configured = $this->reconfiguration->isConfigured();

        $data = $request->validate([
            'tenant_id' => ['nullable', 'string', 'max:255'],
            'client_id' => ['nullable', 'string', 'max:255'],
            'redirect_uri' => ['nullable', 'string', 'max:255'],
            // NULLABLE MEANS UNCHANGED. A blank secret box on a form somebody
            // opened to fix a redirect address must not wipe the credential.
            'client_secret' => ['nullable', 'string', 'max:1024'],
        ]);

        /*
         * A CONFIGURED DEPLOYMENT CANNOT BE PARTIALLY CLEARED.
         *
         * Replacing the configuration is what this screen is for. Emptying half
         * of it is never something somebody meant to do, and the result is an
         * installation nobody can sign in to - so it is refused here, before
         * anything is staged, rather than discovered when the probe fails and
         * half the reason is already gone.
         *
         * "SUBMITTED AND EMPTY" IS DETECTED, NOT "EQUAL TO EMPTY STRING".
         *
         * Laravel's ConvertEmptyStringsToNull turns a blank box into `null`
         * before this method runs, so a check for `=== ''` never fires and the
         * request falls through to "Nothing was changed" - a refusal, but the
         * wrong one, and one that rests entirely on a global middleware staying
         * in the stack. The Gate C round 2 record already contains one rule that
         * had quietly become that middleware's responsibility; this does not
         * become the second.
         *
         * So BOTH shapes count, and the key's PRESENCE is what distinguishes
         * "cleared" from "not touched": validate() returns only attributes that
         * were in the request.
         */
        $fields = [];

        foreach (IdentityReconfiguration::FIELDS as $name) {
            if (! array_key_exists($name, $data)) {
                // Not submitted. Keeping what is in force is the correct
                // reading of an absent field on a partial form.
                continue;
            }

            $value = $data[$name] === null ? '' : trim((string) $data[$name]);

            if ($value === '' && $configured) {
                throw ValidationException::withMessages([
                    $name => 'This cannot be left empty while Microsoft sign-in is in use. '
                        .'Enter the new value instead.',
                ]);
            }

            if ($value !== '') {
                $fields[$name] = $value;
            }
        }

        $secret = is_string($data['client_secret'] ?? null) && $data['client_secret'] !== ''
            ? $data['client_secret']
            : null;

        if ($fields === [] && $secret === null) {
            throw ValidationException::withMessages([
                'tenant_id' => 'Nothing was changed.',
            ]);
        }

        $actor = $request->attributes->get('semantiq_user');

        if (! $actor instanceof User) {
            throw ValidationException::withMessages([
                'tenant_id' => 'That change cannot be confirmed from here.',
            ]);
        }

        $stagedId = $this->reconfiguration->stage($fields, $secret, (int) $actor->getKey());

        $reference = $this->stepUp->begin(
            $actor,
            $request->session()->getId(),
            StepUpAction::ReconfigureIdentity,
            [
                // OPAQUE TO P1-05. A kind and an id, through the seam P1-07
                // established. No directory, no application, no secret.
                'subject_type' => IdentityReconfigurationCompletion::SUBJECT_TYPE,
                'subject_id' => $stagedId,
                'subject_intent' => 'microsoft',
            ],
        );

        return redirect()->route('access.step-up.begin', ['reference' => $reference]);
    }

    /**
     * Reveal ONE identifier, on an explicit action - D-27.
     *
     * POST rather than GET, and therefore CSRF-protected, for the same reason
     * auth.logout is POST: a GET that returns a value is triggerable by any
     * third-party page the administrator happens to be visiting.
     *
     * Exactly two field names are accepted. There is no name that would return
     * the client secret, and no code path that could: the secret never becomes a
     * string in this module.
     */
    public function reveal(Request $request): JsonResponse
    {
        $field = $request->input('field');

        // The RESOLVED source, so a reveal cannot show a directory identifier
        // that sign-in is not using. Revealing .env's value on a store-backed
        // deployment would be an administrator reading, and then trusting, an
        // identifier no longer in play.
        $identity = $this->identityConfiguration->resolve();

        $value = match ($field) {
            'directory' => $identity->tenantId,
            'application' => $identity->clientId,
            default => null,
        };

        if ($value === null) {
            // Names nothing about which fields exist.
            return response()->json(['message' => 'That cannot be revealed.'], 422);
        }

        return response()->json(['value' => $value]);
    }
}
