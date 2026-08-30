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
     * Recognised, deliberately not required yet, and owned by a later unit.
     *
     * @return array<string, string>
     */
    public static function declared(): array
    {
        return [
            'semantiq.identity.microsoft.tenant_id' => 'P1-00',
            'semantiq.identity.microsoft.client_id' => 'P1-00',
            'semantiq.identity.microsoft.client_secret' => 'P1-00',
            'semantiq.identity.microsoft.redirect_uri' => 'P1-00',
        ];
    }
}
