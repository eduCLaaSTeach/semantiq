# Search & Filter UI/UX Design Guide

Reference for AI-assisted development of search boxes, filter controls, and result-narrowing UI. Values
here (palette, fonts, icons, class prefixes) are this design system's tokens, defined once and reused;
the canonical source is `design-tokens.md`. Omitting unwired (non-navigation) filter controls is correct
- the "Soon" placeholder rule applies only to navigation destinations, not filter affordances.
Responsive is mobile-first; every token is defined in both a light and a dark theme.

> **TL;DR for the AI**
> 1. **Search ≠ Filter.** Search is free-text matching across fields (§3); filter narrows by a known facet - status, type, owner (§4-§5). A list often has both.
> 2. **The search box is the `.search-bar` pill** (§3): white, 1px-bordered, 8px-radius, leading magnifier, borderless input. Show a **clear (✕)** the moment it has text.
> 3. **Pick the filter affordance by cardinality** (§4-§5): 2-5 mutually-exclusive states → underline **tabs** (with status dots); a few always-visible facets → labelled **dropdown fields** (each independently clearable, with a **Reset filters** button); applied multi-facets → removable **chips**; many grouped criteria → a **`Filters (n)` popover**.
> 4. **Typeahead is for picking one known entity** (§6) - a `combobox` + `listbox` with full keyboard nav - not for filtering a table in place.
> 5. **Every search/filter has a no-results state** (§7). Filter **client-instant** only where the table already holds its whole result set; a paginated or server-fed list sends the query to the server (**debounced ~250ms**) and renders the narrowed page it returns ([tables.md](tables.md) §4a).
> 6. **Every button in the bar is the shared neutral secondary** - `btn btn-secondary` from [buttons.md](buttons.md) §4: `Filters`, `Reset filters`, `Clear all`, `Apply`. Nothing in a filter commits anything, so neither the bar nor the popover footer has a **solid button**; the search field's clear ✕ is `icon-btn` chrome, not a button.
> 7. Use the design tokens - never hardcode a hex or invent a shade outside the palette.

---

## Contents

