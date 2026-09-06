<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Modules\Access\Support\DecisionReason;
use App\Modules\Access\Support\ReasonNarrator;
use App\Modules\Access\Support\RoleCode;
use App\Modules\Access\Support\ScopeType;
use App\Modules\Access\Support\Sensitivity;
use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;
use App\Modules\Platform\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AccessFactory;
use Tests\Support\OrganisationFactory;
use Tests\Support\ScreenSource;
use Tests\TestCase;

/**
 * N-EN4, N-C9, N-C10, N-Q2. WHAT THE SCREENS SAY.
 *
 * Every screen-source assertion reads ScreenSource::rendered(), which strips
 * comments - because these screens document their copy decisions in prose that
 * QUOTES the copy, and an assertion satisfied by a docblock proves nothing. P1-03
 * shipped exactly that: a cell was changed to render nothing and the assertion
 * still passed against the comment above it.
 */
final class PresentationTest extends TestCase
{
    use RefreshDatabase;

    private OrganisationFactory $make;

    private AccessFactory $access;

    protected function setUp(): void
    {
        parent::setUp();

        $this->make = new OrganisationFactory;
        $this->access = new AccessFactory;
    }

    /**
     * N-EN4. THE REASON MAPPING IS TOTAL, and no raw code reaches a screen.
     *
     * Mutation: add a reason code with no sentence. It must FAIL rather than
     * fall through to the enum - "raw enum values on a user-facing surface" is
     * exactly what the CLAUDE.md §4 gate names, and this makes it structural
     * rather than a review habit.
     */
    public function test_every_reason_code_has_a_business_language_sentence(): void
    {
        $narrated = ReasonNarrator::all();

        $this->assertCount(
            count(DecisionReason::cases()),
            $narrated,
            'A reason code has no business-language sentence.'
        );

        foreach (DecisionReason::cases() as $reason) {
            $sentence = $narrated[$reason->value];

            $this->assertNotSame('', trim($sentence));

            // The sentence must not BE the code, nor contain it.
            $this->assertStringNotContainsString(
                $reason->value,
                $sentence,
                "The sentence for [{$reason->value}] contains the code itself."
            );

            // It reads as a sentence: a capital and a full stop.
            $this->assertMatchesRegularExpression(
                '/^[A-Z].*\.$/s',
                $sentence,
                "The sentence for [{$reason->value}] does not read as a sentence."
            );
        }
    }

    /**
     * NO RAW ENUM VALUE APPEARS IN ANY ACCESS SCREEN'S RENDERED OUTPUT.
     *
     * The screens carry enum values as form VALUES - a <option value="manager">
     * is correct and necessary. What must never appear is a raw value where a
     * label belongs.
     *
     * Asserted on the served HTML rather than on the source, because that is
     * what a person reads.
     */
    public function test_no_raw_enum_value_is_rendered_as_a_label(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);
        $domain = $this->access->domain($organisation);

        $entitlement = $this->access->completePath($admin, $domain, RoleCode::Manager);

