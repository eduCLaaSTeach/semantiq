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
     * 31 August 2026 as a reviewed transfer - the same way users moved to P1-00.
     * They are delivered, so forbidding them would now be false. Everything
     * still listed belongs to a unit that has NOT been delivered.
     */
    private const FORBIDDEN = [
        'roles', 'permissions', 'domains', 'business_domains', 'scopes',
        'sensitivity', 'entitlements', 'audit', 'access_reviews', 'fabric',
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
     * delivered Users & Groups, and neither before.
     */
    public function test_only_delivered_modules_exist(): void
    {
        $modules = array_map('basename', glob(__DIR__.'/../../app/Modules/*', GLOB_ONLYDIR) ?: []);

        sort($modules);

        $this->assertSame(
            ['Identity', 'Organisation', 'People', 'Platform'],
            $modules,
            'A module directory appeared for a unit that has not been delivered. '
            .'Directories are not pre-created to reserve them.'
        );
    }
}
