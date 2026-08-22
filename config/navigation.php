<?php

declare(strict_types=1);

use App\Enums\Role;

/*
|--------------------------------------------------------------------------
| Navigation And Access
|--------------------------------------------------------------------------
|
| The confirmed navigation tree from doc/06-App-Definition.md section 4, and
| the access policies that gate it. This file is the single source of truth:
| the sidebar, the breadcrumb and the route guards all read it, so a node
| cannot be visible in one and unreachable in another.
|
| The four clusters are a closed set and are never added to, renamed or
| reordered. A cluster is a heading, not a level.
|
| Node shape:
|   label   the visible text, and what the nav filter matches
|   icon    a symbol id from the sprite in resources/views/components/icons
|   access  a policy key from the 'policies' map below
|   route   a named route; its absence means the destination is not built yet
|   children  present on a group, absent on a leaf
|
| Rule 5 of the shell contract: an unbuilt destination stays visible, renders
| disabled with a "Soon" indicator, and is never a link. It therefore needs no
| route and no controller. Anything the user's role cannot reach is absent
| entirely rather than dimmed, which is a different thing and deliberate: a
| disabled control says "not yet", an absent one says nothing at all.
|
*/

return [

    /*
     * Who satisfies each policy. The tiers are cumulative, so these are
     * expressed as a minimum rather than as a list to keep in step with
     * App\Enums\Role.
     */
    'policies' => [
        'workspace' => Role::Viewer,      // every authenticated person
        'compliance' => Role::Team,       // Collaborator and above
        'app_admin' => Role::Admin,       // Administrator and above
        'sys_admin' => Role::SystemAdmin, // Platform Administrator alone
    ],

    'clusters' => [

        [
            'cluster' => 'Workspace',
            'nodes' => [
                ['label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'i-grid', 'access' => 'workspace'],

                [
                    'group' => 'Projects',
                    'icon' => 'i-folder',
                    'access' => 'workspace',
                    'children' => [
                        ['label' => 'All Projects', 'icon' => 'i-list', 'access' => 'workspace'],
                        ['label' => 'New Project', 'icon' => 'i-plus', 'access' => 'workspace'],
                        ['label' => 'Blueprints', 'icon' => 'i-map', 'access' => 'workspace'],
                    ],
                ],

                [
                    'group' => 'Data Platform',
                    'icon' => 'i-database',
                    'access' => 'workspace',
                    'children' => [
                        [
                            'group' => 'Environments',
                            'icon' => 'i-server',
                            'access' => 'workspace',
                            'children' => [
                                ['label' => 'Workspaces', 'icon' => 'i-layers', 'access' => 'workspace'],
                                ['label' => 'Capacities', 'icon' => 'i-gauge', 'access' => 'workspace'],
                            ],
                        ],
                        [
                            'group' => 'Sources',
                            'icon' => 'i-plug',
                            'access' => 'workspace',
                            'children' => [
                                ['label' => 'Source Register', 'icon' => 'i-list', 'access' => 'workspace'],
                                ['label' => 'Connections', 'icon' => 'i-link', 'access' => 'workspace'],
                                ['label' => 'Gateways', 'icon' => 'i-shield', 'access' => 'workspace'],
                            ],
                        ],
                        [
                            'group' => 'Lakehouse',
                            'icon' => 'i-cube',
                            'access' => 'workspace',
                            'children' => [
                                ['label' => 'Lakehouses', 'icon' => 'i-cube', 'access' => 'workspace'],
                                ['label' => 'Medallion Layers', 'icon' => 'i-stack', 'access' => 'workspace'],
                                ['label' => 'Warehouses', 'icon' => 'i-box', 'access' => 'workspace'],
                            ],
                        ],
                        [
                            'group' => 'Ingestion',
                            'icon' => 'i-download',
                            'access' => 'workspace',
                            'children' => [
                                ['label' => 'Pipelines', 'icon' => 'i-flow', 'access' => 'workspace'],
                                ['label' => 'Pipeline Runs', 'icon' => 'i-history', 'access' => 'workspace'],
                            ],
                        ],
                        [
                            'group' => 'Transformation',
                            'icon' => 'i-wand',
                            'access' => 'workspace',
                            'children' => [
                                ['label' => 'Transformations', 'icon' => 'i-wand', 'access' => 'workspace'],
                                ['label' => 'Quality Rules', 'icon' => 'i-check', 'access' => 'workspace'],
                                ['label' => 'Quality Results', 'icon' => 'i-report', 'access' => 'workspace'],
                            ],
                        ],
                    ],
                ],

                [
                    'group' => 'Semantics and AI',
                    'icon' => 'i-sparkles',
                    'access' => 'workspace',
                    'children' => [
                        [
                            'group' => 'Modelling',
                            'icon' => 'i-share',
                            'access' => 'workspace',
                            'children' => [
                                ['label' => 'Semantic Models', 'icon' => 'i-share', 'access' => 'workspace'],
                                ['label' => 'Measures', 'icon' => 'i-sigma', 'access' => 'workspace'],
                                ['label' => 'Row and Column Security', 'icon' => 'i-lock', 'access' => 'workspace'],
                            ],
                        ],
                        [
                            'group' => 'Glossary',
                            'icon' => 'i-book',
                            'access' => 'workspace',
                            'children' => [
                                ['label' => 'Terms', 'icon' => 'i-book', 'access' => 'workspace'],
                                ['label' => 'AI Instructions', 'icon' => 'i-note', 'access' => 'workspace'],
                                ['label' => 'Verified Answers', 'icon' => 'i-check', 'access' => 'workspace'],
                            ],
                        ],
                        [
                            'group' => 'Agents',
                            'icon' => 'i-robot',
                            'access' => 'workspace',
                            'children' => [
                                ['label' => 'Data Agents', 'icon' => 'i-robot', 'access' => 'workspace'],
                                ['label' => 'Examples', 'icon' => 'i-quote', 'access' => 'workspace'],
                            ],
                        ],
                        [
                            'group' => 'Evaluation',
                            'icon' => 'i-target',
                            'access' => 'workspace',
                            'children' => [
                                ['label' => 'Ground Truth', 'icon' => 'i-target', 'access' => 'workspace'],
                                ['label' => 'Test Runs', 'icon' => 'i-play', 'access' => 'workspace'],
                                ['label' => 'Accuracy Results', 'icon' => 'i-report', 'access' => 'workspace'],
                            ],
                        ],
                    ],
                ],

                [
                    'group' => 'Delivery',
                    'icon' => 'i-rocket',
                    'access' => 'workspace',
                    'children' => [
                        ['label' => 'Access Grants', 'icon' => 'i-key', 'access' => 'workspace'],
                        ['label' => 'Conversational Apps', 'icon' => 'i-chat', 'access' => 'workspace'],
                        ['label' => 'Channels', 'icon' => 'i-signal', 'access' => 'workspace'],
                    ],
                ],

                [
                    'group' => 'Observability',
                    'icon' => 'i-activity',
                    'access' => 'workspace',
                    'children' => [
                        ['label' => 'Capacity Metrics', 'icon' => 'i-gauge', 'access' => 'workspace'],
                        ['label' => 'Conversation Quality', 'icon' => 'i-chat', 'access' => 'workspace'],
                    ],
                ],
            ],
        ],

        [
            'cluster' => 'Compliance',
            'nodes' => [
                ['label' => 'Audit Log', 'icon' => 'i-clipboard', 'access' => 'compliance'],
                ['label' => 'Sensitivity Labels', 'icon' => 'i-tag', 'access' => 'compliance'],
                ['label' => 'Lineage Register', 'icon' => 'i-share', 'access' => 'compliance'],
                ['label' => 'Exceptions', 'icon' => 'i-alert', 'access' => 'compliance'],
                [
                    'group' => 'Change Control',
                    'icon' => 'i-git-branch',
                    'access' => 'compliance',
                    'children' => [
                        ['label' => 'Change Requests', 'icon' => 'i-git-branch', 'access' => 'compliance'],
                        ['label' => 'Sign-offs', 'icon' => 'i-check', 'access' => 'compliance'],
                        ['label' => 'Go-Live Gate', 'icon' => 'i-flag', 'access' => 'compliance'],
                    ],
                ],
            ],
        ],

        [
            'cluster' => 'Application Administration',
            'nodes' => [
                [
                    'group' => 'Access Control',
                    'icon' => 'i-shield-check',
                    'access' => 'app_admin',
                    'children' => [
                        ['label' => 'Users', 'icon' => 'i-users', 'access' => 'app_admin'],
                        ['label' => 'Roles', 'icon' => 'i-shield-check', 'access' => 'app_admin'],
                    ],
                ],
                ['label' => 'Recycle Bin', 'icon' => 'i-trash', 'access' => 'app_admin'],
                ['label' => 'Notifications', 'icon' => 'i-bell', 'access' => 'app_admin'],
            ],
        ],

        [
            'cluster' => 'System Administration',
            'nodes' => [
                [
                    'group' => 'Integrations',
                    'icon' => 'i-plug',
                    'access' => 'sys_admin',
                    'children' => [
                        ['label' => 'Entra Connections', 'icon' => 'i-key', 'access' => 'sys_admin'],
                        ['label' => 'Fabric Capacities', 'icon' => 'i-gauge', 'access' => 'sys_admin'],
                        ['label' => 'Git Bindings', 'icon' => 'i-git-branch', 'access' => 'sys_admin'],
                    ],
                ],
                [
                    'group' => 'Settings',
                    'icon' => 'i-cog',
                    'access' => 'sys_admin',
                    'children' => [
                        ['label' => 'General', 'icon' => 'i-adjustments', 'access' => 'sys_admin'],
                    ],
                ],
            ],
        ],
    ],
];
