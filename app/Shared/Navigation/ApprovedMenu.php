<?php

declare(strict_types=1);

namespace App\Shared\Navigation;

/**
 * The complete approved SemantIQ menu, in one place.
 *
 * Every label here is verbatim from the phase authority documents - Phase 1
 * section "Menu", Phase 2 section "Menu", Phase 3 section "Menu" - and the
 * order is the Product Owner's. Nothing is invented and nothing is abbreviated.
 *
 * ONE capability is delivered: System Administration -> Organisation. Every
 * other entry is LOCKED, which means it carries no route, no controller, no
 * table and no service. It shows the shape of the product; it grants nothing.
 *
 * When a unit is delivered, its entry moves from locked() to leaf() and gains
 * its route. That is the only edit needed here.
 */
final class ApprovedMenu
{
    /**
     * @return list<NavigationNode>
     */
    public static function roadmap(): array
    {
        return [
            ...self::workplace(),
            ...self::fabricConfiguration(),
            ...self::systemAdministration(),
        ];
    }

    /** Phase 3. Nothing delivered. */
    private static function workplace(): array
    {
        $area = ProductArea::SemantiqWorkplace;
        $policy = 'workplace.view';

        return [
            NavigationNode::locked($area, 'Home', 'i-home', $policy),

            NavigationNode::group($area, 'My Intelligence', 'i-brain', $policy, [
                NavigationNode::locked($area, 'Executive Intelligence', 'i-crown', $policy),
                NavigationNode::locked($area, 'Sales Intelligence', 'i-trending-up', $policy),
                NavigationNode::locked($area, 'Finance Intelligence', 'i-coins', $policy),
                NavigationNode::locked($area, 'People Intelligence', 'i-id-badge', $policy),
                NavigationNode::locked($area, 'Operations Intelligence', 'i-cog', $policy),
                NavigationNode::locked($area, 'Customer Intelligence', 'i-smile', $policy),
                NavigationNode::locked($area, 'Learning Intelligence', 'i-book', $policy),
                NavigationNode::locked($area, 'Custom Intelligence', 'i-puzzle', $policy),
            ]),

            NavigationNode::locked($area, 'Explore', 'i-compass', $policy),
            NavigationNode::locked($area, 'Ask SemantIQ', 'i-message', $policy),
            NavigationNode::locked($area, 'Insights', 'i-lightbulb', $policy),
            NavigationNode::locked($area, 'Risks & Opportunities', 'i-alert-triangle', $policy),
            NavigationNode::locked($area, 'Recommendations', 'i-check-circle', $policy),
            NavigationNode::locked($area, 'Decisions & Alerts', 'i-bell', $policy),
            NavigationNode::locked($area, 'Reports & Dashboards', 'i-chart-pie', $policy),
            NavigationNode::locked($area, 'My Workspace', 'i-briefcase', $policy),
            NavigationNode::locked($area, 'Help', 'i-help', $policy),
        ];
    }

    /** Phase 2. Nothing delivered. */
    private static function fabricConfiguration(): array
    {
        $area = ProductArea::FabricConfiguration;
        $policy = 'fabric.view';

        return [
            NavigationNode::locked($area, 'Overview', 'i-gauge', $policy),
            NavigationNode::locked($area, 'Data Sources', 'i-database', $policy),
            NavigationNode::locked($area, 'Connect Source', 'i-plug', $policy),
            NavigationNode::locked($area, 'Discovery', 'i-search', $policy),
            NavigationNode::locked($area, 'Data Classification', 'i-tag', $policy),
            NavigationNode::locked($area, 'Ingestion', 'i-download', $policy),
            NavigationNode::locked($area, 'Data Quality', 'i-clipboard-check', $policy),
            NavigationNode::locked($area, 'Business Model', 'i-cube', $policy),
            NavigationNode::locked($area, 'Security Mapping', 'i-shield-check', $policy),
            NavigationNode::locked($area, 'Semantic Model', 'i-share-nodes', $policy),
            NavigationNode::locked($area, 'AI Readiness', 'i-sparkles', $policy),
            NavigationNode::locked($area, 'Pipelines & Refresh', 'i-refresh', $policy),
            NavigationNode::locked($area, 'Power BI Publication', 'i-upload', $policy),
            NavigationNode::locked($area, 'Monitoring', 'i-activity', $policy),
        ];
    }

