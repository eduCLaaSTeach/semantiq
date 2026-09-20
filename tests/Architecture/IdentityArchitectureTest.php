<?php

declare(strict_types=1);

namespace Tests\Architecture;

use App\Modules\Identity\Support\ApprovedProviders;
use App\Modules\Identity\Support\ProviderInventory;
use App\Modules\Identity\Support\SessionPolicy;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The shape of P1-02, asserted rather than trusted.
 *
 * These are PRESENCE GUARDS, and that is the point. P1-01's root cause was that
 * an operation which does not exist has no test to fail: behaviour tests only
 * cover what somebody thought to write a test for, so a write route added next
 * month, a provider bound next month, or a hardcoded 60 typed into a component
 * next month would each pass every behavioural test in this unit - because the
 * test that would fail does not exist yet.
 */
final class IdentityArchitectureTest extends TestCase
{
    /**
     * A1. Nothing in the application writes .env.
     *
     * The one controlled configuration change is a deployment step, not an
     * application capability, and this is what keeps it that way.
     *
     * Mutation: add a file_put_contents to any controller or service.
     */
    public function test_no_code_path_writes_env(): void
    {
        $checked = 0;

        foreach ($this->phpFiles(app_path()) as $file) {
            $source = (string) file_get_contents($file);
            $checked++;

            foreach (['file_put_contents', 'fopen', 'fwrite', 'rename('] as $writer) {
                if (! str_contains($source, $writer)) {
                    continue;
                }

                $this->assertStringNotContainsString(
                    '.env',
                    $source,
                    "[{$file}] both writes files and mentions .env. The application has no business "
                    .'writing its own trust anchors.'
                );
            }
        }

        $this->assertGreaterThan(30, $checked, 'Too few files were read for this to prove anything.');
    }

