<?php

declare(strict_types=1);

namespace App\Modules\Security\Catalogue;

use App\Modules\Platform\Security\SecurityEventLogger;
use InvalidArgumentException;

/**
 * Every declared security event, in business language.
 *
 * THE CATALOGUE IS SOURCE TRUTH; IT IS NOT DISPLAY TEXT. The KEYS come from
 * SecurityEventLogger::events() AT RUNTIME, so coverage cannot drift from
 * reality. The MAPPING below is this class's own data, and N-SS33 asserts
 * completeness IN BOTH DIRECTIONS: a new event added by a future unit fails the
 * build until somebody writes its label, and a removed event fails too.
 *
 * A screen that printed `access.step_up.refused` at an administrator would be
 * exactly the CLAUDE.md §4 failure - an internal key on a user-facing surface.
 * No rendered prop on these screens may match a dotted identifier, and N-SS33
 * scans for it.
 *
 * TEN CATEGORIES, NOT THE FIVE THE PLAN SKETCHED. PLAN §7.2 proposed Sign-in,
 * Administration changes, Access changes, Privileged confirmations and
 * Permanent deletions, and gave DESIGN the mapping. Five would have forced 39
 * of the 71 into a single "Administration changes" bucket, which is a heading a
 * reader scrolls past rather than uses. All five proposed names survive; four
 * unchanged. "Administration changes" is split into the four things an
 * administrator actually distinguishes, and "Access system conditions" is added
 * for the two events that are CONDITIONS RATHER THAN CHANGES.
 *
 * THIS CLASS ADDS NOTHING TO THE VOCABULARY. P1-06 creates no table, records no
 * event and adds no key to ALLOWED_KEYS. If a control genuinely needed a new
 * event, that is a change to the P1-08-bound vocabulary and a Product Owner
 * decision, not a reporting screen's convenience. N-SS30 and N-SS31 guard it.
 */
final class EventCatalogue
{
    /**
     * The fifteen permitted context keys, read from the logger rather than
     * copied. A copy would go stale, and this panel's whole claim is that a
     * token, code, nonce or grant CANNOT be recorded because there is nowhere
     * for it to go.
     */
    public const REDACTION_PROMISE =
        'A security event may only carry the fields listed here. There is no field for free text, '
        .'so a password, a token, a one-time code or a sign-in secret cannot be recorded even by '
        .'mistake — there is nowhere in the record for one to go.';

    /**
     * P1-08 REWROTE THIS, IT DID NOT REMOVE IT. D-106.
     *
     * This screen is still the CATALOGUE - what is recorded and how it is
     * protected - and it still reads no log file and no audit table. What has
     * changed is that "there is no history" stopped being true, so the sentence
     * that said so would now be a lie by omission. It points at Audit instead.
     */
    public const LIMITATION =
        'This is what is being recorded, not a record of what happened. Open Audit to see the '
        .'events themselves — note that Audit begins on the day it was installed, so activity '
        .'before then was never stored anywhere it can be searched.';

