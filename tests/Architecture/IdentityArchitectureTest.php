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
     * A2. Exactly five GETs and two POSTs under identity. No PUT, PATCH or
     * DELETE.
     *
     * Mutation: add a PUT. A write route added later fails the build rather than
     * quietly becoming the .env editor this unit is defined as not having.
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
            'GET console/identity/health',
            'GET console/identity/login-experience',
            'GET console/identity/providers',
            'GET console/identity/session-policy',
            'POST console/identity/entra/reveal',
            'POST console/identity/health/re-check',
        ], $found, 'The Identity route set changed. Five reads and two actions, and nothing else.');
    }

    /**
     * A3. client_secret is referenced in exactly ONE place in the module.
     *
     * PHP cannot make reading a config value impossible - any class can call
     * config(). So this does not claim to be a structural guarantee. It narrows
     * the surface to one line and fails when a second appears.
     *
     * Mutation: read it a second time anywhere under app/Modules/Identity.
     */
    public function test_the_client_secret_is_read_in_exactly_one_place(): void
    {
        $readers = [];

        foreach ($this->phpFiles(app_path('Modules/Identity')) as $file) {
            // The CONFIG READ specifically. IdentityHealthCheck names a row
            // 'client_secret', which is a label and not a disclosure - matching
            // the bare string flagged it and would have taught the next person
            // to loosen this guard rather than the code.
            if (str_contains((string) file_get_contents($file), "config('identity.microsoft.client_secret')")) {
                $readers[] = basename($file);
            }
        }

        $this->assertSame(
            ['IdentityConfigurationReport.php'],
            $readers,
            'The client secret is read somewhere new. It becomes a SecretPresence at one line, and '
            .'what leaves there cannot be turned back into the value.'
        );
    }

    /** ...and no Identity screen source mentions it at all. */
    public function test_no_identity_screen_mentions_the_client_secret(): void
    {
        foreach (glob(resource_path('js/Pages/Identity/*.jsx')) ?: [] as $file) {
            $this->assertStringNotContainsString('client_secret', (string) file_get_contents($file));
            $this->assertStringNotContainsString('clientSecret', (string) file_get_contents($file));
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

    /** No Identity screen offers a save, because the unit has nothing to save. */
    public function test_no_identity_screen_offers_a_save(): void
    {
        foreach (glob(resource_path('js/Pages/Identity/*.jsx')) ?: [] as $file) {
            // Comments first: these files explain in prose why there is no Save,
            // and the first version of this guard failed on its own docblock.
            $source = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents($file));

            foreach (['Save', 'form.put(', 'form.patch(', 'form.delete('] as $editable) {
                $this->assertStringNotContainsString(
                    $editable,
                    $source,
                    '['.basename($file).'] offers editing. A read-only screen with a disabled Save is '
                    .'worse than one with none: it implies a capability that does not exist.'
                );
            }
        }
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
