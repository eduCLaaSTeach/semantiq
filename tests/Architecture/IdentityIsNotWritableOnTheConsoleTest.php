<?php

declare(strict_types=1);

namespace Tests\Architecture;

use App\Modules\Access\Models\RoleAssignment;
use App\Modules\Access\Support\RoleCode;
use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;
use App\Modules\Platform\Models\User;
use App\Modules\Platform\Models\UserStatus;
use App\Modules\Platform\Setup\IntegrationFamily;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * D-148. PLATFORM INTEGRATIONS IS NOT A SECOND IDENTITY ADMINISTRATION
 * SURFACE.
 *
 * P1-02 owns Microsoft Entra configuration after installation - its screens,
 * its re-check, its masking and its reveal rules. Rendering the same editable
 * form under Platform Integrations would make two places to change one thing,
 * two sets of validation, and two chances for one of them to be more
 * permissive. The permissive one is the one that gets found.
 *
 * THE BOUNDARY IS IN THE ROUTE CONSTRAINT, not in a controller check. A
 * controller refusal is a refusal somebody can weaken; a route that does not
 * resolve has nothing to weaken.
 *
 * FIRST-RUN IS THE EXPLICIT EXCEPTION, and it is about WHO rather than about
 * ownership: the local Bootstrap principal is not a User and can reach no
 * `/console/*` route at all, so P1-02's screens are unreachable to it.
 */
final class IdentityIsNotWritableOnTheConsoleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<string> every console integrations route, as METHOD uri
     */
    private function consoleIntegrationRoutes(): array
    {
        $found = [];

        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'console/integrations')) {
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

        return $found;
    }

    /**
     * THE WRITABLE SET IS email, ai, fabric - AS AN EQUALITY.
     *
     * Mutation: add `identity` back to either constraint. This fails.
     */
    public function test_the_writable_families_are_exactly_email_ai_and_fabric(): void
    {
        $this->assertSame(
            ['email', 'ai', 'fabric'],
            array_map(
                static fn (IntegrationFamily $f): string => $f->value,
                IntegrationFamily::writableOnTheConsole(),
            ),
            'The set of families the normal console may write has changed.',
        );

        $this->assertFalse(IntegrationFamily::Identity->isWritableOnTheConsole(),
            'Identity is writable on the normal console, which makes Platform Integrations a '
            .'second Identity administration surface. P1-02 owns it after installation.');
    }

    /** ...and the routes agree, which is where it actually has to be true. */
    public function test_no_console_integrations_route_accepts_identity(): void
    {
        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'console/integrations')) {
                continue;
            }

            $pattern = $route->wheres['family'] ?? null;

            if ($pattern === null) {
                continue;
            }

            $this->assertDoesNotMatchRegularExpression(
                '/^'.$pattern.'$/',
                'identity',
                "[{$route->uri()}] accepts `identity`, so PUT /console/integrations/identity "
                .'resolves and Platform Integrations becomes a second Identity surface.',
            );

            foreach (['email', 'ai', 'fabric'] as $family) {
                $this->assertMatchesRegularExpression(
                    '/^'.$pattern.'$/',
                    $family,
                    "[{$route->uri()}] rejects [{$family}], so the constraint is too narrow and "
                    .'a family that SHOULD be writable is not.',
                );
            }
        }
    }

    /** The console route set is exactly this, so a new verb has to be justified. */
    public function test_the_console_integrations_route_set_is_exactly_this(): void
    {
        $this->assertSame(
            [
                'DELETE console/integrations/{family}/secret/{name}',
                'GET console/integrations',
                'POST console/integrations/{family}/test',
                'PUT console/integrations/{family}',
            ],
            $this->consoleIntegrationRoutes(),
            'The Platform Integrations route set changed. One read, one write, one test and one '
            .'explicit removal - and no reveal verb for any secret.',
        );
    }

    /** FIRST-RUN KEEPS ITS IDENTITY SETUP FORM. The exception is real. */
    public function test_first_run_can_still_set_up_identity(): void
    {
        $identityRoutes = [];

        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'first-run/integration')) {
                continue;
            }

            $pattern = $route->wheres['family'] ?? '';

            if ($pattern !== '' && preg_match('/^'.$pattern.'$/', 'identity') === 1) {
                $identityRoutes[] = implode('|', array_diff($route->methods(), ['HEAD'])).' '.$route->uri();
            }
        }

        $this->assertNotEmpty($identityRoutes,
            'First-Run can no longer set up identity. The Bootstrap principal cannot reach P1-02 '
            .'console screens at all, so removing this makes a fresh installation impossible.');
    }

    /**
     * THE PROPS THEMSELVES. The route constraint is the boundary; this is what
     * the administrator's browser actually receives.
     *
     * A ROUTE THAT DOES NOT RESOLVE IS NOT ENOUGH ON ITS OWN. The screen could
     * still ship the directory and application identifiers into the page source
     * and render an editable form that posts nowhere - which looks, to the
     * person reading it, exactly like a second Identity administration surface
     * that happens to be broken.
     *
     * Mutation: return $this->projection->all() from index() again. The
     * identity entry then arrives in `integrations` carrying `fields` and
     * `secrets`, and all three assertions below fail.
     */
    public function test_the_console_sends_identity_as_a_summary_with_no_fields_or_secrets(): void
    {
        $admin = User::query()->create([
            'provider' => 'microsoft',
            'external_subject' => 'oid-console-admin',
            'tenant_id' => 'tenant-1',
            'email' => 'admin@example.test',
            'display_name' => 'The Administrator',
            'status' => UserStatus::Active,
        ]);

        RoleAssignment::query()->create([
            'user_id' => $admin->id,
            'organisation_id' => null,
            'role_code' => RoleCode::SystemAdministrator,
            'assigned_at' => now(),
        ]);

        $response = $this->withSession([
            EnsureSessionIsCurrent::SESSION_USER_ID => $admin->id,
            EnsureSessionIsCurrent::SESSION_AUTHENTICATED_AT => now()->toIso8601String(),
        ])->get('/console/integrations');

        $response->assertOk();

        $props = $response->viewData('page')['props'];

        $writable = array_column($props['integrations'], 'family');

        $this->assertSame(['email', 'ai', 'fabric'], $writable,
            'The editable card set on Platform Integrations is no longer exactly the three '
            .'families the console owns.');

        $summaries = array_column($props['summaries'], 'family');

        $this->assertSame(['identity'], $summaries,
            'Microsoft sign-in is not rendered as a summary card, so the screen either edits it '
            .'or does not mention it at all.');

        $identity = $props['summaries'][0];

        // THE KEYS ARE ABSENT, not empty. An editor cannot render a field it
        // was never given, and a future edit cannot un-hide one.
        $this->assertArrayNotHasKey('fields', $identity,
            'The identity summary carries `fields`, so the directory and application identifiers '
            .'are in the page source of a screen that has no business holding them.');

        $this->assertArrayNotHasKey('secrets', $identity);
        $this->assertArrayNotHasKey('choices', $identity);

        // It must still SAY something, or it is not a summary.
        $this->assertArrayHasKey('status', $identity);
        $this->assertArrayHasKey('statusInWords', $identity);
    }

    /** First-Run still writes through P1-02's owning writer, not a second model. */
    public function test_first_run_identity_still_goes_through_the_owning_writer(): void
    {
        $controller = (string) file_get_contents(
            __DIR__.'/../../app/Modules/Platform/Setup/Http/Controllers/IntegrationController.php',
        );

        $this->assertStringContainsString('IntegrationConfigurationWriter', $controller,
            'The setup controller no longer names the owning writer, so it may be writing '
            .'identity configuration through a second model.');

        $this->assertStringNotContainsString('IntegrationConfiguration::query()->update', $controller,
            'The controller writes the configuration table directly, bypassing the writer that '
            .'owns validation, invalidation and the revision boundary.');
    }
}
