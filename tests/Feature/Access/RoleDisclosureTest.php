<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Modules\Access\Support\ActionClass;
use App\Modules\Access\Support\RoleCatalogue;
use App\Modules\Access\Support\RoleCode;
use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;
use App\Modules\Platform\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AccessFactory;
use Tests\Support\OrganisationFactory;
use Tests\TestCase;

/**
 * WHAT THE ROLE PICKER TELLS AN ADMINISTRATOR MUST BE TRUE.
 *
 * RoleCode::summary() is not a label. AccessController::roleOptions() sends it
 * straight to the Roles & Access picker, so it is the sentence somebody reads
 * at the moment they decide to delegate a role. A summary that UNDERSTATES a
 * role is worse than no summary at all: it does not merely fail to inform, it
 * actively reassures.
 *
 * THIS FILE EXISTS BECAUSE THAT HAPPENED. Organisation Administrator read "No
 * platform authority and no business data" - correct when written, and the
 * reason it was written - until CL-12's amended ruling granted PlatformAdmin.
 * For the length of that change the picker promised NO PLATFORM AUTHORITY
 * while delegating Identity & SSO, System Health and Integrations, which
 * includes the Entra client secret and the deployment-wide integration
 * credentials. Raised as a P1 on #149, and correctly.
 *
 * SO THE DISCLOSURE IS TESTED AGAINST THE GRANT, not just against itself. The
 * exact-text cases below would pass against any sentence somebody typed; the
 * cases that tie the words to RoleCatalogue are what make the wording
 * accountable to the authorisation it describes.
 *
 * NOTHING HERE CHANGES AUTHORISATION. This is disclosure and UX alignment. The
 * action classes, the navigation and the grant rules are asserted unchanged.
 */
final class RoleDisclosureTest extends TestCase
{
    use RefreshDatabase;

    /** The Product Owner's approved wording, verbatim. */
    private const ORGANISATION_ADMINISTRATOR_SUMMARY =
        'Full organisation and platform administration, including Identity & SSO, '
        .'System Health and Integrations. No business data. Cannot grant System Administrator.';

    private OrganisationFactory $make;

    private AccessFactory $access;

    protected function setUp(): void
    {
        parent::setUp();

        $this->make = new OrganisationFactory;
        $this->access = new AccessFactory;
    }

    // =================================================================
    // The words.
    // =================================================================

    /** D1. The approved sentence, exactly. */
    public function test_the_organisation_administrator_summary_is_the_approved_wording(): void
    {
        $this->assertSame(
            self::ORGANISATION_ADMINISTRATOR_SUMMARY,
            RoleCode::OrganisationAdministrator->summary(),
            'The Organisation Administrator summary is not the Product Owner approved wording.'
        );
    }

    /**
     * D2. AND IT DOES NOT SAY "No platform authority".
     *
     * Stated separately from D1 although D1 implies it, because this is the
     * specific false claim the P1 finding was about. If somebody rewords the
     * summary later, D1 tells them it changed; this tells them WHICH sentence
     * must never come back.
     *
     * Mutation: restore the original summary. D1 and D2 both fail, and D2's
     * message names the reason rather than printing a string diff.
     */
    public function test_the_organisation_administrator_summary_does_not_deny_platform_authority(): void
    {
        $this->assertStringNotContainsStringIgnoringCase(
            'No platform authority',
            RoleCode::OrganisationAdministrator->summary(),
            'The role picker tells an administrator that Organisation Administrator has no '
            .'platform authority, while the role holds PlatformAdmin and delegates Identity & '
            .'SSO, System Health and Integrations. That understates what is being granted at '
            .'the moment somebody decides to grant it.'
        );
    }

    /**
     * D3. THE WORDS ARE ACCOUNTABLE TO THE GRANT.
     *
     * The case that stops this file becoming a spell-checker. D1 and D2 pass
     * against any sentence somebody types; this one fails if the summary and
     * RoleCatalogue ever disagree about platform authority in EITHER
     * direction - a role that gains PlatformAdmin while still denying it, and
     * a role that claims it without holding it.
     */
    public function test_no_role_summary_denies_an_authority_the_role_actually_holds(): void
    {
        foreach (RoleCatalogue::roles() as $role) {
            $summary = $role->summary();
            $holdsPlatform = RoleCatalogue::permits($role, ActionClass::PlatformAdmin);

            if ($holdsPlatform) {
                $this->assertStringNotContainsStringIgnoringCase(
                    'no platform',
                    $summary,
                    "[{$role->value}] holds PlatformAdmin and its summary denies platform authority."
                );
            }

            if (! $holdsPlatform) {
                $this->assertStringNotContainsStringIgnoringCase(
                    'platform administration',
                    $summary,
                    "[{$role->value}] does not hold PlatformAdmin and its summary claims platform "
                    .'administration.'
                );
            }

            if (RoleCatalogue::permits($role, ActionClass::BusinessData)) {
                $this->assertStringNotContainsStringIgnoringCase(
                    'no business data',
                    $summary,
                    "[{$role->value}] holds BusinessData and its summary denies business data."
                );
            }
        }
    }

