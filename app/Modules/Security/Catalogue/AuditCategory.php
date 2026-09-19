<?php

declare(strict_types=1);

namespace App\Modules\Security\Catalogue;

/**
 * The four approved Audit categories. D-98.
 *
 * EXACTLY ONE PER OCCURRENCE. A sign-in refusal is Security Events and a
 * successful sign-in is User Access; neither appears twice. A count that
 * depends on which tab you are standing on is not evidence.
 *
 * DECLARED BESIDE THE EVENT VOCABULARY rather than inside the Audit module,
 * because the catalogue is where an event's meaning is already recorded and a
 * second place to record it is a second place to drift. P1-08 consumes this;
 * P1-06 does not consume P1-08.
 */
enum AuditCategory: string
{
    case UserAccess = 'user_access';

    case AdminChanges = 'admin_changes';

    case SecurityEvents = 'security_events';

    case ConfigurationChanges = 'configuration_changes';

    public function label(): string
    {
        return match ($this) {
            self::UserAccess => 'User Access',
            self::AdminChanges => 'Admin Changes',
            self::SecurityEvents => 'Security Events',
            self::ConfigurationChanges => 'Configuration Changes',
        };
    }

    /** The URL segment. Privileged Reviews' precedent: the first tab is the index. */
    public function slug(): string
    {
        return match ($this) {
            self::UserAccess => '',
            self::AdminChanges => 'admin-changes',
            self::SecurityEvents => 'security-events',
            self::ConfigurationChanges => 'configuration',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::UserAccess => 'Who signed in, and every change to what somebody may reach.',
            self::AdminChanges => 'Changes to the organisation structure and to business domains.',
            self::SecurityEvents => 'Refused sign-ins, refused privileged actions, and conditions the platform raised.',
            self::ConfigurationChanges => 'Changes to how sign-in itself is configured.',
        };
    }
}
