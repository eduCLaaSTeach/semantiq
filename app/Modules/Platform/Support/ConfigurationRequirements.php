<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support;

/**
 * What configuration this build actually needs.
 *
 * Correction 4 at design approval: P1-BASE must not refuse to boot because P1-00
 * Microsoft configuration does not exist yet. The two sets below are data, so
 * P1-00 promotes the Microsoft keys by moving them from DECLARED to REQUIRED -
 * one edit, in one place, that cannot be half-done.
 *
 * No placeholder secrets. A key that is declared but not yet required stays
 * empty. Inventing a fake value to satisfy a validator only moves the failure
 * from boot, where it is obvious, to the identity provider, where it is not.
 */
final class ConfigurationRequirements
{
    /**
     * Always required, whatever the environment.
     *
     * Database keys are NOT listed here: which ones matter depends on the active
     * connection, and hardcoding the MySQL set would make the validator wrong
     * everywhere MySQL is not the driver. ConfigurationValidator resolves those
     * from the default connection instead.
     *
     * @return list<string>
     */
    public static function required(): array
    {
        return ['app.key', 'app.env', 'app.url', 'database.default'];
    }

    /**
     * Connection keys that must be set, by driver.
     *
     * SQLite needs only a database path or :memory:; a server driver also needs
     * a username to connect with.
     *
     * @return array<string, list<string>>
     */
    public static function connectionKeysByDriver(): array
    {
        return [
            'mysql' => ['database', 'username'],
            'mariadb' => ['database', 'username'],
            'pgsql' => ['database', 'username'],
            'sqlsrv' => ['database', 'username'],
            'sqlite' => ['database'],
        ];
    }

    /**
     * Configuration keys required in production, read from config().
     *
     * THE MICROSOFT IDENTITY KEYS ARE NO LONGER LISTED HERE, and their absence
     * is the point rather than an oversight.
     *
     * P1-00 put them here because .env was the only identity authority. P1-10
     * makes that one of two: a deployment whose identity_source is `store`
     * reads the store, and its .env identity keys are EXPECTED to be empty -
     * they are retired by an operator once the store is proven, and a genuinely
     * fresh installation never had them at all.
     *
     * Listing them as production requirements would have made the health
     * screens report a correctly configured store-backed deployment as
     * misconfigured, naming four environment variables it is right not to have.
     * A false red on a working system is worse than no check, and it is the
     * same failure the identity screens were corrected for: reporting on a
     * source the sign-in path does not use.
     *
     * THE REQUIREMENT ITSELF HAS NOT BEEN DROPPED. ConfigurationValidator now
     * asks IdentityConfigurationSource whether the RESOLVED identity
     * configuration is complete, which is the same question asked of whichever
     * authority is actually in force.
     *
     * @return list<string>
     */
    public static function requiredInProduction(): array
    {
        return [];
    }

    /**
     * Recognised, deliberately not required yet, and owned by a later unit.
     *
     * @return array<string, string>
     */
    public static function declared(): array
    {
        return [];
    }
}
