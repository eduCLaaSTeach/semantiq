# Table & Pagination UI/UX Design Guide

Reference for AI-assisted development of tables, data grids, and pagination. Values here (palette,
fonts, icon names, class prefixes) are approved constants; the canonical source is `design-tokens.md`
(and `../assets/` for brand assets). Every token is defined in both a light and a dark theme with a
Light/Dark/System switcher; the styling below is the light-theme default.

> **TL;DR for the AI**
> 1. **Pick a tier by the job** (§3): Simple / Standard data table / Advanced data grid. Don't reach for the grid when a Standard table will do.
> 2. **Keep it light** (§1): white card, 1px border (flat, no resting shadow), no gradient bars or vertical gridlines; 10px uppercase muted headers, 1px horizontal row dividers, subtle hover.
> 3. **Behavior is ENFORCED** (§4-§9): sortable columns (`aria-sort`) with a declared default sort, a search/filter bar on every list/index table, hover, selectable rows for bulk actions, pagination when rows > 25 (default 25/page; 25/50/75/100).
> 4. **Sort and filter run over the whole result set** (§4, §4a), never the loaded page; any change returns to page 1; the counts report the filtered total; the state lives in the URL query.
> 5. **States required** (§7): every table ships loading, empty, and error - never a bare blank box.
> 6. **Align by type** (§6): text left, numbers right (tabular), status as a pill, row actions as labeled buttons - the icon **plus** its word, never a bare glyph.
> 7. Use the design tokens - never hardcode a hex or invent a shade outside the palette.

---

## Contents

