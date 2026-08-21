# UI and UX Layout Template

A working blueprint for generating a role-aware, sidebar-driven application UI in the approved
CLaaS2SaaS design system: its shell, navigation, tokens, page archetypes, component contracts, and
role/access model. Fill in the App Definition Block (§1), then generate the screens in your stack, or
run a mockup batch (see Mockup Mode).

The design system is approved and fixed. Tokens, palette, per-theme surfaces, fonts, logos,
favicons, icon style, shell architecture, archetypes, and status-role meanings never vary per app
and are never asked about. Only the App Definition varies: app name, browser title-bar name,
navigation tree, brand-assets destination path, UI stack, entities, roles, feature toggles, and
domain copy.

This file is self-contained. Everything needed to build or mock up a screen is in it, and the brand
asset pack ships in `assets/` next to it.

## Table Of Contents

0. [How To Use This File](#0--how-to-use-this-file)
1. [App Definition Block](#1--app-definition-block)
2. [Information Architecture](#2--information-architecture)
3. [Application Shell](#3--application-shell)
4. [Design System And Tokens](#4--design-system-and-tokens)
5. [Page Archetypes](#5--page-archetypes)
6. [Sub-Navigation Patterns](#6--sub-navigation-patterns)
7. [Role And Access Model](#7--role-and-access-model)
8. [Component And Interaction Contracts](#8--component-and-interaction-contracts)
9. [Conventions And Guardrails](#9--conventions-and-guardrails)
10. [Generation Checklist](#10--generation-checklist)
11. [Appendix](#11--appendix)

Plus [Mockup Mode](#mockup-mode), for generating a throwaway HTML mockup of a screen. That section is
a workflow, not a design rule.

## 0 · How To Use This File

When asked to scaffold or mock up a UI:

1. Read the App Definition (§1), or ask for the missing values. The only things ever asked are the
   app-specific ones. Never ask about, offer, or vary the theme, colors, fonts, logos, or favicon.
2. Confirm the UI stack. This file names no language, framework, view layer, CSS system, or
   component library. Everything generated is written in the confirmed stack. For a mockup with no
   declared stack, the output is a single self-contained HTML file (§ Mockup Mode).
3. Ask where the brand asset files go inside the project (`<BRAND_ASSETS_PATH>`) and record it.
   Never choose that location. The developer may also copy the files manually and record the path.
4. Generate in this order:
   1. Navigation config and access policies (§2): the four fixed clusters, groups, leaves.
   2. Role and policy layer (§7): tiers, cluster grants, record policy, query scope, purge gate.
   3. The shell (§3): full-height rail, slim top bar over the canvas, role-filtered nav.
   4. Design tokens and primitives (§4), in both themes.
   5. One page per screen in the App Definition, from the matching archetype (§5).
5. Report what was generated: files touched, archetypes used, UI states covered (success, empty,
   loading, error, small screen), the accessibility check, and anything assumed.

### Placeholder Conventions

| Notation | Means | Example |
| --- | --- | --- |
| `<ANGLE_CAPS>` | a value from the App Definition | `<APP_NAME>`, `<BRAND_ASSETS_PATH>` |
| `dotted.path` | a reference to an App Definition field | `app.name`, `stack.ui_stack` |
| `[bracketed]` | an inline placeholder inside a diagram | `[app.name]`, `[chrome surface]` |
| PSEUDO-STRUCTURE | uppercase region names and indentation trees describing structure and behavior, naming no framework | `RAIL -> CLUSTER -> GROUP -> LEAF` |

The output is structure that is wired and navigable: layouts, nav and access config, the token
file, the policy layer, and empty-but-wired screens in the confirmed stack. Business logic and
real data come after. Translate any pseudo-structure into the project's view layer; never paste it
verbatim.

## 1 · App Definition Block

This is the only part a project fills in. Record the confirmed values wherever the project keeps its
durable settings, and keep this block as the shape.

```yaml
# ---------------------------------------------------------------------------
# APPROVED, NEVER ASKED, NEVER VARIED - shown here for reference only.
# Full values in §4; the image files ship in assets/ next to this file.
# Do not put any of these in a question to the developer.
# ---------------------------------------------------------------------------
approved:
  company_name:   CLaaS2SaaS
  logos:          logo-full-light.png / logo-full-dark.png (expanded rail)
                  logo-short-light.png / logo-short-dark.png (collapsed rail)
  favicons:       favicon-light.ico / favicon-dark.ico
  palette:        Midnight Blue #193E6B · Green Gold #B3A125 · Avocado Green #5F8025
                  Sunray #E9AC53 · Violet-Red #991547 · Jelly Bean Blue #448E9D
                  Cadmium Violet #7F3F98 · Platinum Beige + White neutrals
  fonts:          Montserrat headings · Source Sans 3 body
  icon_style:     one central inline-SVG registry, 24px viewBox, 2px stroke,
                  round caps and joins, outline; symbol ids i-<concept>
  density:        compact
  accessibility:  WCAG AA
  themes:         light and dark, both fully defined; System / Dark / Light switcher
  clusters:       Workspace · Compliance · Application Administration · System Administration
                  (fixed order, closed set, omit the ones the app does not need)

# ---------------------------------------------------------------------------
# STACK - all asked. This file names no technology.
# ---------------------------------------------------------------------------
stack:
  ui_stack:        <UI STACK>            # language, framework, view layer, CSS system   [ASK]
  charting:        <CHARTING LIBRARY>    # only if the app charts anything               [ASK]

# ---------------------------------------------------------------------------
# APP IDENTITY - asked, never invented.
# ---------------------------------------------------------------------------
app:
  name:            <APP_NAME>            # shown in the TOP BAR (the rail shows the logo) [ASK]
  title_bar:       <BROWSER_TAB_TITLE>   # the document <title>                           [ASK]
  tagline:         <SHORT_TAGLINE>       # login / empty-state strapline        [ASK · optional]
  brand_assets_path: <BRAND_ASSETS_PATH> # where the logo/favicon files live in the project.
                                         # ENFORCED: ask, never choose it.                [ASK]

# ---------------------------------------------------------------------------
# FEATURE TOGGLES - confirmed defaults.
# ---------------------------------------------------------------------------
features:
  theme_switcher:         true    # mandatory; System / Dark / Light in the Appearance section
  sidebar_nav_filter:     true
  sidebar_collapsible:    true
  customizable_dashboard: true
  notifications:          true    # in-app, top-bar bell
  sso:                    true    # external IdP; the provider and path are per app     [ASK]
  recycle_bin:            true    # soft-delete plus restore
  audit_log:              true
  ai_model_catalog:       false   # app calls an AI model at runtime (§5.10)            [ASK]
  # There is NO global-search toggle. The top bar carries no global search bar (§3).

# ---------------------------------------------------------------------------
# NAVIGATION TREE - asked, never invented.
#
# Standing navigation lives ONLY in the left sidebar, under the four fixed clusters.
# A cluster is a HEADING, not an accordion group, and does not count toward depth.
# Each cluster holds NODES:
#   a LEAF  -> has a route + an icon                 -> a direct link
#   a GROUP -> has children + an icon                -> an accordion sub-menu
# Groups nest at MOST 3 accordion levels within a cluster. A LEAF is not a level.
# A leaf under the level-3 group that itself needs children becomes a routable page
# whose children render as a horizontal in-canvas TAB STRIP (§6), never a 4th level.
# A level-3 group whose children are plain leaves stays an accordion; that is not the case.
# Every node carries a meaningful, mandatory icon and the access policy that gates it.
# Never name a group the same as its cluster. Omit a cluster the app does not need.
# The tree below is SHAPE ONLY. Confirm the real labels and structure.
# ---------------------------------------------------------------------------
navigation:
  - cluster: Workspace                       # the day-to-day working area
    nodes:
      - { label: Dashboard, route: dashboard, icon: i-grid, access: workspace }
      - group: <ENTITY_PL>
        icon: i-document
        access: workspace
        children:
          - { label: All <ENTITY_PL>, route: <entity>.index,  icon: i-list,  access: workspace }
          - { label: New <ENTITY>,    route: <entity>.create, icon: i-plus,  access: workspace }
          - { label: Recycle Bin,     route: <entity>.bin,    icon: i-trash, access: workspace }
  - cluster: Compliance                      # audit logs, activity trails, governance views
    nodes:
      - { label: Audit Log, route: audit.index, icon: i-clipboard, access: compliance }
  - cluster: Application Administration      # the app's own users, roles, app-level settings
    nodes:
      - group: Access Control                # never name a group after its cluster
        icon: i-shield-check
        access: app_admin
        children:
          - { label: Users, route: users.index, icon: i-users,        access: app_admin }
          - { label: Roles, route: roles.index, icon: i-shield-check, access: app_admin }
  - cluster: System Administration           # system config, integrations, platform settings
    nodes:
      - group: Settings
        icon: i-cog
        access: sys_admin
        children:
          - { label: General,      route: settings.index,        icon: i-adjustments, access: sys_admin }
          - { label: Integrations, route: settings.integrations, icon: i-plug,        access: sys_admin }

# ---------------------------------------------------------------------------
# ENTITIES - each becomes a CRUD set built from the §5 archetypes.
# ---------------------------------------------------------------------------
entities:
  - name:    <ENTITY>                       # singular
    plural:  <ENTITY_PL>
    screens: [list, detail, form]           # any of: dashboard, list, detail, form, settings,
                                            #         builder, recycle_bin, status
    facet_nav: tabs                         # a record's facets use the in-canvas tab strip (§6)
    owned:   true                           # has an owner for record scoping
    state_machine: <draft -> ... -> done | none>

# ---------------------------------------------------------------------------
# ROLES - the four-tier baseline (§7). Rename labels per app; keep the tier shapes.
# ---------------------------------------------------------------------------
roles:
  - { key: administrator, label: Administrator, tier: admin,     clusters: [Workspace, Compliance, Application Administration, System Administration] }
  - { key: collaborator,  label: Collaborator,  tier: team,      clusters: [Workspace, Compliance] }
  - { key: contributor,   label: Contributor,   tier: self,      clusters: [Workspace] }
  - { key: viewer,        label: Viewer,        tier: self_view, clusters: [Workspace] }
```

If the app name, title-bar name, navigation tree, brand-assets path, entities, roles, or UI stack
is missing, stop and ask before generating UI that displays or depends on it. Never invent,
generate, randomly pick, or silently default any of them.

## 2 · Information Architecture

Navigation is config-driven, not hand-coded per page. Standing navigation lives only in the left
sidebar, organized under the four fixed clusters, with accordion groups nested at most three levels
within a cluster. Navigation deeper than that, and a record's facets, move to a horizontal in-canvas
tab strip (§6).

| Concept | What it is | Lives in |
| --- | --- | --- |
| Cluster | One of four fixed headings (`Workspace`, `Compliance`, `Application Administration`, `System Administration`), rendered top-to-bottom in that order. A heading, not an accordion. | nav config |
| Leaf | A direct link (icon plus label) declaring the access policy that gates it. | nav config |
| Group | An accordion node (icon, label, chevron) revealing children, which may be leaves or nested groups, up to 3 levels within a cluster. | nav config |
| Tab strip | A horizontal in-canvas switch for navigation past the 3 accordion levels, or one record's facets. Route-backed and terminal. | page canvas |
| Access policy | A named rule listing which roles may use a node tagged with it. | access config |

### The Four Fixed Clusters

Each cluster has a defined home. Place every feature under the one cluster whose purpose it fits.

| Cluster | Holds |
| --- | --- |
| `Workspace` | The day-to-day working area: the app's primary work features |
| `Compliance` | Audit logs, activity trails, compliance and governance views |
| `Application Administration` | The application's own users, roles, and app-level settings |
| `System Administration` | System-level configuration, integrations, platform settings |

The four are a closed set. An app uses only the clusters it needs and omits the rest; an unused or
empty cluster is not rendered. Never add, invent, rename, or reorder a cluster.

### The Rules The Shell Follows

1. Filter, do not fork. Render the tree once for every authenticated user and drop the features the
   user's roles cannot access. A group disappears when all its children are filtered out; a cluster
   disappears when it has no visible features. Never maintain per-role duplicate menus.
2. Accordion groups expand and collapse in place. The group holding the active route auto-expands
   and shows the active-trail tint, and each group persists its open/closed state across navigation.
3. Bounded nesting, then tabs. Groups nest at most 3 levels within a cluster. A leaf under the
   level-3 group that itself needs children becomes a routable page whose children render as a
   horizontal in-canvas tab strip, never a fourth accordion level. Standing navigation never moves
   to the top bar, and tabs are terminal.
4. Every node carries a meaningful, mandatory icon from the approved registry.
5. Render only functional UI. An unbuilt navigation destination renders disabled with a "Soon"
   indicator and stays visible. Anything the user's roles cannot access is absent, never dimmed.

### Counting The Three Levels

Only accordion groups count. A cluster heading is not a level and a leaf is not a level.

```text
Cluster  (fixed heading - not a level, just a title)
└─ Group ............................. level 1   (accordion)
   └─ Group .......................... level 2   (accordion)
      └─ Group ....................... level 3   (accordion - the limit)
         ├─ Leaf                                 (link)
         ├─ Leaf  ->  tab-strip page             (link that needs its own sub-areas)
         └─ Leaf                                 (link)
```

A concrete illustration of the same structure. These names are an example only, never a default:

```text
System Administration     <- cluster heading - NOT a level (a fixed title)
  Platform                <- group · level 1
    Security              <- group · level 2
      Sign-in Methods     <- group · level 3   (the limit - deepest accordion)
        Password          <- leaf   (a leaf is NOT a level)
        SSO / SAML  >     <- leaf that needs sub-areas -> routable page with a TAB STRIP (§6)
        Passkeys          <- leaf
    Data Retention        <- leaf
```

That structure is the rule, not a menu. Build the real tree from the App Definition. Different
apps need different panels, most use only one or two levels, and none should adopt a shape by
default.

### Two Configs, One Source Of Truth

Implement these as two data structures in the confirmed stack. Keep them separate so the menu can be
reorganized without touching authorization, and the reverse.

- Navigation tree. Answers "what is in the sidebar and where". An ordered list of the clusters the
  app uses; each holds nodes; a node is a leaf (`label · route · match · icon · access`) or a group
  (`label · icon · access · children[]`). A node may be `disabled`, which renders a "Soon" row.
- Access policy map. Answers "who may use it". Each policy key maps to the roles that grant it, plus
  optional flags such as `admin_only`. Nodes reference policies by key.

```text
# PSEUDO-STRUCTURE - translate into the stack's config or data layer
NAV_TREE:
  Workspace  -> nodes:
      LEAF  "Dashboard"     route dashboard      icon i-grid      access workspace
      GROUP "<ENTITY_PL>"   icon i-document      access workspace
            LEAF "All <ENTITY_PL>"  route <entity>.index   icon i-list   access workspace
            LEAF "New <ENTITY>"     route <entity>.create  icon i-plus   access workspace
            LEAF "Recycle Bin"      route <entity>.bin     icon i-trash  access workspace
      LEAF  "Reports"  route (none)  icon i-chart-bar  access compliance  disabled -> "Soon"
  Compliance                 -> nodes: [ ... access = compliance ]
  Application Administration -> nodes: [ ... access = app_admin ]
  System Administration      -> nodes: [ ... access = sys_admin ]

ACCESS_POLICIES:
  workspace   -> roles: [administrator, collaborator, contributor, viewer]
  compliance  -> roles: [administrator, collaborator]
  app_admin   -> roles: [], admin_only: true
  sys_admin   -> roles: [], admin_only: true
```

### The Gate Engine

```text
# PSEUDO-CODE - names no framework; reimplement idiomatically
canAccess(user, policy):
    if user.isAdmin:            return true        # admins see everything
    if policy.admin_only:       return false       # admin-only excludes all others
    for role in policy.roles:   if user.hasRole(role): return true
    return false

canAccessKey(user, key):                            # gate controllers and handlers with this
    return canAccess(user, ACCESS_POLICIES[key] or {})

clustersFor(user):                                  # builds the filtered sidebar tree
    out = []
    for cluster in NAV_TREE:                        # in the fixed cluster order
        nodes = filterNodes(user, cluster.nodes)
        if nodes: out += { label: cluster.label, nodes }
    return out

# GROUP -> keep visible children, drop the group if none remain; LEAF -> keep if accessible
filterNodes(user, nodes):
    kept = []
    for n in nodes:
        if n.children:
            children = filterNodes(user, n.children)
            if children: kept += { group: n.label, icon: n.icon, children }
        else if canAccessKey(user, n.access):
            kept += n
    return kept
```

Nodes reference policies by key, so the menu and the authorization rules evolve independently. The
shell consumes `clustersFor(currentUser)`. `filterNodes` recurses through nested groups, but real
nesting stays capped at three levels within a cluster.

## 3 · Application Shell

Every authenticated screen extends one master layout with three regions.

```text
┌───────────────┬──────────────────────────────────────────────┐
│  [ LOGO ]  ◫  │  App Name                    🔔  ◐  avatar   │  <- rail head + top bar:
│  🔍 filter... ├──────────────────────────────────────────────┤     SAME 52px height,
│  WORKSPACE    │                                              │     dividers form ONE line
│  ▸ Dashboard  │   MAIN - the canvas, a distinct surface      │
│  ▾ Projects   │   · breadcrumb (deep routes only)            │
│     • All     │   · page title + primary CTA                 │
│     • New     │   · tab strip (depth overflow / facets)      │
│     • Bin     │   · page content -> cards float on canvas    │
│  COMPLIANCE   │                                              │
│  ▸ Audit Log  │                                              │
└───────────────┴──────────────────────────────────────────────┘
  rail is FULL HEIGHT and owns the top-left corner · the top bar spans ONLY the main
  column, never full-width above the rail · sidebar + top bar share ONE chrome surface ·
  the canvas is a clearly distinct surface · visible divider between all three regions ·
  logo in the rail head (wide expanded / C2S mark collapsed) · app name in the top bar ·
  no nav tabs, no global search bar, no action buttons in the top bar · every node has
  an icon · collapses to a 56px icon rail at every breakpoint
```

### Layout (ENFORCED)

The rail is the left grid column spanning both rows, so it is full height and owns the corner. The
top bar and main are the right column.

```css
.app-shell {
  display: grid;
  grid-template-columns: auto 1fr;   /* rail width · everything else */
  grid-template-rows: auto 1fr;      /* top-bar row · content row */
  height: 100vh;
  overflow: hidden;
  background: var(--color-bg-page);  /* canvas: #E8DFD0 light / #1A2E46 dark */
}
.app-body { display: contents; }     /* let rail + main place into the shell grid */

.rail-container { grid-column: 1; grid-row: 1 / 3; position: relative; width: 240px;
                  transition: width 0.28s cubic-bezier(0.4, 0, 0.2, 1); }
.rail-container.collapsed { width: 56px; }

.top-nav  { grid-column: 2; grid-row: 1; }
.app-main { grid-column: 2; grid-row: 2; min-width: 0; min-height: 0; overflow: auto;
            background: var(--color-bg-page); }
```

- Render a visible divider between the three regions (rail, top bar, canvas), plus the chrome edge
  shadow that lifts the chrome off the canvas.
- The rail head is exactly the same height as the top bar (52px), so their bottom dividers form one
  continuous line at every breakpoint.
- Only `.app-main` and the rail's nav body scroll. Both hide their scrollbars visually while staying
  fully scrollable.

### Sidebar (NavRail)

Vertical rail: brand block, then an optional nav filter, then the clusters the app uses, each
holding accordion groups and leaves.

- Brand block. Displays the company logo image in a reserved rectangular area (the wide wordmark
  expanded, the C2S short mark collapsed), is the home link, and is the only collapse/expand control
  in the shell. Keep the logo as supplied, at its natural size, directly on the chrome. Never
  recolour, box, pad, plate, stretch, regenerate, or replace it.
- The rail-toggle glyph (ENFORCED). The collapse/expand control uses one icon, the panel /
  sidebar-toggle glyph `i-panel`, the same glyph in both states. Accordion group headers use a
  chevron-down, and a collapsed group icon may show a small chevron hint. Those are different
  controls; do not swap the rail toggle for a chevron.
- Pinned head (ENFORCED). The brand block and the nav filter stay fixed; only the nav list scrolls,
  so the logo and filter never scroll out of view.
- Nav filter. Matches both leaf labels and group labels. A group-name match reveals that group and
  its children, since people often remember only the group name.
- Active leaf. Marked with the active-nav gold tint, a 3px gold left bar, and `aria-current="page"`.
  Active detection is `pathname === item.path` or `pathname.startsWith(item.path + '/')`.
- Groups. Header button plus an accordion body animated with `max-height`. The group holding the
  active route auto-expands and shows the active-trail tint; each group persists its own state. A
  1px divider sits between groups. Never name a group the same as its cluster.
- Permission gating (ENFORCED). Items the user's roles cannot access render null, never disabled and
  never hidden-but-present. Unbuilt destinations are the opposite case: disabled with a "Soon" pill.
- A group with zero visible children must not render its header.

### Collapse And Expand (ENFORCED)

Expanded: the wide logo on the left, the `i-panel` toggle on the right.

Collapsed (`56px`): the short mark and the `i-panel` expander share one fixed `40x34` slot
(`object-fit: contain`) and cross-fade. At rest show only the short mark; on hover OR keyboard-focus
of the brand block show only the `i-panel` expander on an opaque overlay covering the slot exactly.
Activating the block (click, `Enter`, `Space`) re-expands the rail, and the collapsed state persists.

In collapsed mode: labels and chevrons hide by opacity while staying in flow; every nav item and
group header becomes a uniform centered 40px square with no left border; the active item renders as a
full rounded square in the active-nav tint.

Collapsed flyouts and tooltips (ENFORCED). Hovering or keyboard-focusing a group icon reveals its
children in a flyout; a leaf shows a label tooltip. The popover must:

- Stay as legible as the expanded rail. Give every entry (nested group titles and leaves) its icon,
  indent nested groups so the hierarchy reads, and set the flyout's own header apart.
- Escape the rail's clipping. The nav list scrolls and clips its overflow, so position the popover
  `fixed` (coordinates from the icon) or portal it out.
- Use hover-intent, not instant-hide. Keep it open while the pointer crosses the gap and while the
  popover itself is hovered: a short close delay, cancelled on popover hover or focus. Keep the
  icon-to-popover gap small.
- Route through the same in-app handlers as the expanded nav items. Never a raw `href` that reloads
  or leaves the app.

Flicker-free collapse and expand (ENFORCED). No flash, clip, wipe-in, pop, hard-cut, or vertical
jump in either direction while the width animates. Use an asymmetric delay, not just clipping:

- Hide expanded-only content (labels, chevrons, pills, wide logo, collapse control) by opacity while
  it stays in flow. Never `position: absolute` or `width: 0`; that out-of-flow snap is the flash.
- Fade out fast with zero delay on collapse, so it is gone before the rail narrows enough to clip.
  Fade in only after the width animation nears completion on expand: put
  `transition-delay` roughly equal to the width duration on the expanded-state selector and `0` on
  the collapsed-state selector, since a transition uses the timing of the state it goes to. Tune the
  expand delay just under the width duration so content lands as the rail settles.
- Keep each icon at a consistent inset. Do not re-center the item when collapsed.
- Make the brand logos absolute overlays that cross-fade at fixed positions, reserving no layout box,
  so the collapsed rail stays centered and the mark never slides.
- Let the collapse control ride just inside the moving edge, never clipped, fading with the panel's
  easing over a duration close to the width animation. Pinning it at the edge lets the narrowing rail
  clip it mid-fade; edge-anchoring with a fast fade makes it jerk-slide.
- Collapse vertical regions (filter, cluster labels, an open group body) with `max-height` coupled to
  the width, so vertical reflow rides with the horizontal animation.
- Define any state-driven overlay unconditionally but invisible so it fades rather than hard-cuts,
  and drop hidden controls from the tab order with `visibility: hidden` or `disabled`; opacity alone
  leaves a focus stop. Honor `prefers-reduced-motion` by making the change instant.

### Top Bar

A three-column grid (`auto 1fr auto`), 52px tall: app name, spacer, utilities.

- Left: the application name as text. The logo lives in the rail, never here.
- Right, in order: notifications (bell with an unread dot), theme switcher, profile menu.
- The top bar carries no navigation tabs, no global search bar, and no action buttons. A primary
  action such as a create or "New" button lives in the page header of the archetype.
- Overlays (profile menu, notification panel) are mutually exclusive; opening one closes the others.
  Show and hide them by toggling `display: none`, never `visibility` or `opacity`, so the element
  leaves layout. Dismiss on outside click.

### Profile Menu

Anchored under the utilities. Three parts, in this order:

1. Identity block: the avatar beside the user's name with the email below, as a single clickable row
   with a trailing chevron that opens the profile page. Not a plain text link, and no separate
   header above it.
2. The fixed section labeled `Appearance`, holding the theme switcher, with a leading icon so the
   row matches the others.
3. Sign out.

### Theme Switcher (mandatory)

System / Dark / Light, segments in that left-to-right order, System the default and resolved from
the OS preference. Present the three options as one connected horizontal segmented control filling
the menu width in equal segments, split by thin dividers: one icon segment per option, the active one
highlighted, each with an accessible name. Not detached buttons, not a vertical list, and not a blind
click-to-cycle. Persist the choice, set the effective theme on the document root, declare
`color-scheme` per theme, and swap the logos and the favicon with the effective theme.

### Responsive

Mobile-first. Prefer keeping the same collapsible rail at every breakpoint, so behavior is consistent
and the logo stays visible. An off-canvas drawer is an optional pattern for very small screens: it
slides in over the content with a dimmed backdrop and dismisses on backdrop tap or `Escape`. If used,
the logo must still show, for example in the top bar, never a bare hamburger. Content stacks on small
screens and touch targets are at least 44px. Use CSS breakpoints (width and/or aspect ratio); never
JS for responsive layout logic.

### Auth Screens

Sign in, register, and reset do not use the shell. They are standalone centered cards sharing only
the brand mark, the tokens, and the fonts, with an optional SSO button, flash and error display, the
optional tagline, and a trust footer.

### Reference Skeleton

```text
LAYOUT shell:
  state: collapsed = persisted(false)   navFilter = ""   sidebarOpen = false (small screens)

  BACKDROP  (only with the optional off-canvas drawer) -> dim overlay, click closes

  ASIDE  [chrome surface] - full-height left column, owns the top-left corner
    RAIL HEAD (52px, home link, accessible name "Home") - the ONLY collapse/expand control
      EXPANDED  -> wide wordmark (height 22px, width auto) + i-panel collapse toggle
      COLLAPSED -> C2S short mark in a fixed 40x34 slot at rest; on hover OR keyboard-focus
                   the i-panel expander overlays the SAME slot and the mark is hidden
      App name is NOT here - it renders in the TOP BAR.

    NAV FILTER input  (pinned; matches leaf AND group labels)

    NAV BODY (the only scrolling region) = clustersFor(currentUser):
      for each CLUSTER in the fixed order:
        muted uppercase cluster label  (hidden when collapsed)
        for each NODE:
          if GROUP (accordion):
            BUTTON: [node.icon] + label + chevron-down (aria-expanded)
                    collapsed? hover/focus reveals a FLYOUT : click toggles open
                    auto-open + active-trail tint when it holds the active route
            CHILDREN (max-height animation, persisted open state):
              nested GROUP -> same shape, indented one step (up to level 3)
              LEAF         -> link [child.icon] + label; active = gold tint + 3px gold bar
          else if unbuilt LEAF:   non-link row [icon] + label + "Soon" pill (aria-disabled)
          else if unauthorized:   render null (absent)
          else LEAF:              link [icon] + label; active = gold tint + 3px gold bar

  COLUMN (fills remaining width):
    TOP BAR  [chrome surface - the SAME color as the rail] - 52px, over the canvas only
      APP NAME (left)  ·  spacer  ·  notifications · theme switcher · profile menu
      NO nav tabs · NO global search bar · NO action buttons
    MAIN  [canvas surface - clearly distinct from the chrome], the scrolling region
      BREADCRUMB   (only for destinations deeper than about 3 levels)
      PAGE HEADER  (title + subtitle left; the primary CTA right)
      TAB STRIP    (only for depth overflow or record facets, §6)
      PAGE CONTENT -> cards float on the canvas; every data region owns its
                      loading / empty / error states
      TOAST HOST   (one persistent aria-live region, present from page load)
```

The document head is standard for the stack. The `<title>` comes from `app.title_bar`, the favicon
from the per-theme `.ico` pair at `<BRAND_ASSETS_PATH>`, swapped with the effective theme.

## 4 · Design System And Tokens

Define each token once in the project's own styling system (CSS custom properties, a theme file, a
preprocessor map, a component-library theme), in both themes, then author every component against the
tokens. Token names may adapt to the stack; token values may not change. Never hardcode a hex.

### Brand Palette

| Role | Color | Hex |
| --- | --- | --- |
| Primary accent (actions, links, focus, active tab) | Midnight Blue | `#193E6B` |
| Secondary accent (active-nav gold treatment) | Green Gold | `#B3A125` |
| Secondary (buttons) | Cadmium Violet | `#7F3F98` |
| Secondary (non-interactive headers, info) | Jelly Bean Blue | `#448E9D` |
| semantic.success | Avocado Green | `#5F8025` |
| semantic.warning | Sunray | `#E9AC53` |
| semantic.danger | Violet-Red | `#991547` |
| semantic.info | Jelly Bean Blue | `#448E9D` |
| Neutral family | Platinum Beige plus White | `#EEE7E0` ramp, `#FFFFFF` |

Color role rules (ENFORCED): secondary buttons use only Cadmium Violet; destructive is always
Violet-Red; Green Gold is the active-nav and highlight treatment only, never warnings or deletion;
Sunray appears interactively only as the Warning button variant; Jelly Bean Blue stays
non-interactive. Never repurpose a role's color and never introduce an off-palette hue; a genuinely
new tint derives from an approved hue and keeps the WCAG AA bar. Semantic colors used as text, icons,
or thin edges on a surface always go through the theme-aware readable tokens (`--badge-*-fg`), never
the raw semantic hex; raw Violet-Red on a dark card is about 1.6:1 and invisible. Raw semantic hex is
only for solid fills such as a danger button with white text.

Color split: 60% neutral background (canvas plus surfaces) · 30% secondary UI (Cadmium Violet, Jelly
Bean Blue, Sunray) · 10% primary accent (Midnight Blue).

### Core Tokens, Per Theme

| Token | Light | Dark |
| --- | --- | --- |
| Chrome (sidebar plus top bar, ONE color) | `#FFFFFF` | `#080F1A` (ink navy) |
| Chrome text | `#223349` | `#E4EBF4` |
| Chrome muted text (icons, cluster labels, placeholders) | `#566779` | `#9AABC0` |
| Chrome hover tint (nav items, icon buttons) | `rgba(25,62,107,0.07)` | `rgba(255,255,255,0.09)` |
| Chrome edge shadow color | `rgba(15,25,45,0.14)` | `rgba(0,0,0,0.50)` |
| Canvas (main working area) | `#E8DFD0` | `#1A2E46` |
| Card / surface | `#FFFFFF` | `#253E5D` |
| Card border / strong | `#E8E2D8` / `#D5CEC1` | `rgba(255,255,255,0.10)` / `rgba(255,255,255,0.18)` |
| Surface hover tint (table rows, buttons, tabs at rest) | `rgba(25,62,107,0.06)` | `rgba(255,255,255,0.07)` |
| Divider (chrome region borders) | `#E4DCCD` | `rgba(255,255,255,0.13)` |
| Text (ink, never gray) | `#1E2E42` | `#E9EFF6` |
| Muted text | `#4D5E75` | `#A3B2C5` |
| Interactive accent (links, primary buttons, focus, active tab) | `#193E6B` (hover `#142F52`) | `#7FADE1` (hover `#93BBE8`) |
| Accent contrast (text on the accent) | `#FFFFFF` | `#0F1C2E` |
| Nav active (gold treatment) | bg `rgba(179,161,37,0.16)`, fg `#5C5010`, 3px bar `#B3A125` | bg `rgba(201,182,47,0.22)`, fg `#EBDD7E`, 3px bar `#C9B62F` |
| Sidebar filter input background | `#EFE8DB` | `rgba(255,255,255,0.08)` |
| "Soon" pill (nav) | bg `#EFEAE0`, fg `#514A3B` | bg `rgba(255,255,255,0.16)`, fg `#DCE4F0` |
| Notification / alert dot (on chrome) | `#991547` | `#F3AFC9` |
| Tooltip surface (white text on it) | `#202C3E` | `#2A3A52` |
| Text selection highlight | `rgba(179,161,37,0.35)` (both themes) | same |

The two themes' chromes are different colors by design (white against ink navy). Never render the
same chrome in both modes.

### Status Badge Tokens (theme-aware readable pairs)

| Role | Light bg (mix with `#FFFFFF`) | Light fg | Dark bg (mix with `#253E5D`) | Dark fg |
| --- | --- | --- | --- | --- |
| neutral | `#F0EBE1` | `#514A3B` | `rgba(255,255,255,0.10)` | `rgba(255,255,255,0.80)` |
| success | 16% Avocado | `#3A4E13` | 28% Avocado | `#CBE79B` |
| warning | 26% Sunray | `#7A500C` | 30% Sunray | `#F8DFAC` |
| danger | 14% Violet-Red | `#85113E` | 32% Violet-Red | `#F5B8CF` |
| info | 18% Jelly Bean | `#1E545F` | 30% Jelly Bean | `#B4E3EC` |
| violet | 15% Cadmium | `#5B2B6E` | 30% Cadmium | `#DFBAEC` |

"n% X" means mix the brand color at that percentage into the theme's card surface, for example
`color-mix(in srgb, <brand> n%, <card>)`.

### Type, Shape, Elevation, Motion

| Aspect | Fixed value |
| --- | --- |
| Heading font | Montserrat (600/700), fallback `system-ui, -apple-system, 'Segoe UI', sans-serif` |
| Body font | Source Sans 3 (400/500/600/700), same fallback; base body text 13px, line-height 1.5 |
| Font loading | Google Fonts `css2?family=Montserrat:wght@600;700;800&family=Source+Sans+3:wght@400;500;600;700&display=swap`, or self-host the same families |
| Spacing scale | 4px base: 4 / 8 / 16 / 24 / 32 / 48 / 64 |
| Radius | cards 12px, controls 8px, tab tops 10px, modals 14px, pills 999px |
| Resting elevation | Cards are FLAT: 1px card border, no resting shadow |
| Hover / overlay elevation | md `0 2px 8px rgba(25,40,65,0.08)` light, `0 3px 10px rgba(0,0,0,0.30)` dark; lg (popovers, modals, toasts) `0 8px 24px rgba(15,25,45,0.14)` light, `0 10px 28px rgba(0,0,0,0.45)` dark |
| Chrome edge shadow | rail `2px 0 10px <chrome-edge>`, top bar `0 2px 10px <chrome-edge>` |
| Focus ring | `2px solid <accent>`, `outline-offset: 2px`, on every interactive element |
| Rail animation | width `0.28s cubic-bezier(0.4, 0, 0.2, 1)`; honor `prefers-reduced-motion` (instant) |

### Type Scale (ENFORCED)

Approved size steps: 10 / 11 / 12 / 13 / 14 / 15 / 16 / 18 / 20 / 24 px. No other size exists.
Weight 300 is not loaded, so nothing may use it; 800 is optional display emphasis only.

| Token | px | Role | Family | Weight |
| --- | --- | --- | --- | --- |
| `--text-display` | 24 | Display (hero numbers, status headlines) | heading | 700 |
| `--text-h1` | 20 | Page titles | heading | 700 |
| `--text-h2` | 18 | Section headers | heading | 700 |
| `--text-h3` | 16 | Card titles | heading | 600 |
| `--text-h4` | 15 | Sub-section headers | heading | 600 |
| `--text-lead` | 14 | Lead / emphasised body | body | 400/500 |
| `--text-body` | 13 | Body content (line-height 1.5) | body | 400 |
| `--text-small` | 12 | Labels, captions | body | 500 |
| `--text-xs` | 11 | Meta data | body | 400 |
| `--text-micro` | 10 | Uppercase micro-labels, table headers | body | 600 |

### Shell Dimensions

| Element | Fixed value |
| --- | --- |
| Top bar and rail head height | 52px, their bottom dividers forming one continuous line |
| Sidebar width | 240px expanded / 56px collapsed |
| Controls | buttons and inputs 32px tall (small 27px), top-bar icon buttons 34px, avatar 30px |
| Wide logo | `height: 22px; width: auto` in the rail head |
| C2S short mark | 40x34 slot, `object-fit: contain`, centered in the collapsed rail |
| Scrollbars | the rail's nav list and the main canvas hide scrollbars visually while staying fully scrollable |

### Brand Assets

| Asset | Light theme | Dark theme |
| --- | --- | --- |
| Logo, full wordmark (expanded sidebar) | `logo-full-light.png` | `logo-full-dark.png` |
| Logo, C2S short mark (collapsed rail) | `logo-short-light.png` | `logo-short-dark.png` |
| Favicon (`.ico` 16/32/48/256) | `favicon-light.ico` | `favicon-dark.ico` |

The pack ships in `assets/` next to this file. Alt text is "CLaaS2SaaS". Light variants sit on the
light (white) chrome, dark (white) variants on the dark (ink) chrome, swapped with the effective
theme.

Never modify, recolour, distort, plate, re-typeset, regenerate, substitute, or invent a logo or
favicon, and never source one from anywhere but the bundled pack. The in-project destination is the
developer's decision, recorded as `<BRAND_ASSETS_PATH>`; ask if it is unrecorded and never choose it.

If a required asset is missing: generate with a placeholder, mark it
`<!-- ASSET MISSING: [asset] - replace with actual path -->`, and tell the developer which asset,
where it is expected, and what is affected.

### Icons

One central inline-SVG registry for the app: a single `<symbol>` sprite, or the stack's equivalent
icon-component registry. All glyphs follow the fixed style: 24px viewBox, 2px stroke, round caps and
joins, outline. Do not mix libraries; no second icon set, no emoji, no ad-hoc SVGs. Add a genuinely
new glyph to the registry in the same style.

- Naming: symbol ids are `i-<concept>`, for example `i-grid`, `i-trash`, `i-chevron-right`.
- Sizing: icons render at `1em` and are sized via `font-size`, not by scaling the SVG.
- Sizes: nav and group icons 18px (20px when the rail is collapsed); top-bar action icons 20px;
  chevrons 16px; empty-state illustration icons 48px.
- Accessibility: decorative icons carry `aria-hidden="true"`; an icon-only control needs an
  accessible name on the control.

Register an icon before use. Keep every icon meaningful; the same glyph always denotes the same
concept or action.

## 5 · Page Archetypes

Every screen is one of these. Pick the matching archetype and fill it with the entity's data. Same
kind of screen means same archetype. Do not invent a new layout per screen.

### 5.1 Dashboard

Optional accent hero, then a metric-tile grid, then either a quick-actions grid or a recent-activity
table. Harden each count so a data failure shows a placeholder rather than erroring. When the
customizable-dashboard toggle is on, the main area is a user-arrangeable widget grid whose layout
persists per user; treat each widget as an independently renderable partial.

### 5.2 List / Index

Page header with the primary CTA, an optional filter bar, a card-wrapped table, and a pagination
footer only when there is more than one page. Table rules live in §8.

### 5.3 Detail / Show

Back link, a header card (title, status badge, meta line, right-aligned actions), then a 2/3 plus 1/3
body grid. The side-panel column widens to full width when its sibling is absent. When the record has
several facets, switch them with the horizontal in-canvas tab strip above the body (§6): one tab per
facet, each a deep-linkable route.

### 5.4 Form (create / edit)

One shared template serves both modes, keyed on whether the record exists; it flips the title, the
action route, the method, the submit label, and the back/cancel targets. Regions: back link, section
cards of fields, an optional error-summary card, then a footer with cancel plus submit. Always
repopulate prior input. Field, validation, grouping, and wizard rules live in §8.

### 5.5 Settings / Config

List plus form, with boolean toggles, secrets shown only as a masked badge labeled "encrypted at
rest", structured config in a monospace area, and optional row-level test or health actions.

Connection and integration configuration is a stricter mandatory sub-pattern (ENFORCED). It applies
whenever the fields configure a connection to an external system: API credentials, email or SMTP, a
third-party app integration, a webhook, or any similar outbound-connection config.

| State | Buttons shown | Emphasis |
| --- | --- | --- |
| Untested (first load, or after any tested field is edited) | `Reset` + `Test Configuration` | `Reset` ghost; `Test Configuration` primary |
| Test in progress | `Reset` (disabled) + `Test Configuration` (loading) | Same as above |
| Test succeeded, values unchanged since | `Reset` + `Test Configuration` + `Save` | `Reset` ghost; `Test Configuration` outline; `Save` primary |
| Test failed | `Reset` + `Test Configuration` | Same as Untested, with inline failure detail and no `Save` |

- `Reset` restores the last-saved values, or clears the form on first setup, discarding unsaved edits
  and any test result. It is a routine exit, not destructive, so it is ghost emphasis.
- `Test Configuration` runs the real connection check as an async action (§8 Buttons): disable and
  show the loading affordance, guard against double-submit, keep the width stable. Report the result
  two ways: a toast, and an inline connection-status indicator beside the fields (a status badge,
  never color alone) that persists after the toast dismisses.
- `Save` is never rendered, or stays disabled with an explanatory affordance, until the most recent
  `Test Configuration` against the current unedited field values returned success.
- Editing invalidates the test. The moment any tested field changes after a successful test, `Save`
  hides or disables again, the inline status clears back to untested, and the primary emphasis
  returns to `Test Configuration`. A fresh successful test is required before `Save` reappears.

Never offer a way to save unvalidated connection settings. There is no "save anyway", no "skip
test", and no force-save affordance. This applies only to the fields that configure an outbound
connection, not to a boolean toggle or an unrelated setting on the same screen.

### 5.6 Builder (hub and spoke)

For multi-step authoring such as templates, form builders, or pipelines. A central hub page whose row
actions spoke out to single-purpose sub-pages, each returning via a back link. Lifecycle state shows
inline with exactly one confirm-guarded "advance" control per row; terminal states show no button. A
spreadsheet-style field builder edits rows in a table with add-row and per-row delete. A validation
gate checks readiness before a transition, and its success copy names the next workflow step.
Drag-and-drop or canvas builders may be desktop-only; say so in the UI.

### 5.7 Auth

A standalone centered card with no shell: brand mark, optional SSO button, flash and error display,
the credential form, an optional tagline, and a trust footer.

### 5.8 Recycle Bin / Soft Delete

Destructive deletes route here, not to a hard delete. Per-user restore. The admin view buckets by
entity, each bucket listing trashed records with restore plus a confirm-gated permanent delete.
"Empty everything" requires typing a confirm word, the strongest guard in the app. Owners restore
their own records; only an admin permanently deletes.

### 5.9 Status / Result

A single full-width card tinted by outcome, with a circular icon medallion, a bold headline, and a
muted explanation. No table.

### 5.10 AI Model Catalog (composite)

Built only when the `ai_model_catalog` toggle is on. Not a new archetype: it is three screens made
from the archetypes above, for an app that calls an AI model at runtime and keeps each model as a
configuration record rather than as code. The record holds the endpoint, the header rows, the typed
body fields, the response paths, the price, and a reference to its credential, so a new model is a
new row and never a code change.

| Screen | Archetype | Purpose |
| --- | --- | --- |
| Model catalog | 5.2 List / index | Browse, filter, and act on the entries |
| Add / edit model | 5.4 Form, with the 5.5 test-before-save contract | Author one entry |
| Run | 5.4 Form plus the call-result panel below | Send a prompt to a saved entry and read the result |

Placement. The catalog is integration configuration that authorizes spending, so it sits under
`System Administration`, admin-only, gated at the handler as well as in the nav (§7). Where the Run
screen sits depends on who uses it: beside the catalog when it is an admin diagnostic, under
`Workspace` when end users work in it. Ask; never place it by guessing. Never add a cluster for this.

The list carries name, model id, method pill, endpoint (truncated per the table URL-cell treatment),
cost in and out with the currency, a status badge, and when it was last tested. Row actions are Run,
Edit, Duplicate, and Move to Recycle Bin. Status uses the fixed roles only: active is success, draft
and retired are neutral, a failed last test is danger, never-tested is warning. Show last tested,
because an entry that has never made a successful call is the thing a reader most needs to notice
before depending on it. Render cost as unknown, never as zero or a dash that reads like zero, when
the price is unset. Never render a credential, a partial credential, or a length hint anywhere.

The form's section cards run: identity, endpoint, credential, header rows, body rows, response paths,
cost, routing, then the test block. Header and body rows use the typed row repeater (§8). The
credential field takes a reference; where an admin may enter a value for the secret store, it is a
password-type input, never re-rendered with a saved value, showing a masked badge that says where the
value is stored, and a blank field on edit means unchanged rather than cleared.

This is connection configuration, so the test-before-save contract in §5.5 applies in full, with
`Test call` as the check: `Reset` plus `Test call` until a call on the current values succeeds, then
`Save` appears; editing any tested field clears the result and hides `Save` again.

There is no save-anyway, skip-test, or force-save path. A saved but untested entry is a call that
fails in production at the moment a user needs it, having already been marked configured.

The Run screen is a card with a model picker, a prompt textarea, and one primary `Send`, then the
result panel. The picker offers only callable entries (active, last test passed) and shows the
selected entry's price and last-tested state, so the person sending knows what a call costs before
sending it. `Send` is the async action from §8 Buttons.

#### The Call Result Panel

One panel renders every call result, on the form's test and on the Run screen, so a reader learns it
once. Sections in order: status bar, response, usage and cost, the masked request, the raw response.

```text
┌──────────────────────────────────────────────────────────────────────────────┐
│ ● Success (HTTP 200)                                    1.8s · 412 tokens    │
├──────────────────────────────────────────────────────────────────────────────┤
│ Response                                                                     │
│ <the answer, rendered as TEXT, never as markup>                              │
├──────────────────────────────────────────────────────────────────────────────┤
│ Usage and cost   in 128 · out 284 · cost x.xxxxxx <CURRENCY>                 │
├──────────────────────────────────────────────────────────────────────────────┤
│ ▸ Request sent (masked)                                                      │
│ ▸ Raw response                                                               │
└──────────────────────────────────────────────────────────────────────────────┘
```

- The status bar carries the outcome as a status badge with text, never color alone, plus the HTTP
  status and the duration.
- All provider text renders as text. A result panel built by concatenating provider output into
  markup is an injection hole fed by a third party.
- Usage and cost render only when at least one is known, and render unknown as unknown.
- The masked request and the raw response are collapsible monospace blocks with a bounded height and
  their own scroll: collapsed on success, expanded on a failure or a path miss.
- A path miss is its own visible outcome. When the call succeeded but the response path matched
  nothing, say exactly that, name the path, and open the raw response so the correct path can be read
  off it and fixed in one edit. Never show a blank response section instead.
- On failure, show the provider's message expanded, with no stack trace and no internals.
- Loading is a skeleton shaped like this panel (§8), not a spinner in an empty box. The only spinner
  is the small one inside the button in flight.
- Announce the outcome through a toast and the live region: polite on success, assertive on error.
  The button never becomes a success checkmark.

## 6 · Sub-Navigation Patterns

Standing navigation lives in the left sidebar accordion under the four fixed clusters, at most
three levels deep. Navigation deeper than that, and a single record's facets, move to a horizontal
in-canvas tab strip. Standing navigation never moves to the top bar, and tabs never replace the
sidebar.

### Pattern A · Sidebar Accordion

The whole standing navigation tree is the left-rail accordion built in §3. Sub-menus are collapsible
groups, not tabs, and they nest at most three levels within a cluster. This is where most navigation
happens. Reach for it for every persistent destination. When a branch must go deeper, hand the
overflow to Pattern B.

### Pattern B · Horizontal Tab Strip

Sanctioned for exactly two cases:

1. Depth overflow. A leaf sitting under a level-3 accordion group that itself needs children. Adding
   them would force a forbidden fourth accordion level, so that leaf becomes a routable page whose
   children render as tabs. A level-3 group whose children are plain leaves stays an accordion and is
   not this case.
2. Record or page facets, for example `Overview | Activity | Permissions`.

Route-backed links are the default: each tab is its own deep-linkable URL, reflected in the
breadcrumb and back/forward, rendered as a `<nav>` landmark of real `<a href>` links with
`aria-current="page"` on the active tab. Reserve the ARIA tab widget (`role="tablist"` /
`role="tabpanel"` with roving tabindex and arrow keys) for genuine in-page panels that are not
separate routes, and never mix the two on one strip.

```text
# PSEUDO-STRUCTURE - a page with a horizontal tab strip
MAIN:
  BREADCRUMB (reflects the route path INCLUDING the active tab)
  PAGE TITLE
  TAB STRIP (horizontal, top of the canvas, under the breadcrumb and title):
    for each TAB (label + optional leading icon):
      route-backed LINK -> the facet's own route
                           active = card-surface fill + hairline border + weight + aria-current
      unbuilt facet   -> disabled + "Soon" pill, out of the tab order
      unauthorized    -> absent (render null)
  PANEL (fills the width below the strip): the active tab's content, an archetype.
        The PANEL owns its loading / empty / error states; the strip never skeletons.
```

Placement and shape: one strip per page, at the top of the work canvas, below the breadcrumb and page
title. Curved browser-tab look: rounded 10px top corners and an always-visible resting tint; the
active tab fills with the card surface, gains a hairline border with no bottom border, visibly breaks
the strip's rule, and flares into it with concave bottom fillets. Fill plus border plus attachment
plus weight carry the active state, never color alone. Inactive tabs darken one step on hover; the
active tab never changes on hover. On small screens the strip scrolls horizontally, pinned to
horizontal scroll only (`overflow-y: hidden`), keeping the active tab in view; it never wraps to a
second row and never truncates the active tab. Touch targets are at least 44px.

Terminal (ENFORCED). A tab panel holds content, never another tab strip and never a fresh sidebar
accordion. If a panel seems to need sub-tabs, the tree is mis-shaped: restructure it.

### Pattern C · Click-Through And Back

Use when drilling into a different record or a nested collection: list to detail, category to its
items, bin to its records. Standing navigation does not change; the sub-page opens with the canonical
back link and returns through it.

```text
# PSEUDO-STRUCTURE
BACK LINK: <- All <ENTITY_PL>   (muted, small, arrow-left icon, -> the index route)
```

### Tabs Versus Filters

A list's compact filter control is a separate affordance. Filter tabs (`All / Active / Inactive`)
narrow a list's rows and read as a filter, not navigation; they live in the search-and-filter contract
(§8), not here. Do not use the navigation tab strip for a list filter, and do not move either into
the top bar.

| | A · Sidebar accordion | B · Horizontal tab strip | C · Click-through and back |
| --- | --- | --- | --- |
| Scope | standing nav tree, at most 3 levels | depth overflow plus one record's facets | between records or nested collections |
| Placement | left sidebar, always | top of the canvas, under breadcrumb and title | full-page navigation |
| Mental model | "where in the app am I" | "which face or deeper area is this" | "I opened a different thing" |
| Form | accordion groups plus leaves | horizontal route-backed links, terminal | a link out plus a back link |

## 7 · Role And Access Model

Four tiers, four gate layers. Confirmed labels live in the App Definition. Rename or extend only for
a documented per-app reason, keeping the tier shapes.

### The Four Tiers

| Role | Tier | Clusters | Record scope | Create | Read | Update | Soft delete | Restore | Permanent delete |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Administrator | `admin` | all four | everyone's | yes | all | all | all | all | yes (only tier) |
| Collaborator | `team` | Workspace · Compliance | self plus people beneath them | yes | scope | scope | scope | scope | no |
| Contributor | `self` | Workspace | own only | yes | own | own | own | own | no |
| Viewer | `self_view` | Workspace | own only, configurable per app | no | own | no | no | no | no |

- Administrator is the super-tier. It bypasses every cluster, policy, and ownership filter, and is
  the only tier that permanently deletes.
- Collaborator is a team-lead tier: full CRUD over its own records plus records owned by users
  beneath it in the org hierarchy. It gets Compliance for oversight but neither administration
  cluster.
- Contributor is the standard worker: full CRUD over its own records only, Workspace only.
- Viewer views but never mutates, within Workspace. The default view scope is own; an auditor-style
  read-only that sees everything sets its scope wider, recorded per app.

Cluster grants:

| Cluster | Roles granted |
| --- | --- |
| Workspace | Viewer, Contributor, Collaborator, Administrator |
| Compliance | Collaborator, Administrator |
| Application Administration | Administrator only |
| System Administration | Administrator only |

### Data Scope

"Own data" means the record's owner is the current user. Scope widens by tier:

```text
admin      -> all records
team       -> records owned by { self } union { everyone reporting up to self }   (org subtree)
self       -> records where owner = self
self_view  -> records where owner = self   (read-only)
```

The hierarchy is a self-referential manager or team reference on the user; `teamMemberIds()` returns
self plus the transitive subordinate ids. Where no org tree exists, make the team tier a flat group
membership instead: scope is records owned by anyone sharing the user's team. Same pattern, simpler
hierarchy. Decide this in the App Definition.

### The Four Gate Layers (defense in depth, all must agree)

1. Cluster and feature access. `canAccessKey(user, policyKey)` decides sidebar visibility and gates
   the controller or handler; deny if not allowed.
2. Per-record policy. Authorizes a single record's actions, including ownership and any state lock.
3. Query scope. List and index queries filter to the user's visible scope, so out-of-scope records
   never load.
4. Permanent-delete guard. A hard admin-only gate on force-delete and purge routes.

```text
# PSEUDO-CODE - implement in the confirmed language
isAdmin(user)      -> user.tier == "admin"
isReadonly(user)   -> user.tier == "self_view"
teamMemberIds(u)   -> [u.id] + descendants(u).ids          # self plus org subtree
visibleOwnerIds(u):                                        # none = "all" (admin)
    admin -> none ;  team -> teamMemberIds(u) ;  otherwise -> [u.id]

# query scope - apply to EVERY list query for the entity
scopeVisibleTo(query, u):
    ids = visibleOwnerIds(u)
    return query if ids is none else query.where(owner in ids)

# generic per-record policy (one per ownable entity)
viewAny(u)        -> true                                  # the list is filtered by scopeVisibleTo
view(u, r)        -> inScope(u, r)
create(u)         -> not isReadonly(u)
update(u, r)      -> (state lock) and not isReadonly(u) and inScope(u, r)
delete(u, r)      -> not isReadonly(u) and inScope(u, r)   # soft
restore(u, r)     -> not isReadonly(u) and inScope(u, r)
forceDelete(u, r) -> isAdmin(u)                            # admin only
inScope(u, r)     -> ids = visibleOwnerIds(u); ids is none or r.owner in ids
```

Keep the layers consistent. If a tier is granted a cluster, make sure its policy and query scope can
actually serve that cluster's data, or users hit empty screens and denials. The list-query scope and
the `view` policy must tell the same story.

### Extending The Model

- More roles: add a row on one of the existing tiers, or define a new tier with its own arm in
  `visibleOwnerIds()` plus a cluster grant.
- Multi-tenant: add a tenant reference and AND it into `scopeVisibleTo` for all tiers including
  admin, so a tenant admin never sees another tenant. Record it in the App Definition.
- Capability flags: for fine-grained permissions beyond tiers, attach named abilities to roles and
  check them in policies. Start with tiers; they cover most apps.

## 8 · Component And Interaction Contracts

The floor every implementation meets. Apply the entry for each component on the screen; the same
control always gets the same primitive and the same behavior.

### Buttons

A button is one semantic meaning, one emphasis level, one size, one state. Pick the variant by what
the action does, not by color preference.

| Variant | Color | Text | Use for |
| --- | --- | --- | --- |
| Primary | Midnight Blue `#193E6B` | white | the single main action: Submit, Save, Create, Confirm, Continue |
| Secondary | Cadmium Violet `#7F3F98` | white | a real alternative beside primary, for example Save as draft next to Publish |
| Danger | Violet-Red `#991547` | white | destructive or irreversible: Delete, Remove, Discard, Revoke |
| Warning | Sunray `#E9AC53` | dark ink `#1E2E42` | caution, not destruction: Proceed anyway, override |
| Success | Avocado Green `#5F8025` | white | confirming a positive completion: Approve, Mark complete, Publish |
| Accent | Green Gold `#B3A125` | dark ink `#1E2E42` | highlight or upsell CTA |

Emphasis: solid (the one primary action per group), outline (medium), ghost (Cancel, Back,
toolbar and table-row actions). Exactly one solid button per action group. Cancel and Back are never
a filled color.

Sizes: small 27px (dense tables and toolbars), medium 32px (the default), large 48px (hero, empty
state, mobile primary). Touch targets are at least 44x44px; use the large size for a mobile primary
action. Icon-only buttons require an accessible name.

States: rest, hover (lift 1px and darken), active (press 1px and darken further), visible
`:focus-visible` ring, disabled (`opacity: 0.5`, not-allowed, no transforms), and loading.

Async actions: on click immediately disable, hide the label and show a centered spinner, and guard
against double-submit. Keep the width stable. Set `aria-busy` while loading, and pair the spinner
with verb-ing text ("Saving...", "Deleting...") for important or destructive operations. On success
return to rest; the toast confirms it, so never relabel the button to "Saved". On error return to
rest, re-enable, and surface the error elsewhere. Never trap a button in a permanent loading state.

Copy: say what happens ("Save changes", not "Submit"), sentence case, active voice, and keep the verb
consistent through the flow so Publish produces a "Published." toast.

### Cards

One base shell filling a subset of slots: header (title plus icon or actions), body, optional footer.
The card surface is white on the light canvas and raised navy on dark, flat at rest with a 1px border,
12px radius, and no resting shadow. Card titles use the h3 step (16px). Variants: content (the
default), KPI/metric (label, large value at the display step, direction-based trend color through the
readable tokens), entity (media, text, actions in a row), form section (reduced padding), plus an
interactive modifier for a card that is wholly clickable. Soft elevation is reserved for the
interactive hover and for overlays; a resting card never has a shadow. Cards sit in a mobile-first
grid: one column on small screens, fanning out as width allows.

### Tables And Pagination

Pick the smallest tier that does the job:

| Tier | Use for | Capabilities |
| --- | --- | --- |
| Simple | short read-only list, about 10 rows or fewer | header, rows, hover |
| Standard | the workhorse list or index view | plus sortable columns, row-click to detail, status pills, row action icons, pagination |
| Advanced | dense power-user data management | plus row selection and a bulk bar, sticky check and action columns, column show/hide, density toggle, expandable rows, inline cell editing |

Keep the light look at every tier: a white card with a 1px border and no resting shadow, 10px
uppercase muted headers, 1px horizontal row dividers, subtle hover. No gradient bars, no vertical
gridlines, no heavy borders.

- Align by type: text left; numbers, currency, and counts right-aligned with tabular figures; status
  as a pill; actions in the right-most column as ghost icon buttons.
- Never leave a blank cell; render a muted placeholder.
- A sortable header is a `<button>` inside the `th`, and the `th` carries `aria-sort`. One column
  sorts at a time.
- Pagination is numbered and canonical: show it when total rows exceed 25, default page size 25, with
  options 25 / 50 / 75 / 100, info text "Showing 1-25 of 247", and Prev, numbered pages, Next. On
  small screens simplify to Prev / Next plus "Page 3 of 10" and hide the per-page selector.
- Selection reveals the bulk bar only when at least one row is selected. A destructive bulk action
  routes through a confirmation and resolves with a toast.
- On narrow screens the table scrolls horizontally inside its scroll container with the checkbox and
  action columns pinned, so the row's identity and controls never scroll out of view. Do not
  transform rows into stacked cards by default.
- Every table ships loading, empty, and error states. A bare blank box is never acceptable.

### Forms And Validation

Two sizing contexts, page form (roomy) and modal form (compact), plus a third structural shape, the
multi-step wizard. Sizing differs; validation is identical everywhere.

A field is a visible associated label, the control, optional help text, and a reserved validation
message slot. Placeholders are not labels. Mark required-ness with one consistent convention per form.

Validation contract (ENFORCED):

1. Validate inline on blur and show the error directly below the field, tied via `aria-describedby`.
2. Validation is error-only. No green borders, checkmarks, or "Looks good" on valid fields; a passing
   field returns to rest.
3. Submit stays enabled. Never grey out the primary button. Clicking it runs full validation.
4. Re-validate on input once a field has errored, so the error clears the moment it is fixed.
5. On submit, if anything is invalid, focus and scroll to the first invalid field and announce it.
6. Map async and server errors back to the field, or to a form-level summary. Never swallow them.
7. Do not flag required-ness on first focus. Empty-but-untouched is not an error yet.
8. Reserve the message slot so the layout does not jump, and never signal validity by color alone.

Layout: single column by default, capped at a readable line length. Two or three columns only with
justification, and within the modal-size limit; all multi-column rows collapse to one column on touch.
Group beyond about eight fields, by section headings or fieldsets, by tabs (page forms and large
modals), or by wizard steps. Keep required fields in the first group, tab, or step.

Wizards use exactly two buttons in the same right-aligned footer group: Back (hidden or disabled on
step one) immediately left of the one solid Next. On the last step Next's label becomes Create, Save,
or Finish and it submits; never add a third persistent button. Validate the current step before
advancing and never lose entered data on Back.

Connection and integration config forms follow the test-before-save contract in §5.5.

### Typed Row Repeater

For a form section that edits a variable-length list of rows: header rows, key/value configuration,
typed payload fields, a field builder. A table-style repeater, not a stack of loose inputs.

```text
Body
┌───────────┬───────────────────┬──────────────────────────────────────┬────┐
│ Type      │ Key               │ Value                                │    │
├───────────┼───────────────────┼──────────────────────────────────────┼────┤
│ [Text  ▾] │ model             │ <MODEL_ID>                           │ ✕  │
│ [Number▾] │ max_tokens        │ 1024                                 │ ✕  │
│ [JSON  ▾] │ messages          │ [{"role":"user","content":"{{prom... │ ✕  │
└───────────┴───────────────────┴──────────────────────────────────────┴────┘
                                                            [ + Add row ]
```

- A legend row labels the columns, and every column is a real labelled control. A placeholder is not
  a label.
- One icon-only remove control per row, keyboard-reachable, with an accessible name saying what it
  removes.
- `Add row` appends an empty row and moves focus into its first field. Server-rendered and
  script-added rows use identical markup, so one set of behaviors covers both.
- Removing the last row leaves the legend and the add control visible, never a bare empty area.
- Per-row validation follows the contract above, in that row's reserved message slot. A row with a
  blank key is ignored on save rather than rejected.
- On small screens a row stacks into a labelled group rather than scrolling horizontally.

### Modals And Dialogs

Use a modal only when a decision must happen now or context must stay visible. Otherwise use a page,
a drawer, or a toast. Never open a modal from within a modal.

Behavior is ENFORCED. Every modal implements all four: `Escape` closes, backdrop click closes (both
treated as Cancel), focus is trapped inside, and focus returns to the trigger on close. Supporting
requirements: move focus in on open (to the first field, the dialog, or, for a destructive modal, the
safe action), scroll-lock the background, and make the rest of the page inert.

Label it with `role="dialog"`, or `role="alertdialog"` for destructive and critical cases, plus
`aria-modal="true"` and `aria-labelledby` / `aria-describedby`. Exactly one solid action; Cancel is a
ghost. Only the body scrolls; the header and footer stay pinned.

Sizes by content: small 420px (confirmations and simple forms, a strict minimum), medium 600px
(standard forms, the default), large 900px (complex data entry). Above 900px only when content
genuinely needs it, still capped so it never becomes full-bleed.

Destructive confirmations name the exact target, state the consequence and whether it is recoverable,
use a verb-labeled danger action rather than "OK" or "Yes", and put initial focus on the safe action.
High-stakes or bulk deletes require friction first: type the name, type a confirm word, or check "I
understand".

Discard prompts appear only when the form is actually dirty and guard every exit path: Cancel, the
close control, `Escape`, the backdrop, and in-app navigation. The destructive choice is Discard;
Keep editing is the safe ghost default with initial focus.

Resolve the outcome with a toast and close on success; on error keep the modal with an inline message.
Never leave a modal showing a success checkmark.

The segmented, in-header-tabbed form modal is an opt-in variant built only when the developer asks
for it. Never convert a form into tabbed segments on your own.

### Toasts

A toast is a brief, non-blocking confirmation of an action's result. It never interrupts or demands a
click; that is a modal.

Four types by meaning: success (check icon, auto-dismiss about 4s), error (warning triangle, persists
until dismissed), warning (persists or about 6 to 8s), info (info icon, auto-dismiss about 5s). The
type sits on a left accent bar plus the icon; the surface stays neutral so text keeps contrast. Never
signal type by color alone.

Every toast has a manual close, even when it auto-dismisses. Auto-dismiss pauses on hover and on
keyboard focus. Stack in one corner, default top-right, newest nearest the edge, capped at about three
or four visible. At most one inline action (Undo, Retry, View), styled low-emphasis; a toast carrying
an action must not auto-dismiss out from under the user. On small or tall screens toasts reflow
full-width across the top.

Render one persistent `aria-live` region in the DOM at page load, polite for success and info,
assertive with `role="alert"` for errors. A toast never steals focus. Write human copy: outcome first,
next step for errors, and never a raw status code or stack trace.

### Empty, Loading, And Error States

Loading is a skeleton, not a spinner (ENFORCED). Every structured region that loads shows a skeleton
mirroring the shape of the content coming: a page, route, panel, table, list, card grid, detail view,
or dashboard section. Keep the chrome live; only the data region skeletons, and the table `<thead>`
never skeletons. One shared grey shimmer recipe, never tinted a brand color, slowed under
`prefers-reduced-motion`. The only permitted spinner is a small bounded wait inside a single control:
a button in flight, a field validating, a "load more" row.

An empty state is an icon (a registry glyph at 48px, never emoji), a short title, at most one muted
line, and one action. Three flavors, picked by why it is empty:

| Flavor | Trigger | Title | Action |
| --- | --- | --- | --- |
| No data yet | nothing created in this collection | "No `<things>` yet" | primary "Add `<thing>`" |
| No results | a search or filter returned nothing | "No matches for '`<query>`'" | "Clear filters", never "Add" |
| Post-action / cleared | the user emptied it themselves | "All clear" | usually none |

Error is the third sibling, in the same slot when the data failed to load: a danger icon, a human
message with no raw status codes, and a Retry action. A failed fetch must never silently render as an
empty state.

Pick the scope first. A page or region state fills the content area and keeps the shell and page
header; a table state renders in place of the body and keeps the card, toolbar, search, and column
header. Never shift layout when the data lands. Announce via `aria-busy` and `aria-live="polite"`,
with `role="alert"` on error, and never steal focus.

### Breadcrumbs

Render only for destinations deeper than about three levels; a shallow page gets none. One trail per
page, in the content area above the page title, kept live while the data region skeletons.

Smart links (ENFORCED): a crumb that resolves to a real route is a link; a section-grouping label with
no destination is plain muted text; the current page is emphasized, non-link, and carries
`aria-current="page"`. Never render a dead link, including an ancestor the user lacks permission for.

Structure is `<nav aria-label="Breadcrumb">` wrapping an `<ol>`, with `aria-hidden` chevron
separators. Muted ancestors, bold current, compact 12px row. Collapse a trail longer than four crumbs
to Home, an overflow control, and the last two. On a narrow or portrait viewport collapse to the last
two crumbs preceded by a back affordance.

### Tabs

The in-canvas navigation tab strip, bounded to depth overflow and record facets. Full contract in §6.

### Search And Filter

Search is free-text matching across fields; a filter narrows by a known facet. A list often has both.

The search box is one shape: a flat pill with a 1px border, 8px radius, a leading magnifier from the
registry, and a borderless input. The clear control appears only when the field has text; never a
permanently visible empty one. Name the searchable fields in the placeholder rather than a bare
"Search...". Filtering already-loaded rows is instant; only server round-trips are debounced, at about
250ms, showing an in-field spinner while in flight.

Pick the filter affordance by cardinality:

| Situation | Use |
| --- | --- |
| two to five mutually-exclusive states | underline filter tabs with a leading status dot |
| a few independent facets kept on screen | labelled dropdown fields, each independently clearable, plus a Reset button |
| independent facets whose applied set must stay visible | removable chips plus Clear all |
| six or more grouped criteria, or rarely changed | a `Filters (n)` popover with a count badge |
| picking one known entity from a large set | a typeahead combobox with full keyboard navigation |

Never signal a filter state by color alone; pair it with a label, dot, or checkmark. Never render a
facet, group, or tab whose behavior is not wired; omit it rather than showing it disabled. The "Soon"
placeholder rule applies only to navigation destinations. Every search and filter ships a no-results
state with a Clear filters escape.

### Date And Time

Use the brand-styled native input: `type="date"` for a single day, `type="datetime-local"` for date
plus time. Never build a custom calendar popover. No native range input exists, so a range is two
linked `type="date"` fields where the end's minimum follows the chosen start and the start's maximum
follows the chosen end. Express bounds with `min` and `max`; validate cross-field rules on submit
through the forms error pattern. Style the field shell and keep the native calendar indicator visible.
Add quick presets (Today, Last 7 days, This month) only when the picker filters a list, and only wired
ones.

## 9 · Conventions And Guardrails

- Status roles carry fixed meaning: success is healthy or confirmed, warning is pending or needs
  attention, danger is destructive or error, info is informational, neutral is draft. Never repurpose
  a role or reach for an ad-hoc color. Keep one `state -> classes` map with a neutral fallback; never
  hard-code a color per status inline.
- Status color is never the only signal. Always pair it with text or an icon.
- Soft-delete by default. Destructive deletes route to the recycle bin with a worded confirmation
  ("Move X to Recycle Bin?"); only an admin permanently deletes, and "empty everything" requires
  typing a confirm word.
- Authorization in views gates row and page actions with policy checks, not merely by hiding links.
- Pagination appears only when there is more than one page.
- Iconography is stable: the same glyph always denotes the same concept or action.
- Accessibility meets WCAG AA: full keyboard navigation, ARIA labels on custom controls, an
  always-visible focus indicator on every interactive element, and respected reduced-motion. Never
  remove a focus outline without an equivalent replacement.
- Responsive is mobile-first. Tables scroll inside a horizontal-scroll container, the sidebar keeps
  its collapsible rail (with the optional off-canvas drawer on very small screens), and complex
  drag-and-drop authoring may be desktop-only; say so in the UI.
- Constrain any horizontally-scrolling strip to horizontal scroll only, so a stray vertical scrollbar
  or arrows do not appear.
- Externalize user-facing strings and format dates and currency per locale; support RTL where tenants
  need it.
- Remove filler, lorem-ipsum, and stand-in copy before delivering, and ship no default or un-themed
  component styling where the standard applies.
- When the styling framework purges unused classes, run the build after editing classes or icons so
  the primitives survive.

### Global Anti-Patterns (ENFORCED, never)

- Change, re-derive, or "improve" the theme: no new palettes, surfaces, fonts, logos, shadows, or
  per-app re-skins.
- Move standing navigation to the top bar, or replace the sidebar with top tabs or a hamburger,
  except the mobile collapse. In-canvas content tabs are fine.
- Add, invent, rename, or reorder a navigation cluster beyond the four fixed ones. Using only a
  subset is fine.
- Open a fourth accordion level.
- Put action buttons or a global search bar in the top bar.
- Use JS-driven responsive logic, or maintain separate mobile and desktop codebases. Pixel and
  aspect-ratio breakpoints are fine.
- Hardcode colors, or use system fonts where Montserrat and Source Sans 3 are specified.
- Skip loading, empty, or error states.
- Vary button colors by domain, use Green Gold for destructive, or use Jelly Bean Blue for a button.
- Invent, generate, recolour, or plate a logo. Use the bundled per-theme assets as supplied.
- Invent a project-specific variant of a pattern the standard defines.

### Deviating From A Principled Default

Every rule in the design system is tagged ENFORCED (follow exactly) or PRINCIPLED (a sensible default,
deviation allowed with written justification). To deviate from a principled default, state: the
standard pattern, the proposed deviation, the rationale in two or three sentences, the domain context,
and the trade-offs acknowledged. Get sign-off before generating. Anything ENFORCED - every token
value, brand asset, and theme decision - is not deviable, and a deviation the developer does authorize
is recorded as a documented exception in the App Definition, never applied silently.

## 10 · Generation Checklist

Produce and report the following, in order, in the confirmed stack:

- [ ] Resolve inputs first (§1). Confirm the UI stack, the app name, the title-bar name, the
      navigation tree, the entities, the roles, and `<BRAND_ASSETS_PATH>`. Never ask about, offer, or
      vary the theme, colors, fonts, logos, or favicon.
- [ ] Copy the brand asset pack to `<BRAND_ASSETS_PATH>` (or confirm the files are already there) and
      reference them from it, per theme.
- [ ] Navigation config: the clusters the app uses in the fixed order, with leaf and group nodes,
      icons, and access keys, nested at most three levels (§2).
- [ ] Access config: policy keys mapped to roles and flags (§2, §7).
- [ ] Gate helpers: `canAccess`, `canAccessKey`, `clustersFor`, `filterNodes` (§2).
- [ ] Role and policy layer: tiers, `visibleOwnerIds()`, `teamMemberIds()`, the query scope, one
      per-record policy per ownable entity, and the permanent-delete gate (§7).
- [ ] Design-token file: every token in §4, in both themes, with `color-scheme` declared per theme.
- [ ] Icon registry: one central inline-SVG registry in the fixed style, with a meaningful icon for
      every nav node plus the action and status icons the screens reference (§4).
- [ ] App shell (§3): full-height rail owning the top-left corner, the top bar over the canvas only,
      visible dividers between the three regions, the rail head matched to the 52px top-bar height,
      a pinned rail head and filter with only the nav list scrolling, the filter matching group labels
      too, the `i-panel` toggle in both states, the collapsed overlay swap on hover and focus, legible
      collapsed flyouts that escape the scroll clip and use hover-intent and in-app routing, and a
      flicker-free collapse animation.
- [ ] Top bar: app name, notifications, theme switcher, profile menu. No nav tabs, no global search
      bar, no action buttons.
- [ ] Profile menu: identity block as one clickable row, the fixed Appearance section with the
      System / Dark / Light segmented control, then sign out. Wire the logo and favicon theme swap.
- [ ] Auth screens, standalone, with SSO when enabled (§5.7).
- [ ] One screen per entity screen in the App Definition, from the matching archetype (§5), wired to
      routes, with record facets on the in-canvas tab strip (§6).
- [ ] Profile page plus sign-out wiring.
- [ ] When enabled: the customizable widget dashboard, the recycle bin, and the audit log.
- [ ] When `ai_model_catalog` is on: the catalog list, the add/edit form with the typed row repeater
      and the test-before-save footer, and the Run screen with the call-result panel (§5.10). The
      catalog under `System Administration`, admin-gated; the Run screen's cluster asked, not chosen.
- [ ] Every data-driven view covers success, empty (both flavors), loading (skeleton), and error.
- [ ] Accessibility pass: keyboard reach, focus rings, `aria-current` on active nav and tabs, labelled
      custom controls, contrast, and reduced motion.
- [ ] A short README noting the file map, the stack used, the role matrix, and what is stubbed versus
      wired.
- [ ] State every assumption made for an App Definition field left blank.

Generate structure that is wired and navigable, with realistic empty states. Not dummy data, and not
finished business logic. The team fills domain logic into a working skeleton.

## 11 · Appendix

### What The Developer Declares

The UI stack and charting library, the app name and title-bar name, the optional tagline,
`<BRAND_ASSETS_PATH>`, the navigation tree under the four fixed clusters, the entities, the roles and
their scopes, the feature toggles, and the domain copy. Nothing visual: the theme, tokens, palette,
surfaces, fonts, logos, favicons, icon style, shell architecture, archetypes, and status-role meanings
are approved constants.

### Three Config-Driven Layers To Build First

1. Navigation tree: the clusters the app uses, holding leaf and group nodes (§2).
2. Access-policy map: policy keys mapped to roles and flags (§2, §7).
3. Gate engine: recursively filters the tree (`clustersFor`) and gates handlers (`canAccessKey`). The
   shell consumes the filtered tree (§2, §3).

### Starter Icon Registry

Semantic concepts, rendered as `i-<concept>` symbols in the one registry. Give every nav node a
meaningful one.

`i-grid` (dashboard) · `i-apps` (modules) · `i-document` · `i-list` · `i-table` · `i-plus` ·
`i-trash` · `i-pencil` · `i-eye` · `i-inbox` · `i-clipboard` (audit, task list) · `i-users` ·
`i-user` · `i-user-arrow` (assignment) · `i-shield-check` (security) · `i-scales` (governance) ·
`i-key` (access, credentials) · `i-cog` (settings) · `i-adjustments` · `i-plug` · `i-globe`
(environment, region) · `i-headset` (support) · `i-bell` (notifications) · `i-search` ·
`i-filter` (outlined inactive, solid active) · `i-panel` (the rail collapse AND expand toggle) ·
`i-chevron-down` (accordion, flyout hint) · `i-chevron-right` · `i-arrow-left` (back link) ·
`i-x` (close) · `i-sun` / `i-moon` (Appearance) · `i-logout` · `i-check-circle` · `i-chart-bar` ·
`i-upload` · `i-plug-off` (failed to load) · `i-sparkles` (AI model, model catalog) ·
`i-play` (run a prompt) · `i-braces` (raw or structured payload)

Add domain glyphs in the same style so each destination is recognizable at a glance. The goal is
meaningful, never decorative.

### Archetype Quick Map

| Need | Archetype |
| --- | --- |
| Cluster landing, KPIs, widgets | §5.1 Dashboard |
| Browse a collection | §5.2 List / index |
| Inspect one record | §5.3 Detail / show |
| Create or edit a record | §5.4 Form |
| Manage config, integrations, secrets | §5.5 Settings / config |
| Configure an outbound connection | §5.5 Settings, test-before-save sub-pattern |
| Multi-step authoring plus lifecycle | §5.6 Builder (hub and spoke) |
| Sign in, SSO | §5.7 Auth |
| Restore or purge deleted records | §5.8 Recycle bin |
| Show an action's outcome | §5.9 Status / result |
| Manage AI model definitions, or send a prompt to one | §5.10 AI model catalog (composite) |
| Edit a variable-length list of rows in a form | §8 Typed row repeater |

## Mockup Mode

How to generate a throwaway mockup of an application screen. This is a workflow, not a design rule.
Every design decision still comes from §1 to §11.

### Rotate The Domain

A mockup batch must exercise the patterns across different business domains, not the same one
repeatedly. Each run targets a different category than the previous one. Rotate through at least:

`HR · Multi-Tenant Admin · IT Service Management · Finance · Learning / LMS · CRM / Sales ·
Procurement · Helpdesk / Support · Inventory / Assets · Project Management`

For the chosen domain, vary the app name, navigation tree, entities, KPIs, statuses, and page content
so the mockup reads like that domain's real app: HR gives Employees, Leave, Payroll; IT gives Tickets,
Assets, Changes; multi-tenant admin gives Tenants, Plans, Usage, Billing. State which domain was
picked. The requester may always name the domain to override the rotation.

The app name follows the approved company name, for example "CLaaS2SaaS HR" or "CLaaS2SaaS IT Service
Desk". The domain changes the content and the nav tree. It never changes the theme, the clusters, or
any archetype.

### Output

- Default to one self-contained, directly viewable HTML file: the tokens as CSS custom properties in
  both themes, minimal vanilla JS for the accordion, the rail collapse and expand, the theme menu, and
  the overlays.
- Copy the per-theme asset pack (wordmark, C2S mark, favicon) from `assets/` alongside the output and
  reference it by relative path. Never inline the images as base64, and never recolour or plate them.
- Wire the theme switcher fully, including the logo and favicon swap.
- Mobile-first and responsive, with realistic empty, loading (skeleton), and error states. No
  lorem-ipsum beyond what makes the mockup read as real.
- Always include the in-canvas tab strip, even when it was not asked for, and demonstrate both
  sanctioned uses: a sidebar branch nested to the three-level limit whose overflow leaf becomes a
  tab-strip page, and a detail page reached from a list row showing an `Overview · ...` facet strip.
- Optionally include a view-as-role demo switcher at the foot of the profile menu, after sign out,
  that re-renders the sidebar for the chosen role, since the sidebar is role-filtered. Tag it clearly
  as template-only, with a small pill plus an info tooltip stating it is a preview aid, not a shipped
  capability and not tied to any real account. The top bar still stays button-free; this control
  belongs inside the profile menu.
- If a stack is declared, emit in that stack instead of standalone HTML.
