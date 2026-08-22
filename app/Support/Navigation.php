<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\Role;
use App\Models\User;

/**
 * Builds the sidebar for one person from the single navigation source of truth.
 *
 * The shell contract's first rule is "filter, do not fork": the tree is
 * declared once and the nodes a person's role cannot reach are dropped, rather
 * than a separate menu being maintained per role. A group disappears when all
 * its children are filtered out, and a cluster disappears when it has no
 * visible nodes left.
 *
 * The same gate answers whether a route may be entered, so a node can never be
 * visible in the sidebar but refused at the handler, or vice versa.
 */
class Navigation
{
    /**
     * The visible clusters for a person, in the fixed cluster order.
     *
     * @return list<array{cluster: string, nodes: list<array<string, mixed>>}>
     */
    public function for(User $user, ?string $activeRoute = null): array
    {
        $visible = [];

        foreach (config('navigation.clusters', []) as $cluster) {
            $nodes = $this->filterNodes($cluster['nodes'] ?? [], $user, $activeRoute);

            // A cluster with nothing left in it is not rendered at all.
            if ($nodes !== []) {
                $visible[] = ['cluster' => $cluster['cluster'], 'nodes' => $nodes];
            }
        }

        return $visible;
    }

    /**
     * Whether a person satisfies a named access policy.
     *
     * An unknown policy denies. Failing closed matters here: a typo in the
     * navigation config should hide a node, never expose one.
     */
    public function allows(User $user, string $policy): bool
    {
        $minimum = config("navigation.policies.{$policy}");

        if (! $minimum instanceof Role) {
            return false;
        }

        return $user->hasAtLeast($minimum);
    }

    /**
     * The breadcrumb trail for a route, from its cluster down to the leaf.
     *
     * The trail is the way back, so it carries every step rather than only the
     * immediate parent.
     *
     * @return list<string>
     */
    public function trailFor(string $route): array
    {
        foreach (config('navigation.clusters', []) as $cluster) {
            $found = $this->findTrail($cluster['nodes'] ?? [], $route, [$cluster['cluster']]);

            if ($found !== null) {
                return $found;
            }
        }

        return [];
    }

    /**
     * Drop what this person cannot reach, and mark what is built and active.
     *
     * @param  list<array<string, mixed>>  $nodes
     * @return list<array<string, mixed>>
     */
    private function filterNodes(array $nodes, User $user, ?string $activeRoute): array
    {
        $visible = [];

        foreach ($nodes as $node) {
            if (! $this->allows($user, (string) ($node['access'] ?? ''))) {
                continue;
            }

            if (isset($node['children'])) {
                $children = $this->filterNodes($node['children'], $user, $activeRoute);

                // An empty group is noise, so it goes with its children.
                if ($children === []) {
                    continue;
                }

                $node['children'] = $children;
                $node['is_group'] = true;

                /*
                 * A group holding the active route opens on arrival and takes
                 * the active-trail tint, so the person can see where they are
                 * without expanding anything by hand.
                 */
                $node['in_active_trail'] = $this->containsActive($children);
                $visible[] = $node;

                continue;
            }

            $node['is_group'] = false;

            /*
             * A leaf with no route is a destination that is not built yet. It
             * stays visible, disabled, with a "Soon" indicator, per rule 5.
             */
            $node['is_built'] = isset($node['route']);
            $node['is_active'] = $node['is_built'] && $node['route'] === $activeRoute;

            $visible[] = $node;
        }

        return $visible;
    }

    /**
     * Whether any node in this branch is the active one.
     *
     * @param  list<array<string, mixed>>  $nodes
     */
    private function containsActive(array $nodes): bool
    {
        foreach ($nodes as $node) {
            if (($node['is_active'] ?? false) === true) {
                return true;
            }

            if (($node['in_active_trail'] ?? false) === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * Walk the declared tree looking for a route, accumulating the labels.
     *
     * @param  list<array<string, mixed>>  $nodes
     * @param  list<string>  $trail
     * @return list<string>|null
     */
    private function findTrail(array $nodes, string $route, array $trail): ?array
    {
        foreach ($nodes as $node) {
            if (isset($node['children'])) {
                $found = $this->findTrail(
                    $node['children'],
                    $route,
                    [...$trail, (string) $node['group']]
                );

                if ($found !== null) {
                    return $found;
                }

                continue;
            }

            if (($node['route'] ?? null) === $route) {
                return [...$trail, (string) $node['label']];
            }
        }

        return null;
    }
}