    /**
     * key => [category, label]. Every declared event appears exactly once.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const MAPPING = [
        // ---- First-run setup — 3 ----------------------------------------
        'bootstrap.grant.issued' => ['First-run setup', 'First-run setup grant issued'],
        'bootstrap.completed' => ['First-run setup', 'First-run setup completed'],
        'bootstrap.refused' => ['First-run setup', 'First-run setup refused'],

        // P1-10. THE LOCAL BOOTSTRAP ADMINISTRATOR, in the words a reader
        // needs. "Setup administrator" rather than "bootstrap principal":
        // the second is what the code calls it, and nobody reading a security
        // trail should have to know that.
        'bootstrap.signin.succeeded' => ['First-run setup', 'Setup administrator signed in'],
        'bootstrap.signin.refused' => ['First-run setup', 'Setup administrator sign-in refused'],
        'bootstrap.signout' => ['First-run setup', 'Setup administrator signed out'],
        'bootstrap.session.expired' => ['First-run setup', 'Setup session timed out'],
        'bootstrap.administrator.created' => ['First-run setup', 'Setup administrator created'],
        'bootstrap.closed' => ['First-run setup', 'First-run setup closed'],
        'bootstrap.recovery.issued' => ['First-run setup', 'Setup recovery token issued'],
        'bootstrap.recovery.consumed' => ['First-run setup', 'Setup recovery token used'],

        // P1-10. Platform integrations.
        'integration.configuration.changed' => ['Sign-in configuration', 'Integration settings changed'],
        'integration.connection.tested' => ['Sign-in configuration', 'Integration connection tested'],
        'identity.configuration.cutover' => ['Sign-in configuration', 'Sign-in configuration moved into SemantIQ'],
        // ---- Sign-in — 7 ------------------------------------------------
        'auth.login.succeeded' => ['Sign-in', 'Signed in'],
        'auth.login.refused.unknown_identity' => ['Sign-in', 'Sign-in refused — account not recognised'],
        'auth.login.refused.inactive' => ['Sign-in', 'Sign-in refused — account not active'],
        'auth.login.refused.tenant' => ['Sign-in', 'Sign-in refused — outside the approved directory'],
        'auth.login.refused.protocol' => ['Sign-in', 'Sign-in refused — sign-in could not be completed'],
        'auth.logout' => ['Sign-in', 'Signed out'],
        'auth.session.expired' => ['Sign-in', 'Session expired'],
        // ---- Sign-in configuration — 2 ----------------------------------
        'identity.health.checked' => ['Sign-in configuration', 'Sign-in health checked'],
        'identity.health.state_changed' => ['Sign-in configuration', 'Sign-in health changed'],
        // ---- Organisation structure changes — 22 ------------------------
        'organisation.created' => ['Organisation structure changes', 'Organisation created'],
        'organisation.updated' => ['Organisation structure changes', 'Organisation updated'],
        'legal_entity.created' => ['Organisation structure changes', 'Legal entity created'],
        'legal_entity.updated' => ['Organisation structure changes', 'Legal entity updated'],
        'legal_entity.deactivated' => ['Organisation structure changes', 'Legal entity deactivated'],
        'business_unit.created' => ['Organisation structure changes', 'Business unit created'],
        'business_unit.updated' => ['Organisation structure changes', 'Business unit updated'],
        'business_unit.deactivated' => ['Organisation structure changes', 'Business unit deactivated'],
        'department.created' => ['Organisation structure changes', 'Department created'],
        'department.updated' => ['Organisation structure changes', 'Department updated'],
        'department.deactivated' => ['Organisation structure changes', 'Department deactivated'],
        'department.moved' => ['Organisation structure changes', 'Department moved'],
        'team.created' => ['Organisation structure changes', 'Team created'],
        'team.updated' => ['Organisation structure changes', 'Team updated'],
        'team.deactivated' => ['Organisation structure changes', 'Team deactivated'],
        'team.moved' => ['Organisation structure changes', 'Team moved'],
        'team.member.added' => ['Organisation structure changes', 'Person added to a team'],
        'team.member.removed' => ['Organisation structure changes', 'Person removed from a team'],
        'management.relationship.set' => ['Organisation structure changes', 'Manager set'],
        'management.relationship.cleared' => ['Organisation structure changes', 'Manager cleared'],
        'business_unit.legal_entity.associated' => ['Organisation structure changes', 'Business unit linked to a legal entity'],
        'business_unit.legal_entity.dissociated' => ['Organisation structure changes', 'Business unit unlinked from a legal entity'],
        // ---- People and group changes — 11 ------------------------------
        'user.provisioned' => ['People and group changes', 'Person added'],
        'user.provision.refused' => ['People and group changes', 'Adding a person was refused'],
        'user.activated' => ['People and group changes', 'Person reactivated'],
        'user.deactivated' => ['People and group changes', 'Person deactivated'],
        'user.organisation.assigned' => ['People and group changes', 'Person assigned to the organisation'],
        'group.created' => ['People and group changes', 'Group created'],
        'group.updated' => ['People and group changes', 'Group updated'],
        'group.deactivated' => ['People and group changes', 'Group deactivated'],
        'group.activated' => ['People and group changes', 'Group reactivated'],
        'group.member.added' => ['People and group changes', 'Person added to a group'],
        'group.member.removed' => ['People and group changes', 'Person removed from a group'],
        // ---- Business domain changes — 6 --------------------------------
        'business_domain.created' => ['Business domain changes', 'Business domain created'],
        'business_domain.updated' => ['Business domain changes', 'Business domain updated'],
        'business_domain.enabled' => ['Business domain changes', 'Business domain enabled'],
        'business_domain.disabled' => ['Business domain changes', 'Business domain disabled'],
        'business_domain.owner.assigned' => ['Business domain changes', 'Business domain owner assigned'],
        'business_domain.owner.cleared' => ['Business domain changes', 'Business domain owner cleared'],
        // ---- Access changes — 8 -----------------------------------------
        'access.role.assigned' => ['Access changes', 'Role assigned'],
        'access.role.revoked' => ['Access changes', 'Role removed'],
        'access.role.self_assigned' => ['Access changes', 'Role assigned to oneself'],
        'access.entitlement.granted' => ['Access changes', 'Domain entitlement granted'],
        'access.entitlement.revoked' => ['Access changes', 'Domain entitlement removed'],
        'access.scope.assigned' => ['Access changes', 'Scope assigned'],
        'access.scope.revoked' => ['Access changes', 'Scope removed'],
        'access.ceiling.set' => ['Access changes', 'Sensitivity limit set'],
        // ---- Privileged confirmations — 3 -------------------------------
        'access.step_up.requested' => ['Privileged confirmations', 'Identity re-confirmation requested'],
        'access.step_up.completed' => ['Privileged confirmations', 'Identity re-confirmed'],
        'access.step_up.refused' => ['Privileged confirmations', 'Identity re-confirmation refused'],
        // ---- Permanent deletions — 7 ------------------------------------
        'legal_entity.purged' => ['Permanent deletions', 'Legal entity permanently deleted'],
        'business_unit.purged' => ['Permanent deletions', 'Business unit permanently deleted'],
        'department.purged' => ['Permanent deletions', 'Department permanently deleted'],
        'team.purged' => ['Permanent deletions', 'Team permanently deleted'],
        'user.purged' => ['Permanent deletions', 'Person permanently deleted'],
        'group.purged' => ['Permanent deletions', 'Group permanently deleted'],
        'business_domain.purged' => ['Permanent deletions', 'Business domain permanently deleted'],
        // ---- Access reviews — 6 -----------------------------------------
        // P1-07. The business words a reader needs, not the identifiers. A
        // self-review is named as one because it is the case that matters.
        'access.review.cycle.started' => ['Access reviews', 'Review cycle started'],
        'access.review.item.retained' => ['Access reviews', 'Access confirmed at review'],
        'access.review.item.revoked' => ['Access reviews', 'Access removed at review'],
        'access.review.item.superseded' => ['Access reviews', 'Review overtaken by a change to the access'],
        'access.review.item.self_reviewed' => ['Access reviews', 'Access reviewed by the person who holds it'],
        'access.review.refused' => ['Access reviews', 'Review decision refused'],
        // ---- Access system conditions — 2 -------------------------------
        'access.state.unrecognised' => ['Access system conditions', 'Access state could not be interpreted'],
        'access.engine.failed' => ['Access system conditions', 'Access decision could not be completed'],
    ];

    /**
     * PER-EVENT AUDIT SEMANTICS. The second facet of the SAME catalogue, not
     * a second event list: the keys are the canonical ones and
     * EventSemanticsCompletenessTest asserts this map and
     * SecurityEventLogger::events() are the SAME SET, as an equality.
     *
     * READ EventSemantics FIRST. There is deliberately no default and no
     * global convention, because the repository does not have one: user
     * lifecycle puts the ADMINISTRATOR in user_id and role administration puts
     * the SUBJECT there. They are inverses, so any single rule names the wrong
     * person half the time - plausibly, and invisibly.
     *
     * @var array<string, array{0: AuditCategory, 1: ActorSource, 2: SubjectSource, 3: TargetSource, 4: OrganisationSource, 5: OutcomeClass, 6?: string}>
     */
    private const SEMANTICS = [
        // ---- First-run setup. Platform-scoped: there is no organisation yet.
        'bootstrap.grant.issued' => [AuditCategory::UserAccess, ActorSource::System, SubjectSource::ExternalSubject, TargetSource::None, OrganisationSource::None, OutcomeClass::StateChange],
        'bootstrap.completed' => [AuditCategory::UserAccess, ActorSource::UserId, SubjectSource::UserId, TargetSource::None, OrganisationSource::None, OutcomeClass::StateChange],
        'bootstrap.refused' => [AuditCategory::SecurityEvents, ActorSource::System, SubjectSource::None, TargetSource::None, OrganisationSource::None, OutcomeClass::Refusal],

        // ---- P1-10. The LOCAL Bootstrap Administrator: the pre-SSO principal
        //      that exists only while no System Administrator does.
        //
        // NO user_id ANYWHERE IN THIS BLOCK, and that is the boundary rather
        // than an omission. The bootstrap principal is not a User, has no row
        // in users, and never enters RoleCatalogue - so ActorSource::System is
        // the honest answer. Putting an id here would require inventing one,
        // and an invented id in an audit trail is worse than none.
        //
        // SIGN-IN IS StateChangeRecordedFirst, THE SAME CLASS AS ORDINARY
        // LOGIN. The session is not in the database, so the invariant is kept
        // by ORDER: evidence commits, then the privileged session is issued.
        // If the evidence cannot be written, no session exists. The first
        // draft had this as BestEffort, which would have allowed a silent
        // sign-in to the most privileged local credential in the deployment.
        'bootstrap.signin.succeeded' => [AuditCategory::UserAccess, ActorSource::System, SubjectSource::None, TargetSource::None, OrganisationSource::None, OutcomeClass::StateChangeRecordedFirst],
        // A refusal that cannot be recorded must still refuse. Hardening this
        // into a failure would let a broken audit store lock out recovery
        // while granting nothing.
        'bootstrap.signin.refused' => [AuditCategory::SecurityEvents, ActorSource::System, SubjectSource::None, TargetSource::None, OrganisationSource::None, OutcomeClass::Refusal],
        // Failing a sign-out on an audit error keeps a privileged session
        // alive, which is the wrong direction to fail in.
        'bootstrap.signout' => [AuditCategory::UserAccess, ActorSource::System, SubjectSource::None, TargetSource::None, OrganisationSource::None, OutcomeClass::BestEffort],
        // BestEffort, like auth.session.expired above it and for the same
        // reason: failing to end an expired privileged session because its
        // note could not be filed would KEEP that session alive, which is the
        // wrong direction to fail in.
        'bootstrap.session.expired' => [AuditCategory::SecurityEvents, ActorSource::System, SubjectSource::None, TargetSource::None, OrganisationSource::None, OutcomeClass::BestEffort],
        'bootstrap.administrator.created' => [AuditCategory::AdminChanges, ActorSource::System, SubjectSource::None, TargetSource::None, OrganisationSource::None, OutcomeClass::StateChange],
        // THE EVIDENCE THAT BOOTSTRAP WAS CLOSED. StateChange, so it commits
        // inside the same transaction as the closure and the first permanent
        // System Administrator, or none of the three happen.
        'bootstrap.closed' => [AuditCategory::AdminChanges, ActorSource::System, SubjectSource::None, TargetSource::None, OrganisationSource::None, OutcomeClass::StateChange],
        // Issued over SSH by a trusted operator. This is the ONLY thing that
        // reopens local password login once bootstrap has closed, so it is
        // evidence rather than a note.
        'bootstrap.recovery.issued' => [AuditCategory::AdminChanges, ActorSource::System, SubjectSource::None, TargetSource::None, OrganisationSource::None, OutcomeClass::StateChange],
        'bootstrap.recovery.consumed' => [AuditCategory::AdminChanges, ActorSource::System, SubjectSource::None, TargetSource::None, OrganisationSource::None, OutcomeClass::StateChange],

        // ---- P1-10. Platform integrations.
        //
        // The actor is the administrator when there is one and the system when
        // the change came from First-Run, where there is no User yet. provider
        // carries the family name - an existing ALLOWED_KEY, so no key is
        // added and a host, endpoint or credential has nowhere to go.
        'integration.configuration.changed' => [AuditCategory::ConfigurationChanges, ActorSource::UserId, SubjectSource::None, TargetSource::None, OrganisationSource::None, OutcomeClass::StateChange],
        // A test changes nothing. Failing the test because its note could not
        // be written would turn a diagnostic into an outage.
        'integration.connection.tested' => [AuditCategory::ConfigurationChanges, ActorSource::UserId, SubjectSource::None, TargetSource::None, OrganisationSource::None, OutcomeClass::BestEffort],
        // The moment .env stops being the identity authority. There is no
        // larger configuration change this deployment can make.
        'identity.configuration.cutover' => [AuditCategory::ConfigurationChanges, ActorSource::System, SubjectSource::None, TargetSource::None, OrganisationSource::None, OutcomeClass::StateChange],

        // ---- Sign-in. user_id is the person signing in - the ACTOR, not a
        //      subject somebody else acted upon.
        // The session is not in the database, so the invariant is kept by
        // ORDER: evidence first, session second. StateChangeRecordedFirst.
        'auth.login.succeeded' => [AuditCategory::UserAccess, ActorSource::UserId, SubjectSource::UserId, TargetSource::None, OrganisationSource::Context, OutcomeClass::StateChangeRecordedFirst],
        // No SemantIQ account exists, which is the point of refusing. The
        // directory subject is recorded and NO lookup is attempted.
        'auth.login.refused.unknown_identity' => [AuditCategory::SecurityEvents, ActorSource::ExternalSubject, SubjectSource::ExternalSubject, TargetSource::None, OrganisationSource::None, OutcomeClass::Refusal],
        'auth.login.refused.inactive' => [AuditCategory::SecurityEvents, ActorSource::UserId, SubjectSource::UserId, TargetSource::None, OrganisationSource::Context, OutcomeClass::Refusal],
        'auth.login.refused.tenant' => [AuditCategory::SecurityEvents, ActorSource::ExternalSubject, SubjectSource::ExternalSubject, TargetSource::None, OrganisationSource::None, OutcomeClass::Refusal],
        'auth.login.refused.protocol' => [AuditCategory::SecurityEvents, ActorSource::ExternalSubject, SubjectSource::ExternalSubject, TargetSource::None, OrganisationSource::None, OutcomeClass::Refusal],
        'auth.logout' => [AuditCategory::UserAccess, ActorSource::UserId, SubjectSource::UserId, TargetSource::None, OrganisationSource::Context, OutcomeClass::BestEffort],
        // PLATFORM-SCOPED, and stated rather than glossed: the middleware
        // records this BEFORE it loads the user, so no organisation is
        // available, and reordering P1-00's session checks to make one
        // available is outside P1-08. System Administrator only.
        'auth.session.expired' => [AuditCategory::SecurityEvents, ActorSource::UserId, SubjectSource::UserId, TargetSource::None, OrganisationSource::None, OutcomeClass::BestEffort],

        // ---- Sign-in configuration. Platform, and the only Configuration
        //      Changes category in Release 1.
        'identity.health.checked' => [AuditCategory::ConfigurationChanges, ActorSource::UserId, SubjectSource::None, TargetSource::None, OrganisationSource::None, OutcomeClass::BestEffort],
        /*
         * BEST EFFORT, and the reclassification is deliberate.
         *
         * The "state" it records is a CACHE note about whether Microsoft is
         * reachable - IdentityHealthCheck writes it with Cache::put. Nothing in
         * the database changes, nobody's access changes, and there is no
         * transaction that could roll it back. Classing it StateChange demanded
         * an atomic boundary that cannot exist, which is a guard asking for the
         * impossible rather than a guard protecting anything.
         */
        'identity.health.state_changed' => [AuditCategory::ConfigurationChanges, ActorSource::System, SubjectSource::None, TargetSource::None, OrganisationSource::None, OutcomeClass::BestEffort],

        // ---- Organisation structure. user_id is the ADMINISTRATOR throughout.
        'organisation.created' => [AuditCategory::AdminChanges, ActorSource::UserId, SubjectSource::None, TargetSource::None, OrganisationSource::Context, OutcomeClass::StateChange],
        'organisation.updated' => [AuditCategory::AdminChanges, ActorSource::UserId, SubjectSource::None, TargetSource::None, OrganisationSource::Context, OutcomeClass::StateChange],
        'legal_entity.created' => [AuditCategory::AdminChanges, ActorSource::UserId, SubjectSource::None, TargetSource::EntityTypeAndId, OrganisationSource::Context, OutcomeClass::StateChange],
        'legal_entity.updated' => [AuditCategory::AdminChanges, ActorSource::UserId, SubjectSource::None, TargetSource::EntityTypeAndId, OrganisationSource::Context, OutcomeClass::StateChange],
        'legal_entity.deactivated' => [AuditCategory::AdminChanges, ActorSource::UserId, SubjectSource::None, TargetSource::EntityTypeAndId, OrganisationSource::Context, OutcomeClass::StateChange],
        'business_unit.created' => [AuditCategory::AdminChanges, ActorSource::UserId, SubjectSource::None, TargetSource::EntityTypeAndId, OrganisationSource::Context, OutcomeClass::StateChange],
        'business_unit.updated' => [AuditCategory::AdminChanges, ActorSource::UserId, SubjectSource::None, TargetSource::EntityTypeAndId, OrganisationSource::Context, OutcomeClass::StateChange],
        'business_unit.deactivated' => [AuditCategory::AdminChanges, ActorSource::UserId, SubjectSource::None, TargetSource::EntityTypeAndId, OrganisationSource::Context, OutcomeClass::StateChange],
        'department.created' => [AuditCategory::AdminChanges, ActorSource::UserId, SubjectSource::None, TargetSource::EntityTypeAndId, OrganisationSource::Context, OutcomeClass::StateChange],
        'department.updated' => [AuditCategory::AdminChanges, ActorSource::UserId, SubjectSource::None, TargetSource::EntityTypeAndId, OrganisationSource::Context, OutcomeClass::StateChange],
        'department.deactivated' => [AuditCategory::AdminChanges, ActorSource::UserId, SubjectSource::None, TargetSource::EntityTypeAndId, OrganisationSource::Context, OutcomeClass::StateChange],
        'department.moved' => [AuditCategory::AdminChanges, ActorSource::UserId, SubjectSource::None, TargetSource::EntityTypeAndId, OrganisationSource::Context, OutcomeClass::StateChange],
        'team.created' => [AuditCategory::AdminChanges, ActorSource::UserId, SubjectSource::None, TargetSource::EntityTypeAndId, OrganisationSource::Context, OutcomeClass::StateChange],
        'team.updated' => [AuditCategory::AdminChanges, ActorSource::UserId, SubjectSource::None, TargetSource::EntityTypeAndId, OrganisationSource::Context, OutcomeClass::StateChange],
        'team.deactivated' => [AuditCategory::AdminChanges, ActorSource::UserId, SubjectSource::None, TargetSource::EntityTypeAndId, OrganisationSource::Context, OutcomeClass::StateChange],
        'team.moved' => [AuditCategory::AdminChanges, ActorSource::UserId, SubjectSource::None, TargetSource::EntityTypeAndId, OrganisationSource::Context, OutcomeClass::StateChange],
        // The member is related_id; the team is the target.
        'team.member.added' => [AuditCategory::AdminChanges, ActorSource::UserId, SubjectSource::RelatedId, TargetSource::EntityTypeAndId, OrganisationSource::Context, OutcomeClass::StateChange],
        'team.member.removed' => [AuditCategory::AdminChanges, ActorSource::UserId, SubjectSource::RelatedId, TargetSource::EntityTypeAndId, OrganisationSource::Context, OutcomeClass::StateChange],
        // entity_id IS the managed person here - target and subject coincide.
        'management.relationship.set' => [AuditCategory::AdminChanges, ActorSource::UserId, SubjectSource::EntityId, TargetSource::EntityTypeAndId, OrganisationSource::Context, OutcomeClass::StateChange],
        'management.relationship.cleared' => [AuditCategory::AdminChanges, ActorSource::UserId, SubjectSource::EntityId, TargetSource::EntityTypeAndId, OrganisationSource::Context, OutcomeClass::StateChange],
        'business_unit.legal_entity.associated' => [AuditCategory::AdminChanges, ActorSource::UserId, SubjectSource::None, TargetSource::EntityTypeAndId, OrganisationSource::Context, OutcomeClass::StateChange],
        'business_unit.legal_entity.dissociated' => [AuditCategory::AdminChanges, ActorSource::UserId, SubjectSource::None, TargetSource::EntityTypeAndId, OrganisationSource::Context, OutcomeClass::StateChange],

        // ---- People and groups. user_id is the ADMINISTRATOR and entity_id is
        //      the affected person - the exact inverse of P1-05 below.
        'user.provisioned' => [AuditCategory::UserAccess, ActorSource::UserId, SubjectSource::EntityId, TargetSource::EntityTypeAndId, OrganisationSource::Context, OutcomeClass::StateChange],
        'user.provision.refused' => [AuditCategory::SecurityEvents, ActorSource::UserId, SubjectSource::None, TargetSource::None, OrganisationSource::Context, OutcomeClass::Refusal],
        'user.activated' => [AuditCategory::UserAccess, ActorSource::UserId, SubjectSource::EntityId, TargetSource::EntityTypeAndId, OrganisationSource::Context, OutcomeClass::StateChange],
        'user.deactivated' => [AuditCategory::UserAccess, ActorSource::UserId, SubjectSource::EntityId, TargetSource::EntityTypeAndId, OrganisationSource::Context, OutcomeClass::StateChange],
        'user.organisation.assigned' => [AuditCategory::UserAccess, ActorSource::UserId, SubjectSource::EntityId, TargetSource::EntityTypeAndId, OrganisationSource::Context, OutcomeClass::StateChange],
        'group.created' => [AuditCategory::UserAccess, ActorSource::UserId, SubjectSource::None, TargetSource::EntityTypeAndId, OrganisationSource::Context, OutcomeClass::StateChange],
        'group.updated' => [AuditCategory::UserAccess, ActorSource::UserId, SubjectSource::None, TargetSource::EntityTypeAndId, OrganisationSource::Context, OutcomeClass::StateChange],
        'group.deactivated' => [AuditCategory::UserAccess, ActorSource::UserId, SubjectSource::None, TargetSource::EntityTypeAndId, OrganisationSource::Context, OutcomeClass::StateChange],
        'group.activated' => [AuditCategory::UserAccess, ActorSource::UserId, SubjectSource::None, TargetSource::EntityTypeAndId, OrganisationSource::Context, OutcomeClass::StateChange],
        // The group is the target; the member added or removed is related_id.
        'group.member.added' => [AuditCategory::UserAccess, ActorSource::UserId, SubjectSource::RelatedId, TargetSource::EntityTypeAndId, OrganisationSource::Context, OutcomeClass::StateChange, 'group'],
        'group.member.removed' => [AuditCategory::UserAccess, ActorSource::UserId, SubjectSource::RelatedId, TargetSource::EntityTypeAndId, OrganisationSource::Context, OutcomeClass::StateChange, 'group'],

        // ---- Business domains. user_id is the administrator; related_id the owner.
        'business_domain.created' => [AuditCategory::AdminChanges, ActorSource::UserId, SubjectSource::None, TargetSource::EntityTypeAndId, OrganisationSource::Context, OutcomeClass::StateChange],
        'business_domain.updated' => [AuditCategory::AdminChanges, ActorSource::UserId, SubjectSource::None, TargetSource::EntityTypeAndId, OrganisationSource::Context, OutcomeClass::StateChange],
        'business_domain.enabled' => [AuditCategory::AdminChanges, ActorSource::UserId, SubjectSource::None, TargetSource::EntityTypeAndId, OrganisationSource::Context, OutcomeClass::StateChange],
        'business_domain.disabled' => [AuditCategory::AdminChanges, ActorSource::UserId, SubjectSource::None, TargetSource::EntityTypeAndId, OrganisationSource::Context, OutcomeClass::StateChange],
        'business_domain.owner.assigned' => [AuditCategory::AdminChanges, ActorSource::UserId, SubjectSource::RelatedId, TargetSource::EntityTypeAndId, OrganisationSource::Context, OutcomeClass::StateChange],
        'business_domain.owner.cleared' => [AuditCategory::AdminChanges, ActorSource::UserId, SubjectSource::RelatedId, TargetSource::EntityTypeAndId, OrganisationSource::Context, OutcomeClass::StateChange],

        // ---- Roles and access. THE INVERSE OF USER LIFECYCLE: user_id is the
        //      SUBJECT whose access changed, related_id the administrator who
        //      changed it. EntitlementService has used that convention since
        //      P1-05 and P1-07 follows it; People does not.
        'access.role.assigned' => [AuditCategory::UserAccess, ActorSource::RelatedId, SubjectSource::UserId, TargetSource::EntityTypeAndId, OrganisationSource::Context, OutcomeClass::StateChange, 'role_assignment'],
        'access.role.revoked' => [AuditCategory::UserAccess, ActorSource::RelatedId, SubjectSource::UserId, TargetSource::EntityTypeAndId, OrganisationSource::Context, OutcomeClass::StateChange, 'role_assignment'],
        'access.role.self_assigned' => [AuditCategory::UserAccess, ActorSource::RelatedId, SubjectSource::UserId, TargetSource::EntityTypeAndId, OrganisationSource::Context, OutcomeClass::StateChange, 'role_assignment'],
        'access.entitlement.granted' => [AuditCategory::UserAccess, ActorSource::RelatedId, SubjectSource::UserId, TargetSource::EntityTypeAndId, OrganisationSource::Context, OutcomeClass::StateChange, 'domain_entitlement'],
        'access.entitlement.revoked' => [AuditCategory::UserAccess, ActorSource::RelatedId, SubjectSource::UserId, TargetSource::EntityTypeAndId, OrganisationSource::Context, OutcomeClass::StateChange, 'domain_entitlement'],
        'access.scope.assigned' => [AuditCategory::UserAccess, ActorSource::RelatedId, SubjectSource::UserId, TargetSource::EntityTypeAndId, OrganisationSource::Context, OutcomeClass::StateChange, 'entitlement_scope'],
        'access.scope.revoked' => [AuditCategory::UserAccess, ActorSource::RelatedId, SubjectSource::UserId, TargetSource::EntityTypeAndId, OrganisationSource::Context, OutcomeClass::StateChange, 'entitlement_scope'],
        'access.ceiling.set' => [AuditCategory::UserAccess, ActorSource::RelatedId, SubjectSource::UserId, TargetSource::EntityTypeAndId, OrganisationSource::Context, OutcomeClass::StateChange, 'entitlement_ceiling'],

        // ---- Privileged confirmations. user_id is the person confirming.
        'access.step_up.requested' => [AuditCategory::UserAccess, ActorSource::UserId, SubjectSource::UserId, TargetSource::None, OrganisationSource::Context, OutcomeClass::StateChange],
        'access.step_up.completed' => [AuditCategory::UserAccess, ActorSource::UserId, SubjectSource::UserId, TargetSource::EntityTypeAndId, OrganisationSource::Context, OutcomeClass::StateChange, 'pending_step_up'],
        'access.step_up.refused' => [AuditCategory::SecurityEvents, ActorSource::UserId, SubjectSource::UserId, TargetSource::EntityTypeAndId, OrganisationSource::Context, OutcomeClass::Refusal, 'pending_step_up'],

        // ---- Permanent deletions.
        'legal_entity.purged' => [AuditCategory::AdminChanges, ActorSource::UserId, SubjectSource::None, TargetSource::EntityTypeAndId, OrganisationSource::Context, OutcomeClass::StateChange],
        'business_unit.purged' => [AuditCategory::AdminChanges, ActorSource::UserId, SubjectSource::None, TargetSource::EntityTypeAndId, OrganisationSource::Context, OutcomeClass::StateChange],
        'department.purged' => [AuditCategory::AdminChanges, ActorSource::UserId, SubjectSource::None, TargetSource::EntityTypeAndId, OrganisationSource::Context, OutcomeClass::StateChange],
        'team.purged' => [AuditCategory::AdminChanges, ActorSource::UserId, SubjectSource::None, TargetSource::EntityTypeAndId, OrganisationSource::Context, OutcomeClass::StateChange],
        'business_domain.purged' => [AuditCategory::AdminChanges, ActorSource::UserId, SubjectSource::None, TargetSource::EntityTypeAndId, OrganisationSource::Context, OutcomeClass::StateChange],
        'user.purged' => [AuditCategory::UserAccess, ActorSource::UserId, SubjectSource::EntityId, TargetSource::EntityTypeAndId, OrganisationSource::Context, OutcomeClass::StateChange],
        'group.purged' => [AuditCategory::UserAccess, ActorSource::UserId, SubjectSource::None, TargetSource::EntityTypeAndId, OrganisationSource::Context, OutcomeClass::StateChange],

        // ---- Access reviews. P1-05's convention: subject in user_id, reviewer
        //      in related_id. The cycle carries no subject at all.
        'access.review.cycle.started' => [AuditCategory::UserAccess, ActorSource::RelatedId, SubjectSource::None, TargetSource::EntityTypeAndId, OrganisationSource::Context, OutcomeClass::StateChange, 'access_review_cycle'],
        'access.review.item.retained' => [AuditCategory::UserAccess, ActorSource::RelatedId, SubjectSource::UserId, TargetSource::EntityTypeAndId, OrganisationSource::Context, OutcomeClass::StateChange, 'access_review_item'],
        'access.review.item.revoked' => [AuditCategory::UserAccess, ActorSource::RelatedId, SubjectSource::UserId, TargetSource::EntityTypeAndId, OrganisationSource::Context, OutcomeClass::StateChange, 'access_review_item'],
        // Raised by the state of the reviewed access, not by a decision.
        'access.review.item.superseded' => [AuditCategory::UserAccess, ActorSource::RelatedId, SubjectSource::UserId, TargetSource::EntityTypeAndId, OrganisationSource::Context, OutcomeClass::StateChange, 'access_review_item'],
        'access.review.item.self_reviewed' => [AuditCategory::UserAccess, ActorSource::RelatedId, SubjectSource::UserId, TargetSource::EntityTypeAndId, OrganisationSource::Context, OutcomeClass::StateChange, 'access_review_item'],
        'access.review.refused' => [AuditCategory::SecurityEvents, ActorSource::RelatedId, SubjectSource::UserId, TargetSource::EntityTypeAndId, OrganisationSource::Context, OutcomeClass::Refusal, 'access_review_item'],

        // ---- Conditions the platform raised. No actor exists to record, and
        //      none is invented.
        'access.state.unrecognised' => [AuditCategory::SecurityEvents, ActorSource::System, SubjectSource::None, TargetSource::None, OrganisationSource::None, OutcomeClass::Refusal],
        'access.engine.failed' => [AuditCategory::SecurityEvents, ActorSource::System, SubjectSource::None, TargetSource::None, OrganisationSource::None, OutcomeClass::Refusal],
    ];

