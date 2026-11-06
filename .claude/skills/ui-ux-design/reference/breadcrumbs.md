# Breadcrumbs UI/UX Design Guide

Reference for AI-assisted development: build a breadcrumb trail so the "where am I / how do I get back"
affordance stays consistent, honest, and accessible.

> **TL;DR for the AI**
> 1. **Breadcrumbs show the path, not the menu.** One horizontal trail above the page title (§5),
>    carrying the **full path** from the top-level section down to the current page, **generated from
>    the navigation config** rather than written per page. Render it once the page sits inside a group
>    (three segments or more); a shallow page gets none. The trail is also the way back, so a page
>    never carries a separate back link on a row of its own (§5).
> 2. **Smart links, not blind links (ENFORCED).** A crumb that resolves to a **real route** is a link
>    (`bc-link`); a **section-grouping label** with no destination (e.g. "System Administration") is plain text
>    (`bc-text`); the **current page** is emphasized, non-link, `aria-current="page"` (§3). Never render
>    a dead link.
> 3. **Mirror one source component.** Muted ancestors -> bold current, a **chevron** divider, compact
>    height. In a framework, render your framework's breadcrumb primitive; this file is the
>    framework-free contract (§8).
> 4. **Four variants** (§4): base trail, optional **leading Home icon**, **overflow** (collapse the
>    middle to `...` when the trail is long), and **mobile collapse** (last two + back on narrow viewports).
> 5. **It's `<nav>` + `<ol>` (ENFORCED, §9).** Ordered list, `aria-label="Breadcrumb"`,
>    `aria-current="page"` on the leaf, `aria-hidden` separators, keyboard-focusable links.
> 6. Use the **design tokens**; never hardcode a hex. Responsive is mobile-first (width and/or
>    aspect-ratio breakpoints).

---

## Contents

