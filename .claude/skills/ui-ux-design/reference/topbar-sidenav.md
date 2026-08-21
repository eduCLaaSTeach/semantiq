# Top Bar & Side Nav (App Shell)

The application shell: a slim top bar over a collapsible left sidebar (NavRail) and a scrolling main
area. Use when building the shell or primary navigation for any module.

## Contents

- Shell structure
- Tokens & values
- Top bar
- Side nav (NavRail) - brand block, the four fixed clusters, features (nested groups ≤3 levels & leaves), depth-overflow tabs
- Collapse / expand (brand block) & collapsed mode
- Icons
- Responsive
- Render only functional UI
- Accessibility
- Do / Don't

---

## Shell structure

A **full-height NavRail** on the left that **owns the top-left corner**, beside a **right column**
holding a slim top bar over the scrolling main. The top bar spans **only the main column** - not
full-width across the top above the rail. The **logo lives in the NavRail brand block**; the top bar
carries the **app name** and utilities.

```
┌───────────────┬──────────────────────────────┐
│  [LOGO]    ◫  │  [App name]      🔔 ◐ avatar │  ← rail head + top bar = one row,
│  🔍 filter...   ├──────────────────────────────┤    SAME height, dividers aligned
│  NavRail      │                              │
│  (expanded /  │   Main content               │
│   collapsed)  │   (overflow: auto)           │
└───────────────┴──────────────────────────────┘
```

```css
/* The rail is the LEFT column (full height); the top bar + main are the RIGHT column.
   A grid keeps the rail full-height and the top bar over the canvas only. If the rail + main
   are wrapped in a container, give that wrapper `display: contents` so they place into the grid. */
.app-shell {
  display: grid;
  grid-template-columns: auto 1fr;   /* rail width · everything else */
  grid-template-rows: auto 1fr;      /* top-bar row (its own height) · content row */
  height: 100vh;
  overflow: hidden;
  background: var(--color-bg-page);   /* canvas: #E8DFD0 light / #1A2E46 dark */
}
.app-body { display: contents; }     /* let the rail + main place directly into the shell grid */

.rail-container {                     /* sidebar: spans BOTH rows → full height, owns the corner */
  grid-column: 1; grid-row: 1 / 3;
  position: relative;
  width: 240px;                       /* the `auto` grid column sizes to this */
  transition: width 0.28s cubic-bezier(0.4, 0, 0.2, 1);   /* width animates on toggle */
}
.rail-container.collapsed { width: 56px; }   /* icon-rail width */

.top-nav  { grid-column: 2; grid-row: 1; }   /* top bar: over the canvas only, not above the rail */
.app-main {
  grid-column: 2; grid-row: 2;
  min-width: 0; min-height: 0;
  overflow: auto;                    /* main scrolls; shell does not */
  background: var(--color-bg-page);
}
```

**Rules (ENFORCED):**

- The **rail is full height** and owns the top-left corner; the **top bar sits only over the main column**, never full-width above the rail.
- Render a **visible divider** between the three regions (rail | top bar | canvas).
- The **rail head (brand block) is the same height as the top bar**, so their bottom dividers form **one continuous line at every breakpoint** (large, medium, small).
- Only `.app-main` scrolls; the rail animates between its expanded and collapsed widths and persists state (see Collapse / expand). On small screens, see Responsive.

---

## Tokens & values

The shell values are approved; the single source of truth is `design-tokens.md` (chrome, canvas,
nav-active, divider, chrome-edge values, both themes). The sidebar and top bar share **one chrome
surface per theme**, clearly distinct from the main canvas - and the two themes' chromes are **different
colors by design**.

| Role | Light | Dark | Used for |
|------|-------|------|----------|
| Chrome (rail + top bar, ONE color) | White `#FFFFFF` | Ink navy `#080F1A` | Sidebar and top-bar background |
| Canvas | Warm platinum `#E8DFD0` | Lifted navy `#1A2E46` | Main working area |
| Chrome text | `#223349` | `#E4EBF4` | App name, nav labels |
| Chrome muted text | `#566779` | `#9AABC0` | Cluster labels, nav icons, filter placeholder |
| Divider | `#E4DCCD` | `rgba(255,255,255,0.13)` | Region borders (rail edge, bar bottom, rail head) |
| Chrome edge shadow | `rgba(15,25,45,0.14)` | `rgba(0,0,0,0.50)` | Soft shadow lifting the chrome off the canvas |
| Active nav (gold treatment) | tint `rgba(179,161,37,0.16)`, fg `#5C5010`, bar `#B3A125` | tint `rgba(201,182,47,0.22)`, fg `#EBDD7E`, bar `#C9B62F` | Active item background + label + 3px left bar - **Green Gold tint, not a status role** |
| Notification dot | `#991547` | `#F3AFC9` | Unread indicator on the bell |

