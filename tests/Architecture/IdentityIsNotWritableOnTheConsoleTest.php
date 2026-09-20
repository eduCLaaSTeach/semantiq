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

    /**
     * The console route set is exactly this, so a new verb has to be justified.
     *
     * THE SEND IS D-153's, added at Gate C round 3. It is the only route here
     * that causes something to leave the deployment, and it is deliberately the
     * narrowest shape in the table: no `{family}` - Email is the only family
     * that can send anything, so a parameter would have one legal value - and no
     * recipient, because the address comes from the signed-in principal.
     *
     * THE EXTRA READ IS THE GATE D TAB CORRECTION. Four integrations that were
     * four cards on one URL became four tabs, and a tab in this product is a
     * real link to a real URL. Microsoft Entra ID is the bare path - as Company
     * Profile is /console/organisation - so only the three writable families
     * needed a route, and the constraint on it excludes identity.
     *
     * IT IS A READ, and the equality is what makes that checkable: a PUT or
     * POST smuggled in beside it fails here rather than being noticed on a
     * screen. test_no_console_write_route_accepts_identity separately proves
     * the constraint on every non-GET route still rejects `identity`, and
     * PostInstallSsoChange proves PUT /console/integrations/identity is still
     * NOT FOUND rather than merely not allowed.
     *
     * NONE OF THEM WRITES IDENTITY, which is the claim the rest of this file
     * makes. The send route does not even have a family segment to accept it
     * with.
     */
    public function test_the_console_integrations_route_set_is_exactly_this(): void
    {
        $this->assertSame(
            [
                'DELETE console/integrations/{family}/secret/{name}',
                'GET console/integrations',
                'GET console/integrations/{family}',
                'POST console/integrations/email/send-test',
                'POST console/integrations/{family}/test',
                'PUT console/integrations/{family}',
            ],
            $this->consoleIntegrationRoutes(),
            'The Platform Integrations route set changed. Two reads - the landing tab and the '
            .'three writable tabs - one write, one connection test, one explicit removal and '
            .'one send, and no reveal verb for any secret.',
        );
    }

    /**
     * A signed-in System Administrator, which several cases below need.
     *
     * @return $this
     */
    private function signedInAsSystemAdministrator(): self
    {
        $admin = User::query()->firstOrCreate(
            ['external_subject' => 'oid-console-admin'],
            [
                'provider' => 'microsoft',
                'tenant_id' => 'tenant-1',
                'email' => 'admin@example.test',
                'display_name' => 'The Administrator',
                'status' => UserStatus::Active,
            ],
        );

        RoleAssignment::query()->firstOrCreate([
            'user_id' => $admin->id,
            'organisation_id' => null,
            'role_code' => RoleCode::SystemAdministrator,
        ], ['assigned_at' => now()]);

        return $this->withSession([
            EnsureSessionIsCurrent::SESSION_USER_ID => $admin->id,
            EnsureSessionIsCurrent::SESSION_AUTHENTICATED_AT => now()->toIso8601String(),
        ]);
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
     * GATE D. THE LANDING TAB IS THE IDENTITY TAB, so this case reads the same
     * URL it always did and gets `summary` where it used to get `summaries`.
     * The claim is unchanged and is if anything narrower: the screen now sends
     * ONE integration's props, so `integration` being null here says identity
     * has no editable shape on this surface at all.
     *
     * Mutation: send $view->toArray() for identity from renderTab(). The
     * directory and application identifiers arrive in `fields` and every
     * assertion below fails.
     */
    public function test_the_console_sends_identity_as_a_summary_with_no_fields_or_secrets(): void
    {
        $response = $this->signedInAsSystemAdministrator()->get('/console/integrations');

        $response->assertOk();

        $props = $response->viewData('page')['props'];

        $this->assertSame('identity', $props['active'],
            'The landing tab is no longer Microsoft Entra ID, so the menu leaf opens a different '
            .'screen from the one this file makes its claims about.');

        $this->assertNull($props['integration'],
            'Microsoft sign-in arrived as an EDITABLE integration on the console. That is the '
            .'second Identity administration surface D-148 removed.');

        $identity = $props['summary'];

        $this->assertSame('identity', $identity['family'],
            'Microsoft sign-in is not rendered as a summary, so the screen either edits it or '
            .'does not mention it at all.');

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

    /**
     * THE THREE WRITABLE TABS STILL CARRY THEIR FORMS. Gate D.
     *
     * The correction moved four cards onto four tabs. The risk it introduces is
     * the opposite of the one this file usually guards: not that identity
     * gained an editor, but that email, AI or Fabric quietly LOST theirs and
     * nobody noticed, because each now lives on a URL of its own that nothing
     * else renders.
     *
     * Mutation: send null for `integration` from renderTab().
     */
    public function test_each_writable_tab_still_receives_its_own_editable_configuration(): void
    {
        foreach (['email', 'ai', 'fabric'] as $family) {
            $response = $this->signedInAsSystemAdministrator()
                ->get("/console/integrations/{$family}");

            $response->assertOk();

            $props = $response->viewData('page')['props'];

            $this->assertSame($family, $props['active']);
            $this->assertNull($props['summary'], "[{$family}] arrived as a read-only summary.");

            $integration = $props['integration'];

            $this->assertSame($family, $integration['family']);
            $this->assertNotSame([], $integration['fields'],
                "[{$family}] lost its editable fields when it moved onto its own tab.");
            $this->assertArrayHasKey('secrets', $integration);
        }
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
