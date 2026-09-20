<?php

declare(strict_types=1);

namespace App\Modules\Platform\Security;

use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * NOT final, so tests may substitute a recording subclass that still runs this
 * validation - see Tests\Support\RecordingSecurityEventLogger and the note
 * there about why Log::spy() could not be trusted for this.
 *
 * The D-12 boundary: structured, redacted security events through the existing
 * logging boundary. No audit table - P1-08 owns durable storage and adopts
 * these events later.
 *
 * The context shape is fixed rather than a free array. That is the whole point:
 * a caller cannot pass a token, code, nonce or grant by accident, because there
 * is nowhere for it to go. A forbidden key is a hard failure, not a warning,
 * because a security logger that quietly drops a leak is worse than none.
 */
class SecurityEventLogger
{
    /**
     * P1-08 persists through this seam. It is a REQUIRED dependency, not a
     * nullable one: an optional recorder is a recorder somebody forgets to
     * wire, and the failure mode is an audit trail that is quietly empty.
     */
    public function __construct(private readonly EvidenceRecorder $evidence) {}

    public const BOOTSTRAP_GRANT_ISSUED = 'bootstrap.grant.issued';

    public const BOOTSTRAP_COMPLETED = 'bootstrap.completed';

    public const BOOTSTRAP_REFUSED = 'bootstrap.refused';

    /*
     * P1-10. The LOCAL Bootstrap Administrator, which is a different thing from
     * the SSH grant channel above and deliberately reads that way in the trail.
     *
     * BOOTSTRAP_SIGNIN_SUCCEEDED CARRIES THE SAME SEMANTIC CLASS AS ORDINARY
     * SUCCESSFUL LOGIN - StateChangeRecordedFirst, declared in EventCatalogue.
     * The first draft made it BestEffort, which in this codebase means exactly
     * one thing: if the write fails, carry on. Carrying on here means issuing a
     * session for the most privileged local credential in the deployment with
     * no record that it was ever used, so anyone able to make audit writes fail
     * would get a silent sign-in. A successful login is a state change, not a
     * courtesy note.
     *
     * THE REFUSAL STAYS A REFUSAL AND THE SIGN-OUT STAYS BEST-EFFORT. Failing
     * a refusal that cannot be recorded would let a broken audit store lock
     * somebody out while granting nothing; failing a sign-out would keep a
     * privileged session alive. Both are the wrong failure direction.
     */
    public const BOOTSTRAP_SIGNIN_SUCCEEDED = 'bootstrap.signin.succeeded';

    public const BOOTSTRAP_SIGNIN_REFUSED = 'bootstrap.signin.refused';

    public const BOOTSTRAP_SIGNOUT = 'bootstrap.signout';

    /*
     * D-165. The setup session ran out of one of its two clocks.
     *
     * ITS OWN EVENT rather than bootstrap.signout, because they are different
     * facts: a sign-out is somebody leaving, an expiry is the deployment
     * ending a privileged session on its own. Reading a trail where both said
     * "signed out" would make an abandoned session indistinguishable from a
     * closed one, and the abandoned one is the one worth noticing.
     *
     * `reason` carries which clock - an existing ALLOWED_KEY with a fixed
     * vocabulary. NO KEY IS ADDED.
     */
    public const BOOTSTRAP_SESSION_EXPIRED = 'bootstrap.session.expired';

    public const BOOTSTRAP_ADMINISTRATOR_CREATED = 'bootstrap.administrator.created';

    /*
     * The durable evidence that bootstrap was closed - the write the first
     * draft of the design lacked entirely, which is what left the original
     * local password usable again the moment every System Administrator was
     * deactivated. It is a StateChange, so it commits with the closure or
     * neither happens.
     */
    public const BOOTSTRAP_CLOSED = 'bootstrap.closed';

    public const BOOTSTRAP_RECOVERY_ISSUED = 'bootstrap.recovery.issued';

    public const BOOTSTRAP_RECOVERY_CONSUMED = 'bootstrap.recovery.consumed';

    public const INTEGRATION_CONFIGURATION_CHANGED = 'integration.configuration.changed';

    public const INTEGRATION_CONNECTION_TESTED = 'integration.connection.tested';

    public const IDENTITY_CONFIGURATION_CUTOVER = 'identity.configuration.cutover';

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

    /*
     * P1-02 identity observability.
     *
     * Two events, and only two. A visit to a read-only screen is NOT logged
     * merely because the screen is sensitive: large volumes of low-value
     * security events bury the ones that matter, and P1-08 would inherit the
     * noise.
     *
     * The name is state_changed rather than degraded, which is what it was
     * first called. An event named "degraded" that fires on a transition to
     * failed is a false statement in the audit trail, and the prose explaining
     * that in a design document does not travel with the log line.
     */
    public const IDENTITY_HEALTH_CHECKED = 'identity.health.checked';

    public const IDENTITY_HEALTH_STATE_CHANGED = 'identity.health.state_changed';

