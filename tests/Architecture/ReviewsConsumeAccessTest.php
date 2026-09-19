<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * P1-07 CONSUMES THE ACCESS MODEL. IT DOES NOT REINTERPRET IT.
 *
 * A second implementation of revocation is a second interpretation of access,
 * and this unit's whole premise is that there is one. These guards make the
 * three ways that could happen unrepresentable rather than discouraged - the
 * same technique that caught a second precedence implementation in P1-06.
 */
final class ReviewsConsumeAccessTest extends TestCase
{
    private const MODULE = __DIR__.'/../../app/Modules/Reviews';

    /**
     * NOTHING IN P1-07 ENDS ACCESS BY ITSELF.
     *
     * Mutation: write `$entitlement->update(['ended_at' => now()])` in the
     * decision service instead of calling EntitlementService::revoke().
     */
    public function test_no_review_code_ends_access_directly(): void
    {
        foreach ($this->sources() as $path => $code) {
            $this->assertDoesNotMatchRegularExpression(
                "/'ended_at'\s*=>/",
                $code,
                "[{$path}] writes ended_at itself. Ending access is P1-05's, through "
                .'RoleAssignmentService::revoke() or EntitlementService::revoke().'
            );

            $this->assertDoesNotMatchRegularExpression(
                '/->(delete|forceDelete|truncate)\(\)/',
                $code,
                "[{$path}] deletes rows. Nothing in this system deletes access; revoking ends a period."
            );
        }
    }

    /**
     * EXACTLY ONE AUTHORITY ALGORITHM, and it derives from the catalogue.
     *
     * Mutation: add a second grantableBy-shaped list in the controller.
     */
    public function test_authority_is_decided_in_exactly_one_place(): void
    {
        $sources = $this->sources();

        $deciders = array_keys(array_filter(
            $sources,
            static fn (string $code): bool => str_contains($code, 'grantableBy('),
        ));

        $this->assertSame(
            ['Services/ReviewerAuthority.php'],
            $deciders,
            'More than one file decides reviewer authority from the role catalogue. Two lists agree '
            .'on the day they are written and drift afterwards.'
        );
    }

    /**
     * EXACTLY ONE SUPERSEDE RULE.
     *
     * Mutation: inline a second fingerprint comparison in a controller.
     */
    public function test_the_supersede_rule_exists_in_exactly_one_place(): void
    {
        $sources = $this->sources();

        // The model merely NAMES the column in $fillable and $casts, which is
        // not a comparison. What must exist once is the comparison itself.
        $comparers = array_keys(array_filter(
            $sources,
            static fn (string $code): bool => str_contains($code, 'Composition::fingerprint('),
        ));

        $this->assertSame(
            ['Services/ReviewCycleGenerator.php', 'Services/ReviewDecisionService.php'],
            $comparers,
            'The composition fingerprint is computed outside generation and the decision service, so '
            .'two places can disagree about whether a review is still valid.'
        );

        // And only the decision service COMPARES it.
        $this->assertSame(
            1,
            substr_count($sources['Services/ReviewDecisionService.php'], 'hash_equals('),
            'The supersede comparison is not in exactly one place.'
        );
    }

    /**
     * RETAIN WRITES NOTHING TO THE ACCESS MODEL - visible in the code, not only
     * in a test fixture.
     *
     * Mutation: call the entitlement service from the retain branch.
     */
    public function test_the_retain_branch_touches_no_access_service(): void
    {
        $code = (string) file_get_contents(self::MODULE.'/Services/ReviewDecisionService.php');

        $this->assertMatchesRegularExpression(
            '/if \(\$decision === ReviewDecision::Revoke\) \{\s*\$this->revoke\(/',
            $code,
            'The decision service no longer calls revoke() ONLY on the revoke branch, so retain may be '
            .'reaching the access model.'
        );
    }

    /**
     * THE DECISION IS TAKEN UNDER A LOCK.
     *
     * Recorded honestly: the mutation that removes lockForUpdate SURVIVES the
     * behavioural suite on SQLite, because a single-threaded test cannot
     * observe a lock that is not there. The real evidence is the MySQL
     * concurrency run in CI; this guard exists so the lock cannot quietly
     * disappear between those runs.
     *
     * Mutation: delete lockForUpdate() from ReviewDecisionService.
     */
    public function test_the_decision_and_the_reviewed_object_are_locked(): void
    {
        $code = (string) file_get_contents(self::MODULE.'/Services/ReviewDecisionService.php');

        $this->assertSame(
            3,
            substr_count($code, 'lockForUpdate()'),
            'The decision service no longer locks the item and both reviewed-object shapes. '
            .'Two reviewers can then decide the same review at once.'
        );
    }

