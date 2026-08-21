# In-Canvas Tabs UI/UX Design Guide

Reference for AI-assisted development: build the in-canvas tab strip so deeper navigation stays
consistent, honest, and accessible.

This is the **navigation** tab strip - the sanctioned in-canvas switch the sidebar hands off to when
navigation goes deeper than three accordion levels, plus the switch between a record's or page's
facets. It is **not** the list **filter** tabs (All / Active / Inactive) in
[search-filter.md](search-filter.md) §4 - those narrow a list's rows and read as a *filter*, not
navigation. Keep the two separate.

> **TL;DR for the AI**
> 1. **Two roles only.** A tab strip is allowed for (a) **navigation depth-overflow** - a **leaf**
>    under a level-3 accordion group that itself needs children, so its children render as tabs instead
>    of forcing a 4th accordion level (a level-3 *group* whose children are plain leaves stays an
>    accordion and is not this case) - and (b) **a single record's / page's facets**. Nothing else.
> 2. **Route-backed by default.** Each tab is its **own deep-linkable URL**, reflected in the breadcrumb
>    and back/forward. Render it as a **`<nav>` of links** with `aria-current="page"` on the active tab,
>    *not* the JS `role="tablist"` widget. Reserve the widget (§4) for genuine in-page panels that are
>    **not** separate routes.
> 3. **Terminal.** A tab panel holds content (an archetype), **never another tab strip and never a new
>    sidebar accordion**. If a panel seems to need sub-tabs, restructure the tree.
> 4. **Top of the canvas, under the breadcrumb/title.** Curved browser-tab look: rounded 10px top
>    corners, an always-visible resting tint; the **active tab fills with the card surface** (hairline
>    border, no bottom border) and visibly **breaks the strip's rule**, with concave bottom fillets -
>    fill + border + weight, **never color alone**. Strip **scrolls horizontally** on small screens.
> 5. **Render only functional UI.** An unbuilt facet renders **disabled + "Soon"**; an unauthorized
>    facet is **absent** (`render null`).
> 6. Use the **design tokens**; never hardcode a hex. Every token resolves in light **and** dark.

---

## Contents