    /**
     * Phase 1. Organisation is delivered (P1-01); everything else is locked.
     *
     * Administration Home appears in its correct position and stays locked
     * until P1-10, which is deliberately built last from real sources.
     */
    private static function systemAdministration(): array
    {
        $area = ProductArea::SystemAdministration;
        $policy = 'administration.view';

        return [
            NavigationNode::locked($area, 'Administration Home', 'i-grid', $policy),

            // The one delivered capability.
            NavigationNode::leaf($area, 'Organisation', 'i-sitemap', 'organisation.profile', 'organisation.view'),

            // P1-03. Delivered: Users and Groups, System Administrator only.
            // Creating a user or adding somebody to a group grants nothing.
            NavigationNode::leaf($area, 'Users & Groups', 'i-users', 'people.users', 'people.view'),
            // P1-05. Delivered: role assignments, domain entitlements, scopes,
            // sensitivity ceilings and the Access Simulator. A role grants
            // nothing on its own - business information needs a complete path
            // of role, entitlement, scope and sensitivity.
            NavigationNode::leaf($area, 'Roles & Access', 'i-key', 'access.index', 'access.view'),
            // P1-04. Delivered: one list, one record page, System Administrator
            // only. A domain existing, being enabled, or having an owner grants
            // ZERO access - to its owner or to anybody.
            NavigationNode::leaf($area, 'Business Domains', 'i-layers', 'domains.index', 'domains.view'),
            // P1-02. Delivered: five route-backed tabs, read-only, System
            // Administrator only. Every route re-authorises on its own.
            NavigationNode::leaf($area, 'Identity & SSO', 'i-fingerprint', 'identity.entra', 'identity.view'),
            // P1-06. Delivered: four read-only tabs. Every route is a GET, so
            // nothing on these screens can switch a control off. Reaching them
            // needs EvidenceRead, which System Administrator, Organisation
            // Administrator and Auditor already hold; what each may VALUE is
            // narrower and is decided in the projection, not here.
            NavigationNode::leaf($area, 'Security Status', 'i-shield', 'security.baseline', 'security.view'),
            // P1-07. Delivered: three route-backed tabs. Reviews CONFIRM or
            // REMOVE existing access - they never create, widen or repair it,
            // and confirming writes nothing to the access model at all.
            NavigationNode::leaf($area, 'Access Reviews', 'i-clipboard-list', 'access-reviews.privileged', 'access-reviews.view'),
            // P1-08. Delivered: four read-only tabs over durable evidence.
            // Every route is a GET and no application path updates or deletes a
            // row. D-19 IS UNCHANGED: this node already sits inside System
            // Administration, which is shown to System Administrators only, so
            // nothing outside Audit is widened. Auditor and Organisation
            // Administrator route-level permissions are implemented and tested;
            // reaching the screen through the sidebar stays carried.
            NavigationNode::leaf($area, 'Audit', 'i-scroll', 'audit.user-access', 'audit.view'),
            // P1-09. Delivered: one read-only screen, ONE GET and no other
            // verb, so nothing on it can restart, clear or re-run anything.
            // PlatformAdmin, not EvidenceRead: infrastructure visibility is a
            // different authority from audit-evidence access. D-19 IS
            // UNCHANGED - this node already sits inside System Administration,
            // which is shown to System Administrators only, so nothing is
            // widened.
            NavigationNode::leaf($area, 'System Health', 'i-heart-pulse', 'system-health.show', 'system-health.view'),
        ];
    }
}
