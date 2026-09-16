<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Modules\Security\Catalogue\ControlCatalogue;
use App\Modules\Security\Posture\PostureEvaluator;
use App\Modules\Security\Posture\PostureRow;
use App\Modules\Security\Posture\PostureState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * N-SS8a and N-SS12a - THE TWO WAYS THIS SCREEN WOULD LIE MOST EASILY.
 *
 * Both were found by MUTATION rather than by review. The suite as first written
 * covered the aggregation contract, the projection and the metric split, and
 * still let through:
 *
 *   reclassifying encryption from Unverified to NotApplicable, which silently
 *   writes an applicable control out of scope and out of the aggregate;
 *
 *   inferring the external half of step-up from the local half, which is the
 *   exact failure P1-05 hit in production - the local redirect configuration
 *   was right and step-up still failed, because Entra had not registered the
 *   URI.
 *
 * Neither is caught by anything else, because both produce a screen that looks
 * entirely healthy.
 */
final class BaselinePostureTest extends TestCase
{
    use RefreshDatabase;

    private function row(string $control): PostureRow
    {
        foreach (app(PostureEvaluator::class)->evaluate()->rows as $row) {
            if ($row->control === $control) {
                return $row;
            }
        }

        $this->fail("No row was produced for {$control}.");
    }

    /**
     * N-SS8a. AN APPLICABLE-BUT-UNOBSERVABLE CONTROL IS UNVERIFIED, NEVER
     * NOT_APPLICABLE.
     *
     * `not_applicable` means genuinely OUTSIDE Release 1. Encryption, backups
     * and web-exposure hardening all plainly APPLY to this product; what is
     * missing is evidence. Calling an applicable control "not applicable" takes
     * it out of the applicable set, so it stops contributing to the aggregate
     * at all - a way of getting to green that nobody has to argue for.
     */
    public function test_a_control_that_applies_but_cannot_be_observed_is_unverified_not_out_of_scope(): void
    {
        foreach ([
            ControlCatalogue::ENCRYPTION,
            ControlCatalogue::WEB_EXPOSURE,
            ControlCatalogue::BACKUPS,
        ] as $control) {
            $row = $this->row($control);

            $this->assertSame(
                PostureState::Unverified,
                $row->state,
                "{$control} is not Unverified. If it is NotApplicable, an applicable control has "
                .'been written out of scope and out of the aggregate.',
            );

            $this->assertNotSame(PostureState::NotApplicable, $row->state);
            $this->assertNotSame(PostureState::Healthy, $row->state);

            // And it CONTRIBUTES, which is the consequence that matters.
            $this->assertTrue(
                $row->state->contributes(),
                "{$control} no longer contributes to the aggregate.",
            );
        }
    }