> **Dark theme:** every value above is approved for both themes in `design-tokens.md`. Author against
> the tokens; do not hardcode the light values into component CSS. The active-nav tint derives from
> Green Gold - a status-role color is never repurposed for non-status state.

**Dimensions (approved):** top bar and rail head `52px` tall; rail `240px` expanded / `56px` collapsed;
icon buttons `34px`, avatar `30px`; wide logo `height: 22px; width: auto` (expanded), C2S mark in a
`40×34` `object-fit: contain` slot when collapsed; controls radius `8px`. The rail's nav list hides its
scrollbar visually (`scrollbar-width: none` + hidden webkit scrollbar) while staying fully scrollable.

---

## Top bar

Three-column grid (`auto 1fr auto`): **app name** · spacer · utilities. The top bar carries **no
navigation tabs**, **no global search bar**, and **no action buttons** - a primary action (e.g. a
create/"New" button) lives in the **page header** of the archetype, not here. (In-canvas content tabs -
depth-overflow nav and record facets - live in the work area; see `tabs.md`.)

```css
.top-nav {
  display: grid;
  grid-template-columns: auto 1fr auto;
  align-items: center;
  height: 52px;
  padding: 0 18px;
  background: var(--chrome-bg);          /* #FFFFFF light / #080F1A dark */
  border-bottom: 1px solid var(--divider);
  box-shadow: 0 2px 10px var(--chrome-edge);
  flex-shrink: 0;
  position: relative;          /* stacking context for dropdowns */
  gap: 12px;
}
.d-none { display: none !important; }   /* overlay hide utility */
```

### App name (left)

The top bar's left shows the **application name** as text (the logo lives in the sidebar brand block).

```html
<div class="nav-left">
  <span class="nav-app-name"><App Name></span>
</div>
```

```css
.nav-left { display: flex; align-items: center; gap: 16px; }
.nav-app-name {
  color: var(--chrome-text);             /* #223349 light / #E4EBF4 dark */
  font-family: var(--font-heading);
  font-size: 1rem;
  font-weight: 600;
  user-select: none;
}
```

