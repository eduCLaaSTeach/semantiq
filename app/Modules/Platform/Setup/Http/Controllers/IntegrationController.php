<?php

declare(strict_types=1);

namespace App\Modules\Platform\Setup\Http\Controllers;

use App\Modules\Access\StepUp\StepUpAction;
use App\Modules\Access\StepUp\StepUpService;
use App\Modules\Platform\Models\User;
use App\Modules\Platform\Security\SecurityEventLogger;
use App\Modules\Platform\Setup\Bootstrap\BootstrapReconfirmation;
use App\Modules\Platform\Setup\Connections\ConnectionTesterRegistry;
use App\Modules\Platform\Setup\Connections\SendsTestEmail;
use App\Modules\Platform\Setup\IntegrationConfigurationWriter;
use App\Modules\Platform\Setup\IntegrationFamily;
use App\Modules\Platform\Setup\Secrets\IntegrationSecretStore;
use App\Modules\Platform\Setup\Secrets\StagedChangeStore;
use App\Modules\Platform\Setup\StepUp\IntegrationChangeAuthority;
use App\Modules\Platform\Setup\StepUp\IntegrationSecretStepUpCompletion;
use App\Modules\Platform\Setup\Support\SetupProjection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Save and test an integration. Shared by both surfaces.
 *
 * ONE CONTROLLER, TWO ENTRY POINTS. First-Run and Platform Integrations differ
 * in chrome and in who is signed in; they must not differ in what saving does.
 * Two controllers would be two validation rules, two invalidation paths and
 * two chances to forget one.
 *
 * EVERY WRITE IS A PUT OR POST BODY - never a query string. A secret in a URL
 * is in the access log, the referrer header and the browser history before
 * anybody decides how to store it.
 *
 * A TEST CHANGES NOTHING AND THEREFORE NEEDS NO STEP-UP (D-159). It also
 * cannot be run in a loop: one test per integration per minute, which is
 * P1-02's limiter shape reused rather than a second one invented.
 */
final class IntegrationController
{
    private const TEST_EVERY_SECONDS = 60;

    public function __construct(
        private readonly SetupProjection $projection,
        private readonly IntegrationConfigurationWriter $writer,
        private readonly ConnectionTesterRegistry $testers,
        private readonly SecurityEventLogger $events,
        private readonly IntegrationChangeAuthority $authority,
        private readonly StagedChangeStore $staged,
        private readonly StepUpService $stepUp,
        private readonly BootstrapReconfirmation $reconfirmation,
        private readonly IntegrationSecretStore $secrets,
        private readonly SendsTestEmail $testEmail,
    ) {}

    /**
     * The Platform Integrations screen.
     *
     * GATE C CORRECTION 3. IDENTITY IS A SUMMARY, NOT A FORM.
     *
     * D-148 gives Microsoft sign-in to P1-02, and this screen was editing it
     * anyway - a second writer for a configuration that already had an owner,
     * with its own test button and its own idea of what "saved" means. It now
     * SAYS how identity is and links to the screen that owns it.
     *
     * THE SPLIT IS SERVER-SIDE, not a component that chooses to render fewer
     * inputs. IntegrationFamily::writableOnTheConsole() decides, the same list
     * the routes are constrained to, so the props and the routes cannot come to
     * disagree - and the identity props do not contain the directory and
     * application identifiers at all.
     */
    public function index(Request $request): Response
    {
        $writable = [];
        $summaries = [];

        foreach (IntegrationFamily::cases() as $family) {
            $view = $this->projection->forFamily($family);

            if ($family->isWritableOnTheConsole()) {
                $writable[] = $view->toArray();

                continue;
            }

            $summaries[] = $view->toSummaryArray();
        }

        return Inertia::render('Platform/Integrations', [
            'integrations' => $writable,
            'summaries' => $summaries,
        ])->toResponse($request);
    }