        foreach ([
            '/console/access',
            "/console/access/assignments/{$entitlement->role_assignment_id}",
            '/console/access/simulator/run',
        ] as $uri) {
            $body = $this->signedInAs($admin)->get($uri)->getContent();

            // Strip attribute values, which legitimately carry enum values.
            $visible = (string) preg_replace('/\\\\?"[a-z_]+\\\\?"/', '', (string) $body);

            foreach ([
                ...array_map(static fn (DecisionReason $r): string => $r->value, DecisionReason::cases()),
                ...array_map(static fn (RoleCode $r): string => $r->value, RoleCode::cases()),
                ...array_map(static fn (ScopeType $s): string => $s->value, ScopeType::cases()),
            ] as $raw) {
                $this->assertStringNotContainsString(
                    '>'.$raw.'<',
                    $visible,
                    "[{$uri}] renders the raw value [{$raw}] where a label belongs."
                );
            }
        }
    }

    /**
     * N-C9. AN INCOMPLETE ENTITLEMENT SAYS SO, IN THOSE WORDS.
     *
     * The exact sentence, because "shows something" is not the requirement -
     * the requirement is that a reader is told the grant does not work and why.
     *
     * Mutation: show it as an ordinary active entitlement.
     */
    public function test_an_incomplete_entitlement_says_no_access_scope_required(): void
    {
        $source = ScreenSource::rendered('Pages/Access/Record.jsx');

        $this->assertStringContainsString('No access — scope required', $source);
        $this->assertStringContainsString(
            'has no active scope and currently grants no',
            $source,
            'The screen does not explain WHY an incomplete entitlement grants nothing.'
        );

        // And it is driven by the server's answer, not recomputed in the screen.
        $this->assertStringContainsString('entitlement.incomplete', $source);

        // Behaviourally: the flag is true when the last scope is gone.
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);
        $domain = $this->access->domain($organisation);

        $assignment = $this->access->assignment($admin, RoleCode::Manager, $organisation);
        $entitlement = $this->access->entitlement($assignment, $domain);
        $this->access->ceiling($entitlement, Sensitivity::Standard);

        $props = $this->signedInAs($admin)
            ->get("/console/access/assignments/{$assignment->id}")
            ->viewData('page')['props'];

        $this->assertTrue(
            $props['entitlements'][0]['incomplete'],
            'An entitlement with no scope was not reported as incomplete, so the screen would show '
            .'it as though it worked.'
        );
    }

    /**
     * N-C10. AN ADMINISTRATOR CAN FIND incomplete entitlements.
     *
     * A state that exists but nobody can reach is the P1-04 discoverability
     * defect. Here the record page carries it per entitlement, and the LIST
     * carries the count of current entitlements so a role with none is visible
     * without opening it.
     */
    public function test_an_entitlement_that_grants_nothing_is_discoverable_from_the_list(): void
    {
        $source = ScreenSource::rendered('Pages/Access/Index.jsx');

        $this->assertStringContainsString(
            'None — grants no access',
            $source,
            'The list does not distinguish a role with no entitlements from one that works.'
        );

        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);

        $props = $this->signedInAs($admin)->get('/console/access')->viewData('page')['props'];

        $this->assertArrayHasKey('entitlementCount', $props['assignments']['data'][0]);
    }

    /**
     * N-Q2. THE SCREEN STATES THE D-74 EQUIVALENCE.
     *
     * Both halves: that Domain and Organisation grant the same records today,
     * AND that Domain is reserved for a future partition. Two identical choices
     * presented without explanation is a trap - the next person to grant access
     * assumes one of them must be narrower.
     *
     * Mutation: delete the sentence.
     */
    public function test_the_screens_state_that_domain_and_organisation_are_the_same_today(): void
    {
        /*
         * TWO SCREENS, TWO INSTRUMENTS, because the sentence lives in two
         * different places on purpose.
         *
         * On the RECORD page it is literal copy shown beside the scope picker
         * at the moment somebody chooses one, so it is asserted in the rendered
         * source.
         *
         * On the SIMULATOR it comes from the SERVER as a prop - one sentence,
         * one place - so it is asserted on what the server actually sends. That
         * is the stronger of the two: a docblock cannot satisfy it at all.
         */
        $record = ScreenSource::rendered('Pages/Access/Record.jsx');

        $this->assertStringContainsString('grant the same records today', $record);
        $this->assertStringContainsString('reserved for a future', $record);

        // And it is shown for BOTH whole-domain scopes rather than only one.
        $this->assertStringContainsString('chosenScope.coversWholeDomain', $record);

        foreach ([ScopeType::Domain, ScopeType::Organisation] as $scope) {
            $this->assertTrue(
                $scope->coversWholeDomain(),
                "[{$scope->value}] no longer reports as covering the whole domain, so the screen "
                .'would stop explaining the equivalence for it.'
            );
        }

        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);

        $props = $this->signedInAs($admin)
            ->get('/console/access/simulator/run')
            ->viewData('page')['props'];

        $note = (string) $props['scopeEquivalenceNote'];

        $this->assertStringContainsString('grant the same records today', $note);
        $this->assertStringContainsString('reserved for a future', $note);

        // The simulator renders it, rather than merely receiving it.
        $this->assertStringContainsString(
            'scopeEquivalenceNote',
            ScreenSource::rendered('Pages/Access/Simulator.jsx'),
        );
    }

    /**
     * THE STEP-UP CARD ASKS FOR NO CREDENTIAL.
     *
     * There is no password field and nothing imitating one - the button leaves
     * for Microsoft. A dialog that merely said "are you sure?" would be a
     * confirmation prompt wearing the word "step-up", which is what this unit
     * was told not to build.
     *
     * Mutation: add a password input, or replace the redirect with a confirm.
     */
    public function test_the_step_up_card_collects_no_credential(): void
    {
        $source = ScreenSource::rendered('Pages/Access/StepUp.jsx');

        foreach (['type="password"', 'password', 'passcode', 'window.confirm'] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source,
                "The step-up card contains [{$forbidden}]. SemantIQ never handles a credential, and "
                .'a dialog is not step-up.'
            );
        }

        $this->assertStringContainsString('Continue to Microsoft', $source);

        // And it says what is about to happen, before anybody leaves.
        $this->assertStringContainsString('You are about to', $source);
    }

    /**
     * THE HEADER SAYS A ROLE ALONE GRANTS NOTHING, on every Access screen.
     *
     * This is the one thing a reader must not assume on the screen where access
     * is actually granted.
     */
    public function test_every_access_screen_says_a_role_alone_grants_nothing(): void
    {
        $source = ScreenSource::rendered('Components/AccessPage.jsx');

        $this->assertStringContainsString('A role on its own grants', $source);
        $this->assertStringContainsString('nothing', $source);
    }

    /**
     * N-M21, the visible half. THE SOLE-ADMINISTRATOR WARNING IS INFORMATIONAL.
     *
     * role="status", not role="alert" - it is worth seeing every time and must
     * never read as though something has been blocked.
     *
     * Mutation: render it as a refusal.
     */
    public function test_the_sole_administrator_warning_is_a_status_and_not_a_refusal(): void
    {
        $source = ScreenSource::rendered('Components/AccessPage.jsx');

        $this->assertMatchesRegularExpression(
            '/org-notice[^>]*role="status"/s',
            $source,
            'The sole-administrator warning is not role="status". It is informational and must not '
            .'interrupt.'
        );

        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);

        $props = $this->signedInAs($admin)->get('/console/access')->viewData('page')['props'];

        $this->assertStringContainsString(
            'Only one active System Administrator remains',
            (string) $props['soleAdministratorWarning'],
        );

        // With two, it is absent rather than empty-but-rendered.
        $this->make->user($organisation, administrator: true);

        $props = $this->signedInAs($admin)->get('/console/access')->viewData('page')['props'];

        $this->assertNull($props['soleAdministratorWarning']);
    }

    private function signedInAs(User $user): self
    {
        return $this->withSession([
            EnsureSessionIsCurrent::SESSION_USER_ID => $user->id,
            EnsureSessionIsCurrent::SESSION_AUTHENTICATED_AT => now()->toIso8601String(),
        ]);
    }
}
