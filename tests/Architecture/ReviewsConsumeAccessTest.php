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