    /**
     * A2. THE IDENTITY ROUTE SET, AS AN EQUALITY.
     *
     * It was "five GETs and two POSTs, no PUT" - because P1-02 was read-only
     * and the guard existed so that a write route added later could not quietly
     * become the .env editor this unit is defined as not having.
     *
     * GATE C ROUND 3 ADDS THE ONE WRITE ON PURPOSE, and the guard is the reason
     * it is worth trusting: a change screen and a single PUT, named here, so
     * that a SECOND write path still fails the build. The thing being protected
     * was never "no writes" - it was "no write nobody approved", and an
     * equality says that better than a prohibition did.
     *
     * The PUT does not edit .env and cannot: it stages a candidate, and the
     * activation writes the STORE through the owning writer. EnvIsNotIdentity-
     * AuthorityAfterCutover and the no-.env-writer guard above still hold.
     *
     * Mutation: add any further write route under console/identity.
     */
    public function test_the_identity_routes_are_exactly_the_approved_set(): void
    {
        $found = [];

        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'console/identity')) {
                continue;
            }

            foreach ($route->methods() as $method) {
                if (in_array($method, ['HEAD', 'OPTIONS'], true)) {
                    continue;
                }

                $found[] = $method.' '.$route->uri();
            }
        }

        sort($found);

        $this->assertSame([
            'GET console/identity',
            'GET console/identity/entra/change',
            'GET console/identity/health',
            'GET console/identity/login-experience',
            'GET console/identity/providers',
            'GET console/identity/session-policy',
            'POST console/identity/entra/reveal',
            'POST console/identity/health/re-check',
            'PUT console/identity/entra',
        ], $found, 'The Identity route set changed. Six reads, two actions and exactly ONE write - '
            .'the Gate C round 3 change path - and nothing else.');
    }

    /**
     * A3. client_secret is read from configuration in exactly ONE place in the
     * whole application.
     *
     * PHP cannot make reading a config value impossible - any class can call
     * config(). So this does not claim to be a structural guarantee. It narrows
     * the surface to one line and fails when a second appears.
     *
     * THE SCAN IS app/, NOT app/Modules/Identity. P1-10 moved the read out of
     * the Identity module into IdentityConfigurationSource, which resolves
     * .env or the store depending on which authority the deployment is on. Had
     * this test kept scanning only the old directory it would have found zero
     * readers and passed - a guard reporting a safety that had simply moved
     * out of its field of view. Widening the scan is what keeps the assertion
     * about the application rather than about one folder.
     *
     * IT STILL MATCHES THE CALL SHAPE AND NOT THE BARE KEY. IdentityHealthCheck
     * names a row 'client_secret' and SecretPresence's docblock names the key;
     * both are labels rather than disclosures, and flagging them would teach
     * the next person to loosen this guard instead of fixing the code. The
     * dotted-key-in-a-variable case - a read that no call-shape match would
     * see - is covered separately by NoDirectIdentityConfigRead, which scans
     * for the key itself across all four identity values.
     *
     * Mutation: read it a second time anywhere under app/.
     */
    public function test_the_client_secret_is_read_in_exactly_one_place(): void
    {
        $readers = [];

        foreach ($this->phpFiles(app_path()) as $file) {
            if (str_contains((string) file_get_contents($file), "config('identity.microsoft.client_secret')")) {
                $readers[] = basename($file);
            }
        }

        $this->assertSame(
            ['IdentityConfigurationSource.php'],
            $readers,
            'The client secret is read somewhere new. It is resolved at one line, becomes a '
            .'SecretPresence for every screen, and what leaves there cannot be turned back into the value.'
        );
    }

    /**
     * ...and NO IDENTITY SCREEN EVER RENDERS A SECRET VALUE.
     *
     * The claim used to be "no screen mentions client_secret at all", which was
     * enforceable while every screen was read-only. Gate C round 3 adds one
     * screen that must COLLECT a new secret, and a write-only password box is
     * not the thing this guard exists to prevent.
     *
     * SO THE CLAIM IS NARROWED TO WHAT IT ALWAYS MEANT: a screen may have an
     * input whose value the administrator is typing, and no screen may ever
     * DISPLAY one. The distinction is enforced structurally:
     *
     *   - the change screen's secret box is bound to its own empty form state
     *     and to no prop, so there is no value from the server for it to show;
     *   - `secretIsSet` is a BOOLEAN prop, which is what the screen is allowed
     *     to know;
     *   - every other Identity screen keeps the original prohibition.
     *
     * Mutation: pre-fill the secret box from a prop. The props assertion below
     * and PostInstallSsoChangeTest both fail.
     */
    public function test_no_identity_screen_renders_a_client_secret(): void
    {
        $collectors = ['EntraChange.jsx'];

        foreach (glob(resource_path('js/Pages/Identity/*.jsx')) ?: [] as $file) {
            $source = (string) file_get_contents($file);

            if (! in_array(basename($file), $collectors, true)) {
                $this->assertStringNotContainsString('client_secret', $source,
                    '['.basename($file).'] names the client secret. Only the change screen may '
                    .'collect one, and it may not show one.');
                $this->assertStringNotContainsString('clientSecret', $source);

                continue;
            }

            /*
             * THE COLLECTOR, HELD TO THE HARDER RULE.
             *
             * It must have a password input, and its value must come from the
             * form's own state - never from a prop, a destructured argument or
             * anything the server sent.
             */
            $this->assertStringContainsString('type="password"', $source,
                '['.basename($file).'] collects a client secret in something other than a password '
                .'field, so it is on screen and in the browser autofill store.');

            $this->assertStringContainsString('form.data.client_secret', $source);

            foreach (['value={client_secret', 'value={clientSecret', 'value={configuration.'] as $leak) {
                $this->assertStringNotContainsString($leak, $source,
                    '['.basename($file).'] binds the secret box to something the server sent. A '
                    .'secret that arrives pre-filled is a secret in the page source.');
            }

            // And the read model has no property it could have come from.
            $this->assertStringNotContainsString('clientSecret', $source);
        }
    }

    /**
     * A4. The idle timeout is written in exactly the three places §8.2 allows.
     *
     * Mutation: type 60 into the Session Policy page. The screen would then be
     * able to display a policy the system is not applying - which is the D-31
     * defect, recreated one layer further out.
     */
    public function test_the_idle_timeout_is_not_written_into_any_screen(): void
    {
        $sources = array_merge(
            glob(resource_path('js/Pages/Identity/*.jsx')) ?: [],
            glob(resource_path('js/Components/Identity*.jsx')) ?: [],
            $this->phpFiles(app_path('Modules/Identity/Http')),
        );

        $this->assertNotEmpty($sources);

        foreach ($sources as $file) {
            $source = (string) file_get_contents($file);

            $this->assertDoesNotMatchRegularExpression(
                '/\b60\s*(minutes|mins)\b/i',
                $source,
                "[{$file}] writes the idle timeout into a screen. Every number on Session Policy is "
                .'read from what enforces it.'
            );
        }

        // And the one place it IS written is the policy, once.
        $this->assertSame(60, SessionPolicy::APPROVED_IDLE_MINUTES);
    }

    /**
     * A4b. The approved constant is READ, not merely declared.
     *
     * If APPROVED_IDLE_MINUTES ever ends up referenced only by its own
     * declaration, that is IDLE_MINUTES all over again - and this is what says
     * so.
     *
     * Mutation: stop the health check comparing against it.
     */
    public function test_the_approved_policy_is_actually_read(): void
    {
        $readers = 0;

        foreach ($this->phpFiles(app_path()) as $file) {
            if (str_contains(basename($file), 'SessionPolicy.php')) {
                continue;
            }

            if (str_contains((string) file_get_contents($file), 'APPROVED_IDLE_MINUTES')) {
                $readers++;
            }
        }

        $this->assertGreaterThan(
            0,
            $readers,
            'Nothing outside SessionPolicy reads the approved idle timeout. A constant nothing '
            .'reads is exactly how the enforced policy drifted from the approved one.'
        );
    }

    /**
     * A5. An unapproved runtime provider fails the build.
     *
     * THIS REPLACES the earlier guard, which asserted the opposite: that
     * registering a second provider made it APPEAR as approved. That made
     * "approved" mean "present", so anything bound would have promoted itself
     * onto an administrator's screen as an approved way into the product.
     *
     * Mutation: bind a second IdentityProvider without a catalogue entry.
     */
    public function test_every_runtime_provider_is_an_approved_provider(): void
    {
        $inventory = app(ProviderInventory::class);

        $this->assertNotSame([], $inventory->runtimeKeys(), 'No providers were found, so this proves nothing.');

        $this->assertSame(
            [],
            $inventory->unapprovedKeys(),
            'An identity provider is registered that the Product Owner has not approved. A provider '
            .'that merely exists in the code is not an approved way to sign in.'
        );
    }

    /** A5c. Release 1's catalogue is exactly one entry. */
    public function test_the_approved_catalogue_is_one_entry(): void
    {
        $this->assertSame(['microsoft' => 'Microsoft Entra ID'], ApprovedProviders::all());
    }

    /**
     * A6. Every tab points at a route that exists.
     *
     * A tab added later without a route fails immediately, rather than rendering
     * a dead link nobody notices until a customer finds it.
     */
    public function test_every_identity_tab_points_at_a_real_route(): void
    {
        preg_match_all(
            "/href: '(\\/console\\/identity[^']*)'/",
            (string) file_get_contents(resource_path('js/Components/IdentityTabs.jsx')),
            $matches
        );

        $hrefs = $matches[1] ?? [];

        $this->assertCount(5, $hrefs, 'The tab strip is not the five approved sections.');

        $uris = array_map(fn ($route) => '/'.$route->uri(), iterator_to_array(Route::getRoutes()));

        foreach ($hrefs as $href) {
            $this->assertContains($href, $uris, "Tab [{$href}] points at a route that does not exist.");
        }
    }

    /**
     * A8. Exactly ONE class named RequireSystemAdministrator, and it is in
     * Platform.
     *
     * Mutation: leave the Organisation copy behind. Two authorisation gates
     * drift apart over the units that follow, and this is the one class where
     * being wrong means letting the wrong person in.
     */
    public function test_there_is_exactly_one_administrator_gate(): void
    {
        $found = [];

        foreach ($this->phpFiles(app_path()) as $file) {
            if (basename($file) === 'RequireSystemAdministrator.php') {
                $found[] = str_replace(app_path().'/', '', $file);
            }
        }

        $this->assertSame(['Modules/Platform/Http/Middleware/RequireSystemAdministrator.php'], $found);
    }

    /**
     * EXACTLY ONE IDENTITY SCREEN WRITES ANYTHING.
     *
     * The rule was "no Identity screen offers a save, because the unit has
     * nothing to save", and it was right for as long as that was true. Gate C
     * round 3 gives the unit something to save - a customer whose Entra client
     * secret expired had no route back except SSH - so the guard becomes an
     * EQUALITY rather than a prohibition.
     *
     * That is a narrowing, not a weakening. The dangerous thing was never "a
     * form exists"; it was "a form exists on a screen nobody decided should
     * have one", and a list of one name catches that where a blanket ban now
     * could not.
     *
     * Every other screen keeps the original rule, including the reason for it:
     * a read-only screen with a disabled Save is worse than one with none,
     * because it implies a capability that does not exist.
     *
     * Mutation: add a form to any other Identity page, or point the change
     * screen at a second route.
     */
    public function test_exactly_one_identity_screen_offers_a_save(): void
    {
        $writers = [];

        foreach (glob(resource_path('js/Pages/Identity/*.jsx')) ?: [] as $file) {
            // Comments first: these files explain in prose why there is no Save,
            // and the first version of this guard failed on its own docblock.
            $source = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents($file));

            /*
             * form.post( IS DELIBERATELY ABSENT from this list, and removing it
             * again would break a screen that is correct.
             *
             * SSO Health's "Check sign-in now" is a POST because it is an
             * action with a side effect, not because it saves anything - it
             * stores no configuration and the original guard excluded it for
             * that reason. A draft of this rewrite added form.post( and
             * immediately failed on Health.jsx, which is the guard reporting a
             * defect that is in the guard.
             *
             * What stops a new screen POSTing configuration is the route
             * equality above: it would need a route, and the route set is
             * asserted by name.
             */
            foreach (['Save', 'form.put(', 'form.patch(', 'form.delete('] as $editable) {
                if (str_contains($source, $editable)) {
                    $writers[] = basename($file);

                    break;
                }
            }
        }

        $this->assertSame(['EntraChange.jsx'], array_values(array_unique($writers)),
            'The set of Identity screens that write has changed. P1-02 owns exactly one change '
            .'path - Microsoft Entra - and a second one means two places that write one '
            .'configuration.');

        $change = (string) file_get_contents(resource_path('js/Pages/Identity/EntraChange.jsx'));

        $this->assertStringContainsString("form.put('/console/identity/entra')", $change,
            'The change screen posts somewhere other than the one approved write route.');
    }

    /** @return list<string> */
    private function phpFiles(string $directory): array
    {
        $files = [];

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }
}
