<?php

declare(strict_types=1);

namespace App\Modules\Platform\Setup\Http\Controllers;

use App\Modules\Platform\Security\SecurityEventLogger;
use App\Modules\Platform\Setup\Connections\ConnectionTesterRegistry;
use App\Modules\Platform\Setup\IntegrationConfigurationWriter;
use App\Modules\Platform\Setup\IntegrationFamily;
use App\Modules\Platform\Setup\Support\SetupProjection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
    ) {}

    /** The Platform Integrations screen. */
    public function index(Request $request): Response
    {
        return Inertia::render('Platform/Integrations', [
            'integrations' => $this->projection->all(),
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

        // The writer records integration.configuration.changed INSIDE its own
        // transaction, with the change it evidences. Recording it here would
        // put a state change and its evidence in different transactions, which
        // is what D-111 forbids and what P1-08's guard caught.
        $this->writer->save($resolved, $fields, $secrets, $this->actorId($request));

        return back()->with('confirmation', $resolved->inWords().' settings saved.');
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

        return is_object($user) && property_exists($user, 'id') ? (int) $user->id : null;
    }
}