    /** The category order a reader sees. Fixed; never sorted by volume. */
    private const ORDER = [
        'First-run setup',
        'Sign-in',
        'Sign-in configuration',
        'Organisation structure changes',
        'People and group changes',
        'Business domain changes',
        'Access changes',
        'Privileged confirmations',
        'Access reviews',
        'Permanent deletions',
        'Access system conditions',
    ];

    /**
     * Every declared event, grouped, in the order above.
     *
     * READS events() AT RUNTIME. A hand-copied list goes stale, and a stale
     * coverage claim is worse than none - N-SS32 breaks it by adding an event
     * the mapping does not know.
     *
     * @return list<array{category: string, events: list<string>}>
     */
    public static function grouped(): array
    {
        $grouped = [];

        foreach (self::ORDER as $category) {
            $grouped[$category] = [];
        }

        foreach (SecurityEventLogger::events() as $key) {
            [$category, $label] = self::MAPPING[$key]
                // An event with no mapping must never reach a screen as a raw
                // key. N-SS33 makes this branch unreachable by failing the
                // build first; it fails closed here in case it ever is.
                ?? ['Access system conditions', 'A recorded security event'];

            $grouped[$category][] = $label;
        }

        $out = [];

        foreach (self::ORDER as $category) {
            if ($grouped[$category] === []) {
                continue;
            }

            $out[] = ['category' => $category, 'events' => $grouped[$category]];
        }

        return $out;
    }