    /*
     * P1-03 people and groups.
     *
     * Structural identifiers only. There is deliberately no event carrying an
     * email, a display name, a group name or an Entra Object ID - the logger has
     * no key for free text and none is added for this unit, which is what makes
     * a leak here unrepresentable rather than merely discouraged.
     */
    public const USER_PROVISIONED = 'user.provisioned';

    public const USER_PROVISION_REFUSED = 'user.provision.refused';

    public const USER_ACTIVATED = 'user.activated';

    public const USER_DEACTIVATED = 'user.deactivated';

    public const USER_ORGANISATION_ASSIGNED = 'user.organisation.assigned';

    public const USER_PURGED = 'user.purged';

    public const GROUP_CREATED = 'group.created';

    public const GROUP_UPDATED = 'group.updated';

    public const GROUP_DEACTIVATED = 'group.deactivated';

    public const GROUP_ACTIVATED = 'group.activated';

    public const GROUP_PURGED = 'group.purged';

    public const GROUP_MEMBER_ADDED = 'group.member.added';

    public const GROUP_MEMBER_REMOVED = 'group.member.removed';

    /*
     * P1-04 business domains.
     *
     * SEVEN EVENTS AND NO NEW CONTEXT KEY. Every value one of these carries
     * already has somewhere to go - entity_type, entity_id, organisation_id,
     * user_id, related_id, result - and that is the D-12 boundary working as
     * designed rather than a coincidence: a domain's NAME, CODE and DESCRIPTION
     * are business content, there is no key for free text, and so a leak here
     * is UNREPRESENTABLE rather than merely discouraged. A design that needed a
     * new key would be a design putting business content in the log.
     *
     * REFUSALS ARE DELIBERATELY NOT LOGGED. P1-02's note above says a screen is
     * not logged merely because it is sensitive, because volume buries what
     * matters and P1-08 inherits the noise. user.provision.refused exists
     * because a failed provision can indicate enumeration; being told "assign
     * an owner before enabling this" cannot indicate anything.
     *
     * None of these events records a grant, because none of these operations is
     * one. An owner is accountable, not entitled.
     */
    public const BUSINESS_DOMAIN_CREATED = 'business_domain.created';

    public const BUSINESS_DOMAIN_UPDATED = 'business_domain.updated';

    public const BUSINESS_DOMAIN_ENABLED = 'business_domain.enabled';

    public const BUSINESS_DOMAIN_DISABLED = 'business_domain.disabled';

    public const BUSINESS_DOMAIN_PURGED = 'business_domain.purged';

    public const BUSINESS_DOMAIN_OWNER_ASSIGNED = 'business_domain.owner.assigned';

    public const BUSINESS_DOMAIN_OWNER_CLEARED = 'business_domain.owner.cleared';

    /*
     * P1-05 roles and access.
     *
     * D-71: PRIVILEGED SURFACES ONLY. There is deliberately no event for an
     * ordinary business denial and no repeated-denial detector - volume buries
     * what matters, and P1-08 inherits the noise. What is logged is a change to
     * somebody's authority, a self-grant, a step-up refusal, and a state the
     * engine could not interpret.
     *
     * D-72 adds FOUR context keys and no free-text channel: `role` is a fixed
     * CODE from the catalogue and never a name, `domain_id` and `scope` are
     * structural, `sensitivity` is an enum value. A role's LABEL, a domain's
     * NAME and an administrator's reason are business content, there is nowhere
     * for them to go, and so a leak here stays unrepresentable rather than
     * merely discouraged.
     */
    public const ROLE_ASSIGNED = 'access.role.assigned';

    public const ROLE_REVOKED = 'access.role.revoked';

    public const ROLE_SELF_ASSIGNED = 'access.role.self_assigned';

    public const ENTITLEMENT_GRANTED = 'access.entitlement.granted';

    public const ENTITLEMENT_REVOKED = 'access.entitlement.revoked';

    public const SCOPE_ASSIGNED = 'access.scope.assigned';

    public const SCOPE_REVOKED = 'access.scope.revoked';

    public const CEILING_SET = 'access.ceiling.set';

    public const STEP_UP_REQUESTED = 'access.step_up.requested';

    public const STEP_UP_COMPLETED = 'access.step_up.completed';

    public const STEP_UP_REFUSED = 'access.step_up.refused';

    /*
     * P1-07 Access Reviews. D-94.
     *
     * NO ALLOWED_KEYS EXPANSION. Every context these carry is already
     * permitted: user_id is the SUBJECT, related_id the ACTOR - the convention
     * EntitlementService already uses - plus entity_id, role, domain_id,
     * sensitivity, reason and result.
     *
     * SELF-REVIEW EMITS ITS OWN KEY rather than the ordinary retained/revoked
     * one with the same actor and subject. Distinguishable is the whole point:
     * D-88 permits self-review only when nobody else could do it, and evidence
     * that needs a join to spot it is evidence nobody spots.
     *
     * reason CARRIES A FIXED VOCABULARY, never a message. A reviewer's words
     * never reach a security event - D-12, and the leak channel P1-06 refused.
     */
    public const REVIEW_CYCLE_STARTED = 'access.review.cycle.started';