1. [Design tokens (source of truth)](#1-design-tokens-source-of-truth)
2. [Anatomy of a table](#2-anatomy-of-a-table)
3. [The three tiers](#3-the-three-tiers)
4. [Columns](#4-columns)
   - [4a. Search and filter bar (ENFORCED on every list/index table)](#4a-search-and-filter-bar-enforced-on-every-listindex-table)
5. [Rows](#5-rows)
6. [Cell content patterns](#6-cell-content-patterns)
7. [States - loading, empty, error (ENFORCED)](#7-states--loading-empty-error-enforced)
8. [Selection & bulk actions](#8-selection--bulk-actions)
9. [Pagination](#9-pagination)
10. [Toolbar](#10-toolbar)
11. [Responsive (ENFORCED, mobile-first)](#11-responsive-enforced-mobile-first)
12. [Full CSS reference (copy-paste ready)](#12-full-css-reference-copy-paste-ready)
13. [Full JS reference (behavior contract)](#13-full-js-reference-behavior-contract)
14. [Accessibility checklist](#14-accessibility-checklist)
15. [Do's and Don'ts](#15-dos-and-donts)
16. [Rules for the AI assistant](#16-rules-for-the-ai-assistant)
17. [Quick decision guide](#17-quick-decision-guide)

---

## 1. Design tokens (source of truth)

Tables consume the approved CLaaS2SaaS palette plus a small set of table tokens. Complete two-theme
set is canonical in `design-tokens.md`. Buttons inside a table follow [buttons.md](buttons.md); a
table inside a modal follows [modals-dialogs.md](modals-dialogs.md).

```css
:root {
    /* Brand palette (approved) */
    --color-primary: #193E6B;        /* Midnight Blue - active page, links, toolbar title */
    --color-accent: #B3A125;         /* Green Gold */
    --color-secondary-violet: #7F3F98;   /* Cadmium Violet */
    --color-secondary-blue: #448E9D; /* Jelly Bean Blue - non-interactive: edit affordance, score bars */
    --color-secondary-sunray: #E9AC53;   /* Sunray */
    --color-success: #5F8025;        /* Avocado Green - status: active / ok */
    --color-danger: #991547;         /* Violet-Red - status: inactive / error, delete */
    --color-background: #E8DFD0;     /* canvas */
    --color-surface: #FFFFFF;        /* card */
    --color-text: #1E2E42;
    --color-text-muted: #4D5E75;

    /* Theme-aware badge pairs - semantic color as text/icon always goes through a
       readable --badge-*-fg, never the raw semantic hex on a surface */
    --badge-success-bg: color-mix(in srgb, var(--color-success) 16%, #fff);
    --badge-success-fg: #3A4E13;
    --badge-danger-bg: color-mix(in srgb, var(--color-danger) 14%, #fff);
    --badge-danger-fg: #85113E;
    --badge-info-fg: #1E545F;        /* readable Jelly Bean Blue for icons/text */
    --badge-neutral-bg: #F0EBE1;
    --badge-neutral-fg: #514A3B;

    /* Table tokens */
    --dt-radius: 12px;               /* card radius (= --radius-lg) */
    --card-border: #E8E2D8;          /* card border - the card is FLAT at rest, no shadow */
    --shadow-lg: 0 8px 24px rgba(15,25,45,0.14); /* overlay shadow - popovers only (column menu) */
    --dt-border: color-mix(in srgb, var(--color-text) 12%, transparent);        /* row divider */
    --dt-border-strong: color-mix(in srgb, var(--color-text) 22%, transparent); /* header divider, controls */
    --dt-row-hover: rgba(25, 62, 107, 0.06);    /* subtle per-theme surface tint */
    --dt-row-selected: color-mix(in srgb, var(--color-primary) 7%, transparent);
    --dt-head-bg: var(--color-surface);

    /* Density */
    --dt-cell-pad: 14px 16px;        /* comfortable (default) */
    --dt-cell-pad-compact: 8px 16px; /* compact */

    /* Sticky layering (advanced grid) */
    --dt-z-head: 2;
    --dt-z-sticky-col: 3;
    --dt-z-sticky-corner: 4;
}

[data-theme="dark"] {
    --color-primary: #7FADE1;        /* interactive accent on dark (links, focus, active page) */
    --color-background: #1A2E46;     /* canvas */
    --color-surface: #253E5D;        /* card */
    --color-text: #E9EFF6;
    --color-text-muted: #A3B2C5;
    --card-border: rgba(255, 255, 255, 0.10);
    --shadow-lg: 0 10px 28px rgba(0,0,0,0.45);
    --badge-success-bg: color-mix(in srgb, var(--color-success) 28%, #253E5D);
    --badge-success-fg: #CBE79B;
    --badge-danger-bg: color-mix(in srgb, var(--color-danger) 32%, #253E5D);
    --badge-danger-fg: #F5B8CF;
    --badge-info-fg: #B4E3EC;
    --badge-neutral-bg: rgba(255, 255, 255, 0.10);
    --badge-neutral-fg: rgba(255, 255, 255, 0.80);
    --dt-row-hover: rgba(255, 255, 255, 0.07);
    /* complete dark set - chrome, interactive accent #7FADE1, full badge/tab/toast
       tokens - in design-tokens.md */
}
```

**Light rules (ENFORCED):** no gradient accent bars, no vertical gridlines, no heavy borders.
Dividers are horizontal only (1px, `--dt-border`); the header carries a single 2px bottom divider.
Selection/hover are subtle tints, never saturated fills.

---

## 2. Anatomy of a table

A table is a card wrapping a toolbar, an optional selection bar, a scroll area holding the `<table>`
(header + body), and a footer / pagination.

```
┌─────────────────────────────────────────────────────────────────────────────┐  ← .dt-card (white, 12px, 1px border)
│  Module Listing            12 found                       [Export] [⋮ Cols] │  ← .dt-toolbar (title · count · view controls)
│  ┌───────────────────────────────────────────────────────────────────────┐  │
│  │ 🔍 analytics                                                         ✕ │  │  ← .dt-search (required on a list/index, §4a)
│  └───────────────────────────────────────────────────────────────────────┘  │
│  ☑ 3 selected       [Delete]  [Export]                             [Clear]  │  ← .dt-select-bar (only when rows selected)
│ ┌─────────────────────────────────────────────────────────────────────────┐ │
│ │ NAME ▲    CODE     STATUS    OWNER         ACTIONS                      │ │  ← sticky header (10px uppercase, muted)
│ │ Acme      AGNT_HR  ●Active   JD  Jane Doe  [👁 View] [✎ Edit] [🗑 Delete] │ │  ← row (hover tint, click → detail; labeled actions, §6)
│ └─────────────────────────────────────────────────────────────────────────┘ │  ← .dt-scroll (body scrolls)
│  Showing 1-12 of 12    [25/pg ▾]                                            │  ← .dt-pagination (one page, so no controls)
└─────────────────────────────────────────────────────────────────────────────┘
```

- **Card** (`.dt-card`): the light surface; everything lives inside it.
- **Toolbar** (`.dt-toolbar`): list title + muted result count (left); actions (primary button, column menu, density, export) (right). The count is the filtered total, so it agrees with the footer: the sketch above shows a filtered state, "12 found" beside "Showing 1-12 of 12" (§4a). Search/filter is its own component (§10).
- **Selection bar** (`.dt-select-bar`): appears only when ≥ 1 row is selected (§8).
- **Scroll area** (`.dt-scroll`): only the body scrolls; the header stays pinned.
- **Table** (`.dt`): a real semantic `<table>` - `<thead>`, `<tbody>`, `<th scope="col">`.
- **Footer / pagination** (`.dt-footer` / `.dt-pagination`): "Showing X of Y" and page controls.

---

## 3. The three tiers

Pick the smallest tier that does the job; every added capability is weight the user must parse.

| Tier | Class | Use for | Capabilities |
|---|---|---|---|
| **Simple** | `.dt` | A short, read-only sub-table inside a detail page, or the dashboard's recent-activity table (≤ ~10 rows, no paging) | Header + rows + hover. No sort, filter, selection, pagination. |
| **Standard data table** | `.dt` | The workhorse list/index view | **Sortable columns + a search/filter bar (both required, §4a)**, hover, **row-click → detail**, status pills, **row actions** (labeled buttons, §6), **pagination** (> 25 rows). |
| **Advanced data grid** | `.dt` (+ feature classes) | Dense, power-user data management | Standard **plus**: row **selection + bulk bar**, **sticky** check/action columns on horizontal scroll, **column show/hide**, **density toggle**, **expandable rows**, **inline cell editing**. |

**Capability matrix**

| Capability | Simple | Standard | Advanced |
|---|:--:|:--:|:--:|
| Hover · semantic markup | ✅ | ✅ | ✅ |
| Sortable columns (§4) | - | ✅ | ✅ |
| Search / filter bar (§4a) | - | ✅ | ✅ |
| Row-click → detail (§5) | - | ✅ | ✅ |
| Row actions (§6) | - | ✅ | ✅ |
| Pagination (§9) | - | ✅ (> 25) | ✅ |
| Row selection + bulk bar (§8) | - | optional | ✅ |
| Sticky columns (§11) | - | - | ✅ |
| Column show/hide (§4) | - | - | ✅ |
| Density toggle (§5) | - | - | ✅ |
| Expandable rows (§5) | - | - | ✅ |
| Inline cell editing (§5) | - | - | ✅ |

Don't over-reach: a 6-row read-only summary inside a detail page needs no sort, filter, checkboxes, or
pagination. Promote to the Advanced grid only for thousands of rows or multi-select ops - keep the **same
light styling** (§1) at every tier.

**Don't under-reach either.** A list/index view is Standard at minimum, so it sorts and filters. Those
two are not the Advanced tier's job and not a later enhancement: a list the user cannot reorder or narrow
is one they have to read line by line, and it degrades every week the table grows (§4, §4a).

---

## 4. Columns

**Cell types & alignment** (visuals in §6):
- Text → left (default).
- Numbers / currency / counts → **right-aligned**, `font-variant-numeric: tabular-nums` (class `dt-num` on `th` and `td`).
- Status → a **pill** (`dt-badge`), left-aligned.
- Actions → right-most column, labeled buttons - the icon plus its word (§6).

**Width & truncation:** let content size columns; constrain only known-short fields. Long free-text
truncates with ellipsis + tooltip (`dt-truncate`), never wrapping into tall ragged rows.

**Sortable columns (ENFORCED on every list/index table):**
- Every column holding a value worth ordering sorts, which is most of them: name, code, status, owner, dates, counts, amounts. The **actions column** and a **free-text notes** column are the exceptions. "This column is hard to sort" is a reason to fix the query, not to drop the control.
- A sortable header is a **`<button class="dt-sort">`** inside the `th` (keyboard operable). The `th` carries **`aria-sort="none|ascending|descending"`**.
- Click cycles none → ascending → descending. Only **one** column sorts at a time; sorting a new column resets others to `none`.
- Show a direction caret, dim at rest, solid on the active sort. Direction is never carried by the caret alone - `aria-sort` carries it too.
- **Declare a default sort** and open the list on it (newest business date first, or the primary identifier ascending). A list with no stated order comes back in whatever order the database chose, which changes under the user between visits.
- **Sort the result set, not the page.** Where the table is paginated, the sort is a server-side parameter and the page returns already ordered. Reordering the 25 rows on screen while the other 222 matches sit behind pagination is a defect (§9, §13).
- Sort state lives in the **URL query** alongside the filters and the page, so a refresh, the back button, and a shared link all reproduce the view (§4a).

**Sticky columns (advanced):** wide grids that scroll horizontally keep **checkbox** (`dt-sticky-left`)
and **actions** (`dt-sticky-right`) columns pinned. See §11.

**Show/hide columns (advanced, PRINCIPLED):** a "Columns" menu (`dt-col-menu`) toggles low-priority
columns. Keep identifying columns (name/code) always visible; never let the user hide actions or the
row's primary identifier.

**Density (advanced):** a comfortable/compact toggle adds `.dt--compact`, tightening `td` padding only
(§5). Header and font sizes don't change.

---

## 4a. Search and filter bar (ENFORCED on every list/index table)

The controls themselves belong to [search-filter.md](search-filter.md) - the pill, the tabs, the dropdown
fields, the chips, the popover, their states and ARIA. This section is the part that is the table's
business: that the bar exists, and how it binds to the rows, the count, and the page.

- **Every list/index table has one.** The search pill plus a facet for each dimension the list is
  genuinely narrowed by. A list is where the user goes to *find* a record, and search is how they do it.
- **Pick facets from the columns, not one per column:** status, type, owner, source, a date range. Each
  facet reads the column's codified reference set, so the values are the real codes with their labels -
  never a free-text match against a coded column.
- **Filter the result set, not the page.** Same rule as sorting (§4): where the table is paginated, the
  filter is a server-side parameter and the page comes back already narrowed. Filtering the 25 loaded rows
  is a defect, because the matches on page 3 never appear.
- **Any change to a filter, the sort, or the page size returns to page 1.** Leaving the user on page 7 of
  a result set that now has two pages shows them an empty table that is not empty.
- **Both counts report the filtered total.** The toolbar count (§10) and the pagination info (§9) agree:
  "12 found" and "Showing 1-12 of 12", not the unfiltered 247.
- **Zero matches is the no-results state** with a Clear filters escape (§7), never the no-data-yet state
  and never a blank body.
- **State lives in the URL query**, so a refresh, the back button, a bookmark, and a link to a colleague
  all reproduce the same view. Restore the view by reading the URL on load, not from remembered client
  state.

```text
/products?q=analytics&status=active&owner=me&sort=updated_at&dir=desc&page=2&size=25
```

Use the project's existing query convention when it has one; otherwise keep these names and use them the
same way on every list. Omit an empty parameter rather than writing `&status=`.

- **Announce the result count** from its own polite live region on the count element, so a filter is
  evidently doing something for a screen-reader user rather than silently. Put the live region on the
  count, not on the row body: a live `<tbody>` re-reads every matching row's text on each change, which
  buries the one sentence that mattered.
- **Small screens:** the bar stacks and the pill goes full width ([search-filter.md](search-filter.md) §9);
  it never collapses into an icon the user has to discover.

---

## 5. Rows

- **Hover:** every tier tints the hovered row (`--dt-row-hover`) - subtle, signals interactivity, not selection.
- **Row-click → detail** (Standard/Advanced): clicking a row opens its detail (add `.dt--rowlink` for the pointer cursor). Keep an explicit affordance too (name as a link, or a chevron). **Clicks on a control inside the row (checkbox, action button, link) must not trigger row navigation.**
- **Selection (advanced; optional in Standard):** leading checkbox column + header **select-all**. Selected rows get `.dt-row--selected` and surface the **bulk bar** (§8). Independent of hover and row-click.
- **Expandable / nested rows (advanced):** leading chevron button (`dt-expand-btn`, `aria-expanded`) reveals a **detail row** (`dt-detail-row`) beneath. One expanded row doesn't collapse others unless single-expand is chosen.
- **Inline cell editing (advanced):** a cell becomes an input (`dt-cell-edit`) on click/Enter. **Enter or blur commits; Esc cancels.** Validate per [forms.md](forms.md) §6-§7 (no below-cell error - show the cell in danger border and surface the message via toast or tooltip). For anything beyond a single field, route to the record's **edit page** - never a form in a dialog (forms.md §3).
- **Density (advanced):** `.dt--compact` reduces vertical padding; default is comfortable.

---

## 6. Cell content patterns

| Content | Pattern | Notes |
|---|---|---|
| Plain text | bare text | Left-aligned; truncate long values (`dt-truncate`) + tooltip |
| Number / currency | `td.dt-num` | Right-aligned, tabular numerals |
| Status | `dt-badge dt-badge--success/--danger/--neutral` | Pill; pair color with the word, never color alone |
| Code / ID | `dt-badge--neutral` (mono optional) | Quiet neutral pill keeps codes scannable |
| Person | `dt-avatar-cell` → `dt-avatar` (initials) + name | 28px round initials + name |
| Link / drill-in | `dt-link` (dotted underline) | An in-cell **navigation link** (an `<a>`): a documentation link, a jump to a related record. The actions column's `View` is a labeled button (§6, below), never this |
| Progress / score | `dt-bar` → `dt-bar-fill` | Slim track; fill in secondary-blue |
| Row actions | `dt-actions` → the shared `btn btn-secondary btn-sm` (plus `btn-text-danger` on a destructive one) | Each control carries its icon **and its word**; never a bare glyph |
| Empty value | `-` in `dt-empty-cell` | Never leave a blank cell; render an em dash, muted |

**Status colors (ENFORCED):** success = Avocado Green `#5F8025`, error/inactive = Violet-Red `#991547`,
neutral = muted - always through the theme-aware `--badge-*-bg`/`--badge-*-fg` pairs (§1), never the raw
semantic hex as text on a surface. Never use accent gold or a domain color to signal status.

**Row actions carry their labels (ENFORCED):** every control in the actions column is the shared
`btn btn-secondary btn-sm` from [buttons.md](buttons.md) §4.2, carrying its registry icon **and its
word** - `View`, `Edit`, `Delete`, `Restore`, `Duplicate`, `Test`. A bare pencil beside a bare trash asks
the user to guess, and the tooltip that would explain it does not exist on a touch device. The word is
visible text, not only an `aria-label`, and it stays at every breakpoint (§11).

- Past about **three** actions on a row, keep the first three labeled inline and move the rest behind one
  `More` control - the kebab icon plus the word - whose accessible name names the row.
- A row's `Delete` **opens the confirmation**, it does not commit, so it is
  `btn btn-secondary btn-sm btn-text-danger`: the same neutral shell, with the danger color on the icon and
  label only. The dialog's `Delete` is the solid `btn-danger` that commits (§8,
  [modals-dialogs.md](modals-dialogs.md) §7).
- **A table never gets a button style of its own for an action** - no ghost, no outline, no
  `dt-`-namespaced button for a row action, a toolbar action, or a bulk action (§12). The table's own
  primitives are a separate matter and keep the looks this file defines: the sortable column header (§4),
  the expand chevron (§5), and the pager (§9) are not actions on a record, so they are neither of the two
  button looks ([buttons.md](buttons.md) §4.1).
- **The one icon-only action inside a table-shaped surface** is a form repeater's per-row remove
  (`ui-ux-quality.md`, Typed row repeater), where the row is a field group rather than a record.

---

## 7. States - loading, empty, error (ENFORCED)

Every table ships all three; a bare blank box is never acceptable. Shared vocabulary (token values,
icon rules, three empty flavors, error sibling) is owned by
[empty-and-loading-state.md](empty-and-loading-state.md). This section covers the table-scope application.

- **Loading:** skeleton rows (`dt-skeleton-bar` shimmer, `<div>` not `<span>`) matching the column layout - never a spinner. Keep `<thead>` visible. Bars are block-level `<div>`s so `width`/`height` apply inside a `<td>` (an inline `<span>` collapses to zero height).
  The live region here announces the load itself; the skeleton carries no text, and the result count is
  announced from its own live region once the rows land (§4a).

  ```html
  <tbody aria-live="polite" aria-busy="true">
    <tr>
      <td><div class="dt-skeleton-bar" style="width:70%"></div></td>  <!-- NAME -->
      <td><div class="dt-skeleton-bar" style="width:50%"></div></td>  <!-- CODE -->
      <td><div class="dt-skeleton-bar" style="width:55%"></div></td>  <!-- STATUS -->
      <td><div class="dt-skeleton-bar" style="width:60%"></div></td>  <!-- OWNER -->
      <td><div class="dt-skeleton-bar" style="width:120px"></div></td> <!-- ACTIONS -->
    </tr>
    <!-- ... 5 more ... -->
  </tbody>
  ```
- **Empty** - two flavors (see [empty-and-loading-state.md](empty-and-loading-state.md) §4.2):
  - **No data yet** - "No modules yet" + primary "Add module" (`btn-primary`).
  - **No results** - "No matches for 'x'" + "Clear filters" (`btn-secondary`). Never "Add" here.
- **Error:** human message + **Retry** (`btn-primary`), `role="alert"`. Keep the toolbar so the user can change the query.

Render states **in place of `<tbody>`** (a full-width `colspan` row), never a layout-shifting popover.
Use the shared `.state` layout from [empty-and-loading-state.md](empty-and-loading-state.md) §7 -
**not** `.dt-state`. Icons from the skill's bundled icon registry (see [assets.md](assets.md)) at 48px
(`aria-hidden`), never emoji. Representative markup (no-data variant; no-results swaps to a search icon
+ `role="status"` + "Clear filters", error swaps to `state--error` + `role="alert"` + Retry):

```html
<tbody>
  <tr class="state-row"><td colspan="5">
    <div class="state" role="status">
      <!-- icon: a grid/apps icon at 48px, aria-hidden -->
      <svg class="state-icon" viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
      <p class="state-title">No modules yet</p>
      <p class="state-body">Register your first module to start configuring access.</p>
      <div class="state-actions"><button type="button" class="btn btn-primary">+ Add module</button></div>
    </div>
  </td></tr>
</tbody>
```

The `.state` CSS comes from [empty-and-loading-state.md](empty-and-loading-state.md) §7. Table-scope tweak:
```css
.dt .state-row td { padding: 0; }
.dt .state-row .state { padding: 56px 24px; }
```

---

## 8. Selection & bulk actions

For multi-row operations (delete, export, assign):

- A **leading checkbox column** (`dt-check-col`) + a header **select-all** (selects the rows currently in view / on the page, not silently across all pages).
- When **≥ 1 row** is selected, reveal the **selection bar** (`dt-select-bar`) above the table: **count** ("3 selected") · **bulk action buttons**, each carrying its word (per [buttons.md](buttons.md) §4.2 - the bar's `Delete` opens the confirmation, so it is `btn btn-secondary btn-sm btn-text-danger`, and the dialog's `Delete` is the solid `btn-danger` that commits) · a **Clear** to deselect. Hide at zero selection.
- Selected rows get a subtle `--dt-row-selected` tint.
- A **destructive** bulk action routes through a confirmation ([modals-dialogs.md](modals-dialogs.md) §7) and resolves with a toast ([toasts.md](toasts.md)). Never delete on the first click.

---

## 9. Pagination

**Numbered pagination is the canonical model (ENFORCED):**

- **Show pagination when total rows > 25.**
- **Paging comes after the sort and the filter, never instead of them.** The page is a window onto the
  filtered, sorted result set (§4, §4a): the total is the filtered total, and any filter, sort, or
  page-size change returns to page 1.
- **Default page size 25**; options **25 / 50 / 75 / 100**.
- Info text reads **"Showing 1-25 of 247"**; controls are **‹ Prev · numbered pages · Next ›** with the current page as `dt-page-btn--active` (primary color). Disable Prev on page 1, Next on the last.
- The pager is the table's own primitive, not two button looks: the filled active page is a **current-item marker**, like an active tab, and not this group's solid action - a pager changes what is on screen and commits nothing ([buttons.md](buttons.md) §4.1). Prev and Next are its icon-only members and carry accessible names.
- Collapse long ranges with an ellipsis (`1 2 3 ... 10`).

```html
<div class="dt-pagination">
  <span class="dt-pagination-info">Showing 1-25 of 247</span>
  <div class="dt-pagination-controls">
    <select class="dt-page-size" aria-label="Rows per page">
      <option value="25" selected>25 / page</option>
      <option value="50">50 / page</option>
      <option value="75">75 / page</option>
      <option value="100">100 / page</option>
    </select>
    <div class="dt-page-btns">
      <button class="dt-page-btn" aria-label="Previous page" disabled>‹</button>
      <button class="dt-page-btn dt-page-btn--active dt-page-num">1</button>
      <button class="dt-page-btn dt-page-num">2</button>
      <button class="dt-page-btn dt-page-num">3</button>
      <button class="dt-page-btn" aria-label="Next page">›</button>
    </div>
  </div>
</div>
```

### Alternatives - when numbered isn't the best fit

| Model | Use when | Trade-off |
|---|---|---|
| **Numbered** *(default)* | User needs to jump to a page, know the total, or return to a known position | Requires a known total count |
| **Load more** | Feed-like list, fuzzy totals, read top-down | No page-jumping; long lists grow the DOM |
| **Infinite scroll** | Continuous browsing / discovery (rare in admin) | No footer position; **avoid for data the user must act on row-by-row** - prefer numbered |

Default to numbered for management tables. Use load-more for read-light feeds; reserve infinite scroll
for discovery surfaces, never for tables with row actions or selection.

**Mobile (small screens):** simplify to **Prev / Next** + **"Page 3 of 10"**; hide the per-page
selector (use 25).

---

## 10. Toolbar

The row above the table - light and purposeful.

- **Left:** list **title** (`dt-toolbar-title`, 16px/600 primary color) + muted **count** (`dt-toolbar-count`, "12 found"). The count is the **filtered** total and updates with the bar (§4a).
- **Right (`dt-toolbar-actions`):** the **one create action** (`btn-primary`, e.g. "Add module"), then secondary controls - **column menu** (advanced), **density toggle** (advanced), **export**. An export exports what is on screen: the current filter and sort, not the whole table.
- **That create action is placed once.** On a list / index page it lives in the page header, per the archetype in `ui-ux-quality.md`, and the toolbar then carries only the count and the view controls, which are all secondary and leave the toolbar with no solid button. A table **embedded** in a larger page (a section table inside a detail view) has no page header of its own, so it keeps the create action in its own toolbar.
- **Search & filter** sit just below the toolbar in the `dt-search` slot. They are required on every list/index table and their binding to the rows, counts, and page is §4a; the controls themselves are their own component - see [search-filter.md](search-filter.md).

---

## 11. Responsive (ENFORCED, mobile-first)

Design the small-screen layout first, then layer on wider-screen enhancements. Use width and/or
aspect-ratio breakpoints as needed.

```css
@media (max-width: 48rem) { /* phones / small tablets */ }
@media (max-aspect-ratio: 1/1) { /* portrait orientation */ }
```

- **Horizontal scroll + sticky columns:** keep the table; let it scroll horizontally inside `.dt-scroll`. The **checkbox** (`dt-sticky-left`) and **actions** (`dt-sticky-right`) columns stay pinned so the user never loses the row's identity or controls.
- Give the table a sensible `min-width` so columns don't crush.
- **The action labels stay at every breakpoint.** A narrow screen scrolls the pinned actions column; it never degrades to bare icons (§6).
- **Touch targets ≥ 44px:** on a coarse pointer `btn-sm` grows to a 44px minimum ([buttons.md](buttons.md) §10) and cell padding loosens. The control grows; the label never drops to save width.
- **Pagination** simplifies to Prev / Next + page count; the per-page selector is hidden (§9).
- Do **not** transform rows into stacked cards by default - the chosen behaviour is horizontal scroll with sticky columns.

---

## 12. Full CSS reference (copy-paste ready)

Light-theme default, `dt-`-namespaced, design-system tokens (dark values are fixed too - see the
`[data-theme="dark"]` block in §1 and `design-tokens.md`). Pairs with the button CSS
([buttons.md](buttons.md) §10). Uses `color-mix()`.

```css
/* ===== Card - FLAT at rest: 1px border, no resting shadow ===== */
.dt-card { background: var(--color-surface); border: 1px solid var(--card-border); border-radius: var(--dt-radius); overflow: hidden; }

/* ===== Toolbar ===== */
.dt-toolbar { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; padding: 20px 24px 12px; }
.dt-toolbar-title { font-family: var(--font-heading); font-size: 16px; font-weight: 600; color: var(--color-primary); }
.dt-toolbar-count { font-size: 13px; color: var(--color-text-muted); }
.dt-toolbar-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }

/* ===== Search slot (control lives in search-filter.md) ===== */
.dt-search { display: flex; align-items: center; gap: 8px; margin: 0 24px 16px; padding: 8px 12px; border: 1px solid var(--dt-border-strong); border-radius: 8px; background: var(--color-surface); }
.dt-search svg { width: 16px; height: 16px; color: var(--color-text-muted); flex-shrink: 0; }
.dt-search input { flex: 1; border: none; outline: none; font: inherit; font-size: 14px; color: var(--color-text); background: transparent; }

/* ===== Scroll area (only the body scrolls) ===== */
.dt-scroll { overflow: auto; max-height: min(60vh, 620px); overscroll-behavior: contain; }

/* ===== Table ===== */
.dt { width: 100%; border-collapse: collapse; }
.dt caption { text-align: left; padding: 0 24px 8px; font-size: 13px; color: var(--color-text-muted); }

/* Header - 10px uppercase muted, sticky, single 2px divider */
.dt thead th {
  position: sticky; top: 0; z-index: var(--dt-z-head);
  text-align: left; padding: 10px 16px;
  font-size: 10px; font-weight: 600; letter-spacing: 0.04em; text-transform: uppercase;
  color: var(--color-text-muted); background: var(--dt-head-bg);
  white-space: nowrap; border-bottom: 2px solid var(--dt-border-strong);
}
.dt th.dt-num, .dt td.dt-num { text-align: right; }

/* Sortable header = a button filling the th */
.dt-sort { display: inline-flex; align-items: center; gap: 6px; background: none; border: none; padding: 0; margin: 0; font: inherit; color: inherit; text-transform: inherit; letter-spacing: inherit; cursor: pointer; }
.dt-sort-icon { width: 12px; height: 12px; opacity: 0.35; transition: opacity 0.15s; }
.dt th[aria-sort="ascending"] .dt-sort-icon, .dt th[aria-sort="descending"] .dt-sort-icon { opacity: 1; }
.dt th[aria-sort="descending"] .dt-sort-icon { transform: rotate(180deg); }
.dt-sort:focus-visible { outline: 2px solid var(--color-primary); outline-offset: 2px; border-radius: 4px; }

/* Body - 13px, horizontal dividers only, subtle hover */
.dt tbody td { padding: var(--dt-cell-pad); font-size: 13px; color: var(--color-text); border-bottom: 1px solid var(--dt-border); vertical-align: middle; }
.dt tbody td.dt-num { font-variant-numeric: tabular-nums; }
.dt tbody tr:last-child td { border-bottom: none; }
.dt tbody tr:hover td { background: var(--dt-row-hover); }
.dt--rowlink tbody tr { cursor: pointer; }
.dt--compact tbody td { padding: var(--dt-cell-pad-compact); }

/* ===== Cell content ===== */
.dt-badge { display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; white-space: nowrap; }
.dt-badge--success { background: var(--badge-success-bg); color: var(--badge-success-fg); }
.dt-badge--danger  { background: var(--badge-danger-bg); color: var(--badge-danger-fg); }
.dt-badge--neutral { background: var(--badge-neutral-bg); color: var(--badge-neutral-fg); }
.dt-link { color: var(--color-primary); text-decoration: underline; text-decoration-style: dotted; text-underline-offset: 2px; }   /* an <a>, so no button resets */
.dt-link:hover { text-decoration-style: solid; }
.dt-avatar-cell { display: inline-flex; align-items: center; gap: 8px; }
.dt-avatar { width: 28px; height: 28px; border-radius: 50%; background: color-mix(in srgb, var(--color-primary) 15%, var(--color-surface)); color: var(--color-primary); font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; }
.dt-bar { width: 120px; height: 8px; border-radius: 999px; background: color-mix(in srgb, var(--color-text) 8%, transparent); overflow: hidden; display: inline-block; vertical-align: middle; }
.dt-bar-fill { display: block; height: 100%; border-radius: 999px; background: var(--color-secondary-blue); }
.dt-truncate { max-width: 240px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.dt-empty-cell { color: var(--color-text-muted); }

/* ===== Row actions - layout only (§6) =====
   The controls themselves are the shared .btn .btn-secondary .btn-sm from buttons.md §10, plus
   .btn-text-danger on a destructive one. A table gets no button style of its own: no ghost, no
   outline, no dt- namespaced button, and no per-table size or hover override. */
.dt-actions { display: flex; align-items: center; gap: 6px; flex-wrap: nowrap; white-space: nowrap; }

/* ===== Selection ===== */
.dt-check-col { width: 44px; text-align: center; }
.dt input[type="checkbox"] { width: 16px; height: 16px; accent-color: var(--color-primary); cursor: pointer; }
.dt tbody tr.dt-row--selected td { background: var(--dt-row-selected); }
.dt-select-bar { display: flex; align-items: center; gap: 12px; padding: 10px 24px; border-bottom: 1px solid var(--dt-border); background: color-mix(in srgb, var(--color-primary) 6%, transparent); font-size: 14px; }
.dt-select-bar[hidden] { display: none; }
.dt-select-count { font-weight: 600; color: var(--color-primary); }
.dt-select-spacer { flex: 1; }

/* ===== Sticky columns (advanced, horizontal scroll) ===== */
.dt-sticky-left { position: sticky; left: 0; z-index: var(--dt-z-sticky-col); background: var(--color-surface); }
.dt thead .dt-sticky-left { z-index: var(--dt-z-sticky-corner); }
.dt-sticky-right { position: sticky; right: 0; z-index: var(--dt-z-sticky-col); background: var(--color-surface); box-shadow: -1px 0 0 var(--dt-border); }
.dt thead .dt-sticky-right { z-index: var(--dt-z-sticky-corner); }
.dt tbody tr:hover .dt-sticky-left, .dt tbody tr:hover .dt-sticky-right { background: color-mix(in srgb, var(--color-text) 7%, var(--color-surface)); } /* opaque per-theme equivalent of --dt-row-hover */

/* ===== Expandable rows ===== */
.dt-expand-btn { display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; border: none; background: none; cursor: pointer; color: var(--color-text-muted); }
.dt-expand-btn svg { width: 14px; height: 14px; transition: transform 0.15s; }
.dt-expand-btn[aria-expanded="true"] svg { transform: rotate(90deg); }
.dt-detail-row td { background: color-mix(in srgb, var(--color-text) 3%, transparent); padding: 16px 24px; }
.dt-detail-row[hidden] { display: none; }

/* ===== Inline cell editing ===== */
.dt-editable { cursor: text; }
.dt-editable:hover { background: color-mix(in srgb, var(--color-secondary-blue) 8%, transparent); }
.dt-cell-edit { width: 100%; box-sizing: border-box; padding: 6px 8px; font: inherit; font-size: 13px; color: var(--color-text); border: 1px solid var(--color-secondary-blue); border-radius: 6px; background: var(--color-surface); }
.dt-cell-edit:focus-visible { outline: 2px solid var(--color-primary); outline-offset: 2px; }

/* ===== Column show/hide menu ===== */
.dt-col-menu { position: relative; }
.dt-col-menu-panel { position: absolute; right: 0; top: calc(100% + 6px); z-index: 20; min-width: 200px; background: var(--color-surface); border: 1px solid var(--dt-border-strong); border-radius: 8px; box-shadow: var(--shadow-lg); padding: 8px; }
.dt-col-menu-panel[hidden] { display: none; }
.dt-col-menu-item { display: flex; align-items: center; gap: 8px; padding: 6px 8px; font-size: 13px; border-radius: 6px; cursor: pointer; }
.dt-col-menu-item:hover { background: color-mix(in srgb, var(--color-text) 6%, transparent); }

/* ===== Footer & pagination ===== */
.dt-footer { padding: 12px 24px; font-size: 13px; color: var(--color-text-muted); border-top: 1px solid var(--dt-border); }
.dt-pagination { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; padding: 14px 24px; border-top: 1px solid var(--dt-border); }
.dt-pagination-info { font-size: 13px; color: var(--color-text-muted); }
.dt-pagination-controls { display: flex; align-items: center; gap: 8px; }
.dt-page-size { height: 32px; border: 1px solid var(--dt-border-strong); border-radius: 7px; padding: 0 8px; font: inherit; font-size: 13px; background: var(--color-surface); color: var(--color-text); }
.dt-page-btns { display: flex; gap: 4px; }
.dt-page-btn { height: 32px; min-width: 32px; padding: 0 8px; border: 1px solid var(--dt-border-strong); border-radius: 7px; background: var(--color-surface); font: inherit; font-size: 13px; font-weight: 600; color: var(--color-text); cursor: pointer; }
.dt-page-btn:hover:not(:disabled):not(.dt-page-btn--active) { background: color-mix(in srgb, var(--color-text) 6%, transparent); }
.dt-page-btn--active { background: var(--color-primary); border-color: var(--color-primary); color: #fff; }
.dt-page-btn:disabled { opacity: 0.45; cursor: not-allowed; }
.dt-load-more { display: flex; justify-content: center; padding: 16px; border-top: 1px solid var(--dt-border); }

/* ===== Skeleton - shared shimmer, identical to .skeleton in empty-and-loading-state.md §7 ===== */
.dt-skeleton-bar { height: 12px; border-radius: 6px; background: linear-gradient(90deg, color-mix(in srgb, var(--color-text) 8%, transparent) 25%, color-mix(in srgb, var(--color-text) 15%, transparent) 37%, color-mix(in srgb, var(--color-text) 8%, transparent) 63%); background-size: 400% 100%; animation: dt-shimmer 1.4s ease infinite; }
@keyframes dt-shimmer { 0% { background-position: 100% 50%; } 100% { background-position: 0 50%; } }

/* Empty / error - use .state from empty-and-loading-state.md §7, not .dt-state. Only the padding tweak lives here: */
.dt .state-row td { padding: 0; }
.dt .state-row .state { padding: 56px 24px; }

/* ===== Responsive (mobile-first) ===== */
@media (max-width: 48rem) {
  .dt thead th, .dt tbody td { padding: 12px; }
  /* touch targets: btn-sm grows to 44px in buttons.md §10 (pointer: coarse) - no table override */
  .dt-pagination-controls { width: 100%; justify-content: space-between; }
  .dt-page-size { display: none; }                        /* default 25; hide selector */
  .dt-page-btns .dt-page-num { display: none; }           /* Prev/Next + page count only */
}

/* ===== Reduced motion ===== */
@media (prefers-reduced-motion: reduce) {
  .dt-skeleton-bar { animation-duration: 2.6s; }
  .dt-expand-btn svg, .dt-sort-icon { transition: none; }
}
```

---

## 13. Full JS reference (behavior contract)

Dependency-free implementations. Frameworks should use their own data-grid/table primitive - the
**contract** (sort cycle, selection→bulk bar, expand, inline edit commit/cancel, pagination, column
toggle) is identical.

> **Scope warning for the sort below.** It reorders the rows currently in the DOM, which is correct only
> for a table that holds its whole result set (no pagination, no server query). The moment the list is
> paginated or server-fed, the sort is a request parameter: send the column and direction, take the ordered
> page back, and let the server order all the matches (§4, §4a). The same applies to filtering. Keeping the
> `aria-sort` cycle and the caret is the part that carries over.

```js
/* ===== Sorting (§4) - cycle none → asc → desc, reorder rows, set aria-sort =====
   Client-side, whole-set-in-DOM case only. Paginated or server-fed: send sort + dir to the server. */
function initSort(table) {
  const tbody = table.tBodies[0];
  table.querySelectorAll('th[aria-sort] .dt-sort').forEach(btn => {
    const th = btn.closest('th');
    const idx = [...th.parentNode.children].indexOf(th);
    btn.addEventListener('click', () => {
      const cur = th.getAttribute('aria-sort');
      const next = cur === 'ascending' ? 'descending' : 'ascending';
      table.querySelectorAll('th[aria-sort]').forEach(h => h.setAttribute('aria-sort', 'none'));
      th.setAttribute('aria-sort', next);
      const rows = [...tbody.querySelectorAll('tr:not(.dt-detail-row)')];
      const num = th.classList.contains('dt-num');
      rows.sort((a, b) => {
        const x = a.children[idx].textContent.trim(), y = b.children[idx].textContent.trim();
        const cmp = num ? (parseFloat(x) || 0) - (parseFloat(y) || 0) : x.localeCompare(y);
        return next === 'ascending' ? cmp : -cmp;
      });
      rows.forEach(r => tbody.appendChild(r));   // re-append in sorted order
    });
  });
}

/* ===== Selection (§8) - select-all + per-row → toggle .dt-row--selected, show bulk bar ===== */
function initSelection(table, bar) {
  const all = table.querySelector('thead input[type="checkbox"]');
  const boxes = () => [...table.querySelectorAll('tbody input[type="checkbox"]')];
  const count = bar.querySelector('.dt-select-count');
  function refresh() {
    const sel = boxes().filter(b => b.checked);
    sel.forEach(b => b.closest('tr').classList.add('dt-row--selected'));
    boxes().filter(b => !b.checked).forEach(b => b.closest('tr').classList.remove('dt-row--selected'));
    bar.hidden = sel.length === 0;
    if (count) count.textContent = `${sel.length} selected`;
    if (all) all.checked = sel.length > 0 && sel.length === boxes().length;
  }
  if (all) all.addEventListener('change', () => { boxes().forEach(b => (b.checked = all.checked)); refresh(); });
  boxes().forEach(b => b.addEventListener('change', refresh));
  bar.querySelector('[data-clear]')?.addEventListener('click', () => { boxes().forEach(b => (b.checked = false)); if (all) all.checked = false; refresh(); });
  refresh();
}

/* ===== Expandable rows (§5) - toggle the matching detail row ===== */
function initExpand(table) {
  table.querySelectorAll('.dt-expand-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const open = btn.getAttribute('aria-expanded') === 'true';
      btn.setAttribute('aria-expanded', String(!open));
      const detail = btn.closest('tr').nextElementSibling;
      if (detail?.classList.contains('dt-detail-row')) detail.hidden = open;
    });
  });
}

/* ===== Inline cell editing (§5) - Enter/blur commit, Esc cancel ===== */
function initInlineEdit(table) {
  table.querySelectorAll('.dt-editable').forEach(cell => {
    cell.addEventListener('click', () => {
      if (cell.querySelector('.dt-cell-edit')) return;
      const prev = cell.textContent.trim();
      cell.innerHTML = `<input class="dt-cell-edit" value="${prev}">`;
      const input = cell.firstChild; input.focus(); input.select();
      const commit = () => { cell.textContent = input.value.trim() || '-'; };
      const cancel = () => { cell.textContent = prev; };
      input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { e.preventDefault(); commit(); }
        if (e.key === 'Escape') { e.preventDefault(); cancel(); }
      });
      input.addEventListener('blur', commit);
    });
  });
}

/* ===== Pagination (§9, numbered) - slice rows by page & size ===== */
function initPagination(table, { info, sizeSelect, btnHost, pageSize = 25 }) {
  const tbody = table.tBodies[0];
  let size = pageSize, page = 1;
  const rows = () => [...tbody.querySelectorAll('tr:not(.dt-detail-row)')];
  function render() {
    const all = rows(), total = all.length, pages = Math.max(1, Math.ceil(total / size));
    page = Math.min(page, pages);
    const start = (page - 1) * size, end = Math.min(start + size, total);
    all.forEach((r, i) => (r.hidden = i < start || i >= end));
    if (info) info.textContent = `Showing ${total ? start + 1 : 0}-${end} of ${total}`;
    btnHost.innerHTML = '';
    const mk = (label, p, { active = false, disabled = false, aria } = {}) => {
      const b = document.createElement('button');
      b.className = 'dt-page-btn' + (active ? ' dt-page-btn--active' : '') + (aria ? '' : ' dt-page-num');
      b.textContent = label; if (aria) b.setAttribute('aria-label', aria);
      b.disabled = disabled;
      b.addEventListener('click', () => { page = p; render(); });
      btnHost.appendChild(b);
    };
    mk('‹', page - 1, { disabled: page === 1, aria: 'Previous page' });
    for (let p = 1; p <= pages; p++) mk(String(p), p, { active: p === page });
    mk('›', page + 1, { disabled: page === pages, aria: 'Next page' });
  }
  sizeSelect?.addEventListener('change', () => { size = parseInt(sizeSelect.value, 10); page = 1; render(); });
  render();
}

/* ===== Column show/hide (§4) - toggle every cell in a column index ===== */
function initColumnMenu(table, menu) {
  menu.querySelectorAll('input[data-col]').forEach(cb => {
    cb.addEventListener('change', () => {
      const idx = parseInt(cb.dataset.col, 10);
      table.querySelectorAll('tr').forEach(tr => {
        const cell = tr.children[idx];
        if (cell) cell.hidden = !cb.checked;
      });
    });
  });
}

/* ===== Density (§5) - toggle compact ===== */
function initDensity(table, toggle) {
  toggle.addEventListener('click', () => table.classList.toggle('dt--compact'));
}
```

> **Framework note:** prefer a headless table library or your UI library's data-grid primitive for
> sorting, selection, column visibility, and virtualization. Your job is then to apply the token
> styling (§1, §12), keep `aria-sort` correct, and wire the bulk-bar + confirmations.

---

## 14. Accessibility checklist

- [ ] Real semantic `<table>` with `<thead>`/`<tbody>` and `<th scope="col">` (and `scope="row"` where a row header exists).
- [ ] A `<caption>` or `aria-label` names the table.
- [ ] Sortable headers are **`<button>`s**; the `th` carries `aria-sort` (`none`/`ascending`/`descending`), kept in sync on every sort.
- [ ] Sort direction isn't signalled by the caret alone - `aria-sort` carries it for AT.
- [ ] The list opens on its declared default sort, and that column shows it in `aria-sort`.
- [ ] The search input has a real label, and the filter controls are keyboard-operable per [search-filter.md](search-filter.md) §12.
- [ ] The result count carries its own polite live region and is announced when a filter or the sort changes, rather than silently swapped or drowned by a live `<tbody>` re-reading every row.
- [ ] Row checkboxes and select-all have labels; selection count is announced.
- [ ] Status isn't color-only - the word ("Active") is in the pill.
- [ ] Row-click navigation has a real focusable affordance (link/button); whole-row click is an enhancement, and clicks on inner controls don't trigger it.
- [ ] Every actions-column control is a **labeled button** showing its word; an `aria-label` is the floor for assistive tech, never a replacement for the visible label (§6).
- [ ] A `More` overflow control's accessible name names the row it belongs to.
- [ ] Touch targets ≥ 44×44px on touch, reached by growing the control, never by dropping its label.
- [ ] Visible `:focus-visible` ring on every interactive element (sort, checkbox, actions, paging).
- [ ] Pagination buttons have `aria-label`s (Previous/Next); disabled states use `disabled`.
- [ ] Loading/empty/error are announced (e.g. `aria-live` on the body region) and don't shift layout.
- [ ] Sticky header/columns don't trap or hide focus.

---

## 15. Do's and Don'ts

| ✅ Do | ❌ Don't | Why |
|---|---|---|
| Keep the light look: white card, horizontal dividers, subtle hover | Add gradient bars, vertical gridlines, or heavy borders | Tables are deliberately airy |
| Right-align numbers with tabular figures | Left-align amounts and counts | Misaligned digits are hard to compare |
| Pick the smallest tier that does the job | Ship the full grid for a 6-row read-only list | Unused controls are noise |
| Pace data with numbered pagination > 25 rows | Dump 500 rows in one scroll | The 25-row default keeps pages fast |
| Show skeleton / empty / error states | Leave a blank box while loading or empty | A bare box reads as broken |
| Make sortable headers buttons + `aria-sort` | Sort on a non-focusable `th` with a caret only | Keyboard + AT users must sort too |
| Give every list/index table both a sort and a filter bar | Ship a list the user can only read top to bottom | Finding a record is the whole point of a list (§4, §4a) |
| Sort and filter the whole result set on the server | Reorder or filter only the 25 rows in the DOM | The matches on page 3 never surface |
| Put the sort, filters, and page in the URL query | Keep them in client state only | A refresh or a shared link must reproduce the view |
| Return to page 1 when a filter or the sort changes | Leave the user on page 7 of a 2-page result | They see an empty table that is not empty |
| Report the filtered total in both counts | Show "247 rows" beside 12 filtered rows | The two numbers disagreeing reads as a bug |
| Reveal the bulk bar only when rows are selected | Keep a permanent disabled bulk toolbar | Show the action when it's actionable |
| Confirm destructive bulk actions, then toast | Delete selected rows on the first click | Irreversible actions need a guard (§8) |
| Render `-` for empty cells | Leave cells blank | A blank cell looks like a bug |
| Label every actions-column control: the icon **+** "View" / "Edit" / "Delete" | Ship a bare pencil and trash side by side | An unlabeled icon makes the user guess, and a touch device has no hover to explain it (§6) |
| Keep one solid button per group - the list's one create CTA | Make a row action, a bulk-bar Delete, or an Export the second solid button | Competing emphasis hides the real action ([buttons.md](buttons.md) §4) |
| Reuse `btn btn-secondary btn-sm` for row actions | Give the table its own ghost, outline, or `dt-` button style | The same action must look the same on every screen ([buttons.md](buttons.md) §4) |
| Sticky checkbox + actions on horizontal scroll | Let the row's identity/actions scroll out of view | Users lose their place in wide grids |

---

## 16. Rules for the AI assistant

**ALWAYS**
- Choose the tier by the task (§3); keep the light styling (§1) at every tier - no gradient bars, no vertical gridlines.
- Use a semantic `<table>`; right-align numbers; render status as a pill, never by color alone (§6).
- Make every orderable column sortable with `<button>` + `aria-sort`, and open the list on a declared default sort (§4). The actions column and free-text notes are the only exceptions.
- Give every list/index table a search and filter bar, with a facet per dimension the list is narrowed by, taken from the columns' codified reference sets (§4a).
- Run the sort and the filter over the whole result set, on the server where the list is paginated; return to page 1 on any filter, sort, or page-size change; report the filtered total in both counts; keep the sort, filters, and page in the URL query (§4, §4a, §9).
- Ship loading, empty (no-data vs no-results), and error states (§7).
- Add pagination when rows > 25: numbered, default 25 (25/50/75/100), "Showing X-Y of N"; simplify to Prev/Next on touch (§9).
- For multi-row ops, use a leading checkbox + selection bar that appears only when rows are selected; route destructive bulk actions through a confirmation + toast (§8).
- Make every actions-column control a labeled button - the shared `btn btn-secondary btn-sm` with its icon and its word, `btn-text-danger` when it opens a destructive confirmation - and move anything past about three behind one `More` control (§6, [buttons.md](buttons.md) §4.2).
- Keep exactly one solid button per action group: the list's one create CTA is the only solid action (in the page header on a list / index page, in the toolbar for an embedded table), and row actions, bulk-bar actions, and view controls are all secondary ([buttons.md](buttons.md) §4).
- On narrow screens, horizontally scroll with sticky checkbox/actions columns, keeping the action labels visible at every breakpoint (§6, §11).
- Use design tokens; reuse [buttons.md](buttons.md), [modals-dialogs.md](modals-dialogs.md) for confirmations, [forms.md](forms.md) for inline-edit validation.

**NEVER**
- Ship a list/index table with no sort or no filter bar, or treat either as an advanced extra to add later.
- Sort or filter only the rows already loaded while the rest of the matches sit behind pagination.
- Leave a list with no declared default sort, or lose the sort and filters on a refresh because they were never in the URL.
- Reach for the Advanced grid (or infinite scroll) when a Standard table + numbered pagination fits.
- Signal status or sort direction by color alone.
- Leave a blank cell, or a blank table with no loading/empty/error state.
- Hide the row's identifying column or its actions behind horizontal scroll.
- Put a bare icon-only control in an actions column, or drop an action's label to save width.
- Give a table its own button style (ghost, outline, or a `dt-` namespaced button), or add a second solid button to an action group.
- Hardcode colors or invent shades outside the palette; add heavy borders/gradients.

---

## 17. Quick decision guide

```
Which tier?
├─ Detail-page sub-table, or dashboard recent
│  activity ..................................... Simple table (no sort, no filter)
├─ A list/index users search, sort, open ....... Standard data table
│                                               (sort + filter bar REQUIRED, pagination > 25)
└─ Dense management: multi-select, sticky cols,
   column toggle, density, expand, inline edit .. Advanced data grid (same light styling)

Columns & cells:
├─ Numbers ..................................... right-align, tabular figures (dt-num)
├─ Status ...................................... pill (success/danger/neutral) + the word
├─ Long text ................................... truncate + tooltip (dt-truncate)
└─ Empty value ................................. render "-" (dt-empty-cell)

Row actions (every list/index):
├─ Every control ............................... btn btn-secondary btn-sm + its icon AND its word
├─ Destructive (opens the confirmation) ........ + btn-text-danger (the dialog's Delete is btn-danger)
├─ More than about three ....................... first three inline, the rest behind one "More"
├─ One solid per group ......................... the list's one create CTA; no row action is solid
└─ Narrow screen ............................... the pinned column scrolls; the words never drop

States (always all three):
├─ Loading ..................................... skeleton rows (keep the header)
├─ Empty ....................................... no-data (+Add) vs no-results (+Clear filters)
└─ Error ....................................... message + Retry

Sort & filter (every list/index):
├─ Which columns sort? ......................... all but actions and free-text notes
├─ Opening order ............................... a declared default sort
├─ Which facets? ............................... one per dimension the list is narrowed by
├─ Applied to ................................. the whole result set (server-side when paginated)
├─ On change ................................... back to page 1, both counts show the filtered total
└─ Kept in .................................... the URL query (?q=&status=&sort=&dir=&page=&size=)

Pagination:
├─ Management/list view ........................ numbered (default 25; 25/50/75/100; show > 25)
├─ Read-light feed ............................. load more
├─ Discovery surface ........................... infinite scroll (never for row-action tables)
└─ Touch ....................................... Prev / Next + "Page X of Y" (hide page-size)

Responsive: horizontal scroll + sticky checkbox/actions columns · 44px touch targets (grow the control,
never drop the label)
Every interactive element: <button>/label · aria-sort/aria-label · :focus-visible ring
```
