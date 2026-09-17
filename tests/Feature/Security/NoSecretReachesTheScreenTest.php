<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Modules\Access\Support\RoleCode;
use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;
use App\Modules\Platform\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\Support\AccessFactory;
use Tests\Support\OrganisationFactory;
use Tests\TestCase;

/**
 * N-SS23 - NO SECRET CROSSES THE RENDER BOUNDARY.
 *
 * THE GUARD IS ADVERSARIAL, NOT A SPELLING CHECK. A regex for "secret-shaped"
 * values passes over a short secret, an unlucky one, or one that happens to
 * look like a word. So the real mechanism is a SEEDED FIXTURE SECRET planted in
 * configuration and then searched for BY EQUALITY across the whole prop tree,
 * the JSON, and the log output - for all three authorised roles.
 *
 * The shape-based sweep runs as well, because the two catch different things:
 * equality catches the value we planted, and the shapes catch a value we did
 * not think to plant.
 */
final class NoSecretReachesTheScreenTest extends TestCase
{
    use RefreshDatabase;

    private const SEEDED_SECRET = 'SEMANTIQ-FIXTURE-SECRET-8f3a27c1d9b64e05';

    private const SEEDED_CLIENT_ID = 'SEMANTIQ-FIXTURE-CLIENT-4d1e77aa';

    private const SEEDED_TENANT = 'SEMANTIQ-FIXTURE-TENANT-90b2ccf1';

    private const SCREENS = [
        '/console/security',
        '/console/security/privileged-access',
        '/console/security/exceptions',
        '/console/security/events',
    ];

    private OrganisationFactory $make;

    private AccessFactory $access;

    protected function setUp(): void
    {
        parent::setUp();

        $this->make = new OrganisationFactory;
        $this->access = new AccessFactory;

        // Planted where the real ones live, so a passthrough would pick these
        // up exactly as it would pick up production values.
        config([
            'identity.microsoft.client_secret' => self::SEEDED_SECRET,
            'identity.microsoft.client_id' => self::SEEDED_CLIENT_ID,
            'identity.microsoft.tenant_id' => self::SEEDED_TENANT,
        ]);
    }

    private function signedInAs(User $user): self
    {
        return $this->withSession([
            EnsureSessionIsCurrent::SESSION_USER_ID => $user->id,
            EnsureSessionIsCurrent::SESSION_AUTHENTICATED_AT => now()->toIso8601String(),
        ]);
    }

    private function userWith(RoleCode $role): User
    {
        $organisation = $this->make->organisation();
        $user = $this->make->user($organisation);
        $this->access->assignment($user, $role, $organisation);

        return $user;
    }

    /**
     * THE HEADLINE. Every screen, every authorised role, searched by EQUALITY.
     */
    public function test_no_seeded_secret_appears_in_any_rendered_prop_for_any_role(): void
    {
        $roles = [
            RoleCode::SystemAdministrator,
            RoleCode::OrganisationAdministrator,
            RoleCode::Auditor,
        ];

        foreach ($roles as $role) {
            $user = $this->userWith($role);

            foreach (self::SCREENS as $screen) {
                $response = $this->signedInAs($user)->get($screen);
                $response->assertOk();

                $props = json_encode($response->viewData('page')['props']) ?: '';
                $body = $response->getContent() ?: '';

                foreach ([self::SEEDED_SECRET, self::SEEDED_CLIENT_ID, self::SEEDED_TENANT] as $secret) {
                    $this->assertStringNotContainsString(
                        $secret,
                        $props,
                        "A seeded secret reached the props of {$screen} for {$role->value}.",
                    );

                    $this->assertStringNotContainsString(
                        $secret,
                        $body,
                        "A seeded secret reached the rendered body of {$screen} for {$role->value}.",
                    );
                }
            }
        }
    }

    /**
     * And the shape sweep, for a value nobody thought to plant.
     */
    public function test_no_secret_shaped_value_appears_in_any_rendered_prop(): void
    {
        $user = $this->userWith(RoleCode::SystemAdministrator);

        $shapes = [
            '/eyJ[A-Za-z0-9_\-]{10,}/' => 'a JSON Web Token',
            '/-----BEGIN [A-Z ]+-----/' => 'a PEM block',
            '/\b[A-Fa-f0-9]{40,}\b/' => 'a long hexadecimal run',
            '/\b[A-Za-z0-9+\/]{60,}={0,2}\b/' => 'a long base64 run',
        ];

        foreach (self::SCREENS as $screen) {
            $props = json_encode(
                $this->signedInAs($user)->get($screen)->viewData('page')['props']
            ) ?: '';

            foreach ($shapes as $pattern => $what) {
                $this->assertDoesNotMatchRegularExpression(
                    $pattern,
                    $props,
                    "{$screen} renders something shaped like {$what}.",
                );
            }
        }
    }

    /** Nothing these screens do writes a secret to the log either. */
    public function test_rendering_writes_no_secret_to_the_log(): void
    {
        $written = [];

        Log::listen(static function ($message) use (&$written): void {
            $written[] = json_encode([$message->message, $message->context]);
        });

        $user = $this->userWith(RoleCode::SystemAdministrator);

        foreach (self::SCREENS as $screen) {
            $this->signedInAs($user)->get($screen)->assertOk();
        }

        $log = implode("\n", $written);

        foreach ([self::SEEDED_SECRET, self::SEEDED_CLIENT_ID, self::SEEDED_TENANT] as $secret) {
            $this->assertStringNotContainsString($secret, $log);
        }
    }

    /**
     * P1-06 HAS NO REVEAL. P1-02's own screen has one; this unit must not.
     *
     * The client secret is evidence BY PRESENCE ONLY.
     */
    public function test_there_is_no_reveal_route_under_security_status(): void
    {
        $user = $this->userWith(RoleCode::SystemAdministrator);

        /*
         * 404, not 405. A path that does not exist at all answers 404; 405 is
         * what an EXISTING path answers to the wrong verb. Asserting 405 here
         * was wrong and would have started passing the moment somebody added a
         * reveal route with a different verb.
         */
        foreach (['/console/security/reveal', '/console/security/entra/reveal'] as $guess) {
            $this->signedInAs($user)->post($guess)->assertNotFound();
            $this->signedInAs($user)->get($guess)->assertNotFound();
        }

        // And no route anywhere under the prefix is named for revealing.
        foreach (app('router')->getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'console/security')) {
                continue;
            }

            $this->assertStringNotContainsString('reveal', $route->uri());
            $this->assertSame(['GET', 'HEAD'], $route->methods());
        }
    }

    /**
     * N-SS24 AT RUNTIME. Every mutating verb is refused on every screen.
     *
     * The architecture guard proves no such route is DECLARED; this proves the
     * router agrees, so a route added through some other mechanism would still
     * be caught.
     */
    public function test_no_mutating_verb_is_accepted_on_any_security_screen(): void
    {
        $user = $this->userWith(RoleCode::SystemAdministrator);

        foreach (self::SCREENS as $screen) {
            foreach (['post', 'put', 'patch', 'delete'] as $verb) {
                $response = $this->signedInAs($user)->{$verb}($screen);

                $this->assertSame(
                    405,
                    $response->getStatusCode(),
                    "{$screen} accepted a {$verb}. A mandatory control could be switched off.",
                );
            }
        }
    }
}