    /**
     * D-83. NO ARTIFICIAL not_applicable ROW IS RENDERED IN RELEASE 1.
     *
     * The state stays in the model and in the fixtures, because the contract
     * must be right before the first real instance arrives. No CONTROL carries
     * it, because the product should report real controls rather than invented
     * rows.
     */
    public function test_no_release_one_control_is_marked_not_applicable(): void
    {
        $offenders = [];

        foreach (app(PostureEvaluator::class)->evaluate()->rows as $row) {
            if ($row->state === PostureState::NotApplicable) {
                $offenders[] = $row->control;
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'These controls render as "Not part of Release 1": '.implode(', ', $offenders)
            .'. D-83 says the UI reports real controls, not invented rows.',
        );
    }

    /**
     * N-SS12a. THE EXTERNAL HALF OF STEP-UP IS NEVER INFERRED FROM THE LOCAL
     * HALF.
     *
     * The local half is healthy here - the routes exist and the action
     * catalogue is populated. The external half must STILL be unverified,
     * because SemantIQ cannot see whether Microsoft has registered the return
     * address. P1-05 proved this failure mode is real.
     */
    public function test_the_external_step_up_half_stays_unverified_even_when_the_local_half_is_healthy(): void
    {
        $local = $this->row(ControlCatalogue::STEP_UP_LOCAL);
        $external = $this->row(ControlCatalogue::STEP_UP_EXTERNAL);

        $this->assertSame(
            PostureState::Healthy,
            $local->state,
            'The local half is not healthy, so this test cannot prove the external half is not '
            .'merely copying it.',
        );

        $this->assertSame(
            PostureState::Unverified,
            $external->state,
            'The external half reports the local half\'s answer. A configured local redirect URI '
            .'does not prove Microsoft has registered it - that is how a screen ends up green '
            .'over a redirect_uri_mismatch.',
        );

        $this->assertStringContainsString('cannot be checked from inside SemantIQ', $external->finding);
    }

    /** N-SS12a, again, on the Privileged Access Health copies of the same two facts. */
    public function test_the_privileged_screens_step_up_rows_agree_with_the_baseline_ones(): void
    {
        $this->assertSame(
            $this->row(ControlCatalogue::STEP_UP_LOCAL)->state,
            $this->row(ControlCatalogue::STEP_UP_LOCAL_PRIVILEGED)->state,
            'The two screens disagree about SemantIQ\'s side of step-up.',
        );

        $this->assertSame(
            PostureState::Unverified,
            $this->row(ControlCatalogue::STEP_UP_EXTERNAL_PRIVILEGED)->state,
        );
    }

    /**
     * THE EXTERNAL HALF IS A CONSTANT, not a branch - so it cannot be made to
     * lie by a configuration change.
     *
     * Read from the source, because that is where the property lives: no
     * runtime fixture can prove that no reachable branch returns Healthy.
     */
    public function test_the_external_step_up_half_has_no_branch_that_could_report_healthy(): void
    {
        $source = (string) file_get_contents(
            base_path('app/Modules/Security/Posture/Adapters/StepUpAdapter.php')
        );

        $start = strpos($source, 'private function externalHalf');
        $this->assertNotFalse($start, 'externalHalf has gone.');

        $body = substr($source, $start);
        $body = substr($body, 0, strpos($body, "\n    }") ?: strlen($body));

        $this->assertStringNotContainsString('PostureState::Healthy', $body);
        $this->assertStringNotContainsString('if (', $body);
        $this->assertStringNotContainsString('localHalf', $body);
        $this->assertStringContainsString('PostureState::Unverified', $body);
    }

    /**
     * N-SS12b. THE LOCAL HALF IS CRITICAL when a route it needs is missing.
     *
     * Collapsing both halves into one row is the mutation; so is treating a
     * missing route as merely unverified.
     */
    public function test_the_local_step_up_half_is_critical_when_a_route_is_missing(): void
    {
        $router = app('router');

        // Rebuild the route collection WITHOUT the step-up return. Nothing is
        // written; the collection is replaced for this request only.
        $kept = new \Illuminate\Routing\RouteCollection;

        foreach ($router->getRoutes() as $route) {
            if ($route->getName() === 'auth.microsoft.step-up') {
                continue;
            }

            $kept->add($route);
        }

        $router->setRoutes($kept);

        $adapter = new \App\Modules\Security\Posture\Adapters\StepUpAdapter($router);

        $local = null;

        foreach ($adapter->evidence() as $evidence) {
            if ($evidence->control === ControlCatalogue::STEP_UP_LOCAL) {
                $local = $evidence;
            }
        }

        $this->assertNotNull($local);

        $this->assertSame(
            PostureState::Critical,
            $local->state,
            'A missing step-up route is not reported as critical. Privileged changes would be '
            .'unconfirmable and the screen would not say so.',
        );
    }

    /** The sign-in reachability row is UNVERIFIED until somebody runs the probe. */
    public function test_an_unrun_live_probe_is_unverified_rather_than_healthy(): void
    {
        $row = $this->row(ControlCatalogue::SIGN_IN_REACHABLE);

        $this->assertSame(
            PostureState::Unverified,
            $row->state,
            'A live check nobody has run reported something other than "not verified". P1-02 '
            .'treats NotChecked as contributing nothing; P1-06 must not.',
        );
    }
}
