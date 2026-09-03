<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * D-01 and the P1-BASE boundary, enforced rather than trusted.
 *
 * P1-BASE ships Laravel framework baseline tables only. Role, domain, scope,
 * sensitivity, user, organisation and Fabric schema belong to later units, and
 * creating them early is how a "baseline" quietly becomes half of Phase 1.
 */
final class NoBusinessSchemaTest extends TestCase
{
    /*
     * organisations, teams and business_units were transferred to P1-01 on
     * 31 August 2026 as a reviewed transfer - the same way users moved to P1-00,
     * and `domains` / `business_domains` to P1-04 on 3 September 2026. They are
     * delivered, so forbidding them would now be false. Everything still listed
     * belongs to a unit that has NOT been delivered.
     *
     * WHAT DID NOT MOVE WITH THEM IS THE POINT. roles, permissions, scopes,
     * sensitivity, entitlements, audit, access_reviews and fabric all still
     * belong to units after P1-04, and this list staying intact is the cheapest
     * proof in the codebase that P1-04 did not pre-build P1-05. Removing one of
     * them to make an implementation pass is exactly the over-reach somebody
     * makes with a red test in the way - so P1_05_STILL_FORBIDDEN below asserts
     * they are all still here.
     */
    private const FORBIDDEN = [
        'roles', 'permissions', 'scopes',
        'sensitivity', 'entitlements', 'audit', 'access_reviews', 'fabric',
    ];

    /**
     * The subset of FORBIDDEN that P1-05 and later own.
     *
     * Asserted as PRESENT, not absent. Every other test here fails when
     * something forbidden appears; this one fails when something stops being
     * forbidden, which is the failure a transfer commit actually risks.
     */
    private const P1_05_STILL_FORBIDDEN = [
        'roles', 'permissions', 'scopes', 'sensitivity', 'entitlements',
    ];

    public function test_no_migration_creates_business_schema(): void
    {
        foreach (glob(__DIR__.'/../../database/migrations/*.php') ?: [] as $file) {
            preg_match_all("/Schema::create\(\s*'([^']+)'/", file_get_contents($file), $created);

            foreach ($created[1] ?? [] as $table) {
                $this->assertNotContains(
                    $table,
                    self::FORBIDDEN,
                    'Migration '.basename($file)." creates [{$table}], which is business schema "
                    .'owned by a later Phase 1 unit, not by P1-BASE.'
                );
            }
        }

        $this->assertTrue(true);
    }

    /**
     * Exactly the modules that have been delivered, and no others.
     *
     * The point is unchanged from P1-BASE: a directory is not pre-created to
     * reserve a name. Access and Audit still have no module here, and adding one
     * before its unit is approved is the failure this catches.
     *
     * Identity joined the list when P1-02 delivered it, People when P1-03
     * delivered Users & Groups, Domains when P1-04 delivered Business Domains,
     * and none of them before.
     */
    public function test_only_delivered_modules_exist(): void
    {
        $modules = array_map('basename', glob(__DIR__.'/../../app/Modules/*', GLOB_ONLYDIR) ?: []);

        sort($modules);

        $this->assertSame(
            ['Domains', 'Identity', 'Organisation', 'People', 'Platform'],
            $modules,
            'A module directory appeared for a unit that has not been delivered. '
            .'Directories are not pre-created to reserve them.'
        );
    }

    /**
     * The P1-05 names are STILL forbidden after the P1-04 transfer.
     *
     * A transfer removes exactly the names the delivered unit owns. The way
     * this guard is really lost is a wider deletion made in the moment - a red
     * test, a list in the way, and `scopes` or `sensitivity` going out with
     * `business_domains` because they were on the same line.
     */
    public function test_the_p1_05_names_are_still_forbidden(): void
    {
        foreach (self::P1_05_STILL_FORBIDDEN as $name) {
            $this->assertContains(
                $name,
                self::FORBIDDEN,
                "[{$name}] has been removed from the forbidden list. It belongs to P1-05 or later, "
                .'and P1-04 delivering Business Domains is not a reason to admit it.'
            );
        }
    }
}
