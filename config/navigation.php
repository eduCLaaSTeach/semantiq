<?php

declare(strict_types=1);

use App\Enums\BusinessDomain;
use App\Enums\Role;

/*
|-------------------------------------------------------------------------------
| Navigation
|-------------------------------------------------------------------------------
|
| The functional tree comes from doc/MENU_STRUCTURE.md v1.1. The four clusters
| are the design template's closed, fixed-order set, so the two are reconciled
| by MAPPING rather than by replacing either - decision D1 in
| doc/execution/PHASE-00-PLAN.md.
|
| Administration's fifteen groups are split by responsibility rather than kept
| as one entry: Compliance takes governance and evidence, Application
| Administration takes the data and model estate, System Administration takes
| the platform itself. That split is the reason the template gives a fourth
| cluster at all - an administrator who can invite a colleague should not
| thereby hold every provider credential.
|
| GENERATED FROM THE SPECIFICATION, not hand-typed, so the tree cannot quietly
| drift from the document it comes from.
|
*/

return [

    /*
    | A named rule: the minimum tier, and whether the Auditor capability also
    | satisfies it. Nodes name a policy rather than a role, so widening access
    | is one edit here rather than a search through the tree.
    |
    | An unknown policy DENIES. A typo must never become a grant.
    |
    | A `domain` key means the policy ALSO requires an entitlement to that
    | business domain. ROLE_MODEL.md section 1: a role alone never grants
    | business data, so a System Administrator holds no Sales figures by virtue
    | of being one.
    */
    'policies' => [
        'workspace' => ['min' => Role::Viewer],
        'analyst' => ['min' => Role::Analyst],
        'compliance' => ['min' => Role::DomainOwner, 'or_auditor' => true],
        'app-admin' => ['min' => Role::Admin],
        'system-admin' => ['min' => Role::SystemAdmin],

        'domain-executive' => ['min' => Role::Viewer, 'domain' => BusinessDomain::Executive],
        'domain-sales' => ['min' => Role::Viewer, 'domain' => BusinessDomain::Sales],
        'domain-finance' => ['min' => Role::Viewer, 'domain' => BusinessDomain::Finance],
        'domain-people' => ['min' => Role::Viewer, 'domain' => BusinessDomain::People],
        'domain-operations' => ['min' => Role::Viewer, 'domain' => BusinessDomain::Operations],
        'domain-customer' => ['min' => Role::Viewer, 'domain' => BusinessDomain::Customer],
        'domain-learning' => ['min' => Role::Viewer, 'domain' => BusinessDomain::Learning],
    ],

    'clusters' => [

        'Workspace' => [
            [
                'label' => 'Home',
                'icon' => 'i-home',
                'route' => 'home',
                'policy' => 'workspace',
            ],
            [
                'label' => 'My Intelligence',
                'icon' => 'i-brain',
                'route' => 'intelligence',
                'policy' => 'workspace',
                'children' => [
                    [
                        'label' => 'Executive Intelligence',
                        'icon' => 'i-target',
                        'policy' => 'domain-executive',
                        'children' => [
                            [
                                'label' => 'Enterprise Overview',
                                'icon' => 'i-layout',
                                'policy' => 'domain-executive',
                            ],
                            [
                                'label' => 'Strategic KPIs',
                                'icon' => 'i-target',
                                'policy' => 'domain-executive',
                            ],
                            [
                                'label' => 'Financial Performance',
                                'icon' => 'i-banknote',
                                'policy' => 'domain-executive',
                            ],
                            [
                                'label' => 'Sales Performance',
                                'icon' => 'i-funnel',
                                'policy' => 'domain-executive',
                            ],
                            [
                                'label' => 'Workforce',
                                'icon' => 'i-users',
                                'policy' => 'domain-executive',
                            ],
                            [
                                'label' => 'Operations',
                                'icon' => 'i-headset',
                                'policy' => 'domain-executive',
                            ],
                            [
                                'label' => 'Customer',
                                'icon' => 'i-user-round',
                                'policy' => 'domain-executive',
                            ],
                            [
                                'label' => 'Cross-Functional Risks',
                                'icon' => 'i-alert-triangle',
                                'policy' => 'domain-executive',
                            ],
                            [
                                'label' => 'Opportunities',
                                'icon' => 'i-sparkle',
                                'policy' => 'domain-executive',
                            ],
                            [
                                'label' => 'Forecast',
                                'icon' => 'i-trending-up',
                                'policy' => 'domain-executive',
                            ],
                            [
                                'label' => 'Ask Executive AI',
                                'icon' => 'i-message-circle',
                                'policy' => 'domain-executive',
                            ],
                        ],
                    ],
                    [
                        'label' => 'Sales Intelligence',
                        'icon' => 'i-trending-up',
                        'policy' => 'domain-sales',
                        'children' => [
                            [
                                'label' => 'Overview',
                                'icon' => 'i-layout',
                                'policy' => 'domain-sales',
                            ],
                            [
                                'label' => 'Revenue',
                                'icon' => 'i-banknote',
                                'policy' => 'domain-sales',
                            ],
                            [
                                'label' => 'Pipeline',
                                'icon' => 'i-funnel',
                                'policy' => 'domain-sales',
                            ],
                            [
                                'label' => 'Opportunities',
                                'icon' => 'i-sparkle',
                                'policy' => 'domain-sales',
                            ],
                            [
                                'label' => 'Customers',
                                'icon' => 'i-user-round',
                                'policy' => 'domain-sales',
                            ],
                            [
                                'label' => 'Products',
                                'icon' => 'i-box',
                                'policy' => 'domain-sales',
                            ],
                            [
                                'label' => 'Sales Team',
                                'icon' => 'i-users',
                                'policy' => 'domain-sales',
                            ],
                            [
                                'label' => 'Forecast',
                                'icon' => 'i-trending-up',
                                'policy' => 'domain-sales',
                            ],
                            [
                                'label' => 'Trends',
                                'icon' => 'i-activity',
                                'policy' => 'domain-sales',
                            ],
                            [
                                'label' => 'Risks & Opportunities',
                                'icon' => 'i-alert-triangle',
                                'policy' => 'domain-sales',
                            ],
                            [
                                'label' => 'Ask Sales AI',
                                'icon' => 'i-message-circle',
                                'policy' => 'domain-sales',
                            ],
                        ],
                    ],
                    [
                        'label' => 'Finance Intelligence',
                        'icon' => 'i-banknote',
                        'policy' => 'domain-finance',
                        'children' => [
                            [
                                'label' => 'Financial Overview',
                                'icon' => 'i-layout',
                                'policy' => 'domain-finance',
                            ],
                            [
                                'label' => 'Revenue',
                                'icon' => 'i-banknote',
                                'policy' => 'domain-finance',
                            ],
                            [
                                'label' => 'Expenses',
                                'icon' => 'i-banknote',
                                'policy' => 'domain-finance',
                            ],
                            [
                                'label' => 'Profitability',
                                'icon' => 'i-banknote',
                                'policy' => 'domain-finance',
                            ],
                            [
                                'label' => 'Cash Flow',
                                'icon' => 'i-banknote',
                                'policy' => 'domain-finance',
                            ],
                            [
                                'label' => 'Budget vs Actual',
                                'icon' => 'i-scale',
                                'policy' => 'domain-finance',
                            ],
                            [
                                'label' => 'Receivables',
                                'icon' => 'i-scale',
                                'policy' => 'domain-finance',
                            ],
                            [
                                'label' => 'Payables',
                                'icon' => 'i-scale',
                                'policy' => 'domain-finance',
                            ],
                            [
                                'label' => 'Business Units',
                                'icon' => 'i-building',
                                'policy' => 'domain-finance',
                            ],
                            [
                                'label' => 'Variance Analysis',
                                'icon' => 'i-git-compare',
                                'policy' => 'domain-finance',
                            ],
                            [
                                'label' => 'Forecast',
                                'icon' => 'i-trending-up',
                                'policy' => 'domain-finance',
                            ],
                            [
                                'label' => 'Risks',
                                'icon' => 'i-alert-triangle',
                                'policy' => 'domain-finance',
                            ],
                            [
                                'label' => 'Ask Finance AI',
                                'icon' => 'i-message-circle',
                                'policy' => 'domain-finance',
                            ],
                        ],
                    ],
                    [
                        'label' => 'People Intelligence',
                        'icon' => 'i-users',
                        'policy' => 'domain-people',
                        'children' => [
                            [
                                'label' => 'Workforce Overview',
                                'icon' => 'i-layout',
                                'policy' => 'domain-people',
                            ],
                            [
                                'label' => 'Headcount',
                                'icon' => 'i-user-plus',
                                'policy' => 'domain-people',
                            ],
                            [
                                'label' => 'Attrition',
                                'icon' => 'i-user-minus',
                                'policy' => 'domain-people',
                            ],
                            [
                                'label' => 'Recruitment',
                                'icon' => 'i-user-plus',
                                'policy' => 'domain-people',
                            ],
                            [
                                'label' => 'Performance',
                                'icon' => 'i-gauge',
                                'policy' => 'domain-people',
                            ],
                            [
                                'label' => 'Skills',
                                'icon' => 'i-award',
                                'policy' => 'domain-people',
                            ],
                            [
                                'label' => 'Workforce Cost',
                                'icon' => 'i-banknote',
                                'policy' => 'domain-people',
                            ],
                            [
                                'label' => 'Attendance',
                                'icon' => 'i-calendar',
                                'policy' => 'domain-people',
                            ],
                            [
                                'label' => 'Learning & Development',
                                'icon' => 'i-graduation',
                                'policy' => 'domain-people',
                            ],
                            [
                                'label' => 'Workforce Planning',
                                'icon' => 'i-map',
                                'policy' => 'domain-people',
                            ],
                            [
                                'label' => 'Risks',
                                'icon' => 'i-alert-triangle',
                                'policy' => 'domain-people',
                            ],
                            [
                                'label' => 'Ask People AI',
                                'icon' => 'i-message-circle',
                                'policy' => 'domain-people',
                            ],
                        ],
                    ],
                    [
                        'label' => 'Operations Intelligence',
                        'icon' => 'i-server',
                        'policy' => 'domain-operations',
                        'children' => [
                            [
                                'label' => 'Operations Overview',
                                'icon' => 'i-layout',
                                'policy' => 'domain-operations',
                            ],
                            [
                                'label' => 'Service Levels',
                                'icon' => 'i-headset',
                                'policy' => 'domain-operations',
                            ],
                            [
                                'label' => 'Throughput',
                                'icon' => 'i-gauge',
                                'policy' => 'domain-operations',
                            ],
                            [
                                'label' => 'Productivity',
                                'icon' => 'i-zap',
                                'policy' => 'domain-operations',
                            ],
                            [
                                'label' => 'Exceptions',
                                'icon' => 'i-alert-octagon',
                                'policy' => 'domain-operations',
                            ],
                            [
                                'label' => 'Capacity',
                                'icon' => 'i-server',
                                'policy' => 'domain-operations',
                            ],
                            [
                                'label' => 'Cost',
                                'icon' => 'i-banknote',
                                'policy' => 'domain-operations',
                            ],
                            [
                                'label' => 'Trends',
                                'icon' => 'i-activity',
                                'policy' => 'domain-operations',
                            ],
                            [
                                'label' => 'Forecast',
                                'icon' => 'i-trending-up',
                                'policy' => 'domain-operations',
                            ],
                            [
                                'label' => 'Risks',
                                'icon' => 'i-alert-triangle',
                                'policy' => 'domain-operations',
                            ],
                            [
                                'label' => 'Ask Operations AI',
                                'icon' => 'i-message-circle',
                                'policy' => 'domain-operations',
                            ],
                        ],
                    ],
                    [
                        'label' => 'Customer Intelligence',
                        'icon' => 'i-heart',
                        'policy' => 'domain-customer',
                        'children' => [
                            [
                                'label' => 'Customer Overview',
                                'icon' => 'i-layout',
                                'policy' => 'domain-customer',
                            ],
                            [
                                'label' => 'Segments',
                                'icon' => 'i-pie',
                                'policy' => 'domain-customer',
                            ],
                            [
                                'label' => 'Revenue',
                                'icon' => 'i-banknote',
                                'policy' => 'domain-customer',
                            ],
                            [
                                'label' => 'Retention',
                                'icon' => 'i-refresh',
                                'policy' => 'domain-customer',
                            ],
                            [
                                'label' => 'Engagement',
                                'icon' => 'i-heart',
                                'policy' => 'domain-customer',
                            ],
                            [
                                'label' => 'Satisfaction',
                                'icon' => 'i-heart',
                                'policy' => 'domain-customer',
                            ],
                            [
                                'label' => 'At-Risk Customers',
                                'icon' => 'i-alert-triangle',
                                'policy' => 'domain-customer',
                            ],
                            [
                                'label' => 'Growth Opportunities',
                                'icon' => 'i-sparkle',
                                'policy' => 'domain-customer',
                            ],
                            [
                                'label' => 'Trends',
                                'icon' => 'i-activity',
                                'policy' => 'domain-customer',
                            ],
                            [
                                'label' => 'Ask Customer AI',
                                'icon' => 'i-message-circle',
                                'policy' => 'domain-customer',
                            ],
                        ],
                    ],
                    [
                        'label' => 'Learning Intelligence',
                        'icon' => 'i-graduation',
                        'policy' => 'domain-learning',
                        'children' => [
                            [
                                'label' => 'Learning Overview',
                                'icon' => 'i-layout',
                                'policy' => 'domain-learning',
                            ],
                            [
                                'label' => 'Enrolment',
                                'icon' => 'i-user-plus',
                                'policy' => 'domain-learning',
                            ],
                            [
                                'label' => 'Attendance',
                                'icon' => 'i-calendar',
                                'policy' => 'domain-learning',
                            ],
                            [
                                'label' => 'Engagement',
                                'icon' => 'i-heart',
                                'policy' => 'domain-learning',
                            ],
                            [
                                'label' => 'Progress',
                                'icon' => 'i-bar-chart',
                                'policy' => 'domain-learning',
                            ],
                            [
                                'label' => 'Completion',
                                'icon' => 'i-check-circle',
                                'policy' => 'domain-learning',
                            ],
                            [
                                'label' => 'Assessment',
                                'icon' => 'i-file-check',
                                'policy' => 'domain-learning',
                            ],
                            [
                                'label' => 'At-Risk Learners',
                                'icon' => 'i-alert-triangle',
                                'policy' => 'domain-learning',
                            ],
                            [
                                'label' => 'Skills',
                                'icon' => 'i-award',
                                'policy' => 'domain-learning',
                            ],
                            [
                                'label' => 'Intervention Opportunities',
                                'icon' => 'i-lightbulb',
                                'policy' => 'domain-learning',
                            ],
                            [
                                'label' => 'Ask Learning AI',
                                'icon' => 'i-message-circle',
                                'policy' => 'domain-learning',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'label' => 'Ask SemantIQ',
                'icon' => 'i-message-circle',
                'policy' => 'workspace',
                'children' => [
                    [
                        'label' => 'New Conversation',
                        'icon' => 'i-message-square',
                        'policy' => 'workspace',
                    ],
                    [
                        'label' => 'Suggested Questions',
                        'icon' => 'i-help-circle',
                        'policy' => 'workspace',
                    ],
                    [
                        'label' => 'Domain Selector',
                        'icon' => 'i-layers',
                        'policy' => 'workspace',
                    ],
                    [
                        'label' => 'Conversation History',
                        'icon' => 'i-clock',
                        'policy' => 'workspace',
                    ],
                    [
                        'label' => 'Saved Questions',
                        'icon' => 'i-bookmark',
                        'policy' => 'workspace',
                    ],
                    [
                        'label' => 'Shared Questions',
                        'icon' => 'i-share',
                        'policy' => 'workspace',
                    ],
                ],
            ],
            [
                'label' => 'Explore',
                'icon' => 'i-ruler',
                'policy' => 'analyst',
                'children' => [
                    [
                        'label' => 'Business Metrics',
                        'icon' => 'i-ruler',
                        'policy' => 'analyst',
                    ],
                    [
                        'label' => 'Dimensions',
                        'icon' => 'i-layers',
                        'policy' => 'analyst',
                    ],
                    [
                        'label' => 'Trends',
                        'icon' => 'i-activity',
                        'policy' => 'analyst',
                    ],
                    [
                        'label' => 'Comparisons',
                        'icon' => 'i-git-compare',
                        'policy' => 'analyst',
                    ],
                    [
                        'label' => 'Drill Down',
                        'icon' => 'i-corner-down-right',
                        'policy' => 'analyst',
                    ],
                    [
                        'label' => 'Saved Analysis',
                        'icon' => 'i-bookmark',
                        'policy' => 'analyst',
                    ],
                    [
                        'label' => 'My Views',
                        'icon' => 'i-eye',
                        'policy' => 'analyst',
                    ],
                ],
            ],
            [
                'label' => 'Decisions & Alerts',
                'icon' => 'i-gavel',
                'policy' => 'workspace',
                'children' => [
                    [
                        'label' => 'Attention Required',
                        'icon' => 'i-bell-ring',
                        'policy' => 'workspace',
                    ],
                    [
                        'label' => 'Risks',
                        'icon' => 'i-alert-triangle',
                        'policy' => 'workspace',
                    ],
                    [
                        'label' => 'Opportunities',
                        'icon' => 'i-sparkle',
                        'policy' => 'workspace',
                    ],
                    [
                        'label' => 'Anomalies',
                        'icon' => 'i-scan',
                        'policy' => 'workspace',
                    ],
                    [
                        'label' => 'Recommendations',
                        'icon' => 'i-lightbulb',
                        'policy' => 'workspace',
                    ],
                    [
                        'label' => 'My Alerts',
                        'icon' => 'i-bell',
                        'policy' => 'workspace',
                    ],
                    [
                        'label' => 'Assigned Decisions',
                        'icon' => 'i-inbox',
                        'policy' => 'workspace',
                    ],
                    [
                        'label' => 'Decision History',
                        'icon' => 'i-clock',
                        'policy' => 'workspace',
                    ],
                ],
            ],
            [
                'label' => 'Reports & Insights',
                'icon' => 'i-file-text',
                'policy' => 'workspace',
                'children' => [
                    [
                        'label' => 'My Reports',
                        'icon' => 'i-file-text',
                        'policy' => 'workspace',
                    ],
                    [
                        'label' => 'Executive Reports',
                        'icon' => 'i-layout',
                        'policy' => 'workspace',
                    ],
                    [
                        'label' => 'Sales',
                        'icon' => 'i-funnel',
                        'policy' => 'workspace',
                    ],
                    [
                        'label' => 'Finance',
                        'icon' => 'i-banknote',
                        'policy' => 'workspace',
                    ],
                    [
                        'label' => 'People',
                        'icon' => 'i-users',
                        'policy' => 'workspace',
                    ],
                    [
                        'label' => 'Operations',
                        'icon' => 'i-headset',
                        'policy' => 'workspace',
                    ],
                    [
                        'label' => 'Customer',
                        'icon' => 'i-user-round',
                        'policy' => 'workspace',
                    ],
                    [
                        'label' => 'Learning',
                        'icon' => 'i-graduation',
                        'policy' => 'workspace',
                    ],
                    [
                        'label' => 'Saved Insights',
                        'icon' => 'i-bookmark',
                        'policy' => 'workspace',
                    ],
                    [
                        'label' => 'Scheduled Reports',
                        'icon' => 'i-calendar',
                        'policy' => 'workspace',
                    ],
                ],
            ],
            [
                'label' => 'My Workspace',
                'icon' => 'i-grid',
                'policy' => 'workspace',
                'children' => [
                    [
                        'label' => 'My Dashboard',
                        'icon' => 'i-grid',
                        'policy' => 'workspace',
                    ],
                    [
                        'label' => 'Saved Insights',
                        'icon' => 'i-bookmark',
                        'policy' => 'workspace',
                    ],
                    [
                        'label' => 'Saved Questions',
                        'icon' => 'i-help-circle',
                        'policy' => 'workspace',
                    ],
                    [
                        'label' => 'My Alerts',
                        'icon' => 'i-bell',
                        'policy' => 'workspace',
                    ],
                    [
                        'label' => 'My Reports',
                        'icon' => 'i-file-text',
                        'policy' => 'workspace',
                    ],
                    [
                        'label' => 'My Decisions',
                        'icon' => 'i-gavel',
                        'policy' => 'workspace',
                    ],
                    [
                        'label' => 'Preferences',
                        'icon' => 'i-sliders',
                        'policy' => 'workspace',
                    ],
                ],
            ],
            [
                'label' => 'Help',
                'icon' => 'i-help-circle',
                'policy' => 'workspace',
                'children' => [
                    [
                        'label' => 'Getting Started',
                        'icon' => 'i-flag',
                        'policy' => 'workspace',
                    ],
                    [
                        'label' => 'Using My Intelligence',
                        'icon' => 'i-brain',
                        'policy' => 'workspace',
                    ],
                    [
                        'label' => 'Asking SemantIQ',
                        'icon' => 'i-message-circle',
                        'policy' => 'workspace',
                    ],
                    [
                        'label' => 'Exploring Data',
                        'icon' => 'i-ruler',
                        'policy' => 'workspace',
                    ],
                    [
                        'label' => 'Decisions & Alerts',
                        'icon' => 'i-gavel',
                        'policy' => 'workspace',
                    ],
                    [
                        'label' => 'Reports',
                        'icon' => 'i-file-text',
                        'policy' => 'workspace',
                    ],
                    [
                        'label' => 'Privacy & Data Use',
                        'icon' => 'i-shield',
                        'policy' => 'workspace',
                    ],
                    [
                        'label' => 'Troubleshooting',
                        'icon' => 'i-wrench',
                        'policy' => 'workspace',
                    ],
                    [
                        'label' => 'Contact Support',
                        'icon' => 'i-headset',
                        'policy' => 'workspace',
                    ],
                ],
            ],
        ],

        'Compliance' => [
            [
                'label' => 'Governance',
                'icon' => 'i-book',
                'policy' => 'compliance',
                'children' => [
                    [
                        'label' => 'Catalogue',
                        'icon' => 'i-book',
                        'policy' => 'compliance',
                    ],
                    [
                        'label' => 'Ownership',
                        'icon' => 'i-user-round',
                        'policy' => 'compliance',
                    ],
                    [
                        'label' => 'Classification',
                        'icon' => 'i-tag',
                        'policy' => 'compliance',
                    ],
                    [
                        'label' => 'Lineage',
                        'icon' => 'i-git-branch',
                        'policy' => 'compliance',
                    ],
                    [
                        'label' => 'Access Policy',
                        'icon' => 'i-shield',
                        'policy' => 'compliance',
                    ],
                    [
                        'label' => 'Certifications',
                        'icon' => 'i-badge-check',
                        'policy' => 'compliance',
                    ],
                    [
                        'label' => 'Audit',
                        'icon' => 'i-list-check',
                        'policy' => 'compliance',
                    ],
                    [
                        'label' => 'Governance Decisions',
                        'icon' => 'i-gavel',
                        'policy' => 'compliance',
                    ],
                ],
            ],
            [
                'label' => 'Data Protection',
                'icon' => 'i-shield',
                'policy' => 'compliance',
                'children' => [
                    [
                        'label' => 'Data Protection Profile',
                        'icon' => 'i-shield',
                        'policy' => 'compliance',
                    ],
                    [
                        'label' => 'Personal / Sensitive Data',
                        'icon' => 'i-lock',
                        'policy' => 'compliance',
                    ],
                    [
                        'label' => 'Sensitivity Labels',
                        'icon' => 'i-tag',
                        'policy' => 'compliance',
                    ],
                    [
                        'label' => 'DLP Policies',
                        'icon' => 'i-shield-alert',
                        'policy' => 'compliance',
                    ],
                    [
                        'label' => 'Retention',
                        'icon' => 'i-refresh',
                        'policy' => 'compliance',
                    ],
                    [
                        'label' => 'Minimisation',
                        'icon' => 'i-minimize',
                        'policy' => 'compliance',
                    ],
                    [
                        'label' => 'Export Policy',
                        'icon' => 'i-upload',
                        'policy' => 'compliance',
                    ],
                    [
                        'label' => 'Exceptions',
                        'icon' => 'i-alert-octagon',
                        'policy' => 'compliance',
                    ],
                ],
            ],
            [
                'label' => 'Data Sovereignty',
                'icon' => 'i-globe',
                'policy' => 'compliance',
                'children' => [
                    [
                        'label' => 'Approved Geographies',
                        'icon' => 'i-globe',
                        'policy' => 'compliance',
                    ],
                    [
                        'label' => 'Storage Geography',
                        'icon' => 'i-hard-drive',
                        'policy' => 'compliance',
                    ],
                    [
                        'label' => 'Processing Geography',
                        'icon' => 'i-cpu',
                        'policy' => 'compliance',
                    ],
                    [
                        'label' => 'AI Processing Geography',
                        'icon' => 'i-sparkles',
                        'policy' => 'compliance',
                    ],
                    [
                        'label' => 'Cross-Geo Controls',
                        'icon' => 'i-globe',
                        'policy' => 'compliance',
                    ],
                    [
                        'label' => 'Network Route',
                        'icon' => 'i-route',
                        'policy' => 'compliance',
                    ],
                    [
                        'label' => 'Exceptions',
                        'icon' => 'i-alert-octagon',
                        'policy' => 'compliance',
                    ],
                    [
                        'label' => 'Evidence',
                        'icon' => 'i-file-check',
                        'policy' => 'compliance',
                    ],
                ],
            ],
            [
                'label' => 'Audit Logs',
                'icon' => 'i-list-check',
                'policy' => 'compliance',
            ],
        ],

        'Application Administration' => [
            [
                'label' => 'Organisation & Users',
                'icon' => 'i-building',
                'policy' => 'app-admin',
                'children' => [
                    [
                        'label' => 'Organisation Profile',
                        'icon' => 'i-building',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'Business Units',
                        'icon' => 'i-building',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'Teams',
                        'icon' => 'i-users',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'Users',
                        'icon' => 'i-users',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'Roles',
                        'icon' => 'i-key',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'Domain Entitlements',
                        'icon' => 'i-ticket',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'Security Groups',
                        'icon' => 'i-users',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'Access Reviews',
                        'icon' => 'i-search-check',
                        'policy' => 'app-admin',
                    ],
                ],
            ],
            [
                'label' => 'Data Sources',
                'icon' => 'i-database',
                'policy' => 'app-admin',
                'children' => [
                    [
                        'label' => 'Source Registry',
                        'icon' => 'i-database',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'Add Source',
                        'icon' => 'i-plus-circle',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'Authentication',
                        'icon' => 'i-lock',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'Schema Discovery',
                        'icon' => 'i-table',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'Connection Test',
                        'icon' => 'i-activity',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'Source Health',
                        'icon' => 'i-heart-pulse',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'Source Help',
                        'icon' => 'i-help-circle',
                        'policy' => 'app-admin',
                    ],
                ],
            ],
            [
                'label' => 'Data Engineering',
                'icon' => 'i-git-branch',
                'policy' => 'app-admin',
                'children' => [
                    [
                        'label' => 'Ingestion Jobs',
                        'icon' => 'i-download',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'Pipelines',
                        'icon' => 'i-git-branch',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'Mirroring',
                        'icon' => 'i-copy',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'Shortcuts',
                        'icon' => 'i-link',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'Dataflow Gen2',
                        'icon' => 'i-workflow',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'Incremental Load',
                        'icon' => 'i-repeat',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'Schedules',
                        'icon' => 'i-calendar',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'Lakehouse',
                        'icon' => 'i-database',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'Bronze',
                        'icon' => 'i-circle',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'Silver',
                        'icon' => 'i-circle',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'Gold',
                        'icon' => 'i-circle',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'Run History',
                        'icon' => 'i-clock',
                        'policy' => 'app-admin',
                    ],
                ],
            ],
            [
                'label' => 'Data Quality',
                'icon' => 'i-clipboard-check',
                'policy' => 'app-admin',
                'children' => [
                    [
                        'label' => 'Profiling',
                        'icon' => 'i-scan',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'Quality Rules',
                        'icon' => 'i-list-check',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'Validation Rules',
                        'icon' => 'i-list-check',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'Duplicates',
                        'icon' => 'i-copy',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'Null Handling',
                        'icon' => 'i-slash',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'Standardisation',
                        'icon' => 'i-ruler',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'Anomalies',
                        'icon' => 'i-scan',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'Rejects',
                        'icon' => 'i-x-circle',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'Quality Scorecard',
                        'icon' => 'i-clipboard-check',
                        'policy' => 'app-admin',
                    ],
                ],
            ],
            [
                'label' => 'Business Model',
                'icon' => 'i-box',
                'policy' => 'app-admin',
                'children' => [
                    [
                        'label' => 'Business Domains',
                        'icon' => 'i-layers',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'Entity Discovery',
                        'icon' => 'i-table',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'Business Entities',
                        'icon' => 'i-box',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'Business Keys',
                        'icon' => 'i-key',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'Relationships',
                        'icon' => 'i-git-merge',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'Facts',
                        'icon' => 'i-table',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'Dimensions',
                        'icon' => 'i-layers',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'Hierarchies',
                        'icon' => 'i-sitemap',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'Business Glossary',
                        'icon' => 'i-book',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'Model Versions',
                        'icon' => 'i-git-commit',
                        'policy' => 'app-admin',
                    ],
                ],
            ],
            [
                'label' => 'Semantic Intelligence',
                'icon' => 'i-cpu',
                'policy' => 'app-admin',
                'children' => [
                    [
                        'label' => 'Semantic Models',
                        'icon' => 'i-cpu',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'Measures',
                        'icon' => 'i-ruler',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'KPIs',
                        'icon' => 'i-target',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'Relationships',
                        'icon' => 'i-git-merge',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'Synonyms',
                        'icon' => 'i-languages',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'Business Definitions',
                        'icon' => 'i-book',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'Security',
                        'icon' => 'i-shield',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'Direct Lake',
                        'icon' => 'i-waves',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'Validation',
                        'icon' => 'i-file-check',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'Certification',
                        'icon' => 'i-badge-check',
                        'policy' => 'app-admin',
                    ],
                ],
            ],
            [
                'label' => 'AI & Agents',
                'icon' => 'i-sparkles',
                'policy' => 'app-admin',
                'children' => [
                    [
                        'label' => 'AI Readiness',
                        'icon' => 'i-check-circle',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'Approved Data for AI',
                        'icon' => 'i-check-circle',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'Business Instructions',
                        'icon' => 'i-file-text',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'Verified Answers',
                        'icon' => 'i-badge-check',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'Ground Truth',
                        'icon' => 'i-anchor',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'Fabric Data Agents',
                        'icon' => 'i-bot',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'Conversational Apps',
                        'icon' => 'i-app-window',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'Agent Orchestration',
                        'icon' => 'i-workflow',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'AI Validation Centre',
                        'icon' => 'i-file-check',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'Technology Decisions',
                        'icon' => 'i-gavel',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'AI Governance',
                        'icon' => 'i-shield',
                        'policy' => 'app-admin',
                    ],
                ],
            ],
            [
                'label' => 'Deployment',
                'icon' => 'i-package',
                'policy' => 'app-admin',
                'children' => [
                    [
                        'label' => 'DEV',
                        'icon' => 'i-code',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'TEST',
                        'icon' => 'i-flask',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'PROD',
                        'icon' => 'i-rocket',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'Deployment Pipeline',
                        'icon' => 'i-git-branch',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'Release Validation',
                        'icon' => 'i-file-check',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'History',
                        'icon' => 'i-clock',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'Rollback Evidence',
                        'icon' => 'i-undo',
                        'policy' => 'app-admin',
                    ],
                ],
            ],
            [
                'label' => 'Monitoring',
                'icon' => 'i-activity',
                'policy' => 'app-admin',
                'children' => [
                    [
                        'label' => 'Application Health',
                        'icon' => 'i-heart-pulse',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'Fabric Health',
                        'icon' => 'i-heart-pulse',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'Pipeline Health',
                        'icon' => 'i-git-branch',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'Capacity',
                        'icon' => 'i-server',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'Data Quality',
                        'icon' => 'i-clipboard-check',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'Semantic Health',
                        'icon' => 'i-cpu',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'AI Quality',
                        'icon' => 'i-sparkles',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'Security Alerts',
                        'icon' => 'i-shield',
                        'policy' => 'app-admin',
                    ],
                    [
                        'label' => 'Usage & Adoption',
                        'icon' => 'i-bar-chart',
                        'policy' => 'app-admin',
                    ],
                ],
            ],
        ],

        'System Administration' => [
            [
                'label' => 'Platform Overview',
                'icon' => 'i-list-check',
                'route' => 'admin.overview',
                'policy' => 'system-admin',
                'children' => [
                    [
                        'label' => 'Setup Progress',
                        'icon' => 'i-list-check',
                        'policy' => 'system-admin',
                    ],
                    [
                        'label' => 'Environment Health',
                        'icon' => 'i-heart-pulse',
                        'policy' => 'system-admin',
                    ],
                    [
                        'label' => 'Data Health',
                        'icon' => 'i-clipboard-check',
                        'policy' => 'system-admin',
                    ],
                    [
                        'label' => 'Intelligence Health',
                        'icon' => 'i-sparkles',
                        'policy' => 'system-admin',
                    ],
                    [
                        'label' => 'Security & Sovereignty Status',
                        'icon' => 'i-shield',
                        'policy' => 'system-admin',
                    ],
                    [
                        'label' => 'Pending Actions',
                        'icon' => 'i-hourglass',
                        'policy' => 'system-admin',
                    ],
                    [
                        'label' => 'Failed Automations',
                        'icon' => 'i-x-circle',
                        'policy' => 'system-admin',
                    ],
                    [
                        'label' => 'Recent Changes',
                        'icon' => 'i-clock',
                        'policy' => 'system-admin',
                    ],
                ],
            ],
            [
                'label' => 'Fabric Environment',
                'icon' => 'i-plug',
                'policy' => 'system-admin',
                'children' => [
                    [
                        'label' => 'Tenant Connection',
                        'icon' => 'i-plug',
                        'policy' => 'system-admin',
                    ],
                    [
                        'label' => 'SSO & Entra Configuration',
                        'icon' => 'i-fingerprint',
                        'policy' => 'system-admin',
                    ],
                    [
                        'label' => 'Fabric Readiness',
                        'icon' => 'i-check-circle',
                        'policy' => 'system-admin',
                    ],
                    [
                        'label' => 'Capacity',
                        'icon' => 'i-server',
                        'policy' => 'system-admin',
                    ],
                    [
                        'label' => 'Tenant Settings',
                        'icon' => 'i-sliders',
                        'policy' => 'system-admin',
                    ],
                    [
                        'label' => 'Workspaces',
                        'icon' => 'i-folder',
                        'policy' => 'system-admin',
                    ],
                    [
                        'label' => 'Network & Private Connectivity',
                        'icon' => 'i-network',
                        'policy' => 'system-admin',
                    ],
                    [
                        'label' => 'Gateways',
                        'icon' => 'i-network',
                        'policy' => 'system-admin',
                    ],
                    [
                        'label' => 'API Permissions',
                        'icon' => 'i-key',
                        'policy' => 'system-admin',
                    ],
                    [
                        'label' => 'Environment Validation',
                        'icon' => 'i-file-check',
                        'policy' => 'system-admin',
                    ],
                ],
            ],
            [
                'label' => 'System Configuration',
                'icon' => 'i-sliders',
                'policy' => 'system-admin',
                'children' => [
                    [
                        'label' => 'General Settings',
                        'icon' => 'i-sliders',
                        'policy' => 'system-admin',
                    ],
                    [
                        'label' => 'Environment Settings',
                        'icon' => 'i-sliders',
                        'policy' => 'system-admin',
                    ],
                    [
                        'label' => 'Feature Flags',
                        'icon' => 'i-flag',
                        'policy' => 'system-admin',
                    ],
                    [
                        'label' => 'Integrations',
                        'icon' => 'i-plug',
                        'policy' => 'system-admin',
                    ],
                    [
                        'label' => 'Secret References',
                        'icon' => 'i-lock',
                        'policy' => 'system-admin',
                    ],
                    [
                        'label' => 'API Registry',
                        'icon' => 'i-code',
                        'policy' => 'system-admin',
                    ],
                    [
                        'label' => 'Background Jobs',
                        'icon' => 'i-cpu',
                        'policy' => 'system-admin',
                    ],
                    [
                        'label' => 'Scheduler',
                        'icon' => 'i-calendar',
                        'policy' => 'system-admin',
                    ],
                    [
                        'label' => 'Context Registers',
                        'icon' => 'i-book',
                        'policy' => 'system-admin',
                    ],
                    [
                        'label' => 'Diagnostics',
                        'icon' => 'i-heart-pulse',
                        'policy' => 'system-admin',
                    ],
                ],
            ],
        ],

    ],

];