    /**
     * How THIS event's context is read as evidence.
     *
     * Throws rather than defaulting. A default bucket is how an event nobody
     * classified quietly becomes somebody else's actor, and the completeness
     * test makes this branch unreachable by failing the build first.
     */
    public static function semanticsFor(string $event): EventSemantics
    {
        $row = self::SEMANTICS[$event] ?? throw new InvalidArgumentException(
            "No audit semantics declared for security event [{$event}]."
        );

        return new EventSemantics($row[0], $row[1], $row[2], $row[3], $row[4], $row[5], $row[6] ?? null);
    }

    /** @return list<string> */
    public static function semanticsKeys(): array
    {
        return array_keys(self::SEMANTICS);
    }

    /** @return list<AuditCategory> */
    public static function auditCategories(): array
    {
        return AuditCategory::cases();
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function mapping(): array
    {
        return self::MAPPING;
    }

    /** @return list<string> */
    public static function categories(): array
    {
        return self::ORDER;
    }

    public static function total(): int
    {
        return count(SecurityEventLogger::events());
    }

    /**
     * The permitted context keys, in business words.
     *
     * Read from the logger through reflection rather than copied, so the panel
     * cannot claim a contract the code does not enforce.
     *
     * @return list<string>
     */
    public static function permittedFields(): array
    {
        $reflection = new \ReflectionClass(SecurityEventLogger::class);

        /** @var list<string> $keys */
        $keys = $reflection->getConstant('ALLOWED_KEYS') ?: [];

        $words = [
            'provider' => 'Which sign-in provider',
            'subject' => 'The provider\'s reference for the person',
            'tenant' => 'Which Microsoft directory',
            'user_id' => 'Which person, by reference',
            'result' => 'What happened',
            'reason' => 'Why, from a fixed list',
            'expires_at' => 'When something expires',
            'organisation_id' => 'Which organisation',
            'entity_type' => 'What kind of record',
            'entity_id' => 'Which record, by reference',
            'related_id' => 'A second record, by reference',
            'role' => 'Which role, by its fixed code',
            'domain_id' => 'Which business domain, by reference',
            'scope' => 'Which scope',
            'sensitivity' => 'Which sensitivity level',
        ];

        return array_map(
            static fn (string $key): string => $words[$key] ?? 'A structural reference',
            $keys,
        );
    }
}