    public const REVIEW_ITEM_RETAINED = 'access.review.item.retained';

    public const REVIEW_ITEM_REVOKED = 'access.review.item.revoked';

    public const REVIEW_ITEM_SUPERSEDED = 'access.review.item.superseded';

    public const REVIEW_ITEM_SELF_REVIEWED = 'access.review.item.self_reviewed';

    public const REVIEW_REFUSED = 'access.review.refused';

    public const ACCESS_STATE_UNRECOGNISED = 'access.state.unrecognised';

    public const ACCESS_ENGINE_FAILED = 'access.engine.failed';

    private const EVENTS = [
        self::BOOTSTRAP_GRANT_ISSUED,
        self::BOOTSTRAP_COMPLETED,
        self::BOOTSTRAP_REFUSED,
        self::BOOTSTRAP_SIGNIN_SUCCEEDED,
        self::BOOTSTRAP_SIGNIN_REFUSED,
        self::BOOTSTRAP_SIGNOUT,
        self::BOOTSTRAP_SESSION_EXPIRED,
        self::BOOTSTRAP_ADMINISTRATOR_CREATED,
        self::BOOTSTRAP_CLOSED,
        self::BOOTSTRAP_RECOVERY_ISSUED,
        self::BOOTSTRAP_RECOVERY_CONSUMED,
        self::INTEGRATION_CONFIGURATION_CHANGED,
        self::INTEGRATION_CONNECTION_TESTED,
        self::IDENTITY_CONFIGURATION_CUTOVER,
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
        self::IDENTITY_HEALTH_CHECKED,
        self::IDENTITY_HEALTH_STATE_CHANGED,
        self::USER_PROVISIONED,
        self::USER_PROVISION_REFUSED,
        self::USER_ACTIVATED,
        self::USER_DEACTIVATED,
        self::USER_ORGANISATION_ASSIGNED,
        self::USER_PURGED,
        self::GROUP_CREATED,
        self::GROUP_UPDATED,
        self::GROUP_DEACTIVATED,
        self::GROUP_ACTIVATED,
        self::GROUP_PURGED,
        self::GROUP_MEMBER_ADDED,
        self::GROUP_MEMBER_REMOVED,
        self::BUSINESS_DOMAIN_CREATED,
        self::BUSINESS_DOMAIN_UPDATED,
        self::BUSINESS_DOMAIN_ENABLED,
        self::BUSINESS_DOMAIN_DISABLED,
        self::BUSINESS_DOMAIN_PURGED,
        self::BUSINESS_DOMAIN_OWNER_ASSIGNED,
        self::BUSINESS_DOMAIN_OWNER_CLEARED,
        self::ROLE_ASSIGNED,
        self::ROLE_REVOKED,
        self::ROLE_SELF_ASSIGNED,
        self::ENTITLEMENT_GRANTED,
        self::ENTITLEMENT_REVOKED,
        self::SCOPE_ASSIGNED,
        self::SCOPE_REVOKED,
        self::CEILING_SET,
        self::STEP_UP_REQUESTED,
        self::STEP_UP_COMPLETED,
        self::STEP_UP_REFUSED,
        self::REVIEW_CYCLE_STARTED,
        self::REVIEW_ITEM_RETAINED,
        self::REVIEW_ITEM_REVOKED,
        self::REVIEW_ITEM_SUPERSEDED,
        self::REVIEW_ITEM_SELF_REVIEWED,
        self::REVIEW_REFUSED,
        self::ACCESS_STATE_UNRECOGNISED,
        self::ACCESS_ENGINE_FAILED,
    ];

    /**
     * Only these keys may ever appear in an event's context.
     *
     * P1-01 adds structural identifiers only. There is deliberately no key for a
     * name, a description or any free text: a name is business content, and a
     * free-text key is where a leak eventually goes.
     *
     * P1-05 adds four - D-72, approved individually. `role` carries a CODE from
     * RoleCatalogue and never a label, so "Super Admin" has nowhere to appear
     * even if somebody typed it. `domain_id` and `scope` are structural.
     * `sensitivity` is an enum value. The absence of a `note` or `justification`
     * key is deliberate and is what keeps this boundary honest.
     */
    private const ALLOWED_KEYS = [
        'provider', 'subject', 'tenant', 'user_id', 'result', 'reason', 'expires_at',
        'organisation_id', 'entity_type', 'entity_id', 'related_id',
        'role', 'domain_id', 'scope', 'sensitivity',
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

        /*
         * DURABLE EVIDENCE FIRST, THEN THE LOG.
         *
         * D-111: a state change that cannot be evidenced must not complete, so
         * this throws before anything downstream treats the operation as done.
         * The recorder decides per event whether a failure is fatal - a refusal
         * and a sign-out are not - and OutcomeClass is where that is declared.
         */
        $this->evidence->record($event, $context);

        // Operator diagnostics. NOT evidence, and nothing reads it back.
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
