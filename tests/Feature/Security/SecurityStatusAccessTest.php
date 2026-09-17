<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Modules\Access\Support\RoleCode;
use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;
use App\Modules\Platform\Models\User;
use App\Modules\Security\Projection\WithheldRow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AccessFactory;
use Tests\Support\OrganisationFactory;
use Tests\TestCase;

/**
 * N-SS23, N-SS25, N-SS26, N-SS27 - WHO MAY LOOK, AND WHAT THEY SEE.
 *
 * The three roles holding EvidenceRead reach every screen. What they may VALUE
 * is narrower, and D-76 is enforced in the projection rather than in a
 * template - a template that hid a value would be a filter, and filters are
 * what later edits remove.
 */
final class SecurityStatusAccessTest extends TestCase
{
    use RefreshDatabase;

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

    /** A System Administrator reaches all four. */
    public function test_a_system_administrator_reaches_every_screen(): void
    {
        $user = $this->userWith(RoleCode::SystemAdministrator);

        foreach (self::SCREENS as $screen) {
            $this->signedInAs($user)->get($screen)->assertOk();
        }
    }

    /** N-SS27. An Auditor may read the evidence, and holds nothing else. */
    public function test_an_auditor_reaches_every_screen_and_no_other_administration_screen(): void
    {
        $user = $this->userWith(RoleCode::Auditor);

        foreach (self::SCREENS as $screen) {
            $this->signedInAs($user)->get($screen)->assertOk();
        }

        // EvidenceRead is the WHOLE of the Auditor's catalogue entry, so no
        // other administration screen opens. Mutation: add a class to the
        // Auditor's row in RoleCatalogue.
        foreach (['/console/identity', '/console/organisation', '/console/people/users', '/console/domains'] as $elsewhere) {
            $this->signedInAs($user)
                ->get($elsewhere)
                ->assertRedirect(route('auth.access-denied'));
        }
    }

    /** An Organisation Administrator reaches them too. */
    public function test_an_organisation_administrator_reaches_every_screen(): void
    {
        $user = $this->userWith(RoleCode::OrganisationAdministrator);

        foreach (self::SCREENS as $screen) {
            $this->signedInAs($user)->get($screen)->assertOk();
        }
    }

    /**
     * N-SS25. AN UNAUTHORISED REQUEST RETURNS NO SECURITY METADATA.
     *
     * Identical refusal whether the deployment is healthy or critical, and
     * whether the screen exists or not. A posture screen is an attacker's map;
     * the refusal must not be part of it.
     */
    public function test_a_business_user_is_refused_with_no_security_metadata(): void
    {
        $user = $this->userWith(RoleCode::BusinessUser);

        foreach (self::SCREENS as $screen) {
            $response = $this->signedInAs($user)->get($screen);

            $response->assertRedirect(route('auth.access-denied'));

            $body = $response->getContent() ?: '';

            foreach (['critical', 'unverified', 'Act now', 'Needs attention', 'administrator', 'posture'] as $leak) {
                $this->assertStringNotContainsString(
                    $leak,
                    $body,
                    "The refusal for {$screen} mentions \"{$leak}\", which tells an unauthorised "
                    .'caller something about this deployment.',
                );
            }
        }
    }

    /** And an anonymous request is refused the same way. */
    public function test_an_anonymous_request_is_refused(): void
    {
        foreach (self::SCREENS as $screen) {
            $this->get($screen)->assertRedirect();
            $this->get($screen)->assertDontSee('Act now');
        }
    }

    /**
     * N-SS26 THROUGH THE REAL HTTP STACK.
     *
     * The projection test proves the payloads are byte-identical; this proves
     * the CONTROLLER actually uses the projection, and that the rendered props
     * an Organisation Administrator receives carry no platform value.
     */
    public function test_an_organisation_administrator_receives_platform_rows_named_but_not_valued(): void
    {
        $orgAdmin = $this->userWith(RoleCode::OrganisationAdministrator);

        $response = $this->signedInAs($orgAdmin)->get('/console/security');
        $response->assertOk();

        $rows = $response->viewData('page')['props']['rows'];

        $withheld = array_values(array_filter($rows, static fn (array $r): bool => $r['withheld'] === true));
        $valued = array_values(array_filter($rows, static fn (array $r): bool => $r['withheld'] === false));

        $this->assertNotSame([], $withheld, 'Nothing was withheld, so D-76 is not in effect at all.');
        $this->assertNotSame([], $valued, 'Everything was withheld, so the screen is useless.');

        foreach ($withheld as $row) {
            $this->assertNull($row['state']);
            $this->assertNull($row['stateLabel']);
            $this->assertNull($row['ownerHref']);
            $this->assertSame(WithheldRow::SENTENCE, $row['finding']);

            // NAMED. A hidden row would make the count wrong.
            $this->assertNotSame('', $row['label']);
        }

        // A System Administrator sees the same rows WITH values.
        $sysAdmin = $this->userWith(RoleCode::SystemAdministrator);

        $full = $this->signedInAs($sysAdmin)->get('/console/security');
        $fullRows = $full->viewData('page')['props']['rows'];

        $this->assertSame(
            array_map(static fn (array $r): string => $r['control'], $rows),
            array_map(static fn (array $r): string => $r['control'], $fullRows),
            'The two viewers receive different ROWS. The same controls must be named for '
            .'everybody; only the values differ.',
        );

        $this->assertSame(
            [],
            array_values(array_filter($fullRows, static fn (array $r): bool => $r['withheld'] === true)),
        );
    }

    /**
     * The tab count an Organisation Administrator sees excludes withheld rows.
     *
     * A withheld critical reaching "Exceptions (4)" would be a disclosure
     * through a number nobody thinks of as a value.
     */
    public function test_the_exception_count_an_organisation_administrator_sees_excludes_withheld_rows(): void
    {
        $orgAdmin = $this->userWith(RoleCode::OrganisationAdministrator);

        $response = $this->signedInAs($orgAdmin)->get('/console/security/exceptions');
        $response->assertOk();

        $props = $response->viewData('page')['props'];

        $this->assertSame(
            count($props['exceptions']),
            $props['summary']['exceptionCount'],
            'The count and the list disagree, so one of them is derived separately.',
        );

        foreach ($props['exceptions'] as $exception) {
            $this->assertFalse($exception['withheld']);
        }
    }
}