    public function update(Request $request, string $family): RedirectResponse
    {
        $resolved = $this->family($family);

        $rules = [];

        foreach ($resolved->fields() as $field) {
            $rules[$field] = ['nullable', 'string', 'max:255'];
        }

        foreach ($resolved->secrets() as $name) {
            // NULLABLE MEANS UNCHANGED, not cleared. A blank secret field on a
            // form the administrator opened to edit the port must not wipe the
            // credential - which is what "required" or a blind overwrite would
            // do, silently, and only discoverable at the next connection test.
            $rules['secret_'.$name] = ['nullable', 'string', 'max:1024'];
        }

        $data = $request->validate($rules);

        $fields = [];

        foreach ($resolved->fields() as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== null) {
                $fields[$field] = $data[$field];
            }
        }

        $secrets = [];

        foreach ($resolved->secrets() as $name) {
            $submitted = $data['secret_'.$name] ?? null;

            if (is_string($submitted) && $submitted !== '') {
                $secrets[$name] = $submitted;
            }
        }

        /*
         * D-159, AS WIDENED BY GATE C ROUND 3.
         *
         * Once a credential exists, THREE kinds of change are privileged:
         * replacing it, removing it, and MOVING WHERE IT IS SENT. The third is
         * the one the first version missed, and it is the dangerous one,
         * because it needs no credential at all:
         *
         *   the SMTP password is saved
         *     -> change only the mail server address
         *     -> nothing asks who is asking
         *     -> the next test hands that password to the new host.
         *
         * IntegrationChangeAuthority answers all three in one place, so this
         * surface and First-Run cannot come to disagree about any of them.
         */
        $partition = $this->authority->partition($resolved, $secrets, [], $fields);

        $privileged = $partition['replace'] !== []
            || $partition['remove'] !== []
            || $partition['destination'] !== [];

        /*
         * D-159, THE BOOTSTRAP HALF.
         *
         * First-Run reaches this same action, and must not reach the Microsoft
         * step-up below it: Microsoft may not exist yet. It reconfirms the
         * local password instead, and it does so BEFORE anything is written -
         * so a refusal leaves the configuration exactly as it was.
         *
         * The condition is the same one the console uses, from the same class,
         * so the two surfaces cannot come to disagree about which changes are
         * privileged.
         */
        if ($this->isBootstrapRequest($request)) {
            if ($privileged) {
                if (! $this->reconfirmation->confirms($request, $request->input('password'))) {
                    throw ValidationException::withMessages([
                        'password' => 'That password was not accepted.',
                    ]);
                }
            }

            // Reconfirmed, or nothing privileged was asked for. Bootstrap
            // applies directly: there is no round trip to survive, so there is
            // nothing to stage.
            $this->writer->save($resolved, $fields, $secrets, null);

            return back()->with('confirmation', $resolved->inWords().' settings saved.');
        }

        if ($privileged) {
            /*
             * NOTHING IS WRITTEN BEFORE THE CONFIRMATION - the correction.
             *
             * The first version saved the non-secret fields immediately,
             * reasoning that they were not the privileged part and that
             * discarding them would mean re-typing. Both halves were wrong:
             * the destination IS a privileged part, and writing it before the
             * confirmation is exactly how the configuration comes to hold a
             * new host beside an old password - the mixed state a step-up is
             * supposed to make unreachable.
             *
             * THE WHOLE CHANGE IS STAGED, including the fields that are not
             * themselves privileged. Applying an unprivileged field now and a
             * privileged one later would leave the form showing a save that
             * half happened.
             */
            return $this->confirmReconfiguration($request, $resolved, $fields, $partition);
        }

        $this->writer->save($resolved, $fields, $partition['establish'], $this->actorId($request));

