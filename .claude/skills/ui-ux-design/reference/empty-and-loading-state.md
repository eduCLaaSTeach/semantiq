# Empty & Loading State UI/UX Design Guide

Reference for AI-assisted development: build *no-data*, *no-results*, and *loading* states so the
moments before and without data stay consistent, calm, and recoverable.

> **TL;DR for the AI**
> 1. **Loading = skeleton, not spinner (ENFORCED - every region/content load).** Mirror the shape of
>    the content coming; the **table skeleton is the same `dt-skeleton-bar` shimmer** in
>    [tables.md](tables.md) §12, keeping surrounding chrome (header, toolbar) visible. The **only**
>    spinner allowed is a tiny in-control wait (button in-flight, field validating, §3.2).
> 2. **Empty state = icon + title + one line + one action** (§4). The icon is a glyph from the skill's
>    bundled icon registry at **48px** ([assets.md](assets.md)); never an emoji, another library, or a one-off SVG.
> 3. **Pick the scope** (§2): a **page/region** state fills the content area; a **table** state renders
>    **in place of the body** with the header kept.
> 4. **Empty has three flavors** (§4.2): **no-data-yet** (+ primary "Add"), **no-results** (+ "Clear
>    filters", never "Add"), and **post-action/cleared**. Pick by *why* it's empty.
> 5. **Never a bare blank box.** Loading, empty, and error (§6) are a triad; ship the relevant ones together.
> 6. Use the **design tokens**; never hardcode a hex or invent a shade.

---

## Contents

1. [Design tokens (source of truth)](#1-design-tokens-source-of-truth)
2. [Two scopes - page vs table/region](#2-two-scopes--page-vs-tableregion)
3. [Loading states (skeleton, ENFORCED)](#3-loading-states-skeleton-enforced)
4. [Empty states (ENFORCED)](#4-empty-states-enforced)
5. [Choosing the icon](#5-choosing-the-icon)
6. [Error state (the third sibling)](#6-error-state-the-third-sibling)
7. [Full CSS reference (copy-paste ready)](#7-full-css-reference-copy-paste-ready)
8. [Full HTML reference](#8-full-html-reference)
9. [Accessibility (ENFORCED)](#9-accessibility-enforced)
10. [Do's and Don'ts](#10-dos-and-donts)
11. [Rules for the AI assistant](#11-rules-for-the-ai-assistant)
12. [Quick decision guide](#12-quick-decision-guide)

---

## 1. Design tokens (source of truth)

These states consume the brand palette plus a few state tokens. The **skeleton recipe is shared with
tables** ([tables.md](tables.md) §12) so a loading table and a loading page shimmer identically.
Buttons in an empty state follow [buttons.md](buttons.md); icons follow [assets.md](assets.md).

```css
:root {
    /* Brand palette (approved - canonical: design-tokens.md) */
    --color-primary: #193E6B;                  /* Midnight Blue */
    --color-accent: #B3A125;                   /* Green Gold */
    --color-secondary-violet: #7F3F98;         /* Cadmium Violet - badge/chip only, never a button fill */
    --color-secondary-blue: #448E9D;           /* Jelly Bean Blue - non-interactive only */
    --color-secondary-sunray: #E9AC53;         /* Sunray */
    --color-success: #5F8025;                  /* Avocado Green */
    --color-danger: #991547;                   /* Violet-Red - error state only; error TEXT uses --badge-danger-fg */
    --color-background: #E8DFD0;               /* canvas (dark: #1A2E46) */
    --color-surface: #FFFFFF;                  /* dark: #253E5D */
    --color-text: #1E2E42;                     /* ink (dark: #E9EFF6) */
    --color-text-muted: #4D5E75;               /* dark: #A3B2C5 */
    --card-border: #E8E2D8;                    /* card border (dark: rgba(255,255,255,0.10)) - cards are FLAT at rest */

    /* State tokens */
    --state-pad: 48px 24px;               /* vertical breathing room for empty/error */
    --state-icon-size: 48px;              /* illustration icon (assets.md) */
    --state-max-width: 420px;             /* copy column inside an empty/error state */

    /* Skeleton - SAME recipe as tables' dt-skeleton-bar (tables.md §12) */
    --skel-base:  color-mix(in srgb, var(--color-text) 8%, transparent);
    --skel-sheen: color-mix(in srgb, var(--color-text) 15%, transparent);
    --skel-radius: 6px;
    --skel-speed: 1.4s;
}
```

**Light rules (ENFORCED):** empty/error states are **muted and centered** - one icon, a dark
primary-toned title, a muted line, at most one or two buttons. No saturated fills, large
illustrations, or gradients. The skeleton is a **neutral grey shimmer** (never a brand color).

---

## 2. Two scopes - page vs table/region

The **same three states** (loading · empty · error) appear at two scopes; the message content is
identical, only the **container** and where it renders differ. Decide scope first.

| Scope | When | Container | Keeps visible |
|---|---|---|---|
| **Page / region** | A whole route, panel, or dashboard section has no data / is loading | Centered block filling the content area below the [shell](topbar-sidenav.md) | The shell (sidebar, top bar) and any page header/breadcrumb |
| **Table** | A data table inside a populated page is loading / empty / failed | Rendered **in place of `<tbody>`** (full-width row or panel under the header) | The table **card, toolbar, and `<thead>`** ([tables.md](tables.md) §7) |

- **Never replace the whole page** for a table's state; keep the page header, toolbar, and search so
  the user can add, clear a filter, or retry.
- **Never shift layout.** Render inside the slot the content would occupy - same width, no popover, no
  jump. Reserve the height so the page doesn't reflow when data arrives.
- **One scope owns the message.** Don't show a page-level "No data" *and* a table-level "No data" at
  once - pick the smallest scope that's actually empty.

```
PAGE scope                                  TABLE scope (tables.md §7)
┌─ shell ─────────────────────────┐         ┌─ .dt-card ──────────────────────┐
│ sidebar │  Page header          │         │  Modules        12 found  [+Add] │ ← toolbar kept
│         │ ┌───────────────────┐ │         │  🔍 Search...                      │ ← search kept
│         │ │      [icon 48]    │ │         │ ┌ NAME  CODE  STATUS  ACTIONS ─┐ │ ← thead kept
│         │ │   No campaigns yet│ │         │ │     [icon 48]                │ │ ← state in
│         │ │   [ + New campaign]│ │         │ │     No modules yet           │ │   place of
│         │ └───────────────────┘ │         │ │     [ + Add module ]         │ │   <tbody>
└─────────┴───────────────────────┘         │ └──────────────────────────────┘ │
                                             └──────────────────────────────────┘
```

---

## 3. Loading states (skeleton, ENFORCED)

**Every loading state uses a skeleton (ENFORCED).** Any loading region or content - page, route,
panel, table, list, card grid, detail view, dashboard section - shows a **skeleton** mirroring the
shape of the content coming. It tells the user *what* is loading and preserves layout; a bare spinner
hides structure and shifts the page.

**The one exception:** a small bounded wait *inside a single control* with nothing to skeletonize - a
button's in-flight state, a field validating, a "load more" row (§3.2). Everywhere else, a spinner is
not acceptable.

### 3.1 Principles
- **Match the shape.** Rows for a table; title + meta + paragraph for a detail page; a grid of card
  outlines for a card list.
- **Keep the chrome.** Headers, toolbars, and the table `<thead>` stay rendered; only the data region skeletons.
- **No layout shift.** The skeleton occupies the same footprint as the loaded content.
- **One shimmer recipe.** The shared grey shimmer (§1, same as [tables.md](tables.md)'s `dt-skeleton-bar`);
  never tint it a brand color.
- **Honor `prefers-reduced-motion`** - slow the shimmer right down (§7).

### 3.2 Skeleton is the rule - the one exception (ENFORCED)
| Use | When |
|---|---|
| **Skeleton** *(ENFORCED - always)* | **Any region or content load** - page, route, panel, table, list, card grid, detail view, dashboard. Mirror the shape of what's coming; never a spinner. |
| **Inline spinner** *(only exception)* | A **small, bounded wait inside a single control** with no layout to skeletonize - a button's in-flight state ([buttons.md](buttons.md)), a field validating ([forms.md](forms.md)), a "load more" row. |

A full-page or full-panel spinner is **not allowed** where a skeleton can model the layout (every
structured region). Even an unknown-shape region gets a **generic skeleton block**, never a centered
spinner.

### 3.3 Table loading (skeleton rows)
Canonical case, owned by [tables.md](tables.md) §7 - **match it exactly**.

- Render **skeleton rows in place of `<tbody>`**, **keeping the column `<thead>` visible**; never a
  spinner that hides it.
- Each skeleton cell is a `dt-skeleton-bar` sized to roughly match its column (short for a code, wide
  for a name, pill-width for a status).
- Show **~5-8 rows** (or the page size, capped), enough to fill the viewport, not the full 25.
- **Don't skeleton the toolbar, search, or pagination** - those stay live; render in place of the body,
  never a layout-shifting popover.

### 3.4 Page / region loading (skeleton layout)
Skeleton the **layout primitives** the page is about to show:
- **Detail page:** a title bar, a couple of meta lines, then paragraph bars.
- **Card grid:** a grid of card-shaped outlines, each with a title bar + 2-3 text bars.
- **Dashboard section:** stat-tile outlines + a chart-area block.
- **List / table page:** a page-title bar + optional stat-tile row, then the **table card** loaded per
  §3.3 / [tables.md](tables.md) §7 (visible column `<thead>` + skeleton rows). Only the page chrome
  *around* the table skeletons; the table header never skeletonizes.

Keep the page header / breadcrumb live; skeleton only the content region.

**Cold load vs data refresh.** §3.4 wraps a whole route arriving cold - skeleton the page chrome
around the table. The **table itself always loads per §3.3 / [tables.md](tables.md) §7**. When only
the table's **data** refreshes on an already-painted page, render the §3.3 table alone.

---

## 4. Empty states (ENFORCED)

Shown when a region has **no data to display**. Never a blank box - it **names** what's missing and
offers **the next step**.

### 4.1 Anatomy
```
        ┌─────────────────┐
        │   [ icon 48 ]   │   ← icon: bundled icon registry, outlined, 48px (assets.md), muted
        │  No modules yet │   ← title: short, plain (heading typeface 16/600)
        │  Add your first │   ← body: one line - what this is / why it's empty (muted)
        │  module to begin│
        │  [ + Add module]│   ← action: ONE solid btn-primary (buttons.md); a second one is btn-secondary
        └─────────────────┘
```

| Part | Rule |
|---|---|
| **Icon** | A single outlined glyph from the bundled icon registry at **48px** via `font-size` ([assets.md](assets.md)). Muted (`--color-text-muted`), `aria-hidden`. One icon, not a scene. |
| **Title** | One short line in `--font-heading`, ~16px/600. States the absence plainly: "No modules yet", "No results". |
| **Body** | At most **one sentence**, muted - what the area holds or *why* it's empty and what to do. Optional. |
| **Action** | **One** action for the obvious next step. In the no-data case it is the solid `btn-primary` that starts the create path; any second action, and every action in a no-results state, is `btn-secondary` ([buttons.md](buttons.md) §4). Omit when there's no action. |

**Render only functional UI** ([../SKILL.md](../SKILL.md)): if the primary action isn't wired up,
show the empty state without the button rather than a dead control.

### 4.2 The three flavors - pick by *why* it's empty
| Flavor | Trigger | Icon | Title | Action |
|---|---|---|---|---|
| **No data yet** | Nothing created in this collection | domain/object icon (document, apps/grid, people) | "No *<things>* yet" | solid **`btn-primary`** "Add *<thing>*" - the create path |
| **No results** | A search/filter returned nothing (data exists, hidden by the query) | a search icon | "No matches for '<query>'" | **`btn-secondary`** "Clear filters" / "Clear search" - **never** "Add", and no solid button here |
| **Post-action / cleared** | The user emptied it themselves | a checkmark/success icon or the object icon | "All clear" / "No *<things>* left" | Usually none, or the create path |

- **No-data ≠ no-results.** If a filter/search is active and hid everything, that's **no-results** →
  offer **Clear filters**. "Add" here is wrong: the data exists, the query hid it.
- **Echo the query.** A no-results state names what was searched ("No matches for *acme*").
- **One action, the obvious one.** Don't stack three CTAs.
- **Only a genuine main action is solid.** Creating the first record commits new data, so that one is
  solid. Clearing a filter only changes what is on screen, so a no-results state carries no solid
  button at all ([buttons.md](buttons.md) §4).

### 4.3 Page vs table empty (same flavors, different container)
- **Table empty** → render in place of `<tbody>`, keep toolbar + search + `<thead>` (§2,
  [tables.md](tables.md) §7). A no-results table keeps its filter chips so the user can clear them.
- **Page/region empty** → a centered block in the content area, keeping the shell and page header.

---

## 5. Choosing the icon

Icons come from the skill's bundled icon registry (central inline-SVG sprite) in its outlined style,
referenced by symbol id and sized via `font-size` ([assets.md](assets.md)). One registry across the
suite - **no emoji, mixed libraries, or ad-hoc SVG**.

```html
<!-- Empty-state illustration icon - a sprite glyph, 48px via font-size (assets.md) -->
<svg class="state-icon" aria-hidden="true"><use href="#i-document" /></svg>
```

**Pick by meaning, not decoration.** Reuse the object's nav/domain icon for "no data yet".

| Situation | Icon | Note |
|---|---|---|
| No records yet (generic) | a document / inbox icon | Neutral "nothing here" |
| No modules / apps | an apps/grid icon | Matches the modules nav icon (assets.md catalog) |
| No people / contacts / users | a people / person icon | |
| No tabular data / config rows | a table icon | |
| **No search/filter results** | a search icon | Pairs with "Clear filters" |
| No tasks / audit entries | a task-list / clipboard icon | |
| Cleared / all done | a checkmark/success icon | Post-action empty |
| **Failed to load (error §6)** | a disconnected-plug / error-circle icon | Pairs with "Retry" |

If the exact concept isn't in the [assets.md](assets.md) catalog, choose the closest outlined glyph
by meaning; don't reach outside the library. If the icon isn't available, follow assets.md's
**asset-missing** protocol (placeholder + `<!-- ASSET MISSING -->` + tell the user).

---

## 6. Error state (the third sibling)

Empty and loading travel with **error** - the same slot, when the data **failed to load**. Ship it
alongside the other two.

- **Icon:** a disconnected-plug / error-circle icon, 48px, in `--color-danger`.
- **Title + line:** what failed, in human terms ("Couldn't load modules"). No raw status codes or stack
  traces (route those per [toasts.md](toasts.md) §7).
- **Action:** **Retry**, the state's one main action, so it is the solid `btn-primary`; anything beside it
  (a `Clear filters` in table scope) is `btn-secondary`. Keep the toolbar/search live (table scope) so the
  user can change the query.
- **Don't confuse error with empty.** "Failed to load" (error → Retry) is not "there's nothing here"
  (empty → Add/Clear). A failed fetch must never silently render as an empty state.

---

## 7. Full CSS reference (copy-paste ready)

`state-`/`skeleton-`-namespaced. The skeleton shimmer is the **same recipe** as [tables.md](tables.md)'s
`dt-skeleton-bar` (§12) - reuse `dt-skeleton-bar` inside a table; use `.skeleton` for page/region
scopes. Uses `color-mix()` (current evergreen browsers).

```css
/* ===== Skeleton primitive (shared shimmer - matches dt-skeleton-bar) ===== */
.skeleton {
  height: 12px; border-radius: var(--skel-radius);
  background: linear-gradient(90deg, var(--skel-base) 25%, var(--skel-sheen) 37%, var(--skel-base) 63%);
  background-size: 400% 100%;
  animation: skeleton-shimmer var(--skel-speed) ease infinite;
}
@keyframes skeleton-shimmer { 0% { background-position: 100% 50%; } 100% { background-position: 0 50%; } }
/* Width/shape helpers - compose to mirror the real content */
.skeleton--text   { height: 12px; }
.skeleton--title  { height: 20px; width: 40%; }
.skeleton--pill   { height: 22px; width: 72px; border-radius: 999px; }
.skeleton--avatar { height: 28px; width: 28px; border-radius: 50%; }
.skeleton--block  { height: 120px; border-radius: var(--radius-md, 8px); }
.skeleton--w-25 { width: 25%; } .skeleton--w-50 { width: 50%; }
.skeleton--w-75 { width: 75%; } .skeleton--w-90 { width: 90%; }

/* Skeleton page/region scaffolds */
.skeleton-stack { display: flex; flex-direction: column; gap: 12px; }
.skeleton-card {
  background: var(--color-surface); border-radius: var(--radius-lg, 12px);
  border: 1px solid var(--card-border);          /* FLAT at rest - no resting shadow */
  padding: 20px; display: flex; flex-direction: column; gap: 12px;
}
.skeleton-grid { display: grid; gap: 16px; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); }

/* ===== Empty / error state (shared layout, both scopes) ===== */
.state {
  display: flex; flex-direction: column; align-items: center; text-align: center;
  gap: 8px; padding: var(--state-pad); color: var(--color-text-muted);
}
.state-icon { font-size: var(--state-icon-size); width: 1em; height: 1em; line-height: 1; color: var(--color-text-muted); }
.state--error .state-icon { color: var(--color-danger); }
.state-title {
  font-family: var(--font-heading, 'Montserrat', sans-serif);
  font-size: 16px; font-weight: 600; color: var(--color-text); margin: 4px 0 0;
}
.state-body { max-width: var(--state-max-width); font-size: 14px; line-height: 1.5; margin: 0; }
.state-actions { display: flex; gap: 8px; flex-wrap: wrap; justify-content: center; margin-top: 8px; }

/* Page/region scope: fill the content area, vertically centered */
.state--page { min-height: 60vh; justify-content: center; }

/* Table scope: a full-width cell standing in for <tbody> (see tables.md §7) */
.dt .state-row td { padding: 0; }
.dt .state-row .state { padding: 56px 24px; }

/* ===== Reduced motion ===== */
@media (prefers-reduced-motion: reduce) { .skeleton { animation-duration: 2.6s; } }

/* ===== Responsive (mobile-first) - base targets small screens; widen at larger viewports.
   Small screens: single-column grid, tighter padding; sidebar goes off-canvas (topbar-sidenav.md).
   Keep action buttons ≥44px touch targets. */
@media (max-width: 640px), (max-aspect-ratio: 1/1) {
  .state { padding: 32px 16px; }
  .state--page { min-height: 50vh; }
  .skeleton-grid { grid-template-columns: 1fr; }
}
```

---

## 8. Full HTML reference

Icons are sprite references from the bundled icon registry (§5, [assets.md](assets.md)) -
`<svg class="state-icon" aria-hidden="true"><use href="#i-..." /></svg>`, sized via `font-size`. In a
framework, keep the same registry glyph and markup contract.

### 8.1 Table loading - skeleton rows in place of `<tbody>` (keep `<thead>`)
```html
<tbody aria-live="polite" aria-busy="true">
  <!-- repeat ~6 rows; bars sized to the columns. Bars are block-level <div>s (matching tables.md §7)
       - an inline <span> in a <td> collapses to zero height. -->
  <tr>
    <td><div class="dt-skeleton-bar" style="width:70%"></div></td>   <!-- NAME -->
    <td><div class="dt-skeleton-bar" style="width:50%"></div></td>   <!-- CODE -->
    <td><div class="dt-skeleton-bar" style="width:55%"></div></td>   <!-- STATUS -->
    <td><div class="dt-skeleton-bar" style="width:60%"></div></td>   <!-- OWNER -->
    <td><div class="dt-skeleton-bar" style="width:120px"></div></td>  <!-- ACTIONS: labeled buttons -->
  </tr>
  <!-- ... 5 more ... -->
</tbody>
```

### 8.2 Page/region loading - skeleton layout
```html
<div class="skeleton-card" aria-busy="true" aria-label="Loading">
  <span class="skeleton skeleton--title"></span>
  <span class="skeleton skeleton--text skeleton--w-90"></span>
  <span class="skeleton skeleton--text skeleton--w-75"></span>
  <span class="skeleton skeleton--text skeleton--w-50"></span>
</div>

<!-- card grid loading -->
<div class="skeleton-grid" aria-busy="true" aria-label="Loading">
  <div class="skeleton-card">
    <span class="skeleton skeleton--title"></span>
    <span class="skeleton skeleton--text"></span>
    <span class="skeleton skeleton--text skeleton--w-75"></span>
  </div>
  <!-- ... repeat ... -->
</div>
```

### 8.3 Empty - no data yet (page scope)
```html
<div class="state state--page" role="status">
  <svg class="state-icon" aria-hidden="true"><use href="#i-apps" /></svg>
  <p class="state-title">No campaigns yet</p>
  <p class="state-body">Create your first campaign to start reaching contacts.</p>
  <div class="state-actions">
    <button type="button" class="btn btn-primary">+ New campaign</button>
  </div>
</div>
```

### 8.4 Empty - no results (table scope, in place of `<tbody>`)
```html
<tbody>
  <tr class="state-row">
    <td colspan="5">
      <div class="state" role="status">
        <svg class="state-icon" aria-hidden="true"><use href="#i-search" /></svg>
        <p class="state-title">No matches for "acme"</p>
        <p class="state-body">No modules match your search and filters.</p>
        <div class="state-actions">
          <button type="button" class="btn btn-secondary">Clear filters</button>
        </div>
      </div>
    </td>
  </tr>
</tbody>
```

### 8.5 Error - failed to load (table scope)
```html
<tbody>
  <tr class="state-row">
    <td colspan="5">
      <div class="state state--error" role="alert">
        <!-- register #i-plug-off in the same style if the registry lacks it -->
        <svg class="state-icon" aria-hidden="true"><use href="#i-plug-off" /></svg>
        <p class="state-title">Couldn't load modules</p>
        <p class="state-body">Something went wrong. Check your connection and try again.</p>
        <div class="state-actions">
          <button type="button" class="btn btn-primary">Retry</button>
        </div>
      </div>
    </td>
  </tr>
</tbody>
```

---

## 9. Accessibility (ENFORCED)

- **Announce async changes.** The data region carries `aria-busy="true"` while loading and
  `aria-live="polite"` so loading → loaded/empty is announced without stealing focus.
- **Error is assertive.** An error state uses `role="alert"` (or `aria-live="assertive"`); loading/empty
  use `polite` / `role="status"`.
- **Icons are decorative.** The glyph is `aria-hidden="true"`; the **title text** carries the meaning.
- **Actions are real controls.** Empty/error CTAs are real `<button>`/`<a>` with visible
  `:focus-visible` rings and ≥44px touch targets ([buttons.md](buttons.md)).
- **No focus theft.** Showing a state must not move keyboard focus.
- **No layout shift on settle.** Reserve the region's height.
- **Reduced motion.** The skeleton shimmer slows under `prefers-reduced-motion: reduce` (§7).
- **Contrast.** Muted body text still meets contrast on `--color-surface`; don't drop below `--color-text-muted`.

---

## 10. Do's and Don'ts

| ✅ Do | ❌ Don't | Why |
|---|---|---|
| Use a **skeleton** that mirrors the content shape | Blank the region behind a full-page spinner | The spinner hides structure and shifts layout (§3) |
| Reuse the **shared shimmer** (`dt-skeleton-bar` / `.skeleton`) | Invent a new loading animation or tint it a brand color | One calm, consistent loading language (§1) |
| Keep **chrome live** while the data region loads | Skeleton the toolbar, search, header too | The user should recognize the page mid-load (§2-§3) |
| Distinguish **no-data** (+Add) from **no-results** (+Clear) | Show "Add" when a filter hid the rows | The data exists; the query hid it (§4.2) |
| Use an **outlined** registry icon at **48px** | Use an emoji, another library, or a custom SVG scene | One icon library, one style (§5, assets.md) |
| Offer **one** obvious action | Stack three CTAs in an empty state | Empty states guide, they don't menu (§4.1) |
| Render **error** with a **Retry**, distinct from empty | Render a failed fetch as "nothing here" | A failure isn't an absence (§6) |
| Reserve height so nothing jumps on settle | Let the page reflow when data lands | Layout shift reads as a bug (§2) |
| Announce via `aria-busy` + `aria-live`; error is `alert` | Leave the transition silent for AT | Screen-reader users must learn the result (§9) |

---

## 11. Rules for the AI assistant

When generating any empty / loading (or its sibling error) state, the assistant **must**:

1. **Pick the scope first** - **page/region** or **table** (§2). A table's state renders in place of
   `<tbody>` and keeps the card, toolbar, search, and `<thead>`; it never replaces the whole page.
2. **Load with a skeleton, not a spinner (ENFORCED for every region/content load)** (§3), mirroring the
   content shape and reusing the **shared shimmer** - `dt-skeleton-bar` in a table ([tables.md](tables.md)
   §12), `.skeleton` for page/region scope. The **only** spinner permitted is a small in-control wait
   (button in-flight, field validating, "load more" row, §3.2); never a full-page or full-panel spinner.
3. **Build the empty state** as **icon (48px) + title + one line + one action** (§4.1), the icon an
   outlined sprite glyph from the bundled icon registry sized via `font-size` ([assets.md](assets.md));
   never emoji or another library.
4. **Choose the empty flavor by *why* it's empty** (§4.2): **no-data-yet** → the solid `btn-primary` "Add";
   **no-results** → `btn-secondary` "Clear filters" and echo the query, **never** "Add"; **post-action** →
   usually no action.
5. **Ship error alongside** (§6) with a 48px danger icon and a **Retry**; never render a failed load as
   an empty state.
6. **Apply "render only functional UI"** ([../SKILL.md](../SKILL.md)): omit a CTA whose action isn't
   wired up rather than showing a dead button.
7. **Wire accessibility** (§9): `aria-busy` + `aria-live="polite"` on the loading region, `role="alert"`
   on error, `aria-hidden` decorative icons, real focusable action buttons, no focus theft, no layout shift.
8. **Use the design tokens**; keep states muted, centered, and light (§1).
9. **Honor `prefers-reduced-motion`** by slowing the shimmer (§7).

---

## 12. Quick decision guide

```
What's the state of this region?
│
├─ Data is on its way ─────────▶ LOADING  →  SKELETON (ENFORCED - every region/content load)
│   ├─ table ........... skeleton rows in place of <tbody>, keep <thead>  (dt-skeleton-bar)
│   ├─ page/region ..... skeleton title + text bars / card-grid outlines  (.skeleton)
│   ├─ unknown shape ... a generic skeleton block - still NOT a spinner
│   └─ EXCEPTION: a tiny bounded wait INSIDE one control (button in-flight,
│                 field validating, "load more" row) ─▶ inline spinner (buttons.md / forms.md)
│
├─ Loaded but nothing to show ─▶ EMPTY  (icon 48px + title + line + ONE action)
│   ├─ Nothing created yet ........... "No <things> yet"      + PRIMARY "Add <thing>"
│   ├─ Filter/search hid everything .. "No matches for 'x'"   + "Clear filters" (NEVER Add)
│   └─ User just cleared/deleted all . "All clear"            + (usually no action)
│
└─ Failed to load ─────────────▶ ERROR (danger icon 48px + human message + RETRY)
       └─ not the same as empty - a failure is not an absence

Scope: only the table empty? ──▶ keep the page header/toolbar/search; state replaces <tbody>.
       whole route/section empty? ▶ centered .state--page in the content area; keep the shell.
Always: bundled-registry icons · design tokens · aria-busy/aria-live · no layout shift.
```

---
