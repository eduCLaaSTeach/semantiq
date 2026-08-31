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
    private const FORBIDDEN = [
        'roles', 'permissions', 'domains', 'business_domains', 'scopes',
        'sensitivity', 'entitlements', 'organisations', 'organizations', 'teams',
        'business_units', 'audit', 'access_reviews', 'fabric',
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

    public function test_only_the_platform_module_exists(): void
    {
        $modules = array_map('basename', glob(__DIR__.'/../../app/Modules/*', GLOB_ONLYDIR) ?: []);

        $this->assertSame(
            ['Platform'],
            $modules,
            'P1-BASE creates exactly one module. Directories are not pre-created to reserve them.'
        );
    }
}