        return back()->with('confirmation', $resolved->inWords().' settings saved.');
    }

    /**
     * Stage a whole privileged change and send the administrator to Microsoft.
     *
     * @param  array<string, scalar|null>  $fields
     * @param  array{replace: array<string, string>, remove: list<string>, establish: array<string, string>, destination: array<string, scalar|null>, ordinary: array<string, scalar|null>}  $partition
     */
    private function confirmReconfiguration(
        Request $request,
        IntegrationFamily $family,
        array $fields,
        array $partition,
    ): RedirectResponse {
        $actorId = $this->actorId($request);

        if ($actorId === null) {
            throw ValidationException::withMessages([
                'family' => 'That change cannot be confirmed from here.',
            ]);
        }

        // A removal arriving through update() is not a thing the form can ask
        // for - removal has its own verb - so this path never carries one.
        $replace = $partition['replace'] + $partition['establish'];

        if (count($replace) > 1) {
            throw ValidationException::withMessages([
                'family' => 'Only one credential can be confirmed at a time.',
            ]);
        }

        $secretName = $replace === [] ? null : (string) array_key_first($replace);
        $secretValue = $replace === [] ? null : (string) reset($replace);

        $stagedId = $this->staged->stageReconfiguration(
            $family,
            $fields,
            $secretName,
            $secretValue,
            $actorId,
        );

        return $this->beginStepUp(
            $request,
            $family,
            StepUpAction::ReplaceIntegrationSecret,
            $stagedId,
        );
    }

    /**
     * Gate C correction 4B. REMOVE A SAVED CREDENTIAL, EXPLICITLY.
     *
     * NEVER INFERRED FROM A BLANK FIELD. The form says a blank secret keeps
     * what is saved, and it must keep meaning that: an administrator who opens
     * this page to change a port and leaves the password box alone has not
     * asked for their mail credential to be deleted. So removal is its own
     * verb, its own route and its own confirmation.
     */
    public function removeSecret(Request $request, string $family, string $name): RedirectResponse
    {
        $resolved = $this->family($family);

        if (! in_array($name, $resolved->secrets(), true)) {
            throw ValidationException::withMessages([
                'secret' => 'That integration has no such credential.',
            ]);
        }

        $partition = $this->authority->partition($resolved, [], [$name]);

        if ($partition['remove'] === []) {
            // Nothing saved. Reporting success would tell a caller whether a
            // credential exists; reporting failure would be a refusal for a
            // state that is already what was asked for.
            return back()->with('confirmation', 'There is no saved credential to remove.');
        }

        /*
         * D-159, THE BOOTSTRAP HALF - the same shape as update().
         *
         * Setup reconfirms the local password; Microsoft step-up cannot be
         * required before Microsoft exists. The check runs BEFORE anything is
         * deleted, so a refusal leaves the credential exactly where it was.
         */
        if ($this->isBootstrapRequest($request)) {
            if (! $this->reconfirmation->confirms($request, $request->input('password'))) {
                throw ValidationException::withMessages([
                    'password' => 'That password was not accepted.',
                ]);
            }

            DB::transaction(function () use ($resolved, $name): void {
                $this->secrets->forget($resolved->value, $name);
                $this->writer->recordSecretChanged($resolved, null);
            });

            $this->events->record(SecurityEventLogger::INTEGRATION_CONFIGURATION_CHANGED, [
                // THE FAMILY AND NOTHING ELSE. Not the credential, not the
                // host it authenticated to, not the name of the endpoint.
                'provider' => $resolved->value,
                'result' => 'removed',
            ]);

            return back()->with('confirmation', 'That saved credential has been removed.');
        }

        return $this->confirmThroughMicrosoft(
            $request,
            $resolved,
            StepUpAction::RemoveIntegrationSecret,
            [],
            $partition['remove'],
        );
    }

    /**
     * Stage the change, begin the step-up, and send the administrator to
     * Microsoft.
     *
     * THE PLAINTEXT GOES INTO staged_integration_changes AND NOWHERE ELSE. Not
     * the session - which on this deployment is a database table - not the
     * step-up row, whose columns are structural and safe to read, and not the
     * URL. The step-up carries only the staged row's id.
     *
     * @param  array<string, string>  $replace
     * @param  list<string>  $remove
     */
    private function confirmThroughMicrosoft(
        Request $request,
        IntegrationFamily $family,
        StepUpAction $action,
        array $replace,
        array $remove = [],
    ): RedirectResponse {
        $actorId = $this->actorId($request);

        if ($actorId === null) {
            // Unreachable from the console, which requires a User session.
            // Refusing rather than assuming is the correct direction.
            throw ValidationException::withMessages([
                'family' => 'That change cannot be confirmed from here.',
            ]);
        }

        /*
         * ONE CONFIRMATION AUTHORISES ONE CREDENTIAL.
         *
         * Every family declares exactly one secret today, so this cannot
         * currently be reached - and that is precisely why it is here rather
         * than assumed. The staging below takes the FIRST entry, so a family
         * that gained a second secret would have the second one SILENTLY
         * DROPPED: the administrator would type two credentials, confirm with
         * Microsoft, and one of them would simply not be saved, discoverable
         * only at the next connection test.
         *
         * Refusing is the honest failure. OneSecretPerFamily pins the
         * assumption so the day it stops being true is a red build rather than
         * a support ticket.
         */
        if (count($replace) > 1) {
            throw ValidationException::withMessages([
                'family' => 'Only one credential can be confirmed at a time.',
            ]);
        }

        $stagedId = $remove !== []
            ? $this->staged->stageRemoval($family, $remove[0], $actorId)
            : $this->staged->stageReplacement(
                $family,
                (string) array_key_first($replace),
                (string) reset($replace),
                $actorId,
            );

        return $this->beginStepUp($request, $family, $action, $stagedId);
    }

    /**
     * Bind an already-staged change to a fresh Microsoft confirmation.
     *
     * SPLIT OUT so that the two staging shapes - one named credential, or a
     * whole reconfiguration - reach the step-up through the SAME call. The
     * alternative was a second copy of the target array, which is the one place
     * a future edit could put something in the step-up row that does not belong
     * there.
     */
    private function beginStepUp(
        Request $request,
        IntegrationFamily $family,
        StepUpAction $action,
        int $stagedId,
    ): RedirectResponse {
        $reference = $this->stepUp->begin(
            $request->attributes->get('semantiq_user'),
            $request->session()->getId(),
            $action,
            [
                // OPAQUE TO P1-05, through the seam P1-07 established. A kind,
                // an id and the family - no credential, no host, no endpoint.
                'subject_type' => IntegrationSecretStepUpCompletion::SUBJECT_TYPE,
                'subject_id' => $stagedId,
                'subject_intent' => $family->value,
            ],
        );

        return redirect()->route('access.step-up.begin', ['reference' => $reference]);
    }

    public function test(Request $request, string $family): RedirectResponse
    {
        $resolved = $this->family($family);

        $key = 'integration-test:'.$resolved->value.':'.($this->actorId($request) ?? $request->ip());

        if (RateLimiter::tooManyAttempts($key, 1)) {
            throw ValidationException::withMessages([
                'test' => 'That was tested a moment ago. Please wait a minute before testing again.',
            ]);
        }

        RateLimiter::hit($key, self::TEST_EVERY_SECONDS);

        $result = $this->testers->for($resolved)->test();

        // THE ONLY WRITER OF A POSITIVE STATUS, with the adapter's own chosen
        // sentence.
        $this->writer->recordTestResult($resolved, $result->status, $result->explanation);

        $this->events->record(SecurityEventLogger::INTEGRATION_CONNECTION_TESTED, [
            'provider' => $resolved->value,
            'user_id' => $this->actorId($request),
            'result' => $result->status->value,
        ]);

        return back()->with('confirmation', $result->explanation);
    }

    /**
     * D-153. SEND ONE TEST MESSAGE TO THE PERSON WHO PRESSED THE BUTTON.
     *
     * THE RECIPIENT IS RESOLVED HERE, SERVER-SIDE, FROM THE AUTHENTICATED
     * PRINCIPAL. It is the single most important line in this action:
     *
     *   normal console      the signed-in System Administrator's own address,
     *                       from the User record Microsoft authenticated;
     *   First-Run           the Bootstrap Administrator's configured address.
     *
     * NOTHING FROM THE REQUEST IS CONSULTED. There is no recipient field in the
     * validation rules because there is no recipient field at all, and
     * TestEmailSender's signature has no parameter a request body could reach.
     * A test that could be pointed at an address is an open relay wearing a
     * diagnostic's clothes - sending from the customer's own domain, through
     * their own authenticated server, to anywhere.
     *
     * IT REQUIRES NO STEP-UP, deliberately. It changes no stored configuration,
     * and the only thing it can do is send one fixed message to the address of
     * the person already signed in.
     *
     * ONE PER ADMINISTRATOR PER MINUTE. The same limiter shape as Test
     * connection, keyed on the actor rather than the family: the thing being
     * rationed is outbound mail from this deployment, not attention paid to one
     * integration.
     */
    public function sendTestEmail(Request $request): RedirectResponse
    {
        $recipient = $this->principalEmail($request);

        if ($recipient === null) {
            throw ValidationException::withMessages([
                'test' => 'A test message can only be sent to your own address, and this session '
                    .'has none.',
            ]);
        }

        $key = 'integration-test-email:'.$recipient;

        if (RateLimiter::tooManyAttempts($key, 1)) {
            throw ValidationException::withMessages([
                'test' => 'A test message was sent a moment ago. Please wait a minute before '
                    .'sending another.',
            ]);
        }

        RateLimiter::hit($key, self::TEST_EVERY_SECONDS);

        $result = $this->testEmail->send($recipient);

        /*
         * THE RESULT IS REPORTED AND NOT STORED.
         *
         * recordTestResult() is for Test connection, which describes whether
         * the integration is reachable. Sending is a different question, and
         * writing this answer into the same column would make the card claim
         * something it was not asked.
         */
        $this->events->record(SecurityEventLogger::INTEGRATION_CONNECTION_TESTED, [
            // The family and the outcome. Never the recipient - it is a
            // person's address, and `provider` is the only key here that
            // carries a configuration choice.
            'provider' => IntegrationFamily::Email->value,
            'user_id' => $this->actorId($request),
            'result' => $result->status->value,
        ]);

        return back()->with('confirmation', $result->explanation);
    }

    /**
     * The authenticated principal's OWN address, whichever surface this is.
     *
     * TWO SOURCES, BOTH SERVER-SIDE, NEITHER OF THEM THE REQUEST BODY. The
     * bootstrap attribute is set by RequireBootstrapSession only after it has
     * re-read the live state; the user attribute is set by
     * EnsureSessionIsCurrent. A request carries exactly one of them.
     */
    private function principalEmail(Request $request): ?string
    {
        $bootstrap = $request->attributes->get('semantiq_bootstrap');

        if ($bootstrap !== null) {
            $email = trim((string) $bootstrap->email);

            return $email === '' ? null : $email;
        }

        $user = $request->attributes->get('semantiq_user');

        if (! $user instanceof User) {
            return null;
        }

        $email = trim((string) $user->email);

        return $email === '' ? null : $email;
    }

    /**
     * Is this the First-Run surface?
     *
     * READ FROM THE REQUEST ATTRIBUTE THE GUARD SET, not from the path. A path
     * check would be a string somebody could reach with a rewritten URL;
     * `semantiq_bootstrap` is set by RequireBootstrapSession only after it has
     * re-read the live state, and a console request never carries it.
     */
    private function isBootstrapRequest(Request $request): bool
    {
        return $request->attributes->get('semantiq_bootstrap') !== null;
    }

    private function family(string $family): IntegrationFamily
    {
        return IntegrationFamily::tryFrom($family)
            ?? throw ValidationException::withMessages(['family' => 'That integration does not exist.']);
    }

    /**
     * The signed-in User, when there is one.
     *
     * NULL DURING FIRST-RUN, and that is correct rather than a gap: the
     * bootstrap principal is not a User and has no id that belongs in
     * `user_id`. An invented id in an audit trail is worse than none.
     */
    private function actorId(Request $request): ?int
    {
        $user = $request->attributes->get('semantiq_user');

        /*
         * instanceof, NOT property_exists.
         *
         * The first version asked property_exists($user, 'id'), which is FALSE
         * for an Eloquent model: `id` is not a declared property, it lives in
         * the model's $attributes array and is reached through __get. So this
         * returned null for EVERY console request - the actor was never
         * recorded on a configuration change, and the D-159 step-up refused to
         * begin because it could not identify who was asking.
         *
         * It failed silently in both directions, which is why it took a
         * behavioural test to find: nothing threw, the write still happened,
         * and last_changed_by_user_id was simply empty.
         */
        return $user instanceof User ? (int) $user->getKey() : null;
    }
}