    /** D4. The System Administrator summary is untouched. */
    public function test_the_system_administrator_summary_is_unchanged(): void
    {
        $this->assertSame(
            'Full platform and system administration, including Identity and single sign-on. No business data.',
            RoleCode::SystemAdministrator->summary(),
            'The System Administrator summary changed. This correction is about the Organisation '
            .'Administrator only.'
        );
    }

    // =================================================================
    // The screen.
    // =================================================================

    /**
     * D5. THE CORRECTED SENTENCE REACHES THE ROLES & ACCESS PICKER.
     *
     * Read from the rendered props, not from the enum. A corrected enum that
     * the screen never sends would leave the administrator reading the old
     * promise, and every assertion above would still pass.
     */
    public function test_the_roles_and_access_picker_sends_the_corrected_summary(): void
    {
        $response = $this->actingAsAdministrator()->get('/console/access');

        $response->assertOk();

        $roles = $response->viewData('page')['props']['roles'] ?? [];

        $this->assertNotEmpty($roles, 'The picker sent no roles, so this guard asserts nothing.');

        $summaries = [];

        foreach ($roles as $role) {
            $summaries[$role['value']] = $role['summary'];
        }

        $this->assertArrayHasKey(
            RoleCode::OrganisationAdministrator->value,
            $summaries,
            'The picker does not offer Organisation Administrator at all.'
        );

        $this->assertSame(
            self::ORGANISATION_ADMINISTRATOR_SUMMARY,
            $summaries[RoleCode::OrganisationAdministrator->value],
            'The Roles & Access picker is still sending a summary that is not the approved '
            .'wording.'
        );

        $this->assertStringNotContainsStringIgnoringCase(
            'No platform authority',
            $summaries[RoleCode::OrganisationAdministrator->value],
            'The screen an administrator actually reads still denies platform authority.'
        );
    }

    // =================================================================
    // And the authorisation did not move while the words did.
    // =================================================================

    /** D6. Exactly the four administration classes, unchanged by this correction. */
    public function test_the_organisation_administrator_action_classes_are_unchanged(): void
    {
        $this->assertSame(
            [
                ActionClass::PlatformAdmin,
                ActionClass::OrgAdmin,
                ActionClass::AccessAdmin,
                ActionClass::EvidenceRead,
            ],
            RoleCatalogue::classesFor(RoleCode::OrganisationAdministrator),
            'The Organisation Administrator action classes changed. This task corrects wording '
            .'only.'
        );
    }

    /** D7. BusinessData is still absent. */
    public function test_the_organisation_administrator_still_does_not_hold_business_data(): void
    {
        $this->assertFalse(
            RoleCatalogue::permits(RoleCode::OrganisationAdministrator, ActionClass::BusinessData),
            'An Organisation Administrator gained BusinessData.'
        );
    }

    /** D8. And still cannot grant System Administrator - which the summary now promises. */
    public function test_the_organisation_administrator_still_cannot_grant_system_administrator(): void
    {
        $grantable = RoleCatalogue::grantableBy(RoleCode::OrganisationAdministrator);

        $this->assertNotEmpty($grantable, 'No roles were read, so this guard asserts nothing.');

        $this->assertNotContains(
            RoleCode::SystemAdministrator,
            $grantable,
            'An Organisation Administrator can grant System Administrator - and the role summary '
            .'now tells administrators that they cannot, so the disclosure would be false in the '
            .'more dangerous direction.'
        );
    }

    private function actingAsAdministrator(): self
    {
        $organisation = $this->make->organisation();
        $user = $this->make->user($organisation);
        $this->access->assignment($user, RoleCode::SystemAdministrator, $organisation);

        return $this->signedInAs($user);
    }

    private function signedInAs(User $user): self
    {
        return $this->withSession([
            EnsureSessionIsCurrent::SESSION_USER_ID => $user->id,
            EnsureSessionIsCurrent::SESSION_AUTHENTICATED_AT => now()->toIso8601String(),
        ]);
    }
}
