<?php

declare(strict_types=1);

namespace App\Modules\Platform\Security;

use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * The D-12 boundary: structured, redacted security events through the existing
 * logging boundary. No audit table - P1-08 owns durable storage and adopts
 * these events later.
 *
 * The context shape is fixed rather than a free array. That is the whole point:
 * a caller cannot pass a token, code, nonce or grant by accident, because there
 * is nowhere for it to go. A forbidden key is a hard failure, not a warning,
 * because a security logger that quietly drops a leak is worse than none.
 */
final class SecurityEventLogger
{
    public const BOOTSTRAP_GRANT_ISSUED = 'bootstrap.grant.issued';

    public const BOOTSTRAP_COMPLETED = 'bootstrap.completed';

    public const BOOTSTRAP_REFUSED = 'bootstrap.refused';

    public const LOGIN_SUCCEEDED = 'auth.login.succeeded';

    public const LOGIN_REFUSED_UNKNOWN = 'auth.login.refused.unknown_identity';

    public const LOGIN_REFUSED_INACTIVE = 'auth.login.refused.inactive';

    public const LOGIN_REFUSED_TENANT = 'auth.login.refused.tenant';

    public const LOGIN_REFUSED_PROTOCOL = 'auth.login.refused.protocol';

    public const LOGOUT = 'auth.logout';

    public const SESSION_EXPIRED = 'auth.session.expired';

    /*
     * P1-01 scope-affecting events.
     *
     * Structural identifiers only - no personal data beyond a user reference and
     * no free text. The *.moved events carry the most weight: a move is the
     * change most likely to alter someone's future scope.
     */
    public const ORGANISATION_CREATED = 'organisation.created';

    public const ORGANISATION_UPDATED = 'organisation.updated';

    public const LEGAL_ENTITY_CREATED = 'legal_entity.created';

    public const LEGAL_ENTITY_UPDATED = 'legal_entity.updated';

    public const LEGAL_ENTITY_DEACTIVATED = 'legal_entity.deactivated';

    public const BUSINESS_UNIT_CREATED = 'business_unit.created';

    public const BUSINESS_UNIT_UPDATED = 'business_unit.updated';

    public const BUSINESS_UNIT_DEACTIVATED = 'business_unit.deactivated';

    public const DEPARTMENT_CREATED = 'department.created';

    public const DEPARTMENT_UPDATED = 'department.updated';

    public const DEPARTMENT_DEACTIVATED = 'department.deactivated';

    public const DEPARTMENT_MOVED = 'department.moved';

    public const TEAM_CREATED = 'team.created';

    public const TEAM_UPDATED = 'team.updated';

    public const TEAM_DEACTIVATED = 'team.deactivated';

    public const TEAM_MOVED = 'team.moved';

    public const TEAM_MEMBER_ADDED = 'team.member.added';

    public const TEAM_MEMBER_REMOVED = 'team.member.removed';

    public const MANAGEMENT_RELATIONSHIP_SET = 'management.relationship.set';

    public const MANAGEMENT_RELATIONSHIP_CLEARED = 'management.relationship.cleared';

    public const BUSINESS_UNIT_LEGAL_ENTITY_ASSOCIATED = 'business_unit.legal_entity.associated';

    /*
     * D-24 guarded permanent deletion.
     *
     * A purge is the only operation in P1-01 that destroys a record, so it is
     * the one that most needs a durable trace. The event carries the entity
     * type and its identifier and nothing else: the name is gone from the
     * database by the time anyone reads the log, and putting it in the event to
     * compensate would make the log the place business content leaks.
     */
    public const LEGAL_ENTITY_PURGED = 'legal_entity.purged';

    public const BUSINESS_UNIT_PURGED = 'business_unit.purged';

    public const DEPARTMENT_PURGED = 'department.purged';

    public const TEAM_PURGED = 'team.purged';

    public const BUSINESS_UNIT_LEGAL_ENTITY_DISSOCIATED = 'business_unit.legal_entity.dissociated';

    private const EVENTS = [
        self::BOOTSTRAP_GRANT_ISSUED,
        self::BOOTSTRAP_COMPLETED,
        self::BOOTSTRAP_REFUSED,
        self::LOGIN_SUCCEEDED,
        self::LOGIN_REFUSED_UNKNOWN,
        self::LOGIN_REFUSED_INACTIVE,
        self::LOGIN_REFUSED_TENANT,
        self::LOGIN_REFUSED_PROTOCOL,
        self::LOGOUT,
        self::SESSION_EXPIRED,
        self::ORGANISATION_CREATED,
        self::ORGANISATION_UPDATED,
        self::LEGAL_ENTITY_CREATED,
        self::LEGAL_ENTITY_UPDATED,
        self::LEGAL_ENTITY_DEACTIVATED,
        self::BUSINESS_UNIT_CREATED,
        self::BUSINESS_UNIT_UPDATED,
        self::BUSINESS_UNIT_DEACTIVATED,
        self::DEPARTMENT_CREATED,
        self::DEPARTMENT_UPDATED,
        self::DEPARTMENT_DEACTIVATED,
        self::DEPARTMENT_MOVED,
        self::TEAM_CREATED,
        self::TEAM_UPDATED,
        self::TEAM_DEACTIVATED,
        self::TEAM_MOVED,
        self::TEAM_MEMBER_ADDED,
        self::TEAM_MEMBER_REMOVED,
        self::MANAGEMENT_RELATIONSHIP_SET,
        self::MANAGEMENT_RELATIONSHIP_CLEARED,
        self::BUSINESS_UNIT_LEGAL_ENTITY_ASSOCIATED,
        self::BUSINESS_UNIT_LEGAL_ENTITY_DISSOCIATED,
        self::LEGAL_ENTITY_PURGED,
        self::BUSINESS_UNIT_PURGED,
        self::DEPARTMENT_PURGED,
        self::TEAM_PURGED,
    ];

    /**
     * Only these keys may ever appear in an event's context.
     *
     * P1-01 adds structural identifiers only. There is deliberately no key for a
     * name, a description or any free text: a name is business content, and a
     * free-text key is where a leak eventually goes.
     */
    private const ALLOWED_KEYS = [
        'provider', 'subject', 'tenant', 'user_id', 'result', 'reason', 'expires_at',
        'organisation_id', 'entity_type', 'entity_id', 'related_id',
    ];

    /**
     * @param  array<string, scalar|null>  $context
     */
    public function record(string $event, array $context = []): void
    {
        if (! in_array($event, self::EVENTS, true)) {
            throw new InvalidArgumentException("Unknown security event [{$event}].");
        }

        foreach (array_keys($context) as $key) {
            if (! in_array($key, self::ALLOWED_KEYS, true)) {
                throw new InvalidArgumentException(
                    "Security event context key [{$key}] is not permitted. Tokens, codes, "
                    .'nonces, PKCE verifiers and bootstrap grants must never be logged.'
                );
            }
        }

        Log::info($event, $context + ['at' => now()->toIso8601String()]);
    }

    /**
     * @return list<string>
     */
    public static function events(): array
    {
        return self::EVENTS;
    }
}
