<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\BusinessDomain;
use App\Enums\Role;
use App\Models\User;

/**
 * Builds the sidebar for one person, and answers whether they may reach a node.
 *
 * The rule the template calls "filter, do not fork": the tree is authored once
 * and rendered once, with everything the viewer cannot reach removed. There is
 * never a second tree for a second role. Per-role menus drift the moment someone
 * edits one and forgets the other, and the drift shows up as a person seeing a
 * link that 403s - or worse, not seeing one they should.
 *
 * Three removals happen, in this order:
 *
 *  1. A node the viewer's tier cannot reach is ABSENT. Not dimmed, not hidden
 *     but present in the markup: absent. A dimmed link still tells them the
 *     feature exists and who it belongs to.
 *  2. A group left with no visible children disappears with them, because a
 *     group header that opens onto nothing is worse than no header.
 *  3. A cluster left with no visible nodes is not rendered at all.
 *
 * The opposite case is a node that exists but is not built yet: it stays
 * visible, disabled, with a "Soon" pill, so the shape of the product is legible.
 */
class Navigation
{
    /**
     * The clusters this person can see, in the fixed order the config declares.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function for(?User $user): array
    {
        $visible = [];

        foreach ((array) config('navigation.clusters', []) as $cluster => $nodes) {
            $nodes = $this->filter($nodes, $user);

            if ($nodes !== []) {
                $visible[$cluster] = $nodes;
            }
        }

        return $visible;
    }

    /**
     * Whether this person may use a node tagged with the given policy.
     *
     * Three things can be required, and ALL of them must hold:
     *
     *  1. A minimum tier.
     *  2. Or, where the policy says so, the Auditor capability instead. An
     *     auditor reads compliance evidence without holding the tier that
     *     normally comes with it, which is the whole reason it is a flag
     *     rather than a rung.
     *  3. A business-domain entitlement, when the policy names a domain.
     *     ROLE_MODEL.md section 1: a role alone never grants business data, so
     *     a System Administrator sees no Sales figures without being entitled
     *     to Sales - being the highest tier does not help.
     *
     * An unknown policy denies. A node naming a policy that does not exist is a
     * mistake, and the safe reading of a mistake is no access: the alternative
     * turns a typo into a grant nobody notices until it matters.
     */
    public function allows(?User $user, ?string $policy): bool
    {
        if ($user === null || $policy === null) {
            return false;
        }

        $rule = config('navigation.policies.'.$policy);

        if (! is_array($rule) || ! ($rule['min'] ?? null) instanceof Role) {
            return false;
        }

        $byTier = $user->hasAtLeast($rule['min']);
        $byAudit = ($rule['or_auditor'] ?? false) === true && $user->is_auditor;

        if (! $byTier && ! $byAudit) {
            return false;
        }

        $domain = $rule['domain'] ?? null;

        if ($domain instanceof BusinessDomain && ! $user->isEntitledTo($domain)) {
            return false;
        }

        return true;
    }

    /**
     * The breadcrumb trail down to the active route.
     *
     * The trail is the way back, so it carries the full path from the cluster
     * down rather than a separate back link. Only the leaf is a destination in
     * its own right; a group above it is a label, since a group has no page.
     *
     * `$parameters` disambiguates two nodes sharing one route name. General
     * Settings and Environment Settings are both `admin.system.settings` and
     * differ only by their `category`, so matching on the name alone would put
     * whichever came first in the config at the end of both trails.
     *
     * @param  array<string, scalar>  $parameters
     * @return list<array{label: string, route: string|null, parameters: array<string, scalar>, cluster: bool}>
     */
    public function trailFor(?User $user, string $routeName, array $parameters = []): array
    {
        foreach ($this->for($user) as $cluster => $nodes) {
            $found = $this->descend($nodes, $routeName, $parameters);

            if ($found !== null) {
                return array_merge(
                    [['label' => $cluster, 'route' => null, 'parameters' => [], 'cluster' => true]],
                    $found,
                );
            }
        }

        return [];
    }

    /**
     * Depth-first search for the node owning a route, collecting the path to it.
     *
     * A node matches when its route name matches AND every route parameter it
     * declares matches the current request's. A node declaring no parameters
     * matches on the name alone, which keeps every existing single-route node
     * behaving exactly as before.
     *
     * @param  list<array<string, mixed>>  $nodes
     * @param  array<string, scalar>  $parameters
     * @return list<array{label: string, route: string|null, parameters: array<string, scalar>, cluster: bool}>|null
     */
    private function descend(array $nodes, string $routeName, array $parameters): ?array
    {
        foreach ($nodes as $node) {
            if ($this->matches($node, $routeName, $parameters)) {
                return [[
                    'label' => $node['label'],
                    'route' => $node['route'],
                    'parameters' => (array) ($node['route_parameters'] ?? []),
                    'cluster' => false,
                ]];
            }

            $children = $node['children'] ?? null;

            if (is_array($children)) {
                $deeper = $this->descend($children, $routeName, $parameters);

                if ($deeper !== null) {
                    return array_merge(
                        [['label' => $node['label'], 'route' => null, 'parameters' => [], 'cluster' => false]],
                        $deeper,
                    );
                }
            }
        }

        return null;
    }

    /**
     * Whether a node is the one currently being viewed.
     *
     * @param  array<string, mixed>  $node
     * @param  array<string, scalar>  $parameters
     */
    public function matches(array $node, string $routeName, array $parameters): bool
    {
        if (($node['route'] ?? null) !== $routeName) {
            return false;
        }

        foreach ((array) ($node['route_parameters'] ?? []) as $key => $value) {
            if (($parameters[$key] ?? null) !== $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * Remove what this person cannot reach, and any container left empty.
     *
     * @param  list<array<string, mixed>>  $nodes
     * @return list<array<string, mixed>>
     */
    private function filter(array $nodes, ?User $user): array
    {
        $kept = [];

        foreach ($nodes as $node) {
            if (! $this->allows($user, $node['policy'] ?? null)) {
                continue;
            }

            $children = $node['children'] ?? null;

            if (is_array($children)) {
                $children = $this->filter($children, $user);

                if ($children === []) {
                    /*
                     * A pure group whose children were all filtered away goes
                     * with them: a header opening onto nothing is worse than no
                     * header.
                     *
                     * A node that is ALSO a page is different. My Intelligence
                     * is the case: someone entitled to no domains loses every
                     * child, but the page itself is exactly where they are told
                     * that domains are granted separately. Dropping it would
                     * delete the one screen that explains the empty rail. It
                     * degrades to a leaf instead.
                     */
                    if (($node['route'] ?? null) === null) {
                        continue;
                    }

                    unset($node['children']);
                } else {
                    $node['children'] = $children;
                }
            }

            $kept[] = $node;
        }

        return $kept;
    }
}