1. [Design tokens (source of truth)](#1-design-tokens-source-of-truth)
2. [Anatomy & structure](#2-anatomy--structure)
3. [Smart links - link vs text vs current (ENFORCED)](#3-smart-links--link-vs-text-vs-current-enforced)
4. [Variants](#4-variants)
5. [Placement](#5-placement)
6. [States & interaction](#6-states--interaction)
7. [Full CSS reference (copy-paste ready)](#7-full-css-reference-copy-paste-ready)
8. [Full HTML reference](#8-full-html-reference)
9. [Accessibility (ENFORCED)](#9-accessibility-enforced)
10. [Do's and Don'ts](#10-dos-and-donts)
11. [Rules for the AI assistant](#11-rules-for-the-ai-assistant)
12. [Quick decision guide](#12-quick-decision-guide)

---

## 1. Design tokens (source of truth)

Breadcrumbs consume the design-system palette plus a few component tokens derived from it. The visual
model is a single source-of-truth breadcrumb component (muted ancestors -> bold current, chevron
divider, compact height).

```css
:root {
    /* Design-system palette (approved - canonical: design-tokens.md) */
    --color-primary: #193E6B;                      /* Midnight Blue (dark: #7FADE1) - link hover, focus ring */
    --color-text: #1E2E42;                         /* ink (dark: #E9EFF6) */
    --color-text-muted: #4D5E75;                   /* dark: #A3B2C5 */

    /* Breadcrumb tokens - all derived, no invented shades */
    --bc-font: var(--text-small);                                       /* 12px - compact, non-intrusive */
    --bc-gap: 6px;                                                      /* label ↔ separator spacing */
    --bc-pad: 2px 0;                                                    /* compact row */
    --bc-color-ancestor: var(--color-text-muted);                      /* non-current crumbs */
    --bc-color-current: var(--color-text);                             /* current page (semibold) */
    --bc-link-hover: var(--color-primary);                             /* ancestor link hover */
    --bc-link-hover-bg: color-mix(in srgb, var(--color-primary) 6%, transparent);
    --bc-sep: color-mix(in srgb, var(--color-text) 35%, transparent);  /* chevron - lighter than text */
    --bc-radius: var(--radius-sm, 4px);                                /* link hover/focus pill */
}
```

**Light rules (ENFORCED):** the trail is **quiet chrome**, not a headline - `--text-small`, a single
muted row. Ancestors are muted; only the current page is darker and semibold. The separator is a
**chevron**, lighter than the text. No fills, pills, or brand color except on link hover/focus.

---

## 2. Anatomy & structure

One horizontal row: crumbs separated by chevrons, ending on the current page.

```
┌ nav.breadcrumb (aria-label="Breadcrumb") ──────────────────────────────┐
│  ⌂ Home   ›   System Administration   ›   Modules                       │
│  └ bc-link  └ bc-sep  └ bc-text      └ bc-current (aria-current="page") │
│  (real route)        (no destination)   (this page, bold, non-link)     │
└────────────────────────────────────────────────────────────────────────┘
   ↑ optional bc-home-icon
```

- The container is a `<nav aria-label="Breadcrumb">` wrapping an **ordered list** `<ol class="bc-list">`.
- Each crumb is a `<li class="bc-item">`. For every item after the first, a leading
  `<svg class="bc-sep" aria-hidden="true">` chevron precedes the label - the divider travels with the
  crumb it precedes.
- The label is one of three renderings - see §3.

---

## 3. Smart links - link vs text vs current (ENFORCED)

The trail mixes three kinds of segment. **Pick the rendering by what the segment actually is** - never
make everything a link, never make everything plain text.

| Segment | When | Render | Interactive? |
|---|---|---|---|
| **Navigable ancestor** | Resolves to a **real route** (e.g. `Home` -> `/home`) | `<a class="bc-link">` | Yes - navigates |
| **Section label** | A grouping with **no page** (e.g. "System Administration", "Security", "Governance") | `<span class="bc-text">` | No - plain muted text |
| **Current page** | The leaf - the page you're on | `<span class="bc-current" aria-current="page">` | No - emphasized |

**Why:** a trail (`Home > System Administration > Modules`) mixes real routes with section groupings.
"System Administration" is a heading, not a destination - linking it would be a dead link. **Honesty over
uniformity:** link what you can navigate to, state the rest plainly.

> **Render only functional UI:** if an ancestor's route exists but the user lacks permission for it,
> render it as `bc-text`, not a link they can't follow. Never a disabled-looking dead link.

---

## 4. Variants

### 4.1 Base trail
`Home › Section › Current` per §2-§3. The default; covers nearly every page.

### 4.2 Leading Home icon (optional)
The first crumb may carry a **home glyph** from the skill's bundled icon registry (16px) inside the
link, before or instead of the word "Home" ([assets.md](assets.md)). Use it consistently across the app
or not at all. `aria-hidden` on the glyph; the link's accessible name stays "Home".

### 4.3 Overflow - long trails collapse to `...`
When a trail is long (**> 4 crumbs**), keep the **first** (Home) and the **last two**, and collapse the
middle into a single overflow control:

```
Home › ... › Roles › Edit
        └ bc-overflow (button) - reveals the hidden crumbs
```

- `<button class="bc-overflow" aria-haspopup="menu" aria-expanded="false" aria-label="Show hidden breadcrumbs">...</button>`
- Activating it reveals the collapsed crumbs (a small menu, or expanded inline). Keyboard-operable.
- Never truncate the **current page** or **Home** - only the middle.

### 4.4 Mobile collapse
Mobile-first: on a narrow / portrait viewport collapse to the **last two crumbs** preceded by a `‹`
back affordance to the parent:

```
‹ Roles › Edit
```

Use a width and/or aspect-ratio breakpoint (e.g. `@media (max-width: 600px)` or
`@media (max-aspect-ratio: 1/1)`); a pure-CSS media query is preferred over JS resize logic.

---

## 5. Placement

- Breadcrumbs sit **in the content area, above the page title**, below the [shell](topbar-sidenav.md)
  (top bar + side nav). They are **page chrome**, not part of the top bar.
- They are **kept live during loading** - when the data region skeletons
  ([empty-and-loading-state.md](empty-and-loading-state.md) §3), the breadcrumb and page header stay
  rendered so the user keeps orientation.
- **One trail per page.** Don't nest breadcrumbs inside a card or repeat them per section.
- **Full path, generated from the navigation config.** Every segment from the top-level section down to
  the current page, in the tree's own order. A hand-written trail drifts from the menu the moment a
  feature moves, and then the two disagree about where the user is.
- **The trail is the way back, so there is no separate back link.** A record page or a record form is
  not itself a navigation entry, so it declares the list it belongs to; that list joins the trail as an
  ordinary link, one click from the page. Never render a back link on one row with a breadcrumb on the
  next: that is two controls, two rows, one meaning.
- **Show the trail once the page sits inside a group.** A page whose whole path is its section and
  itself renders **no** breadcrumb, because the sidebar already says that much. Three segments or more
  earns the line.

---

## 6. States & interaction

- **Ancestor link** (`bc-link`): muted by default; on **hover/focus** shifts to `--bc-link-hover` (navy)
  with an underline and a subtle `--bc-link-hover-bg` pill. Visible `:focus-visible` ring
  (`2px solid var(--color-primary)`, offset 2px), ≥ touch target on touch.
- **Section label** (`bc-text`): muted, no hover, not focusable.
- **Current page** (`bc-current`): `--bc-color-current`, semibold, not interactive.
- **Separators / overflow glyph**: decorative chevrons are `aria-hidden`; the overflow control is a real
  labelled `<button>`.

---

## 7. Full CSS reference (copy-paste ready)

`bc-`-namespaced, design tokens, `color-mix()` (current evergreen browsers). Responsive is
**mobile-first** - collapse the trail on narrow viewports using width and/or aspect-ratio breakpoints.

```css
/* ===== Container ===== */
.breadcrumb { font-family: var(--font-body); }
.bc-list {
    display: flex; align-items: center; flex-wrap: wrap; gap: var(--bc-gap);
    list-style: none; margin: 0; padding: var(--bc-pad);
}
.bc-item { display: inline-flex; align-items: center; gap: var(--bc-gap); font-size: var(--bc-font); }

/* ===== Separator (chevron) - decorative ===== */
.bc-sep { width: 14px; height: 14px; flex-shrink: 0; color: var(--bc-sep); }

/* ===== Navigable ancestor (link) ===== */
.bc-link {
    display: inline-flex; align-items: center; gap: 4px;
    color: var(--bc-color-ancestor); text-decoration: none;
    padding: 2px 5px; border-radius: var(--bc-radius); cursor: pointer;
}
.bc-link:hover { color: var(--bc-link-hover); text-decoration: underline; background: var(--bc-link-hover-bg); }
.bc-link:focus-visible { outline: 2px solid var(--color-primary); outline-offset: 2px; }
.bc-home-icon { width: 15px; height: 15px; flex-shrink: 0; }

/* ===== Section label (no destination) ===== */
.bc-text { color: var(--bc-color-ancestor); padding: 2px 5px; }

/* ===== Current page (leaf) ===== */
.bc-current { color: var(--bc-color-current); font-weight: 600; padding: 2px 5px; }

/* ===== Overflow (collapsed middle) ===== */
.bc-overflow {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 24px; height: 22px; padding: 0 6px;
    border: none; background: transparent;
    color: var(--bc-color-ancestor); font: inherit; font-size: var(--bc-font);
    border-radius: var(--bc-radius); cursor: pointer;
}
.bc-overflow:hover { color: var(--bc-link-hover); background: var(--bc-link-hover-bg); }
.bc-overflow:focus-visible { outline: 2px solid var(--color-primary); outline-offset: 2px; }

/* Back affordance shown only on mobile collapse */
.bc-back { display: none; }

/* ===== Mobile collapse (mobile-first; width and/or aspect-ratio breakpoint) ===== */
@media (max-aspect-ratio: 1/1) {
    /* Keep only the last two crumbs; show a back affordance to the parent */
    .bc-item[data-collapse="hide"] { display: none; }
    .bc-back { display: inline-flex; align-items: center; gap: 4px; }
}
```

---

## 8. Full HTML reference

Static markup is the contract. In a framework, map this onto your framework's breadcrumb primitive (a
container, items, a divider); the smart-link rule (§3) maps a crumb with a route to a link/button-with-href
item and a section label to a plain text item. The chevron is the divider element.

### 8.1 Base trail (smart links)
```html
<nav class="breadcrumb" aria-label="Breadcrumb">
  <ol class="bc-list">
    <li class="bc-item">
      <a class="bc-link" href="/home">Home</a>
    </li>
    <li class="bc-item">
      <svg class="bc-sep" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
      <span class="bc-text">System Administration</span>   <!-- section label: no route -->
    </li>
    <li class="bc-item">
      <svg class="bc-sep" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
      <span class="bc-current" aria-current="page">Modules</span>
    </li>
  </ol>
</nav>
```

### 8.2 Leading Home icon (§4.2)
```html
<a class="bc-link" href="/home">
  <!-- icon library: a home glyph, 16px, aria-hidden -->
  <svg class="bc-home-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 11l8-7 8 7"/><path d="M6 10v9h12v-9"/></svg>
  Home
</a>
```

### 8.3 Overflow - long trail (§4.3)
```html
<nav class="breadcrumb" aria-label="Breadcrumb">
  <ol class="bc-list">
    <li class="bc-item"><a class="bc-link" href="/home">Home</a></li>
    <li class="bc-item">
      <svg class="bc-sep" ...aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
      <button class="bc-overflow" aria-haspopup="menu" aria-expanded="false" aria-label="Show hidden breadcrumbs">...</button>
    </li>
    <li class="bc-item">
      <svg class="bc-sep" ...aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
      <span class="bc-text">Roles</span>
    </li>
    <li class="bc-item">
      <svg class="bc-sep" ...aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
      <span class="bc-current" aria-current="page">Edit</span>
    </li>
  </ol>
</nav>
```

### 8.4 Mobile-collapse markup (§4.4)
Same trail; CSS hides the middle on `max-aspect-ratio: 1/1` and reveals the back affordance. Mark
collapsible items with `data-collapse="hide"`; the back link points at the parent route.
```html
<nav class="breadcrumb" aria-label="Breadcrumb">
  <ol class="bc-list">
    <li class="bc-item bc-back-item">
      <a class="bc-back bc-link" href="/roles" aria-label="Back to Roles">
        <svg class="bc-sep" ...aria-hidden="true"><path d="M15 6l-6 6 6 6"/></svg> Roles
      </a>
    </li>
    <li class="bc-item" data-collapse="hide"><a class="bc-link" href="/home">Home</a></li>
    <li class="bc-item" data-collapse="hide">
      <svg class="bc-sep" ...aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
      <span class="bc-text">System Administration</span>
    </li>
    <li class="bc-item">
      <svg class="bc-sep" ...aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
      <span class="bc-current" aria-current="page">Edit</span>
    </li>
  </ol>
</nav>
```

---

## 9. Accessibility (ENFORCED)

- **Landmark + list.** The trail is a `<nav aria-label="Breadcrumb">` wrapping an `<ol>`; each crumb is
  an `<li>`. The ordered list conveys the hierarchy to assistive tech.
- **Mark the leaf.** The current page carries `aria-current="page"` and is **not** a link.
- **Separators are decorative.** Chevrons are `aria-hidden="true"` (or CSS); never the only signal of
  hierarchy - the list structure carries it.
- **Real controls.** Links are real `<a href>` (keyboard-focusable, visible `:focus-visible` ring); the
  overflow trigger is a labelled `<button>`. No click-only `<div>`s.
- **Honest affordances.** A non-navigable section label is plain text, not a link (§3).
- **No focus theft / no layout shift.** Rendering the trail doesn't move focus; it occupies a fixed
  compact row above the title so the page doesn't reflow.
- **Contrast.** Muted ancestor text still meets contrast on `--color-surface`; don't drop below
  `--color-text-muted`.

---

## 10. Do's and Don'ts

| ✅ Do | ❌ Don't | Why |
|---|---|---|
| Link only **navigable** crumbs; state section labels as text | Make every crumb a link | Section labels have no route - dead links (§3) |
| Mark the leaf `aria-current="page"`, non-link | Render the current page as a link to itself | A breadcrumb leaf isn't a destination (§9) |
| Use a **chevron** divider, muted | Invent a slash/pipe or a brand-colored separator | One quiet, consistent divider (§1) |
| Collapse long trails to `...`, keeping Home + last two | Wrap a 7-deep trail onto two lines | Overflow keeps the row compact (§4.3) |
| Show breadcrumbs only for destinations deeper than ~3 levels | Render `Home` alone as a one-item breadcrumb | A shallow trail says nothing (§5) |
| Generate the trail from the navigation config, full path | Hand-write a shortened trail on the page | A hand-written trail drifts from the menu (§5) |
| Let the trail carry the way back | Add a back link above the breadcrumb | Two rows saying the same thing (§5) |
| Keep it **above the page title**, one per page | Put it in the top bar or repeat per section | It's page chrome, not global nav (§5) |
| Collapse on a narrow viewport, last two + back | Reach for a JS resize handler when CSS suffices | Mobile-first; a pure-CSS media query handles it (§4.4) |
| `<nav>` + `<ol>` + `<li>`, real `<a>`/`<button>` | Build it from click-only `<div>`s | Semantics + keyboard (§9) |

---

## 11. Rules for the AI assistant

When generating a breadcrumb, the assistant **must**:

1. **Render only once the page sits inside a group** - three segments or more (§5); otherwise no
   breadcrumb. Place it **above the page title**, one per page, and keep it live during loading
   ([empty-and-loading-state.md](empty-and-loading-state.md) §3).
1a. **Generate the full path from the navigation config**, and where the page is a record or a form
   that is not itself a navigation entry, include the list it belongs to as a segment, so the trail is
   the way back and no separate back link is needed (§5).
2. **Apply the smart-link rule (ENFORCED, §3):** navigable ancestor -> `bc-link`; section label with no
   route -> `bc-text`; current page -> `bc-current` + `aria-current="page"`, non-link. **Never a dead
   link** (including permission-blocked routes - render as text).
3. **Mirror one source-of-truth visual** (§1, §6): muted ancestors -> bold current, **chevron** divider,
   compact `--text-small` row. In a framework, render your framework's breadcrumb primitive.
4. **Cover the variants the page needs** (§4): base; optional Home icon (consistently or not at all);
   **overflow** to `...` when > 4 crumbs (keep Home + last two); **mobile collapse** to the last two + `‹`
   back on a narrow / portrait viewport.
5. **Wire accessibility** (§9): `<nav aria-label="Breadcrumb">` + `<ol>`/`<li>`, `aria-current="page"` on
   the leaf, `aria-hidden` separators, real focusable `<a>`/`<button>`, no focus theft, no layout shift.
6. **Use the design tokens** (§1) - never hardcode a hex; responsive is mobile-first using width and/or
   aspect-ratio breakpoints; keep the trail quiet (`--text-small`, muted, chevron).

---

## 12. Quick decision guide

```
Is this destination deeper than ~3 levels?
│
├─ No ───────────────────▶ No breadcrumb (a shallow trail says nothing). §5
│
└─ Yes ──────────────────▶ BREADCRUMB: <nav aria-label> + <ol>, above the page title
    For each crumb, by what it IS (§3):
    ├─ Real route ............ <a class="bc-link">            (Home, navigable ancestors)
    ├─ Section label ......... <span class="bc-text">         (System Administration / Security / Governance)
    └─ Current page .......... <span class="bc-current" aria-current="page">  (bold, non-link)

    Trail too long (> 4)? ──▶ collapse middle to <button class="bc-overflow">...</button>
                              keep Home + last two; never truncate Home or the leaf. §4.3
    Portrait / narrow? ─────▶ width/aspect-ratio media query: show last two + ‹ back. §4.4

Always: chevron divider · muted ancestors → bold current · --text-small · design tokens ·
        aria-current on the leaf · aria-hidden separators · no dead links.
```

---