> The app name may switch by route (e.g. a default and a distinct name on a cluster's routes). It comes from the App Definition; never invent it.

### Right controls (utilities)

Order: **notifications → theme switcher → profile menu** (no primary-action button in the top bar - the
archetype's primary CTA lives in its page header).

```css
.nav-right { display: flex; align-items: center; gap: 4px; position: relative; }

.nav-icon-btn {
  display: flex; align-items: center; justify-content: center;
  width: 34px; height: 34px;
  border: none; background: none; border-radius: 8px; cursor: pointer;
  color: var(--chrome-text);             /* #223349 light / #E4EBF4 dark */
  position: relative;          /* for badge-dot */
}
.nav-icon-btn:hover { background: var(--chrome-hover); }   /* rgba(25,62,107,0.07) light / rgba(255,255,255,0.09) dark */

.badge-dot {                    /* render only when unread > 0 */
  position: absolute; top: 5px; right: 5px;
  min-width: 8px; height: 8px;
  border-radius: 999px;
  background: var(--alert-dot);  /* #991547 light / #F3AFC9 dark - chrome-safe alert color */
  box-shadow: 0 0 0 2px var(--chrome-bg);
}

.user-avatar {
  width: 30px; height: 30px; border-radius: 50%;
  background: var(--color-primary); color: var(--color-primary-contrast);   /* fallback initials; contrast #FFFFFF light / #0F1C2E dark */
  box-shadow: 0 0 0 2px var(--divider);
  font-size: 0.75rem; font-weight: 600;
  display: flex; align-items: center; justify-content: center;
}
```

**Appearance (theme switcher):** the profile menu's **fixed section labeled Appearance** offers **System
/ Dark / Light** (segments in that left-to-right order); persist the choice and apply it via `[data-theme]`
on the root (System follows `prefers-color-scheme`). Present the three options as **one connected
horizontal segmented control** filling the menu width in equal segments, split by thin dividers (one icon
segment per option, the active one highlighted, each with an accessible name; **not** detached buttons,
**not** a vertical list) - not a blind click-to-cycle; default **System** and reflect the current choice.
Being a **low-frequency control**, it does **not** need to be a standing top-bar icon - it may live
**inside the profile/account menu**. When in the menu, its **fixed label is Appearance**, carrying a
**leading icon** so the row matches the others (My Profile / Sign out). The switcher is mandatory; every
token is defined in both themes in `design-tokens.md`, so wiring the switcher is all dark mode needs.

**Avatar initials:** empty name → `?`; 2+ words → first letter of first + last word, upper; else first 2
chars, upper. (`"John Doe"` → `JD`.)

### Overlays (dropdowns/panels)

Mutually exclusive - opening one closes the others. Shown/hidden by toggling `.d-none` (`display:none`),
never `visibility`/`opacity` (the element must leave layout). Dismiss on outside click via a `document`
listener attached ~10ms after open.

**User dropdown (profile menu)** - anchored under `.nav-right`. Lead with an **identity block**: the
avatar beside the user's name with the email below, as a **single clickable row** (trailing chevron) that
opens the profile page - not a plain text link. Then the fixed **Appearance** section (the theme switcher
- its label carries a leading icon and its segments fill the menu width), then **Logout / Sign out**.
**Notification panel** - anchored under `.nav-notification-wrapper`; renders an empty state (`No new
notifications`) until wired to data.

```css
.user-dropdown, .notification-panel {
  position: absolute;
  top: calc(100% + 8px);
  right: 20px;
  background: var(--card-bg);            /* #FFFFFF light / #253E5D dark */
  border: 1px solid var(--card-border);  /* #E8E2D8 light / rgba(255,255,255,0.10) dark */
  border-radius: 8px;
  box-shadow: var(--shadow-lg);          /* overlay: 0 8px 24px rgba(15,25,45,0.14) light / 0 10px 28px rgba(0,0,0,0.45) dark */
  z-index: 2000;
  overflow: hidden;
}
.user-dropdown { width: 280px; }
.notification-panel { right: 0; width: 320px; max-height: 400px; display: flex; flex-direction: column; }
.notification-list { overflow-y: auto; padding: 8px 0; }
.notification-empty {
  padding: 24px 20px; font-size: 0.875rem; text-align: center;
  color: var(--color-text-muted);        /* #4D5E75 light / #A3B2C5 dark */
}
.dropdown-divider { height: 1px; background: var(--card-border); }
.user-dropdown button, .notification-item {
  display: flex; align-items: center; gap: 10px;
  width: 100%; padding: 12px 20px;
  border: none; background: none;
  font-size: 0.875rem; text-align: left; cursor: pointer;
  color: var(--color-text);              /* #1E2E42 light / #E9EFF6 dark */
}
.user-dropdown button:hover, .notification-item:hover { background: var(--surface-hover); }   /* rgba(25,62,107,0.06) light / rgba(255,255,255,0.07) dark */
```

---

## Side nav (NavRail)

Vertical rail: **brand block** (logo + collapse control) → the **four fixed clusters** (`Workspace`,
`Compliance`, `Application Administration`, `System Administration`) → **features** rendered as accordion
groups (**nested up to 3 levels** within a cluster) or permission-gated leaves. The four clusters render
**top-to-bottom in this fixed order**, each with a home - **Workspace** (day-to-day work), **Compliance**
(audit logs, activity trails), **Application Administration** (the app's own admin: users, roles, app
settings), **System Administration** (configuration, integrations, platform settings). They are a
**closed set** - an app uses only the ones it needs and omits the rest; never add, invent, rename, or
reorder a cluster beyond these four. A **leaf** under the third-level group that itself needs children
promotes those children to an **in-canvas horizontal tab strip** (`tabs.md`) - never a fourth accordion
level.

```css
.nav-rail {
  display: flex; flex-direction: column;
  width: 100%; height: 100%;
  overflow: hidden;                 /* the rail does NOT scroll as a whole - the head stays pinned */
  background: var(--chrome-bg);              /* #FFFFFF light / #080F1A dark */
  border-right: 1px solid var(--divider);    /* #E4DCCD light / rgba(255,255,255,0.13) dark */
  box-shadow: 2px 0 10px var(--chrome-edge); /* lifts the chrome off the canvas */
}
/* Pinned head + scrolling body: the brand block (and the filter, if any) are flex-shrink:0; the
   cluster/group list lives in a scroll region (.nav-body) that is the ONLY thing that scrolls. */
.rail-brand, .nav-filter { flex-shrink: 0; }
.nav-body { flex: 1; min-height: 0; overflow-y: auto; overflow-x: hidden; padding-bottom: 20px; }
```

### Brand block (logo + the only collapse/expand control)

The brand block sits at the top of the rail. It **displays the company logo image** in a reserved
rectangular area - the **wide** logo when expanded, the **short/compact** mark when collapsed - and it is
the **home link**. It is also the **only** collapse/expand control in the shell.

**The rail-toggle glyph (ENFORCED):** the rail collapse/expand control uses **one** icon - the **panel /
sidebar-toggle glyph** from the bundled registry, symbol **`i-panel`** (a rounded rectangle with a
vertical divider) - the **same glyph in both states**, expanded and collapsed. (This is the rail toggle
only; accordion **group** headers use a chevron-down, and a collapsed **group** icon may show a small
chevron hint - different controls, see Groups and Collapsed mode.)

- **Expanded:** wide logo (clickable → home) on the left, the **`i-panel` toggle icon** on the right (collapses the rail).
- **Collapsed (ENFORCED - overlay swap, like ChatGPT):** the short/compact mark and the `i-panel` expander share **one** fixed `40×34` slot (`object-fit: contain`) and cross-fade. At rest show **only** the short mark (clickable → home); on **hover OR keyboard-focus** of the brand block show **only** the `i-panel` expander on an opaque overlay that covers the slot exactly. Activating the block (click / `Enter` / `Space`) re-expands the rail, and the collapsed state persists.

Both logos come from the approved per-theme asset pack bundled with this skill (asset rules in
`assets.md`): the wide wordmark and the C2S short mark, each in a light and a dark variant. The only
developer input is the in-project destination `<BRAND_ASSETS_PATH>`, recorded in
`.claude/PROJECT-CONTEXT.md` - ask if it is unrecorded, never choose it, and **never invent a logo**.

**Pinned head (ENFORCED):** the brand block (and a nav filter, if present) **stays fixed** - only the
**nav list** scrolls, so the logo/filter never scroll out of view. Make the brand block the **same height
as the top bar** so their bottom dividers line up. Implement by making the NavRail a flex column whose
brand block + filter are `flex-shrink: 0` and whose nav body is the only scrolling region. Keep the logo
**as supplied** - never recolour, box, pad, or shrink it to force it onto a surface. The bundled pack
includes a **per-theme variant** of each logo (navy/gold on light chrome, white/gold on dark chrome) -
swap them with the effective theme; the logo sits directly on the chrome at its **natural size**.

```html
<div class="rail-brand" role="button" tabindex="0" aria-label="Home">
  <!-- per-theme pairs; <BRAND_ASSETS_PATH> is the developer-confirmed asset location from
       PROJECT-CONTEXT (ask if unrecorded - never choose it). The theme swap hides the off-theme variant. -->
  <img class="rail-logo-wide logo-light"  src="<BRAND_ASSETS_PATH>/logo-full-light.png"  alt="CLaaS2SaaS" />
  <img class="rail-logo-wide logo-dark"   src="<BRAND_ASSETS_PATH>/logo-full-dark.png"   alt="CLaaS2SaaS" />
  <img class="rail-logo-short logo-light" src="<BRAND_ASSETS_PATH>/logo-short-light.png" alt="CLaaS2SaaS" />
  <img class="rail-logo-short logo-dark"  src="<BRAND_ASSETS_PATH>/logo-short-dark.png"  alt="CLaaS2SaaS" />
  <!-- Collapsed expander: the SAME i-panel icon, overlaid on the mark's slot on hover/focus. -->
  <span class="rail-expander" aria-hidden="true"><svg class="ic"><use href="#i-panel" /></svg></span>
  <!-- Expanded collapse control: the ONE rail-toggle icon - i-panel; the same glyph appears on the
       collapsed expander above. -->
  <button class="rail-collapse" aria-label="Collapse navigation" title="Collapse navigation">
    <svg class="ic" aria-hidden="true"><use href="#i-panel" /></svg>
  </button>
</div>
```

```css
.rail-brand {
  position: relative;
  display: flex; align-items: center; justify-content: space-between;
  gap: 8px; padding: 12px; min-height: 52px;   /* = top-bar height - the bottom dividers align */
  border-bottom: 1px solid var(--divider); cursor: pointer;
}
/* Wide logo (expanded): fixed HEIGHT, auto width - preserve the wordmark's aspect ratio (never crop or
   distort it, per assets.md). max-width bounds it inside the brand block; object-fit: contain letterboxes
   (never cover/crop) if ever clamped. */
.rail-logo-wide  { height: 22px; width: auto; max-width: 100%; object-fit: contain; }
.rail-logo-short { height: 24px; width: auto; }   /* expanded fallback; collapsed scope fixes the 40×34 slot */

/* The ONE rail-toggle control (expanded state): carries the i-panel icon - the SAME icon appears on
   the collapsed expander below. */
.rail-collapse {
  display: flex; align-items: center; justify-content: center;
  width: 28px; height: 28px; border: none; background: none;
  border-radius: 8px; cursor: pointer;
  color: var(--chrome-muted);            /* #566779 light / #9AABC0 dark */
}
.rail-collapse:hover { background: var(--chrome-hover); color: var(--chrome-text); }
.rail-collapse .ic { font-size: 18px; }   /* icon sized via font-size, per assets.md */

/* Collapsed expander: the SAME i-panel icon on an opaque overlay. Invisible at rest -
   visibility:hidden keeps it out of the tab order and lets it fade rather than hard-cut. */
.rail-expander {
  position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
  width: 40px; height: 34px;        /* same fixed slot → covers the mark exactly */
  display: flex; align-items: center; justify-content: center;
  background: var(--chrome-bg);     /* opaque chrome surface: #FFFFFF light / #080F1A dark */
  color: var(--chrome-text);        /* #223349 light / #E4EBF4 dark */
  border-radius: 8px;
  opacity: 0; visibility: hidden; transition: opacity 0.12s ease;
}
.rail-expander .ic { font-size: 17px; }

/* Collapsed (ENFORCED - overlay swap): the brand mark occupies a FIXED 40×34 slot. The short mark
   and the expander share that exact slot and only cross-fade opacity - no surface-matching or sizing
   guesswork. No dim-and-keep, no glyph beside the mark, no reopen button. */
.rail-container.collapsed .rail-brand { position: relative; justify-content: center; padding: 0; }   /* min-height keeps the head 52px */
.rail-container.collapsed .rail-logo-wide,
.rail-container.collapsed .rail-collapse { display: none; }   /* collapsed: the whole brand block is the expander */

/* fixed slot for the short mark; the image letterboxes inside it - never cropped */
.rail-container.collapsed .rail-logo-short {
  display: block;
  width: 40px; height: 34px;        /* fixed slot - the 40×34 collapsed mark slot */
  object-fit: contain;              /* letterbox inside the slot - never cover/crop the logo */
  border-radius: 8px;               /* matches the collapsed icon-square radius */
  transition: opacity 0.12s ease;
}

/* hidden, not dimmed - swap the short mark for the SAME i-panel expander on hover OR keyboard focus */
.rail-container.collapsed .rail-brand:hover .rail-logo-short,
.rail-container.collapsed .rail-brand:focus-within .rail-logo-short { opacity: 0; }
.rail-container.collapsed .rail-brand:hover .rail-expander,
.rail-container.collapsed .rail-brand:focus-within .rail-expander { opacity: 1; visibility: visible; }
```

### Groups

Each group = a header button + an accordion body. Body animates via `max-height` toggled between `500px`
(open) and `0` (closed). Groups default to open; the group holding the active route auto-expands and
shows the active-trail tint. Each group's open/closed state persists across navigation. A `1px` divider
sits between groups. **Never name a group the same as its cluster** (e.g. a "System Administration" group
inside the System Administration cluster reads as a bug) - name groups for what they hold.

```css
.group-header {
  display: flex; align-items: center; gap: 8px;
  padding: 8px 12px; min-height: 40px; width: 100%;
  border: none; background: none; cursor: pointer;
  color: var(--chrome-text); font-size: 16px; font-weight: 600;   /* #223349 light / #E4EBF4 dark */
  text-align: left; border-radius: 8px; margin: 0 4px;
}
.group-header:hover { background: var(--chrome-hover); }
.group-chevron { transition: transform 0.15s ease; }      /* 0deg open, -90deg collapsed */
.group-items-wrapper { overflow: hidden; transition: max-height 0.28s ease; }
```

> **Example features:** under a cluster, a tree might have groups such as `Operations` and `Support` (each holding leaves) alongside plain leaves - define your own from the App Definition.

### Nested groups & depth limit

Groups may **nest up to three accordion levels** within a cluster (a subgroup is a group whose accordion
body contains further groups). The **cluster** (`Workspace`, `Compliance`, `Application Administration`,
or `System Administration`) is a heading, not an accordion group, and does **not** count toward the three
(a leaf is not a level either). Render a nested subgroup with the same `group-header` + accordion-body
shape, indented one step; the active route auto-expands the whole open trail and each subgroup persists
its own open/closed state.

A **leaf** sitting under the **third-level** group that still needs its own children does **not** force a
fourth accordion level. That leaf becomes a **routable page**, and its children render as a **horizontal
in-canvas tab strip** on that page (`tabs.md`) - never a deeper accordion (a level-3 group whose children
are plain leaves stays an accordion). Tabs are terminal: a tab panel holds content, not more tabs.

The structure below is the rule - roles and levels only:

```text
Cluster  (fixed heading - not a level, just a title)
└─ Group ............................. level 1   (accordion)
   └─ Group .......................... level 2   (accordion)
      └─ Group ....................... level 3   (accordion - the limit)
         ├─ Leaf                                 (link)
         ├─ Leaf  →  tab-strip page              (link that needs its own sub-areas)
         └─ Leaf                                 (link)
```

Build the real tree from the App Definition - different apps need different panels, most use only 1-2
levels. A concrete illustration of the same structure (names are an **example only**):

```text
System Administration     ← cluster heading - NOT a level (a fixed title)
  Platform                ← group · level 1
    Security              ← group · level 2
      Sign-in Methods     ← group · level 3   (the limit - deepest accordion)
        Password          ← leaf   (a leaf is NOT a level)
        SSO / SAML  ›     ← leaf that needs its own sub-areas → routable page with a TAB STRIP (tabs.md)
        Passkeys          ← leaf
    Data Retention        ← leaf
```

```css
/* Indent each nested level one step so the tree reads as a hierarchy. */
.group-items-wrapper .nav-group { --nav-depth-pad: 12px; }
.group-items-wrapper .nav-group .group-header,
.group-items-wrapper .nav-group .nav-item { padding-left: calc(16px + var(--nav-depth-pad)); }
```

> **Collapsed rail:** a cluster/group flyout (see Collapsed mode) shows the group's subtree; nested subgroups expand inline within the flyout. Keep it keyboard-reachable and dismiss on blur/`Escape`.

### Nav items

```css
.nav-item {
  display: flex; align-items: center; gap: 8px;
  padding: 10px 16px; min-height: 40px;
  color: var(--chrome-text); text-decoration: none;
  border-radius: 8px; margin: 0 4px; font-size: 14px;
  border-left: 3px solid transparent;     /* placeholder for active bar */
}
.nav-item:hover { background: var(--chrome-hover); }

.nav-item.nav-item-active {
  background: var(--color-nav-active-bg);   /* gold tint: rgba(179,161,37,0.16) light / rgba(201,182,47,0.22) dark */
  color: var(--color-nav-active-fg);        /* #5C5010 light / #EBDD7E dark */
  border-left-color: var(--color-nav-active-bar);   /* 3px gold bar: #B3A125 light / #C9B62F dark */
}

/* Not-yet-built destinations stay visible but disabled with a "Soon" pill. */
.nav-item.is-soon { opacity: 0.55; pointer-events: none; }
.nav-item .soon-pill {
  margin-left: auto; font-size: 0.625rem; font-weight: 600;
  padding: 1px 6px; border-radius: 999px;
  background: var(--soon-pill-bg);   /* #EFEAE0 light / rgba(255,255,255,0.16) dark */
  color: var(--soon-pill-fg);        /* #514A3B light / #DCE4F0 dark */
}
```

**Active detection:** item is active when `pathname === item.path` or `pathname.startsWith(item.path +
'/')`. Set `aria-current="page"` on it.

**Permission gating (ENFORCED):** items the user's roles cannot access render `null` - never disabled,
never hidden-but-present. Omit before paint. (Distinct from **not-yet-built** destinations, which render
disabled with a "Soon" pill - see Render only functional UI.)

---

## Collapse / expand (brand block) & collapsed mode

The collapse/expand control is the **brand block only** (see Brand block), carrying the **`i-panel` icon**
in both states. When collapsed, hovering **or keyboard-focusing** the brand block swaps the short mark for
the `i-panel` expander in the same slot, and activating it (click / `Enter` / `Space`) re-expands the rail.

**Collapsed mode (`width: 56px`):**

1. Group chevrons and nav-item/group labels hide by **opacity while staying in flow** (see Flicker-free below - never `position:absolute`/`width:0`, never `display:none`), leaving an icon rail.
2. Every nav item and group header becomes a **uniform centered 40px square** (icon only): fixed `40px`, `justify-content: center`, `border-radius: 8px`, **no left border** (the 3px active bar is dropped so the icon stays centered).
3. **Active item** renders as a full rounded square in the active-nav tint with an accent icon.
4. **Group flyout:** hovering **or keyboard-focusing** a **group** icon reveals that group's children in a **flyout panel** to the right of the rail; leaf items (no children) show a label **tooltip** instead. **ENFORCED** for the flyout/tooltip:
   - **Legible like the expanded rail.** Give every flyout entry (nested group titles and leaves) its **icon**, and **indent nested groups** so the hierarchy reads; set the flyout's own group header apart (e.g. a divider).
   - **Escape the rail's clipping.** The rail/nav list scrolls (clipping its overflow), so position the popover to **escape that clip** - `position: fixed` (coordinates from the icon) or a portal.
   - **Hover-intent, not instant-hide.** Keep it open while the pointer crosses the gap to it **and** while the popover itself is hovered (a short close delay + cancel-on-popover-hover). Keep the icon→popover gap small.
   - **Same navigation as the expanded rail.** Flyout entries route through the **same in-app handlers** as the expanded nav items - never a raw `href` that reloads or leaves the app.
5. **Flyout hint:** a collapsed **group** icon may show a small chevron hint (on hover/focus, in a theme-aware color) - on the group icon, **not** on the rail-head toggle.
6. The rail-head collapse/expand control uses the **one panel/sidebar-toggle icon** (`i-panel`) - the **same glyph in both states**.
7. State persists to `localStorage` (`sidebarCollapsed`), restored on load.
8. **Flicker-free (ENFORCED):** no flash, clip, wipe-in, pop, hard-cut, or vertical jump in **either** direction while the rail width animates. Use an **asymmetric delay**, not just clipping:
   - Hide expanded-only content (labels, chevrons, pills, wide logo, collapse control) by **opacity while it stays in flow** - never `position:absolute`/`width:0` (the out-of-flow snap *is* the flash and lets the icon shift).
   - **Fade out fast, zero delay on collapse** (gone before the rail narrows enough to clip); **fade in only after the width nears completion on expand** - put `transition-delay ~ width duration` on the **expanded-state** selector and `0` on the **collapsed-state** selector (a transition uses the timing of the state it goes *to*). Keep the expand delay just under the width duration - no blank "dead beat", no early wipe-in.
   - Keep each icon at a **consistent inset** (don't re-center when collapsed; pick an inset that already centers the icon at the collapsed width, e.g. `margin-left + border + padding ~ half the collapsed rail`).
   - Make the brand logos **absolute overlays** that cross-fade at fixed positions (no reserved box, so the collapsed rail stays centered and the mark never slides; a logo inside BOTH widths is never clipped, so it fades cleanly). The **collapse control** sits at the rail's edge, OUTSIDE the collapsed width: pinning it there lets the narrowing rail **clip it mid-fade** (abrupt cut), and edge-anchoring it with a fast fade makes it **jerk-slide**. Let it ride just *inside* the moving edge (never clipped) and fade with the **panel's own easing over a duration close to the width**, so it recedes/emerges *with* the rail.
   - Collapse vertical regions (filter, cluster labels, an open group body) with **`max-height`** (since `height: auto` can't transition) coupled to the width, so vertical reflow rides *with* the horizontal animation, not a t=0 snap.
   - Define any state-driven overlay (the collapsed expander) **unconditionally but invisible** so it **fades** instead of hard-cutting; drop hidden controls from the tab order with `visibility: hidden`/`disabled` (opacity alone leaves a focus stop); honor `prefers-reduced-motion` by making the change instant.

```css
.nav-flyout {
  position: fixed; left: 60px; min-width: 200px;   /* fixed escapes the rail's clip; 60px sits just past the 56px rail */
  background: var(--card-bg);            /* #FFFFFF light / #253E5D dark */
  border: 1px solid var(--card-border);  /* #E8E2D8 light / rgba(255,255,255,0.10) dark */
  border-radius: 8px;
  box-shadow: var(--shadow-lg);          /* overlay: 0 8px 24px rgba(15,25,45,0.14) light / 0 10px 28px rgba(0,0,0,0.45) dark */
  padding: 6px; z-index: 2000;
}
```

---

## Icons

The icon **system** - the skill's bundled SVG icon registry, regular-vs-filled choice, import/fallback -
lives in `assets.md`. This file only **maps shell elements to icons**. Sizes: nav/group icons `18px`
(`20px` when collapsed); top-bar action icons `20px`.

| Shell element | Icon (semantic) |
|---------------|-----------------|
| Notifications | an alert/bell icon |
| Appearance (theme switch) | a sun icon (light) / a moon icon (dark) |
| Rail collapse **and** expand (brand block; same glyph both states) | the **`i-panel`** panel/sidebar-toggle icon |
| Group expand/collapse (accordion header) | a chevron-down |
| Collapsed-group flyout hint (on the group icon, not the rail toggle) | a small chevron |
| My Profile | a person/profile icon |
| Logout | a sign-out icon |
| Example group (e.g. Governance) | a shield/check icon |
| Example group (e.g. settings-led group) | a settings/gear icon |
| Example group (e.g. Support) | a headset icon |
| Dashboard | a grid icon |
| Module Management | an apps/grid-of-tiles icon |
| Profile management | a person/profile icon |
| Role management | a shield/check icon |
| Role assignment | a person-with-arrow icon |
| Audit Logs | a clipboard/task-list icon |
| Lookup Configuration | a table icon |

> The mapping above is an **example** instance. Other modules map their own nav items to icons following the rules in `assets.md`. Every node carries a meaningful, mandatory icon.

---

## Responsive

Mobile-first. Prefer keeping the **same collapsible rail at every breakpoint** (collapsed icon rail with
the overlay-swap expander) so behavior is consistent and the **logo stays visible**. An **off-canvas
drawer** is an optional pattern for very small screens - slides in over the content with a dimmed
backdrop, dismisses on backdrop tap / `Escape`; if used, the logo must still show (e.g. in the top bar),
never a bare hamburger. Content stacks on small screens. Touch targets ≥ 44px. Use width and/or
aspect-ratio breakpoints as needed. Prefer CSS for responsive layout over JS.

```css
@media (max-aspect-ratio: 1/1) {
  /* the drawer and backdrop start below the 52px top bar */
  .rail-container { position: fixed; inset: 52px auto 0 0; z-index: 1500; transform: translateX(-100%); }
  .rail-container.open { transform: translateX(0); }
  .rail-backdrop { position: fixed; inset: 52px 0 0 0; background: rgba(0,0,0,0.4); z-index: 1400; }
}
```

---

## Render only functional UI

- **Unbuilt nav destination** → render **disabled with a "Soon" pill** (`.nav-item.is-soon`), kept visible so the planned structure is legible.
- **Unauthorized** (role-gated) item → render `null` (absent, never dimmed).
- **Unbuilt non-nav control/panel** → omit the markup entirely; no dead DOM or hidden trigger.
- A group with **zero visible children** must not render its header.

---

## Accessibility

| Element | Requirement |
|---------|-------------|
| `<nav class="nav-rail">` | `aria-label="Primary navigation"` |
| Brand block (logo / home / expander) | `role="button"`, `tabindex="0"`, Enter/Space handler; `aria-label="Home"` expanded, `aria-label="Expand navigation"` when collapsed. Collapsed: the short mark is **hidden on hover/focus** and the expander overlays it in place; the expander is **keyboard-reachable because it *is* the focusable brand block** (no separate control). |
| Clickable avatar | `role="button"`, `tabindex="0"`, Enter/Space handler |
| Icons | `aria-hidden="true"` (decorative) |
| Top-bar icon buttons (notifications, etc.) | accessible name via `aria-label` / `title` |
| Appearance (theme switcher) | `aria-label` reflecting current mode; announce the new mode on change |
| Collapse control | `aria-label="Collapse navigation"` (expanded) / handled by the brand block when collapsed |
| Group header | `aria-expanded` reflecting state; in collapsed mode the flyout is `aria-haspopup` + keyboard-reachable |
| Active nav item | `aria-current="page"` |
| "Soon" nav item | `aria-disabled="true"` + visible "Soon" pill (not color alone) |
| Main content | `id="main-content"`, `tabindex="-1"` |
| Overlays & flyout | dismissible with `Escape`; all controls reachable by `Tab` |
| Focus ring | `2px solid var(--color-primary)` (Midnight Blue `#193E6B` light / `#7FADE1` dark), `outline-offset: 2px` |

---

## Do / Don't

**Do**

- Put the **logo in the sidebar brand block** (wide/short); show the **app name** in the top bar.
- Make the **brand block the only** collapse/expand control, carrying the **`i-panel` icon** (same glyph expanded and collapsed). Collapsed: at rest show only the short mark; on hover/focus show the `i-panel` expander in the same slot, and activating it re-expands.
- Reveal collapsed-group children via a **flyout** (icons on every entry, nested groups indented, header set apart); give leaf items a tooltip.
- Put **notifications + theme switcher + profile** in the top-bar utilities.
- Gate nav items by permission (`render null`); render **unbuilt** nav destinations disabled with "Soon".
- Mark the active item with the **active-nav gold tint** + 3px gold left bar **and** `aria-current`.
- Persist collapse and per-group open state; go **off-canvas** on small screens.
- Author every color against tokens - every token is defined in **both themes**.

**Don't**

- ❌ Put the logo in the top bar, or the app name in the sidebar.
- ❌ Use the warning token (or any status-role color) for the active-nav background.
- ❌ Render controls/panels for non-functional **non-nav** features (omit them); don't omit unbuilt **nav** destinations (show "Soon").
- ❌ Leave a group header with no visible children.
- ❌ Move standing navigation to the top bar, or replace the sidebar with top tabs / hamburger (except mobile collapse). In-canvas content tabs (depth-overflow / record facets) are fine - see `tabs.md`.
- ❌ Hide overlays with `visibility`/`opacity` - use `.d-none` (`display:none`).
- ❌ Hardcode light-only colors that can't resolve under `[data-theme="dark"]`.