1. [When to use a tab strip (and when not)](#1-when-to-use-a-tab-strip-and-when-not)
2. [Design tokens (source of truth)](#2-design-tokens-source-of-truth)
3. [Anatomy & placement](#3-anatomy--placement)
4. [Route-backed links vs the in-page widget](#4-route-backed-links-vs-the-in-page-widget)
5. [States - active, hover, Soon, overflow, panel states](#5-states--active-hover-soon-overflow-panel-states)
6. [Responsive (ENFORCED, mobile-first)](#6-responsive-enforced-mobile-first)
7. [Full CSS reference (copy-paste ready)](#7-full-css-reference-copy-paste-ready)
8. [Full HTML & JS reference](#8-full-html--js-reference)
9. [Accessibility checklist](#9-accessibility-checklist)
10. [Do's and Don'ts](#10-dos-and-donts)
11. [Rules for the AI assistant](#11-rules-for-the-ai-assistant)
12. [Quick decision guide](#12-quick-decision-guide)

---

## 1. When to use a tab strip (and when not)

The sidebar carries standing navigation as accordion groups nested **at most three levels** within a
cluster ([topbar-sidenav.md](topbar-sidenav.md)). The tab strip is what the canvas uses when that
budget is reached, plus the standard facet switcher.

**Use a tab strip for exactly these two cases:**

- **Depth-overflow navigation.** Only accordion *groups* count toward the 3-level cap - a cluster
  heading is not a level, a leaf is not a level. When a **leaf** under the level-3 group itself needs
  children, it does **not** get a 4th accordion level: it becomes a **routable page** whose children
  render as tabs. (A level-3 group whose children are plain leaves stays an accordion - not this case.)
- **Record / page facets.** The facets of one record or page (e.g. `Overview | Activity | Permissions`)
  switch via the tab strip.

**How the 3-level count works** - roles and levels only (build the real tree from the App Definition;
most apps never reach this depth, so do not adopt a shape by default):

```text
Cluster  (fixed heading - not a level, just a title)
└─ Group ............................. level 1   (accordion)
   └─ Group .......................... level 2   (accordion)
      └─ Group ....................... level 3   (accordion - the limit)
         ├─ Leaf                                 (link)
         ├─ Leaf  →  tab-strip page              (link that needs its own sub-areas)
         └─ Leaf                                 (link)
```

Concrete illustration of the same structure (names are an **example only**):

```text
System Administration     ← cluster heading - NOT a level (a fixed title)
  Platform                ← group · level 1
    Security              ← group · level 2
      Sign-in Methods     ← group · level 3   (the limit - deepest accordion)
        Password          ← leaf   (a leaf is NOT a level)
        SSO / SAML  ›     ← leaf that needs its own sub-areas → routable page with a tab strip
        Passkeys          ← leaf
    Data Retention        ← leaf
```

**Do not use a tab strip for:**

- A **list filter** (All / Active / Inactive, by status/type/owner) - use [search-filter.md](search-filter.md) §4.
- **Standing navigation.** It lives in the sidebar and **never** moves to the top bar. The tab strip is
  in-canvas only; it never replaces the sidebar.
- **A fourth level of depth below a tab.** Tabs are terminal (§4): a panel never contains another tab
  strip and never re-opens a sidebar accordion.

---

## 2. Design tokens (source of truth)

The tab strip consumes the approved brand palette plus a small `pt-` ("page tabs") token set (values
from `design-tokens.md`). The approved visual model is the **curved browser-tab style**: every tab is
a rounded-top shape with a resting tint, and the active tab is a filled tab attached to the content
below. The active label uses the **primary interactive accent** (`--color-primary` - Midnight Blue
light / `#7FADE1` dark), the same accent links and primary actions use. Tabs never redefine
`--color-accent` - that token is Green Gold system-wide (the sidebar's active-nav treatment) and plays
no part in the tab strip.

```css
:root {
    /* Brand palette + surfaces (approved) */
    --color-primary:     #193E6B;             /* Midnight Blue - active tab label, focus ring */
    --color-text:        #1E2E42;             /* ink */
    --color-text-muted:  #4D5E75;             /* resting tab label */
    --color-surface:     #FFFFFF;             /* active tab fill = card surface */
    --card-border:       #E8E2D8;             /* hairline border of the active tab */
    --badge-neutral-bg:  #F0EBE1;             /* "Soon" pill - neutral badge pair */
    --badge-neutral-fg:  #514A3B;

    /* Page-tab tokens - approved values, see design-tokens.md */
    --pt-track:      #E8E2D8;                 /* strip bottom rule, an INSET hairline the active tab covers */
    --pt-rest-bg:    rgba(25, 62, 107, 0.06); /* resting tab tint, always visible */
    --pt-hover-bg:   rgba(25, 62, 107, 0.13); /* inactive hover, one step darker */
    --pt-active-fg:  var(--color-primary);    /* active label */
    --pt-radius:     10px 10px 0 0;           /* curved browser-tab top corners */
    --pt-fillet:     10px;                    /* concave bottom fillets flaring the active tab into the rule */
    --pt-gap:        3px;                     /* space between tabs */
    --pt-pad:        8px 14px;                /* tab padding */
}

[data-theme="dark"] {
    --color-primary:     #7FADE1;             /* interactive accent on dark */
    --color-text:        #E9EFF6;
    --color-text-muted:  #A3B2C5;
    --color-surface:     #253E5D;
    --card-border:       rgba(255, 255, 255, 0.10);
    --badge-neutral-bg:  rgba(255, 255, 255, 0.10);
    --badge-neutral-fg:  rgba(255, 255, 255, 0.80);
    --pt-track:          rgba(255, 255, 255, 0.10);
    --pt-rest-bg:        rgba(255, 255, 255, 0.07);
    --pt-hover-bg:       rgba(255, 255, 255, 0.16);
    /* complete dark set in design-tokens.md */
}
```

**Shape rules (ENFORCED):** every tab has **rounded top corners (10px)** and carries the resting tint.
The **active tab is filled** with the card surface plus a hairline border (no bottom border) and sits
**on** the strip's bottom rule - the rule visibly **breaks underneath it** (draw it as an inset
shadow/hairline so the fill can cover it), with small **concave fillets** at its bottom corners
sweeping into the rule. Inactive tabs darken one step on hover; the active tab never changes on hover.
Never signal the active tab by color alone - fill + border + attachment + weight carry it.

> **Dark theme:** every value above has an approved dark counterpart (the `[data-theme="dark"]` block);
> the complete set lives in `design-tokens.md`. Author against the tokens, never the light hex.

---

## 3. Anatomy & placement

One horizontal row at the **top of the work canvas**, below the breadcrumb and page title, above the
active facet's content.

```text
shell main (canvas)
 ├─ breadcrumb:  Home › ... › Security › Sign-in Methods › SSO / SAML
 ├─ page title:  SSO / SAML
 ├─ nav.pt-strip  (aria-label="SSO / SAML sections")
 │   ├─ Providers            <- active (filled with the card surface, breaking the rule)
 │   ├─ Attribute Mapping
 │   └─ Certificates (Soon)  <- disabled + Soon
 └─ .pt-panel  -  the active tab's route content (an archetype)
```

- **Strip** (`.pt-strip`): the row of curved tabs on a single inset hairline rule. A `<nav>` landmark
  when route-backed (§4).
- **Tab** (`.pt-tab`): a rounded-top, tinted shape holding a label (optionally a leading icon). Active
  fills with the card surface, gains the hairline border, and its label uses `--pt-active-fg`.
- **Panel** (`.pt-panel`): the content region below the strip - a standard archetype filled with the
  active facet's data. It owns its own loading / empty / error states
  ([empty-and-loading-state.md](empty-and-loading-state.md)); the strip itself does **not** skeleton.
- **Placement:** the breadcrumb ([breadcrumbs.md](breadcrumbs.md)) reflects the route path *including*
  the active tab, since each tab is its own route. One strip per page - never stack two tab rows.

---

## 4. Route-backed links vs the in-page widget

Both roles in §1 are **navigation**, so the **default** is route-backed links - honest, bookmarkable,
and free with the browser/router.

### 4.1 Route-backed tabs (DEFAULT)

Each tab is its **own URL**. Render the strip as a **`<nav>` landmark of `<a>` links**; mark the active
one with `aria-current="page"`. No JS is needed to switch - the link navigates and the router/server
renders the matching panel; active detection is `pathname === tab.href` (or `startsWith` for nested
routes), exactly like the sidebar nav item.

- Use real `<a href>` - keyboard-focusable, middle-click / open-in-new-tab work, no click-only `<div>`.
- Active tab: `aria-current="page"` + the filled active treatment (§2). Do **not** also set `role="tab"`
  - these are links, not the ARIA tab widget.
- The form to use for **depth-overflow** and for addressable **record facets**.

### 4.2 In-page tab widget (FALLBACK - PRINCIPLED)

Only when the panels are **genuinely in-page** and are **not** separate routes (no URL change, no
bookmark) use the ARIA tab widget:

- Container `role="tablist"`; each tab a `<button role="tab" aria-selected aria-controls>`; each panel
  `role="tabpanel" aria-labelledby` + `tabindex="0"`.
- **Roving tabindex** (active tab `tabindex="0"`, the rest `-1`); `←/→` move and activate, `Home`/`End`
  jump to first/last.
- Prefer 4.1 whenever the facet could reasonably be a route; reach for 4.2 only for transient in-page
  panels. **Never** mix the two on one strip.

### 4.3 Terminal (ENFORCED)

A tab panel holds **content** - a page archetype - **never** another tab strip and **never** a fresh
sidebar accordion. If a facet seems to need its own sub-tabs, the tree is mis-shaped: restructure it
(promote the facet to its own sidebar node, or flatten), don't nest tabs.

---

## 5. States - active, hover, Soon, overflow, panel states

- **Active** - the current route (`aria-current="page"`) or selected widget tab (`aria-selected="true"`):
  filled with the card surface plus a hairline border (no bottom border), sitting on the strip's rule
  (which visibly breaks beneath it) with concave bottom fillets sweeping the fill into the rule; label
  in `--pt-active-fg`, 600 weight. The active tab **never changes on hover**.
- **Resting** - muted label on the always-visible resting tint (`--pt-rest-bg`), rounded 10px top corners.
- **Hover / focus** - an inactive tab's tint darkens one step (`--pt-hover-bg`) and its label darkens to
  ink; `:focus-visible` shows the standard focus ring (`2px solid var(--color-primary)`,
  `outline-offset: 2px`; never `outline: none` without a replacement).
- **Soon (unbuilt facet)** - renders **disabled with a "Soon" pill** (`.pt-tab.is-soon`,
  `aria-disabled="true"`, removed from the tab order) - the same rule as unbuilt sidebar destinations.
- **Unauthorized facet** - **absent** (`render null`), never a disabled-looking dead tab.
- **Overflow** - when tabs exceed the width, the strip **scrolls horizontally** (no wrap to a second
  row, no truncation of the active tab). Keep the active tab scrolled into view. Constrain to
  **horizontal scroll only** (pin `overflow-y: hidden`) - an `overflow-x: auto` otherwise also makes
  the cross-axis `auto`, which can pop a stray vertical scrollbar / arrows.
- **Panel states** - loading / empty / error belong to the **panel**, not the strip: the strip stays
  live while the panel skeletons ([empty-and-loading-state.md](empty-and-loading-state.md)). Never blank
  the whole page on a tab switch.

---

## 6. Responsive (ENFORCED, mobile-first)

Design for the small screen first. On a narrow / portrait viewport the strip becomes a
**horizontal-scroll row** (touch-swipeable), the active tab is kept in view, and every tab is a
**≥ 44px** touch target. Use width and/or aspect-ratio breakpoints (see the [global rule](../SKILL.md)).
Never use JS for responsive layout.

```css
@media (max-aspect-ratio: 1/1) {
    .pt-tab { min-height: 44px; }   /* touch target; the gap stays var(--pt-gap) */
}
```

---

## 7. Full CSS reference (copy-paste ready)

`pt-`-namespaced, every variable defined in the §2 token block (both themes) - no raw hex in the rules.

```css
/* ===== Strip (the rule the tabs sit on) ===== */
.pt-strip {
    display: flex; align-items: stretch;         /* tabs reach the rule, no gap beneath them */
    gap: var(--pt-gap); margin: 0 0 var(--spacing-lg, 24px);
    padding: 0 var(--pt-fillet);                 /* room so an end tab's fillet is not clipped */
    box-shadow: inset 0 -1px 0 var(--pt-track);  /* the rule is an INSET hairline; the active fill breaks it */
    overflow-x: auto;                            /* small screens scroll, never wrap */
    overflow-y: hidden;                          /* horizontal-only: no stray vertical scrollbar/arrows */
    scrollbar-width: thin; -webkit-overflow-scrolling: touch;
}

/* ===== Tab (curved browser-tab shape) ===== */
.pt-tab {
    position: relative; display: inline-flex; align-items: center; gap: 8px;
    padding: var(--pt-pad);
    border: 1px solid transparent;               /* reserves the active hairline - no layout shift */
    border-bottom: none;                         /* the strip's rule plays the bottom edge */
    border-radius: var(--pt-radius);
    background: var(--pt-rest-bg);               /* resting tint - always visible */
    color: var(--color-text-muted);
    font-family: inherit; font-size: 13px; font-weight: 500; line-height: 1.5;
    text-decoration: none; white-space: nowrap; cursor: pointer;
}
.pt-tab:hover:not([aria-current="page"]):not([aria-selected="true"]) {
    background: var(--pt-hover-bg);              /* one step darker - the ACTIVE tab never changes */
    color: var(--color-text);
}
.pt-tab:focus-visible { outline: 2px solid var(--color-primary); outline-offset: 2px; }
.pt-tab .pt-icon { width: 16px; height: 16px; flex-shrink: 0; }

/* Active - route-backed (aria-current) OR widget (aria-selected) */
.pt-tab[aria-current="page"], .pt-tab[aria-selected="true"] {
    background: var(--color-surface);            /* filled with the card surface */
    border-color: var(--card-border);            /* hairline border - no bottom border */
    color: var(--pt-active-fg); font-weight: 600;
    z-index: 1;                                  /* fill + fillets sit over the rule */
}

/* Concave bottom fillets - the active tab flares into the rule it breaks */
.pt-tab[aria-current="page"]::before, .pt-tab[aria-selected="true"]::before,
.pt-tab[aria-current="page"]::after,  .pt-tab[aria-selected="true"]::after {
    content: ""; position: absolute; bottom: 0;
    width: var(--pt-fillet); height: var(--pt-fillet);
}
.pt-tab[aria-current="page"]::before, .pt-tab[aria-selected="true"]::before {
    left: calc(-1 * var(--pt-fillet));           /* left fillet: card fill outside a top-left circle */
    background: radial-gradient(circle at 0 0,
        transparent calc(var(--pt-fillet) - 0.5px), var(--color-surface) var(--pt-fillet));
}
.pt-tab[aria-current="page"]::after, .pt-tab[aria-selected="true"]::after {
    right: calc(-1 * var(--pt-fillet));          /* right fillet: card fill outside a top-right circle */
    background: radial-gradient(circle at 100% 0,
        transparent calc(var(--pt-fillet) - 0.5px), var(--color-surface) var(--pt-fillet));
}

/* ===== Soon (unbuilt facet) ===== */
.pt-tab.is-soon { opacity: 0.55; pointer-events: none; }
.pt-tab .pt-soon {
    font-size: 10px; font-weight: 600; padding: 1px 6px; border-radius: 999px;
    background: var(--badge-neutral-bg); color: var(--badge-neutral-fg);   /* neutral badge pair - design-tokens.md */
}

/* ===== Panel ===== */
.pt-panel { /* fill with the active facet's archetype; owns its own loading/empty/error states */ }
```

---

## 8. Full HTML & JS reference

### 8.1 Route-backed tabs (DEFAULT, §4.1) - no switching JS

The link navigates; the router/server renders the panel and sets `aria-current`. Active detection
mirrors the sidebar nav item.

```html
<nav class="pt-strip" aria-label="SSO / SAML sections">
  <a class="pt-tab" href="/admin/security/sign-in/sso/providers" aria-current="page">Providers</a>
  <a class="pt-tab" href="/admin/security/sign-in/sso/mapping">Attribute Mapping</a>
  <!-- unbuilt facet: disabled + Soon, out of the tab order -->
  <a class="pt-tab is-soon" aria-disabled="true" tabindex="-1">Certificates <span class="pt-soon">Soon</span></a>
</nav>
<section class="pt-panel" aria-label="Providers settings"><!-- active route's archetype --></section>
```

```js
/* Active detection (same contract as the sidebar nav item). */
function markActiveTabs(strip, pathname) {
    strip.querySelectorAll('.pt-tab[href]').forEach((a) => {
        const href = a.getAttribute('href');
        const active = pathname === href || pathname.startsWith(href + '/');
        if (active) a.setAttribute('aria-current', 'page');
        else a.removeAttribute('aria-current');
    });
}
```

### 8.2 In-page widget (FALLBACK, §4.2) - roving tabindex + arrows

```html
<div class="pt-strip" role="tablist" aria-label="Overview sections">
  <button class="pt-tab" role="tab" id="t-a" aria-selected="true"  aria-controls="p-a" tabindex="0">Summary</button>
  <button class="pt-tab" role="tab" id="t-b" aria-selected="false" aria-controls="p-b" tabindex="-1">Details</button>
</div>
<div class="pt-panel" role="tabpanel" id="p-a" aria-labelledby="t-a" tabindex="0"><!-- ... --></div>
<div class="pt-panel" role="tabpanel" id="p-b" aria-labelledby="t-b" tabindex="0" hidden><!-- ... --></div>
```

```js
/* Use ONLY for genuine in-page panels (no route). Prefer 8.1 otherwise. */
function initTabWidget(tablist) {
    const tabs = [...tablist.querySelectorAll('[role="tab"]')];
    const select = (tab) => {
        tabs.forEach((t) => {
            const on = t === tab;
            t.setAttribute('aria-selected', String(on));
            t.tabIndex = on ? 0 : -1;
            const panel = document.getElementById(t.getAttribute('aria-controls'));
            if (panel) panel.hidden = !on;
        });
        tab.focus();
    };
    tablist.addEventListener('click', (e) => { const t = e.target.closest('[role="tab"]'); if (t) select(t); });
    tablist.addEventListener('keydown', (e) => {
        const i = tabs.indexOf(document.activeElement);
        if (i < 0) return;
        if (e.key === 'ArrowRight') { e.preventDefault(); select(tabs[(i + 1) % tabs.length]); }
        else if (e.key === 'ArrowLeft') { e.preventDefault(); select(tabs[(i - 1 + tabs.length) % tabs.length]); }
        else if (e.key === 'Home') { e.preventDefault(); select(tabs[0]); }
        else if (e.key === 'End') { e.preventDefault(); select(tabs[tabs.length - 1]); }
    });
}
```

---

## 9. Accessibility checklist

- [ ] **Route-backed (default):** the strip is a `<nav aria-label="...">` of real `<a href>` links; the
      active tab carries `aria-current="page"`; no `role="tab"` on links.
- [ ] **Widget (fallback only):** `role="tablist"` / `role="tab"` (`aria-selected`, `aria-controls`) /
      `role="tabpanel"` (`aria-labelledby`, `tabindex="0"`); roving tabindex; `←/→`/`Home`/`End` work.
- [ ] Active tab is signaled by the **card-surface fill + hairline border + weight**, never color alone.
- [ ] Visible `:focus-visible` ring on every tab (never `outline: none` without a replacement).
- [ ] Leading icons are `aria-hidden="true"`; the label carries the meaning.
- [ ] "Soon" facet: `aria-disabled="true"`, removed from the tab order (`tabindex="-1"` / not focusable),
      shows the visible "Soon" pill (not color alone).
- [ ] Unauthorized facet is **absent**, not a disabled dead tab.
- [ ] On a tab switch the breadcrumb/title stay rendered; the panel shows its own skeleton - no blank
      page, no focus theft.
- [ ] Strip scrolls horizontally on small screens; touch targets ≥ 44px; the active tab stays in view.

---

## 10. Do's and Don'ts

| ✅ Do | ❌ Don't | Why |
|---|---|---|
| Use a tab strip only for **depth-overflow** or **record facets** | Use tabs for a list filter | A filter reads as a filter - search-filter.md §4 |
| Make each tab a **route** (`<nav>` of links + `aria-current`) | Build a click-only `role="tablist"` for real routes | Honest, bookmarkable, keyboard-correct (§4) |
| Keep navigation depth in the **sidebar** up to 3 levels | Move standing navigation to the top bar / replace the sidebar | Standing nav lives in the sidebar (topbar-sidenav.md) |
| Keep tabs **terminal** - a panel holds content | Nest a tab strip inside a tab panel, or re-open an accordion | Sub-tabs mean the tree is wrong - restructure (§4.3) |
| Mark active with the **card-surface fill + border + weight** | Signal the active tab by color alone | Fails contrast / color-blind users (§2) |
| Render unbuilt facets **disabled + "Soon"** | Render an unauthorized facet as a disabled tab | Unauthorized is absent; unbuilt is "Soon" (§5) |
| Let the **panel** own loading / empty / error | Blank the whole page on a tab switch | Keep orientation; skeleton the panel only (§5) |
| Scroll the strip horizontally on small screens | Wrap tabs onto a second row or truncate the active tab | One quiet row; mobile-first (§6) |

---

## 11. Rules for the AI assistant

When generating an in-canvas tab strip, the assistant **must**:

1. **Confirm the role (§1).** Only build a tab strip for **depth-overflow** (a leaf under a level-3
   accordion group that itself needs children) or **record/page facets**. For a list filter, use
   [search-filter.md](search-filter.md) §4; never move standing nav to the top bar.
2. **Default to route-backed links (§4.1):** a `<nav aria-label>` of `<a href>` links with
   `aria-current="page"` on the active tab, breadcrumb reflecting the route. Use the `role="tablist"`
   widget (§4.2) **only** for genuine in-page panels that are not routes - never mix the two.
3. **Keep tabs terminal (§4.3):** a panel holds a page archetype, never another tab strip and never a
   sidebar accordion. If sub-tabs seem necessary, flag that the tree should be restructured.
4. **Cover the states (§5):** active, hover/focus, "Soon" for unbuilt facets, absent for unauthorized,
   horizontal-scroll overflow, and panel-owned loading / empty / error.
5. **Wire accessibility (§9):** links + `aria-current` (default) or the full ARIA tab widget (fallback),
   visible focus ring, `aria-hidden` icons, no color-only active signal, no focus theft on switch.
6. **Use the design tokens (§2)** - never hardcode a hex; the active label uses `--color-primary` (never
   redefine `--color-accent`, which is Green Gold system-wide); every token resolves in light and dark;
   responsive is mobile-first.

---

## 12. Quick decision guide

```
Is this switching standing navigation, narrowing a list, or switching facets/depth?
│
├─ Standing navigation ─────────▶ Sidebar accordion (≤3 levels). Never a tab strip. topbar-sidenav.md
├─ Narrowing a list's rows ─────▶ Filter tabs / chips / popover. search-filter.md §4   (NOT this file)
└─ Depth-overflow OR facets ────▶ THIS tab strip:
     Is each tab its own URL / bookmarkable?
     ├─ Yes (almost always) ──▶ <nav> of <a> links + aria-current="page"   (§4.1, DEFAULT)
     └─ No (in-page panel) ───▶ role="tablist" widget + roving tabindex     (§4.2, fallback)

   Always: top of canvas under breadcrumb/title · curved tabs, active filled with the card
           surface (not color alone) · terminal (no sub-tabs) · Soon for unbuilt ·
           panel owns loading/empty/error · scrolls horizontally on small screens ·
           design tokens · light + dark.
```

---
