<?php

declare(strict_types=1);

namespace App\Modules\Security\Catalogue;

use App\Modules\Platform\Security\SecurityEventLogger;

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

    public const LIMITATION =
        'Searchable security history arrives with Audit. What you see here is what is being '
        .'recorded, not a record of what happened.';

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
        // ---- Access system conditions — 2 -------------------------------
        'access.state.unrecognised' => ['Access system conditions', 'Access state could not be interpreted'],
        'access.engine.failed' => ['Access system conditions', 'Access decision could not be completed'],
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
