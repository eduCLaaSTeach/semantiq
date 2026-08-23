<?php

declare(strict_types=1);

use App\Enums\Role;

return [

    /*
    |---------------------------------------------------------------------------
    | Access policies
    |---------------------------------------------------------------------------
    |
    | A named rule listing the minimum tier a node tagged with it requires.
    | Nodes name a policy rather than a role, so widening or narrowing access is
    | one edit here instead of a search through the tree.
    |
    | An unknown policy DENIES. A typo in a policy name must never become an
    | accidental grant.
    |
    */

    'policies' => [
        'workspace' => Role::Viewer,
        'compliance' => Role::Collaborator,
        'app-admin' => Role::Admin,
        'system-admin' => Role::SystemAdmin,
    ],

    /*
    |---------------------------------------------------------------------------
    | The navigation tree
    |---------------------------------------------------------------------------
    |
    | The four clusters are a closed, fixed-order set and are never renamed,
    | reordered or added to. A cluster with no visible nodes is not rendered, so
    | the ones this application does not yet use simply do not appear.
    |
    | THIS TREE IS DELIBERATELY SMALL. The template is explicit that the
    | navigation tree is asked and never invented, and nothing has been confirmed
    | for this application yet. What is here is only what genuinely exists or is
    | unambiguously next; the rest is absent rather than guessed at. The shell
    | itself supports groups nested three deep, flyouts and the filter - that
    | machinery is exercised by tests against a fixture tree, not by inventing
    | menu entries nobody asked for.
    |
    | Node shape:
    |   label   - what a person reads
    |   icon    - a symbol id from the one registry; mandatory on every node
    |   route   - a named route. A node WITHOUT one renders disabled with a
    |             "Soon" pill and is given no link at all.
    |   policy  - the access policy gating it
    |   children- present on a group, absent on a leaf
    |
    */

    'clusters' => [

        'Workspace' => [
            [
                'label' => 'Dashboard',
                'icon' => 'i-grid',
                'route' => 'dashboard',
                'policy' => 'workspace',
            ],
        ],

        'Compliance' => [
            [
                'label' => 'Audit log',
                'icon' => 'i-list-check',
                'policy' => 'compliance',
            ],
        ],

        'Application Administration' => [
            [
                'label' => 'Users',
                'icon' => 'i-users',
                'policy' => 'app-admin',
            ],
        ],

        'System Administration' => [
            [
                'label' => 'Sign-in methods',
                'icon' => 'i-key',
                'policy' => 'system-admin',
            ],
        ],

    ],

];