    /**
     * THE DEPENDENCY RUNS ONE WAY. P1-07 consumes P1-05; P1-05 names nothing
     * from P1-07.
     *
     * GATE C BLOCKER 2. The first implementation had P1-05's StepUpController
     * importing P1-07's models and services. A later unit would have added a
     * second import, and an accepted unit would slowly have become a
     * switchboard for every unit that came after it.
     *
     * Mutation: import any App\Modules\Reviews class into app/Modules/Access.
     */
    public function test_the_access_module_never_names_the_reviews_module(): void
    {
        $offenders = [];
        $base = realpath(__DIR__.'/../../app/Modules/Access');

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base)) as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            if (str_contains((string) file_get_contents($file->getPathname()), 'Modules\\Reviews')) {
                $offenders[] = str_replace($base.'/', '', $file->getPathname());
            }
        }

        sort($offenders);

        $this->assertSame(
            [],
            $offenders,
            'P1-05 names P1-07. The approved boundary is that later units consume Access, never the '
            .'reverse - the step-up completion registry exists so a unit can register itself.'
        );
    }

    /**
     * ONE STEP-UP BINDS TO ONE EXACT SUBJECT AND ONE EXACT INTENT.
     *
     * GATE C BLOCKER 1. The completion must address the item by the id stored
     * on the confirmation, never search for one by the access object - a
     * terminal item with a later pending review for the same access would
     * otherwise hand the confirmation to a review nobody saw.
     *
     * Mutation: look the item up by role_assignment_id or domain_entitlement_id.
     */
    public function test_the_review_completion_addresses_its_item_by_the_stored_id(): void
    {
        $code = (string) file_get_contents(self::MODULE.'/StepUp/ReviewStepUpCompletion.php');

        $this->assertStringContainsString('find($pending->subject_id)', $code);
        $this->assertStringContainsString('$pending->subject_intent', $code);

        foreach (['role_assignment_id', 'domain_entitlement_id', 'ReviewState::Pending'] as $searchShape) {
            $this->assertStringNotContainsString(
                $searchShape,
                $code,
                'The completion searches for an item instead of addressing the one that was confirmed.'
            );
        }
    }

    /**
     * THE CONTROLLER DISPATCHES UNCLAIMED ACTIONS TO THE REGISTRY.
     *
     * RECORDED HONESTLY: this is a SOURCE guard, and it is here because the
     * mutation that replaces the dispatch with a refusal SURVIVES the
     * behavioural suite. The binding tests exercise the completion directly;
     * the one line that joins the controller to it can only be driven through a
     * real Microsoft round trip, which this project cannot automate - the same
     * limitation P1-05 recorded for step-up, verified in a browser instead.
     *
     * Mutation: replace the default arm with a refusal.
     */
    public function test_the_step_up_controller_dispatches_to_the_registry(): void
    {
        $code = (string) file_get_contents(__DIR__.'/../../app/Modules/Access/Http/Controllers/StepUpController.php');

        $this->assertStringContainsString(
            'default => $this->performRegistered($pending, $actor),',
            $code,
            'Actions P1-05 does not own no longer reach the unit that registered for them.'
        );

        $this->assertStringContainsString(
            '$completion = $this->completions->for($pending->action);',
            $code,
        );
    }

    /**
     * THE DECISION IS THE REQUEST BODY, NOT A FORM DEFAULT.
     *
     * GATE D DEFECT 3. The screen used useForm({ decision: 'retain' }) and then
     * post(url, { data: { decision } }). Inertia types those submit options as
     * Omit<VisitOptions, 'data'> - the key is EXCLUDED - so it was silently
     * dropped and EVERY click sent `retain`. "Remove this access" quietly
     * confirmed it.
     *
     * The guard is on the SHAPE because that is where the defect lived: the
     * behavioural tests pass either way when the server is asked directly, and
     * this project has no JavaScript test runner to click the button.
     *
     * Mutation: go back to useForm for the decision, or pass a `data` key to a
     * useForm submit.
     */
    public function test_a_review_decision_is_never_submitted_from_a_form_default(): void
    {
        /*
         * COMMENTS ARE STRIPPED FIRST.
         *
         * The screen carries a comment explaining the defect, and that comment
         * necessarily quotes the broken call - so the guard matched its own
         * prose and failed on correct code. A guard that cannot tell code from
         * a note about code is worse than none: the obvious fix is to delete
         * the explanation, which is the one thing that must survive.
         */
        $screen = (string) preg_replace(
            ['#/\*.*?\*/#s', '#(^|\s)//[^\n]*#'],
            '',
            (string) file_get_contents(__DIR__.'/../../resources/js/Components/ReviewPage.jsx'),
        );

        $this->assertStringContainsString(
            'router.post(',
            $screen,
            'The decision is no longer sent explicitly. A form default is how "remove" became "retain".'
        );

        /*
         * AND IT SENDS THE VARIABLE, NOT A LITERAL.
         *
         * Asserting only that router.post is used let a hardcoded
         * { decision: 'retain' } through - which is the original defect with a
         * different spelling. Found by mutating the guard rather than by
         * reading it.
         */
        $this->assertMatchesRegularExpression(
            '/router\.post\(\s*`[^`]*decide`\s*,\s*\{\s*decision\s*\}/',
            $screen,
            'The decision sent is not the one the button chose.'
        );

        $this->assertDoesNotMatchRegularExpression(
            "/decision:\s*'(retain|revoke)'/",
            $screen,
            'A decision is hardcoded in the client call. Both buttons then send the same thing.'
        );

        $this->assertDoesNotMatchRegularExpression(
            "/useForm\(\{\s*decision/",
            $screen,
            'A review decision is initialised as a form default again.'
        );

        $this->assertDoesNotMatchRegularExpression(
            '/post\([^)]*\{\s*\n?\s*data:/',
            $screen,
            'A useForm submit is being passed a `data` key, which Inertia excludes and silently drops.'
        );
    }

    /**
     * ONE SCREEN OWNS THE START CONTROL.
     *
     * GATE D DEFECT 1. A cycle is one global cycle covering both populations,
     * so offering it on three tabs offered the same action three times - and
     * from the other two it returned the person to Privileged Reviews, which
     * looked like a navigation bug.
     *
     * Mutation: pass offersStart on another screen.
     */
    public function test_only_the_privileged_screen_offers_to_start_a_cycle(): void
    {
        $controller = (string) file_get_contents(self::MODULE.'/Http/Controllers/AccessReviewsController.php');

        $this->assertSame(
            1,
            substr_count($controller, 'offersStart: true'),
            'More than one screen offers to start a review cycle. A cycle is one global cycle.'
        );
    }

    /**
     * NOTHING MUTABLE CARRIES THE DECISION.
     *
     * Mutation: reintroduce a pending_decision column on the review item.
     */
    public function test_no_review_column_holds_a_decision_in_flight(): void
    {
        foreach ($this->sources() as $path => $code) {
            $this->assertStringNotContainsString(
                'pending_decision',
                $code,
                "[{$path}] holds a decision on the item while a confirmation is away. A second tab can "
                .'then change what the returning confirmation performs.'
            );
        }
    }

    /**
     * EVERY REVIEW QUERY IS ORGANISATION-SCOPED.
     *
     * GATE C BLOCKER 3. The System Administrator role is platform-scoped, so
     * "the actor's assignment has no organisation" must never widen into "every
     * organisation".
     *
     * Mutation: drop the organisation filter from scopeVisible() or basisFor().
     */
    public function test_visibility_and_authority_are_organisation_scoped(): void
    {
        $code = (string) file_get_contents(self::MODULE.'/Services/ReviewerAuthority.php');

        $this->assertStringContainsString("\$query->where('organisation_id', \$organisationId);", $code);
        $this->assertStringContainsString('$actor->organisation_id !== $item->organisation_id', $code);
    }

    /** @return array<string, string> path relative to the module => contents */
    private function sources(): array
    {
        $files = [];
        $base = realpath(self::MODULE);

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base));

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $files[str_replace($base.'/', '', $file->getPathname())] = (string) file_get_contents($file->getPathname());
        }

        ksort($files);

        return $files;
    }
}
