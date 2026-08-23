<?php

declare(strict_types=1);

namespace App\Support;

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
     * An unknown policy denies. A node naming a policy that does not exist is a
     * mistake, and the safe reading of a mistake is no access: the alternative
     * turns a typo into a grant nobody notices until it matters.
     */
    public function allows(?User $user, ?string $policy): bool
    {
        if ($user === null) {
            return false;
        }

        $minimum = config('navigation.policies.'.$policy);

        if (! $minimum instanceof Role) {
            return false;
        }

        return $user->hasAtLeast($minimum);
    }

    /**
     * The breadcrumb trail down to the active route.
     *
     * The trail is the way back, so it carries the full path from the cluster
     * down rather than a separate back link. Only the leaf is a destination in
     * its own right; a group above it is a label, since a group has no page.
     *
     * @return list<array{label: string, route: string|null, cluster: bool}>
     */
    public function trailFor(?User $user, string $routeName): array
    {
        foreach ($this->for($user) as $cluster => $nodes) {
            $found = $this->descend($nodes, $routeName);

            if ($found !== null) {
                return array_merge(
                    [['label' => $cluster, 'route' => null, 'cluster' => true]],
                    $found,
                );
            }
        }

        return [];
    }

    /**
     * Depth-first search for the node owning a route, collecting the path to it.
     *
     * @param  list<array<string, mixed>>  $nodes
     * @return list<array{label: string, route: string|null, cluster: bool}>|null
     */
    private function descend(array $nodes, string $routeName): ?array
    {
        foreach ($nodes as $node) {
            if (($node['route'] ?? null) === $routeName) {
                return [['label' => $node['label'], 'route' => $node['route'], 'cluster' => false]];
            }

            $children = $node['children'] ?? null;

            if (is_array($children)) {
                $deeper = $this->descend($children, $routeName);

                if ($deeper !== null) {
                    return array_merge(
                        [['label' => $node['label'], 'route' => null, 'cluster' => false]],
                        $deeper,
                    );
                }
            }
        }

        return null;
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

                // A group whose children were all filtered away goes with them.
                if ($children === []) {
                    continue;
                }

                $node['children'] = $children;
            }

            $kept[] = $node;
        }

        return $kept;
    }
}