1. [Design tokens (source of truth)](#1-design-tokens-source-of-truth)
2. [Anatomy of a search/filter bar](#2-anatomy-of-a-searchfilter-bar)
3. [Search input (ENFORCED shape)](#3-search-input-enforced-shape)
4. [Filter tabs, chips & dropdown fields](#4-filter-tabs-chips--dropdown-fields)
5. [Faceted filter popover](#5-faceted-filter-popover)
6. [Typeahead / autocomplete](#6-typeahead--autocomplete)
7. [States - empty, no-results, loading (ENFORCED)](#7-states--empty-no-results-loading-enforced)
8. [Choosing the right control](#8-choosing-the-right-control)
9. [Responsive (ENFORCED, mobile-first)](#9-responsive-enforced-mobile-first)
10. [Full CSS reference (copy-paste ready)](#10-full-css-reference-copy-paste-ready)
11. [Full JS reference (behavior contract)](#11-full-js-reference-behavior-contract)
12. [Accessibility checklist](#12-accessibility-checklist)
13. [Do's and Don'ts](#13-dos-and-donts)
14. [Rules for the AI assistant](#14-rules-for-the-ai-assistant)
15. [Quick decision guide](#15-quick-decision-guide)

---

## 1. Design tokens (source of truth)

Search & filter consume the brand palette plus a small `sf-` token set. The look is a `.search-bar` pill:
white, 1px border, 8px radius, muted leading icon, borderless input. Buttons inside a filter follow
[buttons.md](buttons.md); a filter over a table follows the [table guide](tables.md) toolbar.

```css
:root {
    /* Brand palette (approved - canonical: design-tokens.md) */
    --color-primary: #193E6B;                  /* Midnight Blue - active tab, focus ring, selected facet */
    --color-accent: #B3A125;                   /* Green Gold */
    --color-secondary-violet: #7F3F98;         /* Cadmium Violet */
    --color-secondary-blue: #448E9D;           /* Jelly Bean Blue - typeahead highlight, edit affordance */
    --color-secondary-sunray: #E9AC53;         /* Sunray */
    --color-success: #5F8025;                  /* Avocado Green - status dot: active */
    --color-danger: #991547;                   /* Violet-Red - status dot: inactive */
    --color-background: #E8DFD0;               /* canvas (dark: #1A2E46) */
    --color-surface: #FFFFFF;                  /* dark: #253E5D */
    --color-text: #1E2E42;                     /* ink (dark: #E9EFF6) */
    --color-text-muted: #4D5E75;               /* dark: #A3B2C5 */

    /* Search & filter tokens */
    --sf-radius: 8px;                  /* pill + popover radius */
    --sf-border: color-mix(in srgb, var(--color-text) 14%, transparent);
    --sf-border-strong: color-mix(in srgb, var(--color-text) 26%, transparent);
    --sf-focus-ring: color-mix(in srgb, var(--color-primary) 35%, transparent);
    --sf-field-h: 32px;                /* control height - the forms.md --field-height-page value */
    --sf-field-pad: 0 12px;            /* horizontal only, like a forms .field; the 32px height centers it */
    --sf-icon: var(--color-text-muted);
    --sf-pop-shadow: 0 8px 24px rgba(0, 0, 0, 0.14);
    --sf-option-active: color-mix(in srgb, var(--color-secondary-blue) 14%, transparent);
    --sf-chip-bg: color-mix(in srgb, var(--color-primary) 8%, transparent);
    --sf-z-pop: 50;                    /* popover / listbox above the list */
}
```

**Light rules (ENFORCED):** the search pill is a flat white field - no inner shadow, no fill tint, no
gradient. Active tabs and selected facets carry navy (`--color-primary`); the typeahead's active option
uses the blue tint (`--sf-option-active`). Never signal a filter state by color alone - pair it with a
label, dot, or checkmark.

---

## 2. Anatomy of a search/filter bar

**The bar is required on every list/index table** ([tables.md](tables.md) §4a), so this is not a decision
about whether to narrow a list, only about which controls do it. That file owns how the bar binds to the
rows, the counts, the page, and the URL; this one owns the controls.

Narrowing controls sit **above the table**, inside or under its toolbar. Order left → right: **search**,
then **filter tabs/chips**, then a **`Filters (n)`** button for overflow facets. Applied facets echo back
as **removable chips** on the row below.

```
┌──────────────────────────────────────────────────────────────────────┐
│  🔍 Search by name, code, owner...                            ✕         │  ← .sf-search (pill)
├──────────────────────────────────────────────────────────────────────┤
│  ● All   ● Active   ● Inactive            [ ▼ Filters (2) ]            │  ← .sf-tabs + .sf-filter-btn (funnel icon)
├──────────────────────────────────────────────────────────────────────┤
│  Status [Active ▾] ✕   Type [Agent ▾] ✕   Owner [Any ▾]   [Reset]     │  ← .sf-filters-row (dropdown fields)
├──────────────────────────────────────────────────────────────────────┤
│  Status: Active ✕   Type: Agent ✕                        [Clear all]   │  ← .sf-chips (applied facets)
└──────────────────────────────────────────────────────────────────────┘
                       ┌───────────────────────────────┐
                       │ Status                         │  ← .sf-pop (faceted popover)
                       │  ☑ Active   ☐ Inactive         │
                       │ Type                           │
                       │  ☑ Agent    ☐ Workflow  ☐ Tool │
                       │       [Clear all]   [Apply]    │
                       └───────────────────────────────┘
```

- **Search** (`.sf-search`): the pill; the primary free-text affordance.
- **Tabs** (`.sf-tabs`): 2-5 mutually-exclusive states with a leading dot; underline marks active.
- **Dropdown fields** (`.sf-filters-row`): a few labelled custom dropdowns (button + listbox), each with a per-field **✕**, plus a **Reset filters** button - facets that stay visible without opening a popover (§4).
- **Filter button** (`.sf-filter-btn`): opens the faceted popover; carries an active-count badge.
- **Chips** (`.sf-chips`): one removable chip per applied facet; **Clear all** resets them.
- **Popover** (`.sf-pop`): grouped criteria with **Apply** / **Clear all**.
- **Typeahead** (`.sf-typeahead`, §6): a search variant whose input owns a results `listbox`.
- **Buttons**: `Filters`, `Reset filters`, `Clear all`, and `Apply` are all the one shared neutral secondary ([buttons.md](buttons.md) §4). The bar only changes what is on screen, so it carries **no solid button** - the page's solid action stays the archetype's primary CTA in the page header.

---

## 3. Search input (ENFORCED shape)

The search box is the `.search-bar` pill, exposed as `.sf-search` here - the **only** approved free-text search shape.

- **Structure** - `<div class="sf-search">` wrapping a leading **search icon**, an `<input type="search">` (borderless, transparent), and a trailing **clear button**.
- **Icon** - a search (magnifier) icon from the skill's bundled icon registry (library component, or inline outlined search SVG). Never an emoji. Decorative (`aria-hidden`) - the input's label carries meaning.
- **Width (PRINCIPLED)** - the pill sits at **~40% of its container** (`min 240px`, `max 420px`); widen toward full-width on a search-first screen or narrow viewports (§9).
- **Height** - the pill and every filter control are **32px**, the same control height as a [forms](forms.md) field (`--field-height-page`), with horizontal-only padding. They sit in a row beside form fields and buttons, so a shorter control reads as a mistake.
- **Placeholder** - name the searchable fields: *"Search by name, code, or owner..."*. Never a bare *"Search..."* when the scope is non-obvious.
- **Clear (✕)** - icon-only chrome, not a button: `icon-btn icon-btn-sm` from [buttons.md](buttons.md) §4.1, with `aria-label="Clear search"`. Appears **only when the field has text** (`hidden` otherwise); clears the value, refocuses the input, re-runs the search. **ENFORCED:** no permanently-visible empty ✕.
- **Result count** - an optional muted *"12 results"* may sit to the right, updating live.
- **Behavior** - filtering is **instant** (each keystroke) only when the table already holds its whole result set; the moment the list is paginated or server-fed, the query goes to the server **debounced ~250ms** (PRINCIPLED) with the in-field spinner (§7) while in flight, and the narrowed page comes back ([tables.md](tables.md) §4a). `Esc` in a non-empty field clears it (same as ✕).
- **Type** - `type="search"` for semantics; native clear is suppressed (we render our own).

---

## 4. Filter tabs, chips & dropdown fields

### Tabs (mutually-exclusive state)

For **2-5 mutually-exclusive states** (All / Active / Inactive), use underline tabs.

> **Filter tabs are not navigation tabs.** These narrow a list's rows and read as a *filter*; the in-canvas **navigation** tab strip is a different control - see [tabs.md](tabs.md).

- `role="tablist"` of `role="tab"` buttons; native `<button>` chrome stripped (no border/background).
- A leading **status dot** (`.sf-dot`) colors the meaning: neutral grey (All), success (Active), muted/danger (Inactive). Decorative (`aria-hidden`) - the label carries meaning.
- **Active** tab: navy text (600) with a 2px navy underline. Inactive: muted, underline transparent; hover darkens text only.
- **ENFORCED:** never more than ~5 tabs. Six or more facets → popover (§5), not a tab row.

### Chips (applied multi-facets)

When facets come from the popover, **echo each applied one as a removable chip** so the active set is always visible.

- `.sf-chip` = a soft navy-tinted pill: *"Status: Active"* + a trailing **✕** button (`aria-label="Remove Status: Active filter"`). Removing a chip drops that facet and re-runs.
- A **Clear all** button - the shared neutral secondary, `btn btn-secondary btn-sm sf-clear-all` ([buttons.md](buttons.md) §4) - sits at the end of the chip row when ≥ 1 chip is present. Dropping a filter is not destructive, so it is never danger-colored and never borderless.
- The chip row is **absent** (no markup) when nothing is applied - never an empty bar.

### Dropdown fields (always-visible facets) - PRINCIPLED

When a list has **a handful of independent facets** that benefit from staying on screen (Status, Type,
Owner), render them as a row of labelled **dropdown fields** rather than hiding them behind the popover.
The inline alternative to §5.

These are **custom single-select dropdowns** - same component family as the typeahead (§6), so the menu
is **branded** (rounded corners, real hover highlight, scrollable list, matching border). A native
`<select>` can't be styled this way.

- **Row** (`.sf-filters-row`) - a flex-wrap row of `.sf-dropdown` fields followed by a **Reset filters** button.
- **Field** (`.sf-dropdown`) - a `<label>` above a **trigger button** (`.sf-dropdown-toggle`) and a trailing **✕** (`.sf-field-clear`). The trigger is field-shaped (same border, radius, height as a form input), shows the selected label or the neutral placeholder (*"All"* / *"Any"*) plus a chevron, and carries `aria-haspopup="listbox"` + `aria-expanded`.
- **Menu** (`.sf-listbox` / `.sf-option`) - reuses the typeahead's listbox: rounded, soft-shadowed, **scrollable** (`max-height`, `overflow-y:auto`), with a **hover** highlight and the current value marked `aria-selected` (blue tint). `role="listbox"` of `role="option"`.
- **Outline = field border** - trigger and menu both use the neutral field border (`--sf-border`); focus uses a subtle ring in that **same border color**, not navy.
- **Per-field ✕** (`.sf-field-clear`) - resets **just that field**; **hidden until** a non-default value is chosen.
- **Reset filters** (`.sf-reset`) - the shared neutral secondary, `btn btn-secondary btn-sm sf-reset` ([buttons.md](buttons.md) §4), clearing **every** field at once; **hidden until** ≥ 1 field is set (no permanently-visible dead control).
- **Keyboard** - open on click/`Enter`/`Space`; `↑`/`↓` move the active option, `Enter` selects, `Esc`/outside-click closes and returns focus to the trigger.
- **Behavior** - selecting re-runs the filter immediately; the active set is the union of all non-default fields. For a *searchable* single-pick of one entity, use the typeahead (§6).
- **ENFORCED (render-only-functional-UI):** omit any field whose options aren't wired - never a disabled placeholder dropdown.

---

## 5. Faceted filter popover

For **many grouped criteria**, a single **Filters** button (funnel icon) opens an anchored popover,
keeping the bar clean while surfacing the active count.

- **Trigger** (`.sf-filter-btn`) - the shared neutral secondary (`btn btn-secondary sf-filter-btn`, [buttons.md](buttons.md) §4) carrying a **filter funnel icon** and a count badge when ≥ 1 facet is applied: **`▽ Filters (2)`**. The icon is **outlined when no filter is set, solid once ≥ 1 is applied** (swap the icon, or toggle the `.is-filtered` class to show the solid SVG) - that is the **icon** reporting filter state, not the button changing emphasis, so the shell stays the same secondary either way while the glyph and the badge carry the state. `aria-haspopup="dialog"`, `aria-expanded` reflects open state.
- **Panel** (`.sf-pop`) - `role="dialog"`, anchored under the trigger, `--sf-pop-shadow`, 8px radius. Grouped sections, each a `<fieldset>` with a `<legend>`: single-choice → radios, multi-choice → checkboxes.
- **Footer** - **Clear all** (left) and **Apply** (right), both the shared neutral secondary (`btn btn-secondary btn-sm`). A filter surface changes only what is on screen, so the footer carries **no solid button** ([buttons.md](buttons.md) §4). **Apply** commits the selection, closes the popover, updates the chips (§4) and the count badge. **PRINCIPLED:** small filter sets may apply live (no Apply button); large/expensive ones use explicit Apply.
- **Dismissal** - `Esc`, outside-click, or Apply closes it; focus returns to the trigger.
- **ENFORCED (render-only-functional-UI):** never render a facet group with no working options. An empty or unwired group is **omitted**, not shown disabled.

---

## 6. Typeahead / autocomplete

Use typeahead when the user must **pick one known entity** from a large set (a user, a module), not to
filter a table in place. It is a search input that owns a results list.

- **Roles** - input is `role="combobox"` with `aria-expanded`, `aria-controls` (the list id), and `aria-activedescendant` (the highlighted option id). The list is `role="listbox"` of `role="option"`. **ENFORCED:** these ARIA hooks are required - a bare div list is not accessible.
- **Matching** - case-insensitive substring; the matched span wrapped `<mark>` (the blue tint), rest muted. Results cap at ~8 with the count announced.
- **Keyboard** - `↓`/`↑` move the active option (wrapping), `Enter` selects it, `Esc` closes the list (keeps text), `Tab` closes and moves on. Mouse hover mirrors the active option.
- **Selection** - picking an option fills the input and fires `onSelect(value)`; the list closes.
- **Empty** - when the query matches nothing, show a single non-selectable **"No matches"** row; don't collapse the list to nothing.
- **Loading** - server-backed typeahead shows the in-field spinner (§7) while fetching.

---

## 7. States - empty, no-results, loading (ENFORCED)

Every search/filter ships these. Never leave a bare blank list after a query.

- **Empty query** - the unfiltered list; the search shows its placeholder, no ✕, no chips.
- **No results** - render the [empty-states](empty-and-loading-state.md) "no results" block (icon + *"No items match your search"* + a **Clear filters** action that resets search **and** facets). **ENFORCED:** the table never renders zero rows with no message.
- **Loading** (server round-trip) - an in-field **spinner** replaces the magnifier (or sits before the clear ✕); the list shows the table's skeleton/loading state ([tables](tables.md) §7). Inputs stay enabled so the user can keep typing.

---

## 8. Choosing the right control

| Situation | Use | Why |
|---|---|---|
| Free-text across several fields | **Search pill** (§3) | One box, matches everything |
| 2-5 mutually-exclusive states | **Tabs** (§4) | One visible click, current state obvious |
| A few independent facets, kept on screen | **Dropdown fields** (§4) | Always-visible selects, each clearable + Reset |
| Several independent facets, applied set must stay visible | **Chips** (§4) | Each facet removable at a glance |
| Many grouped criteria (6+), or rarely changed | **Filters popover** (§5) | Keeps the bar clean; count badge summarizes |
| Pick one known entity from a large set | **Typeahead** (§6) | Narrows as you type, keyboard-selectable |

Combine deliberately: search **+** tabs is the common list header; add the popover **only** when facets
outgrow a tab row. Don't show tabs **and** a popover for the **same** dimension.

---

## 9. Responsive (ENFORCED, mobile-first)

Design for the small screen first, then layer on enhancements. Use width and/or aspect-ratio breakpoints
(see the [global rule](../SKILL.md)). On small screens controls **stack**, the search pill goes
full-width, tabs become a horizontal-scroll row, the Filters popover may render full-width anchored to
the bar, and every touch target is **≥ 44px**.

```css
/* Small screens first: controls stack, pill is full-width, touch targets ≥ 44px.
   Either of these breakpoint styles is acceptable; pick what fits the layout. */
@media (max-width: 768px) {
    .sf-search, .sf-typeahead { width: 100%; max-width: none; }
    .sf-tabs { overflow-x: auto; }
    .sf-dropdown { flex: 1; }
    .sf-dropdown-toggle { min-width: 0; width: 100%; }
}

@media (max-aspect-ratio: 1/1) {
    /* The search pill goes full-width; tabs become a horizontal-scroll row;
       the Filters popover may render full-width anchored to the bar. Touch targets ≥ 44px. */
}
```

❌ Never use JS for responsive layout - CSS breakpoints handle it.

---

## 10. Full CSS reference (copy-paste ready)

```css
/* ===== Search pill (§3) ===== */
.sf-search {
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
    padding: var(--sf-field-pad);
    border: 1px solid var(--sf-border);
    border-radius: var(--sf-radius);
    background: var(--color-surface);
    width: 40%;            /* PRINCIPLED - ~40% of container so it doesn't dominate the toolbar */
    min-width: 240px;
    max-width: 420px;
    min-height: var(--sf-field-h);   /* forms-aligned control height */
    box-sizing: border-box;
    transition: border-color 120ms ease, box-shadow 120ms ease;
}
.sf-search:focus-within {
    border-color: var(--color-primary);
    box-shadow: 0 0 0 3px var(--sf-focus-ring);
}
.sf-search .sf-search-icon { color: var(--sf-icon); line-height: 0; flex-shrink: 0; }
.sf-search .sf-search-icon svg { width: 16px; height: 16px; display: block; }
.sf-search input {
    flex: 1;
    min-width: 0;
    border: none;
    outline: none;
    background: transparent;
    font-family: inherit;
    font-size: var(--text-body);
    color: var(--color-text);
}
.sf-search input::placeholder { color: color-mix(in srgb, var(--color-text-muted) 75%, transparent); }
/* hide native search clear - we render our own */
.sf-search input[type="search"]::-webkit-search-cancel-button { -webkit-appearance: none; appearance: none; }
/* the clear mark is icon-only chrome, not a button: `icon-btn icon-btn-sm` from buttons.md §4.1 / §10,
   with aria-label="Clear search". Only its fit inside the pill lives here. */
.sf-search .sf-clear { flex-shrink: 0; border-radius: 50%; }
.sf-search .sf-clear[hidden] { display: none; }
.sf-search .sf-spinner { flex-shrink: 0; }

.sf-count { font-size: var(--text-small); color: var(--color-text-muted); white-space: nowrap; }

/* ===== Tabs (§4) ===== */
.sf-tabs { display: flex; gap: var(--spacing-lg); }
.sf-tab {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 0;
    border: none;
    background: none;
    border-bottom: 2px solid transparent;
    border-radius: 0;
    font-family: inherit;
    font-size: var(--text-body);
    color: var(--color-text-muted);
    cursor: pointer;
}
.sf-tab:hover { color: var(--color-text); }
.sf-tab[aria-selected="true"] { color: var(--color-primary); font-weight: 600; border-bottom-color: var(--color-primary); }
.sf-tab:focus-visible { outline: 2px solid var(--sf-focus-ring); outline-offset: 2px; }
.sf-dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }
.sf-dot--all { background: var(--color-text-muted); }
.sf-dot--active { background: var(--color-success); }
.sf-dot--inactive { background: var(--color-danger); }

/* ===== Chips (§4) ===== */
.sf-chips { display: flex; flex-wrap: wrap; align-items: center; gap: var(--spacing-sm); }
.sf-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 3px 6px 3px 10px;
    border-radius: 999px;
    background: var(--sf-chip-bg);
    color: var(--color-primary);
    font-size: var(--text-small);
    font-weight: 500;
}
.sf-chip button {
    border: none;
    background: none;
    color: inherit;
    cursor: pointer;
    font-size: 14px;
    line-height: 1;
    padding: 0 2px;
    border-radius: 50%;
    opacity: 0.7;
}
.sf-chip button:hover { opacity: 1; }
.sf-chips .sf-clear-all { margin-left: 4px; }   /* the button look comes from buttons.md - see the Reset / Clear all block below */

/* ===== Dropdown fields (§4) - custom single-select listbox ===== */
.sf-filters-row { display: flex; flex-wrap: wrap; align-items: flex-end; gap: var(--spacing-md); }
.sf-dropdown { position: relative; display: flex; flex-direction: column; gap: 4px; }
.sf-dropdown > label { font-size: var(--text-small); font-weight: 600; color: var(--color-text); }
.sf-dropdown-row { display: flex; align-items: center; gap: 4px; }
.sf-dropdown-toggle {
    display: inline-flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--spacing-sm);
    min-width: 150px;
    min-height: var(--sf-field-h);
    padding: var(--sf-field-pad);
    border: 1px solid var(--sf-border);
    border-radius: var(--sf-radius);
    background: var(--color-surface);
    font-family: inherit;
    font-size: var(--text-body);
    color: var(--color-text);
    cursor: pointer;
    box-sizing: border-box;
}
.sf-dropdown-toggle:hover { border-color: var(--sf-border-strong); }
/* focus / open outline uses the FIELD BORDER color (not navy) */
.sf-dropdown-toggle:focus-visible,
.sf-dropdown-toggle[aria-expanded="true"] {
    outline: none;
    border-color: var(--sf-border-strong);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--sf-border-strong) 45%, transparent);
}
.sf-dropdown-toggle .sf-dropdown-label { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.sf-dropdown-toggle[data-empty="true"] .sf-dropdown-label { color: var(--color-text-muted); }
.sf-dropdown-toggle .sf-caret { flex-shrink: 0; width: 12px; height: 12px; color: var(--color-text-muted); transition: transform 120ms ease; }
.sf-dropdown-toggle[aria-expanded="true"] .sf-caret { transform: rotate(180deg); }

.sf-field-clear {
    flex-shrink: 0;
    border: none;
    background: none;
    color: var(--color-text-muted);
    cursor: pointer;
    font-size: 14px;
    line-height: 1;
    padding: 4px;
    border-radius: 50%;
}
.sf-field-clear:hover { color: var(--color-text); }
.sf-field-clear[hidden] { display: none; }

/* Reset filters + Clear all - the shared neutral secondary: render them as
   `btn btn-secondary btn-sm sf-reset` and `btn btn-secondary btn-sm sf-clear-all`, and let
   buttons.md §10 own the fill, border, ink, hover, focus, and size. Clearing a filter changes what
   is on screen and destroys nothing, so neither control is danger-colored and neither is borderless.
   Only layout and visibility live here - never a second neutral button style. */
.sf-reset { align-self: flex-end; }
/* .btn sets an explicit display, which overrides the UA [hidden] rule - re-assert it */
.sf-reset[hidden], .sf-chips .sf-clear-all[hidden] { display: none; }

/* ===== Filter button + popover (§5) ===== */
.sf-filter-wrap { position: relative; display: inline-block; }
/* The trigger is the shared neutral secondary: `btn btn-secondary sf-filter-btn`, with buttons.md
   §10 owning the fill, border, ink, hover, focus, and height. Only the icon swap and the count badge
   live here - `.is-filtered` reports filter state through the funnel glyph and the badge, never
   through a different fill, border, or emphasis. */
.sf-filter-btn svg { width: 16px; height: 16px; flex-shrink: 0; }
.sf-filter-btn .sf-icon-solid { display: none; }
.sf-filter-btn.is-filtered .sf-icon-outline { display: none; }
.sf-filter-btn.is-filtered .sf-icon-solid { display: inline; }
.sf-filter-btn .sf-badge {
    min-width: 18px;
    height: 18px;
    padding: 0 5px;
    border-radius: 999px;
    background: var(--color-primary);
    color: #fff;
    font-size: var(--text-xs);
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
/* the explicit display above overrides the UA [hidden] rule - re-assert it so the count clears */
.sf-filter-btn .sf-badge[hidden] { display: none; }
.sf-pop {
    position: absolute;
    top: calc(100% + 6px);
    right: 0;
    z-index: var(--sf-z-pop);
    width: 280px;
    padding: var(--spacing-md);
    background: var(--color-surface);
    border: 1px solid var(--sf-border);
    border-radius: var(--sf-radius);
    box-shadow: var(--sf-pop-shadow);
}
.sf-pop[hidden] { display: none; }
.sf-pop fieldset { border: none; margin: 0 0 var(--spacing-md); padding: 0; }
.sf-pop legend {
    padding: 0;
    margin-bottom: 6px;
    font-size: var(--text-small);
    font-weight: 600;
    color: var(--color-primary);
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.sf-opt { display: flex; align-items: center; gap: 8px; padding: 5px 0; font-size: var(--text-body); cursor: pointer; }
.sf-opt input { accent-color: var(--color-primary); width: 15px; height: 15px; }
.sf-pop-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--spacing-sm);
    padding-top: var(--spacing-sm);
    border-top: 1px solid var(--sf-border);
}

/* ===== Typeahead (§6) ===== */
.sf-typeahead { position: relative; max-width: 420px; }
.sf-listbox {
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    right: 0;
    z-index: var(--sf-z-pop);
    margin: 0;
    padding: 4px;
    list-style: none;
    background: var(--color-surface);
    border: 1px solid var(--sf-border);
    border-radius: var(--sf-radius);
    box-shadow: var(--sf-pop-shadow);
    max-height: 280px;
    overflow-y: auto;
}
.sf-listbox[hidden] { display: none; }
.sf-option {
    padding: 8px 10px;
    border-radius: var(--radius-sm);
    font-size: var(--text-body);
    color: var(--color-text);
    cursor: pointer;
}
.sf-option[aria-selected="true"] { background: var(--sf-option-active); }
.sf-option:hover, .sf-option.is-active { background: var(--sf-option-active); }
.sf-option mark { background: none; color: var(--color-primary); font-weight: 600; }
.sf-option .sf-option-sub { font-size: var(--text-small); color: var(--color-text-muted); }
.sf-option--empty { color: var(--color-text-muted); cursor: default; }

/* ===== Spinner (§7) ===== */
.sf-spinner {
    width: 15px;
    height: 15px;
    border: 2px solid var(--sf-border-strong);
    border-top-color: var(--color-primary);
    border-radius: 50%;
    animation: sf-spin 700ms linear infinite;
}
.sf-spinner[hidden] { display: none; }
@keyframes sf-spin { to { transform: rotate(360deg); } }
@media (prefers-reduced-motion: reduce) { .sf-spinner { animation-duration: 1.6s; } }

/* ===== Responsive (§9) ===== */
@media (max-aspect-ratio: 1/1) {
    .sf-search, .sf-typeahead { width: 100%; max-width: none; }
    .sf-tabs { overflow-x: auto; }
    .sf-dropdown { flex: 1; }
    .sf-dropdown-toggle { min-width: 0; width: 100%; }
}
```

---

## 11. Full JS reference (behavior contract)

Vanilla, framework-free, progressive-enhancement. Wire once per control; in React, model the same state
(query, applied facets, active option) with hooks.

> **Scope warning.** The filtering below runs over the rows already in the DOM, which is correct only for a
> table that holds its whole result set. Once the list is paginated or server-fed, the query, the facets,
> and the sort go to the server (debounced) and the narrowed page comes back - `useMemo`'d client filtering
> then narrows one page and hides the rest of the matches ([tables.md](tables.md) §4a). The control
> behavior, ARIA, and states below carry over unchanged either way.

```js
/* ===== Search pill (§3) ===== */
function initSearch(root, { onQuery, debounce = 0 } = {}) {
    const input = root.querySelector('input');
    const clear = root.querySelector('.sf-clear');
    let timer;
    const run = (v) => onQuery && onQuery(v.trim());
    const sync = () => {
        const v = input.value;
        clear.hidden = v.length === 0;
        if (debounce > 0) { clearTimeout(timer); timer = setTimeout(() => run(v), debounce); }
        else run(v);
    };
    input.addEventListener('input', sync);
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && input.value) { input.value = ''; sync(); e.stopPropagation(); }
    });
    clear.addEventListener('click', () => { input.value = ''; sync(); input.focus(); });
    sync();
}

/* ===== Tabs (§4) ===== */
function initTabs(tablist, onChange) {
    const tabs = [...tablist.querySelectorAll('.sf-tab')];
    tabs.forEach((tab) => tab.addEventListener('click', () => {
        tabs.forEach((t) => t.setAttribute('aria-selected', String(t === tab)));
        onChange && onChange(tab.dataset.value);
    }));
}

/* ===== Faceted popover (§5) ===== */
function initFilterPopover(wrap, { onApply } = {}) {
    const btn = wrap.querySelector('.sf-filter-btn');
    const pop = wrap.querySelector('.sf-pop');
    const badge = btn.querySelector('.sf-badge');
    const open = () => { pop.hidden = false; btn.setAttribute('aria-expanded', 'true'); document.addEventListener('click', outside); document.addEventListener('keydown', onEsc); };
    const close = () => { pop.hidden = true; btn.setAttribute('aria-expanded', 'false'); document.removeEventListener('click', outside); document.removeEventListener('keydown', onEsc); btn.focus(); };
    const outside = (e) => { if (!wrap.contains(e.target)) close(); };
    const onEsc = (e) => { if (e.key === 'Escape') close(); };
    const selected = () => [...pop.querySelectorAll('input:checked')].map((i) => ({ group: i.name, value: i.value, label: i.dataset.label }));
    const refreshBadge = (n) => { btn.classList.toggle('is-filtered', n > 0); if (n > 0) { badge.textContent = n; badge.hidden = false; } else badge.hidden = true; };
    btn.addEventListener('click', () => (pop.hidden ? open() : close()));
    pop.querySelector('.sf-apply').addEventListener('click', () => { const f = selected(); refreshBadge(f.length); onApply && onApply(f); close(); });
    pop.querySelector('.sf-clear-pop').addEventListener('click', () => { pop.querySelectorAll('input:checked').forEach((i) => (i.checked = false)); refreshBadge(0); onApply && onApply([]); });
    return { refreshBadge };
}

/* ===== Chips (§4) ===== */
function renderChips(host, facets, { onRemove, onClearAll } = {}) {
    host.innerHTML = '';
    if (!facets.length) return;                       // absent when empty (no empty bar)
    facets.forEach((f) => {
        const chip = document.createElement('span');
        chip.className = 'sf-chip';
        chip.innerHTML = `${f.label} <button type="button" aria-label="Remove ${f.label} filter">✕</button>`;
        chip.querySelector('button').addEventListener('click', () => onRemove && onRemove(f));
        host.appendChild(chip);
    });
    const clearAll = document.createElement('button');
    clearAll.type = 'button';
    clearAll.className = 'btn btn-secondary btn-sm sf-clear-all';
    clearAll.textContent = 'Clear all';
    clearAll.addEventListener('click', () => onClearAll && onClearAll());
    host.appendChild(clearAll);
}

/* ===== Custom single-select dropdown (§4) ===== */
function initDropdown(root, { onChange } = {}) {
    const toggle = root.querySelector('.sf-dropdown-toggle');
    const labelEl = toggle.querySelector('.sf-dropdown-label');
    const menu = root.querySelector('.sf-listbox');
    const clearBtn = root.querySelector('.sf-field-clear');
    const options = [...menu.querySelectorAll('.sf-option')];
    const placeholder = labelEl.textContent;
    let active = -1;

    const highlight = (i) => {
        options.forEach((o, k) => o.classList.toggle('is-active', k === i));
        if (options[i]) menu.setAttribute('aria-activedescendant', options[i].id || '');
        active = i;
    };
    const setValue = (value, text) => {
        root.dataset.value = value || '';
        labelEl.textContent = value ? text : placeholder;
        toggle.dataset.empty = value ? 'false' : 'true';
        clearBtn.hidden = !value;
        options.forEach((o) => o.setAttribute('aria-selected', String(!!value && o.dataset.value === value)));
        if (onChange) onChange(value || null);
    };
    const open = () => {
        menu.hidden = false; toggle.setAttribute('aria-expanded', 'true');
        highlight(Math.max(0, options.findIndex((o) => o.getAttribute('aria-selected') === 'true')));
        document.addEventListener('click', outside); document.addEventListener('keydown', onKey);
    };
    const close = () => {
        menu.hidden = true; toggle.setAttribute('aria-expanded', 'false');
        document.removeEventListener('click', outside); document.removeEventListener('keydown', onKey);
    };
    const outside = (e) => { if (!root.contains(e.target)) close(); };
    const choose = (opt) => { setValue(opt.dataset.value, opt.textContent.trim()); close(); toggle.focus(); };
    const onKey = (e) => {
        if (e.key === 'Escape') { close(); toggle.focus(); }
        else if (e.key === 'ArrowDown') { e.preventDefault(); highlight((active + 1) % options.length); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); highlight((active - 1 + options.length) % options.length); }
        else if (e.key === 'Enter' && options[active]) { e.preventDefault(); choose(options[active]); }
    };

    toggle.addEventListener('click', (e) => { if (e.target.closest('.sf-field-clear')) return; menu.hidden ? open() : close(); });
    options.forEach((opt) => opt.addEventListener('click', () => choose(opt)));
    clearBtn.addEventListener('click', (e) => { e.stopPropagation(); setValue('', ''); toggle.focus(); });
    return { clear: () => setValue('', '') };
}

/* ===== Dropdown filter row (§4) ===== */
function initFilterFields(root, { onChange } = {}) {
    const dropdowns = [...root.querySelectorAll('.sf-dropdown')];
    const reset = root.querySelector('.sf-reset');
    const read = () => {
        const f = {};
        dropdowns.forEach((d) => { if (d.dataset.value) f[d.dataset.facet] = d.dataset.value; });
        return f;
    };
    const sync = () => {
        if (reset) reset.hidden = !dropdowns.some((d) => d.dataset.value);
        if (onChange) onChange(read());
    };
    const controllers = dropdowns.map((d) => initDropdown(d, { onChange: sync }));
    if (reset) reset.addEventListener('click', () => { controllers.forEach((c) => c.clear()); sync(); });
    sync();
}

/* ===== Typeahead (§6) ===== */
function initTypeahead(root, { source, onSelect, render } = {}) {
    const input = root.querySelector('input');
    const list = root.querySelector('.sf-listbox');
    let active = -1, options = [];
    const escape = (s) => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const highlight = (text, q) => q ? text.replace(new RegExp(`(${escape(q)})`, 'ig'), '<mark>$1</mark>') : text;
    const close = () => { list.hidden = true; input.setAttribute('aria-expanded', 'false'); active = -1; };
    const setActive = (i) => {
        options.forEach((o, k) => o.setAttribute('aria-selected', String(k === i)));
        if (options[i]) input.setAttribute('aria-activedescendant', options[i].id);
        active = i;
    };
    const open = (q) => {
        const matches = source.filter((it) => it.label.toLowerCase().includes(q.toLowerCase())).slice(0, 8);
        list.innerHTML = '';
        if (!q) { close(); return; }
        if (!matches.length) {
            list.innerHTML = `<li class="sf-option sf-option--empty" role="option" aria-disabled="true">No matches</li>`;
            options = []; list.hidden = false; input.setAttribute('aria-expanded', 'true'); return;
        }
        matches.forEach((it, k) => {
            const li = document.createElement('li');
            li.className = 'sf-option'; li.id = `sf-opt-${k}`; li.role = 'option';
            li.innerHTML = render ? render(it, q, highlight) : highlight(it.label, q);
            li.addEventListener('mousedown', (e) => { e.preventDefault(); choose(it); });
            li.addEventListener('mouseenter', () => setActive(k));
            list.appendChild(li);
        });
        options = [...list.querySelectorAll('.sf-option')];
        list.hidden = false; input.setAttribute('aria-expanded', 'true'); setActive(0);
    };
    const choose = (it) => { input.value = it.label; close(); onSelect && onSelect(it); };
    input.addEventListener('input', () => open(input.value));
    input.addEventListener('keydown', (e) => {
        if (list.hidden) return;
        if (e.key === 'ArrowDown') { e.preventDefault(); setActive((active + 1) % options.length); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); setActive((active - 1 + options.length) % options.length); }
        else if (e.key === 'Enter' && options[active]) { e.preventDefault(); options[active].dispatchEvent(new MouseEvent('mousedown')); }
        else if (e.key === 'Escape') { close(); }
    });
    input.addEventListener('blur', () => setTimeout(close, 120));
}
```

---

## 12. Accessibility checklist

- [ ] Search input has a label (visible or `aria-label`) naming what it searches.
- [ ] Clear (✕) is a real `<button>` - `icon-btn` chrome ([buttons.md](buttons.md) §4.1) - with `aria-label="Clear search"`, present only when non-empty.
- [ ] Tabs use `role="tablist"`/`role="tab"` + `aria-selected`; the dot is `aria-hidden`.
- [ ] Each chip's remove ✕ is a `<button>` with a descriptive `aria-label`.
- [ ] Dropdown field: trigger is a `<button aria-haspopup="listbox">` + `aria-expanded`; menu is `role="listbox"`, options `role="option"` with `aria-selected`; `↑/↓`/`Enter`/`Esc` + outside-click work and focus returns to the trigger.
- [ ] Filter button: `aria-haspopup="dialog"`, `aria-expanded`; popover is `role="dialog"`, groups are `<fieldset>`/`<legend>`; `Esc`/outside-click closes and returns focus to the trigger.
- [ ] Typeahead: input `role="combobox"` + `aria-expanded`/`aria-controls`/`aria-activedescendant`; list `role="listbox"`; options `role="option"` with `aria-selected`; full keyboard nav.
- [ ] Focus ring visible on every interactive element (never `outline: none` without a replacement).
- [ ] No-results state is announced (lives in a region the user lands on, not a silent empty list).

---

## 13. Do's and Don'ts

**Do**
- ✅ Name the searchable fields in the placeholder.
- ✅ Echo applied facets as removable chips so the active set is always visible.
- ✅ Use tabs for a few exclusive states, a popover for many grouped facets.
- ✅ Filter instantly only where the table holds its whole result set; send a paginated or server-fed list's query to the server, debounced, and render the narrowed page.
- ✅ Always render a no-results state with a **Clear filters** escape.

**Don't**
- ❌ Ship a list/index table with no bar at all, or filter only the rows already loaded.
- ❌ Keep the applied query, facets, and sort in client state only, so a refresh loses the view.
- ❌ Show a permanently-visible empty ✕ in the search box.
- ❌ Give `Reset filters`, `Clear all`, or `Apply` a solid, borderless, or danger-colored look - every button in the bar is the one neutral secondary.
- ❌ Signal a filter state by color alone (pair with a dot/label/check).
- ❌ Put 6+ facets in a tab row - move them to the popover.
- ❌ Render an empty/disabled facet group whose data isn't wired (omit it).
- ❌ Build a typeahead as plain divs without the combobox/listbox ARIA.
- ❌ Hardcode a hex or invent a shade outside the palette.

---

## 14. Rules for the AI assistant

1. **Every list/index table gets this bar** ([tables.md](tables.md) §4a). Filter and sort the whole result set (server-side when the list is paginated), return to page 1 on any change, report the filtered total in the counts, and keep the query, facets, sort, and page in the URL so a refresh or a shared link reproduces the view. Never narrow only the rows already in the DOM when more matches sit behind pagination.
2. **Default the list header to search + tabs.** For a few independent facets, use **dropdown fields** (§4); escalate to the `Filters (n)` popover only when facets outgrow a tab/dropdown row; add chips only when facets are independent and must stay visible.
3. **Reuse `.sf-search`** for every free-text box - don't invent a new search field. Use the bundled icon registry's **search (magnifier) icon**, not an emoji, and keep the pill at the PRINCIPLED **~40% width** unless the screen is search-first.
4. **Typeahead only for entity-picking**, never to filter a table in place - and never without the combobox/listbox ARIA from §6.
5. **Instant client filter only where the whole result set is loaded**, otherwise a debounced (~250ms) server filter. Show the in-field spinner only for server round-trips.
6. **Respect render-only-functional-UI:** omit any facet/group/tab whose behaviour isn't wired - don't render it disabled.
7. **Take every button from [buttons.md](buttons.md) §4:** `Filters`, `Reset filters`, `Clear all`, and `Apply` are `btn btn-secondary` (`btn-sm` in a chip or dropdown row), the search ✕ is `icon-btn` chrome, and no filter control is ever solid, borderless, or danger-colored. Never define a second neutral button style in this file.
8. **Tokens only**, mobile-first responsive. Run the §12 checklist before finalizing.

---

## 15. Quick decision guide

```
Need free text across fields?            → Search pill (§3)
2-5 exclusive states?                    → Tabs + dots (§4)
Independent facets, keep them visible?   → Chips (§4)  [+ popover to set them]
6+ grouped criteria / rarely changed?    → Filters popover (§5)
Pick ONE known entity from many?         → Typeahead (§6)
Query returns nothing?                   → No-results + Clear filters (§7)
```

When unsure, prefer **search + tabs** (the lightest header that works) and escalate to the popover only
when the facet count forces it.
