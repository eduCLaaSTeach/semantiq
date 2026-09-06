<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Modules\Access\StepUp\PendingStepUp;
use App\Modules\Access\StepUp\StepUpAction;
use App\Modules\Access\StepUp\StepUpService;
use App\Modules\Access\Support\AccessViolation;
use App\Modules\Access\Support\RoleCatalogue;
use App\Modules\Access\Support\RoleCode;
use App\Modules\Access\Support\Sensitivity;
use App\Modules\Platform\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\OrganisationFactory;
use Tests\TestCase;

/**
 * N-S1 to N-S11. D-73 STEP-UP RE-AUTHENTICATION.
 *
 * ONE STEP-UP AUTHORISES ONE ACTION, ONCE. Every failure path performs no
 * privileged action AND consumes the reference - failing forward converts a
 * cancelled step-up into a reusable one, and it is exactly the change somebody
 * makes to be helpful.
 */
final class StepUpTest extends TestCase
{
    use RefreshDatabase;

    private OrganisationFactory $make;

    private StepUpService $stepUp;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->make = new OrganisationFactory;
        $this->stepUp = app(StepUpService::class);

        $organisation = $this->make->organisation();
        $this->actor = $this->make->user($organisation, administrator: true);
    }

    /**
     * N-S1. THE FIVE ACTIONS, and only five.
     *
     * Mutation: drop one. Each is an action that changes who holds privileged
     * authority, or that raises information above the level everybody else
     * sees.
     */
    public function test_exactly_five_actions_require_step_up(): void
    {
        $this->assertSame(
            [
                'grant_system_administrator',
                'revoke_system_administrator',
                'grant_organisation_administrator',
                'self_grant',
                'grant_restricted_sensitivity',
            ],
            array_map(static fn (StepUpAction $action): string => $action->value, StepUpAction::cases()),
        );

        // The two roles, named where the catalogue names them.
        $this->assertSame(
            [RoleCode::SystemAdministrator, RoleCode::OrganisationAdministrator],
            RoleCatalogue::requiringStepUp(),
        );

        // And exactly one sensitivity level.
        $requiring = array_values(array_filter(
            Sensitivity::cases(),
            static fn (Sensitivity $level): bool => $level->requiresStepUpToGrant(),
        ));

        $this->assertSame([Sensitivity::Restricted], $requiring);
    }

    /**
     * N-S5. THE ACTION IS STORED SERVER-SIDE, and the reference is HASHED.
     *
     * Nothing about the action travels in the URL: a URL somebody can edit is
     * an action somebody can substitute. And a reader of this table cannot
     * replay a reference, because the plaintext is never written.
     *
     * Mutation: put the target id in the redirect; store the reference in
     * plain text.
     */
    public function test_the_action_is_stored_server_side_and_the_reference_is_hashed(): void
    {
        $subject = $this->make->user();

        $reference = $this->stepUp->begin($this->actor, 'session-a', StepUpAction::GrantSystemAdministrator, [
            'subject_user_id' => $subject->id,
            'role_code' => RoleCode::SystemAdministrator->value,
        ]);

        $row = PendingStepUp::query()->sole();

        $this->assertNotSame($reference, $row->reference_hash, 'The reference was stored in plain text.');
        $this->assertSame(PendingStepUp::hashFor($reference), $row->reference_hash);

        // The target came from the row, not from anything a browser carried.
        $this->assertSame($subject->id, $row->subject_user_id);
        $this->assertSame(RoleCode::SystemAdministrator->value, $row->role_code);
    }

    /**
     * N-S2 and N-S10. SINGLE USE. A replay is refused AND LOGGED.
     *
     * Mutation: refuse silently - the replay then leaves no trace.
     */
    public function test_a_reference_can_be_consumed_only_once(): void
    {
        $reference = $this->begin();

        $pending = $this->stepUp->resolve($reference, $this->actor, 'session-a');

        $performed = $this->stepUp->consumeAndPerform($pending, $this->actor, fn (): string => 'done');

        $this->assertSame('done', $performed);

        // The second attempt finds a consumed row and refuses.
        $this->expectException(AccessViolation::class);

        $this->stepUp->resolve($reference, $this->actor, 'session-a');
    }

    /**
     * The reference is bound to the SESSION and to the USER.
     *
     * A reference stolen from a log is useless in another browser, and useless
     * to another person. Both bindings are broken separately, because a
     * developer closing one would not necessarily close the other.
     */
    public function test_a_reference_is_bound_to_the_session_and_the_user(): void
    {
        $reference = $this->begin();

        try {
            $this->stepUp->resolve($reference, $this->actor, 'a-different-session');
            $this->fail('A step-up reference was accepted in a different session.');
        } catch (AccessViolation) {
            // And the mismatch CONSUMED it, so it cannot be retried correctly.
            $this->assertNotNull(PendingStepUp::query()->sole()->consumed_at);
        }

        $reference = $this->begin();
        $other = $this->make->user();

        try {
            $this->stepUp->resolve($reference, $other, 'session-a');
            $this->fail('A step-up reference was accepted for a different person.');
        } catch (AccessViolation) {
            $this->assertNotNull(
                PendingStepUp::query()->where('reference_hash', PendingStepUp::hashFor($reference))->sole()->consumed_at,
            );
        }
    }

    /**
     * N-S8. AN EXPIRED REFERENCE PERFORMS NO ACTION AND CANNOT BE REUSED.
     *
     * Mutation: extend it on return.
     */
    public function test_an_expired_reference_is_refused_and_consumed(): void
    {
        $reference = $this->begin();

        Carbon::setTestNow(now()->addMinutes(PendingStepUp::LIFETIME_MINUTES + 1));

        try {
            $this->stepUp->resolve($reference, $this->actor, 'session-a');
            $this->fail('An expired step-up reference was accepted.');
        } catch (AccessViolation) {
            $row = PendingStepUp::query()->sole();

            $this->assertNotNull($row->consumed_at, 'An expired reference was left reusable.');
            $this->assertSame('expired', $row->outcome);
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * N-S3 and N-S9. FRESHNESS COMES FROM THE PROVIDER, and every failing
     * shape denies.
     *
     * Three separate conditions, each of which fails closed on its own:
     * absent, predating the request, and outside the tolerance. An
     * application-set flag is forbidden - the application would be asserting
     * freshness rather than proving it.
     *
     * Mutation: accept a missing claim; accept an auth_time from the original
     * sign-in.
     */
    public function test_every_failing_freshness_shape_denies_and_consumes(): void
    {
        /*
         * THE THREE CONDITIONS, ISOLATED FROM EACH OTHER.
         *
         * "predates the request" was first written as now()->subHour(), which
         * ALSO falls outside the tolerance - so a mutation removing the
         * not-before-the-request check SURVIVED, caught by the other condition
         * instead. It is now an auth_time that is comfortably WITHIN the
         * tolerance and still older than the request, which only that one check
         * can reject.
         */
        $withinTolerance = (int) (PendingStepUp::FRESHNESS_TOLERANCE_SECONDS / 2);

        $cases = [
            'absent' => [null, 0],
            'predates the request' => [now()->subSeconds($withinTolerance), $withinTolerance - 30],
            'outside the tolerance' => [now()->subSeconds(PendingStepUp::FRESHNESS_TOLERANCE_SECONDS + 60), 0],
        ];

        foreach ($cases as $label => [$authTime, $requestedSecondsAgo]) {
            $reference = $this->begin();
            $pending = $this->stepUp->resolve($reference, $this->actor, 'session-a');

            if ($requestedSecondsAgo > 0) {
                // The step-up was requested AFTER the provider says the person
                // authenticated - the shape a stale sign-in takes.
                $pending->forceFill(['requested_at' => now()->subSeconds($requestedSecondsAgo)])->save();
                $pending->refresh();
            }

            try {
                $this->stepUp->verifyFreshness($pending, $authTime, $this->actor);
                $this->fail("A step-up with an auth_time that {$label} was accepted.");
            } catch (AccessViolation) {
                $row = $pending->fresh();

                $this->assertNotNull($row->consumed_at, "[{$label}] left the reference reusable.");
                $this->assertSame('stale_freshness', $row->outcome);
            }
        }
    }

    /**
     * ...and the half that makes those non-vacuous: a genuinely fresh
     * auth_time IS accepted.
     *
     * Without this, a verifier that rejected everything would pass every case
     * above.
     */
    public function test_a_fresh_auth_time_is_accepted(): void
    {
        $reference = $this->begin();
        $pending = $this->stepUp->resolve($reference, $this->actor, 'session-a');

        $this->stepUp->verifyFreshness($pending, now(), $this->actor);

        $this->assertNull($pending->fresh()->consumed_at, 'A valid step-up was consumed by the freshness check.');
    }

    /**
     * N-S6 and N-S7. A PROVIDER ERROR OR A CANCELLATION PERFORMS NO ACTION AND
     * CONSUMES THE REFERENCE.
     *
     * Mutation: leave it reusable "so they can try again" - failing forward.
     */
    public function test_a_cancellation_performs_no_action_and_consumes_the_reference(): void
    {
        $reference = $this->begin();
        $pending = $this->stepUp->resolve($reference, $this->actor, 'session-a');

        $this->stepUp->abandon($pending, 'provider_error', $this->actor);

        $row = $pending->fresh();

        $this->assertNotNull($row->consumed_at);
        $this->assertSame('provider_error', $row->outcome);

        // And it cannot be picked up again.
        $this->expectException(AccessViolation::class);

        $this->stepUp->resolve($reference, $this->actor, 'session-a');
    }

    /**
     * N-S11. A FRESH AUTHENTICATION DOES NOT SATISFY A SECOND ACTION.
     *
     * There is no time window in which everything is privileged. Somebody who
     * has just completed a step-up FOR A DIFFERENT ACTION must step up again.
     *
     * Mutation: open a window.
     */
    public function test_one_step_up_does_not_authorise_a_second_action(): void
    {
        $first = $this->begin();
        $second = $this->stepUp->begin($this->actor, 'session-a', StepUpAction::GrantRestrictedSensitivity, []);

        $pending = $this->stepUp->resolve($first, $this->actor, 'session-a');
        $this->stepUp->consumeAndPerform($pending, $this->actor, fn (): bool => true);

        // The second action still has its OWN pending row, still unconsumed -
        // completing the first authorised nothing about it.
        $secondRow = PendingStepUp::query()
            ->where('reference_hash', PendingStepUp::hashFor($second))
            ->sole();

        $this->assertNull(
            $secondRow->consumed_at,
            'Completing one step-up consumed another, which means one confirmation covered two '
            .'privileged actions.'
        );

        // And it still has to be resolved and verified on its own terms.
        $resolved = $this->stepUp->resolve($second, $this->actor, 'session-a');

        $this->assertSame(StepUpAction::GrantRestrictedSensitivity, $resolved->action);
    }

    /**
     * NOTHING IN THE CODEBASE CLAIMS MFA.
     *
     * SemantIQ can verify that Microsoft reports a fresh authentication event.
     * It cannot prove which credential or factor was required unless the
     * tenant's Entra policy guarantees it - so "MFA verified" must not appear
     * on a screen, in a log, or in evidence.
     *
     * Mutation: add the claim anywhere.
     */
    public function test_nothing_claims_that_multi_factor_authentication_was_verified(): void
    {
        $scanned = 0;

        foreach ([base_path('app'), base_path('resources/js')] as $root) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

            foreach ($files as $file) {
                if (! $file->isFile() || ! in_array($file->getExtension(), ['php', 'jsx'], true)) {
                    continue;
                }

                $scanned++;

                /*
                 * COMMENTS STRIPPED FIRST.
                 *
                 * StepUpController's own docblock says: Nothing here says "MFA
                 * verified". A guard that matched prose would fail on the
                 * documentation written to prevent the defect - the same lesson
                 * P1-04 learned the other way round, where a docblock must not
                 * SATISFY an assertion. Only what the code emits counts.
                 */
                $source = $this->codeOnly((string) file_get_contents($file->getPathname()), $file->getExtension());

                foreach (['MFA verified', 'mfa_verified', 'multi-factor verified', 'two-factor verified'] as $claim) {
                    $this->assertStringNotContainsString(
                        $claim,
                        $source,
                        basename($file->getPathname())." claims [{$claim}]. Step-up proves a fresh "
                        .'authentication EVENT, not a factor.'
                    );
                }
            }
        }

        $this->assertGreaterThan(50, $scanned, 'Almost no files were scanned.');
    }

    /**
     * Source with comments removed. token_get_all for PHP rather than a regular
     * expression, because a regex over source gets strings containing slashes
     * wrong - and a guard that is wrong in a corner is a guard nobody trusts.
     */
    private function codeOnly(string $source, string $extension): string
    {
        if ($extension !== 'php') {
            // JSX. Block comments and line comments, in that order.
            $stripped = preg_replace('#/\*.*?\*/#s', '', $source) ?? $source;

            return preg_replace('#^\s*//.*$#m', '', $stripped) ?? $stripped;
        }

        $kept = '';

        foreach (token_get_all($source) as $token) {
            if (is_array($token)) {
                if (in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }

                $kept .= $token[1];

                continue;
            }

            $kept .= $token;
        }

        return $kept;
    }

    private function begin(): string
    {
        return $this->stepUp->begin($this->actor, 'session-a', StepUpAction::GrantSystemAdministrator, [
            'subject_user_id' => $this->make->user()->id,
            'role_code' => RoleCode::SystemAdministrator->value,
        ]);
    }
}
